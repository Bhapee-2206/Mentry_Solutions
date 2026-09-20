<?php
// actions/update-opportunity.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/locations.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
requireAdminOrStaff();
requireCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $status = trim($_POST['status'] ?? 'PUBLISHED');
    $title = trim($_POST['title'] ?? '');
    $domain = trim($_POST['domain'] ?? 'Programming');
    $mode = trim($_POST['mode'] ?? 'OFFLINE');
    $trainingType = trim($_POST['trainingType'] ?? 'COLLEGE');
    $collegeName = trim($_POST['collegeName'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? 'Tamil Nadu');
    list($city, $state) = normalizeIndiaLocation($city, $state);
    $startDate = trim($_POST['startDate'] ?? '');
    $endDate = trim($_POST['endDate'] ?? '');
    $durationDays = (int)($_POST['durationDays'] ?? 5);
    $studentCount = (int)($_POST['studentCount'] ?? 100);
    $trainersNeeded = max(1, (int)($_POST['trainersNeeded'] ?? 1));
    $dailyRateMin = (float)($_POST['dailyRateMin'] ?? 5000);
    $dailyRateMax = (float)($_POST['dailyRateMax'] ?? 7000);
    $minExperienceYears = (int)($_POST['minExperienceYears'] ?? 3);
    $skillsRequired = trim($_POST['skillsRequired'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $travelCovered = !empty($_POST['travelCovered']);
    $accommodationCovered = !empty($_POST['accommodationCovered']);
    $diningCovered = !empty($_POST['diningCovered']);

    if (!empty($id) && !empty($title)) {
        $today = getTodayISTDate();
        $startStr = normalizeDateToISTString($startDate);
        $endStr = normalizeDateToISTString($endDate);

        // Before allowing Publish, Reopen, Republish, or Activate, validate: startDate >= today
        if (in_array($status, ['PUBLISHED', 'OPEN'])) {
            if (!$startStr || $startStr < $today) {
                header("Location: /admin/opportunity-edit.php?id=" . urlencode($id) . "&error=" . urlencode("Start date cannot be in the past. Please select today or a future date."));
                exit();
            }
        }

        // Require: endDate >= startDate
        if (!empty($startStr) && !empty($endStr) && $endStr < $startStr) {
            header("Location: /admin/opportunity-edit.php?id=" . urlencode($id) . "&error=" . urlencode("End date must be on or after the start date."));
            exit();
        }

        if (!empty($startStr) && !empty($endStr) && $durationDays <= 0) {
            $durationDays = calculateWorkingDays($startStr, $endStr);
        }

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
                    'endDate' => !empty($endDate) ? new MongoDB\BSON\UTCDateTime(strtotime($endDate) * 1000) : null,
                    'durationDays' => $durationDays,
                    'studentCount' => $studentCount,
                    'trainersNeeded' => $trainersNeeded,
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

            if ($status === 'PUBLISHED' && function_exists('notifyMatchingTrainersForOpportunity')) {
                notifyMatchingTrainersForOpportunity($id);
            }
        }
    }
}

$id = $_POST['id'] ?? '';
header("Location: " . (!empty($id) ? "/admin/opportunity-view.php?id=" . $id : "/admin/opportunities.php"));
exit();
