<?php
// actions/update-application.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
requireAdminOrStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = $_POST['applicationId'] ?? '';
    $status = $_POST['status'] ?? 'PENDING';
    $adminNotes = trim($_POST['adminNotes'] ?? '');

    if (!empty($applicationId)) {
        $appCol = getCollection("Application");
        $asgCol = getCollection("Assignment");
        $oppCol = getCollection("Opportunity");
        $trainerCol = getCollection("Trainer");

        $app = $appCol ? $appCol->findOne(['_id' => new MongoDB\BSON\ObjectId($applicationId)]) : null;
        if ($app) {
            $prevStatus = strtoupper($app['status'] ?? 'PENDING');
            $trainerId = (string)$app['trainerId'];
            $oppId = (string)$app['opportunityId'];

            $appCol->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($applicationId)],
                ['$set' => [
                    'status' => $status,
                    'adminNotes' => $adminNotes,
                    'reviewedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            // If status is ACCEPTED, automatically create/schedule Assignment & update Trainer availability
            if ($status === 'ACCEPTED') {
                $opp = ($oppCol && !empty($oppId)) ? $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]) : null;
                $duration = (int)($opp['durationDays'] ?? 5);
                $dailyRate = (float)($app['proposedDailyRate'] ?? ($opp['dailyRateMin'] ?? 5000));
                $totalFee = $duration * $dailyRate;
                $startDate = $opp['startDate'] ?? new MongoDB\BSON\UTCDateTime();

                // Check if assignment already exists for this opportunity & trainer
                $startTs = time();
                if ($startDate instanceof MongoDB\BSON\UTCDateTime) {
                    $startTs = round($startDate->toDateTime()->getTimestamp());
                } elseif (is_numeric($startDate)) {
                    $startTs = ($startDate > 20000000000) ? round($startDate / 1000) : (int)$startDate;
                } elseif (is_string($startDate)) {
                    $startTs = strtotime($startDate) ?: time();
                }
                $durationDays = max(1, (int)$duration);
                $endTs = strtotime(date('Y-m-d', $startTs) . " +{$durationDays} days") - 1;
                $endDateBson = new MongoDB\BSON\UTCDateTime($endTs * 1000);

                $now = time();
                if ($now > $endTs) {
                    $asgStatus = 'COMPLETED';
                } elseif ($now >= $startTs) {
                    $asgStatus = 'IN_PROGRESS';
                } else {
                    $asgStatus = 'SCHEDULED';
                }

                if ($asgCol) {
                    $existingAsg = $asgCol->findOne([
                        'opportunityId' => $oppId,
                        'trainerId' => $trainerId
                    ]);

                    if (!$existingAsg) {
                        $asgCol->insertOne([
                            'opportunityId' => $oppId,
                            'trainerId' => $trainerId,
                            'applicationId' => (string)$app['_id'],
                            'status' => $asgStatus,
                            'agreedDailyRate' => (float)$dailyRate,
                            'agreedTotalFee' => (float)$totalFee,
                            'startDate' => $startDate,
                            'endDate' => $endDateBson,
                            'durationDays' => $durationDays,
                            'location' => ($opp['city'] ?? '') . ', ' . ($opp['state'] ?? ''),
                            'accommodationDetails' => 'Campus Guest House Reserved with standard amenities',
                            'travelDetails' => 'Travel itinerary to be shared prior to batch start date',
                            'createdAt' => new MongoDB\BSON\UTCDateTime(),
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]);
                    } else {
                        $asgCol->updateOne(
                            ['_id' => $existingAsg['_id']],
                            ['$set' => [
                                'status' => $asgStatus,
                                'endDate' => $endDateBson,
                                'durationDays' => $durationDays,
                                'updatedAt' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );
                    }
                }

                // Update opportunity status to CLOSED (trainer selected & position filled)
                if ($oppCol && !empty($oppId)) {
                    $oppUpdate = [
                        'status' => 'CLOSED',
                        'assignedTrainerId' => $trainerId,
                        'closedAt' => new MongoDB\BSON\UTCDateTime(),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ];
                    try {
                        $oppCol->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($oppId)],
                            ['$set' => $oppUpdate]
                        );
                    } catch (\Throwable $e) {
                        $oppCol->updateOne(
                            ['_id' => $oppId],
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
                            $vFilter = ['convertedOpportunityId' => (string)$oppId];
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
                }

                // Update Trainer: set to BUSY_ON_ASSIGNMENT only if not already completed
                if ($trainerCol && !empty($trainerId)) {
                    $oppTitle = $opp['title'] ?? 'Campus Training';

                    if ($asgStatus === 'COMPLETED') {
                        // Training dates already passed; keep trainer available
                        $trainerCol->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($trainerId)],
                            ['$set' => [
                                'availabilityStatus' => 'AVAILABLE_NOW',
                                'availabilityNotes' => 'Completed delivery: ' . $oppTitle,
                                'status' => 'APPROVED',
                                'availabilityUpdatedAt' => new MongoDB\BSON\UTCDateTime(),
                                'updatedAt' => new MongoDB\BSON\UTCDateTime()
                            ], '$unset' => ['availableFromDate' => '']]
                        );
                    } else {
                        $trainerCol->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($trainerId)],
                            ['$set' => [
                                'availabilityStatus' => 'BUSY_ON_ASSIGNMENT',
                                'availabilityNotes' => 'Delivering: ' . $oppTitle,
                                'availableFromDate' => $endDateBson,
                                'status' => 'APPROVED',
                                'availabilityUpdatedAt' => new MongoDB\BSON\UTCDateTime(),
                                'updatedAt' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );
                    }
                }
            } elseif ($prevStatus === 'ACCEPTED' && $status !== 'ACCEPTED') {
                // Application was previously accepted but now revoked/rejected
                if ($asgCol) {
                    $asgCol->updateMany(
                        ['opportunityId' => $oppId, 'trainerId' => $trainerId],
                        ['$set' => ['status' => 'CANCELLED', 'updatedAt' => new MongoDB\BSON\UTCDateTime()]]
                    );
                }

                // Also reopen opportunity if it was closed with this trainer
                if ($oppCol && !empty($oppId)) {
                    try {
                        $oppCol->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($oppId), 'assignedTrainerId' => $trainerId],
                            ['$set' => [
                                'status' => 'PUBLISHED',
                                'assignedTrainerId' => null,
                                'closedAt' => null,
                                'updatedAt' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );
                    } catch (\Throwable $e) {
                        $oppCol->updateOne(
                            ['_id' => $oppId, 'assignedTrainerId' => $trainerId],
                            ['$set' => [
                                'status' => 'PUBLISHED',
                                'assignedTrainerId' => null,
                                'closedAt' => null,
                                'updatedAt' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );
                    }
                }

                // Check if trainer has other active assignments
                if ($trainerCol && !empty($trainerId) && $asgCol) {
                    $activeAsg = $asgCol->findOne([
                        'trainerId' => $trainerId,
                        'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS']]
                    ]);
                    if (!$activeAsg) {
                        $trainerCol->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($trainerId)],
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
            }

            // Dispatch real-time live notification to the trainer about application status change
            if ($status !== $prevStatus) {
                try {
                    $notifCol = getCollection("Notification");
                    $targetTrainer = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]) : null;
                    $trainerUserId = (string)($targetTrainer['userId'] ?? '');

                    if ($notifCol && !empty($trainerUserId)) {
                        $oppObj = ($oppCol && !empty($oppId)) ? $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]) : null;
                        $oppTitle = $oppObj['title'] ?? 'Training Opportunity';

                        $notifTitle = '';
                        $notifMsg = '';
                        $notifType = 'APPLICATION_' . $status;

                        if ($status === 'ACCEPTED') {
                            $notifTitle = "🎉 Application Accepted: {$oppTitle}";
                            $notifMsg = "Congratulations! Your application has been ACCEPTED by Mentry Operations. Your assignment is scheduled.";
                        } elseif ($status === 'SHORTLISTED') {
                            $notifTitle = "⭐ Shortlisted: {$oppTitle}";
                            $notifMsg = "Great news! You have been SHORTLISTED for this assignment. Operations will finalize details shortly.";
                        } elseif ($status === 'REJECTED') {
                            $notifTitle = "Application Update: {$oppTitle}";
                            $notifMsg = "Your application was not selected for this opportunity. Browse other openings on your dashboard.";
                        } else {
                            $notifTitle = "Application Status Update: {$oppTitle}";
                            $notifMsg = "Your application status is now {$status}.";
                        }

                        if (!empty($adminNotes)) {
                            $notifMsg .= " Note from Admin: \"{$adminNotes}\"";
                        }

                        $notifCol->insertOne([
                            'userId' => $trainerUserId,
                            'trainerId' => $trainerId,
                            'opportunityId' => $oppId,
                            'applicationId' => (string)$app['_id'],
                            'type' => $notifType,
                            'title' => $notifTitle,
                            'message' => $notifMsg,
                            'link' => '/trainer/applications.php',
                            'read' => false,
                            'createdAt' => new MongoDB\BSON\UTCDateTime()
                        ]);

                        // Web push notification
                        if (function_exists('dispatchWebPushNotification')) {
                            @dispatchWebPushNotification(
                                ['userId' => $trainerUserId],
                                $notifTitle,
                                $notifMsg,
                                '/trainer/applications.php'
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("Failed to create trainer status notification: " . $e->getMessage());
                }
            }
        }
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/admin/applications.php'));
exit();
