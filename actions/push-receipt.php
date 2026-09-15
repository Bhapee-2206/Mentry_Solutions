<?php
// actions/push-receipt.php - Retired endpoint (200 OK no-op)
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'retired' => true]);
