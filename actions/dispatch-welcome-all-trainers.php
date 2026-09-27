<?php
// actions/dispatch-welcome-all-trainers.php - Send In-App Welcome Notification to All Trainers
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Security: Require administrative authorization and CSRF validation
requireAdminOrStaff();
requireCsrfToken();

try {
    $userCol = getCollection("User");
    $trainerCol = getCollection("Trainer");
    $notifCol = getCollection("Notification");

    if (!$notifCol) {
        throw new Exception("Notification collection unavailable");
    }

    $title = "Welcome to Mentry Solutions! 🎉";
    $body = "Welcome aboard! Your trainer portal is active. You will receive real-time notifications for new matching opportunities & assignments.";
    $url = "/trainer/notifications.php";
    $notifType = "WELCOME_TRAINER";

    // 1. Gather all trainer user IDs
    $trainers = $trainerCol ? $trainerCol->find([])->toArray() : [];
    $trainerUserIds = [];
    foreach ($trainers as $t) {
        $uId = isset($t['userId']) ? (string)$t['userId'] : (string)$t['_id'];
        if (!in_array($uId, $trainerUserIds)) {
            $trainerUserIds[] = $uId;
        }
    }

    // Also include any user with role = 'TRAINER'
    $allUsers = $userCol ? $userCol->find(['role' => 'TRAINER'])->toArray() : [];
    foreach ($allUsers as $u) {
        $uId = (string)$u['_id'];
        if (!in_array($uId, $trainerUserIds)) {
            $trainerUserIds[] = $uId;
        }
    }

    // 2. Create In-App Notification in DB for each trainer (Idempotent: unique deterministic _id per trainer)
    $inAppCreated = 0;
    foreach ($trainerUserIds as $uId) {
        $dedupId = 'welcome_' . $uId;
        $existing = $notifCol->findOne(['_id' => $dedupId]);
        if (!$existing) {
            $notifCol->insertOne([
                '_id' => $dedupId,
                'userId' => $uId,
                'type' => $notifType,
                'title' => $title,
                'message' => $body,
                'link' => $url,
                'read' => false,
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ]);
            $inAppCreated++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Welcome notification broadcasted to all trainers via In-App notifications.',
        'title' => $title,
        'body' => $body,
        'inAppNotificationsCreated' => $inAppCreated,
        'trainersCount' => count($trainerUserIds)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
