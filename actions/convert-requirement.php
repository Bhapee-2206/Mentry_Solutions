<?php
// actions/convert-requirement.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requirementId = $_POST['requirementId'] ?? '';

    if (!empty($requirementId)) {
        $reqCol = getCollection("CollegeRequirement");
        $oppCol = getCollection("Opportunity");

        $req = $reqCol ? $reqCol->findOne(['_id' => new MongoDB\BSON\ObjectId($requirementId)]) : null;

        if ($req && $oppCol) {
            $domain = trim($_POST['domain'] ?? ($req['trainingDomain'] ?? 'Technical Training'));
            $jobId = getNextSequentialMentryId('OPPORTUNITY');
            
            $collegeBudget = (float)($req['budgetPerDay'] ?? 6000);
            $dailyRateMin = (float)($_POST['dailyRateMin'] ?? ($collegeBudget > 0 ? max(4000, round($collegeBudget * 0.75 / 500) * 500) : 5000));
            $dailyRateMax = (float)($_POST['dailyRateMax'] ?? ($collegeBudget > 0 ? max(5000, $collegeBudget) : 7000));
            
            $title = trim($_POST['title'] ?? ($req['durationDays'] . "-Day " . $domain . " Training for " . $req['institutionName']));
            $durationDays = (int)($_POST['durationDays'] ?? ($req['durationDays'] ?? 5));
            $city = trim($_POST['city'] ?? ($req['city'] ?? 'India'));
            $state = trim($_POST['state'] ?? ($req['state'] ?? 'Karnataka'));

            $oppInsert = $oppCol->insertOne([
                'jobId' => $jobId,
                'mentryId' => $jobId,
                'title' => $title,
                'domain' => $domain,
                'mode' => $req['mode'] ?? 'OFFLINE',
                'trainingType' => 'COLLEGE',
                'collegeName' => $req['institutionName'],
                'city' => $city,
                'state' => $state,
                'startDate' => $req['tentativeStartDate'] ?? new MongoDB\BSON\UTCDateTime(),
                'durationDays' => $durationDays,
                'dailyRateMin' => $dailyRateMin, // Admin-configured trainer rate!
                'dailyRateMax' => $dailyRateMax, // Admin-configured trainer rate!
                'minExperienceYears' => 3,
                'skillsRequired' => json_encode([$domain, "Hands-on Labs", "Placement Prep"]),
                'description' => "College placement workshop organized for " . $req['institutionName'] . ". " . ($req['notes'] ?? ''),
                'travelCovered' => ($req['mode'] ?? 'OFFLINE') !== 'ONLINE',
                'accommodationCovered' => ($req['mode'] ?? 'OFFLINE') !== 'ONLINE',
                'diningCovered' => ($req['mode'] ?? 'OFFLINE') !== 'ONLINE',
                'status' => 'PUBLISHED',
                'collegeRequirementId' => $requirementId,
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            $newOppId = (string)$oppInsert->getInsertedId();

            $reqCol->updateOne(
                ['_id' => $req['_id']],
                ['$set' => [
                    'status' => 'CONVERTED',
                    'convertedOpportunityId' => $newOppId,
                    'adjustedDailyRateMin' => $dailyRateMin,
                    'adjustedDailyRateMax' => $dailyRateMax,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            header("Location: /admin/opportunity-view.php?id=" . $newOppId);
            exit();
        }
    }
}

header("Location: /admin/requirements.php");
exit();
