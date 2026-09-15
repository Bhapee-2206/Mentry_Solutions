<?php
// actions/push/check-subscription.php - Safe Subscription & VAPID Hash Verification Endpoint
// Returns SHA-256 hashes only. NEVER exposes endpoints, private keys, auth, or p256dh values.

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push/PushConfig.php';
require_once __DIR__ . '/../../includes/push/PushSubscriptionRepository.php';

try {
    // 1. Calculate Server VAPID Public Key Hash (computed on raw 65 EC bytes)
    $serverPubKey = PushConfig::getPublicKey();
    $rawBytes = base64_decode(strtr($serverPubKey, '-_', '+/'));
    $serverVapidRawBytesHash = hash('sha256', $rawBytes);
    $serverVapidStringHash = hash('sha256', $serverPubKey);

    // 2. Query Active Server Subscriptions
    $clientHash = trim($_GET['clientHash'] ?? ($_POST['clientHash'] ?? ''));
    $targetUserId = trim($_GET['userId'] ?? ($_POST['userId'] ?? ''));

    if (empty($targetUserId)) {
        $currentUser = getCurrentUser();
        $targetUserId = $currentUser['id'] ?? '';
    }

    $col = getCollection("PushSubscription");
    $serverSubs = [];
    $matched = false;

    if ($col) {
        $query = [
            'isActive' => true,
            'isDead' => ['$ne' => true]
        ];

        if (!empty($targetUserId)) {
            $userVariants = [$targetUserId];
            try { $userVariants[] = new \MongoDB\BSON\ObjectId($targetUserId); } catch (\Throwable $e) {}
            $query['$or'] = [
                ['userId' => ['$in' => $userVariants]],
                ['user_id' => ['$in' => $userVariants]],
                ['trainerId' => ['$in' => $userVariants]]
            ];
        }

        $cursor = $col->find($query, ['sort' => ['updatedAt' => -1], 'limit' => 10]);

        foreach ($cursor as $s) {
            $ep = $s['endpoint'] ?? '';
            $sHash = !empty($ep) ? hash('sha256', $ep) : '';
            $host = parse_url($ep, PHP_URL_HOST) ?: 'unknown';

            $serverSubs[] = [
                'subscriptionHash' => $sHash,
                'endpointHost' => $host,
                'device' => $s['meta']['device'] ?? ($s['device'] ?? 'Unknown'),
                'browser' => $s['meta']['browser'] ?? ($s['browser'] ?? 'Unknown'),
                'userId' => (string)($s['userId'] ?? ($s['trainerId'] ?? 'N/A')),
                'updatedAt' => isset($s['updatedAt']) ? (is_object($s['updatedAt']) ? $s['updatedAt']->toDateTime()->format('Y-m-d H:i:s') : (string)$s['updatedAt']) : 'N/A'
            ];

            if (!empty($clientHash) && hash_equals($sHash, $clientHash)) {
                $matched = true;
            }
        }
    }

    // If client provided a hash and no user match yet, check globally across all active subs
    if (!empty($clientHash) && !$matched && $col) {
        $allActive = $col->find(['isActive' => true, 'isDead' => ['$ne' => true]], ['limit' => 100]);
        foreach ($allActive as $as) {
            $ep = $as['endpoint'] ?? '';
            $sh = hash('sha256', $ep);
            if (hash_equals($sh, $clientHash)) {
                $matched = true;
                break;
            }
        }
    }

    $primaryServerHash = !empty($serverSubs) ? $serverSubs[0]['subscriptionHash'] : '';

    echo json_encode([
        'success' => true,
        'serverVapidPublicRawBytesHash' => $serverVapidRawBytesHash,
        'serverVapidPublicStringHash' => $serverVapidStringHash,
        'activeSubscriptionsCount' => count($serverSubs),
        'serverSubscriptionHash' => $primaryServerHash,
        'clientSubscriptionHash' => $clientHash,
        'match' => $matched,
        'serverSubscriptions' => $serverSubs
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
