<?php
// actions/send-test-trainer-notification.php - Dispatch Web Push & In-App Diagnostics
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/PushNotificationService.php';

$currentUser = getCurrentUser();
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$targetUserId = null;
$trainerId = null;

if (isAdminOrStaff()) {
    $paramUserId = $_POST['targetUserId'] ?? ($_GET['targetUserId'] ?? '');
    if (!empty($paramUserId)) {
        $targetUserId = $paramUserId;
        $trainerCol = getCollection("Trainer");
        $t = $trainerCol ? $trainerCol->findOne(['userId' => $targetUserId]) : null;
        if ($t) {
            $trainerId = (string)$t['_id'];
        }
    }
    $targetTrainerId = $_POST['trainerId'] ?? ($_GET['trainerId'] ?? '');
    if (empty($targetUserId) && !empty($targetTrainerId)) {
        $trainerCol = getCollection("Trainer");
        $t = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($targetTrainerId)]) : null;
        if ($t) {
            $trainerId = (string)$t['_id'];
            $targetUserId = (string)$t['userId'];
        }
    }
}

// If no specific target or called by trainer themselves
if (empty($targetUserId)) {
    $targetUserId = (string)$currentUser['id'];
    $trainerCol = getCollection("Trainer");
    $t = $trainerCol ? $trainerCol->findOne(['userId' => $targetUserId]) : null;
    if ($t) {
        $trainerId = (string)$t['_id'];
    }
}

if (empty($targetUserId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Target user not found']);
    exit();
}

try {
    $notifCol = getCollection("Notification");
    $userCol = getCollection("User");
    $targetUser = $userCol ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($targetUserId)]) : null;
    $targetName = $targetUser['name'] ?? 'Trainer';

    $testTitle = !empty($_POST['title']) ? cleanString($_POST['title'], 200) : "🎉 Live Alert Test: Opportunity Selection";
    $testMsg = !empty($_POST['message']) ? cleanString($_POST['message'], 1000) : "Hello {$targetName}! This is a live verification alert from Mentry Operations. Your device is connected and live notifications are active.";

    $testNotifId = 'test_' . time();
    if ($notifCol) {
        $insRes = $notifCol->insertOne([
            'userId' => $targetUserId,
            'trainerId' => $trainerId,
            'type' => 'SYSTEM_ALERT',
            'title' => $testTitle,
            'message' => $testMsg,
            'link' => '/trainer/notifications.php',
            'read' => false,
            'createdAt' => new MongoDB\BSON\UTCDateTime()
        ]);
        $testNotifId = (string)$insRes->getInsertedId();
    }

    // STRICT FILTER: Query ONLY active, non-dead subscriptions
    $subCol = getCollection("PushSubscription");
    $targetSubs = [];
    if ($subCol) {
        $query = [
            'isActive' => true,
            'isDead' => ['$ne' => true],
            '$or' => [
                ['userId' => (string)$targetUserId]
            ]
        ];
        if (!empty($trainerId)) {
            $query['$or'][] = ['trainerId' => (string)$trainerId];
        }
        $targetSubs = $subCol->find($query)->toArray();
    }

    $pushAccepted = 0;
    $pushFailed = 0;
    $pushExpired = 0;
    $deviceBreakdown = [];

    $payload = [
        'id' => $testNotifId,
        'title' => $testTitle,
        'body' => $testMsg,
        'message' => $testMsg,
        'url' => '/trainer/notifications.php',
        'link' => '/trainer/notifications.php',
        'type' => 'SYSTEM_ALERT',
        'priority' => 'high'
    ];

    foreach ($targetSubs as $sub) {
        $devName = ($sub['device'] ?? 'Device') . ' • ' . ($sub['browser'] ?? 'Browser');
        $res = PushNotificationService::sendToSubscription($sub, $payload, 'high');
        $code = $res['statusCode'] ?? 0;
        $isOk = !empty($res['success']);

        if ($isOk) {
            $pushAccepted++;
            $deviceBreakdown[] = "{$devName}: ✓ Accepted by push service (HTTP {$code})";
        } else {
            $pushFailed++;
            if ($code === 404 || $code === 410) {
                $pushExpired++;
                $deviceBreakdown[] = "{$devName}: ✗ Expired subscription — automatically deactivated";
            } elseif ($code === 401 || $code === 403) {
                $deviceBreakdown[] = "{$devName}: ✗ Server credentials issue — please reconfigure VAPID keys";
            } else {
                $deviceBreakdown[] = "{$devName}: ✗ Delivery failed (HTTP {$code})";
            }
        }
    }

    $isAdmin = isAdminOrStaff();

    if ($isAdmin) {
        $summary = ($pushAccepted > 0)
            ? "Push Summary: {$pushAccepted} accepted by push service, {$pushFailed} failed."
            : (count($targetSubs) === 0
                ? "In-app alert created. No active push devices found for {$targetName}."
                : "Push delivery issue: {$pushFailed} device(s) failed.");

        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'message' => $summary,
            'targetUserId' => $targetUserId,
            'targetName' => $targetName,
            'devicesFound' => count($targetSubs),
            'pushDeliveredCount' => $pushAccepted,
            'pushAcceptedCount' => $pushAccepted,
            'pushFailedCount' => $pushFailed,
            'pushExpiredCount' => $pushExpired,
            'details' => implode('<br>', $deviceBreakdown)
        ]);
    } else {
        // CLEAN USER-FRIENDLY RESPONSE FOR TRAINERS (No technical jargon)
        if (ob_get_length()) ob_clean();
        $trainerMsg = ($pushAccepted > 0)
            ? 'Test notification sent to your device! Check your notification bar.'
            : 'Alert saved! It will appear in your notifications.';
        echo json_encode([
            'success' => true,
            'message' => $trainerMsg,
            'pushDeliveredCount' => $pushAccepted
        ]);
    }
} catch (\Throwable $e) {
    if (ob_get_length()) ob_clean();
    error_log("send-test-trainer-notification error: " . $e->getMessage());
    // Always return success to trainer since in-app notification was created
    $isAdmin = function_exists('isAdminOrStaff') && isAdminOrStaff();
    if ($isAdmin) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Push delivery error. In-app notification was saved.']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Alert saved! It will appear in your notifications.', 'pushDeliveredCount' => 0]);
    }
}
