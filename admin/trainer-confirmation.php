<?php
// admin/trainer-confirmation.php - Dedicated Trainer Confirmation & Work Order Email System
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/mailer.php';

requireAdminOrStaff();

$oppId = trim($_GET['opp_id'] ?? ($_GET['opportunity_id'] ?? ''));
$trainerId = trim($_GET['trainer_id'] ?? '');
$requestedAction = trim($_GET['action'] ?? '');

if (empty($oppId) || empty($trainerId)) {
    $_SESSION['flash_error'] = "Missing required Opportunity ID or Trainer ID.";
    header("Location: /admin/opportunities.php");
    exit();
}

$oppCol = getCollection("Opportunity");
$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$confCol = getCollection("TrainerConfirmation");
$notifCol = getCollection("Notification");
$assignCol = getCollection("Assignment");

// 1. Fetch authoritative Opportunity from DB
$opp = null;
if (function_exists('findOpportunityById')) {
    $opp = findOpportunityById($oppId);
}
if (!$opp && $oppCol) {
    try {
        $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]);
    } catch (\Throwable $e) {
        $opp = $oppCol->findOne(['_id' => $oppId]);
    }
    if (!$opp) {
        $opp = $oppCol->findOne(['jobId' => $oppId]);
    }
}

// 2. Fetch authoritative Trainer from DB
$trainer = null;
if ($trainerCol) {
    try {
        $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]);
    } catch (\Throwable $e) {
        $trainer = $trainerCol->findOne(['_id' => $trainerId]);
    }
    if (!$trainer) {
        $trainer = $trainerCol->findOne(['trainerId' => $trainerId]);
    }
    if (!$trainer) {
        $trainer = $trainerCol->findOne(['email' => $trainerId]);
    }
}

if (!$opp || !$trainer) {
    $_SESSION['flash_error'] = "Target opportunity or trainer record could not be found.";
    header("Location: /admin/opportunities.php");
    exit();
}

// 3. Fetch associated User account
$user = null;
$trainerUserId = (string)($trainer['userId'] ?? '');
if (!empty($trainerUserId) && $userCol) {
    try {
        $user = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerUserId)]);
    } catch (\Throwable $e) {
        $user = $userCol->findOne(['_id' => $trainerUserId]);
    }
}

// 4. Fetch Assignment
$assignDoc = null;
$isAssigned = false;
$assignmentStatus = 'NOT_ASSIGNED';
if ($assignCol) {
    $assignDoc = $assignCol->findOne(['opportunityId' => (string)$oppId, 'trainerId' => (string)$trainerId]);
}
if (!empty($opp['assignedTrainerId']) && (string)$opp['assignedTrainerId'] === (string)$trainerId) {
    $isAssigned = true;
    $assignmentStatus = $assignDoc ? strtoupper($assignDoc['status'] ?? 'ASSIGNED') : 'ASSIGNED';
} elseif (!empty($opp['assignedTrainerIds']) && is_array($opp['assignedTrainerIds']) && in_array((string)$trainerId, array_map('strval', $opp['assignedTrainerIds']))) {
    $isAssigned = true;
    $assignmentStatus = $assignDoc ? strtoupper($assignDoc['status'] ?? 'ASSIGNED') : 'ASSIGNED';
} elseif ($assignDoc) {
    $isAssigned = true;
    $assignmentStatus = strtoupper($assignDoc['status'] ?? 'ASSIGNED');
}

// Merge user email/name into trainer array if missing
$trainerCombined = (array)$trainer;
if (!empty($user['email']) && empty($trainerCombined['email'])) {
    $trainerCombined['email'] = $user['email'];
}
if (!empty($user['name']) && empty($trainerCombined['name'])) {
    $trainerCombined['name'] = $user['name'];
}

// Quick action: inline update college / partner name
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_college'])) {
    requireCsrfToken();
    $newCollege = trim($_POST['quick_college_name'] ?? '');
    if (!empty($newCollege)) {
        try {
            $oppCol->updateOne(
                ['_id' => $opp['_id']],
                ['$set' => ['collegeName' => $newCollege, 'institution' => $newCollege, 'updatedAt' => new MongoDB\BSON\UTCDateTime()]]
            );
            $_SESSION['flash_success'] = "College / Partner updated to \"" . htmlspecialchars($newCollege) . "\".";
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = "Failed to update college: " . $e->getMessage();
        }
    }
    header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
    exit();
}

// 5. Authoritative Work Order Data Model (Single Source of Truth)
$auth = getAuthoritativeWorkOrderData($opp, $trainerCombined, $assignDoc);

$trainerName = $auth['trainerName'];
$trainerEmail = $auth['trainerEmail'];
$trainerCode = $auth['trainerCode'];
$oppTitle = $auth['course'];
$oppCode = $auth['oppCode'];
$collegeName = $auth['college'];
$hasCollege = $auth['hasCollege'];
$location = $auth['location'];
$mode = $auth['mode'];
$modeLabel = $auth['modeLabel'];
$startDate = $auth['startDate'];
$endDate = $auth['endDate'];
$dates = $auth['dates'];
$durationText = $auth['workingDays'];
$rateText = $auth['rate'];
$oppStatus = strtoupper($opp['status'] ?? 'PUBLISHED');
$canonicalOppUrl = $auth['canonicalOppUrl'];
$portalUrl = $auth['portalUrl'];
$baseUrl = function_exists('getAppUrl') ? getAppUrl() : 'https://mentry-solutions.vercel.app';
$emergencyContacts = $auth['emergencyContacts'];
$missingFields = $auth['missingFields'];
$canSendConfirmation = $auth['isValid'] && $isAssigned;

if (!$isAssigned) {
    $missingFields[] = 'Trainer Assignment (Trainer not assigned to opportunity)';
}

// Authoritative Placeholder Replacement Mapping
$placeholderMap = [
    '{{trainer_name}}' => $trainerName,
    '{{course_title}}' => $oppTitle,
    '{{college_name}}' => $collegeName ?: '[College / Partner not provided]',
    '{{location}}' => $location,
    '{{mode}}' => $modeLabel,
    '{{start_date}}' => $startDate,
    '{{end_date}}' => $endDate,
    '{{working_days}}' => $durationText,
    '{{trainer_rate}}' => $rateText,
    '{{opportunity_url}}' => $canonicalOppUrl,
    '{{portal_url}}' => $portalUrl,
    '{{mentry_website}}' => $baseUrl . '/',
    '{{website_url}}' => $baseUrl . '/',
    '{{emergency_contacts}}' => $emergencyContacts
];

// Resolved Field Values Dictionary for Insert Field Toolbar
$resolvedFieldsMap = [
    'trainer_name' => $trainerName,
    'course_title' => $oppTitle,
    'college_name' => $collegeName ?: '[College / Partner not provided]',
    'location' => $location,
    'mode' => $modeLabel,
    'dates' => $dates,
    'working_days' => $durationText,
    'trainer_rate' => $rateText,
    'portal_url' => $portalUrl,
    'website_url' => $baseUrl . '/',
    'emergency_contacts' => $emergencyContacts
];

// Helper: Sanitize Email HTML
function sanitizeEmailHtmlContent($html) {
    if (empty($html)) return '';
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $html);
    $html = preg_replace('/\s*on[a-z]+\s*=\s*(["\']).*?\1/i', '', $html);
    $html = preg_replace('/\s*on[a-z]+\s*=\s*[^ >]+/i', '', $html);
    $html = preg_replace('/href\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', 'href="#"', $html);
    $html = preg_replace('/src\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', 'src=""', $html);
    return $html;
}

// Helper: Extract editable card from full html
function extractEditableCardHtml($html) {
    if (empty($html)) return '';
    if (preg_match('/<table[^>]*max-width:\s*(?:580|620)px[^>]*>(.*?)<\/table>\s*<\/td>\s*<\/tr>\s*<\/table>/is', $html, $matches)) {
        return '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b;">' . $matches[1] . '</table>';
    }
    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
        return $matches[1];
    }
    return $html;
}

// Helper: Wrap card HTML in compliant responsive email container
function wrapEmailCardForDelivery($cardHtml, $subject = 'Work Order Confirmation') {
    if (stripos($cardHtml, '<!DOCTYPE') !== false || stripos($cardHtml, '<html') !== false) {
        return $cardHtml;
    }
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title>
</head>
<body bgcolor="#f8fafc" style="margin: 0; padding: 16px 8px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#f8fafc" style="background-color: #f8fafc;">
        <tr>
            <td align="center" style="padding: 0;">
                <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="max-width: 580px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                    <tr>
                        <td>
                            ' . $cardHtml . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

// Retrieve confirmation audit history for this opportunity and trainer
$history = [];
if ($confCol) {
    $history = $confCol->find(
        [
            'opportunityId' => (string)$oppId,
            'trainerId' => (string)$trainerId
        ],
        ['sort' => ['updatedAt' => -1, 'createdAt' => -1]]
    )->toArray();
}

// Find existing draft if any
$activeDraft = null;
foreach ($history as $h) {
    if (($h['status'] ?? '') === 'DRAFT') {
        $activeDraft = $h;
        break;
    }
}

// Identify Last Sent Record
$lastSent = null;
$lastFailed = null;
foreach ($history as $h) {
    if (($h['status'] ?? '') === 'SENT' && !$lastSent) {
        $lastSent = $h;
    } elseif (($h['status'] ?? '') === 'FAILED' && !$lastFailed) {
        $lastFailed = $h;
    }
}

// Detect if opportunity data changed since draft was saved
$opportunityDetailsChanged = false;
if ($activeDraft && !empty($activeDraft['snapshot'])) {
    $snap = $activeDraft['snapshot'];
    if (
        ($snap['startDate'] ?? '') !== $startDate ||
        ($snap['endDate'] ?? '') !== $endDate ||
        ($snap['rate'] ?? '') !== $rateText ||
        ($snap['college'] ?? '') !== $collegeName ||
        ($snap['location'] ?? '') !== $location
    ) {
        $opportunityDetailsChanged = true;
    }
}

// Handle "Refresh From Opportunity" action
if ($requestedAction === 'refresh_from_opp') {
    $isRevised = !empty($lastSent);
    $generated = generateWorkOrderEmailData($opp, $trainerCombined, $user, $assignDoc, $isRevised);
    
    // Resolve all supported placeholders immediately
    $resolvedHtml = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $generated['html']);
    $resolvedSubject = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $generated['subject']);
    $resolvedPlain = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $generated['plainText']);

    $activeDraft = [
        'opportunityId' => (string)$oppId,
        'trainerId' => (string)$trainerId,
        'trainerEmail' => $trainerEmail,
        'trainerName' => $trainerName,
        'subject' => $resolvedSubject,
        'html' => $resolvedHtml,
        'plainText' => $resolvedPlain,
        'isRevised' => $isRevised,
        'status' => 'DRAFT',
        'snapshot' => [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'rate' => $rateText,
            'college' => $collegeName,
            'location' => $location
        ],
        'updatedAt' => new MongoDB\BSON\UTCDateTime()
    ];

    if ($confCol) {
        $confCol->updateOne(
            ['opportunityId' => (string)$oppId, 'trainerId' => (string)$trainerId, 'status' => 'DRAFT'],
            ['$set' => $activeDraft],
            ['upsert' => true]
        );
    }
    $_SESSION['flash_success'] = "Draft refreshed with current opportunity data.";
    header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
    exit();
}

// Handle "Create Revised Confirmation" or initialize fresh draft
$isRevisedAction = ($requestedAction === 'new_revised');
if (!$activeDraft || $isRevisedAction) {
    $isRevised = $isRevisedAction || (!empty($lastSent));
    $generated = generateWorkOrderEmailData($opp, $trainerCombined, $user, $assignDoc, $isRevised);
    
    // Automatic Placeholder Resolution: Resolve all placeholders immediately
    $initHtml = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $generated['html']);
    $initSubject = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $generated['subject']);
    $initPlain = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $generated['plainText']);

    // Catch any remaining legacy placeholders
    $initHtml = preg_replace_callback('/\{\{([a-zA-Z0-9_]+)\}\}/', function($m) {
        return '[' . ucwords(str_replace('_', ' ', $m[1])) . ' not provided]';
    }, $initHtml);

    $activeDraft = [
        'opportunityId' => (string)$oppId,
        'trainerId' => (string)$trainerId,
        'trainerEmail' => $trainerEmail,
        'trainerName' => $trainerName,
        'subject' => $initSubject,
        'html' => $initHtml,
        'plainText' => $initPlain,
        'isRevised' => $isRevised,
        'status' => 'DRAFT',
        'snapshot' => [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'rate' => $rateText,
            'college' => $collegeName,
            'location' => $location
        ]
    ];
} else {
    // If loading an existing draft, resolve any legacy placeholders safely without overwriting manual edits
    foreach ($placeholderMap as $phKey => $phVal) {
        $activeDraft['subject'] = str_replace($phKey, $phVal, $activeDraft['subject'] ?? '');
        $activeDraft['html'] = str_replace($phKey, $phVal, $activeDraft['html'] ?? '');
        $activeDraft['plainText'] = str_replace($phKey, $phVal, $activeDraft['plainText'] ?? '');
    }
}

// Prepare editable card content for the visual rich-text editor (RESOLVED HTML - NO RAW PLACEHOLDERS)
$editableCardHtml = extractEditableCardHtml($activeDraft['html'] ?? '');

// Handle Form Submissions (Save Draft or Send Confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action_update_college'])) {
    requireCsrfToken();
    $subAction = trim($_POST['sub_action'] ?? 'save_draft');
    $toEmail = trim($_POST['to_email'] ?? $trainerEmail);
    $ccEmail = trim($_POST['cc_email'] ?? '');
    $bccEmail = trim($_POST['bcc_email'] ?? '');
    $subject = trim($_POST['subject'] ?? ($activeDraft['subject'] ?? ''));
    $submittedHtml = trim($_POST['email_html'] ?? '');
    $isRevisedFlag = !empty($_POST['is_revised']);

    // Email Options
    $optSaveHistory = !empty($_POST['opt_save_history']);
    $optCreateNotif = !empty($_POST['opt_create_notif']);
    $optSendCopy = !empty($_POST['opt_send_copy']);

    // Sanitize HTML body
    $sanitizedCardHtml = sanitizeEmailHtmlContent($submittedHtml);
    if (empty($sanitizedCardHtml)) {
        $sanitizedCardHtml = extractEditableCardHtml($activeDraft['html'] ?? '');
    }

    $finalFullHtml = wrapEmailCardForDelivery($sanitizedCardHtml, $subject);
    $plainContent = strip_tags(str_replace(['<br>', '<br/>', '</p>', '</td>'], "\n", $sanitizedCardHtml));

    // Basic Validation
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = "A valid recipient trainer email address is required.";
        header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
        exit();
    }

    if (empty($subject) || empty($sanitizedCardHtml)) {
        $_SESSION['flash_error'] = "Email subject and body content cannot be empty.";
        header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
        exit();
    }

    $adminUser = $_SESSION['user'] ?? null;
    $adminInfo = [
        'id' => (string)($adminUser['_id'] ?? ($adminUser['id'] ?? 'admin')),
        'name' => $adminUser['name'] ?? ($adminUser['username'] ?? 'Administrator'),
        'email' => $adminUser['email'] ?? ''
    ];

    if ($subAction === 'save_draft') {
        $draftDoc = [
            'opportunityId' => (string)$oppId,
            'trainerId' => (string)$trainerId,
            'trainerEmail' => $toEmail,
            'ccEmail' => $ccEmail,
            'bccEmail' => $bccEmail,
            'trainerName' => $trainerName,
            'subject' => $subject,
            'html' => $finalFullHtml,
            'plainText' => $plainContent,
            'status' => 'DRAFT',
            'isRevised' => $isRevisedFlag,
            'snapshot' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'rate' => $rateText,
                'college' => $collegeName,
                'location' => $location
            ],
            'updatedBy' => $adminInfo,
            'updatedAt' => new MongoDB\BSON\UTCDateTime()
        ];

        if ($confCol) {
            $existingDraft = $confCol->findOne([
                'opportunityId' => (string)$oppId,
                'trainerId' => (string)$trainerId,
                'status' => 'DRAFT'
            ]);
            if ($existingDraft) {
                $confCol->updateOne(['_id' => $existingDraft['_id']], ['$set' => $draftDoc]);
            } else {
                $draftDoc['createdAt'] = new MongoDB\BSON\UTCDateTime();
                $confCol->insertOne($draftDoc);
            }
        }

        $_SESSION['flash_success'] = "Work order confirmation draft saved successfully.";
        header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
        exit();

    } elseif ($subAction === 'send_email') {
        // Send Pipeline

        // 1. Re-read fresh opportunity & trainer from DB
        $freshOpp = $oppCol->findOne(['_id' => $opp['_id']]);
        $freshTrainer = $trainerCol->findOne(['_id' => $trainer['_id']]);
        $freshAssign = $assignCol ? $assignCol->findOne(['opportunityId' => (string)$oppId, 'trainerId' => (string)$trainerId]) : null;

        if (!$freshOpp || !$freshTrainer) {
            $_SESSION['flash_error'] = "Target opportunity or trainer record is no longer available in the database.";
            header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
            exit();
        }

        // 2. Re-read authoritative data and validate required fields
        $freshAuth = getAuthoritativeWorkOrderData($freshOpp, $freshTrainer, $freshAssign);

        if (!$freshAuth['hasCollege']) {
            $_SESSION['flash_error'] = "College / Partner information is missing. Add or correct it before sending the confirmation.";
            header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
            exit();
        }

        if (!$freshAuth['isValid'] || !$isAssigned) {
            $missingList = implode(', ', $freshAuth['missingFields']);
            $_SESSION['flash_error'] = "Cannot send confirmation. Missing required fields: " . htmlspecialchars($missingList) . ". Please update the opportunity before sending.";
            header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
            exit();
        }

        // 3. Resolve any lingering placeholders with authoritative values
        $resolvedSubject = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $subject);
        $resolvedHtml = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $finalFullHtml);
        $resolvedPlain = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $plainContent);

        // Add admin copy if requested
        if ($optSendCopy && !empty($adminInfo['email'])) {
            $bccEmail = trim($bccEmail . ',' . $adminInfo['email'], ',');
        }

        try {
            $mailer = new MentryMailer();
            $meta = [
                'type' => $isRevisedFlag ? 'REVISED_WORK_ORDER_CONFIRMATION' : 'WORK_ORDER_CONFIRMATION',
                'opportunityId' => (string)$oppId,
                'trainerId' => (string)$trainerId,
                'cc' => $ccEmail,
                'bcc' => $bccEmail,
                'sentBy' => $adminInfo
            ];

            $sendResult = $mailer->send($toEmail, $trainerName, $resolvedSubject, $resolvedHtml, $resolvedPlain, $meta);

            if ($sendResult['success']) {
                // Save to audit history
                if ($optSaveHistory && $confCol) {
                    $sentDoc = [
                        'opportunityId' => (string)$oppId,
                        'trainerId' => (string)$trainerId,
                        'trainerEmail' => $toEmail,
                        'ccEmail' => $ccEmail,
                        'bccEmail' => $bccEmail,
                        'trainerName' => $trainerName,
                        'subject' => $resolvedSubject,
                        'html' => $resolvedHtml,
                        'plainText' => $resolvedPlain,
                        'status' => 'SENT',
                        'isRevised' => $isRevisedFlag,
                        'sentBy' => $adminInfo,
                        'sentAt' => new MongoDB\BSON\UTCDateTime(),
                        'createdAt' => new MongoDB\BSON\UTCDateTime(),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ];
                    $confCol->insertOne($sentDoc);

                    // Remove current pending draft once sent
                    $confCol->deleteMany([
                        'opportunityId' => (string)$oppId,
                        'trainerId' => (string)$trainerId,
                        'status' => 'DRAFT'
                    ]);
                }

                // Create In-App Notification
                if ($optCreateNotif && $notifCol && !empty($trainerUserId)) {
                    $notifCol->insertOne([
                        'userId' => $trainerUserId,
                        'trainerId' => (string)$trainerId,
                        'opportunityId' => (string)$oppId,
                        'type' => 'WORK_ORDER_CONFIRMATION',
                        'title' => "Work Order Confirmation Sent",
                        'message' => "Your work order confirmation for {$oppTitle} has been sent to your registered email address.",
                        'action' => 'View Work Order',
                        'link' => '/trainer/assignments.php',
                        'read' => false,
                        'createdAt' => new MongoDB\BSON\UTCDateTime()
                    ]);
                }

                $_SESSION['flash_success'] = "Work order confirmation email has been dispatched to {$toEmail} and recorded in audit history.";
                header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
                exit();
            } else {
                throw new Exception($sendResult['message'] ?? 'Email transmission failed');
            }
        } catch (\Throwable $e) {
            $safeErr = $e->getMessage();
            error_log("Failed to send work order confirmation email: " . $safeErr);

            // Record failure in confirmation history without altering assignment
            if ($confCol) {
                $confCol->insertOne([
                    'opportunityId' => (string)$oppId,
                    'trainerId' => (string)$trainerId,
                    'trainerEmail' => $toEmail,
                    'trainerName' => $trainerName,
                    'subject' => $resolvedSubject,
                    'html' => $resolvedHtml,
                    'status' => 'FAILED',
                    'errorMessage' => $safeErr,
                    'attemptedBy' => $adminInfo,
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]);
            }

            $_SESSION['flash_error'] = "Failed to dispatch email: " . htmlspecialchars($safeErr) . ". The trainer assignment remains intact and you can retry sending.";
            header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
            exit();
        }
    }
}

$pageTitle = "Trainer Confirmation / Work Order";
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="space-y-6 max-w-7xl mx-auto pb-20">
    <!-- HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1">
                <a href="/admin/opportunities.php" class="hover:text-slate-800">Opportunities</a>
                <span>/</span>
                <a href="/admin/opportunity-view.php?id=<?= urlencode($oppId) ?>" class="hover:text-slate-800"><?= htmlspecialchars($oppCode) ?></a>
                <span>/</span>
                <span class="text-slate-800">Trainer Confirmation</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                <span class="p-2 rounded-2xl bg-[#FE5E04]/10 text-[#FE5E04] inline-flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">mark_email_read</span>
                </span>
                Trainer Confirmation / Work Order
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Review the assigned trainer and training details, customize the confirmation email, preview the final message, and send it to the trainer.</p>
        </div>

        <a href="/admin/opportunity-view.php?id=<?= urlencode($oppId) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition shadow-xs">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            ← Back to Opportunity
        </a>
    </div>

    <!-- 4-STEP WORKFLOW STEPPER -->
    <div class="bg-white rounded-2xl border border-slate-200/90 p-3 sm:p-4 shadow-xs">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
            <div class="flex items-center gap-2.5 p-2 rounded-xl bg-orange-50 border border-[#FE5E04]/30 text-slate-900 font-bold">
                <span class="w-6 h-6 rounded-full bg-[#FE5E04] text-white flex items-center justify-center text-[11px] font-black shrink-0">1</span>
                <div>
                    <div class="text-[10px] text-[#FE5E04] font-black uppercase tracking-wider">Step 1</div>
                    <div class="text-xs font-black">Review Details</div>
                </div>
            </div>

            <div class="flex items-center gap-2.5 p-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 font-bold">
                <span class="w-6 h-6 rounded-full bg-slate-700 text-white flex items-center justify-center text-[11px] font-black shrink-0">2</span>
                <div>
                    <div class="text-[10px] text-slate-500 font-black uppercase tracking-wider">Step 2</div>
                    <div class="text-xs font-black">Compose Email</div>
                </div>
            </div>

            <div class="flex items-center gap-2.5 p-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 font-bold">
                <span class="w-6 h-6 rounded-full bg-slate-700 text-white flex items-center justify-center text-[11px] font-black shrink-0">3</span>
                <div>
                    <div class="text-[10px] text-slate-500 font-black uppercase tracking-wider">Step 3</div>
                    <div class="text-xs font-black">Preview</div>
                </div>
            </div>

            <div class="flex items-center gap-2.5 p-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 font-bold">
                <span class="w-6 h-6 rounded-full bg-slate-700 text-white flex items-center justify-center text-[11px] font-black shrink-0">4</span>
                <div>
                    <div class="text-[10px] text-slate-500 font-black uppercase tracking-wider">Step 4</div>
                    <div class="text-xs font-black">Send</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert / Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-xs animate-fadeIn">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 cursor-pointer"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between shadow-xs animate-fadeIn">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-rose-600">error</span>
                <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 cursor-pointer"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- NON-BLOCKING INLINE WARNING IF DATA IS MISSING -->
    <?php if (!$hasCollege): ?>
        <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-950 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-amber-600 text-lg">warning</span>
                <span><strong>College / Partner information is missing.</strong> Set it below or edit the opportunity before sending.</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="/admin/opportunity-view.php?id=<?= urlencode($oppId) ?>" class="px-3 py-1.5 bg-white border border-amber-300 hover:bg-amber-100 text-amber-900 rounded-lg font-bold text-xs transition">
                    Edit Opportunity
                </a>
                <button type="button" onclick="focusQuickCollegeInput()" class="px-3 py-1.5 bg-[#FE5E04] hover:bg-[#e05202] text-white rounded-lg font-bold text-xs transition cursor-pointer">
                    Add College
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- STALE DATA INLINE ALERT WITH "REFRESH FROM OPPORTUNITY" -->
    <?php if ($opportunityDetailsChanged): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-blue-600 text-xl shrink-0 mt-0.5">update</span>
                <div class="text-xs text-blue-950 leading-relaxed">
                    <strong class="font-extrabold block mb-0.5">Opportunity Details Changed</strong>
                    Opportunity details have changed since this draft was created. Please review the updated information.
                </div>
            </div>
            <a href="/admin/trainer-confirmation.php?opp_id=<?= urlencode($oppId) ?>&trainer_id=<?= urlencode($trainerId) ?>&action=refresh_from_opp" 
               class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition shadow-xs whitespace-nowrap text-center">
                Refresh From Opportunity
            </a>
        </div>
    <?php endif; ?>

    <!-- SECTION 1 — ASSIGNMENT SUMMARY CARD -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-7 shadow-card space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Authoritative Backend Record</span>
                <h2 class="text-lg font-black text-slate-900">Assignment & Work Order Summary</h2>
            </div>

            <!-- CONFIRMATION STATUS BADGE -->
            <div>
                <?php if ($lastSent): ?>
                    <div class="bg-emerald-50 border border-emerald-200 px-4 py-2 rounded-2xl text-right">
                        <span class="text-[10px] font-black uppercase text-emerald-800 tracking-wider block">Confirmation Status</span>
                        <span class="text-xs font-bold text-emerald-700 flex items-center gap-1 mt-0.5 justify-end">
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            Sent — <?= formatDate($lastSent['sentAt'] ?? null) ?>
                        </span>
                    </div>
                <?php elseif ($lastFailed): ?>
                    <div class="bg-rose-50 border border-rose-200 px-4 py-2 rounded-2xl text-right">
                        <span class="text-[10px] font-black uppercase text-rose-800 tracking-wider block">Confirmation Status</span>
                        <span class="text-xs font-bold text-rose-700 flex items-center gap-1 mt-0.5 justify-end">
                            <span class="material-symbols-outlined text-sm">warning</span>
                            Failed — Retry Available
                        </span>
                    </div>
                <?php else: ?>
                    <div class="bg-amber-50 border border-amber-200 px-4 py-2 rounded-2xl text-right">
                        <span class="text-[10px] font-black uppercase text-amber-800 tracking-wider block">Confirmation Status</span>
                        <span class="text-xs font-bold text-amber-700 flex items-center gap-1 mt-0.5 justify-end">
                            <span class="material-symbols-outlined text-sm">schedule</span>
                            Draft — Not Sent
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: TRAINER -->
            <div class="lg:col-span-4 bg-slate-50/80 rounded-2xl p-5 border border-slate-100 flex flex-col justify-between">
                <div>
                    <span class="bg-blue-50 text-blue-700 border border-blue-200/60 font-bold text-[10px] px-2.5 py-0.5 rounded-full uppercase tracking-wider">Trainer</span>
                    <h3 class="text-lg font-black text-slate-900 mt-2"><?= htmlspecialchars($trainerName) ?></h3>
                    <p class="text-xs text-slate-600 flex items-center gap-1.5 mt-1">
                        <span class="material-symbols-outlined text-sm text-slate-400">mail</span>
                        <strong><?= htmlspecialchars($trainerEmail) ?></strong>
                    </p>
                    <p class="text-xs text-slate-500 font-mono mt-1">
                        Trainer ID: <span class="bg-white px-2 py-0.5 rounded border border-slate-200 text-slate-700 font-bold"><?= htmlspecialchars($trainerCode) ?></span>
                    </p>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-200/60 flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">Assignment Status:</span>
                    <span class="bg-emerald-50 text-emerald-700 font-bold px-2 py-0.5 rounded-md uppercase text-[10px]">
                        <?= htmlspecialchars($assignmentStatus) ?>
                    </span>
                </div>
            </div>

            <!-- Right: OPPORTUNITY -->
            <div class="lg:col-span-8 bg-slate-50/80 rounded-2xl p-5 border border-slate-100">
                <div class="flex items-center justify-between mb-2">
                    <span class="bg-orange-50 text-[#FE5E04] border border-orange-200/60 font-bold text-[10px] px-2.5 py-0.5 rounded-full uppercase tracking-wider">Opportunity</span>
                    <span class="text-[10px] font-bold text-slate-500 bg-white px-2.5 py-0.5 rounded-full border border-slate-200">
                        Opportunity Status: <strong class="text-slate-800"><?= htmlspecialchars($oppStatus) ?></strong>
                    </span>
                </div>

                <h3 class="text-base font-black text-slate-900"><?= htmlspecialchars($oppTitle) ?></h3>
                <p class="text-xs text-slate-500 font-mono mt-0.5">Opportunity ID: <?= htmlspecialchars($oppCode) ?></p>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4 text-xs">
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">College / Partner</span>
                        <?php if ($hasCollege): ?>
                            <strong class="text-slate-800 truncate block mt-0.5" title="<?= htmlspecialchars($collegeName) ?>"><?= htmlspecialchars($collegeName) ?></strong>
                        <?php else: ?>
                            <span class="text-amber-600 font-bold block mt-0.5">[Not provided]</span>
                        <?php endif; ?>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Location & Mode</span>
                        <strong class="text-slate-800 truncate block mt-0.5"><?= htmlspecialchars($location) ?> (<?= htmlspecialchars($modeLabel) ?>)</strong>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Dates</span>
                        <strong class="text-blue-700 block mt-0.5"><?= htmlspecialchars($dates) ?></strong>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Working Days</span>
                        <strong class="text-slate-800 block mt-0.5"><?= htmlspecialchars($durationText) ?></strong>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Daily Rate / Honorarium</span>
                        <strong class="text-emerald-700 block mt-0.5"><?= htmlspecialchars($rateText) ?></strong>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Portal Link</span>
                        <a href="<?= htmlspecialchars($portalUrl) ?>" target="_blank" class="text-blue-600 hover:underline block mt-0.5 font-bold truncate">Trainer Portal ↗</a>
                    </div>
                </div>

                <!-- Quick Inline College Update Form -->
                <?php if (!$hasCollege): ?>
                    <form method="POST" id="quickCollegeForm" class="flex flex-wrap items-center gap-2 pt-3 mt-3 border-t border-slate-200/70">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                        <input type="hidden" name="action_update_college" value="1">
                        <label class="text-[11px] font-bold text-slate-600">Quick Set College / Partner:</label>
                        <input type="text" id="quickCollegeInput" name="quick_college_name" placeholder="e.g. ABC Engineering College" required
                               class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04] w-64">
                        <button type="submit" class="px-3 py-1.5 bg-[#FE5E04] hover:bg-[#e05202] text-white font-bold text-xs rounded-lg transition shadow-2xs cursor-pointer">
                            Save College
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MAIN TWO-PANEL WORKSPACE (COMPOSER LEFT | LIVE PREVIEW RIGHT) -->
    <form method="POST" id="workOrderForm" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
        <input type="hidden" name="sub_action" id="subActionInput" value="save_draft">
        <input type="hidden" name="is_revised" value="<?= !empty($activeDraft['isRevised']) ? '1' : '0' ?>">
        <input type="hidden" name="email_html" id="emailHtmlInput" value="<?= htmlspecialchars($editableCardHtml, ENT_QUOTES, 'UTF-8') ?>">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- LEFT COLUMN: COMPOSER -->
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-7 shadow-card space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Email Composer</h3>
                            <p class="text-xs text-slate-500">Edit the official confirmation email visually or section-by-section. All fields are auto-resolved.</p>
                        </div>
                        <?php if (!empty($activeDraft['isRevised'])): ?>
                            <span class="bg-purple-100 text-purple-800 font-extrabold text-[10px] px-3 py-1 rounded-full uppercase tracking-wider">
                                Revised Confirmation
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- EMAIL HEADER FIELDS -->
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1 uppercase tracking-wider">TO *</label>
                                <input type="email" name="to_email" id="toEmailInput" value="<?= htmlspecialchars($trainerEmail) ?>" required
                                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1 uppercase tracking-wider">CC (Optional)</label>
                                <input type="text" name="cc_email" id="ccEmailInput" value="<?= htmlspecialchars($activeDraft['ccEmail'] ?? '') ?>" placeholder="operations@mentry.co"
                                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1 uppercase tracking-wider">BCC (Optional)</label>
                                <input type="text" name="bcc_email" id="bccEmailInput" value="<?= htmlspecialchars($activeDraft['bccEmail'] ?? '') ?>" placeholder="audit@mentry.co"
                                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1 uppercase tracking-wider">SUBJECT *</label>
                            <input type="text" name="subject" id="subjectInput" value="<?= htmlspecialchars($activeDraft['subject'] ?? '') ?>" required
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">
                        </div>
                    </div>

                    <!-- STRUCTURED SECTIONS VS VISUAL EDITOR TABS -->
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="setComposerMode('visual')" id="tabVisualMode" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#FE5E04] text-white shadow-xs">
                                Visual Email Editor
                            </button>
                            <button type="button" onclick="setComposerMode('structured')" id="tabStructuredMode" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100">
                                Structured Sections (8)
                            </button>
                        </div>

                        <!-- QUICK INSERT RESOLVED FIELD DROPDOWN -->
                        <div class="flex items-center gap-2">
                            <select id="fieldInsertSelect" onchange="insertResolvedField(this.value); this.selectedIndex=0;" class="px-2.5 py-1.5 bg-white border border-[#FE5E04]/40 rounded-xl text-xs font-bold text-[#FE5E04] focus:outline-none shadow-2xs">
                                <option value="" disabled selected>+ Insert Field</option>
                                <option value="trainer_name">Trainer Name (<?= htmlspecialchars($trainerName) ?>)</option>
                                <option value="course_title">Course (<?= htmlspecialchars($oppTitle) ?>)</option>
                                <option value="college_name">College (<?= htmlspecialchars($collegeName ?: '[Not provided]') ?>)</option>
                                <option value="location">Location (<?= htmlspecialchars($location) ?>)</option>
                                <option value="mode">Mode (<?= htmlspecialchars($modeLabel) ?>)</option>
                                <option value="dates">Dates (<?= htmlspecialchars($dates) ?>)</option>
                                <option value="working_days">Working Days (<?= htmlspecialchars($durationText) ?>)</option>
                                <option value="trainer_rate">Daily Rate (<?= htmlspecialchars($rateText) ?>)</option>
                                <option value="portal_url">Trainer Portal Link</option>
                                <option value="website_url">Mentry Website</option>
                                <option value="emergency_contacts">Emergency Contacts</option>
                            </select>
                        </div>
                    </div>

                    <!-- VISUAL EDITOR CONTAINER -->
                    <div id="visualEditorContainer" class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50/60 shadow-xs">
                        <!-- Formatting Toolbar -->
                        <div class="p-2 bg-slate-100 border-b border-slate-200 flex flex-wrap items-center gap-1.5 text-xs text-slate-700">
                            <button type="button" onclick="formatDoc('bold')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Bold (Ctrl+B)">
                                <span class="material-symbols-outlined text-[18px]">format_bold</span>
                            </button>
                            <button type="button" onclick="formatDoc('italic')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Italic (Ctrl+I)">
                                <span class="material-symbols-outlined text-[18px]">format_italic</span>
                            </button>
                            <button type="button" onclick="formatDoc('underline')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Underline (Ctrl+U)">
                                <span class="material-symbols-outlined text-[18px]">format_underlined</span>
                            </button>

                            <span class="w-px h-5 bg-slate-200 mx-0.5"></span>

                            <button type="button" onclick="formatDoc('insertUnorderedList')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Bulleted List">
                                <span class="material-symbols-outlined text-[18px]">format_list_bulleted</span>
                            </button>
                            <button type="button" onclick="formatDoc('insertOrderedList')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Numbered List">
                                <span class="material-symbols-outlined text-[18px]">format_list_numbered</span>
                            </button>

                            <span class="w-px h-5 bg-slate-200 mx-0.5"></span>

                            <button type="button" onclick="formatDoc('justifyLeft')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Align Left">
                                <span class="material-symbols-outlined text-[18px]">format_align_left</span>
                            </button>
                            <button type="button" onclick="formatDoc('justifyCenter')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Align Center">
                                <span class="material-symbols-outlined text-[18px]">format_align_center</span>
                            </button>

                            <span class="w-px h-5 bg-slate-200 mx-0.5"></span>

                            <button type="button" onclick="insertLinkPrompt()" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Insert Link">
                                <span class="material-symbols-outlined text-[18px]">link</span>
                            </button>
                            <button type="button" onclick="formatDoc('removeFormat')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Clear Formatting">
                                <span class="material-symbols-outlined text-[18px]">format_clear</span>
                            </button>
                            <button type="button" onclick="formatDoc('undo')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Undo">
                                <span class="material-symbols-outlined text-[18px]">undo</span>
                            </button>
                            <button type="button" onclick="formatDoc('redo')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Redo">
                                <span class="material-symbols-outlined text-[18px]">redo</span>
                            </button>
                        </div>

                        <!-- VISUAL EMAIL BODY (RESOLVED HUMAN CONTENT - NO RAW HTML/PLACEHOLDERS) -->
                        <div class="p-4 bg-slate-50 min-h-[520px]">
                            <div id="visualEmailEditor" contenteditable="true" spellcheck="true"
                                 class="w-full bg-white border border-slate-200 rounded-2xl p-6 shadow-sm min-h-[500px] text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]/50 leading-relaxed text-sm">
                                <?= $editableCardHtml ?>
                            </div>
                        </div>
                    </div>

                    <!-- STRUCTURED SECTIONS PANEL -->
                    <div id="structuredSectionsContainer" class="hidden space-y-4">
                        <!-- Section 1: Greeting / Introduction -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4 space-y-2">
                            <label class="block text-xs font-extrabold text-slate-900 uppercase tracking-wider">1. Greeting / Introduction</label>
                            <input type="text" id="secGreeting" value="Dear <?= htmlspecialchars($trainerName) ?>," class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">
                            <textarea id="secIntroText" rows="2" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">We are pleased to confirm your assignment for the following technical training program through Mentry Solutions.</textarea>
                        </div>

                        <!-- Section 2: Work Order Details (Authoritative Summary) -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-extrabold text-slate-900 uppercase tracking-wider">2. Work Order Details Table</label>
                                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Authoritative Data</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs bg-white p-3 rounded-xl border border-slate-200 font-medium text-slate-700">
                                <div>Course: <strong class="text-slate-900"><?= htmlspecialchars($oppTitle) ?></strong></div>
                                <div>College: <strong class="text-slate-900"><?= htmlspecialchars($collegeName ?: '[College / Partner not provided]') ?></strong></div>
                                <div>Location: <strong class="text-slate-900"><?= htmlspecialchars($location) ?></strong></div>
                                <div>Mode: <strong class="text-slate-900"><?= htmlspecialchars($modeLabel) ?></strong></div>
                                <div>Dates: <strong class="text-blue-700"><?= htmlspecialchars($dates) ?></strong></div>
                                <div>Rate: <strong class="text-emerald-700"><?= htmlspecialchars($rateText) ?></strong></div>
                            </div>
                        </div>

                        <!-- Section 3: Scope of Work -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4 space-y-2">
                            <label class="block text-xs font-extrabold text-slate-900 uppercase tracking-wider">3. Scope of Work (One bullet per line)</label>
                            <textarea id="secScope" rows="3" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">Deliver technical training sessions as per the agreed schedule and curriculum.
Ensure high-quality content delivery, interactive hands-on coding, and active learner engagement.
Maintain professional conduct throughout the training assignment.</textarea>
                        </div>

                        <!-- Section 4: Grooming / Dress Code -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4 space-y-2">
                            <label class="block text-xs font-extrabold text-slate-900 uppercase tracking-wider">4. Grooming / Dress Code (One bullet per line)</label>
                            <textarea id="secGrooming" rows="2" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">Trainers are expected to follow a formal, neat, and professional dress code during training sessions.
Proper grooming and presentable attire are mandatory where applicable based on client expectations.</textarea>
                        </div>

                        <!-- Section 5: Payment Terms -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4 space-y-2">
                            <label class="block text-xs font-extrabold text-slate-900 uppercase tracking-wider">5. Payment Terms</label>
                            <textarea id="secPayment" rows="3" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">Daily honorarium of <?= htmlspecialchars($rateText) ?> will be processed upon successful completion of the training assignment and submission of attendance/feedback reports.
Payments are disbursed to your registered bank account within 10–15 business days following verification.</textarea>
                        </div>

                        <!-- Section 6: General Terms -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4 space-y-2">
                            <label class="block text-xs font-extrabold text-slate-900 uppercase tracking-wider">6. General Terms (One bullet per line)</label>
                            <textarea id="secGeneral" rows="3" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">Punctuality and strict adherence to the training schedule are mandatory.
Confidentiality of client, institution, and curriculum materials must be maintained.
Any schedule changes or deviations must be informed in advance.</textarea>
                        </div>

                        <!-- Section 7: Additional Details -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4 space-y-2">
                            <label class="block text-xs font-extrabold text-slate-900 uppercase tracking-wider">7. Additional Details</label>
                            <textarea id="secAdditional" rows="2" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">The end date of the assignment may be extended based on college holidays, schedule changes, or other approved requirements.</textarea>
                        </div>

                        <!-- Section 8: Closing / Signature -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4 space-y-2">
                            <label class="block text-xs font-extrabold text-slate-900 uppercase tracking-wider">8. Closing / Signature</label>
                            <textarea id="secClosing" rows="2" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">Regards,
Mentry Solutions
Trainer Network & Professional Training Services</textarea>
                        </div>

                        <div class="pt-2">
                            <button type="button" onclick="applyStructuredSectionsToVisual()" class="px-4 py-2 bg-[#FE5E04] text-white font-bold text-xs rounded-xl shadow-xs hover:bg-[#e05202] cursor-pointer">
                                Apply Sections to Email & Preview
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: LIVE PREVIEW & OPTIONS -->
            <div class="lg:col-span-5 space-y-6">
                <!-- EMAIL OPTIONS PANEL -->
                <div class="bg-white rounded-3xl border border-slate-200/90 p-5 sm:p-6 shadow-card space-y-3">
                    <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-slate-500">tune</span>
                        Email Options
                    </h4>
                    <div class="space-y-2.5 pt-1 text-xs">
                        <label class="flex items-center gap-2.5 cursor-pointer text-slate-700">
                            <input type="checkbox" name="opt_save_history" value="1" checked class="rounded border-slate-300 text-[#FE5E04] focus:ring-[#FE5E04]">
                            <span class="font-bold">Save to audit history</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer text-slate-700">
                            <input type="checkbox" name="opt_create_notif" value="1" checked class="rounded border-slate-300 text-[#FE5E04] focus:ring-[#FE5E04]">
                            <span class="font-bold">Create in-app notification for trainer</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer text-slate-700">
                            <input type="checkbox" name="opt_send_copy" value="1" class="rounded border-slate-300 text-[#FE5E04] focus:ring-[#FE5E04]">
                            <span>Send me a copy (<span class="font-mono"><?= htmlspecialchars($adminUser['email'] ?? 'admin') ?></span>)</span>
                        </label>
                    </div>
                </div>

                <!-- LIVE EMAIL PREVIEW PANEL -->
                <div class="bg-white rounded-3xl border border-slate-200/90 p-5 sm:p-6 shadow-card space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base text-[#FE5E04]">visibility</span>
                                Live Email Preview
                            </h4>
                            <p class="text-[10px] text-slate-400">Rendered preview as seen in Gmail / Outlook</p>
                        </div>
                        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
                            <button type="button" onclick="setPreviewDevice('desktop')" id="btnPrevDesktop" class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white text-slate-800 shadow-2xs cursor-pointer">Desktop</button>
                            <button type="button" onclick="setPreviewDevice('mobile')" id="btnPrevMobile" class="px-2.5 py-1 rounded-lg text-[10px] font-bold text-slate-500 hover:text-slate-800 cursor-pointer">Mobile</button>
                        </div>
                    </div>

                    <div class="bg-slate-100 p-3 rounded-2xl flex justify-center min-h-[500px]">
                        <div id="previewFrameContainer" class="w-full bg-white rounded-xl shadow-xs overflow-hidden transition-all duration-300">
                            <iframe id="livePreviewIframe" class="w-full h-full min-h-[520px] border-0"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOTTOM ACTION BAR -->
        <div class="sticky bottom-4 z-40 bg-white/95 backdrop-blur-md rounded-2xl border border-slate-200/90 p-4 shadow-xl flex flex-col sm:flex-row items-center justify-between gap-4">
            <!-- Left: Save Draft -->
            <div>
                <button type="button" onclick="submitForm('save_draft')" class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-base">save</span>
                    Save Draft
                </button>
            </div>

            <!-- Center: Preview Email Focus -->
            <div>
                <button type="button" onclick="focusPreview()" class="w-full sm:w-auto px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-base text-[#FE5E04]">preview</span>
                    Preview Email
                </button>
            </div>

            <!-- Right: Send Confirmation Email -->
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <a href="/admin/opportunity-view.php?id=<?= urlencode($oppId) ?>" class="w-full sm:w-auto px-4 py-2.5 text-center text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                    Cancel
                </a>
                <button type="button" onclick="openConfirmationModal()" class="w-full sm:w-auto px-7 py-2.5 bg-[#FE5E04] hover:bg-[#e05202] text-white rounded-xl text-xs font-black transition shadow-md shadow-[#FE5E04]/25 flex items-center justify-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-base">send</span>
                    SEND CONFIRMATION EMAIL
                </button>
            </div>
        </div>
    </form>

    <!-- SENT EMAIL HISTORY TABLE -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-7 shadow-card space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-slate-400">history</span>
                    Sent Email History
                </h3>
                <p class="text-xs text-slate-500">Historical record of all confirmations and revisions dispatched to this trainer.</p>
            </div>

            <!-- REVISED CONFIRMATION BUTTON -->
            <?php if (!empty($history)): ?>
                <a href="/admin/trainer-confirmation.php?opp_id=<?= urlencode($oppId) ?>&trainer_id=<?= urlencode($trainerId) ?>&action=new_revised" 
                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-purple-50 hover:bg-purple-100 border border-purple-200 text-purple-700 rounded-xl text-xs font-bold transition shadow-xs">
                    <span class="material-symbols-outlined text-sm">history_edu</span>
                    Create Revised Confirmation
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($history)): ?>
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                <span class="material-symbols-outlined text-3xl text-slate-300">drafts</span>
                <p class="text-xs text-slate-500 mt-1 font-medium">No prior work order confirmations sent for this trainer yet.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase font-black tracking-wider text-[10px]">
                            <th class="py-3 px-3">TYPE</th>
                            <th class="py-3 px-3">SUBJECT</th>
                            <th class="py-3 px-3">RECIPIENT</th>
                            <th class="py-3 px-3">STATUS</th>
                            <th class="py-3 px-3">SENT AT</th>
                            <th class="py-3 px-3 text-right">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($history as $idx => $item): 
                            $st = $item['status'] ?? 'DRAFT';
                            $isRev = !empty($item['isRevised']);
                        ?>
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="py-3.5 px-3 font-bold text-slate-800">
                                    <?php if ($isRev): ?>
                                        <span class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded text-[10px] font-extrabold uppercase">Revised</span>
                                    <?php else: ?>
                                        <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[10px] font-extrabold uppercase">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-3 font-medium text-slate-900 max-w-xs truncate" title="<?= htmlspecialchars($item['subject'] ?? '') ?>">
                                    <?= htmlspecialchars($item['subject'] ?? 'Work Order Confirmation') ?>
                                </td>
                                <td class="py-3.5 px-3 text-slate-600 font-mono text-[11px]">
                                    <?= htmlspecialchars($item['trainerEmail'] ?? $trainerEmail) ?>
                                </td>
                                <td class="py-3.5 px-3 font-bold">
                                    <?php if ($st === 'SENT'): ?>
                                        <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full text-[10px] font-black uppercase inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-xs">check</span> Sent
                                        </span>
                                    <?php elseif ($st === 'FAILED'): ?>
                                        <span class="bg-rose-100 text-rose-800 px-2 py-0.5 rounded-full text-[10px] font-black uppercase inline-flex items-center gap-1" title="<?= htmlspecialchars($item['errorMessage'] ?? '') ?>">
                                            <span class="material-symbols-outlined text-xs">warning</span> Failed
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full text-[10px] font-black uppercase inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-xs">edit</span> Draft
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-3 text-slate-500">
                                    <?= formatDate($item['sentAt'] ?? ($item['createdAt'] ?? null)) ?>
                                </td>
                                <td class="py-3.5 px-3 text-right space-x-1">
                                    <button type="button" onclick="viewHistoricalEmailModal(<?= $idx ?>)" class="text-xs font-bold text-blue-600 hover:text-blue-800 px-2.5 py-1 rounded-lg hover:bg-blue-50 cursor-pointer">
                                        View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- INLINE ATTENTION / VALIDATION MODAL (NO BROWSER ALERTS) -->
<div id="attentionModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border border-amber-200 animate-fadeIn space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <div class="flex items-center gap-2.5 text-amber-600">
                <span class="material-symbols-outlined text-2xl">warning</span>
                <h3 class="font-extrabold text-base text-slate-900">Email Needs Attention</h3>
            </div>
            <button type="button" onclick="closeAttentionModal()" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <p class="text-xs text-slate-600">The following information is missing or needs attention before sending:</p>

        <ul id="attentionList" class="bg-amber-50/80 border border-amber-200 rounded-xl p-3.5 text-xs text-amber-950 space-y-2 font-medium">
        </ul>

        <div class="flex items-center justify-end gap-2 pt-2">
            <button type="button" onclick="closeAttentionModal()" class="px-4 py-2 bg-[#FE5E04] hover:bg-[#e05202] text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer">
                Review Details
            </button>
        </div>
    </div>
</div>

<!-- FINAL CONFIRMATION DIALOG MODAL -->
<div id="finalConfirmModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-200 animate-fadeIn space-y-5">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2.5 text-[#FE5E04]">
                <span class="material-symbols-outlined text-2xl">mark_email_read</span>
                <h3 class="font-extrabold text-base text-slate-900">Send Confirmation Email?</h3>
            </div>
            <button type="button" onclick="closeConfirmationModal()" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <p class="text-xs text-slate-600 leading-relaxed">
            Please confirm the recipient and work order parameters before dispatching:
        </p>

        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80 space-y-2.5 text-xs">
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Trainer:</span>
                <strong class="text-slate-900" id="confirmTrainerNameDisplay"><?= htmlspecialchars($trainerName) ?></strong>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Email:</span>
                <strong class="text-slate-900 font-mono" id="confirmEmailDisplay"><?= htmlspecialchars($trainerEmail) ?></strong>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Opportunity:</span>
                <strong class="text-slate-900 truncate max-w-[200px]" id="confirmOppDisplay"><?= htmlspecialchars($oppTitle) ?></strong>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Dates:</span>
                <strong class="text-blue-700"><?= htmlspecialchars($dates) ?></strong>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Rate:</span>
                <strong class="text-emerald-700"><?= htmlspecialchars($rateText) ?></strong>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <button type="button" onclick="closeConfirmationModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition cursor-pointer">
                Cancel
            </button>
            <button type="button" onclick="executeSend()" class="px-5 py-2.5 bg-[#FE5E04] hover:bg-[#e05202] text-white rounded-xl text-xs font-black transition shadow-md shadow-[#FE5E04]/20 flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-base">send</span>
                Confirm & Send
            </button>
        </div>
    </div>
</div>

<!-- VIEW HISTORICAL EMAIL MODAL -->
<div id="historicalViewModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-4 sm:p-5 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-[#FE5E04]">history</span>
                <div>
                    <h3 class="font-extrabold text-sm text-white" id="histModalTitle">View Confirmation Email</h3>
                    <p class="text-[11px] text-slate-400" id="histModalRecipient"></p>
                </div>
            </div>
            <button type="button" onclick="closeHistoricalModal()" class="text-slate-400 hover:text-white p-1 rounded-lg ml-2 cursor-pointer">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <div class="p-4 bg-slate-100 flex-1 overflow-y-auto flex justify-center">
            <div id="histPreviewCard" class="w-full bg-white shadow-xs rounded-xl overflow-hidden min-h-[500px]">
                <iframe id="histIframe" class="w-full h-full min-h-[550px] border-0"></iframe>
            </div>
        </div>

        <div class="p-4 bg-white border-t border-slate-100 flex items-center justify-end">
            <button type="button" onclick="closeHistoricalModal()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200 cursor-pointer">Close</button>
        </div>
    </div>
</div>

<script>
// Authoritative JSON Data
const resolvedFields = <?= json_encode($resolvedFieldsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const historyRecords = <?= json_encode($history, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const isMissingCollege = <?= !$hasCollege ? 'true' : 'false' ?>;
const missingFieldNames = <?= json_encode($missingFields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// Visual Editor & Live Preview Synchronization
const visualEditor = document.getElementById('visualEmailEditor');
const emailHtmlInput = document.getElementById('emailHtmlInput');
const liveIframe = document.getElementById('livePreviewIframe');

function getFullEmailHtml(cardContent) {
    const subject = document.getElementById('subjectInput').value || 'Work Order Confirmation';
    if (cardContent.includes('<!DOCTYPE') || cardContent.includes('<html')) {
        return cardContent;
    }
    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>${subject}</title>
</head>
<body bgcolor="#f8fafc" style="margin: 0; padding: 16px 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#f8fafc" style="background-color: #f8fafc;">
        <tr>
            <td align="center" style="padding: 0;">
                <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="max-width: 580px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                    <tr>
                        <td>
                            ${cardContent}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>`;
}

function updateLivePreview() {
    if (!visualEditor) return;
    const content = visualEditor.innerHTML;
    emailHtmlInput.value = content;
    const fullHtml = getFullEmailHtml(content);
    liveIframe.srcdoc = fullHtml;
}

// Attach input listeners
if (visualEditor) {
    visualEditor.addEventListener('input', updateLivePreview);
}
document.getElementById('subjectInput')?.addEventListener('input', updateLivePreview);

// Initial Preview Render
document.addEventListener('DOMContentLoaded', () => {
    updateLivePreview();
});

// Rich Text Formatting Commands
function formatDoc(command, value = null) {
    visualEditor.focus();
    document.execCommand(command, false, value);
    updateLivePreview();
}

function insertLinkPrompt() {
    const url = prompt("Enter hyperlink URL (e.g., https://mentry-solutions.vercel.app/):");
    if (url) {
        formatDoc('createLink', url);
    }
}

// Quick Personalization Insert: INSERTS RESOLVED VALUES DIRECTLY (NO {{...}})
function insertResolvedField(fieldKey) {
    if (!fieldKey) return;
    const value = resolvedFields[fieldKey] || '';
    if (!value) return;
    visualEditor.focus();
    document.execCommand('insertText', false, value);
    updateLivePreview();
}

// Focus quick college input helper
function focusQuickCollegeInput() {
    const input = document.getElementById('quickCollegeInput');
    if (input) {
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        input.focus();
    }
}

// Mode Switcher (Visual vs Structured)
function setComposerMode(mode) {
    const visCont = document.getElementById('visualEditorContainer');
    const structCont = document.getElementById('structuredSectionsContainer');
    const tabV = document.getElementById('tabVisualMode');
    const tabS = document.getElementById('tabStructuredMode');

    if (mode === 'structured') {
        visCont.classList.add('hidden');
        structCont.classList.remove('hidden');
        tabS.classList.add('bg-[#FE5E04]', 'text-white', 'shadow-xs');
        tabS.classList.remove('text-slate-600', 'hover:bg-slate-100');
        tabV.classList.remove('bg-[#FE5E04]', 'text-white', 'shadow-xs');
        tabV.classList.add('text-slate-600', 'hover:bg-slate-100');
    } else {
        structCont.classList.add('hidden');
        visCont.classList.remove('hidden');
        tabV.classList.add('bg-[#FE5E04]', 'text-white', 'shadow-xs');
        tabV.classList.remove('text-slate-600', 'hover:bg-slate-100');
        tabS.classList.remove('bg-[#FE5E04]', 'text-white', 'shadow-xs');
        tabS.classList.add('text-slate-600', 'hover:bg-slate-100');
    }
}

function applyStructuredSectionsToVisual() {
    const greeting = document.getElementById('secGreeting').value.trim();
    const intro = document.getElementById('secIntroText').value.trim();
    const scopeBullets = document.getElementById('secScope').value.split('\n').filter(Boolean);
    const groomingBullets = document.getElementById('secGrooming').value.split('\n').filter(Boolean);
    const payment = document.getElementById('secPayment').value.trim();
    const generalBullets = document.getElementById('secGeneral').value.split('\n').filter(Boolean);
    const additional = document.getElementById('secAdditional').value.trim();
    const closing = document.getElementById('secClosing').value.trim().replace(/\n/g, '<br>');

    // Reconstruct clean card HTML using RESOLVED values directly
    const cardHtml = `
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b;">
        <tr>
            <td style="padding: 24px 28px;">
                <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">${greeting}</div>
                <div style="font-size: 13px; color: #334155; margin-bottom: 18px;">${intro}</div>

                <!-- WORK ORDER DETAILS TABLE -->
                <div style="font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-bottom: 6px;">WORK ORDER DETAILS</div>
                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 18px; font-size: 12px;">
                    <tr><td style="padding: 7px 12px; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9; width: 35%;">Course:</td><td style="padding: 7px 12px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9;">${resolvedFields.course_title}</td></tr>
                    <tr><td style="padding: 7px 12px; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9;">College / Partner:</td><td style="padding: 7px 12px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9;">${resolvedFields.college_name}</td></tr>
                    <tr><td style="padding: 7px 12px; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9;">Location & Mode:</td><td style="padding: 7px 12px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9;">${resolvedFields.location} (${resolvedFields.mode})</td></tr>
                    <tr><td style="padding: 7px 12px; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9;">Program Dates:</td><td style="padding: 7px 12px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9;">${resolvedFields.dates} (${resolvedFields.working_days})</td></tr>
                    <tr><td style="padding: 7px 12px; font-weight: 700; color: #64748b;">Honorarium / Rate:</td><td style="padding: 7px 12px; font-weight: 800; color: #2563eb;">${resolvedFields.trainer_rate}</td></tr>
                </table>

                <!-- Scope of Work -->
                <div style="font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-bottom: 6px;">Scope of Work</div>
                <ul style="margin: 0 0 16px 0; padding-left: 18px; font-size: 12px; color: #475569;">
                    ${scopeBullets.map(b => `<li style="margin-bottom: 3px;">${b}</li>`).join('')}
                </ul>

                <!-- Grooming / Dress Code -->
                <div style="font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-bottom: 6px;">Grooming & Dress Code</div>
                <ul style="margin: 0 0 16px 0; padding-left: 18px; font-size: 12px; color: #475569;">
                    ${groomingBullets.map(b => `<li style="margin-bottom: 3px;">${b}</li>`).join('')}
                </ul>

                <!-- Payment Terms -->
                <div style="font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-bottom: 6px;">Payment Terms</div>
                <div style="background-color: #f8fafc; border-left: 3px solid #FE5E04; padding: 10px 14px; font-size: 11px; color: #475569; white-space: pre-line; margin-bottom: 16px;">${payment}</div>

                <!-- General Terms -->
                <div style="font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-bottom: 6px;">General Terms</div>
                <ul style="margin: 0 0 16px 0; padding-left: 18px; font-size: 12px; color: #475569;">
                    ${generalBullets.map(b => `<li style="margin-bottom: 3px;">${b}</li>`).join('')}
                </ul>

                <!-- Additional Details -->
                <div style="font-size: 11px; color: #64748b; margin-bottom: 16px;">
                    <strong>Additional Details:</strong> ${additional}
                </div>

                <!-- Signature -->
                <div style="font-size: 12px; color: #334155; margin-top: 16px;">
                    ${closing}
                </div>
            </td>
        </tr>
    </table>`;

    visualEditor.innerHTML = cardHtml;
    setComposerMode('visual');
    updateLivePreview();
}

// Preview Device Toggle
function setPreviewDevice(mode) {
    const container = document.getElementById('previewFrameContainer');
    const btnD = document.getElementById('btnPrevDesktop');
    const btnM = document.getElementById('btnPrevMobile');

    if (mode === 'mobile') {
        container.style.maxWidth = '375px';
        btnM.classList.add('bg-white', 'text-slate-800', 'shadow-2xs');
        btnM.classList.remove('text-slate-500');
        btnD.classList.remove('bg-white', 'text-slate-800', 'shadow-2xs');
        btnD.classList.add('text-slate-500');
    } else {
        container.style.maxWidth = '100%';
        btnD.classList.add('bg-white', 'text-slate-800', 'shadow-2xs');
        btnD.classList.remove('text-slate-500');
        btnM.classList.remove('bg-white', 'text-slate-800', 'shadow-2xs');
        btnM.classList.add('text-slate-500');
    }
}

function focusPreview() {
    liveIframe.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Form Submission & Validation (ZERO BROWSER ALERTS)
function submitForm(subAction) {
    const content = visualEditor ? visualEditor.innerHTML : '';
    emailHtmlInput.value = content;
    document.getElementById('subActionInput').value = subAction;
    document.getElementById('workOrderForm').submit();
}

function showAttentionModal(issues) {
    const list = document.getElementById('attentionList');
    list.innerHTML = '';
    issues.forEach(issue => {
        const li = document.createElement('li');
        li.className = 'flex items-start gap-2';
        li.innerHTML = '<span class="text-amber-600 font-bold">•</span><span>' + issue + '</span>';
        list.appendChild(li);
    });
    document.getElementById('attentionModal').classList.remove('hidden');
}

function closeAttentionModal() {
    document.getElementById('attentionModal').classList.add('hidden');
}

function openConfirmationModal() {
    const issues = [];

    if (isMissingCollege) {
        issues.push("College / Partner information is missing. Add or correct it before sending.");
    }

    if (missingFieldNames && missingFieldNames.length > 0) {
        missingFieldNames.forEach(f => {
            if (!f.includes('College') && !issues.includes(f + " is missing.")) {
                issues.push(f + " is missing.");
            }
        });
    }

    const toEmail = document.getElementById('toEmailInput').value.trim();
    if (!toEmail) {
        issues.push("Recipient trainer email address is required.");
    }

    const subject = document.getElementById('subjectInput').value.trim();
    if (!subject) {
        issues.push("Email subject cannot be empty.");
    }

    // Check if any legacy {{...}} placeholders exist
    const content = visualEditor ? visualEditor.innerHTML : '';
    const placeholderRegex = /\{\{([a-zA-Z0-9_]+)\}\}/g;
    const lingering = [];
    let match;
    while ((match = placeholderRegex.exec(content)) !== null) {
        const humanName = match[1].replace(/_/g, ' ');
        if (!lingering.includes(humanName)) lingering.push(humanName);
    }
    while ((match = placeholderRegex.exec(subject)) !== null) {
        const humanName = match[1].replace(/_/g, ' ');
        if (!lingering.includes(humanName)) lingering.push(humanName);
    }
    if (lingering.length > 0) {
        issues.push("The following template fields are missing or not provided: " + lingering.join(', '));
    }

    // If issues exist, display inline Attention Modal (NO browser alert)
    if (issues.length > 0) {
        showAttentionModal(issues);
        return;
    }

    document.getElementById('confirmEmailDisplay').textContent = toEmail;
    document.getElementById('finalConfirmModal').classList.remove('hidden');
}

function closeConfirmationModal() {
    document.getElementById('finalConfirmModal').classList.add('hidden');
}

function executeSend() {
    closeConfirmationModal();
    submitForm('send_email');
}

// Historical Email Modal
let activeHistRecord = null;

function viewHistoricalEmailModal(index) {
    activeHistRecord = historyRecords[index];
    if (!activeHistRecord) return;

    document.getElementById('histModalTitle').textContent = activeHistRecord.subject || 'Work Order Confirmation';
    document.getElementById('histModalRecipient').textContent = 'Dispatched to: ' + (activeHistRecord.trainerEmail || 'Trainer');

    const html = activeHistRecord.html || ('<pre>' + (activeHistRecord.plainText || 'No content') + '</pre>');
    document.getElementById('histIframe').srcdoc = html;

    document.getElementById('historicalViewModal').classList.remove('hidden');
}

function closeHistoricalModal() {
    document.getElementById('historicalViewModal').classList.add('hidden');
}
</script>

</main>
</div>
</body>
</html>
