<?php
// actions/share-analytics.php - Safe Opportunity Share Tracking
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

if (!isSameOriginRequest()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Cross-origin request rejected']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$oppId = trim($data['opportunityId'] ?? '');
$channel = strtolower(trim($data['channel'] ?? 'native'));

if (empty($oppId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing opportunityId']);
    exit();
}

try {
    $oppCol = getCollection("Opportunity");
    $analyticsCol = getCollection("ShareAnalytics");

    $oppQuery = ['_id' => $oppId];
    if (preg_match('/^[a-f\d]{24}$/i', $oppId)) {
        try {
            $oppQuery = ['_id' => new MongoDB\BSON\ObjectId($oppId)];
        } catch (\Throwable $e) {}
    }

    // Increment shareCount on Opportunity document
    if ($oppCol) {
        $oppCol->updateOne(
            ['$or' => [$oppQuery, ['jobId' => $oppId]]],
            ['$inc' => ['shareCount' => 1]]
        );
    }

    // Safe analytics logging (NO personal identity, NO sensitive cookies)
    if ($analyticsCol) {
        $analyticsCol->insertOne([
            'opportunityId' => (string)$oppId,
            'channel' => cleanString($channel, 50),
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'isoTimestamp' => date('c')
        ]);
    }

    echo json_encode(['success' => true, 'tracked' => true]);
} catch (\Throwable $e) {
    logAppError('share_analytics', $e, ['oppId' => $oppId, 'channel' => $channel]);
    echo json_encode(['success' => false, 'error' => 'Unable to record share analytics at this time.']);
}
