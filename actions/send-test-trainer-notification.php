<?php
// actions/send-test-trainer-notification.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

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

    // Dispatch Web Push notification to trainer devices
    require_once __DIR__ . '/../includes/PushNotificationService.php';
    $subCol = getCollection("PushSubscription");

    $targetSubs = [];
    if ($subCol) {
        $allSubs = $subCol->find([])->toArray();
        foreach ($allSubs as $s) {
            if (($s['isActive'] ?? true) === false || !empty($s['isDead'])) continue;
            $sUid = (string)($s['userId'] ?? '');
            $sTid = (string)($s['trainerId'] ?? '');
            if ($sUid === (string)$targetUserId || (!empty($trainerId) && $sTid === (string)$trainerId)) {
                $targetSubs[] = $s;
            }
        }
    }

    $pushDelivered = 0;
    $pushFailed = 0;
    $deliveryDetails = [];

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
        $res = PushNotificationService::sendToSubscription($sub, $payload, 'high');
        $code = $res['statusCode'] ?? 0;
        $isOk = !empty($res['success']);
        if ($isOk) {
            $pushDelivered++;
            $deliveryDetails[] = "Push Delivered (HTTP {$code}) to " . ($sub['device'] ?? 'Device');
        } else {
            $pushFailed++;
            $subId = (string)($sub['_id'] ?? 'unknown');
            if ($code === 403 || $code === 401) {
                $deliveryDetails[] = "Push failed | Reason: Subscription rejected by push service | HTTP: {$code} | Subscription: inactive | Action: subscription will be refreshed on next device visit";
            } elseif ($code === 404 || $code === 410) {
                $deliveryDetails[] = "Push failed | Reason: Subscription expired or unregistered | HTTP: {$code} | Subscription: inactive | Action: device will re-subscribe";
            } else {
                $errText = $res['error'] ?: 'Gateway connection failure';
                $deliveryDetails[] = "Push failed | Reason: {$errText} | HTTP: {$code} | Subscription ID: {$subId}";
            }
        }
    }

    $msg = ($pushDelivered > 0)
        ? "Push Delivered: {$pushDelivered} device(s) confirmed via Web Push (HTTP 201)."
        : (count($targetSubs) === 0 
            ? "In-app alert created. No active push subscriptions found for this trainer (or previous expired token pending renewal on device)."
            : "In-app alert created. Push delivery issue: " . implode(' | ', $deliveryDetails));


    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => true,
        'message' => $msg,
        'targetUserId' => $targetUserId,
        'targetName' => $targetName,
        'devicesFound' => count($targetSubs),
        'pushDeliveredCount' => $pushDelivered,
        'pushFailedCount' => $pushFailed,
        'details' => implode('<br>', $deliveryDetails)
    ]);
} catch (\Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
