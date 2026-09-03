<?php
// actions/compare-trainers-api.php - Role-Protected Multi-Trainer Comparison Endpoint

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_agent.php';

header('Content-Type: application/json');

// Strict Role Authorization
if (!isLoggedIn() || !isAdminOrStaff()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Access Denied: Only Admin and Staff are authorized.'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$trainerIds = $data['trainerIds'] ?? [];
$requirementText = trim($data['requirementText'] ?? 'General technical training alignment');

if (empty($trainerIds) || !is_array($trainerIds)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select at least 2 trainers to compare.'
    ]);
    exit();
}

$result = AIAgent::compareTrainers($trainerIds, $requirementText);
echo json_encode($result);
exit();
