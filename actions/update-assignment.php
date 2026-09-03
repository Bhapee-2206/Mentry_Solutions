<?php
// actions/update-assignment.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

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
        if ($asgCol) {
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
        }
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/admin/assignments.php'));
exit();
