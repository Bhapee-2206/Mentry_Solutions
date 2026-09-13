<?php
// actions/live-sync-api.php - Central Lightweight Real-Time Live Sync Engine
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$currentUser = getCurrentUser();
$lastSyncTs = isset($_GET['last_sync']) ? (int)$_GET['last_sync'] : 0;
$context = isset($_GET['context']) ? trim($_GET['context']) : '';

// Cap lastSyncTs: if 0 or older than 5 minutes ago, only check the last 3 minutes
$nowTs = time();
$serverTimeMs = round(microtime(true) * 1000);
$threeMinutesAgoTs = $nowTs - 180;
$effectiveSinceTs = ($lastSyncTs > 0 && ($lastSyncTs / 1000) > $threeMinutesAgoTs) 
    ? (int)($lastSyncTs / 1000) 
    : $threeMinutesAgoTs;

$sinceBson = new MongoDB\BSON\UTCDateTime($effectiveSinceTs * 1000);

$response = [
    'success' => true,
    'serverTime' => $serverTimeMs,
    'unreadCount' => 0,
    'newNotifications' => [],
    'applicationUpdates' => [],
    'newOpportunities' => [],
    'trainerAvailabilityUpdates' => []
];

// 1. Notification Badge Counter & New Notification Stream
if ($currentUser) {
    try {
        $notifCol = getCollection("Notification");
        if ($notifCol) {
            $userRole = $currentUser['role'] ?? '';
            $userId = (string)($currentUser['id'] ?? '');
            $isAdmin = in_array($userRole, ['ADMIN', 'SUPER_ADMIN', 'STAFF']);

            // Query live unread count
            if ($isAdmin) {
                $response['unreadCount'] = $notifCol->countDocuments([
                    '$or' => [
                        ['isAdminAlert' => true, 'read' => false],
                        ['recipientRole' => 'ADMIN', 'read' => false],
                        ['userId' => $userId, 'read' => false]
                    ]
                ]);

                // Stream newly created notifications for admin
                $newNotifsCursor = $notifCol->find([
                    '$or' => [
                        ['isAdminAlert' => true],
                        ['recipientRole' => 'ADMIN'],
                        ['userId' => $userId]
                    ],
                    'createdAt' => ['$gt' => $sinceBson]
                ], ['sort' => ['createdAt' => -1], 'limit' => 5]);
            } else {
                $userQueries = [$userId];
                try {
                    $userQueries[] = new MongoDB\BSON\ObjectId($userId);
                } catch (\Throwable $e) {}

                $trainerId = null;
                if ($userRole === 'TRAINER') {
                    $trainerCol = getCollection("Trainer");
                    $t = $trainerCol ? $trainerCol->findOne(['userId' => $userId]) : null;
                    if ($t) {
                        $trainerId = (string)$t['_id'];
                    }
                }

                $userFilterOr = [
                    ['userId' => ['$in' => $userQueries]]
                ];
                if (!empty($trainerId)) {
                    $userFilterOr[] = ['trainerId' => $trainerId];
                }

                $response['unreadCount'] = $notifCol->countDocuments([
                    '$and' => [
                        ['$or' => $userFilterOr],
                        ['$or' => [['read' => false], ['read' => ['$exists' => false]]]]
                    ]
                ]);

                // Stream newly created notifications strictly since lastSync baseline
                $newNotifsCursor = $notifCol->find([
                    '$and' => [
                        ['$or' => $userFilterOr],
                        ['createdAt' => ['$gt' => $sinceBson]]
                    ]
                ], ['sort' => ['createdAt' => -1], 'limit' => 5]);
            }

            foreach ($newNotifsCursor as $n) {
                $response['newNotifications'][] = [
                    'id' => (string)$n['_id'],
                    'title' => $n['title'] ?? 'Notification',
                    'message' => $n['message'] ?? '',
                    'type' => $n['type'] ?? 'GENERAL',
                    'link' => $n['link'] ?? '/trainer/notifications.php',
                    'matchScore' => $n['matchScore'] ?? null,
                    'read' => !empty($n['read']),
                    'createdAt' => $n['createdAt'] instanceof MongoDB\BSON\UTCDateTime 
                        ? round($n['createdAt']->toDateTime()->getTimestamp() * 1000) 
                        : null
                ];
            }
        }
    } catch (\Throwable $e) {
        // Silently skip on DB throttle
    }
}

// 2. Application Status Live Updates for Trainers
if ($currentUser && ($currentUser['role'] ?? '') === 'TRAINER') {
    try {
        $trainerCol = getCollection("Trainer");
        $appCol = getCollection("Application");
        $oppCol = getCollection("Opportunity");

        if ($trainerCol && $appCol) {
            $trainer = $trainerCol->findOne(['userId' => (string)$currentUser['id']]);
            if ($trainer) {
                $trainerId = (string)$trainer['_id'];

                // Check for applications reviewed/updated since last sync
                $recentApps = $appCol->find([
                    'trainerId' => $trainerId,
                    'reviewedAt' => ['$gt' => $sinceBson]
                ], ['sort' => ['reviewedAt' => -1], 'limit' => 5]);

                foreach ($recentApps as $app) {
                    $oppId = (string)($app['opportunityId'] ?? '');
                    $oppTitle = 'Training Assignment';
                    if (!empty($oppId) && $oppCol) {
                        try {
                            $oppDoc = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]);
                            if ($oppDoc) $oppTitle = $oppDoc['title'] ?? $oppTitle;
                        } catch (\Throwable $e) {}
                    }

                    $response['applicationUpdates'][] = [
                        'applicationId' => (string)$app['_id'],
                        'opportunityId' => $oppId,
                        'opportunityTitle' => $oppTitle,
                        'status' => strtoupper($app['status'] ?? 'PENDING'),
                        'adminNotes' => $app['adminNotes'] ?? '',
                        'reviewedAt' => $app['reviewedAt'] instanceof MongoDB\BSON\UTCDateTime
                            ? round($app['reviewedAt']->toDateTime()->getTimestamp() * 1000)
                            : null
                    ];
                }
            }
        }
    } catch (\Throwable $e) {}
}

// 3. New Opportunities Stream (For Trainers and Public Visitors)
try {
    $oppCol = getCollection("Opportunity");
    if ($oppCol) {
        $recentOpps = $oppCol->find([
            'status' => 'PUBLISHED',
            'createdAt' => ['$gt' => $sinceBson]
        ], ['sort' => ['createdAt' => -1], 'limit' => 3]);

        foreach ($recentOpps as $opp) {
            $response['newOpportunities'][] = [
                'id' => (string)$opp['_id'],
                'title' => $opp['title'] ?? 'New Opportunity',
                'domain' => $opp['domain'] ?? 'Technology',
                'city' => $opp['city'] ?? 'India',
                'dailyRateMin' => (int)($opp['dailyRateMin'] ?? 0),
                'dailyRateMax' => (int)($opp['dailyRateMax'] ?? 0),
                'durationDays' => (int)($opp['durationDays'] ?? 1),
                'link' => '/opportunity-details.php?id=' . (string)$opp['_id']
            ];
        }
    }
} catch (\Throwable $e) {}

// 4. Trainer Availability Live Changes (For Admins viewing directory)
if ($currentUser && in_array($currentUser['role'] ?? '', ['ADMIN', 'SUPER_ADMIN', 'STAFF'])) {
    try {
        $trainerCol = getCollection("Trainer");
        $userCol = getCollection("User");
        if ($trainerCol) {
            $updatedTrainers = $trainerCol->find([
                'availabilityUpdatedAt' => ['$gt' => $sinceBson]
            ], ['sort' => ['availabilityUpdatedAt' => -1], 'limit' => 5]);

            foreach ($updatedTrainers as $tr) {
                $uDoc = ($userCol && !empty($tr['userId'])) ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$tr['userId'])]) : null;
                $response['trainerAvailabilityUpdates'][] = [
                    'trainerId' => (string)$tr['_id'],
                    'name' => $uDoc['name'] ?? ($tr['headline'] ?? 'Trainer'),
                    'status' => $tr['availabilityStatus'] ?? 'AVAILABLE_NOW',
                    'notes' => $tr['availabilityNotes'] ?? ''
                ];
            }
        }
    } catch (\Throwable $e) {}
}

echo json_encode($response);
exit();
