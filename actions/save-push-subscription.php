<?php
// actions/save-push-subscription.php - Store Web Push Notification Subscriptions
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (empty($data) || empty($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid push subscription data']);
    exit();
}

$endpoint = cleanString($data['endpoint'], 2000);
$keys = $data['keys'] ?? [];
$p256dh = cleanString($keys['p256dh'] ?? '', 500);
$authKey = cleanString($keys['auth'] ?? '', 500);

if (empty($endpoint) || empty($p256dh) || empty($authKey)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing subscription keys']);
    exit();
}

try {
    $subCol = getCollection("PushSubscription");
    if (!$subCol) {
        throw new Exception("Database collection unavailable");
    }

    $currentUser = getCurrentUser();
    $userId = $currentUser['id'] ?? null;
    $userRole = $currentUser['role'] ?? 'GUEST';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);

    $subCol->updateOne(
        ['endpoint' => $endpoint],
        [
            '$set' => [
                'endpoint' => $endpoint,
                'p256dh' => $p256dh,
                'auth' => $authKey,
                'userId' => $userId ? (string)$userId : null,
                'userRole' => $userRole,
                'ip' => $ip,
                'userAgent' => $userAgent,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ],
            '$setOnInsert' => [
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ]
        ],
        ['upsert' => true]
    );

    echo json_encode(['success' => true, 'message' => 'Push subscription saved successfully']);
} catch (\Throwable $e) {
    error_log("save-push-subscription error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not register push token']);
}
