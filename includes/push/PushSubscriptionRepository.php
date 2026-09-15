<?php
// includes/push/PushSubscriptionRepository.php - Data Layer for Web Push Subscriptions
// Responsibilities: save, find, deactivate, delete, update success/failure stats.
// Does NOT alter database collections or schemas. Redacts credentials when read.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

class PushSubscriptionRepository {
    private static string $collectionName = 'PushSubscription';

    /**
     * Get MongoDB collection
     */
    private static function col() {
        return getCollection(self::$collectionName);
    }

    /**
     * Validate endpoint URL for scheme and legitimate push gateway domains
     */
    public static function isValidEndpoint(string $endpoint): bool {
        if (empty($endpoint) || strlen($endpoint) > 2048) {
            return false;
        }
        $parts = parse_url($endpoint);
        if (!$parts || empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https') {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');
        if (empty($host) || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        $allowedDomains = [
            'googleapis.com',
            'push.services.mozilla.com',
            'services.mozilla.com',
            'push.apple.com',
            'notify.windows.com'
        ];

        $matched = false;
        foreach ($allowedDomains as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return false;
        }

        // Prevent SSRF / internal IP destinations
        $ip = @gethostbyname($host);
        if ($ip && $ip !== $host) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Save or update subscription record using endpoint as device identity.
     * Updates in-place if endpoint already exists (no duplicates).
     */
    public static function saveSubscription(array $data, ?string $userId = null, array $meta = []): array {
        $col = self::col();
        if (!$col) {
            throw new \RuntimeException('PushSubscription database collection unavailable.');
        }

        $endpoint = trim($data['endpoint'] ?? '');
        if (!self::isValidEndpoint($endpoint)) {
            throw new \InvalidArgumentException('Invalid push service endpoint.');
        }

        $keys = $data['keys'] ?? [];
        $p256dh = trim($keys['p256dh'] ?? ($data['p256dh'] ?? ''));
        $auth = trim($keys['auth'] ?? ($data['auth'] ?? ''));

        if (strlen($p256dh) < 20 || strlen($auth) < 10) {
            throw new \InvalidArgumentException('Invalid cryptographic subscription keys.');
        }

        $now = new \MongoDB\BSON\UTCDateTime();
        $isoNow = date('c');

        $doc = [
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'keys' => [
                'p256dh' => $p256dh,
                'auth' => $auth
            ],
            'device' => cleanString($meta['device'] ?? ($data['device'] ?? 'Unknown'), 100),
            'platform' => cleanString($meta['platform'] ?? ($data['platform'] ?? 'Unknown'), 100),
            'browser' => cleanString($meta['browser'] ?? ($data['browser'] ?? 'Browser'), 100),
            'userAgent' => cleanString($_SERVER['HTTP_USER_AGENT'] ?? ($meta['userAgent'] ?? ''), 500),
            'isActive' => true,
            'is_active' => true,
            'isDead' => false,
            'updatedAt' => $now,
            'lastActiveAt' => $now
        ];

        if (!empty($userId)) {
            $doc['userId'] = (string)$userId;
            $doc['user_id'] = (string)$userId;

            // Link trainerId if user is a trainer
            $trCol = getCollection("Trainer");
            if ($trCol) {
                $userMatches = [(string)$userId];
                try { $userMatches[] = new \MongoDB\BSON\ObjectId((string)$userId); } catch (\Throwable $e) {}
                $t = $trCol->findOne(['userId' => ['$in' => $userMatches]]);
                if ($t) {
                    $doc['trainerId'] = (string)$t['_id'];
                    $doc['userRole'] = 'TRAINER';
                }
            }
        }

        // Deactivate old endpoint if provided by client rotation
        if (!empty($data['oldEndpoint']) && $data['oldEndpoint'] !== $endpoint) {
            self::deactivate(trim($data['oldEndpoint']), 'REPLACED_BY_ROTATION');
        }

        // Upsert by endpoint
        $col->updateOne(
            ['endpoint' => $endpoint],
            [
                '$set' => $doc,
                '$setOnInsert' => [
                    'createdAt' => $now,
                    'created_at' => $isoNow,
                    'failureCount' => 0,
                    'lastSuccessAt' => null,
                    'lastFailureAt' => null
                ]
            ],
            ['upsert' => true]
        );

        return [
            'success' => true,
            'endpoint' => $endpoint,
            'isActive' => true
        ];
    }

    /**
     * Find a subscription record by endpoint
     */
    public static function findByEndpoint(string $endpoint): ?array {
        $col = self::col();
        if (!$col) return null;
        $sub = $col->findOne(['endpoint' => $endpoint]);
        return $sub ? (array)$sub : null;
    }

    /**
     * Find all active subscriptions for a specific user
     */
    public static function findActiveForUser(string $userId): array {
        $col = self::col();
        if (!$col || empty($userId)) return [];

        $userVariants = [(string)$userId];
        try { $userVariants[] = new \MongoDB\BSON\ObjectId((string)$userId); } catch (\Throwable $e) {}

        // Also check if user has a trainer ID
        $trainerVariants = [];
        $trCol = getCollection("Trainer");
        if ($trCol) {
            $t = $trCol->findOne(['userId' => ['$in' => $userVariants]]);
            if ($t) {
                $trainerVariants[] = (string)$t['_id'];
                try { $trainerVariants[] = new \MongoDB\BSON\ObjectId((string)$t['_id']); } catch (\Throwable $e) {}
            }
        }

        $orConditions = [
            ['userId' => ['$in' => $userVariants]],
            ['user_id' => ['$in' => $userVariants]]
        ];
        if (!empty($trainerVariants)) {
            $orConditions[] = ['trainerId' => ['$in' => $trainerVariants]];
            $orConditions[] = ['trainer_id' => ['$in' => $trainerVariants]];
        }

        $query = [
            'isActive' => true,
            'isDead' => ['$ne' => true],
            '$or' => $orConditions
        ];

        return $col->find($query)->toArray();
    }

    /**
     * Deactivate a subscription (e.g. on HTTP 404/410 from push service)
     */
    public static function deactivate(string $endpoint, string $reason = 'EXPIRED_OR_UNSUBSCRIBED'): bool {
        $col = self::col();
        if (!$col || empty($endpoint)) return false;

        $now = new \MongoDB\BSON\UTCDateTime();
        $col->updateOne(
            ['endpoint' => $endpoint],
            [
                '$set' => [
                    'isActive' => false,
                    'is_active' => false,
                    'isDead' => true,
                    'deactivationReason' => $reason,
                    'deactivatedAt' => $now,
                    'updatedAt' => $now
                ]
            ]
        );
        return true;
    }

    /**
     * Update successful delivery metrics for subscription
     */
    public static function recordSuccess(string $endpoint, int $statusCode = 201): void {
        try {
            $col = self::col();
            if (!$col || empty($endpoint)) return;

            $now = new \MongoDB\BSON\UTCDateTime();
            $col->updateOne(
                ['endpoint' => $endpoint],
                [
                    '$set' => [
                        'lastSuccessAt' => $now,
                        'lastActiveAt' => $now,
                        'lastStatusCode' => $statusCode,
                        'failureCount' => 0,
                        'isActive' => true
                    ]
                ]
            );
        } catch (\Throwable $e) {
            error_log('[PushRepo] Failed to record success: ' . $e->getMessage());
        }
    }

    /**
     * Update failed delivery metrics for subscription.
     * Deactivates only if $deactivate is true (e.g. 404/410).
     */
    public static function recordFailure(string $endpoint, int $statusCode, string $reason, bool $deactivate = false): void {
        try {
            $col = self::col();
            if (!$col || empty($endpoint)) return;

            $now = new \MongoDB\BSON\UTCDateTime();
            $update = [
                '$set' => [
                    'lastFailureAt' => $now,
                    'lastStatusCode' => $statusCode,
                    'lastFailureReason' => cleanString($reason, 200)
                ],
                '$inc' => ['failureCount' => 1]
            ];

            if ($deactivate) {
                $update['$set']['isActive'] = false;
                $update['$set']['is_active'] = false;
                $update['$set']['isDead'] = true;
                $update['$set']['deactivationReason'] = $reason;
                $update['$set']['deactivatedAt'] = $now;
            }

            $col->updateOne(['endpoint' => $endpoint], $update);
        } catch (\Throwable $e) {
            error_log('[PushRepo] Failed to record failure: ' . $e->getMessage());
        }
    }
}
