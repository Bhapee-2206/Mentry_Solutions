<?php
// actions/update-opportunity.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $status = trim($_POST['status'] ?? 'PUBLISHED');
    $title = trim($_POST['title'] ?? '');
    $domain = trim($_POST['domain'] ?? 'Programming');
    $mode = trim($_POST['mode'] ?? 'OFFLINE');
    $trainingType = trim($_POST['trainingType'] ?? 'COLLEGE');
    $collegeName = trim($_POST['collegeName'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? 'Karnataka');
    $startDate = trim($_POST['startDate'] ?? '');
    $durationDays = (int)($_POST['durationDays'] ?? 5);
    $studentCount = (int)($_POST['studentCount'] ?? 100);
    $dailyRateMin = (float)($_POST['dailyRateMin'] ?? 5000);
    $dailyRateMax = (float)($_POST['dailyRateMax'] ?? 7000);
    $minExperienceYears = (int)($_POST['minExperienceYears'] ?? 3);
    $skillsRequired = trim($_POST['skillsRequired'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $travelCovered = isset($_POST['travelCovered']) ? true : false;
    $accommodationCovered = isset($_POST['accommodationCovered']) ? true : false;
    $diningCovered = isset($_POST['diningCovered']) ? true : false;

    if (!empty($id) && !empty($title)) {
        $oppCol = getCollection("Opportunity");
        if ($oppCol) {
            $skillsArray = array_values(array_filter(array_map('trim', explode(',', $skillsRequired))));

            $oppCol->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => [
                    'title' => $title,
                    'domain' => $domain,
                    'mode' => $mode,
                    'trainingType' => $trainingType,
                    'collegeName' => $collegeName,
                    'city' => $city,
                    'state' => $state,
                    'startDate' => !empty($startDate) ? new MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000) : null,
                    'durationDays' => $durationDays,
                    'studentCount' => $studentCount,
                    'dailyRateMin' => $dailyRateMin,
                    'dailyRateMax' => $dailyRateMax,
                    'minExperienceYears' => $minExperienceYears,
                    'skillsRequired' => json_encode($skillsArray),
                    'description' => $description,
                    'travelCovered' => $travelCovered,
                    'accommodationCovered' => $accommodationCovered,
                    'diningCovered' => $diningCovered,
                    'status' => $status,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
        }
    }
}

$id = $_POST['id'] ?? '';
header("Location: " . (!empty($id) ? "/admin/opportunity-view.php?id=" . $id : "/admin/opportunities.php"));
exit();
