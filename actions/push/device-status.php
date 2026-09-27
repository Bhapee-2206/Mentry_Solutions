<?php
// actions/push/device-status.php - Decommissioned Endpoint
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'retired' => true,
    'active' => false
]);
