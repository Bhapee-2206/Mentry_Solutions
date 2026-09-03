<?php
// actions/update-trainer-availability.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = getCurrentUser();
    $userId = $user['id'];

    $availabilityStatus = trim($_POST['availabilityStatus'] ?? 'AVAILABLE_NOW');
    $availableFromDate = trim($_POST['availableFromDate'] ?? '');
    $availabilityNotes = trim($_POST['availabilityNotes'] ?? '');
    $mobilityPreference = trim($_POST['mobilityPreference'] ?? 'PAN_INDIA');

    $trainerCol = getCollection("Trainer");
    if ($trainerCol) {
        $fromDateObj = !empty($availableFromDate) ? new MongoDB\BSON\UTCDateTime(strtotime($availableFromDate) * 1000) : null;

        $trainerCol->updateOne(
            ['userId' => $userId],
            ['$set' => [
                'availabilityStatus' => $availabilityStatus,
                'availableFromDate' => $fromDateObj,
                'availabilityNotes' => $availabilityNotes,
                'travelPreference' => $mobilityPreference,
                'availabilityUpdatedAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]],
            ['upsert' => true]
        );
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? '/trainer/dashboard.php';
header("Location: " . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'avail_updated=1');
exit();
