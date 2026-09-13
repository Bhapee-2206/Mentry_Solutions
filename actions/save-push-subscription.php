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
    $device = cleanString($data['device'] ?? '', 100);
    $browser = cleanString($data['browser'] ?? '', 100);
    $platform = cleanString($data['platform'] ?? '', 100);
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Allow admin/staff to pair a test device to a specific target user / trainer
    if (isAdminOrStaff() && !empty($data['targetUserId'])) {
        $userId = cleanString($data['targetUserId'], 50);
        $trainerCol = getCollection("Trainer");
        $t = $trainerCol ? $trainerCol->findOne(['userId' => (string)$userId]) : null;
        if ($t) {
            $trainerId = (string)$t['_id'];
            $userRole = 'TRAINER';
        } else {
            $userCol = getCollection("User");
            $u = $userCol ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$userId)]) : null;
            if ($u) {
                $userRole = $u['role'] ?? 'TRAINER';
            }
        }
    } else {
        // Link trainerId if user is a trainer
        $trainerId = null;
        if ($userRole === 'TRAINER' && !empty($userId)) {
            $trainerCol = getCollection("Trainer");
            $t = $trainerCol ? $trainerCol->findOne(['userId' => (string)$userId]) : null;
            if ($t) {
                $trainerId = (string)$t['_id'];
            }
        }
    }

    // Deactivate old subscription endpoint if client replaced it
    if (!empty($data['oldEndpoint']) && $data['oldEndpoint'] !== $endpoint) {
        $oldEndpoint = cleanString($data['oldEndpoint'], 2000);
        $subCol->updateOne(
            ['endpoint' => $oldEndpoint],
            ['$set' => [
                'isActive' => false,
                'deactivatedAt' => new MongoDB\BSON\UTCDateTime(),
                'deactivationReason' => 'REPLACED_BY_NEW_CLIENT_SUBSCRIPTION'
            ]]
        );
    }

    $subCol->updateOne(
        ['endpoint' => $endpoint],
        [
            '$set' => [
                'endpoint' => $endpoint,
                'p256dh' => $p256dh,
                'auth' => $authKey,
                'userId' => $userId ? (string)$userId : null,
                'trainerId' => $trainerId,
                'userRole' => $userRole,
                'device' => $device ?: (preg_match('/Mobile|Android|iPhone/i', $userAgent) ? 'Mobile' : 'Desktop'),
                'browser' => $browser,
                'platform' => $platform,
                'ip' => $ip,
                'userAgent' => $userAgent,
                'isActive' => true,
                'isDead' => false,
                'deactivatedAt' => null,
                'deactivationReason' => null,
                'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                'lastActiveAt' => new MongoDB\BSON\UTCDateTime()
            ],
            '$setOnInsert' => [
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ]
        ],
        ['upsert' => true]
    );

    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'message' => 'Push subscription saved successfully', 'userId' => $userId]);
} catch (\Throwable $e) {
    if (ob_get_length()) ob_clean();
    error_log("save-push-subscription error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not register push token']);
}
