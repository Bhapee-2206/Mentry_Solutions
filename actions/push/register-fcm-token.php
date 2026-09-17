<?php
// actions/push/register-fcm-token.php - Authenticated Native Android FCM Token Ingestion
// Accepts FCM token from Mentry Android native app, binds it to the authenticated session identity.
// NEVER trusts a client-supplied user ID blindly.

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push/NativePushTokenRepository.php';

$currentUser = getCurrentUser();
if (!$currentUser || empty($currentUser['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required. Sign in first.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || (isset($_GET['action']) && $_GET['action'] === 'status')) {
    $tokens = NativePushTokenRepository::findActiveForUser((string)$currentUser['id']);
    $activeTokens = [];
    foreach ($tokens as $t) {
        $th = $t['tokenHash'] ?? hash('sha256', $t['fcmToken'] ?? '');
        $activeTokens[] = [
            'tokenHash' => substr($th, 0, 16),
            'active' => !empty($t['isActive']),
            'userId' => (string)($t['userId'] ?? $currentUser['id']),
            'lastSeenAt' => isset($t['lastSeenAt']) ? (is_object($t['lastSeenAt']) ? $t['lastSeenAt']->toDateTime()->format('c') : (string)$t['lastSeenAt']) : null,
            'appVersion' => (string)($t['appVersion'] ?? '1.0.0'),
            'deviceModel' => (string)($t['deviceModel'] ?? 'Android Device'),
            'installationId' => (string)($t['installationId'] ?? '')
        ];
    }
    echo json_encode([
        'success' => true,
        'tokenExists' => !empty($activeTokens),
        'activeCount' => count($activeTokens),
        'userId' => (string)$currentUser['id'],
        'tokens' => $activeTokens
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$fcmToken = trim($data['fcmToken'] ?? ($data['token'] ?? ''));
if (!NativePushTokenRepository::isValidToken($fcmToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid FCM registration token.']);
    exit();
}

try {
    $userId = (string)$currentUser['id'];
    $installationId = trim((string)($data['installationId'] ?? ''));

    $meta = [
        'deviceModel' => $data['deviceModel'] ?? ($data['model'] ?? 'Android Device'),
        'androidVersion' => $data['androidVersion'] ?? ($data['osVersion'] ?? 'Android'),
        'appVersion' => $data['appVersion'] ?? '1.0.0',
        'installationId' => $installationId
    ];

    $result = NativePushTokenRepository::registerToken($userId, $fcmToken, $meta);

    echo json_encode([
        'success' => true,
        'registered' => true,
        'platform' => 'android',
        'userId' => $userId,
        'tokenHash' => $result['tokenHash'],
        'installationId' => $installationId
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to register device token: ' . $e->getMessage()
    ]);
}

