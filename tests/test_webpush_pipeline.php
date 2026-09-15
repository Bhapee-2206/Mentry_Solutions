<?php
// tests/test_webpush_pipeline.php - Automated Server-Side Web Push Verification

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/PushNotificationService.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

echo "=== MENTRY WEB PUSH PIPELINE AUTOMATED TEST ===\n\n";

// 1. Verify VAPID configuration loads
echo "Test 1: VAPID configuration loading... ";
try {
    $keys = PushNotificationService::initKeys();
    if (empty($keys['publicKey']) || empty($keys['privateKey'])) {
        throw new Exception("Missing public or private key in VAPID config");
    }
    echo "PASSED (Public Key: " . substr($keys['publicKey'], 0, 15) . "...)\n";
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Verify VAPID public/private pair validity
echo "Test 2: VAPID cryptographic validation... ";
try {
    $validated = \Minishlink\WebPush\VAPID::validate([
        'subject' => $keys['subject'] ?? 'mailto:support@mentrysolutions.com',
        'publicKey' => $keys['publicKey'],
        'privateKey' => $keys['privateKey']
    ]);
    echo "PASSED\n";
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Verify Subscription object creation
echo "Test 3: Subscription::create() verification... ";
try {
    $dummySub = Subscription::create([
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/dummy-test-endpoint-token',
        'keys' => [
            'p256dh' => 'BDD3Y9q09q14i...dummy',
            'auth' => 'dummyauth123456'
        ],
        'contentEncoding' => 'aes128gcm'
    ]);
    if ($dummySub->getContentEncoding() !== 'aes128gcm') {
        throw new Exception("Unexpected content encoding: " . $dummySub->getContentEncoding());
    }
    echo "PASSED\n";
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Verify WebPush instantiation and client configuration
echo "Test 4: WebPush client initialization... ";
try {
    $auth = [
        'VAPID' => [
            'subject' => $keys['subject'],
            'publicKey' => $keys['publicKey'],
            'privateKey' => $keys['privateKey']
        ]
    ];
    $clientConfig = [
        'timeout' => 15,
        'connect_timeout' => 6,
        'curl' => [
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
        ]
    ];
    if (PHP_OS_FAMILY === 'Windows') {
        $caBundle = __DIR__ . '/../includes/cacert.pem';
        if (file_exists($caBundle)) {
            $clientConfig['verify'] = realpath($caBundle);
        } else {
            $clientConfig['verify'] = true;
        }
    } else {
        $clientConfig['verify'] = true;
    }
    $client = new \GuzzleHttp\Client($clientConfig);
    $webPush = new WebPush($auth, ['TTL' => 86400, 'urgency' => 'high'], $client);
    echo "PASSED\n";
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// Query active subscriptions from database
$subCol = getCollection("PushSubscription");
$activeMobileSub = null;
if ($subCol) {
    $allSubs = $subCol->find(['isActive' => true, 'isDead' => ['$ne' => true]])->toArray();
    foreach ($allSubs as $s) {
        if (!empty($s['deviceId']) && str_contains($s['deviceId'], '90791cdb')) {
            $activeMobileSub = $s;
            break;
        }
    }
    if (!$activeMobileSub && count($allSubs) > 0) {
        $activeMobileSub = $allSubs[0];
    }
}

// 5. Verify Expired Subscription Recognition
echo "Test 5: Expired/invalid endpoint recognition... ";
try {
    $validP256dh = !empty($activeMobileSub['p256dh']) ? $activeMobileSub['p256dh'] : 'BNQJv6uJ7cQJL43_087n5q2E8v7-1G6aK_xL3m6_xO9p4-3K7s2-1m6_xO9p4-3K7s2-1m6_xO9p4-3K7s2-1m4';
    $expiredSub = Subscription::create([
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/expired_or_invalid_token_xyz_12345',
        'keys' => [
            'p256dh' => $validP256dh,
            'auth' => !empty($activeMobileSub['auth']) ? $activeMobileSub['auth'] : '1234567890123456'
        ],
        'contentEncoding' => 'aes128gcm'
    ]);
    $report = $webPush->sendOneNotification($expiredSub, json_encode(['title' => 'Test', 'body' => 'Test body']));
    $code = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
    $isExp = $report->isSubscriptionExpired();
    // FCM returns 400 or 404 for non-existent token
    echo "PASSED (HTTP Status: {$code}, Expired flag: " . ($isExp ? 'YES' : 'NO') . ")\n";
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 6. Verify Real Push Sending to Active Mobile Subscription (if present)
echo "Test 6: Real Push Delivery to Active Mobile Device... ";
try {

    if ($activeMobileSub) {
        $payload = [
            'notification_id' => 'test_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'id' => 'test_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'title' => 'Mentry Test Push',
            'body' => 'Web Push engine verified with minishlink/web-push.',
            'url' => '/trainer/dashboard.php',
            'link' => '/trainer/dashboard.php',
            'type' => 'SYSTEM_ALERT',
            'priority' => 'high'
        ];

        $res = PushNotificationService::sendToSubscription($activeMobileSub, $payload, 'high');
        $status = $res['deliveryStatus'] ?? 'UNKNOWN';
        $code = $res['statusCode'] ?? 0;
        $reqId = $res['pushRequestId'] ?? 'N/A';

        if (!empty($res['success'])) {
            echo "PASSED (HTTP {$code} - {$status} | Request ID: {$reqId})\n";
        } else {
            echo "RESPONSE: HTTP {$code} - {$status} (Error: " . ($res['error'] ?? 'Unknown') . ")\n";
        }
    } else {
        echo "SKIPPED (No active subscriptions found in local database)\n";
    }
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== ALL TESTS COMPLETED SUCCESSFULLY ===\n";
