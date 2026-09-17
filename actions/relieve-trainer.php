<?php
// actions/relieve-trainer.php - Official Faculty Relief & Slot Re-opening
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
requireAdminOrStaff();
requireCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignmentId = trim($_POST['assignmentId'] ?? '');
    $oppId = trim($_POST['opportunityId'] ?? '');
    $trainerId = trim($_POST['trainerId'] ?? '');
    $reliefReason = trim($_POST['reliefReason'] ?? 'Trainer dropped out last minute (unavailability)');
    $reliefNotes = trim($_POST['reliefNotes'] ?? '');
    $reopenSlot = !isset($_POST['reopenSlot']) || $_POST['reopenSlot'] === '1';
    $notifyTrainer = !isset($_POST['notifyTrainer']) || $_POST['notifyTrainer'] === '1';

    $asgCol = getCollection("Assignment");
    $oppCol = getCollection("Opportunity");
    $trainerCol = getCollection("Trainer");
    $appCol = getCollection("Application");
    $userCol = getCollection("User");

    $asg = null;
    if (!empty($assignmentId) && !str_starts_with($assignmentId, 'direct_') && $asgCol) {
        try {
            $asg = $asgCol->findOne(['_id' => new MongoDB\BSON\ObjectId($assignmentId)]);
        } catch (\Throwable $e) {
            $asg = $asgCol->findOne(['_id' => $assignmentId]);
        }
    }

    // Fallback: lookup assignment by opportunityId and trainerId if not found by assignmentId
    if (!$asg && !empty($oppId) && !empty($trainerId) && $asgCol) {
        $oppQueryIds = [(string)$oppId];
        try { $oppQueryIds[] = new MongoDB\BSON\ObjectId($oppId); } catch (\Throwable $e) {}
        $trQueryIds = [(string)$trainerId];
        try { $trQueryIds[] = new MongoDB\BSON\ObjectId($trainerId); } catch (\Throwable $e) {}

        $asg = $asgCol->findOne([
            'opportunityId' => ['$in' => $oppQueryIds],
            'trainerId' => ['$in' => $trQueryIds],
            'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS', 'CONFIRMED', 'ASSIGNED', 'ACCEPTED']]
        ]);
    }

    if ($asg) {
        $trainerId = (string)($asg['trainerId'] ?? $trainerId);
        $oppId = (string)($asg['opportunityId'] ?? $oppId);
    }

    if (!empty($trainerId) || $asg) {
        $adminUser = getCurrentUser();
        $adminId = $adminUser['id'] ?? null;
        $adminName = $adminUser['name'] ?? 'Admin Operations';

        // 1. Mark ALL active assignments for this trainer on this opportunity as RELIEVED
        if ($asgCol && !empty($oppId) && !empty($trainerId)) {
            $oppQueryIds = [(string)$oppId];
            try { $oppQueryIds[] = new MongoDB\BSON\ObjectId((string)$oppId); } catch (\Throwable $e) {}
            $trQueryIds = [(string)$trainerId];
            try { $trQueryIds[] = new MongoDB\BSON\ObjectId((string)$trainerId); } catch (\Throwable $e) {}

            $asgCol->updateMany(
                [
                    'opportunityId' => ['$in' => $oppQueryIds],
                    'trainerId' => ['$in' => $trQueryIds],
                    'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS', 'CONFIRMED', 'ASSIGNED', 'ACCEPTED']]
                ],
                ['$set' => [
                    'status' => 'RELIEVED',
                    'reliefReason' => $reliefReason,
                    'reliefNotes' => $reliefNotes,
                    'relievedAt' => new MongoDB\BSON\UTCDateTime(),
                    'relievedByUserId' => $adminId,
                    'relievedByName' => $adminName,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
        }
        if ($asg && !empty($asg['_id']) && $asgCol) {
            $asgCol->updateOne(
                ['_id' => $asg['_id']],
                ['$set' => [
                    'status' => 'RELIEVED',
                    'reliefReason' => $reliefReason,
                    'reliefNotes' => $reliefNotes,
                    'relievedAt' => new MongoDB\BSON\UTCDateTime(),
                    'relievedByUserId' => $adminId,
                    'relievedByName' => $adminName,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
        }

            // 2. Fetch Trainer & User details
            $trainer = null;
            $trainerUser = null;
            if ($trainerCol && !empty($trainerId)) {
                try {
                    $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]);
                } catch (\Throwable $e) {
                    $trainer = $trainerCol->findOne(['_id' => $trainerId]);
                }
                if ($trainer && !empty($trainer['userId']) && $userCol) {
                    try {
                        $trainerUser = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainer['userId'])]);
                    } catch (\Throwable $e) {}
                }
            }
            $trainerName = $trainerUser['name'] ?? ($trainer['name'] ?? 'Trainer');

            // 3. Reset Trainer Availability if no other active commitments exist
            if ($trainerCol && !empty($trainerId)) {
                $otherActiveQuery = [
                    'trainerId' => $trainerId,
                    'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS', 'CONFIRMED', 'ASSIGNED']]
                ];
                if ($asg && !empty($asg['_id'])) {
                    $otherActiveQuery['_id'] = ['$ne' => $asg['_id']];
                }
                $otherActive = $asgCol ? $asgCol->findOne($otherActiveQuery) : null;

                if (!$otherActive) {
                    $trainerCol->updateOne(
                        ['_id' => $trainer['_id']],
                        ['$set' => [
                            'availabilityStatus' => 'AVAILABLE_NOW',
                            'availabilityNotes' => '',
                            'availableFromDate' => null,
                            'availabilityUpdatedAt' => new MongoDB\BSON\UTCDateTime(),
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );
                }
            }

            // 4. Update Opportunity slots and status
            $opp = null;
            if ($oppCol && !empty($oppId)) {
                try {
                    $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]);
                } catch (\Throwable $e) {
                    $opp = $oppCol->findOne(['_id' => $oppId]);
                }

                if ($opp) {
                    $trainersNeeded = max(1, (int)($opp['trainersNeeded'] ?? 1));

                    $oppQueryIds = [(string)$oppId];
                    try { $oppQueryIds[] = new MongoDB\BSON\ObjectId($oppId); } catch (\Throwable $e) {}

                    // Count remaining active assignments for this opportunity (excluding relieved trainer)
                    $trQueryIds = [(string)$trainerId];
                    try { $trQueryIds[] = new MongoDB\BSON\ObjectId((string)$trainerId); } catch (\Throwable $e) {}

                    $remQuery = [
                        'opportunityId' => ['$in' => $oppQueryIds],
                        'trainerId' => ['$nin' => $trQueryIds],
                        'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS', 'CONFIRMED', 'ASSIGNED', 'ACCEPTED']]
                    ];
                    $remainingActive = $asgCol ? $asgCol->find($remQuery)->toArray() : [];

                    $activeTrainerIds = [];
                    foreach ($remainingActive as $ra) {
                        $raTid = (string)($ra['trainerId'] ?? '');
                        if (!empty($raTid) && $raTid !== (string)$trainerId && !in_array($raTid, $activeTrainerIds)) {
                            $activeTrainerIds[] = $raTid;
                        }
                    }

                    // Also check opportunity's existing assignedTrainerIds and filter out the relieved trainer
                    if (!empty($opp['assignedTrainerIds']) && is_array($opp['assignedTrainerIds'])) {
                        foreach ($opp['assignedTrainerIds'] as $oid) {
                            $sOid = (string)$oid;
                            if ($sOid !== (string)$trainerId && !in_array($sOid, $activeTrainerIds)) {
                                $activeTrainerIds[] = $sOid;
                            }
                        }
                    }

                    $activeCount = count($activeTrainerIds);

                    $oppUpdates = [
                        'assignedTrainerIds' => $activeTrainerIds,
                        'assignedTrainerId' => !empty($activeTrainerIds) ? $activeTrainerIds[0] : null,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ];

                    // If remaining active count is less than needed, reopen opportunity to PUBLISHED
                    if ($reopenSlot && $activeCount < $trainersNeeded) {
                        $oppUpdates['status'] = 'PUBLISHED';
                        $oppUpdates['reopenedAt'] = new MongoDB\BSON\UTCDateTime();
                        $oppUpdates['closedAt'] = null;
                        $oppUpdates['autoClosedReason'] = null;

                        // If start date is in the past, roll it forward so opportunity remains open on trainer feeds
                        $startDateVal = $opp['startDate'] ?? null;
                        $startTs = 0;
                        if ($startDateVal instanceof MongoDB\BSON\UTCDateTime) {
                            $startTs = $startDateVal->toDateTime()->getTimestamp();
                        } elseif (!empty($startDateVal)) {
                            $startTs = strtotime((string)$startDateVal);
                        }
                        if ($startTs > 0 && $startTs < time()) {
                            $oppUpdates['startDate'] = date('Y-m-d', strtotime('+1 day'));
                            $oppUpdates['originalStartDate'] = !empty($opp['originalStartDate']) ? $opp['originalStartDate'] : (is_string($startDateVal) ? $startDateVal : date('Y-m-d', $startTs));
                        }
                    }

                    $oppCol->updateOne(['_id' => $opp['_id']], ['$set' => $oppUpdates]);
                }
            }

            // 5. Update linked Application status if any
            if ($appCol && !empty($trainerId) && !empty($oppId)) {
                $appCol->updateMany(
                    [
                        'opportunityId' => $oppId,
                        'trainerId' => $trainerId,
                        'status' => 'ACCEPTED'
                    ],
                    ['$set' => [
                        'status' => 'RELIEVED',
                        'adminNotes' => 'Relieved from assignment: ' . $reliefReason,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
            }

            // 6. Dispatch Real-Time In-App & Web Push Notification to Relieved Trainer
            $oppTitle = $opp['title'] ?? 'Training Assignment';
            $collegeName = $opp['collegeName'] ?? '';
            $collegeSuffix = !empty($collegeName) ? " at {$collegeName}" : "";

            if ($notifyTrainer && !empty($trainer['userId'])) {
                try {
                    $notifCol = getCollection("Notification");
                    $trainerUserId = (string)$trainer['userId'];
                    $notifTitle = "ℹ️ Assignment Relief: {$oppTitle}";
                    $notifMsg = "You have been officially relieved from the faculty assignment for {$oppTitle}{$collegeSuffix}. Your schedule and availability status have been restored to Available Now.";

                    if ($notifCol) {
                        $insRes = $notifCol->insertOne([
                            'userId' => $trainerUserId,
                            'trainerId' => $trainerId,
                            'opportunityId' => $oppId,
                            'type' => 'ASSIGNMENT_UPDATE',
                            'title' => $notifTitle,
                            'message' => $notifMsg,
                            'link' => '/trainer/assignments.php',
                            'read' => false,
                            'createdAt' => new MongoDB\BSON\UTCDateTime()
                        ]);
                        $notifId = (string)$insRes->getInsertedId();

                        if (function_exists('dispatchWebPushNotification')) {
                            @dispatchWebPushNotification(
                                ['userId' => $trainerUserId],
                                $notifTitle,
                                $notifMsg,
                                '/trainer/assignments.php',
                                [
                                    'id' => $notifId,
                                    'type' => 'ASSIGNMENT_UPDATE',
                                    'opportunityId' => $oppId,
                                    'priority' => 'high'
                                ]
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("Relief notification error: " . $e->getMessage());
                }
            }

            // 7. Audit log for Admin
            try {
                if (function_exists('notifyAdmin')) {
                    notifyAdmin(
                        'TRAINER_RELIEVED',
                        "Trainer Relieved: {$trainerName}",
                        "{$trainerName} was relieved from '{$oppTitle}'. Reason: {$reliefReason}. Position slot has been reopened.",
                        "/admin/opportunity-view.php?id=" . $oppId,
                        [
                            'trainerId' => $trainerId,
                            'opportunityId' => $oppId,
                            'reliefReason' => $reliefReason
                        ]
                    );
                }
            } catch (\Throwable $e) {}

            $_SESSION['flash_success'] = "Trainer {$trainerName} has been successfully relieved. Slot has been reopened for replacement.";
            $redirectUrl = !empty($_POST['redirectUrl']) ? $_POST['redirectUrl'] : (!empty($oppId) ? "/admin/opportunity-view.php?id={$oppId}&relieved=1" : "/admin/assignments.php");
            header("Location: " . $redirectUrl);
            exit();
        }
    }

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? "/admin/opportunities.php"));
exit();
