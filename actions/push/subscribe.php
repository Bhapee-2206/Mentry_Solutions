<?php
// actions/push/subscribe.php - Clean Web Push Subscription Ingestion
// Accepts browser PushSubscription JSON, updates/creates subscription in MongoDB.

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push/PushSubscriptionRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (empty($data) || empty($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid subscription payload.']);
    exit();
}

$endpoint = trim($data['endpoint'] ?? '');
if (!PushSubscriptionRepository::isValidEndpoint($endpoint)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or unauthorized push gateway endpoint.']);
    exit();
}

$keys = $data['keys'] ?? [];
$p256dh = trim($keys['p256dh'] ?? ($data['p256dh'] ?? ''));
$auth = trim($keys['auth'] ?? ($data['auth'] ?? ''));

if (strlen($p256dh) < 20 || strlen($auth) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Malformed subscription cryptography keys.']);
    exit();
}

try {
    $currentUser = getCurrentUser();
    $userId = $currentUser['id'] ?? null;

    // Allow admin test tool to link to specific trainer user ID
    if (isAdminOrStaff() && !empty($data['targetUserId'])) {
        $userId = cleanString($data['targetUserId'], 50);
    }

    $meta = [
        'device' => $data['device'] ?? '',
        'platform' => $data['platform'] ?? '',
        'browser' => $data['browser'] ?? '',
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];

    $result = PushSubscriptionRepository::saveSubscription($data, $userId, $meta);

    echo json_encode([
        'success' => true,
        'subscribed' => true
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
