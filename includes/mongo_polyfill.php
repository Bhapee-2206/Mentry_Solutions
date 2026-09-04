<?php
// includes/mongo_polyfill.php - Zero-dependency polyfill for MongoDB BSON classes & MongoDB Driver
// Automatically polyfills MongoDB\BSON classes when the php-mongodb C extension is not installed (e.g. Vercel Serverless, shared hosting)

namespace MongoDB\BSON {
    if (!interface_exists(__NAMESPACE__ . '\Type', false)) {
        interface Type {}
    }

    if (!interface_exists(__NAMESPACE__ . '\TypeInterface', false)) {
        interface TypeInterface extends Type {}
    }

    if (!interface_exists(__NAMESPACE__ . '\UTCDateTimeInterface', false)) {
        interface UTCDateTimeInterface extends Type {
            public function toDateTime(): \DateTime;
        }
    }

    if (!interface_exists(__NAMESPACE__ . '\ObjectIdInterface', false)) {
        interface ObjectIdInterface extends Type {
            public function getTimestamp(): int;
            public function __toString(): string;
        }
    }

    if (!interface_exists(__NAMESPACE__ . '\RegexInterface', false)) {
        interface RegexInterface extends Type {
            public function getPattern(): string;
            public function getFlags(): string;
            public function __toString(): string;
        }
    }

    if (!interface_exists(__NAMESPACE__ . '\Decimal128Interface', false)) {
        interface Decimal128Interface extends Type {
            public function __toString(): string;
        }
    }

    if (!interface_exists(__NAMESPACE__ . '\BinaryInterface', false)) {
        interface BinaryInterface extends Type {
            public function getData(): string;
            public function getType(): int;
            public function __toString(): string;
        }
    }

    if (!interface_exists(__NAMESPACE__ . '\TimestampInterface', false)) {
        interface TimestampInterface extends Type {
            public function getIncrement(): int;
            public function getTimestamp(): int;
            public function __toString(): string;
        }
    }

    if (!interface_exists(__NAMESPACE__ . '\Serializable', false)) {
        interface Serializable extends Type {
            public function bsonSerialize();
        }
    }

    if (!interface_exists(__NAMESPACE__ . '\Unserializable', false)) {
        interface Unserializable extends Type {
            public function bsonUnserialize(array $data);
        }
    }

    if (!interface_exists(__NAMESPACE__ . '\Persistable', false)) {
        interface Persistable extends Serializable, Unserializable {}
    }

    // --- UTCDateTime Polyfill ---
    if (!class_exists(__NAMESPACE__ . '\UTCDateTime', false)) {
        class UTCDateTime implements UTCDateTimeInterface, \JsonSerializable, \Stringable {
            private $milliseconds;

            public function __construct($milliseconds = null) {
                if ($milliseconds === null || $milliseconds === '') {
                    $this->milliseconds = (int)round(microtime(true) * 1000);
                } elseif ($milliseconds instanceof \DateTimeInterface) {
                    $this->milliseconds = (int)($milliseconds->getTimestamp() * 1000 + (int)($milliseconds->format('u') / 1000));
                } elseif ($milliseconds instanceof UTCDateTime || is_object($milliseconds)) {
                    $this->milliseconds = (int)(string)$milliseconds;
                } elseif (is_numeric($milliseconds)) {
                    $this->milliseconds = (int)$milliseconds;
                } elseif (is_string($milliseconds)) {
                    $parsed = strtotime($milliseconds);
                    if ($parsed !== false) {
                        $this->milliseconds = (int)($parsed * 1000);
                    } else {
                        $this->milliseconds = (int)$milliseconds;
                    }
                } else {
                    $this->milliseconds = (int)round(microtime(true) * 1000);
                }
            }

            public function toDateTime(): \DateTime {
                $seconds = floor($this->milliseconds / 1000);
                $micro = ($this->milliseconds % 1000) * 1000;
                $dt = \DateTime::createFromFormat('U.u', sprintf('%d.%06d', $seconds, $micro), new \DateTimeZone('UTC'));
                if ($dt === false) {
                    $dt = new \DateTime('@' . $seconds, new \DateTimeZone('UTC'));
                }
                return $dt;
            }

            public function __toString(): string {
                return (string)$this->milliseconds;
            }

            #[\ReturnTypeWillChange]
            public function jsonSerialize() {
                return [
                    '$date' => [
                        '$numberLong' => (string)$this->milliseconds
                    ]
                ];
            }
        }
    }

    // --- ObjectId Polyfill ---
    if (!class_exists(__NAMESPACE__ . '\ObjectId', false)) {
        class ObjectId implements ObjectIdInterface, \JsonSerializable, \Stringable {
            private $id;

            public function __construct(?string $id = null) {
                if ($id === null || $id === '') {
                    try {
                        $this->id = bin2hex(random_bytes(12));
                    } catch (\Throwable $e) {
                        $this->id = substr(md5(uniqid((string)mt_rand(), true)), 0, 24);
                    }
                } else {
                    $clean = trim((string)$id);
                    if (preg_match('/^[a-f0-9]{24}$/i', $clean)) {
                        $this->id = strtolower($clean);
                    } else {
                        // Resilient fallback for non-24 hex strings
                        $this->id = substr(md5($clean) . bin2hex(random_bytes(6)), 0, 24);
                    }
                }
            }

            public function getTimestamp(): int {
                return hexdec(substr($this->id, 0, 8));
            }

            public function __toString(): string {
                return $this->id;
            }

            #[\ReturnTypeWillChange]
            public function jsonSerialize() {
                return ['$oid' => $this->id];
            }
        }
    }

    // --- Regex Polyfill ---
    if (!class_exists(__NAMESPACE__ . '\Regex', false)) {
        class Regex implements RegexInterface, \JsonSerializable, \Stringable {
            private $pattern;
            private $flags;

            public function __construct(string $pattern, string $flags = '') {
                $this->pattern = $pattern;
                $this->flags = $flags;
            }

            public function getPattern(): string {
                return $this->pattern;
            }

            public function getFlags(): string {
                return $this->flags;
            }

            public function __toString(): string {
                return '/' . $this->pattern . '/' . $this->flags;
            }

            #[\ReturnTypeWillChange]
            public function jsonSerialize() {
                return [
                    '$regularExpression' => [
                        'pattern' => $this->pattern,
                        'options' => $this->flags
                    ]
                ];
            }
        }
    }

    // --- Decimal128 Polyfill ---
    if (!class_exists(__NAMESPACE__ . '\Decimal128', false)) {
        class Decimal128 implements Decimal128Interface, \JsonSerializable, \Stringable {
            private $value;

            public function __construct(string $value) {
                $this->value = (string)$value;
            }

            public function __toString(): string {
                return $this->value;
            }

            #[\ReturnTypeWillChange]
            public function jsonSerialize() {
                return ['$numberDecimal' => $this->value];
            }
        }
    }

    // --- Binary Polyfill ---
    if (!class_exists(__NAMESPACE__ . '\Binary', false)) {
        class Binary implements BinaryInterface, \JsonSerializable, \Stringable {
            public const TYPE_GENERIC = 0;
            public const TYPE_FUNCTION = 1;
            public const TYPE_OLD_BINARY = 2;
            public const TYPE_OLD_UUID = 3;
            public const TYPE_UUID = 4;
            public const TYPE_MD5 = 5;
            public const TYPE_ENCRYPTED = 6;
            public const TYPE_USER_DEFINED = 128;

            private $data;
            private $type;

            public function __construct(string $data, int $type = self::TYPE_GENERIC) {
                $this->data = $data;
                $this->type = $type;
            }

            public function getData(): string {
                return $this->data;
            }

            public function getType(): int {
                return $this->type;
            }

            public function __toString(): string {
                return $this->data;
            }

            #[\ReturnTypeWillChange]
            public function jsonSerialize() {
                return [
                    '$binary' => [
                        'base64' => base64_encode($this->data),
                        'subType' => sprintf('%02x', $this->type)
                    ]
                ];
            }
        }
    }

    // --- Timestamp Polyfill ---
    if (!class_exists(__NAMESPACE__ . '\Timestamp', false)) {
        class Timestamp implements TimestampInterface, \JsonSerializable, \Stringable {
            private $increment;
            private $timestamp;

            public function __construct(int $increment, int $timestamp) {
                $this->increment = $increment;
                $this->timestamp = $timestamp;
            }

            public function getIncrement(): int {
                return $this->increment;
            }

            public function getTimestamp(): int {
                return $this->timestamp;
            }

            public function __toString(): string {
                return "[{$this->timestamp}:{$this->increment}]";
            }

            #[\ReturnTypeWillChange]
            public function jsonSerialize() {
                return [
                    '$timestamp' => [
                        't' => $this->timestamp,
                        'i' => $this->increment
                    ]
                ];
            }
        }
    }
}

namespace MongoDB {
    if (!class_exists('MongoDB\Client')) {
        class Client {
            private $uri;
            private $options;

            public function __construct(string $uri = 'mongodb://localhost:27017', array $uriOptions = [], array $driverOptions = []) {
                $this->uri = $uri;
                $this->options = array_merge($uriOptions, $driverOptions);
            }

            public function selectDatabase(string $databaseName, array $options = []) {
                return new class($databaseName) {
                    private $name;
                    public function __construct($name) { $this->name = $name; }
                    public function selectCollection(string $collectionName, array $options = []) {
                        return null;
                    }
                    public function __get($name) {
                        return $this->selectCollection($name);
                    }
                };
            }

            public function selectCollection(string $databaseName, string $collectionName, array $options = []) {
                return $this->selectDatabase($databaseName)->selectCollection($collectionName, $options);
            }

            public function __get($name) {
                return $this->selectDatabase($name);
            }
        }
    }
}
