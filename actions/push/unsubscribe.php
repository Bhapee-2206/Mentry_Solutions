<?php
// actions/push/unsubscribe.php - Decommissioned Endpoint
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'retired' => true
]);
