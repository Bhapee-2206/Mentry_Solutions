<?php
// actions/push/public-key.php - Clean Public Key Endpoint
// Return ONLY the public key. Never returns the private key.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

require_once __DIR__ . '/../../includes/push/PushConfig.php';

try {
    $publicKey = PushConfig::getPublicKey();
    echo json_encode([
        'success' => true,
        'publicKey' => $publicKey
    ], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'VAPID public key not available: ' . $e->getMessage()
    ]);
}
