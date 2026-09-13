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
    $targetTrainerId = $_POST['trainerId'] ?? ($_GET['trainerId'] ?? '');
    if (!empty($targetTrainerId)) {
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

    $testTitle = "🎉 Live Alert Test: Opportunity Selection";
    $testMsg = "Hello {$targetName}! This is a live verification alert from Mentry Operations. Your device is connected and live notifications are active.";

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

    // Also dispatch push notification
    @dispatchWebPushNotification(
        ['userId' => $targetUserId],
        $testTitle,
        $testMsg,
        '/trainer/notifications.php',
        ['id' => $testNotifId, 'type' => 'SYSTEM_ALERT']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Live test notification dispatched successfully. Will pop on mobile device within seconds.',
        'targetUserId' => $targetUserId,
        'targetName' => $targetName
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
