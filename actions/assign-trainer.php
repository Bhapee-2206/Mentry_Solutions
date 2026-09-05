<?php
// actions/assign-trainer.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();

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

            // Update opportunity status to CLOSED (or MATCHED)
            $oppUpdate = [
                'status' => $targetStatus,
                'assignedTrainerId' => $trainerId,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            if ($closeOpp) {
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
        }
    }
}

$opportunityId = $_POST['opportunityId'] ?? '';
header("Location: " . (!empty($opportunityId) ? "/admin/opportunity-view.php?id=" . $opportunityId : "/admin/assignments.php"));
exit();
