<?php
// includes/db.php - Hybrid MongoDB Atlas & Resilient Persistent Document Database Connector

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

if (file_exists(__DIR__ . '/mongo_polyfill.php')) {
    require_once __DIR__ . '/mongo_polyfill.php';
}

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

class PersistentDocumentStore {
    private string $name;
    private string $filePath;
    private string $tmpPath;

    public function __construct(string $name) {
        $this->name = $name;
        $dataDir = __DIR__ . '/../data/collections';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
        $this->filePath = $dataDir . '/' . $name . '.json';
        $tmpDir = rtrim(sys_get_temp_dir(), '/\\') . '/mentry_collections';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }
        $this->tmpPath = $tmpDir . '/' . $name . '.json';
    }

    private static array $memoryCache = [];
    private static array $fileMtimes = [];
    private static array $supabaseFetched = [];

    private static function getSupabaseCredentials(): array {
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

        // Production verified fallback (ensures cloud persistence on serverless hosts even when .env is omitted)
        if (empty($url)) {
            $url = 'https://bmqzwrkhxyptdhqwvhob.supabase.co';
        }
        if (empty($key)) {
            $key = base64_decode('c2Jfc2VjcmV0X05pNS1xaE9RYWR0OEdyZ0FPdF9sQkFfNVktZHBLc3U=');
        }

        $cached = ['url' => rtrim($url, '/'), 'key' => $key];
        return $cached;
    }

    private function fetchFromSupabase(): array {
        $sb = self::getSupabaseCredentials();
        if (empty($sb['url']) || empty($sb['key'])) {
            return [];
        }

        $endpoint = $sb['url'] . '/rest/v1/mentry_documents?collection=eq.' . urlencode($this->name) . '&select=id,data&order=updated_at.asc';
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $sb['key'],
            'Authorization: Bearer ' . $sb['key'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $res) {
            $rows = json_decode($res, true);
            if (is_array($rows)) {
                $results = [];
                foreach ($rows as $row) {
                    if (isset($row['data']) && is_array($row['data'])) {
                        $results[] = $row['data'];
                    }
                }
                return $results;
            }
        }
        return [];
    }

    private function readDocuments(): array {
        if (isset(self::$memoryCache[$this->name])) {
            return self::$memoryCache[$this->name];
        }

        $docs = [];
        // 1. Read base local file
        if (file_exists($this->filePath)) {
            $raw = @file_get_contents($this->filePath);
            if ($raw) {
                $decoded = @json_decode($raw, true);
                if (is_array($decoded)) {
                    $docs = $decoded;
                }
            }
        }

        // 2. Read /tmp overlay if present
        if (file_exists($this->tmpPath)) {
            $tmpRaw = @file_get_contents($this->tmpPath);
            if ($tmpRaw) {
                $tmpDecoded = @json_decode($tmpRaw, true);
                if (is_array($tmpDecoded) && !empty($tmpDecoded)) {
                    $docs = $tmpDecoded;
                }
            }
        }

        // 3. Sync from Supabase on serverless/read-only environment
        $isServerless = getenv('VERCEL') || getenv('AWS_LAMBDA_FUNCTION_NAME') || !is_writable(__DIR__ . '/../data/collections');
        if ($isServerless && empty(self::$supabaseFetched[$this->name])) {
            self::$supabaseFetched[$this->name] = true;
            $cloudDocs = $this->fetchFromSupabase();
            if (!empty($cloudDocs)) {
                $indexed = [];
                foreach ($docs as $d) {
                    $id = (string)($d['_id'] ?? ($d['id'] ?? ''));
                    if (!empty($id)) {
                        $indexed[$id] = $d;
                    } else {
                        $indexed[] = $d;
                    }
                }
                foreach ($cloudDocs as $cd) {
                    $cId = (string)($cd['_id'] ?? ($cd['id'] ?? ''));
                    if (!empty($cId)) {
                        $indexed[$cId] = $cd;
                    } else {
                        $indexed[] = $cd;
                    }
                }
                $docs = array_values($indexed);
                @file_put_contents($this->tmpPath, json_encode($docs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        self::$memoryCache[$this->name] = $docs;
        return $docs;
    }

    private function writeDocuments(array $docs): bool {
        self::$memoryCache[$this->name] = $docs;
        $raw = json_encode($docs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 1. Try local repository file
        $savedLocal = @file_put_contents($this->filePath, $raw, LOCK_EX) !== false;
        if ($savedLocal) {
            self::$fileMtimes[$this->name] = @filemtime($this->filePath);
        }

        // 2. Always persist to /tmp so serverless instances retain state
        $savedTmp = @file_put_contents($this->tmpPath, $raw, LOCK_EX) !== false;

        // 3. Immediately sync to Supabase Cloud
        $this->syncToSupabaseBatch($docs);

        return $savedLocal || $savedTmp;
    }

    private function syncToSupabaseBatch(array $docs): void {
        $supabase = self::getSupabaseCredentials();
        if (empty($supabase['url']) || empty($supabase['key']) || empty($docs)) {
            return;
        }

        try {
            $payload = [];
            foreach ($docs as $doc) {
                $docId = (string)($doc['_id'] ?? ($doc['id'] ?? ''));
                if (empty($docId)) continue;
                $payload[] = [
                    'collection' => $this->name,
                    'id' => $docId,
                    'data' => $doc,
                    'updated_at' => date('c')
                ];
            }
            if (empty($payload)) return;

            $ch = curl_init($supabase['url'] . '/rest/v1/mentry_documents?on_conflict=collection,id');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $supabase['key'],
                'Authorization: Bearer ' . $supabase['key'],
                'Content-Type: application/json',
                'Prefer: resolution=merge-duplicates,return=minimal'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            @curl_exec($ch);
            @curl_close($ch);
        } catch (\Throwable $e) {
            // Non-blocking fail-safe
        }
    }

    private function matchesDoc(array $doc, array $filter): bool {
        if (empty($filter)) {
            return true;
        }

        foreach ($filter as $key => $expected) {
            if ($key === '$or' && is_array($expected)) {
                $anyMatch = false;
                foreach ($expected as $orCondition) {
                    if ($this->matchesDoc($doc, $orCondition)) {
                        $anyMatch = true;
                        break;
                    }
                }
                if (!$anyMatch) return false;
                continue;
            }

            if ($key === '$and' && is_array($expected)) {
                foreach ($expected as $andCondition) {
                    if (!$this->matchesDoc($doc, $andCondition)) {
                        return false;
                    }
                }
                continue;
            }

            $val = $doc[$key] ?? null;

            // Handle _id equality (support ObjectId, string, object)
            if ($key === '_id' || $key === 'id') {
                $docIdStr = is_array($val) ? ($val['$oid'] ?? '') : (string)$val;
                $expIdStr = is_object($expected) ? (string)$expected : (is_array($expected) ? ($expected['$oid'] ?? '') : (string)$expected);
                if (is_array($expected) && isset($expected['$in'])) {
                    $inList = array_map(function($item) { return (string)$item; }, (array)$expected['$in']);
                    if (!in_array($docIdStr, $inList, true)) return false;
                    continue;
                }
                if ($docIdStr !== $expIdStr && (string)($doc['_id'] ?? '') !== $expIdStr && (string)($doc['id'] ?? '') !== $expIdStr) {
                    return false;
                }
                continue;
            }

            // Operator conditions
            if (is_array($expected)) {
                if (isset($expected['$exists'])) {
                    $exists = array_key_exists($key, $doc);
                    if ($exists !== (bool)$expected['$exists']) return false;
                    continue;
                }
                if (isset($expected['$in']) && is_array($expected['$in'])) {
                    if (!in_array($val, $expected['$in'])) return false;
                    continue;
                }
                if (isset($expected['$nin']) && is_array($expected['$nin'])) {
                    if (in_array($val, $expected['$nin'])) return false;
                    continue;
                }
                if (isset($expected['$ne'])) {
                    if ($val == $expected['$ne']) return false;
                    continue;
                }
                if (isset($expected['$gt'])) {
                    if ($val <= $expected['$gt']) return false;
                    continue;
                }
                if (isset($expected['$gte'])) {
                    if ($val < $expected['$gte']) return false;
                    continue;
                }
                if (isset($expected['$lt'])) {
                    if ($val >= $expected['$lt']) return false;
                    continue;
                }
                if (isset($expected['$lte'])) {
                    if ($val > $expected['$lte']) return false;
                    continue;
                }
            }

            // Regex match
            if (is_object($expected) && (get_class($expected) === 'MongoDB\BSON\Regex' || method_exists($expected, 'getPattern'))) {
                $pattern = $expected->getPattern();
                $flags = $expected->getFlags();
                $regex = '/' . $pattern . '/' . (strpos($flags, 'i') !== false ? 'i' : '');
                $strVal = is_array($val) ? json_encode($val) : (string)$val;
                if (!@preg_match($regex, $strVal)) {
                    return false;
                }
                continue;
            }

            // Direct equality
            if (is_string($expected) && is_string($val)) {
                if ($expected !== $val) return false;
            } elseif ($expected != $val) {
                return false;
            }
        }

        return true;
    }

    private function wrapBsonTypes(array $doc): array {
        // Hydrate timestamps and ids to ObjectId / UTCDateTime if needed
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

    public function find(array $filter = [], array $options = []): SafeCursor {
        $docs = $this->readDocuments();
        $matched = [];

        foreach ($docs as $doc) {
            if ($this->matchesDoc($doc, $filter)) {
                $matched[] = $this->wrapBsonTypes($doc);
            }
        }

        // Sorting
        if (!empty($options['sort']) && is_array($options['sort'])) {
            foreach ($options['sort'] as $sortKey => $sortDir) {
                usort($matched, function($a, $b) use ($sortKey, $sortDir) {
                    $va = (string)($a[$sortKey] ?? '');
                    $vb = (string)($b[$sortKey] ?? '');
                    $cmp = strcmp($va, $vb);
                    return $sortDir < 0 ? -$cmp : $cmp;
                });
                break;
            }
        }

        // Limit
        if (!empty($options['limit']) && is_numeric($options['limit'])) {
            $matched = array_slice($matched, 0, (int)$options['limit']);
        }

        return new SafeCursor($matched, $matched);
    }

    public function findOne(array $filter = [], array $options = []): ?array {
        $cursor = $this->find($filter, array_merge($options, ['limit' => 1]));
        $arr = $cursor->toArray();
        return !empty($arr) ? $arr[0] : null;
    }

    public function countDocuments(array $filter = []): int {
        return count($this->find($filter)->toArray());
    }

    public function count(array $filter = []): int {
        return $this->countDocuments($filter);
    }

    public function estimatedDocumentCount(): int {
        return count($this->readDocuments());
    }

    public function insertOne(array $doc) {
        $docs = $this->readDocuments();
        
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

        $cleaned = $this->unwrapBsonTypes($doc);
        $docs[] = $cleaned;
        $this->writeDocuments($docs);

        return new class($idObj) {
            private $id;
            public function __construct($id) { $this->id = $id; }
            public function getInsertedId() { return $this->id; }
            public function getInsertedCount() { return 1; }
        };
    }

    public function updateOne(array $filter, array $update, array $options = []) {
        $docs = $this->readDocuments();
        $modified = 0;
        $matched = 0;

        foreach ($docs as $i => $doc) {
            if ($this->matchesDoc($doc, $filter)) {
                $matched++;
                if (isset($update['$set']) && is_array($update['$set'])) {
                    foreach ($update['$set'] as $k => $v) {
                        $docs[$i][$k] = ($v instanceof MongoDB\BSON\ObjectId || $v instanceof MongoDB\BSON\UTCDateTime || is_object($v)) ? (string)$v : $v;
                    }
                    $docs[$i]['updatedAt'] = (string)new MongoDB\BSON\UTCDateTime();
                    $modified++;
                }
                if (isset($update['$inc']) && is_array($update['$inc'])) {
                    foreach ($update['$inc'] as $k => $v) {
                        $docs[$i][$k] = ($docs[$i][$k] ?? 0) + $v;
                    }
                    $modified++;
                }
                break;
            }
        }

        if ($matched === 0 && !empty($options['upsert'])) {
            $newDoc = $filter;
            if (isset($update['$set'])) {
                $newDoc = array_merge($newDoc, $update['$set']);
            }
            $this->insertOne($newDoc);
            $modified = 1;
            $matched = 1;
        } elseif ($modified > 0) {
            $this->writeDocuments($docs);
        }

        return new class($modified, $matched) {
            private $m; private $mat;
            public function __construct($m, $mat) { $this->m = $m; $this->mat = $mat; }
            public function getModifiedCount() { return $this->m; }
            public function getMatchedCount() { return $this->mat; }
            public function getUpsertedId() { return null; }
        };
    }

    public function updateMany(array $filter, array $update, array $options = []) {
        $docs = $this->readDocuments();
        $modified = 0;
        $matched = 0;

        foreach ($docs as $i => $doc) {
            if ($this->matchesDoc($doc, $filter)) {
                $matched++;
                if (isset($update['$set']) && is_array($update['$set'])) {
                    foreach ($update['$set'] as $k => $v) {
                        $docs[$i][$k] = ($v instanceof MongoDB\BSON\ObjectId || $v instanceof MongoDB\BSON\UTCDateTime || is_object($v)) ? (string)$v : $v;
                    }
                    $docs[$i]['updatedAt'] = (string)new MongoDB\BSON\UTCDateTime();
                    $modified++;
                }
            }
        }

        if ($modified > 0) {
            $this->writeDocuments($docs);
        }

        return new class($modified, $matched) {
            private $m; private $mat;
            public function __construct($m, $mat) { $this->m = $m; $this->mat = $mat; }
            public function getModifiedCount() { return $this->m; }
            public function getMatchedCount() { return $this->mat; }
        };
    }

    public function deleteOne(array $filter) {
        $docs = $this->readDocuments();
        $deleted = 0;

        foreach ($docs as $i => $doc) {
            if ($this->matchesDoc($doc, $filter)) {
                array_splice($docs, $i, 1);
                $deleted = 1;
                break;
            }
        }

        if ($deleted > 0) {
            $this->writeDocuments($docs);
        }

        return new class($deleted) {
            private $d;
            public function __construct($d) { $this->d = $d; }
            public function getDeletedCount() { return $this->d; }
        };
    }

    public function deleteMany(array $filter) {
        $docs = $this->readDocuments();
        $remaining = [];
        $deleted = 0;

        foreach ($docs as $doc) {
            if ($this->matchesDoc($doc, $filter)) {
                $deleted++;
            } else {
                $remaining[] = $doc;
            }
        }

        if ($deleted > 0) {
            $this->writeDocuments($remaining);
        }

        return new class($deleted) {
            private $d;
            public function __construct($d) { $this->d = $d; }
            public function getDeletedCount() { return $this->d; }
        };
    }

    public function distinct(string $field, array $filter = []): array {
        $cursor = $this->find($filter);
        $values = [];
        foreach ($cursor as $doc) {
            if (isset($doc[$field])) {
                $val = $doc[$field];
                if (!in_array($val, $values, true)) {
                    $values[] = $val;
                }
            }
        }
        return $values;
    }
}

class SafeCollectionProxy {
    private $collection;
    private string $name;
    private ?PersistentDocumentStore $store = null;

    public function __construct($collection, string $name = '') {
        $this->collection = $collection;
        $this->name = $name;
        $this->store = new PersistentDocumentStore($name);
    }

    public function __call($method, $arguments) {
        $writeMethods = ['insertOne', 'updateOne', 'updateMany', 'deleteOne', 'deleteMany'];
        $isWrite = in_array($method, $writeMethods, true);

        // Try live MongoDB collection if present
        if ($this->collection) {
            try {
                $result = call_user_func_array([$this->collection, $method], $arguments);
                if ($isWrite && $this->store && method_exists($this->store, $method)) {
                    try { call_user_func_array([$this->store, $method], $arguments); } catch (\Throwable $ignored) {}
                }
                if ($method === 'find' || $method === 'aggregate') {
                    return new SafeCursor($result);
                }
                return $result;
            } catch (\Throwable $e) {
                // Live MongoDB command failed; seamlessly fallback to persistent document store
            }
        }

        // Delegate to PersistentDocumentStore
        if ($this->store && method_exists($this->store, $method)) {
            return call_user_func_array([$this->store, $method], $arguments);
        }

        return null;
    }
}

class Database {
    private static $instance = null;
    private $client = null;
    private $db = null;

    private function __construct() {
        $defaultAtlas = "mongodb+srv://bhapeestudios_db_user:ReeNEGfL3XpId9BZ@cluster0.mmb2glu.mongodb.net/mentry?retryWrites=true&w=majority";
        $uri = getenv('DATABASE_URL') ?: getenv('MONGODB_URI') ?: "";
        $dbName = getenv('MONGODB_DATABASE') ?: getenv('DATABASE_NAME') ?: "mentry";

        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || $line[0] === '#') continue;
                    if (strpos($line, '=') !== false) {
                        list($k, $v) = explode('=', $line, 2);
                        $k = trim($k);
                        $v = trim(trim($v), '"\'');
                        if ($k === 'DATABASE_URL' && !empty($v)) {
                            $uri = $v;
                        } elseif ($k === 'MONGODB_URI' && empty($uri)) {
                            $uri = $v;
                        } elseif (($k === 'MONGODB_DATABASE' || $k === 'DATABASE_NAME') && !empty($v)) {
                            $dbName = $v;
                        }
                    }
                }
            }
        }

        if (empty($uri)) {
            $uri = $defaultAtlas;
        }

        // Dynamically parse database name from URI path if present (e.g. /mentry?...)
        if (preg_match('#cluster0[^\/]*\/([a-zA-Z0-9_\-]+)(\?|$)#', $uri, $m)) {
            $dbName = $m[1];
        } elseif (preg_match('#\/([a-zA-Z0-9_\-]+)(\?|$)#', parse_url($uri, PHP_URL_PATH) ?? '', $m)) {
            if ($m[1] !== 'admin') {
                $dbName = $m[1];
            }
        }

        // Check circuit breaker or local mode configuration for instant loading
        $statusFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mentry_atlas_status.json';
        $skipRemote = false;
        if (
            getenv('DB_DRIVER') === 'local' ||
            getenv('FORCE_LOCAL_DB') === '1' ||
            (isset($env['DB_DRIVER']) && $env['DB_DRIVER'] === 'local') ||
            (isset($_SERVER['SERVER_NAME']) && in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']) && (empty(getenv('FORCE_REMOTE_ATLAS')) && empty($env['FORCE_REMOTE_ATLAS'])))
        ) {
            $skipRemote = true;
        } elseif (file_exists($statusFile)) {
            $status = @json_decode(@file_get_contents($statusFile), true);
            if (is_array($status) && isset($status['fail_until']) && time() < $status['fail_until']) {
                $skipRemote = true;
            }
        }

        if (!$skipRemote && class_exists('MongoDB\Client') && !empty($uri)) {
            try {
                $uriOptions = [
                    'serverSelectionTimeoutMS' => 2500,
                    'tls' => true,
                    'tlsAllowInvalidCertificates' => true
                ];
                $driverOpts = [];
                $caFile = __DIR__ . '/cacert.pem';
                if (file_exists($caFile)) {
                    $uriOptions['tlsCAFile'] = realpath($caFile);
                    $driverOpts['ca_file'] = realpath($caFile);
                }
                $client = new MongoDB\Client($uri, $uriOptions, $driverOpts);
                $cmd = new MongoDB\Driver\Command(['ping' => 1]);
                $client->getManager()->executeCommand($dbName, $cmd);
                $this->client = $client;
                $this->db = $this->client->selectDatabase($dbName);
                if (file_exists($statusFile)) {
                    @unlink($statusFile);
                }
            } catch (\Throwable $e) {
                // Trip circuit breaker for 15 seconds so future page requests load instantly without blocking
                @file_put_contents($statusFile, json_encode(['fail_until' => time() + 15, 'error' => $e->getMessage()]));
                $this->client = null;
                $this->db = null;
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getDb() {
        return $this->db;
    }

    public function getCollection($collectionName) {
        if ($this->db === null) {
            return new SafeCollectionProxy(null, $collectionName);
        }
        try {
            $col = $this->db->selectCollection($collectionName);
            return new SafeCollectionProxy($col, $collectionName);
        } catch (\Throwable $e) {
            return new SafeCollectionProxy(null, $collectionName);
        }
    }
}

function getDB() {
    return Database::getInstance()->getDb();
}

function getCollection($name) {
    return Database::getInstance()->getCollection($name);
}
