<?php
// actions/push/device-status.php - Safe Authenticated Device Diagnostics
// Allows authenticated clients & diagnostic tools to verify active native Android FCM registration.
// NEVER exposes actual FCM registration tokens.

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push/NativePushTokenRepository.php';

$currentUser = getCurrentUser();
if (!$currentUser || empty($currentUser['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

$userId = (string)$currentUser['id'];

// If admin requests diagnostic for another user/trainer
if (isAdminOrStaff() && !empty($_GET['userId'])) {
    $userId = trim($_GET['userId']);
}

$tokens = NativePushTokenRepository::findActiveForUser($userId);
$sanitizedTokens = [];

foreach ($tokens as $t) {
    $th = $t['tokenHash'] ?? hash('sha256', $t['fcmToken'] ?? '');
    $sanitizedTokens[] = [
        'tokenHash' => substr($th, 0, 16),
        'isActive' => !empty($t['isActive']),
        'userId' => (string)($t['userId'] ?? $userId),
        'lastSeenAt' => isset($t['lastSeenAt']) ? (is_object($t['lastSeenAt']) ? $t['lastSeenAt']->toDateTime()->format('c') : (string)$t['lastSeenAt']) : null,
        'appVersion' => (string)($t['appVersion'] ?? '1.0.0'),
        'deviceModel' => (string)($t['deviceModel'] ?? 'Android Device'),
        'androidVersion' => (string)($t['androidVersion'] ?? 'Unknown'),
        'installationId' => (string)($t['installationId'] ?? '')
    ];
}

echo json_encode([
    'success' => true,
    'tokenExists' => !empty($sanitizedTokens),
    'deviceCount' => count($sanitizedTokens),
    'userId' => $userId,
    'devices' => $sanitizedTokens
]);
