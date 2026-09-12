<?php
// actions/submit-training-feedback.php - Unified Post-Training Feedback Submission
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

$user = getCurrentUser();
if (!$user) {
    header("Location: /login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignmentId = trim($_POST['assignmentId'] ?? '');
    $feedbackType = strtoupper(trim($_POST['feedbackType'] ?? '')); // 'TRAINER' or 'VENDOR' or 'ADMIN'
    $rating = max(1.0, min(5.0, (float)($_POST['rating'] ?? 5.0)));
    $comments = trim($_POST['comments'] ?? '');
    $redirectUrl = $_POST['redirectUrl'] ?? '';

    if (empty($assignmentId)) {
        header("Location: " . (!empty($redirectUrl) ? $redirectUrl : '/dashboard.php'));
        exit();
    }

    $asgCol = getCollection("Assignment");
    $trainerCol = getCollection("Trainer");
    $oppCol = getCollection("Opportunity");

    if (!$asgCol) {
        header("Location: " . (!empty($redirectUrl) ? $redirectUrl : '/dashboard.php'));
        exit();
    }

    $asgFilter = preg_match('/^[a-f0-9]{24}$/i', $assignmentId)
        ? ['_id' => new MongoDB\BSON\ObjectId($assignmentId)]
        : ['_id' => $assignmentId];

    $asg = $asgCol->findOne($asgFilter);
    if (!$asg) {
        header("Location: " . (!empty($redirectUrl) ? $redirectUrl : '/dashboard.php'));
        exit();
    }

    $now = new MongoDB\BSON\UTCDateTime();
    $trainerId = (string)($asg['trainerId'] ?? '');
    $oppId = (string)($asg['opportunityId'] ?? '');

    $opp = null;
    if (!empty($oppId) && $oppCol) {
        try {
            $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]);
        } catch (\Throwable $e) {}
    }
    $oppTitle = $opp['title'] ?? 'Campus Training';

    if ($feedbackType === 'TRAINER') {
        // Submitted by the Trainer about the College/Campus
        $studentEngagement = max(1, min(5, (int)($_POST['studentEngagement'] ?? 5)));
        $infrastructureRating = max(1, min(5, (int)($_POST['infrastructureRating'] ?? 5)));

        $feedbackData = [
            'rating' => $rating,
            'studentEngagement' => $studentEngagement,
            'infrastructureRating' => $infrastructureRating,
            'comments' => $comments,
            'submittedAt' => $now,
            'submittedByUserId' => (string)$user['id'],
            'submittedByName' => (string)($user['name'] ?? 'Trainer')
        ];

        $asgCol->updateOne(
            $asgFilter,
            [
                '$set' => [
                    'trainerFeedback' => $feedbackData,
                    'status' => 'COMPLETED',
                    'updatedAt' => $now
                ]
            ]
        );

        // Notify Admin of completed training feedback
        try {
            sendNotificationToAdmin([
                'type' => 'FEEDBACK_SUBMITTED',
                'title' => 'Trainer Feedback Received',
                'message' => ($user['name'] ?? 'Trainer') . ' submitted completion feedback for "' . $oppTitle . '" (' . $rating . '/5.0 stars).',
                'link' => '/admin/assignments.php'
            ]);
        } catch (\Throwable $e) {}

        $_SESSION['flash_success'] = "Thank you! Your campus feedback and completion review have been saved.";
        $defaultRedirect = '/trainer/assignments.php';

    } else {
        // Submitted by Vendor / College / Admin about the Trainer
        $subjectExpertise = max(1, min(5, (int)($_POST['subjectExpertise'] ?? 5)));
        $punctuality = max(1, min(5, (int)($_POST['punctuality'] ?? 5)));
        $studentSatisfaction = max(1, min(5, (int)($_POST['studentSatisfaction'] ?? 5)));

        $feedbackData = [
            'rating' => $rating,
            'subjectExpertise' => $subjectExpertise,
            'punctuality' => $punctuality,
            'studentSatisfaction' => $studentSatisfaction,
            'comments' => $comments,
            'submittedAt' => $now,
            'submittedByUserId' => (string)$user['id'],
            'submittedByName' => (string)($user['name'] ?? ($user['organizationName'] ?? 'Institution Client'))
        ];

        $asgCol->updateOne(
            $asgFilter,
            [
                '$set' => [
                    'vendorFeedback' => $feedbackData,
                    'feedbackRating' => $rating,
                    'status' => 'COMPLETED',
                    'updatedAt' => $now
                ]
            ]
        );

        // Recalculate Trainer average rating across all completed assignments
        if ($trainerCol && !empty($trainerId)) {
            try {
                $allTrainerAsgs = $asgCol->find([
                    'trainerId' => $trainerId,
                    'vendorFeedback.rating' => ['$exists' => true]
                ])->toArray();

                $totalRating = 0;
                $count = 0;
                foreach ($allTrainerAsgs as $a) {
                    if (!empty($a['vendorFeedback']['rating'])) {
                        $totalRating += (float)$a['vendorFeedback']['rating'];
                        $count++;
                    }
                }

                if ($count > 0) {
                    $avgRating = round($totalRating / $count, 1);
                    $tFilter = preg_match('/^[a-f0-9]{24}$/i', $trainerId)
                        ? ['_id' => new MongoDB\BSON\ObjectId($trainerId)]
                        : ['_id' => $trainerId];

                    $trainerCol->updateOne(
                        $tFilter,
                        [
                            '$set' => [
                                'adminRating' => $avgRating,
                                'reviewCount' => $count,
                                'updatedAt' => $now
                            ]
                        ]
                    );
                }
            } catch (\Throwable $e) {}
        }

        // Notify Trainer of evaluation
        if ($asg && !empty($asg['trainerId'])) {
            try {
                $trainerObj = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]);
                if ($trainerObj && !empty($trainerObj['userId'])) {
                    createNotification([
                        'userId' => (string)$trainerObj['userId'],
                        'type' => 'FEEDBACK_RECEIVED',
                        'title' => 'New Training Evaluation Received',
                        'message' => 'Your delivery for "' . $oppTitle . '" was evaluated with ' . $rating . '/5.0 stars.',
                        'link' => '/trainer/assignments.php'
                    ]);
                }
            } catch (\Throwable $e) {}
        }

        $_SESSION['flash_success'] = "Thank you! Trainer evaluation and performance rating submitted successfully.";
        $defaultRedirect = ($user['role'] === 'ADMIN' || $user['role'] === 'STAFF') 
            ? '/admin/assignments.php' 
            : '/vendor/assignments.php';
    }

    header("Location: " . (!empty($redirectUrl) ? $redirectUrl : $defaultRedirect));
    exit();
}

header("Location: /dashboard.php");
exit();
