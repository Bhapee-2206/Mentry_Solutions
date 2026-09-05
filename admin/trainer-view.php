<?php
// admin/trainer-view.php - Trainer Dossier & Resume Manager
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai_agent.php';

// Strict backend role enforcement
requireAdminOrStaff();

$id = $_GET['id'] ?? '';
$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$skillCol = getCollection("Skill");
$expCol = getCollection("Experience");
$docCol = getCollection("Document");
$asgCol = getCollection("Assignment");
$appCol = getCollection("Application");
$oppCol = getCollection("Opportunity");

$trainer = null;
if (!empty($id)) {
    try {
        if (preg_match('/^[a-f0-9]{24}$/i', $id)) {
            $trainer = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]) : null;
        }
        if (!$trainer && $trainerCol) {
            $trainer = $trainerCol->findOne([
                '$or' => [
                    ['id' => $id],
                    ['trainerCode' => $id],
                    ['mentryId' => $id]
                ]
            ]);
        }
    } catch (\Throwable $e) {}
}

if (!$trainer) {
    $fallbackTrainer = AIAgent::getTrainerProfile($id);
    if ($fallbackTrainer) {
        $trainer = $fallbackTrainer;
    }
}

if (!$trainer) {
    header("Location: /admin/trainers.php");
    exit();
}

$trainerId = (string)($trainer['_id'] ?? ($trainer['id'] ?? $id));

$u = null;
if (!empty($trainer['userId'])) {
    try { 
        $u = $userCol ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainer['userId'])]) : null; 
    } catch (\Throwable $e) {}
}
if (!$u) {
    $u = [
        'name' => $trainer['name'] ?? 'Trainer',
        'email' => $trainer['email'] ?? 'trainer@mentry.test',
        'phone' => $trainer['phone'] ?? '+91 98450 00000',
        'avatar' => $trainer['avatar'] ?? null
    ];
}

$skills = $skillCol ? $skillCol->find(['trainerId' => $trainerId])->toArray() : [];
if (empty($skills) && !empty($trainer['skills'])) {
    foreach ($trainer['skills'] as $sk) {
        $skills[] = ['name' => $sk, 'proficiency' => 'EXPERT', 'experienceYears' => $trainer['totalExperienceYears'] ?? 5];
    }
}
$experiences = $expCol ? $expCol->find(['trainerId' => $trainerId])->toArray() : [];
$documents = $docCol ? $docCol->find(['trainerId' => $trainerId], ['sort' => ['uploadedAt' => -1]])->toArray() : [];
$assignments = $asgCol ? $asgCol->find(['trainerId' => $trainerId], ['sort' => ['createdAt' => -1]])->toArray() : [];
$applications = $appCol ? $appCol->find(['trainerId' => $trainerId], ['sort' => ['appliedAt' => -1]])->toArray() : [];

$pageTitle = ($u['name'] ?? 'Trainer') . " Dossier";
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <a href="/admin/trainers.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-blue-600">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Trainers Directory
        </a>

        <div class="flex flex-wrap items-center gap-2">
            <a href="/actions/download-trainer-profile.php?id=<?= $trainerId ?>" class="bg-[#FE5E04] hover:bg-[#e05202] text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5" title="Download Official Trainer Profile Dossier PDF">
                <span class="material-symbols-outlined text-[16px]">download</span>
                Download Trainer Profile
            </a>
            <button onclick="document.getElementById('editProfileModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">edit</span>
                Edit Trainer Details
            </button>
            <button onclick="document.getElementById('resumeModal').classList.remove('hidden')" class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">description</span>
                View Printable Resume / CV
            </button>
        </div>
    </div>

    <!-- Top Profile Banner -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-8 shadow-card flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="flex items-center gap-5">
            <div class="flex flex-col items-center gap-1.5 shrink-0">
                <img src="<?= htmlspecialchars(getUserAvatar($u, 200)) ?>" class="w-20 h-20 rounded-3xl object-cover border-2 border-slate-200 shadow-sm">
                <div class="flex items-center gap-1">
                    <?php if (!empty($u['avatar'])): ?>
                        <a href="<?= htmlspecialchars($u['avatar']) ?>" download="trainer_<?= $trainerId ?>_photo" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-700 hover:text-[#FE5E04] bg-slate-100 hover:bg-orange-50 px-2 py-0.5 rounded-lg border border-slate-200 transition-colors" title="Download High-Res Profile Photo">
                            <span class="material-symbols-outlined text-[13px]">download</span>
                            Photo
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($u['name'] ?? 'Trainer') ?></h1>
                    <span class="font-mono text-xs font-black text-[#FE5E04] bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-lg shadow-2xs whitespace-nowrap shrink-0 inline-flex items-center">
                        <?= htmlspecialchars(getMentryCode('TRAINER', $trainer)) ?>
                    </span>
                    <?= getStatusBadge($trainer['status'] ?? 'PENDING_APPROVAL') ?>
                    <span class="bg-amber-50 text-amber-700 text-xs font-extrabold px-2 py-0.5 rounded-lg border border-amber-200 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px] fill text-amber-500">star</span>
                        <?= htmlspecialchars($trainer['adminRating'] ?? 4.9) ?> / 5.0 Rating
                    </span>
                </div>
                <p class="text-xs text-blue-600 font-bold"><?= htmlspecialchars($trainer['professionalTitle'] ?? 'Senior Technical Trainer') ?></p>
                <p class="text-xs text-slate-500 font-medium flex flex-wrap items-center gap-3">
                    <span><i class="material-symbols-outlined text-[14px] align-middle text-slate-400">location_on</i> <?= htmlspecialchars($trainer['currentCity'] ?? 'India') ?><?= !empty($trainer['currentState']) ? ', ' . htmlspecialchars($trainer['currentState']) : '' ?></span>
                    <span><i class="material-symbols-outlined text-[14px] align-middle text-slate-400">email</i> <?= htmlspecialchars($u['email'] ?? 'N/A') ?></span>
                    <span><i class="material-symbols-outlined text-[14px] align-middle text-slate-400">phone</i> <?= htmlspecialchars($u['phone'] ?? 'N/A') ?></span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 self-stretch sm:self-auto justify-end">
            <?php if (($trainer['status'] ?? '') === 'APPROVED'): ?>
                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold px-3 py-2 rounded-xl flex items-center gap-1.5 shadow-2xs">
                    <span class="material-symbols-outlined text-[16px] fill text-emerald-600">verified</span>
                    Approved & Active
                </span>
                <form action="/actions/update-trainer.php" method="POST" onsubmit="return confirm('Are you sure you want to suspend this trainer? They will not appear in matching results.');">
                    <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                    <input type="hidden" name="action_type" value="update_status">
                    <input type="hidden" name="status" value="SUSPENDED">
                    <button type="submit" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold text-xs px-3.5 py-2 rounded-xl transition-all flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">block</span>
                        Suspend Trainer
                    </button>
                </form>
            <?php elseif (($trainer['status'] ?? '') === 'SUSPENDED'): ?>
                <span class="bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold px-3 py-2 rounded-xl flex items-center gap-1.5 shadow-2xs">
                    <span class="material-symbols-outlined text-[16px]">do_not_disturb_on</span>
                    Currently Suspended
                </span>
                <form action="/actions/update-trainer.php" method="POST">
                    <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                    <input type="hidden" name="action_type" value="update_status">
                    <input type="hidden" name="status" value="APPROVED">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">check_circle</span>
                        Re-activate Trainer
                    </button>
                </form>
            <?php else: /* PENDING_APPROVAL */ ?>
                <form action="/actions/update-trainer.php" method="POST">
                    <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                    <input type="hidden" name="action_type" value="update_status">
                    <input type="hidden" name="status" value="APPROVED">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">check_circle</span>
                        Approve Trainer
                    </button>
                </form>
                <form action="/actions/update-trainer.php" method="POST" onsubmit="return confirm('Are you sure you want to reject/suspend this application?');">
                    <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                    <input type="hidden" name="action_type" value="update_status">
                    <input type="hidden" name="status" value="SUSPENDED">
                    <button type="submit" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold text-xs px-3.5 py-2 rounded-xl transition-all flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">close</span>
                        Reject / Suspend
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid md:grid-cols-3 gap-6">
        <!-- Left Column: Metrics & Bio & Notes -->
        <div class="space-y-6">
            <!-- Current Schedule & Availability (Real-Time Booking Status) -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h3 class="font-bold text-sm text-slate-900 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-emerald-600 text-lg">event_available</span>
                        Booking & Availability
                    </h3>
                    <span class="text-[10px] text-slate-400">Live Status</span>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500 font-medium">Current Status</span>
                        <?= getAvailabilityBadge($trainer['availabilityStatus'] ?? 'AVAILABLE_NOW', $trainer['availableFromDate'] ?? null) ?>
                    </div>

                    <?php if (!empty($trainer['availableFromDate'])): ?>
                        <div class="flex items-center justify-between text-xs py-1 border-t border-slate-50">
                            <span class="text-slate-500 font-medium">Free After Date</span>
                            <span class="font-bold text-amber-900 bg-amber-50 px-2 py-0.5 rounded border border-amber-200"><?= formatDate($trainer['availableFromDate']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between text-xs py-1 border-t border-slate-50">
                        <span class="text-slate-500 font-medium">Campus Mobility</span>
                        <span class="font-bold text-slate-800"><?= htmlspecialchars(str_replace('_', ' ', $trainer['travelPreference'] ?? 'PAN_INDIA')) ?></span>
                    </div>

                    <?php if (!empty($trainer['availabilityNotes'])): ?>
                        <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-600 border border-slate-100 italic mt-1">
                            "<?= htmlspecialchars($trainer['availabilityNotes']) ?>"
                        </div>
                    <?php endif; ?>

                    <div class="pt-1 text-[10px] text-slate-400 text-right">
                        Updated <?= formatRelativeTime($trainer['availabilityUpdatedAt'] ?? null) ?>
                    </div>
                </div>
            </div>

            <!-- Rate & Preferences -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                <h3 class="font-bold text-sm text-slate-900 border-b border-slate-100 pb-2">Experience & Rate Terms</h3>
                <div class="text-xs space-y-2.5 text-slate-600">
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Primary Domain</span>
                        <span class="font-bold text-slate-900"><?= htmlspecialchars($trainer['primaryDomain'] ?? 'Software & Tech') ?></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Total Experience</span>
                        <span class="font-bold text-slate-900"><?= htmlspecialchars($trainer['totalExperienceYears'] ?? 0) ?> Years</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">College / Campus Exp</span>
                        <span class="font-bold text-slate-900"><?= htmlspecialchars($trainer['collegeExperienceYears'] ?? 0) ?> Years</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Daily Billing Rate</span>
                        <span class="font-black text-blue-700 text-sm"><?= formatINR($trainer['dailyRateINR'] ?? 0) ?>/day</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Travel Availability</span>
                        <span class="font-bold text-slate-900"><?= htmlspecialchars(str_replace('_', ' ', $trainer['travelPreference'] ?? 'PAN_INDIA')) ?></span>
                    </div>
                </div>
            </div>

            <!-- Internal Admin Notes -->
            <div class="bg-amber-50/70 p-6 rounded-3xl border border-amber-200/90 shadow-xs space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-amber-200/60">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px] text-amber-700">admin_panel_settings</span>
                        <h3 class="font-bold text-sm text-amber-950">Admin Internal Notes & Audit</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="bg-amber-200/80 text-amber-900 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">Internal Only</span>
                        <button type="button" onclick="document.getElementById('editAdminNotesModal').classList.remove('hidden')" class="text-xs font-bold text-amber-800 hover:text-amber-950 flex items-center gap-1 bg-amber-100 hover:bg-amber-200/70 px-2.5 py-1 rounded-lg transition-colors cursor-pointer">
                            <span class="material-symbols-outlined text-[14px]">edit</span> Edit Notes
                        </button>
                    </div>
                </div>
                <p class="text-xs text-amber-950/85 leading-relaxed font-medium">
                    <?= nl2br(htmlspecialchars(!empty($trainer['adminNotes']) ? $trainer['adminNotes'] : 'Verified technical credentials. Recommended for senior semester batches and enterprise bootcamps.')) ?>
                </p>
                <div class="text-[11px] text-amber-800/70 pt-1 flex items-center gap-1 border-t border-amber-200/40">
                    <span class="material-symbols-outlined text-[14px]">shield</span>
                    <span>Confidential to Admin & Operations Staff. Hidden from candidate trainers and colleges.</span>
                </div>
            </div>
        </div>

        <!-- Right 2 Columns: Applications, Resumes & Documents, Skills, Past Experiences -->
        <div class="md:col-span-2 space-y-6">
            <!-- Opportunity Applications & Assignment Approvals -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[#FE5E04] text-lg">assignment_turned_in</span>
                            Opportunity Applications & Assignment Approvals (<?= count($applications) ?>)
                        </h3>
                        <p class="text-[11px] text-slate-500">Review, Accept & Assign, Shortlist, or Reject this candidate's applications.</p>
                    </div>
                </div>

                <?php if (empty($applications)): ?>
                    <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 text-center text-xs text-slate-400">
                        No opportunity applications currently on record for this trainer.
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($applications as $app): 
                            $appId = (string)($app['_id'] ?? ($app['id'] ?? ''));
                            $oppId = (string)($app['opportunityId'] ?? '');
                            $opp = null;
                            if (!empty($oppId) && $oppCol) {
                                try {
                                    $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]);
                                } catch (\Throwable $e) {}
                            }
                            $appStatus = strtoupper($app['status'] ?? 'PENDING');
                        ?>
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100/90 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-100/50 transition-colors">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="font-bold text-xs text-slate-900">
                                            <?= htmlspecialchars($opp['title'] ?? 'Academic Training Assignment') ?>
                                        </h4>
                                        <span class="bg-emerald-50 text-emerald-700 font-extrabold text-[10px] px-2 py-0.5 rounded border border-emerald-200">
                                            <?= htmlspecialchars($app['matchScore'] ?? 95) ?>% Match
                                        </span>
                                        <?= getStatusBadge($appStatus) ?>
                                    </div>
                                    <p class="text-[11px] text-slate-500">
                                        <?= htmlspecialchars($opp['city'] ?? 'Location TBA') ?><?= !empty($opp['state']) ? ', ' . htmlspecialchars($opp['state']) : '' ?> • 
                                        Proposed: <strong class="text-blue-700"><?= formatINR($app['proposedDailyRate'] ?? 0) ?>/day</strong> • 
                                        Applied: <?= formatDate($app['appliedAt'] ?? null) ?>
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                                    <?php if ($opp): ?>
                                        <a href="/opportunity-details.php?id=<?= $oppId ?>" target="_blank" class="p-1.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-blue-600 hover:bg-slate-50 transition-colors" title="View Opportunity Details">
                                            <span class="material-symbols-outlined text-[16px]">visibility</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($appStatus !== 'ACCEPTED'): ?>
                                        <form action="/actions/update-application.php" method="POST" class="inline">
                                            <input type="hidden" name="applicationId" value="<?= $appId ?>">
                                            <input type="hidden" name="status" value="ACCEPTED">
                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-1.5 rounded-xl shadow-xs transition-colors flex items-center gap-1" title="Accept and Assign to Training">
                                                <span class="material-symbols-outlined text-[15px]">check_circle</span>
                                                Accept
                                            </button>
                                        </form>

                                        <?php if ($appStatus !== 'SHORTLISTED'): ?>
                                            <form action="/actions/update-application.php" method="POST" class="inline">
                                                <input type="hidden" name="applicationId" value="<?= $appId ?>">
                                                <input type="hidden" name="status" value="SHORTLISTED">
                                                <button type="submit" class="bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 font-bold text-xs px-2.5 py-1.5 rounded-xl transition-colors" title="Shortlist Candidate">
                                                    Shortlist
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($appStatus !== 'REJECTED'): ?>
                                            <form action="/actions/update-application.php" method="POST" class="inline">
                                                <input type="hidden" name="applicationId" value="<?= $appId ?>">
                                                <input type="hidden" name="status" value="REJECTED">
                                                <button type="submit" class="bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 font-bold text-xs px-2.5 py-1.5 rounded-xl transition-colors" title="Reject Application">
                                                    Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-200 flex items-center gap-1 shadow-2xs">
                                            <span class="material-symbols-outlined text-[15px]">done_all</span>
                                            Accepted & Assigned
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Uploaded Resumes and Documents -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Resumes, CVs & Certifications (<?= count($documents) ?>)</h3>
                        <p class="text-[11px] text-slate-500">Download, preview, and upload official credentials.</p>
                    </div>
                    <button onclick="document.getElementById('uploadDocModal').classList.remove('hidden')" class="bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 text-xs font-bold px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">upload_file</span>
                        Upload File
                    </button>
                </div>

                <?php if (empty($documents) && empty($trainer['resumeUrl'])): ?>
                    <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100 text-center space-y-3">
                        <p class="text-xs text-slate-400">No physical resume file uploaded yet.</p>
                        <button onclick="document.getElementById('resumeModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all inline-flex items-center gap-1.5 shadow-xs">
                            <span class="material-symbols-outlined text-[16px]">visibility</span>
                            Generate & View Formatted Trainer CV
                        </button>
                    </div>
                <?php else: ?>
                    <div class="space-y-2.5">
                        <?php 
                        $cleanTrainerName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($u['name'] ?? 'Trainer'));
                        $cleanTrainerCode = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim(getMentryCode('TRAINER', $trainer)));
                        $trainerDocPrefix = $cleanTrainerName . '_' . $cleanTrainerCode;

                        if (!empty($trainer['resumeUrl'])): 
                            $primaryResumeExt = strtolower(pathinfo($trainer['resumeUrl'] ?? '', PATHINFO_EXTENSION)) ?: 'pdf';
                            $primaryResumeDownloadName = $cleanTrainerName . '_Resume_' . $cleanTrainerCode . '.' . $primaryResumeExt;
                            $primaryResumeDownloadUrl = '/actions/download-document.php?url=' . urlencode($trainer['resumeUrl'] ?? '') . '&filename=' . urlencode($primaryResumeDownloadName);
                        ?>
                            <div class="p-3.5 rounded-2xl bg-blue-50/50 border border-blue-200/70 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-blue-600 text-2xl">description</span>
                                    <div>
                                        <h4 class="font-bold text-xs text-slate-900">Primary Candidate Resume</h4>
                                        <p class="text-[10px] text-blue-600 font-semibold">Active Verified CV</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="openAdminDocViewer('<?= htmlspecialchars($trainer['resumeUrl']) ?>', 'Primary Candidate Resume - <?= htmlspecialchars(addslashes($u['name'] ?? 'Trainer')) ?>', '<?= htmlspecialchars(addslashes($primaryResumeDownloadName)) ?>')" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-3.5 py-1.5 rounded-xl transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-outlined text-[15px]">visibility</span>
                                        Preview Document
                                    </button>
                                    <a href="<?= htmlspecialchars($primaryResumeDownloadUrl) ?>" download="<?= htmlspecialchars($primaryResumeDownloadName) ?>" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-bold p-1.5 rounded-xl transition-all" title="Download <?= htmlspecialchars($primaryResumeDownloadName) ?>">
                                        <span class="material-symbols-outlined text-[15px]">download</span>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($documents as $d): 
                            $docId = (string)($d['_id'] ?? ($d['id'] ?? ''));
                            $docExt = strtolower(pathinfo($d['fileUrl'] ?? '', PATHINFO_EXTENSION)) ?: 'pdf';
                            $cleanDocTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($d['title'] ?? ($d['type'] ?? 'Document')));
                            $docDownloadName = $cleanTrainerName . '_' . $cleanDocTitle . '_' . $cleanTrainerCode . '.' . $docExt;
                            $docDownloadUrl = '/actions/download-document.php?url=' . urlencode($d['fileUrl'] ?? '') . '&filename=' . urlencode($docDownloadName);
                        ?>
                            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between hover:bg-slate-100/50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-slate-600 text-2xl">picture_as_pdf</span>
                                    <div>
                                        <h4 class="font-bold text-xs text-slate-900"><?= htmlspecialchars($d['title'] ?? 'Document') ?></h4>
                                        <p class="text-[10px] text-slate-400"><?= htmlspecialchars($d['type'] ?? 'RESUME') ?> • <?= formatDate($d['uploadedAt'] ?? null) ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="openAdminDocViewer('<?= htmlspecialchars($d['fileUrl'] ?? '#') ?>', '<?= htmlspecialchars(addslashes($d['title'] ?? 'Trainer Document')) ?>', '<?= htmlspecialchars(addslashes($docDownloadName)) ?>')" class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-3.5 py-1.5 rounded-xl transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-outlined text-[15px]">visibility</span>
                                        Preview Document
                                    </button>
                                    <a href="<?= htmlspecialchars($docDownloadUrl) ?>" download="<?= htmlspecialchars($docDownloadName) ?>" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold p-1.5 rounded-xl transition-all shadow-xs flex items-center gap-1" title="Download <?= htmlspecialchars($docDownloadName) ?>">
                                        <span class="material-symbols-outlined text-[15px]">download</span>
                                    </a>
                                    <?php if (!empty($docId)): ?>
                                    <form action="/actions/update-trainer.php" method="POST" class="inline" onsubmit="return confirm('Delete this document?');">
                                        <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                                        <input type="hidden" name="action_type" value="delete_document">
                                        <input type="hidden" name="docId" value="<?= $docId ?>">
                                        <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer" title="Delete record">
                                            <span class="material-symbols-outlined text-[16px]">delete</span>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Skills Stack (Maintained by Trainer) -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-sm text-slate-900">Verified Technical Skills Stack (<?= count($skills) ?>)</h3>
                        <span class="bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-0.5 rounded-md border border-slate-200">
                            Maintained by Trainer
                        </span>
                    </div>
                </div>

                <?php if (empty($skills)): ?>
                    <p class="text-xs text-slate-400">No skills listed yet.</p>
                <?php else: ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($skills as $sk): ?>
                            <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-800 text-xs font-bold px-3 py-1.5 rounded-xl border border-blue-200">
                                <?= htmlspecialchars($sk['name']) ?> (<?= htmlspecialchars($sk['yearsOfExperience'] ?? 3) ?>y)
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Training History & College Engagements (Maintained by Trainer) -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-sm text-slate-900">Campus Training Engagements (<?= count($experiences) ?>)</h3>
                        <span class="bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-0.5 rounded-md border border-slate-200">
                            Maintained by Trainer
                        </span>
                    </div>
                </div>

                <?php if (empty($experiences)): ?>
                    <p class="text-xs text-slate-400">No prior college engagements listed.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($experiences as $ex): 
                            $orgName = $ex['organization'] ?? ($ex['company'] ?? ($ex['institution'] ?? ($ex['college'] ?? '')));
                        ?>
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <h4 class="font-bold text-xs text-slate-900"><?= htmlspecialchars(!empty($orgName) ? $orgName : 'Corporate / Campus Engagement') ?></h4>
                                    <p class="text-xs text-slate-600 font-medium"><?= htmlspecialchars($ex['role'] ?? 'Technical Trainer') ?> • <strong class="text-slate-800"><?= htmlspecialchars($ex['studentsTrained'] ?? 100) ?> Students Trained</strong></p>
                                    <?php if (!empty($ex['description'])): ?>
                                        <p class="text-[11px] text-slate-500 italic mt-1"><?= nl2br(htmlspecialchars($ex['description'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL: EDIT TRAINER PROFILE ================= -->
<div id="editProfileModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 md:p-8 space-y-6 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <h3 class="text-lg font-black text-slate-900">Edit Trainer Dossier & Profile</h3>
            <button onclick="document.getElementById('editProfileModal').classList.add('hidden')" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="/actions/update-trainer.php" method="POST" class="space-y-4">
            <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
            <input type="hidden" name="action_type" value="edit_profile">

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name *</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($u['name'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-slate-900 outline-none focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Professional Title *</label>
                    <input type="text" name="professionalTitle" required value="<?= htmlspecialchars($trainer['professionalTitle'] ?? 'Corporate & Campus Trainer') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($u['email'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Phone Number</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($u['phone'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Primary Domain</label>
                    <select name="primaryDomain" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
                        <option value="Programming & Software" <?= ($trainer['primaryDomain'] ?? '') === 'Programming & Software' ? 'selected' : '' ?>>Programming & Software</option>
                        <option value="Data Science & AI/ML" <?= ($trainer['primaryDomain'] ?? '') === 'Data Science & AI/ML' ? 'selected' : '' ?>>Data Science & AI/ML</option>
                        <option value="Cloud & DevOps" <?= ($trainer['primaryDomain'] ?? '') === 'Cloud & DevOps' ? 'selected' : '' ?>>Cloud & DevOps</option>
                        <option value="VLSI & Embedded" <?= ($trainer['primaryDomain'] ?? '') === 'VLSI & Embedded' ? 'selected' : '' ?>>VLSI & Embedded</option>
                        <option value="Cybersecurity" <?= ($trainer['primaryDomain'] ?? '') === 'Cybersecurity' ? 'selected' : '' ?>>Cybersecurity</option>
                        <option value="Aptitude & Soft Skills" <?= ($trainer['primaryDomain'] ?? '') === 'Aptitude & Soft Skills' ? 'selected' : '' ?>>Aptitude & Soft Skills</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Daily Billing Rate (₹) *</label>
                    <input type="number" name="dailyRateINR" required value="<?= htmlspecialchars($trainer['dailyRateINR'] ?? 6000) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-blue-700 outline-none focus:bg-white">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Base Location (City, State) *</label>
                    <?php
                    $baseLocAdmin = trim(($trainer['currentCity'] ?? '') . (!empty($trainer['currentState']) ? ', ' . $trainer['currentState'] : ''));
                    if (empty($baseLocAdmin)) $baseLocAdmin = 'Chennai, Tamil Nadu';
                    ?>
                    <input type="text" name="baseLocation" value="<?= htmlspecialchars($baseLocAdmin) ?>" placeholder="e.g. Chennai, Tamil Nadu" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Total Experience (Years)</label>
                    <input type="number" name="totalExperienceYears" value="<?= htmlspecialchars($trainer['totalExperienceYears'] ?? 5) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">College Experience (Years)</label>
                    <input type="number" name="collegeExperienceYears" value="<?= htmlspecialchars($trainer['collegeExperienceYears'] ?? 3) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Travel Preference</label>
                    <select name="travelPreference" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
                        <option value="PAN_INDIA" <?= ($trainer['travelPreference'] ?? '') === 'PAN_INDIA' ? 'selected' : '' ?>>PAN India (Ready to Travel)</option>
                        <option value="STATE_ONLY" <?= ($trainer['travelPreference'] ?? '') === 'STATE_ONLY' ? 'selected' : '' ?>>Home State Only</option>
                        <option value="CITY_ONLY" <?= ($trainer['travelPreference'] ?? '') === 'CITY_ONLY' ? 'selected' : '' ?>>Local City Only</option>
                        <option value="REMOTE_ONLY" <?= ($trainer['travelPreference'] ?? '') === 'REMOTE_ONLY' ? 'selected' : '' ?>>Remote / Virtual Only</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Verification Status</label>
                    <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-slate-900 outline-none focus:bg-white">
                        <option value="APPROVED" <?= ($trainer['status'] ?? '') === 'APPROVED' ? 'selected' : '' ?>>APPROVED (Active in Network)</option>
                        <option value="PENDING_APPROVAL" <?= ($trainer['status'] ?? '') === 'PENDING_APPROVAL' ? 'selected' : '' ?>>PENDING APPROVAL</option>
                        <option value="SUSPENDED" <?= ($trainer['status'] ?? '') === 'SUSPENDED' ? 'selected' : '' ?>>SUSPENDED</option>
                        <option value="REJECTED" <?= ($trainer['status'] ?? '') === 'REJECTED' ? 'selected' : '' ?>>REJECTED</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Internal Admin Rating (1 - 5)</label>
                    <input type="number" step="0.1" min="1" max="5" name="adminRating" value="<?= htmlspecialchars($trainer['adminRating'] ?? 4.9) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Admin Internal Audit Notes</label>
                    <textarea name="adminNotes" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white"><?= htmlspecialchars($trainer['adminNotes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('editProfileModal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-6 py-2.5 rounded-xl shadow-xs transition-colors">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: UPLOAD DOCUMENT ================= -->
<div id="uploadDocModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-black text-slate-900">Upload Trainer Document / CV</h3>
            <button onclick="document.getElementById('uploadDocModal').classList.add('hidden')" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="/actions/upload-document.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="trainerId" value="<?= $trainerId ?>">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Document Title *</label>
                <input type="text" name="title" required value="Updated Professional Resume" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Document Type</label>
                <select name="type" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
                    <option value="RESUME">Resume / Comprehensive CV</option>
                    <option value="CERTIFICATE">Industry Certification</option>
                    <option value="GOVT_ID">Government ID Proof</option>
                    <option value="COLLEGE_FEEDBACK">College Recommendation Letter</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select File (PDF, DOCX, PNG) *</label>
                <input type="file" name="document" required class="w-full text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded-xl p-2">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('uploadDocModal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-5 py-2 rounded-xl shadow-xs">
                    Upload & Attach
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: PRINTABLE ATS RESUME / CV VIEW ================= -->
<div id="resumeModal" class="hidden fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[95vh] overflow-y-auto shadow-2xl">
        <!-- Modal Top Bar -->
        <div class="sticky top-0 bg-white/95 backdrop-blur-sm px-6 py-4 border-b border-slate-200 flex items-center justify-between z-10">
            <span class="font-black text-sm text-slate-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600">badge</span>
                Mentry Verified Trainer Dossier & Resume
            </span>
            <div class="flex items-center gap-2">
                <a href="/actions/download-trainer-profile.php?id=<?= $trainerId ?>" class="bg-[#FE5E04] hover:bg-[#e05202] text-white text-xs font-bold px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1 shadow-xs" title="Download Official Trainer Profile Dossier PDF">
                    <span class="material-symbols-outlined text-[15px]">download</span>
                    Download Profile PDF
                </a>
                <button onclick="window.print()" class="bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px]">print</span>
                    Print CV
                </button>
                <button onclick="document.getElementById('resumeModal').classList.add('hidden')" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>

        <!-- Resume Body -->
        <div class="p-8 md:p-12 space-y-8 text-slate-900">
            <!-- Header -->
            <div class="border-b-2 border-slate-900 pb-6 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                <div>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900"><?= htmlspecialchars($u['name'] ?? 'Trainer') ?></h1>
                    <p class="text-base font-bold text-blue-700 mt-0.5"><?= htmlspecialchars($trainer['professionalTitle'] ?? 'Corporate & College Technical Trainer') ?></p>
                    <p class="text-xs text-slate-500 mt-2">
                        <?= htmlspecialchars($trainer['currentCity'] ?? 'Bangalore') ?>, <?= htmlspecialchars($trainer['currentState'] ?? 'India') ?> • 
                        <?= htmlspecialchars($u['email'] ?? '') ?> • 
                        <?= htmlspecialchars($u['phone'] ?? '') ?>
                    </p>
                </div>
                <div class="text-right">
                    <span class="bg-emerald-50 text-emerald-800 border border-emerald-300 text-[11px] font-extrabold px-3 py-1 rounded-md uppercase tracking-wider block">
                        Mentry Verified Trainer
                    </span>
                    <p class="text-xs text-slate-500 font-bold mt-1.5"><?= htmlspecialchars($trainer['totalExperienceYears'] ?? 0) ?>+ Years Industry & Campus Experience</p>
                </div>
            </div>

            <!-- Core Skills Matrix -->
            <div class="space-y-3">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 border-b border-slate-200 pb-1">Core Technical Competencies</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($skills as $sk): ?>
                        <span class="bg-slate-100 border border-slate-200 text-slate-800 text-xs font-bold px-3 py-1 rounded-md">
                            <?= htmlspecialchars($sk['name']) ?> <span class="text-slate-400 font-normal">(<?= htmlspecialchars($sk['yearsOfExperience'] ?? 3) ?>y)</span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Campus Engagements History -->
            <div class="space-y-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 border-b border-slate-200 pb-1">Institutional Training History</h3>
                <?php if (empty($experiences)): ?>
                    <p class="text-xs text-slate-400">No institutional history records logged.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($experiences as $ex): 
                            $orgName = $ex['organization'] ?? ($ex['company'] ?? ($ex['institution'] ?? ($ex['college'] ?? '')));
                        ?>
                            <div class="space-y-1">
                                <div class="flex justify-between items-baseline">
                                    <h4 class="font-bold text-xs text-slate-900"><?= htmlspecialchars(!empty($orgName) ? $orgName : 'Corporate / Campus Engagement') ?></h4>
                                    <span class="text-[11px] font-bold text-blue-700"><?= htmlspecialchars($ex['studentsTrained'] ?? 100) ?> Students Trained</span>
                                </div>
                                <p class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($ex['role'] ?? 'Technical Trainer') ?></p>
                                <?php if (!empty($ex['description'])): ?>
                                    <p class="text-xs text-slate-600"><?= htmlspecialchars($ex['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Terms & Logistics -->
            <div class="border-t border-slate-200 pt-4 flex justify-between text-xs text-slate-500 font-medium">
                <span>Standard Daily Honorarium: <strong class="text-slate-900"><?= formatINR($trainer['dailyRateINR'] ?? 0) ?>/day</strong></span>
                <span>Mobility: <strong class="text-slate-900"><?= htmlspecialchars(str_replace('_', ' ', $trainer['travelPreference'] ?? 'PAN_INDIA')) ?></strong></span>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL: EDIT ADMIN NOTES ================= -->
<div id="editAdminNotesModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 md:p-8 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-600">admin_panel_settings</span>
                <h3 class="text-base font-black text-slate-900">Admin Internal Notes & Rating</h3>
            </div>
            <button onclick="document.getElementById('editAdminNotesModal').classList.add('hidden')" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="/actions/update-trainer.php" method="POST" class="space-y-4">
            <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
            <input type="hidden" name="action_type" value="update_admin_notes">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Internal Admin Rating (1.0 - 5.0)</label>
                <input type="number" step="0.1" min="1" max="5" name="adminRating" value="<?= htmlspecialchars($trainer['adminRating'] ?? 4.9) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white font-bold text-amber-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Confidential Internal Notes</label>
                <textarea name="adminNotes" rows="4" placeholder="Add vetting comments, strengths, feedback from colleges..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white leading-relaxed"><?= htmlspecialchars($trainer['adminNotes'] ?? '') ?></textarea>
                <p class="text-[11px] text-slate-400 mt-1">This audit note is strictly private to operations staff and will never be shared with candidates or colleges.</p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('editAdminNotesModal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                    Cancel
                </button>
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-xs">
                    Save Internal Notes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: IN-BROWSER DOCUMENT / RESUME VIEWER (NO DOWNLOAD) ================= -->
<div id="documentViewerModal" class="hidden fixed inset-0 z-50 bg-slate-900/75 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-5xl w-full h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-slate-200">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-white shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">visibility</span>
                </div>
                <div>
                    <h3 id="adminDocViewerTitle" class="font-bold text-sm text-slate-900">Document Preview</h3>
                    <p class="text-[11px] text-slate-500">Live In-Browser Document Viewer &bull; No download required</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a id="adminDocViewerDownload" href="#" target="_blank" download class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px]">download</span>
                    Download
                </a>
                <button type="button" onclick="closeAdminDocViewer()" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        <div class="flex-1 bg-slate-100 p-2 overflow-hidden relative">
            <iframe id="adminDocViewerIframe" src="" class="w-full h-full rounded-2xl border-0 bg-white"></iframe>
        </div>
    </div>
</div>

<script>
function openAdminDocViewer(url, title, downloadFilename) {
    const modal = document.getElementById('documentViewerModal');
    const iframe = document.getElementById('adminDocViewerIframe');
    const titleEl = document.getElementById('adminDocViewerTitle');
    const downloadEl = document.getElementById('adminDocViewerDownload');
    
    if (titleEl) titleEl.textContent = title || 'Document Preview';
    
    if (downloadEl) {
        const dlName = downloadFilename || 'document';
        downloadEl.href = '/actions/download-document.php?url=' + encodeURIComponent(url) + '&filename=' + encodeURIComponent(dlName);
        downloadEl.setAttribute('download', dlName);
        downloadEl.setAttribute('title', 'Download ' + dlName);
    }

    // Load through /actions/preview-doc.php so DOCX files render as formatted HTML and PDFs render inline without auto-download!
    const previewUrl = '/actions/preview-doc.php?url=' + encodeURIComponent(url) + '&title=' + encodeURIComponent(title || 'Document');
    if (iframe) iframe.src = previewUrl;
    if (modal) modal.classList.remove('hidden');
}

function closeAdminDocViewer() {
    const modal = document.getElementById('documentViewerModal');
    const iframe = document.getElementById('adminDocViewerIframe');
    if (iframe) iframe.src = 'about:blank';
    if (modal) modal.classList.add('hidden');
}
</script>

</main>
</div>
</body>
</html>
