<?php
// actions/push/unregister-fcm-token.php - Safe FCM Token Unlinking upon Logout
// Ensures User B does not receive User A's alerts when sharing an Android device.

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push/NativePushTokenRepository.php';

$currentUser = getCurrentUser();
$userId = $currentUser ? (string)$currentUser['id'] : '';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$fcmToken = trim($data['fcmToken'] ?? ($data['token'] ?? ''));
$installationId = trim($data['installationId'] ?? '');

$unlinked = false;
if (!empty($userId)) {
    $unlinked = NativePushTokenRepository::unlinkUser($userId, $fcmToken, $installationId);
} elseif (!empty($fcmToken)) {
    $unlinked = NativePushTokenRepository::deactivateToken($fcmToken, 'ANONYMOUS_UNLINK');
}

echo json_encode([
    'success' => true,
    'unlinked' => $unlinked
]);
