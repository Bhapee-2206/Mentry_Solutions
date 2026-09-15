<?php
// actions/assign-trainer.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
requireAdminOrStaff();
requireCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opportunityId = $_POST['opportunityId'] ?? '';
    $trainerId = $_POST['trainerId'] ?? '';
    $agreedDailyRate = (float)($_POST['agreedDailyRate'] ?? 6000);
    $accommodationDetails = trim($_POST['accommodationDetails'] ?? 'Campus Guest House / Hotel Reserved');
    $travelDetails = trim($_POST['travelDetails'] ?? 'Tickets arranged by Mentry');
    $notes = trim($_POST['notes'] ?? '');

    $closeOpp = !isset($_POST['closeOpportunity']) || $_POST['closeOpportunity'] === '1';
    $targetStatus = $closeOpp ? 'CLOSED' : 'MATCHED';

    if (!empty($opportunityId) && !empty($trainerId)) {
        $oppCol = getCollection("Opportunity");
        $asgCol = getCollection("Assignment");
        $trainerCol = getCollection("Trainer");

        $opp = null;
        if ($oppCol) {
            try {
                $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($opportunityId)]);
            } catch (\Throwable $e) {
                $opp = $oppCol->findOne(['_id' => $opportunityId]);
            }
        }

        $trainer = null;
        if ($trainerCol) {
            try {
                $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]);
            } catch (\Throwable $e) {
                $trainer = $trainerCol->findOne(['_id' => $trainerId]);
            }
        }

        if ($opp && $trainer && $asgCol) {
            $trainersNeeded = max(1, (int)($opp['trainersNeeded'] ?? 1));

            // Fetch current active assignments for this opportunity
            $activeAssignments = $asgCol->find([
                'opportunityId' => (string)$opportunityId,
                'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS', 'CONFIRMED', 'ASSIGNED']]
            ])->toArray();
            $activeCount = count($activeAssignments);

            // 1. Duplicate check: prevent assigning the same trainer multiple times to the same opportunity
            foreach ($activeAssignments as $act) {
                if ((string)($act['trainerId'] ?? '') === (string)$trainerId) {
                    header("Location: /admin/opportunity-view.php?id=" . urlencode($opportunityId) . "&error=" . urlencode("This trainer is already actively assigned to this opportunity."));
                    exit();
                }
            }

            // 2. Strict Quota Check: cannot assign more trainers than requested
            if ($activeCount >= $trainersNeeded) {
                header("Location: /admin/opportunity-view.php?id=" . urlencode($opportunityId) . "&error=" . urlencode("Quota full: This opportunity requires {$trainersNeeded} trainer(s), and {$activeCount} are already assigned. To assign another trainer, relieve an assigned trainer first."));
                exit();
            }

            $duration = (int)($opp['durationDays'] ?? 5);
            $totalFee = $duration * $agreedDailyRate;
            $startDate = $opp['startDate'] ?? new MongoDB\BSON\UTCDateTime();

            $asgCol->insertOne([
                'opportunityId' => $opportunityId,
                'trainerId' => $trainerId,
                'status' => 'SCHEDULED',
                'agreedDailyRate' => $agreedDailyRate,
                'agreedTotalFee' => $totalFee,
                'startDate' => $startDate,
                'durationDays' => $duration,
                'location' => ($opp['city'] ?? '') . ', ' . ($opp['state'] ?? ''),
                'accommodationDetails' => $accommodationDetails,
                'travelDetails' => $travelDetails,
                'notes' => $notes,
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            // 3. Compile all assigned trainer IDs
            $newActiveCount = $activeCount + 1;
            $allAssignedTrainerIds = [];
            foreach ($activeAssignments as $act) {
                if (!empty($act['trainerId'])) $allAssignedTrainerIds[] = (string)$act['trainerId'];
            }
            $allAssignedTrainerIds[] = (string)$trainerId;
            $allAssignedTrainerIds = array_values(array_unique($allAssignedTrainerIds));

            $isFullyStaffed = ($newActiveCount >= $trainersNeeded);
            $oppStatus = ($isFullyStaffed && $closeOpp) ? 'CLOSED' : 'PUBLISHED';

            // Update opportunity status and assigned trainers list
            $oppUpdate = [
                'status' => $oppStatus,
                'assignedTrainerId' => $trainerId,
                'assignedTrainerIds' => $allAssignedTrainerIds,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            if ($isFullyStaffed && $closeOpp) {
                $oppUpdate['closedAt'] = new MongoDB\BSON\UTCDateTime();
            }

            try {
                $oppCol->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($opportunityId)],
                    ['$set' => $oppUpdate]
                );
            } catch (\Throwable $e) {
                $oppCol->updateOne(
                    ['_id' => $opportunityId],
                    ['$set' => $oppUpdate]
                );
            }

            // Sync with linked VendorRequest if applicable
            $reqCol = getCollection("VendorRequest");
            if ($reqCol) {
                $vFilter = [];
                if (!empty($opp['vendorRequestId'])) {
                    try {
                        $vFilter = ['_id' => new MongoDB\BSON\ObjectId((string)$opp['vendorRequestId'])];
                    } catch (\Throwable $e) {
                        $vFilter = ['_id' => (string)$opp['vendorRequestId']];
                    }
                } else {
                    $vFilter = ['convertedOpportunityId' => (string)$opportunityId];
                }
                try {
                    $reqCol->updateOne(
                        $vFilter,
                        ['$set' => [
                            'status' => 'MATCHED',
                            'assignedTrainerId' => $trainerId,
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );
                } catch (\Throwable $e) {}
            }

            // Update trainer availability and status
            $startTs = time();
            if ($startDate instanceof MongoDB\BSON\UTCDateTime) {
                $startTs = round($startDate->toDateTime()->getTimestamp());
            } elseif (is_numeric($startDate)) {
                $startTs = ($startDate > 20000000000) ? round($startDate / 1000) : (int)$startDate;
            }
            $freeAfterMs = ($startTs + ($duration * 86400)) * 1000;

            $trainerCol->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($trainerId)],
                ['$set' => [
                    'availabilityStatus' => 'BUSY_ON_ASSIGNMENT',
                    'availabilityNotes' => 'Delivering: ' . ($opp['title'] ?? 'Campus Training'),
                    'availableFromDate' => new MongoDB\BSON\UTCDateTime($freeAfterMs),
                    'status' => 'APPROVED',
                    'availabilityUpdatedAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            // Dispatch real-time live notification & Web Push to the assigned trainer
            try {
                $notifCol = getCollection("Notification");
                $trainerUserId = (string)($trainer['userId'] ?? '');
                if ($notifCol && !empty($trainerUserId)) {
                    $oppTitle = trim($opp['title'] ?? 'Training Opportunity');
                    $collegeName = trim($opp['collegeName'] ?? '');
                    $collegeSuffix = !empty($collegeName) ? " at {$collegeName}" : "";
                    $oppCity = trim($opp['city'] ?? 'Campus');
                    $durStr = !empty($opp['durationDays']) ? "{$opp['durationDays']} Days" : "Workshop";
                    $datesStr = "";
                    if (!empty($opp['startDate'])) {
                        $datesStr = " from " . formatDate($opp['startDate']) . (!empty($opp['endDate']) ? " to " . formatDate($opp['endDate']) : "");
                    }
                    $feeStr = !empty($totalFee) ? " Total honorarium: " . formatINR($totalFee) . "." : "";

                    $notifTitle = "🎯 Assignment Confirmed: {$oppTitle}{$collegeSuffix}";
                    $notifMsg = "Congratulations! You have been confirmed as the faculty trainer for {$oppTitle} in {$oppCity}{$datesStr} ({$durStr}).{$feeStr} Tap to view your schedule and logistics.";

                    $insRes = $notifCol->insertOne([
                        'userId' => $trainerUserId,
                        'trainerId' => $trainerId,
                        'opportunityId' => (string)$opportunityId,
                        'type' => 'TRAINER_SELECTED',
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
                                'type' => 'TRAINER_SELECTED',
                                'trainerId' => $trainerId,
                                'opportunityId' => (string)$opportunityId,
                                'priority' => 'high'
                            ]
                        );
                    }
                }
            } catch (\Throwable $e) {
                error_log("Failed to dispatch assignment notification: " . $e->getMessage());
            }
        }
    }
}

$opportunityId = $_POST['opportunityId'] ?? '';
header("Location: " . (!empty($opportunityId) ? "/admin/opportunity-view.php?id=" . $opportunityId : "/admin/assignments.php"));
exit();
