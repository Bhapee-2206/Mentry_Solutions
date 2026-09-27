<?php
// includes/db.php - Direct Supabase Cloud Database Connector & Document Engine
// Architecture: Direct Database-Side Filtering, Atomic Concurrency & Fail-Closed Protection

if (file_exists(__DIR__ . '/mongo_polyfill.php')) {
    require_once __DIR__ . '/mongo_polyfill.php';
}

/**
 * Dedicated Database Exception for Fail-Closed Outage Handling
 */
class DatabaseException extends \RuntimeException {}

/**
 * SafeCursor implements IteratorAggregate and Countable for smooth iteration across all query results.
 */
class SafeCursor implements IteratorAggregate, Countable {
    private $cursor;
    private $fallback;

    public function __construct($cursor = null, array $fallback = []) {
        $this->cursor = $cursor;
        $this->fallback = $fallback;
    }

    public function toArray(): array {
        if ($this->cursor === null) {
            return $this->fallback;
        }
        try {
            if (is_array($this->cursor)) {
                return $this->cursor;
            }
            if (method_exists($this->cursor, 'toArray')) {
                return $this->cursor->toArray();
            }
            return iterator_to_array($this->cursor);
        } catch (\Throwable $e) {
            error_log("SafeCursor toArray Error: " . $e->getMessage());
            return $this->fallback;
        }
    }

    public function count(): int {
        return count($this->toArray());
    }

    public function getIterator(): Traversable {
        try {
            if ($this->cursor instanceof Traversable) {
                return $this->cursor;
            }
            if (is_array($this->cursor)) {
                return new ArrayIterator($this->cursor);
            }
        } catch (\Throwable $e) {
            error_log("SafeCursor getIterator Error: " . $e->getMessage());
        }
        return new ArrayIterator($this->fallback);
    }
}

/**
 * Direct Supabase Document Store.
 * All queries, counts, insertions, updates, and deletions execute database-side
 * against Supabase PostgreSQL (public.mentry_documents).
 * Operates in Fail-Closed mode in production with zero reliance on JSON collections.
 */
class PersistentDocumentStore {
    private string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }

    private static array $memoryCache = [];

    public static function invalidateCache(?string $name = null): void {
        if ($name === null) {
            self::$memoryCache = [];
        } else {
            unset(self::$memoryCache[$name]);
        }
    }

    /**
     * Reads Supabase credentials from environment or .env file securely.
     */
    public static function getSupabaseCredentials(): array {
        static $cached = null;
        if ($cached !== null) return $cached;

        $url = getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ($_SERVER['SUPABASE_URL'] ?? ''));
        $key = getenv('SUPABASE_KEY') ?: ($_ENV['SUPABASE_KEY'] ?? ($_SERVER['SUPABASE_KEY'] ?? (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '')));

        if (empty($url) || empty($key)) {
            $envPath = __DIR__ . '/../.env';
            if (file_exists($envPath)) {
                $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines !== false) {
                    foreach ($lines as $l) {
                        $l = trim($l);
                        if (empty($l) || $l[0] === '#') continue;
                        if (strpos($l, '=') !== false) {
                            list($k, $v) = explode('=', $l, 2);
                            $k = trim($k);
                            $v = trim(trim($v), '"\'');
                            if (($k === 'SUPABASE_URL' || $k === 'NEXT_PUBLIC_SUPABASE_URL') && empty($url)) $url = $v;
                            if (($k === 'SUPABASE_KEY' || $k === 'SUPABASE_SERVICE_ROLE_KEY') && empty($key)) $key = $v;
                        }
                    }
                }
            }
        }

        if (empty($url) || empty($key)) {
            if (function_exists('logAppError')) {
                logAppError('supabase_credentials_missing', new \RuntimeException("Supabase credentials not configured in environment or .env"));
            } else {
                error_log("CRITICAL: Supabase credentials (SUPABASE_URL / SUPABASE_KEY) are not configured.");
            }
        }

        $cached = ['url' => rtrim($url ?? '', '/'), 'key' => $key ?? ''];
        return $cached;
    }

    /**
     * Central PostgREST HTTP executor with connection reuse and error logging.
     */
    public static function executePostgrest(string $method, string $path, array $queryParams = [], $body = null, array $extraHeaders = []): array {
        $sb = self::getSupabaseCredentials();
        if (empty($sb['url']) || empty($sb['key'])) {
            return ['code' => 0, 'headers' => '', 'body' => '', 'data' => null, 'count' => null, 'error' => 'Missing Supabase credentials'];
        }

        $url = $sb['url'] . '/rest/v1/' . ltrim($path, '/');
        if (!empty($queryParams)) {
            $queryString = http_build_query($queryParams);
            // PostgREST requires unescaped parens, commas, asterisks, and colons in filters
            $queryString = str_replace(['%28', '%29', '%2C', '%2A', '%3A'], ['(', ')', ',', '*', ':'], $queryString);
            $url .= (strpos($url, '?') === false ? '?' : '&') . $queryString;
        }

        $ch = curl_init($url);
        $headers = [
            'apikey: ' . $sb['key'],
            'Authorization: Bearer ' . $sb['key'],
            'Content-Type: application/json'
        ];
        foreach ($extraHeaders as $h) {
            $headers[] = $h;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if (file_exists(__DIR__ . '/cacert.pem')) {
            curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
            }
        } elseif ($method === 'PATCH' || $method === 'DELETE' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
            }
        }

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            if (function_exists('logAppError')) {
                logAppError('postgrest_curl_failure', new \RuntimeException($err ?: "cURL request failed"), ['method' => $method, 'url' => $url]);
            }
            return ['code' => 0, 'headers' => '', 'body' => '', 'data' => null, 'count' => null, 'error' => $err];
        }

        $headerStr = substr($raw, 0, $headerSize);
        $bodyStr = substr($raw, $headerSize);
        $data = json_decode($bodyStr, true);

        // Extract total count from Content-Range header (e.g. Content-Range: 0-9/142 or */142)
        $count = null;
        if (preg_match('/content-range:\s*([^\r\n]+)/i', $headerStr, $m)) {
            $cr = trim($m[1]);
            if (strpos($cr, '/') !== false) {
                $parts = explode('/', $cr);
                $total = trim($parts[1]);
                if (is_numeric($total)) {
                    $count = (int)$total;
                }
            }
        }

        return [
            'code' => $code,
            'headers' => $headerStr,
            'body' => $bodyStr,
            'data' => $data,
            'count' => $count,
            'error' => null
        ];
    }

    /**
     * Executes batch PATCH requests concurrently using curl_multi.
     * Dramatically accelerates bulk batch operations while preserving OCC guarantees.
     */
    public static function executeMultiPatch(array $requests): array {
        if (empty($requests)) return [];
        $sb = self::getSupabaseCredentials();
        if (empty($sb['url']) || empty($sb['key'])) return [];

        $baseUrl = $sb['url'] . '/rest/v1/';
        $headers = [
            'apikey: ' . $sb['key'],
            'Authorization: Bearer ' . $sb['key'],
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];

        $results = [];
        $chunks = array_chunk($requests, 25, true);

        foreach ($chunks as $chunk) {
            $mh = curl_multi_init();
            $handles = [];

            foreach ($chunk as $idx => $req) {
                $ch = curl_init($baseUrl . ltrim($req['path'], '/'));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req['payload']));
                curl_setopt($ch, CURLOPT_TIMEOUT, 25);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_TCP_NODELAY, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                if (file_exists(__DIR__ . '/cacert.pem')) {
                    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');
                }
                curl_multi_add_handle($mh, $ch);
                $handles[$idx] = $ch;
            }

            $active = null;
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh, 0.05);
                }
            } while ($active && $status == CURLM_OK);

            foreach ($handles as $idx => $ch) {
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $body = curl_multi_getcontent($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                $data = json_decode($body, true);
                $results[$idx] = [
                    'code' => $code,
                    'data' => is_array($data) ? $data : []
                ];
            }

            curl_multi_close($mh);
        }

        return $results;
    }

    /**
     * Translates MongoDB query filters to PostgREST query parameters.
     */
    public static function buildFilterParams(string $collection, array $filter): array {
        $params = ['collection' => 'eq.' . $collection];
        
        foreach ($filter as $key => $val) {
            if ($key === '_id' || $key === 'id') {
                if (is_array($val)) {
                    if (isset($val['$in']) && is_array($val['$in'])) {
                        $ids = array_map(function($v) {
                            return is_object($v) ? (string)$v : (is_array($v) ? ($v['$oid'] ?? '') : (string)$v);
                        }, $val['$in']);
                        $params['id'] = 'in.(' . implode(',', $ids) . ')';
                    } elseif (isset($val['$gt'])) {
                        $params['id'] = 'gt.' . (string)$val['$gt'];
                    } elseif (isset($val['$gte'])) {
                        $params['id'] = 'gte.' . (string)$val['$gte'];
                    } elseif (isset($val['$lt'])) {
                        $params['id'] = 'lt.' . (string)$val['$lt'];
                    } elseif (isset($val['$lte'])) {
                        $params['id'] = 'lte.' . (string)$val['$lte'];
                    } elseif (isset($val['$ne'])) {
                        $params['id'] = 'neq.' . (string)$val['$ne'];
                    }
                } else {
                    $idStr = is_object($val) ? (string)$val : (is_array($val) ? ($val['$oid'] ?? '') : (string)$val);
                    $params['id'] = 'eq.' . $idStr;
                }
                continue;
            }

            if ($key === '$or' && is_array($val)) {
                $orParts = [];
                foreach ($val as $cond) {
                    if (is_array($cond)) {
                        foreach ($cond as $ck => $cv) {
                            $part = self::buildConditionString($ck, $cv);
                            if ($part !== null) {
                                $orParts[] = $part;
                            }
                        }
                    }
                }
                if (!empty($orParts)) {
                    $params['or'] = '(' . implode(',', $orParts) . ')';
                }
                continue;
            }

            if ($key === '$and' && is_array($val)) {
                $andParts = [];
                foreach ($val as $cond) {
                    if (is_array($cond)) {
                        foreach ($cond as $ck => $cv) {
                            if ($ck === '$or' && is_array($cv)) {
                                $subOr = [];
                                foreach ($cv as $orCond) {
                                    foreach ($orCond as $ock => $ocv) {
                                        $p = self::buildConditionString($ock, $ocv);
                                        if ($p) $subOr[] = $p;
                                    }
                                }
                                if (!empty($subOr)) {
                                    $andParts[] = 'or(' . implode(',', $subOr) . ')';
                                }
                            } else {
                                $p = self::buildConditionString($ck, $cv);
                                if ($p) $andParts[] = $p;
                            }
                        }
                    }
                }
                if (!empty($andParts)) {
                    $params['and'] = '(' . implode(',', $andParts) . ')';
                }
                continue;
            }

            // Normal JSONB field condition
            $targetCol = (strpos($key, 'data->') === 0) ? $key : ('data->>' . $key);
            if (is_null($val)) {
                $params[$targetCol] = 'is.null';
            } elseif (is_bool($val)) {
                $params[$targetCol] = $val ? 'eq.true' : 'eq.false';
            } elseif (is_scalar($val)) {
                $params[$targetCol] = 'eq.' . (string)$val;
            } elseif (is_object($val) && method_exists($val, 'getPattern')) {
                $params[$targetCol] = 'ilike.*' . $val->getPattern() . '*';
            } elseif (is_array($val)) {
                if (isset($val['$in']) && is_array($val['$in'])) {
                    $strVals = array_map(function($v) {
                        return is_object($v) ? (string)$v : (string)$v;
                    }, $val['$in']);
                    $params[$targetCol] = 'in.(' . implode(',', $strVals) . ')';
                } elseif (isset($val['$nin']) && is_array($val['$nin'])) {
                    $strVals = array_map('strval', $val['$nin']);
                    $params[$targetCol] = 'not.in.(' . implode(',', $strVals) . ')';
                } elseif (isset($val['$ne'])) {
                    $params[$targetCol] = 'neq.' . (string)$val['$ne'];
                } elseif (isset($val['$gt'])) {
                    $params[$targetCol] = 'gt.' . (string)$val['$gt'];
                } elseif (isset($val['$gte'])) {
                    $params[$targetCol] = 'gte.' . (string)$val['$gte'];
                } elseif (isset($val['$lt'])) {
                    $params[$targetCol] = 'lt.' . (string)$val['$lt'];
                } elseif (isset($val['$lte'])) {
                    $params[$targetCol] = 'lte.' . (string)$val['$lte'];
                } elseif (isset($val['$exists'])) {
                    $params[$targetCol] = $val['$exists'] ? 'not.is.null' : 'is.null';
                }
            }
        }

        return $params;
    }

    private static function buildConditionString(string $key, $val): ?string {
        if ($key === '_id' || $key === 'id') {
            if (is_array($val)) {
                if (isset($val['$in']) && is_array($val['$in'])) {
                    $items = implode(',', array_map(function($v) {
                        return is_object($v) ? (string)$v : (string)$v;
                    }, $val['$in']));
                    return "id.in.({$items})";
                }
                if (isset($val['$gt'])) return "id.gt.{$val['$gt']}";
                if (isset($val['$gte'])) return "id.gte.{$val['$gte']}";
                if (isset($val['$lt'])) return "id.lt.{$val['$lt']}";
                if (isset($val['$lte'])) return "id.lte.{$val['$lte']}";
                if (isset($val['$ne'])) return "id.neq.{$val['$ne']}";
                return null;
            }
            $valStr = is_object($val) ? (string)$val : (string)$val;
            return "id.eq.{$valStr}";
        }

        $prefix = (strpos($key, 'data->') === 0) ? $key : ('data->>' . $key);

        if (is_null($val)) {
            return "{$prefix}.is.null";
        }
        if (is_bool($val)) {
            return $val ? "{$prefix}.eq.true" : "{$prefix}.eq.false";
        }
        if (is_scalar($val)) {
            return "{$prefix}.eq.{$val}";
        }
        if (is_array($val)) {
            if (isset($val['$exists'])) {
                return $val['$exists'] ? "{$prefix}.not.is.null" : "{$prefix}.is.null";
            }
            if (isset($val['$in']) && is_array($val['$in'])) {
                $items = implode(',', array_map('strval', $val['$in']));
                return "{$prefix}.in.({$items})";
            }
            if (isset($val['$ne'])) {
                return "{$prefix}.neq.{$val['$ne']}";
            }
        }
        return null;
    }

    private function wrapBsonTypes(array $doc): array {
        if (isset($doc['_id']) && is_string($doc['_id']) && preg_match('/^[a-f0-9]{24}$/i', $doc['_id'])) {
            try {
                $doc['_id'] = new MongoDB\BSON\ObjectId($doc['_id']);
            } catch (\Throwable $e) {}
        }
        foreach (['createdAt', 'updatedAt'] as $dtField) {
            if (isset($doc[$dtField])) {
                try {
                    if ($doc[$dtField] instanceof MongoDB\BSON\UTCDateTime) {
                        continue;
                    }
                    if (is_numeric($doc[$dtField])) {
                        $doc[$dtField] = new MongoDB\BSON\UTCDateTime((int)$doc[$dtField]);
                    } elseif (is_string($doc[$dtField])) {
                        $parsed = strtotime($doc[$dtField]);
                        $ms = ($parsed !== false) ? $parsed * 1000 : (int)$doc[$dtField];
                        $doc[$dtField] = new MongoDB\BSON\UTCDateTime($ms);
                    }
                } catch (\Throwable $e) {}
            }
        }
        return $doc;
    }

    private function unwrapBsonTypes(array $doc): array {
        foreach ($doc as $k => $v) {
            if ($v instanceof MongoDB\BSON\ObjectId) {
                $doc[$k] = (string)$v;
            } elseif ($v instanceof MongoDB\BSON\UTCDateTime) {
                $doc[$k] = (string)$v;
            } elseif (is_object($v) && method_exists($v, '__toString')) {
                $doc[$k] = (string)$v;
            }
        }
        return $doc;
    }

    /**
     * Executes database-side filtered query via PostgREST.
     * Only transfers matched rows from Supabase PostgreSQL.
     * In Production: Fails closed. Never falls back to reading local JSON files.
     */
    public function find(array $filter = [], array $options = []): SafeCursor {
        $params = self::buildFilterParams($this->name, $filter);
        $params['select'] = 'id,data';

        // Database-side sorting
        if (!empty($options['sort']) && is_array($options['sort'])) {
            $orderParts = [];
            foreach ($options['sort'] as $sortKey => $sortDir) {
                $dir = ($sortDir < 0) ? 'desc' : 'asc';
                if ($sortKey === '_id' || $sortKey === 'id') {
                    $orderParts[] = "id.{$dir}";
                } else {
                    $orderParts[] = "data->>{$sortKey}.{$dir}";
                }
            }
            if (!empty($orderParts)) {
                $params['order'] = implode(',', $orderParts);
            }
        }

        // Database-side limit and skip
        if (!empty($options['limit']) && is_numeric($options['limit'])) {
            $params['limit'] = (int)$options['limit'];
        }
        if (!empty($options['skip']) && is_numeric($options['skip'])) {
            $params['offset'] = (int)$options['skip'];
        }

        $res = self::executePostgrest('GET', 'mentry_documents', $params);
        if ($res['code'] >= 200 && $res['code'] < 300 && is_array($res['data'])) {
            $docs = [];
            foreach ($res['data'] as $row) {
                if (isset($row['data']) && is_array($row['data'])) {
                    $d = $row['data'];
                    if (empty($d['_id']) && !empty($row['id'])) {
                        $d['_id'] = $row['id'];
                    }
                    $docs[] = $this->wrapBsonTypes($d);
                }
            }
            return new SafeCursor($docs, $docs);
        }

        // FAIL CLOSED: Database error occurred
        $errMsg = "Database query failed for collection '{$this->name}' [HTTP {$res['code']}]: " . ($res['error'] ?: substr((string)$res['body'], 0, 100));
        if (function_exists('logAppError')) {
            logAppError('db_find_failure', new DatabaseException($errMsg), ['collection' => $this->name, 'code' => $res['code']]);
        }

        // In Production: Never expose stale data from JSON files. Throw exception to fail closed.
        if (!function_exists('isDevelopment') || !isDevelopment()) {
            throw new DatabaseException("Database service temporarily unavailable. Please retry shortly.");
        }

        // Development-only warning fallback (strictly blocked in production)
        error_log("DEV NOTICE: Database query failed, development environment active.");
        return new SafeCursor([], []);
    }

    /**
     * Retrieves exactly 1 document with LIMIT 1 applied database-side.
     */
    public function findOne(array $filter = [], array $options = []): ?array {
        $options['limit'] = 1;
        $cursor = $this->find($filter, $options);
        $arr = $cursor->toArray();
        return !empty($arr) ? $arr[0] : null;
    }

    /**
     * Executes database-side count query with 0 rows transferred over the network.
     * In Production: Fails closed. Never calculates counts from stale JSON files or reports false 0.
     */
    public function countDocuments(array $filter = []): int {
        $params = self::buildFilterParams($this->name, $filter);
        $params['limit'] = 0;
        $res = self::executePostgrest('GET', 'mentry_documents', $params, null, ['Prefer: count=exact']);
        
        if ($res['code'] >= 200 && $res['code'] < 300 && $res['count'] !== null) {
            return (int)$res['count'];
        }

        $errMsg = "Database count query failed for collection '{$this->name}' [HTTP {$res['code']}]: " . ($res['error'] ?: substr((string)$res['body'], 0, 100));
        if (function_exists('logAppError')) {
            logAppError('db_count_failure', new DatabaseException($errMsg), ['collection' => $this->name, 'code' => $res['code']]);
        }

        // Fail closed: Do NOT return a fake 0 or count local files
        if (!function_exists('isDevelopment') || !isDevelopment()) {
            throw new DatabaseException("Database count unavailable. Service temporarily unreachable.");
        }

        return 0;
    }

    public function count(array $filter = []): int {
        return $this->countDocuments($filter);
    }

    public function estimatedDocumentCount(): int {
        $params = ['collection' => 'eq.' . $this->name, 'limit' => 0];
        $res = self::executePostgrest('GET', 'mentry_documents', $params, null, ['Prefer: count=exact']);
        
        if ($res['code'] >= 200 && $res['code'] < 300 && $res['count'] !== null) {
            return (int)$res['count'];
        }

        $errMsg = "Database estimated count failed for collection '{$this->name}' [HTTP {$res['code']}]: " . ($res['error'] ?: substr((string)$res['body'], 0, 100));
        if (function_exists('logAppError')) {
            logAppError('db_estimated_count_failure', new DatabaseException($errMsg), ['collection' => $this->name, 'code' => $res['code']]);
        }

        if (!function_exists('isDevelopment') || !isDevelopment()) {
            throw new DatabaseException("Database statistics temporarily unavailable.");
        }

        return 0;
    }

    /**
     * Strict insertOne semantics.
     * Inserts a single document. Does NOT merge or overwrite an existing document.
     * Returns 201 on success (insertedCount = 1).
     * Returns 409 Conflict if record already exists (insertedCount = 0, no overwrite).
     */
    public function insertOne(array $doc) {
        if (empty($doc['_id'])) {
            $idObj = new MongoDB\BSON\ObjectId();
            $doc['_id'] = (string)$idObj;
        } else {
            $doc['_id'] = (string)$doc['_id'];
            if (preg_match('/^[a-f0-9]{24}$/i', $doc['_id'])) {
                try {
                    $idObj = new MongoDB\BSON\ObjectId($doc['_id']);
                } catch (\Throwable $e) {
                    $idObj = $doc['_id'];
                }
            } else {
                $idObj = $doc['_id'];
            }
        }

        if (empty($doc['createdAt'])) {
            $doc['createdAt'] = (string)new MongoDB\BSON\UTCDateTime();
        }
        if (empty($doc['updatedAt'])) {
            $doc['updatedAt'] = (string)new MongoDB\BSON\UTCDateTime();
        }
        if (!isset($doc['_version'])) {
            $doc['_version'] = 1;
        }

        $cleaned = $this->unwrapBsonTypes($doc);
        $docId = (string)$cleaned['_id'];

        $payload = [
            'collection' => $this->name,
            'id' => $docId,
            'data' => $cleaned,
            'updated_at' => date('c')
        ];

        // Strict INSERT: NO on_conflict, NO merge-duplicates
        $res = self::executePostgrest('POST', 'mentry_documents', [], $payload, [
            'Prefer: return=representation'
        ]);

        $inserted = ($res['code'] === 201 || ($res['code'] >= 200 && $res['code'] < 300));
        $isDuplicate = ($res['code'] === 409);

        if (!$inserted && !$isDuplicate) {
            $errMsg = "Database insert failed for collection '{$this->name}' ID '{$docId}' [HTTP {$res['code']}]: " . ($res['error'] ?: substr((string)$res['body'], 0, 100));
            if (function_exists('logAppError')) {
                logAppError('supabase_insert_failed', new DatabaseException($errMsg), [
                    'collection' => $this->name,
                    'id' => $docId,
                    'code' => $res['code']
                ]);
            }
            if ($res['code'] === 0 || $res['code'] >= 500) {
                throw new DatabaseException("Database unavailable while saving record.");
            }
        }

        return new class($idObj, $inserted, $isDuplicate) {
            private $id;
            private bool $inserted;
            private bool $duplicate;
            public function __construct($id, bool $inserted, bool $duplicate) {
                $this->id = $id;
                $this->inserted = $inserted;
                $this->duplicate = $duplicate;
            }
            public function getInsertedId() { return $this->inserted ? $this->id : null; }
            public function getInsertedCount() { return $this->inserted ? 1 : 0; }
            public function isAcknowledged() { return $this->inserted; }
            public function isDuplicate() { return $this->duplicate; }
        };
    }

    /**
     * Updates a single document with Optimistic Concurrency Control (OCC) and lost-update defense.
     * If document is concurrently modified by another process, retries with fresh state.
     * Prevents silent lost updates across distributed/serverless instances.
     */
    public function updateOne(array $filter, array $update, array $options = []) {
        $maxAttempts = $options['maxAttempts'] ?? 50;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $existing = $this->findOne($filter);
            if (!$existing) {
                if (!empty($options['upsert'])) {
                    $newDoc = $filter;
                    if (isset($update['$set'])) {
                        $newDoc = array_merge($newDoc, $update['$set']);
                    }
                    if (isset($update['$inc'])) {
                        foreach ($update['$inc'] as $k => $v) {
                            $newDoc[$k] = $v;
                        }
                    }
                    $insRes = $this->insertOne($newDoc);
                    return new class($insRes->getInsertedCount(), 1) {
                        private $m; private $mat;
                        public function __construct($m, $mat) { $this->m = $m; $this->mat = $mat; }
                        public function getModifiedCount() { return $this->m; }
                        public function getMatchedCount() { return $this->mat; }
                        public function getUpsertedId() { return null; }
                    };
                }
                return new class(0, 0) {
                    public function getModifiedCount() { return 0; }
                    public function getMatchedCount() { return 0; }
                    public function getUpsertedId() { return null; }
                };
            }

            $unwrapped = $this->unwrapBsonTypes($existing);
            $docId = (string)($unwrapped['_id'] ?? ($unwrapped['id'] ?? ''));
            if (empty($docId)) {
                return new class(0, 1) {
                    public function getModifiedCount() { return 0; }
                    public function getMatchedCount() { return 1; }
                    public function getUpsertedId() { return null; }
                };
            }

            $currVersion = (int)($unwrapped['_version'] ?? 0);
            $nextVersion = $currVersion + 1;

            if (isset($update['$set']) && is_array($update['$set'])) {
                foreach ($update['$set'] as $k => $v) {
                    $unwrapped[$k] = (is_object($v) && method_exists($v, '__toString')) ? (string)$v : $v;
                }
            }
            if (isset($update['$inc']) && is_array($update['$inc'])) {
                foreach ($update['$inc'] as $k => $v) {
                    $unwrapped[$k] = ($unwrapped[$k] ?? 0) + $v;
                }
            }
            if (isset($update['$unset']) && is_array($update['$unset'])) {
                foreach ($update['$unset'] as $k => $v) {
                    unset($unwrapped[$k]);
                }
            }
            $unwrapped['_version'] = $nextVersion;
            $unwrapped['updatedAt'] = (string)new MongoDB\BSON\UTCDateTime();

            // Optimistic condition: only update if document version matches what we read
            $occParam = ($currVersion > 0)
                ? ('data->>_version=eq.' . $currVersion)
                : 'or=(data->>_version.is.null,data->>_version.eq.0)';

            // For $inc operations: also condition on exact previous field value for strict serializability
            if (isset($update['$inc']) && is_array($update['$inc'])) {
                foreach ($update['$inc'] as $incField => $incVal) {
                    $currFieldVal = $existing[$incField] ?? 0;
                    $occParam .= '&data->>' . $incField . '=eq.' . $currFieldVal;
                }
            }

            $patchUrl = 'mentry_documents?collection=eq.' . urlencode($this->name) . '&id=eq.' . urlencode($docId) . '&' . $occParam;
            $payload = [
                'data' => $unwrapped,
                'updated_at' => date('c')
            ];

            $res = self::executePostgrest('PATCH', $patchUrl, [], $payload, [
                'Prefer: return=representation'
            ]);

            if ($res['code'] >= 200 && $res['code'] < 300) {
                $rows = is_array($res['data']) ? $res['data'] : [];
                if (count($rows) === 1) {
                    // Update committed atomically without collision
                    return new class(1, 1) {
                        public function getModifiedCount() { return 1; }
                        public function getMatchedCount() { return 1; }
                        public function getUpsertedId() { return null; }
                    };
                }
            }

            // Conflict detected: Another instance modified this record concurrently.
            // Back off with full jitter and retry.
            usleep(random_int(10000, 40000) + (min($attempt, 10) * 5000));
        }

        // Exhausted retries without acquiring clean version
        if (function_exists('logAppError')) {
            logAppError('update_occ_conflict_limit', new DatabaseException("Failed to commit updateOne on '{$this->name}' ID '{$docId}' after {$maxAttempts} OCC attempts"), ['collection' => $this->name, 'id' => $docId]);
        }

        return new class(0, 1) {
            public function getModifiedCount() { return 0; }
            public function getMatchedCount() { return 1; }
            public function getUpsertedId() { return null; }
        };
    }

    /**
     * Updates all matching documents using bounded keyset-paginated batches.
     * Keeps memory bounded (max 100 rows per batch) while updating 100% of matching records.
     * Uses stable id-ordered keyset pagination (WHERE id > lastId ORDER BY id ASC LIMIT 100)
     * so that filter-changing and filter-preserving updates never skip records.
     */
    public function updateMany(array $filter, array $update, array $options = []) {
        $batchSize = $options['batchSize'] ?? 100;
        $totalMatched = 0;
        $totalModified = 0;
        $lastId = null;

        while (true) {
            $batchFilter = $filter;
            if ($lastId !== null) {
                if (isset($batchFilter['_id'])) {
                    if (is_array($batchFilter['_id'])) {
                        $batchFilter['_id']['$gt'] = $lastId;
                    } else {
                        break;
                    }
                } elseif (isset($batchFilter['id'])) {
                    if (is_array($batchFilter['id'])) {
                        $batchFilter['id']['$gt'] = $lastId;
                    } else {
                        break;
                    }
                } else {
                    $batchFilter['_id'] = ['$gt' => $lastId];
                }
            }

            $batchOptions = array_merge($options, [
                'sort' => ['_id' => 1],
                'limit' => $batchSize
            ]);

            $cursor = $this->find($batchFilter, $batchOptions);
            $docs = $cursor->toArray();

            if (empty($docs)) {
                break;
            }

            $totalMatched += count($docs);
            $patchRequests = [];
            $docMap = [];

            foreach ($docs as $idx => $d) {
                $unwrapped = $this->unwrapBsonTypes($d);
                $docId = (string)($unwrapped['_id'] ?? ($unwrapped['id'] ?? ''));
                if (empty($docId)) continue;

                $currVersion = (int)($unwrapped['_version'] ?? 0);
                $nextVersion = $currVersion + 1;

                if (isset($update['$set']) && is_array($update['$set'])) {
                    foreach ($update['$set'] as $k => $v) {
                        $unwrapped[$k] = (is_object($v) && method_exists($v, '__toString')) ? (string)$v : $v;
                    }
                }
                if (isset($update['$inc']) && is_array($update['$inc'])) {
                    foreach ($update['$inc'] as $k => $v) {
                        $unwrapped[$k] = ($unwrapped[$k] ?? 0) + $v;
                    }
                }
                if (isset($update['$unset']) && is_array($update['$unset'])) {
                    foreach ($update['$unset'] as $k => $v) {
                        unset($unwrapped[$k]);
                    }
                }
                $unwrapped['_version'] = $nextVersion;
                $unwrapped['updatedAt'] = (string)new MongoDB\BSON\UTCDateTime();

                $occParam = ($currVersion > 0)
                    ? ('data->>_version=eq.' . $currVersion)
                    : 'or=(data->>_version.is.null,data->>_version.eq.0)';

                if (isset($update['$inc']) && is_array($update['$inc'])) {
                    foreach ($update['$inc'] as $incField => $incVal) {
                        $currFieldVal = $d[$incField] ?? 0;
                        $occParam .= '&data->>' . $incField . '=eq.' . $currFieldVal;
                    }
                }

                $patchUrl = 'mentry_documents?collection=eq.' . urlencode($this->name) . '&id=eq.' . urlencode($docId) . '&' . $occParam;
                $patchRequests[$idx] = [
                    'path' => $patchUrl,
                    'payload' => [
                        'data' => $unwrapped,
                        'updated_at' => date('c')
                    ]
                ];
                $docMap[$idx] = $docId;
                $lastId = $docId;
            }

            // Dispatch batch patches in parallel
            $patchResults = self::executeMultiPatch($patchRequests);

            foreach ($patchResults as $idx => $res) {
                $docId = $docMap[$idx];
                if ($res['code'] >= 200 && $res['code'] < 300 && count($res['data']) === 1) {
                    $totalModified++;
                } else {
                    // Conflict fallback: retry via updateOne
                    $r = $this->updateOne(['_id' => $docId], $update, $options);
                    $totalModified += $r->getModifiedCount();
                }
            }

            if (count($docs) < $batchSize) {
                break;
            }
        }

        return new class($totalModified, $totalMatched) {
            private $m; private $mat;
            public function __construct($m, $mat) { $this->m = $m; $this->mat = $mat; }
            public function getModifiedCount() { return $this->m; }
            public function getMatchedCount() { return $this->mat; }
        };
    }

    /**
     * Deletes a single document directly from Supabase PostgreSQL.
     */
    public function deleteOne(array $filter) {
        $existing = $this->findOne($filter);
        if (!$existing) {
            return new class(0) {
                private $d;
                public function __construct($d) { $this->d = $d; }
                public function getDeletedCount() { return $this->d; }
            };
        }

        $docId = (string)($existing['_id'] ?? ($existing['id'] ?? ''));
        if (empty($docId)) {
            return new class(0) {
                public function getDeletedCount() { return 0; }
            };
        }

        $res = self::executePostgrest('DELETE', 'mentry_documents', [
            'collection' => 'eq.' . $this->name,
            'id' => 'eq.' . $docId
        ]);

        $deleted = ($res['code'] >= 200 && $res['code'] < 300) ? 1 : 0;
        return new class($deleted) {
            private $d;
            public function __construct($d) { $this->d = $d; }
            public function getDeletedCount() { return $this->d; }
        };
    }

    /**
     * Direct database-side bulk DELETE. Zero documents loaded into PHP memory.
     */
    public function deleteMany(array $filter) {
        $params = self::buildFilterParams($this->name, $filter);
        $res = self::executePostgrest('DELETE', 'mentry_documents', $params, null, [
            'Prefer: return=representation'
        ]);

        $deletedCount = (is_array($res['data'])) ? count($res['data']) : (($res['code'] >= 200 && $res['code'] < 300) ? 1 : 0);
        return new class($deletedCount) {
            private $d;
            public function __construct($d) { $this->d = $d; }
            public function getDeletedCount() { return $this->d; }
        };
    }

    public function aggregate(array $pipeline): SafeCursor {
        $firstMatch = [];
        if (!empty($pipeline) && isset($pipeline[0]['$match']) && is_array($pipeline[0]['$match'])) {
            $firstMatch = $pipeline[0]['$match'];
            array_shift($pipeline);
        }

        $result = $this->find($firstMatch)->toArray();

        foreach ($pipeline as $stage) {
            if (isset($stage['$match'])) {
                $filtered = [];
                foreach ($result as $doc) {
                    $matched = true;
                    foreach ($stage['$match'] as $k => $v) {
                        if (($doc[$k] ?? null) != $v) {
                            $matched = false;
                            break;
                        }
                    }
                    if ($matched) $filtered[] = $doc;
                }
                $result = $filtered;
            } elseif (isset($stage['$group'])) {
                $groups = [];
                $groupIdField = $stage['$group']['_id'] ?? null;
                $accumulators = $stage['$group'];
                unset($accumulators['_id']);

                foreach ($result as $doc) {
                    $groupVal = 'null';
                    if (is_string($groupIdField) && strpos($groupIdField, '$') === 0) {
                        $fKey = substr($groupIdField, 1);
                        $groupVal = (string)($doc[$fKey] ?? 'null');
                    }
                    if (!isset($groups[$groupVal])) {
                        $groups[$groupVal] = ['_id' => $groupVal];
                        foreach ($accumulators as $accKey => $accExpr) {
                            $groups[$groupVal][$accKey] = 0;
                        }
                    }
                    foreach ($accumulators as $accKey => $accExpr) {
                        if (isset($accExpr['$sum'])) {
                            $inc = is_numeric($accExpr['$sum']) ? (float)$accExpr['$sum'] : 1;
                            $groups[$groupVal][$accKey] += $inc;
                        } elseif (isset($accExpr['$avg']) && is_string($accExpr['$avg']) && strpos($accExpr['$avg'], '$') === 0) {
                            $fName = substr($accExpr['$avg'], 1);
                            $groups[$groupVal]['_avg_vals'][$accKey][] = (float)($doc[$fName] ?? 0);
                        }
                    }
                }

                foreach ($groups as &$g) {
                    if (isset($g['_avg_vals'])) {
                        foreach ($g['_avg_vals'] as $accKey => $vals) {
                            $g[$accKey] = count($vals) > 0 ? (array_sum($vals) / count($vals)) : 0;
                        }
                        unset($g['_avg_vals']);
                    }
                }
                $result = array_values($groups);
            } elseif (isset($stage['$sort'])) {
                foreach ($stage['$sort'] as $sortKey => $sortDir) {
                    usort($result, function($a, $b) use ($sortKey, $sortDir) {
                        $va = $a[$sortKey] ?? 0;
                        $vb = $b[$sortKey] ?? 0;
                        return $sortDir < 0 ? ($vb <=> $va) : ($va <=> $vb);
                    });
                    break;
                }
            } elseif (isset($stage['$limit'])) {
                $result = array_slice($result, 0, (int)$stage['$limit']);
            }
        }

        return new SafeCursor($result, $result);
    }

    public function distinct(string $field, array $filter = []): array {
        $docs = $this->find($filter)->toArray();
        $values = [];
        foreach ($docs as $doc) {
            if (isset($doc[$field])) {
                $val = $doc[$field];
                $strVal = is_array($val) ? json_encode($val) : (string)$val;
                $values[$strVal] = $val;
            }
        }
        return array_values($values);
    }
}

/**
 * Universal Database Collection Proxy - Completely powered by Supabase.
 */
class SafeCollectionProxy {
    private string $name;
    private PersistentDocumentStore $store;

    public function __construct($unused = null, string $name = '') {
        $this->name = $name;
        $this->store = new PersistentDocumentStore($name);
    }

    public function __call($method, $arguments) {
        if (method_exists($this->store, $method)) {
            return call_user_func_array([$this->store, $method], $arguments);
        }
        return null;
    }
}

/**
 * Native Supabase Database Provider.
 */
class Database {
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getDb(): self {
        return $this;
    }

    public function selectCollection(string $collectionName): SafeCollectionProxy {
        return new SafeCollectionProxy(null, $collectionName);
    }

    public function getCollection(string $collectionName): SafeCollectionProxy {
        return new SafeCollectionProxy(null, $collectionName);
    }

    public function __get(string $name): SafeCollectionProxy {
        return $this->selectCollection($name);
    }
}

/**
 * Global Database Accessors
 */
function getDB(): Database {
    return Database::getInstance();
}

function getCollection(string $name): SafeCollectionProxy {
    return Database::getInstance()->getCollection($name);
}
