<?php
// actions/ai-match-query.php - Role-Protected AI Matching API Endpoint

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_agent.php';

header('Content-Type: application/json');

// 1. Strict Role Authorization (Only Admin & Staff allowed)
if (!isLoggedIn() || !isAdminOrStaff()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Access Denied: Mentor AI is strictly restricted to authenticated Admin and Staff users.'
    ]);
    exit();
}

$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

// Read JSON input or POST form
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$query = trim($data['query'] ?? '');
$opportunityId = trim($data['opportunityId'] ?? '');

if (!empty($opportunityId)) {
    $oppCol = getCollection("Opportunity");
    $opp = $oppCol ? $oppCol->findOne(['_id' => $opportunityId]) : null;
    if ($opp) {
        $skillsStr = is_array($opp['skillsRequired'] ?? []) ? implode(', ', $opp['skillsRequired']) : ($opp['skillsRequired'] ?? '');
        $query = "Find suitable trainers for: {$opp['title']}. Domain: {$opp['domain']}. Required Skills: {$skillsStr}. Location: " . ($opp['city'] ?? 'Any') . ". Mode: " . ($opp['mode'] ?? 'Hybrid') . ". Minimum Experience: " . ($opp['minExperienceYears'] ?? 3) . " years.";
    }
}

if (empty($query)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a training requirement or question.'
    ]);
    exit();
}

// Process requirement through AI pipeline
$response = AIAgent::processRequirementQuery($query);

// Audit logging
try {
    $auditCol = getCollection("AuditLog");
    if ($auditCol) {
        $auditCol->insertOne([
            'userId' => $currentUser['id'] ?? null,
            'userName' => $currentUser['name'] ?? 'Admin/Staff',
            'userRole' => $currentUser['role'] ?? 'STAFF',
            'action' => 'AI_TRAINER_SEARCH',
            'details' => "AI search query: " . substr($query, 0, 100),
            'source' => $response['source'] ?? 'ai-engine',
            'createdAt' => new MongoDB\BSON\UTCDateTime()
        ]);
    }
} catch (\Throwable $e) {}

echo json_encode($response);
exit();
