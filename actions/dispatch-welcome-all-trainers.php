<?php
// actions/dispatch-welcome-all-trainers.php - Send Out-of-App Welcome Push to All Trainers
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/push/PushService.php';

// Security: Require administrative authorization and CSRF validation
requireAdminOrStaff();
requireCsrfToken();

try {
    $subCol = getCollection("PushSubscription");
    $userCol = getCollection("User");
    $trainerCol = getCollection("Trainer");
    $notifCol = getCollection("Notification");

    if (!$subCol) {
        throw new Exception("PushSubscription collection unavailable");
    }

    // 1. Remove any dummy test endpoints
    $subCol->deleteMany(['endpoint' => new MongoDB\BSON\Regex('test-endpoint\.com', 'i')]);

    // 2. Fetch all trainers and their users
    $allUsers = $userCol ? $userCol->find([])->toArray() : [];
    $userMap = [];
    foreach ($allUsers as $u) {
        $userMap[(string)$u['_id']] = [
            'name' => $u['name'] ?? ($u['fullName'] ?? 'Trainer'),
            'role' => $u['role'] ?? 'TRAINER',
            'email' => $u['email'] ?? ''
        ];
    }

    $title = "Welcome to Mentry Solutions! 🎉";
    $body = "Welcome aboard! Your trainer portal is active. You will receive real-time push alerts for new matching opportunities & assignments.";
    $url = "/trainer/notifications.php";
    $notifType = "WELCOME_TRAINER";
    $timestamp = time();

    // 3. Create In-App Notification in DB for each trainer (Idempotent: unique deterministic _id per trainer)
    $trainers = $trainerCol ? $trainerCol->find([])->toArray() : [];
    $trainerUserIds = [];
    foreach ($trainers as $t) {
        $uId = isset($t['userId']) ? (string)$t['userId'] : (string)$t['_id'];
        if (!in_array($uId, $trainerUserIds)) {
            $trainerUserIds[] = $uId;
        }
    }
    // Also include any user with role = 'TRAINER'
    foreach ($allUsers as $u) {
        if (($u['role'] ?? '') === 'TRAINER') {
            $uId = (string)$u['_id'];
            if (!in_array($uId, $trainerUserIds)) {
                $trainerUserIds[] = $uId;
            }
        }
    }

    $inAppCreated = 0;
    if ($notifCol) {
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
    }

    // 4. Fetch all active subscriptions directly from Supabase collection
    $subscriptions = $subCol->find(['isActive' => ['$ne' => false]])->toArray();

    $activeSubs = [];
    foreach ($subscriptions as $s) {
        if (($s['isActive'] ?? true) !== false && !empty($s['endpoint'])) {
            $activeSubs[] = $s;
        }
    }

    $results = [];
    $sentCount = 0;
    $failedCount = 0;

    foreach ($activeSubs as $sub) {
        $uId = (string)($sub['userId'] ?? '');
        $userInfo = $userMap[$uId] ?? [
            'name' => 'Registered Device',
            'role' => $sub['userRole'] ?? 'DEVICE',
            'email' => ''
        ];

        $deviceNotifId = 'welcome_' . ($uId ?: substr(md5($sub['endpoint']), 0, 8)) . '_' . $timestamp;

        $payload = [
            'id' => $deviceNotifId,
            'title' => $title,
            'body' => $body,
            'message' => $body,
            'url' => $url,
            'link' => $url,
            'type' => $notifType,
            'priority' => 'high'
        ];

        $res = PushService::sendToSubscription($sub, $payload, 'high');

        $endpointSnippet = substr($sub['endpoint'] ?? '', 0, 48) . '...';

        $entry = [
            'recipientName' => $userInfo['name'],
            'recipientEmail' => $userInfo['email'],
            'role' => $userInfo['role'],
            'userId' => $uId,
            'device' => $sub['device'] ?? ($sub['userAgent'] ?? 'Mobile/Desktop'),
            'endpoint' => $endpointSnippet,
            'statusCode' => $res['statusCode'] ?? 0,
            'success' => $res['accepted'] ?? false,
            'error' => $res['reason'] ?? null
        ];

        $results[] = $entry;

        if (!empty($res['accepted'])) {
            $sentCount++;
        } else {
            $failedCount++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Out-of-app welcome push notification successfully broadcasted to all trainers and devices',
        'title' => $title,
        'body' => $body,
        'inAppNotificationsCreated' => $inAppCreated,
        'totalPushSubscriptions' => count($activeSubs),
        'pushDeliveredCount' => $sentCount,
        'pushFailedCount' => $failedCount,
        'deliveries' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

