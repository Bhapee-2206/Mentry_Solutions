<?php
// actions/update-application.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = $_POST['applicationId'] ?? '';
    $status = $_POST['status'] ?? 'PENDING';
    $adminNotes = trim($_POST['adminNotes'] ?? '');

    if (!empty($applicationId)) {
        $appCol = getCollection("Application");
        $asgCol = getCollection("Assignment");
        $oppCol = getCollection("Opportunity");

        $app = $appCol ? $appCol->findOne(['_id' => new MongoDB\BSON\ObjectId($applicationId)]) : null;
        if ($app) {
            $appCol->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($applicationId)],
                ['$set' => [
                    'status' => $status,
                    'adminNotes' => $adminNotes,
                    'reviewedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            // If status is ACCEPTED, automatically create or update an Assignment
            if ($status === 'ACCEPTED' && $asgCol) {
                $opp = $oppCol ? $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$app['opportunityId'])]) : null;
                $duration = $opp['durationDays'] ?? 5;
                $dailyRate = $app['proposedDailyRate'] ?? ($opp['dailyRateMin'] ?? 5000);
                $totalFee = $duration * $dailyRate;

                // Check if assignment already exists for this opportunity & trainer
                $existingAsg = $asgCol->findOne([
                    'opportunityId' => (string)$app['opportunityId'],
                    'trainerId' => (string)$app['trainerId']
                ]);

                if (!$existingAsg) {
                    $asgCol->insertOne([
                        'opportunityId' => (string)$app['opportunityId'],
                        'trainerId' => (string)$app['trainerId'],
                        'applicationId' => (string)$app['_id'],
                        'status' => 'SCHEDULED',
                        'agreedDailyRate' => (float)$dailyRate,
                        'agreedTotalFee' => (float)$totalFee,
                        'startDate' => $opp['startDate'] ?? new MongoDB\BSON\UTCDateTime(),
                        'durationDays' => (int)$duration,
                        'location' => ($opp['city'] ?? '') . ', ' . ($opp['state'] ?? ''),
                        'accommodationDetails' => 'Campus Guest House Reserved with standard amenities',
                        'travelDetails' => 'Travel itinerary to be shared prior to batch start date',
                        'createdAt' => new MongoDB\BSON\UTCDateTime(),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]);
                }

                // Update opportunity status to MATCHED / IN_PROGRESS
                if ($oppCol) {
                    $oppCol->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId((string)$app['opportunityId'])],
                        ['$set' => ['status' => 'MATCHED', 'assignedTrainerId' => (string)$app['trainerId']]]
                    );
                }
            }
        }
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/admin/applications.php'));
exit();
