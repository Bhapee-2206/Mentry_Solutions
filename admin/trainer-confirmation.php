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

$opp = null;
try {
    $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]);
} catch (\Throwable $e) {
    $opp = $oppCol->findOne(['_id' => $oppId]);
}

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

// Retrieve confirmation history for this opportunity and trainer
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

// If action is new_revised, or if no draft exists, generate fresh draft data from current opportunity data
$isRevisedAction = ($requestedAction === 'new_revised');
if (!$activeDraft || $isRevisedAction) {
    $isRevised = $isRevisedAction || (!empty($history));
    $generated = generateWorkOrderEmailData($opp, $trainer, $user, $isRevised);
    $activeDraft = [
        'opportunityId' => (string)$oppId,
        'trainerId' => (string)$trainerId,
        'trainerEmail' => $trainerEmail,
        'trainerName' => $trainerName,
        'subject' => $generated['subject'],
        'html' => $generated['html'],
        'plainText' => $generated['plainText'],
        'variables' => $generated['variables'],
        'isRevised' => $isRevised,
        'status' => 'DRAFT'
    ];
}

// Handle Form Submissions (Save Draft or Send Confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $subAction = trim($_POST['sub_action'] ?? 'save_draft');
    $toEmail = trim($_POST['to_email'] ?? $trainerEmail);
    $ccEmail = trim($_POST['cc_email'] ?? '');
    $bccEmail = trim($_POST['bcc_email'] ?? '');
    $subject = trim($_POST['subject'] ?? ($activeDraft['subject'] ?? ''));
    $htmlContent = trim($_POST['email_html'] ?? ($activeDraft['html'] ?? ''));
    $plainContent = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlContent));

    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = "A valid recipient trainer email address is required.";
        header("Location: /admin/trainer-confirmation.php?opp_id=" . urlencode($oppId) . "&trainer_id=" . urlencode($trainerId));
        exit();
    }

    if (empty($subject) || empty($htmlContent)) {
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
            'html' => $htmlContent,
            'plainText' => $plainContent,
            'status' => 'DRAFT',
            'isRevised' => !empty($_POST['is_revised']),
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
        // Strict verification before sending
        try {
            $mailer = new MentryMailer();
            $meta = [
                'type' => 'WORK_ORDER_CONFIRMATION',
                'opportunityId' => (string)$oppId,
                'trainerId' => (string)$trainerId,
                'cc' => $ccEmail,
                'bcc' => $bccEmail,
                'sentBy' => $adminInfo
            ];

            $sendResult = $mailer->send($toEmail, $trainerName, $subject, $htmlContent, $plainContent, $meta);

            if ($sendResult['success']) {
                // Record sent confirmation in audit collection
                $sentDoc = [
                    'opportunityId' => (string)$oppId,
                    'trainerId' => (string)$trainerId,
                    'trainerEmail' => $toEmail,
                    'ccEmail' => $ccEmail,
                    'bccEmail' => $bccEmail,
                    'trainerName' => $trainerName,
                    'subject' => $subject,
                    'html' => $htmlContent,
                    'plainText' => $plainContent,
                    'status' => 'SENT',
                    'isRevised' => !empty($_POST['is_revised']),
                    'sentBy' => $adminInfo,
                    'sentAt' => new MongoDB\BSON\UTCDateTime(),
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ];

                if ($confCol) {
                    $confCol->insertOne($sentDoc);
                    // Remove pending draft once sent
                    $confCol->deleteMany([
                        'opportunityId' => (string)$oppId,
                        'trainerId' => (string)$trainerId,
                        'status' => 'DRAFT'
                    ]);
                }

                // Create in-app notification for trainer (Permanent History)
                if ($notifCol && !empty($trainerUserId)) {
                    $notifCol->insertOne([
                        'userId' => $trainerUserId,
                        'trainerId' => (string)$trainerId,
                        'opportunityId' => (string)$oppId,
                        'type' => 'WORK_ORDER_CONFIRMATION',
                        'title' => "Work Order Confirmation Sent: " . ($opp['title'] ?? 'Training Program'),
                        'message' => "Your work order confirmation for " . ($opp['title'] ?? 'your training assignment') . " has been sent to your registered email address ({$toEmail}).",
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
                    'subject' => $subject,
                    'html' => $htmlContent,
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

// Prepare data for summary card
$oppTitle = $opp['title'] ?? 'Training Assignment';
$collegeName = $opp['collegeName'] ?? 'Client Institution';
$location = ($opp['city'] ?? 'Campus') . (!empty($opp['state']) ? ', ' . $opp['state'] : '');
$mode = strtoupper($opp['mode'] ?? 'OFFLINE');
$startDate = formatDate($opp['startDate'] ?? null);
$endDate = formatDate($opp['endDate'] ?? null);
$durationText = formatOpportunityDuration($opp);
$rateText = formatINR($opp['dailyRateMax'] ?? ($opp['dailyRateMin'] ?? 6000)) . '/day';

$lastSent = null;
foreach ($history as $h) {
    if (($h['status'] ?? '') === 'SENT') {
        $lastSent = $h;
        break;
    }
}
?>

<div class="space-y-8 max-w-6xl mx-auto pb-16">
    <!-- Top Navigation / Back Link -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1">
                <a href="/admin/opportunities.php" class="hover:text-slate-800">Opportunities</a>
                <span>/</span>
                <a href="/admin/opportunity-view.php?id=<?= urlencode($oppId) ?>" class="hover:text-slate-800"><?= htmlspecialchars($opp['jobId'] ?? 'Opportunity') ?></a>
                <span>/</span>
                <span class="text-slate-800">Trainer Confirmation</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                <span class="p-2 rounded-2xl bg-[#FE5E04]/10 text-[#FE5E04] inline-flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">mark_email_read</span>
                </span>
                Trainer Confirmation / Work Order
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Review, customize, preview, and dispatch the official Mentry Solutions work order confirmation.</p>
        </div>

        <a href="/admin/opportunity-view.php?id=<?= urlencode($oppId) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition shadow-xs">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Opportunity
        </a>
    </div>

    <!-- Alert / Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-rose-600">error</span>
                <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Summary & Verification Card -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-8 shadow-card space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-100">
            <div>
                <span class="bg-blue-50 text-blue-700 font-bold text-[10px] px-2.5 py-0.5 rounded-full uppercase tracking-wider">Assigned Faculty Candidate</span>
                <h2 class="text-xl font-black text-slate-900 mt-1"><?= htmlspecialchars($trainerName) ?></h2>
                <p class="text-xs text-slate-500 flex items-center gap-2 mt-0.5">
                    <span class="material-symbols-outlined text-sm text-slate-400">mail</span>
                    <strong class="text-slate-800"><?= htmlspecialchars($trainerEmail) ?></strong>
                    <?php if (empty($trainerEmail)): ?>
                        <span class="text-rose-600 font-bold bg-rose-50 px-2 py-0.5 rounded">No email registered</span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Confirmation Status Badge -->
            <div class="flex items-center gap-3">
                <?php if ($lastSent): ?>
                    <div class="bg-emerald-50 border border-emerald-200 px-4 py-2.5 rounded-2xl text-right">
                        <span class="text-[10px] font-black uppercase text-emerald-800 tracking-wider block">Confirmation Status</span>
                        <span class="text-xs font-bold text-emerald-700 flex items-center gap-1 mt-0.5">
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            Sent on <?= formatDate($lastSent['sentAt'] ?? null) ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="bg-amber-50 border border-amber-200 px-4 py-2.5 rounded-2xl text-right">
                        <span class="text-[10px] font-black uppercase text-amber-800 tracking-wider block">Confirmation Status</span>
                        <span class="text-xs font-bold text-amber-700 flex items-center gap-1 mt-0.5">
                            <span class="material-symbols-outlined text-sm">schedule</span>
                            Draft / Not Dispatched
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Current Approved Program Parameters (Source of Truth) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 text-xs">
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Course</span>
                <span class="font-extrabold text-slate-800 truncate block mt-0.5" title="<?= htmlspecialchars($oppTitle) ?>"><?= htmlspecialchars($oppTitle) ?></span>
            </div>
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">College</span>
                <span class="font-extrabold text-slate-800 truncate block mt-0.5" title="<?= htmlspecialchars($collegeName) ?>"><?= htmlspecialchars($collegeName) ?></span>
            </div>
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Approved Dates</span>
                <span class="font-extrabold text-blue-700 block mt-0.5"><?= $startDate ?> – <?= $endDate ?></span>
            </div>
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Duration</span>
                <span class="font-extrabold text-slate-800 block mt-0.5"><?= $durationText ?></span>
            </div>
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Honorarium</span>
                <span class="font-extrabold text-emerald-700 block mt-0.5"><?= $rateText ?></span>
            </div>
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] font-bold uppercase text-slate-400 block tracking-wider">Mode & Location</span>
                <span class="font-extrabold text-slate-800 truncate block mt-0.5"><?= $mode ?> (<?= htmlspecialchars($location) ?>)</span>
            </div>
        </div>

        <!-- Wrong Trainer Protection Banner -->
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex items-start gap-3">
            <span class="material-symbols-outlined text-blue-600 text-xl shrink-0 mt-0.5">verified_user</span>
            <div class="text-xs text-blue-900">
                <strong class="font-black">Administrator Review Required:</strong>
                Please verify that the candidate name (<span class="underline font-bold"><?= htmlspecialchars($trainerName) ?></span>), email address, and approved dates match your records before clicking <em>Send Confirmation Email</em>. Work order emails are not sent automatically.
            </div>
        </div>
    </div>

    <!-- Email Composer Section -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-8 shadow-card space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h3 class="text-lg font-black text-slate-900">Work Order Email Composer</h3>
                <p class="text-xs text-slate-500">Every section including terms, dress code, and scope of work is fully editable prior to dispatch.</p>
            </div>
            <?php if (!empty($activeDraft['isRevised'])): ?>
                <span class="bg-purple-100 text-purple-800 font-extrabold text-[10px] px-3 py-1 rounded-full uppercase tracking-wider">Revised Confirmation Mode</span>
            <?php endif; ?>
        </div>

        <form method="POST" id="workOrderForm" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            <input type="hidden" name="sub_action" id="subActionInput" value="save_draft">
            <input type="hidden" name="is_revised" value="<?= !empty($activeDraft['isRevised']) ? '1' : '0' ?>">

            <!-- Recipient Fields -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">To (Trainer Registered Email) *</label>
                    <input type="email" name="to_email" id="toEmailInput" value="<?= htmlspecialchars($trainerEmail) ?>" required
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">CC (Optional Operations)</label>
                    <input type="text" name="cc_email" value="<?= htmlspecialchars($activeDraft['ccEmail'] ?? '') ?>" placeholder="mentry.operations@gmail.com"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">BCC (Optional Internal Audit)</label>
                    <input type="text" name="bcc_email" value="<?= htmlspecialchars($activeDraft['bccEmail'] ?? '') ?>" placeholder="audit@mentry.co"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">
                </div>
            </div>

            <!-- Subject Line -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Subject Line *</label>
                <input type="text" name="subject" id="subjectInput" value="<?= htmlspecialchars($activeDraft['subject'] ?? '') ?>" required
                       class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]">
            </div>

            <!-- Email Body Content -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Official Email HTML Body *</label>
                    <button type="button" onclick="openPreviewModal()" class="text-xs font-bold text-[#FE5E04] hover:underline flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">visibility</span> Live Preview
                    </button>
                </div>
                <textarea name="email_html" id="emailHtmlTextarea" rows="18" required
                          class="w-full font-mono text-xs p-4 bg-slate-950 text-emerald-400 border border-slate-800 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[#FE5E04] leading-relaxed"><?= htmlspecialchars($activeDraft['html'] ?? '') ?></textarea>
                <p class="text-[11px] text-slate-400 mt-1">Rendered using Mentry Solutions email design system with table-based details, scope of work, grooming guidelines, and payment terms.</p>
            </div>

            <!-- Actions Bar -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100">
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button type="button" onclick="submitForm('save_draft')" class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-base">save</span>
                        Save Draft
                    </button>
                    <button type="button" onclick="openPreviewModal()" class="w-full sm:w-auto px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-base text-[#FE5E04]">preview</span>
                        Preview Final Email
                    </button>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <a href="/admin/opportunity-view.php?id=<?= urlencode($oppId) ?>" class="w-full sm:w-auto px-4 py-2.5 text-center text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                        Cancel
                    </a>
                    <button type="button" onclick="confirmAndSend()" class="w-full sm:w-auto px-6 py-2.5 bg-[#FE5E04] hover:bg-[#e05202] text-white rounded-xl text-xs font-black transition shadow-md shadow-[#FE5E04]/20 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-base">send</span>
                        SEND CONFIRMATION EMAIL
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Confirmation & Revision History Audit Trail -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-8 shadow-card space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-slate-400">history</span>
                    Sent Email & Engagement Audit History
                </h3>
                <p class="text-xs text-slate-500">Chronological history of engagement confirmations and revisions dispatched for this assignment.</p>
            </div>

            <?php if (!empty($history)): ?>
                <a href="/admin/trainer-confirmation.php?opp_id=<?= urlencode($oppId) ?>&trainer_id=<?= urlencode($trainerId) ?>&action=new_revised" 
                   class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-purple-50 hover:bg-purple-100 border border-purple-200 text-purple-700 rounded-xl text-xs font-bold transition">
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
                            <th class="py-3 px-3">Type & Version</th>
                            <th class="py-3 px-3">Subject</th>
                            <th class="py-3 px-3">Recipient</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3">Dispatched At</th>
                            <th class="py-3 px-3 text-right">Actions</th>
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
                                <td class="py-3.5 px-3 font-medium text-slate-900 max-w-xs truncate">
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
                                    <button type="button" onclick="viewHistoricalEmail(<?= $idx ?>)" class="text-xs font-bold text-blue-600 hover:text-blue-800 px-2 py-1 rounded hover:bg-blue-50">
                                        View Content
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

<!-- Interactive Email Preview Modal -->
<div id="emailPreviewModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-4 sm:p-5 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-[#FE5E04]">mark_email_read</span>
                <div>
                    <h3 class="font-extrabold text-sm text-white">Work Order Confirmation Preview</h3>
                    <p class="text-[11px] text-slate-400">Exact layout delivered to recipient's inbox</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleViewport('desktop')" id="btnDesktop" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white/20 text-white">Desktop</button>
                <button type="button" onclick="toggleViewport('mobile')" id="btnMobile" class="px-2.5 py-1 rounded-lg text-xs font-bold text-slate-400 hover:text-white">Mobile</button>
                <button type="button" onclick="closePreviewModal()" class="text-slate-400 hover:text-white p-1 rounded-lg ml-2">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
        </div>

        <div class="p-4 bg-slate-100 flex-1 overflow-y-auto flex justify-center">
            <div id="previewContainer" class="w-full bg-white shadow-sm transition-all duration-300 rounded-xl overflow-hidden min-h-[500px]">
                <iframe id="previewIframe" class="w-full h-full min-h-[600px] border-0"></iframe>
            </div>
        </div>

        <div class="p-4 bg-white border-t border-slate-100 flex items-center justify-between">
            <span class="text-xs text-slate-500 font-medium">Recipient: <strong class="text-slate-900" id="modalRecipientDisplay"><?= htmlspecialchars($trainerEmail) ?></strong></span>
            <div class="flex items-center gap-3">
                <button type="button" onclick="closePreviewModal()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200">Close Preview</button>
                <button type="button" onclick="closePreviewModal(); confirmAndSend();" class="px-5 py-2 bg-[#FE5E04] text-white rounded-xl text-xs font-black hover:bg-[#e05202] shadow-md shadow-[#FE5E04]/20">Confirm & Send</button>
            </div>
        </div>
    </div>
</div>

<script>
const historyRecords = <?= json_encode($history, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function submitForm(subAction) {
    document.getElementById('subActionInput').value = subAction;
    document.getElementById('workOrderForm').submit();
}

function confirmAndSend() {
    const toEmail = document.getElementById('toEmailInput').value.trim();
    const trainerName = <?= json_encode($trainerName) ?>;
    const oppTitle = <?= json_encode($oppTitle) ?>;

    if (!toEmail) {
        alert("Please enter a valid recipient trainer email.");
        return;
    }

    const confirmed = confirm(
        "CONFIRM EMAIL DISPATCH:\n\n" +
        "• Trainer: " + trainerName + "\n" +
        "• Recipient: " + toEmail + "\n" +
        "• Course: " + oppTitle + "\n\n" +
        "Are you sure you want to dispatch this official work order confirmation now?"
    );

    if (confirmed) {
        submitForm('send_email');
    }
}

function openPreviewModal() {
    const htmlContent = document.getElementById('emailHtmlTextarea').value;
    const toEmail = document.getElementById('toEmailInput').value;
    document.getElementById('modalRecipientDisplay').textContent = toEmail;

    const iframe = document.getElementById('previewIframe');
    iframe.srcdoc = htmlContent;

    document.getElementById('emailPreviewModal').classList.remove('hidden');
}

function closePreviewModal() {
    document.getElementById('emailPreviewModal').classList.add('hidden');
}

function toggleViewport(mode) {
    const container = document.getElementById('previewContainer');
    const btnD = document.getElementById('btnDesktop');
    const btnM = document.getElementById('btnMobile');

    if (mode === 'mobile') {
        container.style.maxWidth = '380px';
        btnM.classList.add('bg-white/20', 'text-white');
        btnM.classList.remove('text-slate-400');
        btnD.classList.remove('bg-white/20', 'text-white');
        btnD.classList.add('text-slate-400');
    } else {
        container.style.maxWidth = '100%';
        btnD.classList.add('bg-white/20', 'text-white');
        btnD.classList.remove('text-slate-400');
        btnM.classList.remove('bg-white/20', 'text-white');
        btnM.classList.add('text-slate-400');
    }
}

function viewHistoricalEmail(index) {
    const rec = historyRecords[index];
    if (!rec) return;

    const iframe = document.getElementById('previewIframe');
    iframe.srcdoc = rec.html || ('<pre>' + (rec.plainText || 'No content') + '</pre>');
    document.getElementById('modalRecipientDisplay').textContent = rec.trainerEmail || 'Trainer';

    document.getElementById('emailPreviewModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
