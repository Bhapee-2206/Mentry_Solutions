<?php
// actions/get-vapid-public-key.php - Expose VAPID Application Server Public Key for PWA Web Push
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/PushNotificationService.php';

try {
    $publicKey = PushNotificationService::getPublicKey();
    echo json_encode([
        'success' => true,
        'publicKey' => $publicKey
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Could not load VAPID configuration'
    ]);
}
