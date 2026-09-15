<?php
// actions/push/unsubscribe.php - Safe Endpoint Deactivation for Clean Rotation
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../../includes/push/PushSubscriptionRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$endpoint = trim($data['endpoint'] ?? '');
$endpointHash = trim($data['endpointHash'] ?? '');

$col = getCollection("PushSubscription");
if (!$col) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}

$deactivated = false;
if (!empty($endpoint)) {
    PushSubscriptionRepository::deactivate($endpoint, 'CLIENT_REQUESTED_UNSUBSCRIBE');
    $deactivated = true;
} elseif (!empty($endpointHash)) {
    // Look up by SHA-256 hash
    $all = $col->find(['isActive' => true, 'isDead' => ['$ne' => true]]);
    foreach ($all as $sub) {
        $ep = $sub['endpoint'] ?? '';
        if (hash('sha256', $ep) === $endpointHash) {
            PushSubscriptionRepository::deactivate($ep, 'CLIENT_REQUESTED_UNSUBSCRIBE_BY_HASH');
            $deactivated = true;
            break;
        }
    }
}

echo json_encode([
    'success' => true,
    'deactivated' => $deactivated
]);
