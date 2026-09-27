<?php
// actions/save-push-subscription.php - Decommissioned Push Endpoint
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'retired' => true,
    'message' => 'Push notifications decommissioned; in-app notifications are active.'
]);
