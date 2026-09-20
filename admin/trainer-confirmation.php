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

// Fetch authoritative Opportunity from DB
$opp = null;
try {
    $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]);
} catch (\Throwable $e) {
    $opp = $oppCol->findOne(['_id' => $oppId]);
}

// Fetch authoritative Trainer from DB
$trainer = null;
try {
    $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]);
} catch (\Throwable $e) {
    $trainer = $trainerCol->findOne(['_id' => $trainerId]);
}

if (!$opp || !$trainer) {
    $_SESSION['flash_error'] = "Target opportunity or trainer record could not be found.";
    header("Location: /admin/opportunities.php");
    exit();
}

// Fetch associated User account
$user = null;
$trainerUserId = (string)($trainer['userId'] ?? '');
if (!empty($trainerUserId) && $userCol) {
    try {
        $user = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerUserId)]);
    } catch (\Throwable $e) {
        $user = $userCol->findOne(['_id' => $trainerUserId]);
    }
}

$trainerEmail = trim($user['email'] ?? ($trainer['email'] ?? ''));
$trainerName = trim($trainer['name'] ?? ($user['name'] ?? 'Faculty Trainer'));
$trainerCode = function_exists('getMentryCode') ? getMentryCode('TRAINER', $trainer) : ($trainer['trainerId'] ?? (string)$trainer['_id']);

// Program Parameters
$oppTitle = $opp['title'] ?? 'Training Assignment';
$oppCode = function_exists('getMentryCode') ? getMentryCode('OPPORTUNITY', $opp) : ($opp['jobId'] ?? (string)$opp['_id']);
$collegeName = $opp['collegeName'] ?? ($opp['institution'] ?? 'Client Institution');
$location = trim(($opp['city'] ?? 'Campus') . (!empty($opp['state']) ? ', ' . $opp['state'] : ''));
$mode = strtoupper($opp['mode'] ?? 'OFFLINE');
$startDate = formatDate($opp['startDate'] ?? null);
$endDate = !empty($opp['endDate']) ? formatDate($opp['endDate']) : $startDate;
$durationText = function_exists('formatOpportunityDuration') ? formatOpportunityDuration($opp) : ($opp['durationDays'] ?? 5) . ' Working Days';
$rateText = formatINR($opp['dailyRateMax'] ?? ($opp['dailyRateMin'] ?? 6000)) . '/day';
$oppStatus = strtoupper($opp['status'] ?? 'PUBLISHED');

// Check Assignment Status
$isAssigned = false;
$assignmentStatus = 'NOT_ASSIGNED';
if (!empty($opp['assignedTrainerId']) && (string)$opp['assignedTrainerId'] === (string)$trainerId) {
    $isAssigned = true;
    $assignmentStatus = 'ASSIGNED';
} elseif (!empty($opp['assignedTrainerIds']) && is_array($opp['assignedTrainerIds']) && in_array((string)$trainerId, array_map('strval', $opp['assignedTrainerIds']))) {
    $isAssigned = true;
    $assignmentStatus = 'ASSIGNED';
} elseif ($assignCol) {
    $assignDoc = $assignCol->findOne(['opportunityId' => (string)$oppId, 'trainerId' => (string)$trainerId]);
    if ($assignDoc) {
        $isAssigned = true;
        $assignmentStatus = strtoupper($assignDoc['status'] ?? 'ASSIGNED');
    }
}

// Canonical URLs
$baseUrl = function_exists('getAppUrl') ? getAppUrl() : 'https://mentry-solutions.vercel.app';
$canonicalOppUrl = function_exists('getCanonicalOpportunityShareUrl') ? getCanonicalOpportunityShareUrl($opp) : ($baseUrl . '/opportunity-details.php?id=' . urlencode($oppCode));
$portalUrl = $baseUrl . '/trainer/assignments.php';
$emergencyContacts = "Operations Desk: +91 98400 12345\nFaculty Support: +91 98400 67890\nEmail: mentry.training@gmail.com";

// Placeholder Replacement Mapping
$placeholderMap = [
    '{{trainer_name}}' => $trainerName,
    '{{course_title}}' => $oppTitle,
    '{{college_name}}' => $collegeName,
    '{{location}}' => $location,
    '{{mode}}' => $mode,
    '{{start_date}}' => $startDate,
    '{{end_date}}' => $endDate,
    '{{working_days}}' => $durationText,
    '{{trainer_rate}}' => $rateText,
    '{{opportunity_url}}' => $canonicalOppUrl,
    '{{portal_url}}' => $portalUrl,
    '{{website_url}}' => $baseUrl . '/',
    '{{emergency_contacts}}' => $emergencyContacts
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
    if (preg_match('/<table[^>]*max-width:\s*620px[^>]*>(.*?)<\/table>\s*<\/td>\s*<\/tr>\s*<\/table>/is', $html, $matches)) {
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
    <title>' . htmlspecialchars($subject) . '</title>
</head>
<body style="margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #1e293b;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 620px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
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

// Detect if opportunity data changed since draft was saved (Section 12)
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

// Handle "Create Revised Confirmation" or initialize fresh draft
$isRevisedAction = ($requestedAction === 'new_revised');
if (!$activeDraft || $isRevisedAction) {
    $isRevised = $isRevisedAction || (!empty($lastSent));
    $generated = generateWorkOrderEmailData($opp, $trainer, $user, [], $isRevised);
    $activeDraft = [
        'opportunityId' => (string)$oppId,
        'trainerId' => (string)$trainerId,
        'trainerEmail' => $trainerEmail,
        'trainerName' => $trainerName,
        'subject' => $generated['subject'],
        'html' => $generated['html'],
        'plainText' => $generated['plainText'],
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
}

// Prepare editable card content for the visual rich-text editor
$editableCardHtml = extractEditableCardHtml($activeDraft['html'] ?? '');

// Handle Form Submissions (Save Draft or Send Confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $subAction = trim($_POST['sub_action'] ?? 'save_draft');
    $toEmail = trim($_POST['to_email'] ?? $trainerEmail);
    $ccEmail = trim($_POST['cc_email'] ?? '');
    $bccEmail = trim($_POST['bcc_email'] ?? '');
    $subject = trim($_POST['subject'] ?? ($activeDraft['subject'] ?? ''));
    $submittedHtml = trim($_POST['email_html'] ?? '');
    $isRevisedFlag = !empty($_POST['is_revised']);

    // Email Options (Section 6)
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
        // Section 15: Confirmation Send Logic

        // 1. Resolve placeholders automatically with current data
        $resolvedSubject = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $subject);
        $resolvedHtml = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $finalFullHtml);
        $resolvedPlain = str_replace(array_keys($placeholderMap), array_values($placeholderMap), $plainContent);

        // 2. Section 4: Strict Placeholder Validation — Block if any unresolved {{...}} remain
        if (preg_match('/\{\{[a-zA-Z0-9_]+\}\}/', $resolvedSubject) || preg_match('/\{\{[a-zA-Z0-9_]+\}\}/', $resolvedHtml)) {
            $_SESSION['flash_error'] = "Please resolve all email placeholders before sending.";
            header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
            exit();
        }

        // 3. Re-read fresh opportunity & trainer from DB
        $freshOpp = $oppCol->findOne(['_id' => $opp['_id']]);
        $freshTrainer = $trainerCol->findOne(['_id' => $trainer['_id']]);
        if (!$freshOpp || !$freshTrainer) {
            $_SESSION['flash_error'] = "Target opportunity or trainer record is no longer available in the database.";
            header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
            exit();
        }

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
                // Save to audit history (Section 6 & 10)
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

                // Section 16: Create In-App Notification
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

            // Record failure in confirmation history without altering assignment (Section 15)
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
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Review the assigned trainer and training details, customize the confirmation email, preview it, and send it to the trainer.</p>
        </div>

        <a href="/admin/opportunity-view.php?id=<?= urlencode($oppId) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition shadow-xs">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            ← Back to Opportunity
        </a>
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

    <!-- SECTION 1 — ASSIGNMENT SUMMARY CARD -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-7 shadow-card space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Assignment Overview</span>
                <h2 class="text-lg font-black text-slate-900">Verified Work Order Summary</h2>
            </div>

            <!-- SECTION 9 — CONFIRMATION STATUS BADGE -->
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
                        ID: <span class="bg-white px-2 py-0.5 rounded border border-slate-200 text-slate-700"><?= htmlspecialchars($trainerCode) ?></span>
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
                        Status: <strong class="text-slate-800"><?= htmlspecialchars($oppStatus) ?></strong>
                    </span>
                </div>

                <h3 class="text-base font-black text-slate-900"><?= htmlspecialchars($oppTitle) ?></h3>
                <p class="text-xs text-slate-500 font-mono mt-0.5">ID: <?= htmlspecialchars($oppCode) ?></p>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4 text-xs">
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">College</span>
                        <strong class="text-slate-800 truncate block mt-0.5" title="<?= htmlspecialchars($collegeName) ?>"><?= htmlspecialchars($collegeName) ?></strong>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Location & Mode</span>
                        <strong class="text-slate-800 truncate block mt-0.5"><?= htmlspecialchars($location) ?> (<?= $mode ?>)</strong>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Dates</span>
                        <strong class="text-blue-700 block mt-0.5"><?= $startDate ?> – <?= $endDate ?></strong>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Working Days</span>
                        <strong class="text-slate-800 block mt-0.5"><?= $durationText ?></strong>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Daily Rate</span>
                        <strong class="text-emerald-700 block mt-0.5"><?= $rateText ?></strong>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/70">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Portal Link</span>
                        <a href="<?= htmlspecialchars($portalUrl) ?>" target="_blank" class="text-blue-600 hover:underline block mt-0.5 font-bold truncate">Trainer Portal ↗</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2 — ADMIN VERIFICATION BANNER -->
        <div class="bg-amber-50/90 border border-amber-200/90 rounded-2xl p-4 flex items-start gap-3 shadow-2xs">
            <span class="material-symbols-outlined text-amber-600 text-xl shrink-0 mt-0.5">verified_user</span>
            <div class="text-xs text-amber-950 leading-relaxed">
                <strong class="font-extrabold block mb-0.5">Administrator Review Required</strong>
                Please verify the trainer name (<span class="underline font-bold"><?= htmlspecialchars($trainerName) ?></span>), email address (<span class="underline font-bold"><?= htmlspecialchars($trainerEmail) ?></span>), opportunity, dates, location, and agreed remuneration before sending the confirmation email.
                The email will <strong>NOT</strong> be sent automatically.
            </div>
        </div>

        <!-- SECTION 12 — STALE DATA ALERT (If opportunity details changed) -->
        <?php if ($opportunityDetailsChanged): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex items-start gap-3 shadow-2xs">
                <span class="material-symbols-outlined text-blue-600 text-xl shrink-0 mt-0.5">update</span>
                <div class="text-xs text-blue-950 leading-relaxed">
                    <strong class="font-extrabold block mb-0.5">Opportunity Details Updated</strong>
                    Opportunity details have changed since this draft was initialized. Review the updated parameters in the summary card above before sending.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- MAIN TWO-PANEL WORKSPACE (EDITOR LEFT | PREVIEW & OPTIONS RIGHT) -->
    <form method="POST" id="workOrderForm" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
        <input type="hidden" name="sub_action" id="subActionInput" value="save_draft">
        <input type="hidden" name="is_revised" value="<?= !empty($activeDraft['isRevised']) ? '1' : '0' ?>">
        <input type="hidden" name="email_html" id="emailHtmlInput" value="<?= htmlspecialchars($editableCardHtml, ENT_QUOTES, 'UTF-8') ?>">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- LEFT COLUMN: COMPOSER (SECTION 3 & 4) -->
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-7 shadow-card space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Email Composer</h3>
                            <p class="text-xs text-slate-500">Edit the official confirmation email visually. Formatting is preserved and sent as responsive HTML.</p>
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

                    <!-- RICH-TEXT VISUAL EDITOR TOOLBAR -->
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50/60 shadow-xs">
                        <!-- Toolbar -->
                        <div class="p-2 bg-slate-100 border-b border-slate-200 flex flex-wrap items-center gap-1.5 text-xs text-slate-700">
                            <!-- Text formatting -->
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

                            <!-- Headings & paragraph -->
                            <select onchange="formatHeading(this.value); this.selectedIndex=0;" class="px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 focus:outline-none">
                                <option value="" disabled selected>Heading</option>
                                <option value="p">Normal Text</option>
                                <option value="h3">Heading 3</option>
                                <option value="h4">Heading 4</option>
                            </select>

                            <span class="w-px h-5 bg-slate-200 mx-0.5"></span>

                            <!-- Lists -->
                            <button type="button" onclick="formatDoc('insertUnorderedList')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Bulleted List">
                                <span class="material-symbols-outlined text-[18px]">format_list_bulleted</span>
                            </button>
                            <button type="button" onclick="formatDoc('insertOrderedList')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Numbered List">
                                <span class="material-symbols-outlined text-[18px]">format_list_numbered</span>
                            </button>

                            <span class="w-px h-5 bg-slate-200 mx-0.5"></span>

                            <!-- Alignment -->
                            <button type="button" onclick="formatDoc('justifyLeft')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Align Left">
                                <span class="material-symbols-outlined text-[18px]">format_align_left</span>
                            </button>
                            <button type="button" onclick="formatDoc('justifyCenter')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Align Center">
                                <span class="material-symbols-outlined text-[18px]">format_align_center</span>
                            </button>
                            <button type="button" onclick="formatDoc('justifyRight')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Align Right">
                                <span class="material-symbols-outlined text-[18px]">format_align_right</span>
                            </button>

                            <span class="w-px h-5 bg-slate-200 mx-0.5"></span>

                            <!-- Link & Table Helpers -->
                            <button type="button" onclick="insertLinkPrompt()" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Insert Link">
                                <span class="material-symbols-outlined text-[18px]">link</span>
                            </button>
                            <button type="button" onclick="insertTableHelper()" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Insert Table">
                                <span class="material-symbols-outlined text-[18px]">table</span>
                            </button>
                            <button type="button" onclick="formatDoc('removeFormat')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Clear Formatting">
                                <span class="material-symbols-outlined text-[18px]">format_clear</span>
                            </button>

                            <span class="w-px h-5 bg-slate-200 mx-0.5"></span>

                            <!-- Undo / Redo -->
                            <button type="button" onclick="formatDoc('undo')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Undo">
                                <span class="material-symbols-outlined text-[18px]">undo</span>
                            </button>
                            <button type="button" onclick="formatDoc('redo')" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-700" title="Redo">
                                <span class="material-symbols-outlined text-[18px]">redo</span>
                            </button>

                            <!-- SECTION 4 — PERSONALIZATION / INSERT FIELD DROPDOWN -->
                            <div class="ml-auto flex items-center gap-1.5">
                                <select id="fieldInsertSelect" onchange="insertPlaceholder(this.value); this.selectedIndex=0;" class="px-2.5 py-1 bg-white border border-[#FE5E04]/40 rounded-lg text-xs font-bold text-[#FE5E04] focus:outline-none shadow-2xs">
                                    <option value="" disabled selected>+ Insert Field</option>
                                    <option value="{{trainer_name}}">Trainer Name</option>
                                    <option value="{{course_title}}">Course</option>
                                    <option value="{{college_name}}">College</option>
                                    <option value="{{location}}">Location</option>
                                    <option value="{{mode}}">Mode</option>
                                    <option value="{{start_date}}">Start Date</option>
                                    <option value="{{end_date}}">End Date</option>
                                    <option value="{{working_days}}">Working Days</option>
                                    <option value="{{trainer_rate}}">Daily Rate</option>
                                    <option value="{{opportunity_url}}">Opportunity Link</option>
                                    <option value="{{portal_url}}">Trainer Portal Link</option>
                                    <option value="{{website_url}}">Mentry Website</option>
                                    <option value="{{emergency_contacts}}">Emergency Contacts</option>
                                </select>

                                <!-- Small Optional HTML Source Button for Technical Users -->
                                <button type="button" onclick="toggleSourceView()" id="btnSourceToggle" class="p-1.5 rounded-lg hover:bg-white hover:shadow-xs transition text-slate-500 hover:text-slate-800" title="Toggle HTML Source (Technical Admins)">
                                    <span class="material-symbols-outlined text-[18px]">code</span>
                                </button>
                            </div>
                        </div>

                        <!-- EMAIL BODY CANVAS (NORMAL RICH-TEXT EDITOR) -->
                        <div class="p-4 bg-slate-50 min-h-[520px]">
                            <!-- Visual ContentEditable Container -->
                            <div id="visualEmailEditor" contenteditable="true" spellcheck="true"
                                 class="w-full bg-white border border-slate-200 rounded-2xl p-6 shadow-sm min-h-[500px] text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]/50 leading-relaxed text-sm">
                                <?= $editableCardHtml ?>
                            </div>

                            <!-- Raw Source Code View (Hidden by default) -->
                            <textarea id="rawSourceTextarea" rows="22" class="hidden w-full font-mono text-xs p-4 bg-slate-950 text-emerald-400 border border-slate-800 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[#FE5E04] leading-relaxed"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
                        <span>Click directly into the email body above to customize payment terms, requirements, or schedule clauses.</span>
                        <button type="button" onclick="resolveAllPlaceholdersClient()" class="text-blue-600 hover:underline font-bold flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">auto_fix_high</span> Resolve All Fields
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: LIVE PREVIEW & OPTIONS (SECTION 5, 6, 7) -->
            <div class="lg:col-span-5 space-y-6">
                <!-- SECTION 6 — EMAIL OPTIONS PANEL -->
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

                <!-- SECTION 7 — ATTACHMENTS (OPTIONAL) -->
                <div class="bg-white rounded-3xl border border-slate-200/90 p-5 sm:p-6 shadow-card space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base text-slate-500">attach_file</span>
                            Attachments (Optional)
                        </h4>
                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded">Future Ready</span>
                    </div>
                    <div class="border-2 border-dashed border-slate-200 hover:border-[#FE5E04]/50 rounded-2xl p-5 text-center transition cursor-pointer bg-slate-50/50">
                        <span class="material-symbols-outlined text-2xl text-slate-400">upload_file</span>
                        <p class="text-xs font-bold text-slate-700 mt-1">Upload Work Order PDF or Guidelines</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Drag and drop file or click to browse (Max 10MB)</p>
                    </div>
                </div>

                <!-- SECTION 5 — LIVE EMAIL PREVIEW PANEL -->
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
                            <button type="button" onclick="setPreviewDevice('desktop')" id="btnPrevDesktop" class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white text-slate-800 shadow-2xs">Desktop</button>
                            <button type="button" onclick="setPreviewDevice('mobile')" id="btnPrevMobile" class="px-2.5 py-1 rounded-lg text-[10px] font-bold text-slate-500 hover:text-slate-800">Mobile</button>
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

        <!-- SECTION 8 — BOTTOM ACTION BAR -->
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

    <!-- SECTION 10 — SENT EMAIL HISTORY TABLE -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-7 shadow-card space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-slate-400">history</span>
                    Sent Email History
                </h3>
                <p class="text-xs text-slate-500">Historical record of all confirmations and revisions dispatched to this trainer.</p>
            </div>

            <!-- SECTION 11 — REVISED CONFIRMATION BUTTON -->
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
                            <th class="py-3 px-3 text-right">ACTIONS</th>
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
                                        View Email
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

<!-- FINAL CONFIRMATION DIALOG MODAL (SECTION 8) -->
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
            You are about to dispatch the official work order confirmation. Please review the key recipient and opportunity details:
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
                <strong class="text-blue-700"><?= $startDate ?> – <?= $endDate ?></strong>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Rate:</span>
                <strong class="text-emerald-700"><?= $rateText ?></strong>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <button type="button" onclick="closeConfirmationModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition cursor-pointer">
                Cancel
            </button>
            <button type="button" onclick="executeSend()" class="px-5 py-2.5 bg-[#FE5E04] hover:bg-[#e05202] text-white rounded-xl text-xs font-black transition shadow-md shadow-[#FE5E04]/20 flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-base">send</span>
                Send Email
            </button>
        </div>
    </div>
</div>

<!-- VIEW HISTORICAL EMAIL MODAL (SECTION 10) -->
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
            <div class="flex items-center gap-2">
                <button type="button" onclick="setHistTab('preview')" id="histTabPreview" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white/20 text-white">View Email</button>
                <button type="button" onclick="setHistTab('source')" id="histTabSource" class="px-2.5 py-1 rounded-lg text-xs font-bold text-slate-400 hover:text-white">View Source</button>
                <button type="button" onclick="closeHistoricalModal()" class="text-slate-400 hover:text-white p-1 rounded-lg ml-2 cursor-pointer">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
        </div>

        <div class="p-4 bg-slate-100 flex-1 overflow-y-auto flex justify-center">
            <div id="histPreviewCard" class="w-full bg-white shadow-xs rounded-xl overflow-hidden min-h-[500px]">
                <iframe id="histIframe" class="w-full h-full min-h-[550px] border-0"></iframe>
            </div>
            <div id="histSourceCard" class="hidden w-full bg-slate-950 rounded-xl p-4 overflow-auto">
                <pre id="histSourcePre" class="font-mono text-xs text-emerald-400 whitespace-pre-wrap"></pre>
            </div>
        </div>

        <div class="p-4 bg-white border-t border-slate-100 flex items-center justify-end">
            <button type="button" onclick="closeHistoricalModal()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200 cursor-pointer">Close</button>
        </div>
    </div>
</div>

<script>
// Authoritative JSON Data
const placeholderValues = <?= json_encode($placeholderMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const historyRecords = <?= json_encode($history, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// Visual Editor & Live Preview Synchronization
const visualEditor = document.getElementById('visualEmailEditor');
const rawSourceArea = document.getElementById('rawSourceTextarea');
const emailHtmlInput = document.getElementById('emailHtmlInput');
const liveIframe = document.getElementById('livePreviewIframe');

let isSourceMode = false;

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
    <title>${subject}</title>
</head>
<body style="margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #1e293b;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 620px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
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
    const content = isSourceMode ? rawSourceArea.value : visualEditor.innerHTML;
    emailHtmlInput.value = content;
    const fullHtml = getFullEmailHtml(content);
    liveIframe.srcdoc = fullHtml;
}

// Attach input listeners
if (visualEditor) {
    visualEditor.addEventListener('input', updateLivePreview);
}
if (rawSourceArea) {
    rawSourceArea.addEventListener('input', updateLivePreview);
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

function formatHeading(tag) {
    visualEditor.focus();
    document.execCommand('formatBlock', false, tag);
    updateLivePreview();
}

function insertLinkPrompt() {
    const url = prompt("Enter hyperlink URL (e.g., https://mentry-solutions.vercel.app/):");
    if (url) {
        formatDoc('createLink', url);
    }
}

function insertTableHelper() {
    const tableHtml = `
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="border: 1px solid #e2e8f0; border-radius: 8px; margin: 12px 0; font-size: 12px;">
        <tr style="background: #f8fafc;">
            <th style="padding: 8px 12px; text-align: left; border-bottom: 1px solid #e2e8f0;">Item</th>
            <th style="padding: 8px 12px; text-align: left; border-bottom: 1px solid #e2e8f0;">Details</th>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9;">Scope</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9;">Technical Sessions</td>
        </tr>
    </table><p></p>`;
    visualEditor.focus();
    document.execCommand('insertHTML', false, tableHtml);
    updateLivePreview();
}

// SECTION 4: Insert Field / Personalization
function insertPlaceholder(placeholder) {
    if (!placeholder) return;
    visualEditor.focus();
    document.execCommand('insertText', false, placeholder);
    updateLivePreview();
}

function resolveAllPlaceholdersClient() {
    let html = isSourceMode ? rawSourceArea.value : visualEditor.innerHTML;
    let subject = document.getElementById('subjectInput').value;

    for (const [key, val] of Object.entries(placeholderValues)) {
        html = html.replaceAll(key, val);
        subject = subject.replaceAll(key, val);
    }

    document.getElementById('subjectInput').value = subject;
    if (isSourceMode) {
        rawSourceArea.value = html;
    } else {
        visualEditor.innerHTML = html;
    }
    updateLivePreview();
}

// Toggle HTML Source (for technical admins)
function toggleSourceView() {
    const btn = document.getElementById('btnSourceToggle');
    if (!isSourceMode) {
        rawSourceArea.value = visualEditor.innerHTML;
        visualEditor.classList.add('hidden');
        rawSourceArea.classList.remove('hidden');
        btn.classList.add('bg-slate-200', 'text-slate-950');
        isSourceMode = true;
    } else {
        visualEditor.innerHTML = rawSourceArea.value;
        rawSourceArea.classList.add('hidden');
        visualEditor.classList.remove('hidden');
        btn.classList.remove('bg-slate-200', 'text-slate-950');
        isSourceMode = false;
    }
    updateLivePreview();
}

// SECTION 5: Preview Device Toggle
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

// SECTION 8: Form Submission & Confirmation Modal
function submitForm(subAction) {
    // Sync editor content before submit
    const content = isSourceMode ? rawSourceArea.value : visualEditor.innerHTML;
    emailHtmlInput.value = content;
    document.getElementById('subActionInput').value = subAction;
    document.getElementById('workOrderForm').submit();
}

function openConfirmationModal() {
    const content = isSourceMode ? rawSourceArea.value : visualEditor.innerHTML;
    const subject = document.getElementById('subjectInput').value;
    const toEmail = document.getElementById('toEmailInput').value.trim();

    if (!toEmail) {
        alert("Please enter a valid recipient trainer email address.");
        return;
    }

    // Client-side placeholder pre-validation check
    const placeholderRegex = /\{\{[a-zA-Z0-9_]+\}\}/;
    if (placeholderRegex.test(subject) || placeholderRegex.test(content)) {
        const resolveConfirm = confirm(
            "Placeholder detected (e.g. {{trainer_name}}).\n\n" +
            "Would you like to automatically resolve all placeholders with current opportunity data before sending?"
        );
        if (resolveConfirm) {
            resolveAllPlaceholdersClient();
        } else {
            alert("Please resolve all email placeholders before sending.");
            return;
        }
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

// SECTION 10: Historical Email Modal
let activeHistRecord = null;

function viewHistoricalEmailModal(index) {
    activeHistRecord = historyRecords[index];
    if (!activeHistRecord) return;

    document.getElementById('histModalTitle').textContent = activeHistRecord.subject || 'Work Order Confirmation';
    document.getElementById('histModalRecipient').textContent = 'Dispatched to: ' + (activeHistRecord.trainerEmail || 'Trainer');

    const html = activeHistRecord.html || ('<pre>' + (activeHistRecord.plainText || 'No content') + '</pre>');
    document.getElementById('histIframe').srcdoc = html;
    document.getElementById('histSourcePre').textContent = html;

    setHistTab('preview');
    document.getElementById('historicalViewModal').classList.remove('hidden');
}

function closeHistoricalModal() {
    document.getElementById('historicalViewModal').classList.add('hidden');
}

function setHistTab(tab) {
    const pCard = document.getElementById('histPreviewCard');
    const sCard = document.getElementById('histSourceCard');
    const tPrev = document.getElementById('histTabPreview');
    const tSrc = document.getElementById('histTabSource');

    if (tab === 'source') {
        pCard.classList.add('hidden');
        sCard.classList.remove('hidden');
        tSrc.classList.add('bg-white/20', 'text-white');
        tSrc.classList.remove('text-slate-400');
        tPrev.classList.remove('bg-white/20', 'text-white');
        tPrev.classList.add('text-slate-400');
    } else {
        sCard.classList.add('hidden');
        pCard.classList.remove('hidden');
        tPrev.classList.add('bg-white/20', 'text-white');
        tPrev.classList.remove('text-slate-400');
        tSrc.classList.remove('bg-white/20', 'text-white');
        tSrc.classList.add('text-slate-400');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
