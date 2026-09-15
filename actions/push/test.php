<?php
// actions/push/test.php - Admin-Only Clean Push Test Endpoint
// Inputs: trainer/user ID. Dispatches test push and returns exact push service response.

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push/PushService.php';
require_once __DIR__ . '/../../includes/push/PushSubscriptionRepository.php';

if (!isAdminOrStaff()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin or Staff access required.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

$targetUserId = trim($_POST['userId'] ?? ($_GET['userId'] ?? ''));
$targetTrainerId = trim($_POST['trainerId'] ?? ($_GET['trainerId'] ?? ''));

if (empty($targetUserId) && !empty($targetTrainerId)) {
    $trCol = getCollection("Trainer");
    if ($trCol) {
        $trVariants = [$targetTrainerId];
        try { $trVariants[] = new \MongoDB\BSON\ObjectId($targetTrainerId); } catch (\Throwable $e) {}
        $t = $trCol->findOne(['_id' => ['$in' => $trVariants]]);
        if ($t && !empty($t['userId'])) {
            $targetUserId = (string)$t['userId'];
        }
    }
}

if (empty($targetUserId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Target user or trainer ID is required.']);
    exit();
}

$subscriptions = PushSubscriptionRepository::findActiveForUser($targetUserId);

if (empty($subscriptions)) {
    echo json_encode([
        'success' => false,
        'pushServiceAccepted' => false,
        'statusCode' => 0,
        'subscriptionCount' => 0,
        'error' => 'No active push subscriptions registered for this user.'
    ]);
    exit();
}

$isBackgroundTest = !empty($_POST['isBackgroundTest']) || !empty($_GET['isBackgroundTest']);
$customTestId = trim($_POST['testId'] ?? ($_GET['testId'] ?? ''));
$testNotificationId = !empty($customTestId) ? $customTestId : (($isBackgroundTest ? 'background_test_' : 'test_') . bin2hex(random_bytes(6)) . '_' . time());

if ($isBackgroundTest) {
    $payload = [
        'id' => $testNotificationId,
        'title' => 'Mentry Background Test',
        'body' => 'This notification was generated while Mentry was closed.',
        'url' => '/trainer/notifications.php',
        'type' => 'BACKGROUND_TEST'
    ];
} else {
    $payload = [
        'id' => $testNotificationId,
        'title' => 'Mentry Test Notification',
        'body' => 'Web Push is working correctly.',
        'url' => '/trainer/notifications.php',
        'type' => 'TEST'
    ];
}

$res = PushService::sendToUser($targetUserId, $payload, 'high');

$primaryResult = !empty($res['results']) ? $res['results'][0] : null;
$statusCode = $primaryResult['statusCode'] ?? ($res['sent'] ? 201 : 500);
$reason = $primaryResult['reason'] ?? ($res['sent'] ? 'Accepted by push service' : 'Dispatch failed');

echo json_encode([
    'success' => $res['sent'],
    'pushServiceAccepted' => $res['sent'],
    'statusCode' => $statusCode,
    'reason' => $reason,
    'subscriptionCount' => $res['subscriptionCount'],
    'acceptedCount' => $res['acceptedCount'],
    'failedCount' => $res['failedCount'],
    'testId' => $testNotificationId
]);
