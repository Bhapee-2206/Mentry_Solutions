<?php
// actions/process-vendor-request.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = $_POST['requestId'] ?? '';
    $action = $_POST['action'] ?? 'save_discussion';

    if (!empty($requestId)) {
        $reqCol = getCollection("VendorRequest");
        $oppCol = getCollection("Opportunity");

        $req = $reqCol ? $reqCol->findOne(['_id' => new MongoDB\BSON\ObjectId($requestId)]) : null;

        if ($req) {
            $title = trim($_POST['title'] ?? ($req['title'] ?? 'Training Opportunity'));
            $domain = trim($_POST['domain'] ?? ($req['domain'] ?? 'Programming'));
            $mode = trim($_POST['mode'] ?? ($req['mode'] ?? 'OFFLINE'));
            $city = trim($_POST['city'] ?? ($req['city'] ?? 'Bengaluru'));
            $state = trim($_POST['state'] ?? ($req['state'] ?? 'Karnataka'));
            $startDate = trim($_POST['startDate'] ?? '');
            $durationDays = (int)($_POST['durationDays'] ?? ($req['durationDays'] ?? 5));
            $dailyRateMin = (float)($_POST['dailyRateMin'] ?? 6000);
            $dailyRateMax = (float)($_POST['dailyRateMax'] ?? 7500);
            $skillsRequired = trim($_POST['skillsRequired'] ?? '');
            $description = trim($_POST['description'] ?? ($req['description'] ?? ''));
            $adminNotes = trim($_POST['adminNotes'] ?? '');

            $skillsArray = array_values(array_filter(array_map('trim', explode(',', $skillsRequired))));
            $startDateObj = !empty($startDate) ? new MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000) : ($req['startDate'] ?? new MongoDB\BSON\UTCDateTime());

            if ($action === 'approve_publish' && $oppCol) {
                // Generate Job ID
                $jobId = getNextSequentialMentryId('OPPORTUNITY');

                // Create live Opportunity
                $oppInsert = $oppCol->insertOne([
                    'jobId' => $jobId,
                    'mentryId' => $jobId,
                    'title' => $title,
                    'domain' => $domain,
                    'mode' => $mode,
                    'trainingType' => 'COLLEGE',
                    'collegeName' => $req['institutionName'] ?? '',
                    'city' => $city,
                    'state' => $state,
                    'startDate' => $startDateObj,
                    'durationDays' => $durationDays,
                    'studentCount' => $req['studentCount'] ?? 100,
                    'dailyRateMin' => $dailyRateMin, // Admin-configured trainer honorarium!
                    'dailyRateMax' => $dailyRateMax, // Admin-configured trainer honorarium!
                    'minExperienceYears' => 3,
                    'skillsRequired' => json_encode($skillsArray),
                    'description' => $description,
                    'travelCovered' => $mode !== 'ONLINE',
                    'accommodationCovered' => $mode !== 'ONLINE',
                    'diningCovered' => $mode !== 'ONLINE',
                    'status' => 'PUBLISHED', // Live on opportunities.php and trainer portal!
                    'vendorRequestId' => $requestId,
                    'vendorId' => $req['vendorId'] ?? null,
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]);

                $newOppId = (string)$oppInsert->getInsertedId();

                // Update Vendor Request to APPROVED_PUBLISHED
                $reqCol->updateOne(
                    ['_id' => $req['_id']],
                    ['$set' => [
                        'status' => 'APPROVED_PUBLISHED',
                        'convertedOpportunityId' => $newOppId,
                        'adjustedDailyRateMin' => $dailyRateMin,
                        'adjustedDailyRateMax' => $dailyRateMax,
                        'adminContacted' => true,
                        'adminNotes' => $adminNotes,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );

                header("Location: /admin/opportunity-view.php?id=" . $newOppId);
                exit();
            } elseif ($action === 'reject') {
                $reqCol->updateOne(
                    ['_id' => $req['_id']],
                    ['$set' => [
                        'status' => 'REJECTED',
                        'adminNotes' => $adminNotes,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
            } else {
                // save_discussion
                $reqCol->updateOne(
                    ['_id' => $req['_id']],
                    ['$set' => [
                        'status' => 'UNDER_DISCUSSION',
                        'title' => $title,
                        'domain' => $domain,
                        'mode' => $mode,
                        'city' => $city,
                        'state' => $state,
                        'startDate' => $startDateObj,
                        'durationDays' => $durationDays,
                        'adjustedDailyRateMin' => $dailyRateMin,
                        'adjustedDailyRateMax' => $dailyRateMax,
                        'skillsRequired' => json_encode($skillsArray),
                        'description' => $description,
                        'adminContacted' => true,
                        'adminNotes' => $adminNotes,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
            }
        }
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/admin/vendor-requests.php'));
exit();
