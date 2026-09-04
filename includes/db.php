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

    public function __construct(string $name) {
        $this->name = $name;
        $dataDir = __DIR__ . '/../data/collections';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
        $this->filePath = $dataDir . '/' . $name . '.json';
    }

    private function readDocuments(): array {
        if (!file_exists($this->filePath)) {
            return [];
        }
        $raw = @file_get_contents($this->filePath);
        if (!$raw) return [];
        $decoded = @json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeDocuments(array $docs): bool {
        $raw = json_encode($docs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return @file_put_contents($this->filePath, $raw, LOCK_EX) !== false;
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
        $uri = getenv('DATABASE_URL') ?: getenv('MONGODB_URI') ?: "mongodb://localhost:27017/mentry";
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

        // Dynamically parse database name from URI path if present (e.g. /mentry?...)
        if (preg_match('#cluster0[^\/]*\/([a-zA-Z0-9_\-]+)(\?|$)#', $uri, $m)) {
            $dbName = $m[1];
        } elseif (preg_match('#\/([a-zA-Z0-9_\-]+)(\?|$)#', parse_url($uri, PHP_URL_PATH) ?? '', $m)) {
            if ($m[1] !== 'admin') {
                $dbName = $m[1];
            }
        }

        try {
            if (class_exists('MongoDB\Client') && !empty($uri)) {
                $driverOpts = [
                    'serverSelectionTimeoutMS' => 3000,
                    'tls' => true,
                    'tlsAllowInvalidCertificates' => true
                ];
                $caFile = __DIR__ . '/cacert.pem';
                if (file_exists($caFile)) {
                    $driverOpts['tlsCAFile'] = realpath($caFile);
                }
                $client = new MongoDB\Client($uri, [], $driverOpts);
                $cmd = new MongoDB\Driver\Command(['ping' => 1]);
                $client->getManager()->executeCommand($dbName, $cmd);
                $this->client = $client;
                $this->db = $this->client->selectDatabase($dbName);
            }
        } catch (\Throwable $e) {
            $this->client = null;
            $this->db = null;
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
