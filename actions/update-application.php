<?php
// actions/update-application.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
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
                            'status' => 'SCHEDULED',
                            'agreedDailyRate' => (float)$dailyRate,
                            'agreedTotalFee' => (float)$totalFee,
                            'startDate' => $startDate,
                            'durationDays' => (int)$duration,
                            'location' => ($opp['city'] ?? '') . ', ' . ($opp['state'] ?? ''),
                            'accommodationDetails' => 'Campus Guest House Reserved with standard amenities',
                            'travelDetails' => 'Travel itinerary to be shared prior to batch start date',
                            'createdAt' => new MongoDB\BSON\UTCDateTime(),
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]);
                    } else {
                        $asgCol->updateOne(
                            ['_id' => $existingAsg['_id']],
                            ['$set' => ['status' => 'SCHEDULED', 'updatedAt' => new MongoDB\BSON\UTCDateTime()]]
                        );
                    }
                }

                // Update opportunity status to MATCHED / IN_PROGRESS
                if ($oppCol && !empty($oppId)) {
                    $oppCol->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($oppId)],
                        ['$set' => ['status' => 'MATCHED', 'assignedTrainerId' => $trainerId, 'updatedAt' => new MongoDB\BSON\UTCDateTime()]]
                    );
                }

                // Update Trainer: set to BUSY_ON_ASSIGNMENT and mark APPROVED
                if ($trainerCol && !empty($trainerId)) {
                    $oppTitle = $opp['title'] ?? 'Campus Training';
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
                            'availabilityNotes' => 'Delivering: ' . $oppTitle,
                            'availableFromDate' => new MongoDB\BSON\UTCDateTime($freeAfterMs),
                            'status' => 'APPROVED',
                            'availabilityUpdatedAt' => new MongoDB\BSON\UTCDateTime(),
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );
                }
            } elseif ($prevStatus === 'ACCEPTED' && $status !== 'ACCEPTED') {
                // Application was previously accepted but now revoked/rejected
                if ($asgCol) {
                    $asgCol->updateMany(
                        ['opportunityId' => $oppId, 'trainerId' => $trainerId],
                        ['$set' => ['status' => 'CANCELLED', 'updatedAt' => new MongoDB\BSON\UTCDateTime()]]
                    );
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
        }
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/admin/applications.php'));
exit();
