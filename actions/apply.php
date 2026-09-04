<?php
// actions/apply.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = getCurrentUser();
    $opportunityId = $_POST['opportunityId'] ?? '';
    $proposedDailyRate = (float)($_POST['proposedDailyRate'] ?? 6000);
    $message = trim($_POST['message'] ?? '');

    $trainerCol = getCollection("Trainer");
    $trainer = null;
    if ($trainerCol) {
        try {
            $trainer = $trainerCol->findOne([
                '$or' => [
                    ['userId' => $user['id']],
                    ['userId' => new MongoDB\BSON\ObjectId($user['id'])]
                ]
            ]);
        } catch (\Throwable $e) {
            $trainer = $trainerCol->findOne(['userId' => $user['id']]);
        }
    }
    $trainerId = $trainer ? (string)$trainer['_id'] : '';

    if (!empty($opportunityId) && !empty($trainerId)) {
        $appCol = getCollection("Application");
        if ($appCol) {
            $existing = $appCol->findOne(['trainerId' => $trainerId, 'opportunityId' => $opportunityId]);
            if (!$existing) {
                $appCol->insertOne([
                    'trainerId' => $trainerId,
                    'opportunityId' => $opportunityId,
                    'proposedDailyRate' => $proposedDailyRate,
                    'message' => $message,
                    'matchScore' => 95,
                    'status' => 'PENDING',
                    'appliedAt' => new MongoDB\BSON\UTCDateTime(),
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]);
            }
        }
    }
}

header("Location: /trainer/applications.php");
exit();
