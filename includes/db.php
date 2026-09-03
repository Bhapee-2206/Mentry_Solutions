<?php
// includes/db.php - MongoDB Atlas Database Connector with Resilient Safe Proxy Wrapper

require_once __DIR__ . '/../vendor/autoload.php';

class SafeCursor implements IteratorAggregate {
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

class SafeCollectionProxy {
    private $collection;
    private $name;

    public function __construct($collection, $name = '') {
        $this->collection = $collection;
        $this->name = $name;
    }

    public function __call($method, $arguments) {
        if (!$this->collection) {
            return $this->getSafeDefault($method);
        }
        try {
            $result = call_user_func_array([$this->collection, $method], $arguments);
            if ($method === 'find' || $method === 'aggregate') {
                return new SafeCursor($result);
            }
            return $result;
        } catch (\Throwable $e) {
            error_log("MongoDB [{$this->name}::{$method}] Error: " . $e->getMessage());
            return $this->getSafeDefault($method);
        }
    }

    private function getSafeDefault($method) {
        switch ($method) {
            case 'countDocuments':
            case 'count':
            case 'estimatedDocumentCount':
                return 0;
            case 'find':
            case 'aggregate':
                return new SafeCursor(null, []);
            case 'distinct':
                return [];
            case 'findOne':
                return null;
            case 'insertOne':
                return new class {
                    public function getInsertedId() { return new MongoDB\BSON\ObjectId(); }
                    public function getInsertedCount() { return 0; }
                };
            case 'updateOne':
            case 'updateMany':
            case 'replaceOne':
                return new class {
                    public function getModifiedCount() { return 0; }
                    public function getMatchedCount() { return 0; }
                    public function getUpsertedId() { return null; }
                };
            case 'deleteOne':
            case 'deleteMany':
                return new class {
                    public function getDeletedCount() { return 0; }
                };
            default:
                return null;
        }
    }
}

class Database {
    private static $instance = null;
    private $client = null;
    private $db = null;

    private function __construct() {
        $uri = getenv('DATABASE_URL') ?: getenv('MONGODB_URI') ?: "mongodb://localhost:27017/mentry_solutions";
        $dbName = "mentry_solutions";

        // Read from .env if available
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $env = @parse_ini_file($envPath);
            if (!empty($env['DATABASE_URL'])) {
                $uri = trim($env['DATABASE_URL'], '"\'');
            } elseif (!empty($env['MONGODB_URI'])) {
                $uri = trim($env['MONGODB_URI'], '"\'');
            }
        }

        try {
            $this->client = new MongoDB\Client($uri, [], ['serverSelectionTimeoutMS' => 500]);
            $this->db = $this->client->selectDatabase($dbName);
        } catch (\Throwable $e) {
            error_log("MongoDB Connection Error: " . $e->getMessage());
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
            error_log("Error selecting collection {$collectionName}: " . $e->getMessage());
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
