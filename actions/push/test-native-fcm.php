<?php
// actions/push/test-native-fcm.php - Admin-Only Native Android FCM Test Dispatcher
// Dispatches native test notification through Firebase Cloud Messaging HTTP v1.

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push/NativeFcmService.php';
require_once __DIR__ . '/../../includes/push/NativePushTokenRepository.php';

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

$tokens = NativePushTokenRepository::findActiveForUser($targetUserId);
if (empty($tokens)) {
    echo json_encode([
        'success' => false,
        'fcmAccepted' => false,
        'statusCode' => 0,
        'tokenCount' => 0,
        'error' => 'No active Android FCM devices registered for this user.'
    ]);
    exit();
}

$testNotificationId = 'native_test_' . bin2hex(random_bytes(6)) . '_' . time();
$payload = [
    'id' => $testNotificationId,
    'title' => 'Mentry Native Test',
    'body' => 'Native Android background notification test via FCM HTTP v1.',
    'url' => '/trainer/notifications.php',
    'type' => 'TEST'
];

$res = NativeFcmService::sendToUser($targetUserId, $payload);
$primaryResult = !empty($res['results']) ? $res['results'][0] : null;

echo json_encode([
    'success' => $res['sent'],
    'fcmAccepted' => $res['sent'],
    'statusCode' => $primaryResult['statusCode'] ?? ($res['sent'] ? 200 : 500),
    'reason' => $primaryResult['reason'] ?? ($res['sent'] ? 'Accepted by FCM' : 'Dispatch failed'),
    'tokenCount' => $res['tokenCount'],
    'acceptedCount' => $res['acceptedCount'],
    'failedCount' => $res['failedCount'],
    'messageId' => $primaryResult['messageId'] ?? '',
    'testId' => $testNotificationId,
    'tokenHash' => $primaryResult['tokenHash'] ?? 'N/A'
]);
