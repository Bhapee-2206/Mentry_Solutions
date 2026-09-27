<?php
// actions/check-subscription-status.php - Decommissioned Push Endpoint
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo json_encode([
    'success' => true,
    'exists' => false,
    'active' => false,
    'isDead' => true,
    'retired' => true,
    'message' => 'Push notifications decommissioned; in-app notifications are active.'
]);
