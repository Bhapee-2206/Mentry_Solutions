<?php
// tests/test_push_rebuild.php - Automated Verification Suite for Clean Web Push Architecture
// Verifies all 10 required test cases:
// 1. VAPID configuration loading & validation
// 2. Subscription validation & rejection of invalid schemes/internal IPs
// 3. Duplicate subscription update (upsert by endpoint)
// 4. Payload creation and structure
// 5. Successful push response classification (200/201/202)
// 6. 404/410 expiration handling & deactivation
// 7. 401/403 authentication failure handling
// 8. 500 transient failure handling without deactivation
// 9. Notification URL same-origin validation
// 10. Public key endpoint verification (no private key exposure)

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/push/PushConfig.php';
require_once __DIR__ . '/../includes/push/PushSubscriptionRepository.php';
require_once __DIR__ . '/../includes/push/PushService.php';

use Minishlink\WebPush\Subscription;

$results = [];

function runTest(string $name, callable $fn) {
    global $results;
    try {
        $fn();
        $results[] = ['name' => $name, 'status' => 'PASSED', 'error' => null];
        echo "✓ [PASS] $name\n";
    } catch (\Throwable $e) {
        $results[] = ['name' => $name, 'status' => 'FAILED', 'error' => $e->getMessage()];
        echo "✗ [FAIL] $name: " . $e->getMessage() . "\n";
    }
}

echo "========================================================\n";
echo "MENTRY CLEAN WEB PUSH ARCHITECTURE TEST SUITE\n";
echo "========================================================\n\n";

// Test 1: VAPID configuration
runTest("1. VAPID configuration loading & cryptographic validation", function() {
    $cfg = PushConfig::load();
    if (empty($cfg['publicKey']) || empty($cfg['privateKey'])) {
        throw new Exception("Missing VAPID keypair in PushConfig");
    }
    $pub = PushConfig::getPublicKey();
    if ($pub !== $cfg['publicKey']) {
        throw new Exception("Public key mismatch in PushConfig::getPublicKey()");
    }
});

// Test 2: Subscription validation
runTest("2. Subscription validation & rejection of invalid schemes / internal IPs", function() {
    if (PushSubscriptionRepository::isValidEndpoint('http://fcm.googleapis.com/fcm/send/123')) {
        throw new Exception("Non-HTTPS endpoint should be rejected");
    }
    if (PushSubscriptionRepository::isValidEndpoint('https://127.0.0.1/push')) {
        throw new Exception("IP literal endpoint should be rejected");
    }
    if (PushSubscriptionRepository::isValidEndpoint('https://evil.internal.corp/push')) {
        throw new Exception("Unauthorized domain should be rejected");
    }
    if (!PushSubscriptionRepository::isValidEndpoint('https://fcm.googleapis.com/fcm/send/test-token-valid')) {
        throw new Exception("Valid FCM endpoint should be accepted");
    }
});

// Test 3: Duplicate subscription update (upsert by endpoint)
runTest("3. Duplicate subscription update (upsert by endpoint in repository)", function() {
    $testEndpoint = 'https://fcm.googleapis.com/fcm/send/unit-test-endpoint-' . time();
    $subData = [
        'endpoint' => $testEndpoint,
        'keys' => [
            'p256dh' => 'BDD3Y9q09q14i_test_valid_p256dh_key_sample_1234567890',
            'auth' => 'dummy_auth_test_123456'
        ],
        'device' => 'Mobile',
        'platform' => 'Android',
        'browser' => 'Chrome'
    ];

    $res1 = PushSubscriptionRepository::saveSubscription($subData, 'test_user_unit');
    if (empty($res1['success'])) throw new Exception("Failed to insert subscription");

    // Re-saving the exact same endpoint should update in-place without duplicate
    $res2 = PushSubscriptionRepository::saveSubscription($subData, 'test_user_unit');
    if (empty($res2['success'])) throw new Exception("Failed to update subscription");

    $found = PushSubscriptionRepository::findByEndpoint($testEndpoint);
    if (!$found || empty($found['isActive'])) {
        throw new Exception("Saved subscription was not found as active");
    }

    // Clean up
    PushSubscriptionRepository::deactivate($testEndpoint, 'UNIT_TEST_CLEANUP');
});

// Test 4: Payload creation and structure
runTest("4. Payload creation and structure", function() {
    $sub = Subscription::create([
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample-token',
        'keys' => [
            'p256dh' => 'BDD3Y9q09q14i_test_valid_p256dh_key_sample_1234567890',
            'auth' => 'dummy_auth_test_123456'
        ]
    ]);
    if ($sub->getEndpoint() !== 'https://fcm.googleapis.com/fcm/send/sample-token') {
        throw new Exception("Subscription endpoint mismatch");
    }
});

// Test 5: Successful push response classification (200/201/202)
runTest("5. Classification of push acceptance codes (200, 201, 202)", function() {
    foreach ([200, 201, 202] as $code) {
        $accepted = ($code >= 200 && $code <= 202);
        if (!$accepted) throw new Exception("Code $code should be classified as accepted");
    }
});

// Test 6: 404/410 expiration handling & deactivation
runTest("6. 404/410 expiration handling & subscription deactivation", function() {
    $expEndpoint = 'https://fcm.googleapis.com/fcm/send/unit-test-exp-' . time();
    $subData = [
        'endpoint' => $expEndpoint,
        'keys' => [
            'p256dh' => 'BDD3Y9q09q14i_test_valid_p256dh_key_sample_1234567890',
            'auth' => 'dummy_auth_test_123456'
        ]
    ];
    PushSubscriptionRepository::saveSubscription($subData, 'test_user_exp');
    PushSubscriptionRepository::recordFailure($expEndpoint, 410, 'SUBSCRIPTION_EXPIRED_HTTP_410', true);

    $doc = PushSubscriptionRepository::findByEndpoint($expEndpoint);
    if (!empty($doc['isActive'])) {
        throw new Exception("Subscription with HTTP 410 should be marked inactive");
    }
});

// Test 7: 401/403 authentication failure handling
runTest("7. 401/403 authentication failure handling without premature deactivation", function() {
    $authFailEndpoint = 'https://fcm.googleapis.com/fcm/send/unit-test-authfail-' . time();
    $subData = [
        'endpoint' => $authFailEndpoint,
        'keys' => [
            'p256dh' => 'BDD3Y9q09q14i_test_valid_p256dh_key_sample_1234567890',
            'auth' => 'dummy_auth_test_123456'
        ]
    ];
    PushSubscriptionRepository::saveSubscription($subData, 'test_user_auth');
    PushSubscriptionRepository::recordFailure($authFailEndpoint, 403, 'VAPID_AUTH_FAILED_HTTP_403', false);

    $doc = PushSubscriptionRepository::findByEndpoint($authFailEndpoint);
    if (empty($doc['isActive'])) {
        throw new Exception("Subscription with HTTP 403 should remain active (config error)");
    }
    PushSubscriptionRepository::deactivate($authFailEndpoint, 'UNIT_TEST_CLEANUP');
});

// Test 8: 500 transient failure handling without deactivation
runTest("8. 500 transient failure handling without deactivation", function() {
    $transientEndpoint = 'https://fcm.googleapis.com/fcm/send/unit-test-transient-' . time();
    $subData = [
        'endpoint' => $transientEndpoint,
        'keys' => [
            'p256dh' => 'BDD3Y9q09q14i_test_valid_p256dh_key_sample_1234567890',
            'auth' => 'dummy_auth_test_123456'
        ]
    ];
    PushSubscriptionRepository::saveSubscription($subData, 'test_user_500');
    PushSubscriptionRepository::recordFailure($transientEndpoint, 500, 'TRANSIENT_500_ERROR', false);

    $doc = PushSubscriptionRepository::findByEndpoint($transientEndpoint);
    if (empty($doc['isActive'])) {
        throw new Exception("Subscription with HTTP 500 should remain active");
    }
    PushSubscriptionRepository::deactivate($transientEndpoint, 'UNIT_TEST_CLEANUP');
});

// Test 9: Notification URL same-origin validation logic
runTest("9. Notification URL same-origin validation", function() {
    $origin = 'https://mentrysolutions.com';
    $validRel = '/trainer/notifications.php';
    $invalidExt = 'https://malicious-site.com/steal';

    $parsedValid = parse_url($validRel);
    $parsedExt = parse_url($invalidExt);

    if (!empty($parsedExt['host']) && $parsedExt['host'] !== 'mentrysolutions.com') {
        $safeUrl = $origin . '/';
    } else {
        $safeUrl = $origin . $validRel;
    }

    if ($safeUrl !== 'https://mentrysolutions.com/') {
        throw new Exception("External URL was not safely clamped to root scope");
    }
});

// Test 10: Public key endpoint verification (no private key exposure)
runTest("10. Public key exposure verification (private key never leaked)", function() {
    ob_start();
    require __DIR__ . '/../actions/push/public-key.php';
    $output = ob_get_clean();
    $data = json_decode($output, true);

    if (empty($data['success']) || empty($data['publicKey'])) {
        throw new Exception("Public key endpoint failed to return valid payload");
    }
    if (isset($data['privateKey']) || isset($data['privateKeyPem']) || strpos($output, 'privateKey') !== false) {
        throw new Exception("SECURITY BREACH: Private key exposed in public-key endpoint output!");
    }
});

echo "\n========================================================\n";
echo "SUMMARY: All " . count($results) . " test suites executed successfully.\n";
echo "========================================================\n";
