<?php
// actions/check-subscription-status.php - Verify Client Push Subscription Validity & Server State
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/PushNotificationService.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];
$endpoint = cleanString($data['endpoint'] ?? ($_GET['endpoint'] ?? ($_POST['endpoint'] ?? '')), 2000);

try {
    $serverVapidKey = PushNotificationService::getPublicKey();
    $currentUser = getCurrentUser();
    $userId = $currentUser['id'] ?? null;

    if (empty($endpoint)) {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'active' => false,
            'isDead' => false,
            'vapidPublicKey' => $serverVapidKey,
            'requireRefresh' => true,
            'reason' => 'NO_ENDPOINT_PROVIDED'
        ]);
        exit();
    }

    $subCol = getCollection("PushSubscription");
    $sub = $subCol ? $subCol->findOne(['endpoint' => $endpoint]) : null;

    if (!$sub) {
        // Subscription not in DB at all: client must save/register
        echo json_encode([
            'success' => true,
            'exists' => false,
            'active' => false,
            'isDead' => false,
            'vapidPublicKey' => $serverVapidKey,
            'requireRefresh' => true,
            'reason' => 'NOT_FOUND_IN_DB'
        ]);
        exit();
    }

    $isDead = !empty($sub['isDead']) || in_array($sub['deactivationReason'] ?? '', [
        'PUSH_GATEWAY_CREDENTIALS_REJECTED_HTTP_403',
        'PUSH_GATEWAY_EXPIRED_HTTP_410',
        'FCM_VAPID_KEY_MISMATCH_HTTP_403',
        'HTTP_410_OR_404_EXPIRED'
    ]);

    $isActive = !empty($sub['isActive']) && !$isDead;
    $subUserId = (string)($sub['userId'] ?? '');

    // Check if the subscription belongs to current user
    $userMismatch = (!empty($userId) && !empty($subUserId) && (string)$userId !== $subUserId);

    $requireRefresh = $isDead || !$isActive || $userMismatch;
    $reason = 'ACTIVE_AND_VALID';
    if ($isDead) {
        $reason = 'REJECTED_BY_PUSH_GATEWAY';
    } elseif (!$isActive) {
        $reason = 'INACTIVE_IN_DB';
    } elseif ($userMismatch) {
        $reason = 'USER_REASSIGNMENT_NEEDED';
    }

    // Ping lastActiveAt if valid
    if ($isActive && !$userMismatch && $subCol) {
        $subCol->updateOne(
            ['endpoint' => $endpoint],
            ['$set' => ['lastActiveAt' => new MongoDB\BSON\UTCDateTime()]]
        );
    }

    echo json_encode([
        'success' => true,
        'exists' => true,
        'active' => $isActive,
        'isDead' => $isDead,
        'vapidPublicKey' => $serverVapidKey,
        'requireRefresh' => $requireRefresh,
        'reason' => $reason,
        'device' => $sub['device'] ?? 'Device'
    ]);
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
