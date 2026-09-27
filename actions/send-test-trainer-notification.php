<?php
// actions/send-test-trainer-notification.php - In-App Notification Test Dispatcher
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

$currentUser = getCurrentUser();
$userId = (string)($currentUser['id'] ?? ($currentUser['_id'] ?? ''));

// If admin is testing for a specific trainer
if (isAdminOrStaff() && !empty($_POST['trainerId'])) {
    $trCol = getCollection("Trainer");
    if ($trCol) {
        $trVariants = [$_POST['trainerId']];
        try { $trVariants[] = new \MongoDB\BSON\ObjectId($_POST['trainerId']); } catch (\Throwable $e) {}
        $t = $trCol->findOne(['_id' => ['$in' => $trVariants]]);
        if ($t && !empty($t['userId'])) {
            $userId = (string)$t['userId'];
        }
    }
}

if (empty($userId)) {
    echo json_encode(['success' => false, 'error' => 'Target user not found']);
    exit();
}

$title = trim($_POST['title'] ?? '🔔 In-App Notification Test');
$message = trim($_POST['message'] ?? 'In-app notifications are working properly.');

$notifCol = getCollection("Notification");
if ($notifCol) {
    $notifCol->insertOne([
        'userId' => $userId,
        'type' => 'SYSTEM_ALERT',
        'title' => $title,
        'message' => $message,
        'link' => '/trainer/notifications.php',
        'read' => false,
        'createdAt' => new MongoDB\BSON\UTCDateTime()
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'In-app test notification created successfully.',
        'pushAcceptedCount' => 0,
        'inAppDelivered' => true
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Notification database unavailable']);
