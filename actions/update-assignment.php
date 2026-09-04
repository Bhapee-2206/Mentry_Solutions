<?php
// actions/update-assignment.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignmentId = $_POST['assignmentId'] ?? '';
    $status = $_POST['status'] ?? 'SCHEDULED';
    $agreedDailyRate = (float)($_POST['agreedDailyRate'] ?? 0);
    $agreedTotalFee = (float)($_POST['agreedTotalFee'] ?? 0);
    $accommodationDetails = trim($_POST['accommodationDetails'] ?? '');
    $travelDetails = trim($_POST['travelDetails'] ?? '');
    $feedbackRating = (float)($_POST['feedbackRating'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($assignmentId)) {
        $asgCol = getCollection("Assignment");
        $trainerCol = getCollection("Trainer");
        $oppCol = getCollection("Opportunity");

        if ($asgCol) {
            $asg = $asgCol->findOne(['_id' => new MongoDB\BSON\ObjectId($assignmentId)]);
            if ($asg) {
                $trainerId = (string)($asg['trainerId'] ?? '');
                $oppId = (string)($asg['opportunityId'] ?? '');

                $updateData = [
                    'status' => $status,
                    'accommodationDetails' => $accommodationDetails,
                    'travelDetails' => $travelDetails,
                    'notes' => $notes,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ];

                if ($agreedDailyRate > 0) $updateData['agreedDailyRate'] = $agreedDailyRate;
                if ($agreedTotalFee > 0) $updateData['agreedTotalFee'] = $agreedTotalFee;
                if ($feedbackRating > 0) $updateData['feedbackRating'] = $feedbackRating;

                $asgCol->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($assignmentId)],
                    ['$set' => $updateData]
                );

                // Sync Trainer availability
                if ($trainerCol && !empty($trainerId)) {
                    if ($status === 'COMPLETED' || $status === 'CANCELLED') {
                        // Check if any other scheduled or in-progress assignment exists
                        $otherActive = $asgCol->findOne([
                            'trainerId' => $trainerId,
                            'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS']],
                            '_id' => ['$ne' => new MongoDB\BSON\ObjectId($assignmentId)]
                        ]);

                        if (!$otherActive) {
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

                        if ($status === 'COMPLETED' && $oppCol && !empty($oppId)) {
                            $oppCol->updateOne(
                                ['_id' => new MongoDB\BSON\ObjectId($oppId)],
                                ['$set' => ['status' => 'COMPLETED', 'updatedAt' => new MongoDB\BSON\UTCDateTime()]]
                            );
                        }
                    } elseif ($status === 'IN_PROGRESS' || $status === 'SCHEDULED') {
                        $opp = ($oppCol && !empty($oppId)) ? $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]) : null;
                        $trainerCol->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($trainerId)],
                            ['$set' => [
                                'availabilityStatus' => 'BUSY_ON_ASSIGNMENT',
                                'availabilityNotes' => 'Delivering: ' . ($opp['title'] ?? 'Campus Training'),
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

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/admin/assignments.php'));
exit();
