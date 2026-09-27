<?php
// actions/push/check-subscription.php - Decommissioned Endpoint
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'exists' => false,
    'active' => false,
    'isDead' => true,
    'retired' => true
]);
