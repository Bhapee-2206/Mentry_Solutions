<?php
// includes/push/NativePushTokenRepository.php - Data Layer for Native Android FCM Tokens
// Stores and manages FCM tokens per user/device in MongoDB NativePushToken collection.
// Supports multiple devices per user, token rotation, and secure user-session unlinking.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

class NativePushTokenRepository {
    private static string $collectionName = 'NativePushToken';

    private static function col() {
        return getCollection(self::$collectionName);
    }

    /**
     * Validate an FCM registration token format.
     */
    public static function isValidToken(string $token): bool {
        $token = trim($token);
        // FCM tokens are typically 140-200+ characters base64-like strings
        return strlen($token) >= 64 && strlen($token) <= 4096 && preg_match('/^[a-zA-Z0-9_\-:\.]+$/', $token);
    }

    /**
     * Register or update an FCM token for an authenticated user.
     *
     * @param string $userId Authenticated Mentry User ID
     * @param string $fcmToken Valid FCM registration token
     * @param array $meta Device metadata (deviceModel, androidVersion, appVersion, installationId)
     * @return array [ 'success' => bool, 'tokenHash' => string ]
     */
    public static function registerToken(string $userId, string $fcmToken, array $meta = []): array {
        $col = self::col();
        if (!$col) {
            throw new \RuntimeException('NativePushToken database collection unavailable.');
        }

        $fcmToken = trim($fcmToken);
        if (!self::isValidToken($fcmToken)) {
            throw new \InvalidArgumentException('Invalid FCM registration token.');
        }

        $now = new \MongoDB\BSON\UTCDateTime();
        $isoNow = date('c');
        $tokenHash = hash('sha256', $fcmToken);

        $doc = [
            'fcmToken' => $fcmToken,
            'tokenHash' => $tokenHash,
            'userId' => (string)$userId,
            'user_id' => (string)$userId,
            'platform' => 'android',
            'deviceModel' => cleanString($meta['deviceModel'] ?? 'Android Device', 100),
            'androidVersion' => cleanString($meta['androidVersion'] ?? 'Unknown', 50),
            'appVersion' => cleanString($meta['appVersion'] ?? '1.0.0', 50),
            'installationId' => cleanString($meta['installationId'] ?? '', 100),
            'isActive' => true,
            'isDead' => false,
            'updatedAt' => $now,
            'lastSeenAt' => $now
        ];

        // Link trainerId if user is a trainer
        $trCol = getCollection("Trainer");
        if ($trCol) {
            $userVariants = [(string)$userId];
            try { $userVariants[] = new \MongoDB\BSON\ObjectId((string)$userId); } catch (\Throwable $e) {}
            $t = $trCol->findOne([
                '$or' => [
                    ['_id' => ['$in' => $userVariants]],
                    ['userId' => ['$in' => $userVariants]],
                    ['user_id' => ['$in' => $userVariants]]
                ]
            ]);
            if ($t) {
                if (!empty($t['userId'])) {
                    $doc['userId'] = (string)$t['userId'];
                    $doc['user_id'] = (string)$t['userId'];
                }
                $doc['trainerId'] = (string)$t['_id'];
                $doc['trainer_id'] = (string)$t['_id'];
                $doc['userRole'] = 'TRAINER';
            }
        }

        // Upsert by fcmToken so a single device never duplicates
        $col->updateOne(
            ['fcmToken' => $fcmToken],
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
            'tokenHash' => substr($tokenHash, 0, 16),
            'userId' => (string)$userId
        ];
    }

    /**
     * Find all active FCM tokens for a user/trainer.
     */
    public static function findActiveForUser(string $userId): array {
        $col = self::col();
        if (!$col || empty($userId)) return [];

        $userVariants = [(string)$userId];
        try { $userVariants[] = new \MongoDB\BSON\ObjectId((string)$userId); } catch (\Throwable $e) {}

        $trainerVariants = [(string)$userId];
        try { $trainerVariants[] = new \MongoDB\BSON\ObjectId((string)$userId); } catch (\Throwable $e) {}

        $trCol = getCollection("Trainer");
        if ($trCol) {
            $t = $trCol->findOne([
                '$or' => [
                    ['_id' => ['$in' => $userVariants]],
                    ['userId' => ['$in' => $userVariants]],
                    ['user_id' => ['$in' => $userVariants]]
                ]
            ]);
            if ($t) {
                if (!empty($t['userId'])) {
                    $uStr = (string)$t['userId'];
                    $userVariants[] = $uStr;
                    try { $userVariants[] = new \MongoDB\BSON\ObjectId($uStr); } catch (\Throwable $e) {}
                }
                $tStr = (string)$t['_id'];
                $trainerVariants[] = $tStr;
                try { $trainerVariants[] = new \MongoDB\BSON\ObjectId($tStr); } catch (\Throwable $e) {}
            }
        }

        $allIdVariants = array_values(array_unique(array_merge($userVariants, $trainerVariants), SORT_REGULAR));

        $query = [
            'isActive' => true,
            'isDead' => ['$ne' => true],
            '$or' => [
                ['userId' => ['$in' => $allIdVariants]],
                ['user_id' => ['$in' => $allIdVariants]],
                ['trainerId' => ['$in' => $allIdVariants]],
                ['trainer_id' => ['$in' => $allIdVariants]]
            ]
        ];

        return $col->find($query)->toArray();
    }

    /**
     * Deactivate an FCM token (e.g. on UNREGISTERED or 404 from Firebase).
     */
    public static function deactivateToken(string $fcmToken, string $reason = 'UNREGISTERED_BY_FCM'): bool {
        $col = self::col();
        if (!$col || empty($fcmToken)) return false;

        $now = new \MongoDB\BSON\UTCDateTime();
        $col->updateOne(
            ['fcmToken' => $fcmToken],
            [
                '$set' => [
                    'isActive' => false,
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
     * Unlink a token or installation upon logout (User Switching security).
     * Prevents User B from receiving User A's notifications.
     */
    public static function unlinkUser(string $userId, ?string $fcmToken = null, ?string $installationId = null): bool {
        $col = self::col();
        if (!$col || empty($userId)) return false;

        $conditions = [
            '$or' => [
                ['userId' => (string)$userId],
                ['user_id' => (string)$userId]
            ]
        ];

        if (!empty($fcmToken)) {
            $conditions['fcmToken'] = $fcmToken;
        } elseif (!empty($installationId)) {
            $conditions['installationId'] = $installationId;
        }

        $now = new \MongoDB\BSON\UTCDateTime();
        $col->updateMany(
            $conditions,
            [
                '$set' => [
                    'isActive' => false,
                    'deactivationReason' => 'USER_LOGOUT_UNLINK',
                    'updatedAt' => $now
                ]
            ]
        );
        return true;
    }

    /**
     * Record a successful send to update statistics.
     */
    public static function recordSuccess(string $fcmToken): void {
        $col = self::col();
        if (!$col || empty($fcmToken)) return;

        $now = new \MongoDB\BSON\UTCDateTime();
        $col->updateOne(
            ['fcmToken' => $fcmToken],
            [
                '$set' => ['lastSuccessAt' => $now, 'lastSeenAt' => $now, 'isActive' => true, 'isDead' => false],
                '$inc' => ['successCount' => 1]
            ]
        );
    }

    /**
     * Record a send failure.
     */
    public static function recordFailure(string $fcmToken, string $reason, bool $isDead = false): void {
        $col = self::col();
        if (!$col || empty($fcmToken)) return;

        $now = new \MongoDB\BSON\UTCDateTime();
        $update = [
            '$set' => ['lastFailureAt' => $now, 'lastFailureReason' => $reason],
            '$inc' => ['failureCount' => 1]
        ];

        if ($isDead) {
            $update['$set']['isActive'] = false;
            $update['$set']['isDead'] = true;
            $update['$set']['deactivationReason'] = $reason;
        }

        $col->updateOne(['fcmToken' => $fcmToken], $update);
    }
}
