<?php
// actions/assign-trainer.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opportunityId = $_POST['opportunityId'] ?? '';
    $trainerId = $_POST['trainerId'] ?? '';
    $agreedDailyRate = (float)($_POST['agreedDailyRate'] ?? 6000);
    $accommodationDetails = trim($_POST['accommodationDetails'] ?? 'Campus Guest House / Hotel Reserved');
    $travelDetails = trim($_POST['travelDetails'] ?? 'Tickets arranged by Mentry');
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($opportunityId) && !empty($trainerId)) {
        $oppCol = getCollection("Opportunity");
        $asgCol = getCollection("Assignment");
        $trainerCol = getCollection("Trainer");

        $opp = $oppCol ? $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($opportunityId)]) : null;
        $trainer = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]) : null;

        if ($opp && $trainer && $asgCol) {
            $duration = (int)($opp['durationDays'] ?? 5);
            $totalFee = $duration * $agreedDailyRate;

            $asgCol->insertOne([
                'opportunityId' => $opportunityId,
                'trainerId' => $trainerId,
                'status' => 'SCHEDULED',
                'agreedDailyRate' => $agreedDailyRate,
                'agreedTotalFee' => $totalFee,
                'startDate' => $opp['startDate'] ?? new MongoDB\BSON\UTCDateTime(),
                'durationDays' => $duration,
                'location' => ($opp['city'] ?? '') . ', ' . ($opp['state'] ?? ''),
                'accommodationDetails' => $accommodationDetails,
                'travelDetails' => $travelDetails,
                'notes' => $notes,
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            // Update opportunity
            $oppCol->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($opportunityId)],
                ['$set' => [
                    'status' => 'MATCHED',
                    'assignedTrainerId' => $trainerId,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
        }
    }
}

$opportunityId = $_POST['opportunityId'] ?? '';
header("Location: " . (!empty($opportunityId) ? "/admin/opportunity-view.php?id=" . $opportunityId : "/admin/assignments.php"));
exit();
