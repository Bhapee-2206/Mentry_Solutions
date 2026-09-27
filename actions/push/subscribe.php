<?php
// actions/push/subscribe.php - Decommissioned Endpoint
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'retired' => true,
    'message' => 'Push notifications are retired in favor of In-App notifications.'
]);
