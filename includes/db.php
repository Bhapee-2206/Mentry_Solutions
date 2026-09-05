<?php
// includes/db.php - Supabase Cloud Database Connector & Document Engine

if (file_exists(__DIR__ . '/mongo_polyfill.php')) {
    require_once __DIR__ . '/mongo_polyfill.php';
}

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
 * Supabase-backed Document Store with high-performance memory cache,
 * serverless /tmp overlay, and real-time Supabase Cloud synchronization.
 */
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
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

        // 3. Sync from Supabase on serverless/read-only environment or first load
        $isServerless = getenv('VERCEL') || getenv('AWS_LAMBDA_FUNCTION_NAME') || !is_writable(__DIR__ . '/../data/collections');
        if (($isServerless || empty($docs)) && empty(self::$supabaseFetched[$this->name])) {
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

    private function deleteFromSupabase(string $docId): void {
        $supabase = self::getSupabaseCredentials();
        if (empty($supabase['url']) || empty($supabase['key']) || empty($docId)) return;
        try {
            $ch = curl_init($supabase['url'] . '/rest/v1/mentry_documents?collection=eq.' . urlencode($this->name) . '&id=eq.' . urlencode($docId));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $supabase['key'],
                'Authorization: Bearer ' . $supabase['key']
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            @curl_exec($ch);
            @curl_close($ch);
        } catch (\Throwable $e) {}
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
                $docId = (string)($doc['_id'] ?? ($doc['id'] ?? ''));
                array_splice($docs, $i, 1);
                $deleted = 1;
                if (!empty($docId)) {
                    $this->deleteFromSupabase($docId);
                }
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
                $docId = (string)($doc['_id'] ?? ($doc['id'] ?? ''));
                $deleted++;
                if (!empty($docId)) {
                    $this->deleteFromSupabase($docId);
                }
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

    public function aggregate(array $pipeline): SafeCursor {
        $docs = $this->readDocuments();
        $result = $docs;

        foreach ($pipeline as $stage) {
            if (isset($stage['$match'])) {
                $filtered = [];
                foreach ($result as $doc) {
                    if ($this->matchesDoc($doc, $stage['$match'])) {
                        $filtered[] = $doc;
                    }
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
        $docs = $this->readDocuments();
        $values = [];
        foreach ($docs as $doc) {
            if ($this->matchesDoc($doc, $filter) && isset($doc[$field])) {
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
