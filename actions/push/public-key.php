<?php
// actions/push/public-key.php - Decommissioned Endpoint
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'retired' => true,
    'publicKey' => '',
    'error' => 'Push notifications are retired in favor of In-App notifications.'
]);
