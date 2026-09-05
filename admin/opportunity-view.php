<?php
// admin/opportunity-view.php - Opportunity View & Match Engine
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();

$id = $_GET['id'] ?? '';
$oppCol = getCollection("Opportunity");
$appCol = getCollection("Application");
$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$asgCol = getCollection("Assignment");
$docCol = getCollection("Document");

$opp = null;
if (!empty($id)) {
    try {
        $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    } catch (Exception $e) {}
}

if (!$opp) {
    header("Location: /admin/opportunities.php");
    exit();
}

$pageTitle = $opp['title'] ?? 'Opportunity View';
$oppId = (string)$opp['_id'];
require_once __DIR__ . '/includes/sidebar.php';

// Get applicants
$applications = $appCol ? $appCol->find(['opportunityId' => $oppId], ['sort' => ['appliedAt' => -1]])->toArray() : [];

// Get all approved trainers for direct assignment
$allApprovedTrainers = $trainerCol ? $trainerCol->find(['status' => 'APPROVED'])->toArray() : [];

// Get existing assignment if any
$assignment = $asgCol ? $asgCol->findOne(['opportunityId' => $oppId]) : null;
$assignedTrainer = null;
$assignedUser = null;
if ($assignment && !empty($assignment['trainerId'])) {
    try {
        $assignedTrainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$assignment['trainerId'])]);
        if ($assignedTrainer && !empty($assignedTrainer['userId'])) {
            $assignedUser = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$assignedTrainer['userId'])]);
        }
    } catch (Exception $e) {}
}

// Extract skills
$skills = [];
if (!empty($opp['skillsRequired'])) {
    $skills = is_array($opp['skillsRequired']) ? $opp['skillsRequired'] : json_decode($opp['skillsRequired'], true);
    if (!is_array($skills)) {
        $skills = explode(',', $opp['skillsRequired']);
    }
}

// Compute intelligent ranked matching candidates
require_once __DIR__ . '/../includes/matching_engine.php';
$matchedCandidates = MatchingEngine::getRankedCandidatesForOpportunity($opp, 12);
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <a href="/admin/opportunities.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-blue-600">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Opportunities
        </a>

        <div class="flex items-center gap-2">
            <?php 
            $currStatus = strtoupper($opp['status'] ?? 'PUBLISHED');
            $isClosedOrMatched = ($currStatus === 'CLOSED' || $currStatus === 'MATCHED' || !empty($opp['assignedTrainerId']));
            ?>
            <form action="/actions/toggle-opportunity-status.php" method="POST" class="inline">
                <input type="hidden" name="opportunityId" value="<?= $oppId ?>">
                <?php if ($isClosedOrMatched): ?>
                    <input type="hidden" name="action" value="reopen">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-3.5 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5" title="Reopen opportunity for trainer applications">
                        <span class="material-symbols-outlined text-[16px]">lock_open</span>
                        Reopen Opportunity
                    </button>
                <?php else: ?>
                    <input type="hidden" name="action" value="close">
                    <button type="submit" onclick="return confirm('Close this opportunity? It will be hidden from trainer feeds and no new applications will be accepted.');" class="bg-slate-800 hover:bg-rose-700 text-white text-xs font-bold px-3.5 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5" title="Close opportunity and stop accepting applications">
                        <span class="material-symbols-outlined text-[16px]">lock</span>
                        Close Opportunity
                    </button>
                <?php endif; ?>
            </form>

            <a href="/admin/opportunity-edit.php?id=<?= $oppId ?>" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">edit</span>
                Edit Opportunity
            </a>
            <form action="/actions/delete-opportunity.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this opportunity?');">
                <input type="hidden" name="id" value="<?= $oppId ?>">
                <button type="submit" class="bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 text-xs font-bold px-3.5 py-2 rounded-xl transition-all flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                    Delete
                </button>
            </form>
        </div>
    </div>

    <?php if ($isClosedOrMatched): ?>
        <!-- Prominent Closed Opportunity Notice -->
        <div class="bg-slate-900 border border-slate-800 text-white rounded-3xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-lg">
            <div class="flex items-center gap-3.5">
                <span class="w-10 h-10 rounded-2xl bg-amber-400/20 border border-amber-400/30 flex items-center justify-center text-amber-400 shrink-0">
                    <span class="material-symbols-outlined text-2xl">lock</span>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-extrabold text-sm text-white">This Training Opportunity is Closed</h3>
                        <span class="bg-amber-400 text-slate-950 font-black text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider">Hidden from Feeds</span>
                    </div>
                    <p class="text-xs text-slate-300 mt-0.5">
                        <?= !empty($assignedTrainer) ? 'Assigned to verified faculty <strong>' . htmlspecialchars($assignedUser['name'] ?? 'Trainer') . '</strong>. It is no longer accepting applications or visible on public feeds.' : 'This requirement is closed and no longer accepting trainer applications.' ?>
                    </p>
                </div>
            </div>
            <form action="/actions/toggle-opportunity-status.php" method="POST" class="shrink-0 w-full sm:w-auto">
                <input type="hidden" name="opportunityId" value="<?= $oppId ?>">
                <input type="hidden" name="action" value="reopen">
                <button type="submit" class="w-full sm:w-auto bg-white hover:bg-slate-100 text-slate-950 text-xs font-bold px-4 py-2 rounded-xl transition-colors shadow-xs flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-emerald-600">lock_open</span>
                    Reopen Opportunity
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Main Opportunity Overview Banner -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-8 shadow-card space-y-6">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-6 border-b border-slate-100">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="bg-blue-50 text-blue-700 font-bold text-[11px] px-2.5 py-0.5 rounded-full uppercase tracking-wider"><?= htmlspecialchars($opp['mode'] ?? 'OFFLINE') ?></span>
                    <span class="bg-slate-100 text-slate-700 font-semibold text-[11px] px-2.5 py-0.5 rounded-full"><?= htmlspecialchars($opp['domain'] ?? 'Software') ?></span>
                    <span class="font-mono text-xs text-slate-400">ID: <?= htmlspecialchars($opp['jobId'] ?? $oppId) ?></span>
                    <?= getStatusBadge($opp['status'] ?? 'PUBLISHED') ?>
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 leading-tight"><?= htmlspecialchars($opp['title']) ?></h1>
                <p class="text-xs text-slate-500 font-medium">
                    <?= !empty($opp['collegeName']) ? htmlspecialchars($opp['collegeName']) . ' • ' : '' ?>
                    <?= htmlspecialchars($opp['city']) ?>, <?= htmlspecialchars($opp['state']) ?> • 
                    <?= htmlspecialchars($opp['durationDays'] ?? 5) ?> Days • 
                    Starts <strong><?= formatDate($opp['startDate'] ?? null) ?></strong> • 
                    Student Batch Size: <strong><?= htmlspecialchars($opp['studentCount'] ?? 100) ?></strong>
                </p>
            </div>

            <div class="text-left md:text-right shrink-0 bg-slate-50 border border-slate-100 p-4 rounded-2xl min-w-[200px]">
                <span class="text-[10px] text-slate-400 font-bold uppercase block tracking-wider">Offered Remuneration</span>
                <p class="text-xl font-black text-blue-700"><?= formatINR($opp['dailyRateMin'] ?? 0) ?> – <?= formatINR($opp['dailyRateMax'] ?? 0) ?></p>
                <span class="text-[10px] text-slate-500">per day (<?= htmlspecialchars($opp['durationDays'] ?? 5) ?> days total)</span>
            </div>
        </div>

        <!-- Required Skills & Description -->
        <div class="space-y-3">
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Required Skills & Technologies</h4>
            <div class="flex flex-wrap gap-1.5">
                <?php if (empty($skills)): ?>
                    <span class="text-xs text-slate-400">No specific skills listed.</span>
                <?php else: ?>
                    <?php foreach ($skills as $s): ?>
                        <span class="bg-blue-50 text-blue-700 border border-blue-100 text-xs font-bold px-3 py-1 rounded-xl">
                            <?= htmlspecialchars(trim($s)) ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-1 pt-2">
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Syllabus & Assignment Description</h4>
            <p class="text-xs text-slate-600 leading-relaxed bg-slate-50/50 p-4 rounded-2xl border border-slate-100"><?= nl2br(htmlspecialchars($opp['description'] ?? '')) ?></p>
        </div>
    </div>

    <!-- Active Confirmed Trainer Assignment (if any) -->
    <?php if ($assignment && $assignedTrainer): ?>
        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-3xl p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <img src="<?= htmlspecialchars($assignedUser['avatar'] ?? "https://avatar.vercel.sh/" . urlencode($assignedUser['name'] ?? 'T') . ".png") ?>" class="w-14 h-14 rounded-2xl object-cover border-2 border-emerald-300 shadow-sm">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="bg-emerald-600 text-white text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md">Assigned Trainer</span>
                            <h3 class="font-black text-base text-slate-900"><?= htmlspecialchars($assignedUser['name'] ?? 'Trainer') ?></h3>
                        </div>
                        <p class="text-xs text-slate-600 font-medium mt-0.5"><?= htmlspecialchars($assignedTrainer['professionalTitle'] ?? 'Lead Faculty') ?> • <?= htmlspecialchars($assignedTrainer['currentCity'] ?? '') ?></p>
                        <p class="text-xs text-emerald-800 font-bold mt-1">Confirmed Rate: <?= formatINR($assignment['agreedDailyRate'] ?? 0) ?>/day (Total: <?= formatINR($assignment['agreedTotalFee'] ?? 0) ?>)</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="/admin/trainer-view.php?id=<?= (string)$assignedTrainer['_id'] ?>" class="bg-white text-emerald-700 border border-emerald-200 text-xs font-bold px-4 py-2 rounded-xl hover:bg-emerald-50 transition-colors shadow-xs">
                        View Trainer Dossier
                    </a>
                    <a href="/admin/assignments.php" class="bg-emerald-600 text-white text-xs font-bold px-4 py-2 rounded-xl hover:bg-emerald-700 transition-colors shadow-xs">
                        Manage Assignment Logistics
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Section: Direct Trainer Assignment Tool -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-base text-slate-900">Direct Trainer Assignment</h3>
                <p class="text-xs text-slate-500">Manually assign any verified trainer from the network without waiting for applications.</p>
            </div>
            <span class="material-symbols-outlined text-blue-600 text-2xl">how_to_reg</span>
        </div>

        <form action="/actions/assign-trainer.php" method="POST" class="grid sm:grid-cols-3 gap-4 pt-2">
            <input type="hidden" name="opportunityId" value="<?= $oppId ?>">

            <div class="sm:col-span-1">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Verified Trainer *</label>
                <select name="trainerId" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs font-medium outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    <option value="">-- Choose Trainer --</option>
                    <?php foreach ($allApprovedTrainers as $at): 
                        $atu = null;
                        if (!empty($at['userId'])) {
                            try { $atu = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$at['userId'])]); } catch (Exception $e) {}
                        }
                        $atAvail = '🟢 Available Now';
                        if (($at['availabilityStatus'] ?? '') === 'FREE_FROM_DATE' && !empty($at['availableFromDate'])) {
                            $atAvail = '🟡 Free from ' . formatDate($at['availableFromDate']);
                        } elseif (($at['availabilityStatus'] ?? '') === 'BUSY_ON_ASSIGNMENT') {
                            $atAvail = '🔵 Delivering' . (!empty($at['availableFromDate']) ? ' (until ' . formatDate($at['availableFromDate']) . ')' : '');
                        } elseif (($at['availabilityStatus'] ?? '') === 'UNAVAILABLE') {
                            $atAvail = '⚪ Unavailable';
                        }
                    ?>
                        <option value="<?= (string)$at['_id'] ?>">
                            <?= htmlspecialchars($atu['name'] ?? 'Trainer') ?> [<?= $atAvail ?>] — <?= htmlspecialchars($at['primaryDomain'] ?? 'Tech') ?> (<?= htmlspecialchars($at['currentCity'] ?? '') ?>, <?= formatINR($at['dailyRateINR'] ?? 0) ?>/day)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Agreed Daily Rate (₹) *</label>
                <input type="number" name="agreedDailyRate" required value="<?= htmlspecialchars($opp['dailyRateMin'] ?? 6000) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-blue-700 outline-none focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Accommodation / Travel Notes</label>
                <input type="text" name="logisticsNotes" value="Campus Executive Guest House + Travel Arranged" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white">
            </div>

            <div class="sm:col-span-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pt-2">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer select-none bg-emerald-50/70 border border-emerald-200 px-3.5 py-2 rounded-xl">
                    <input type="checkbox" name="closeOpportunity" value="1" checked class="w-4 h-4 text-emerald-600 rounded">
                    <span>Close opportunity to further applications upon assignment (hides from public & trainer feeds)</span>
                </label>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow-xs transition-colors flex items-center gap-1.5 shrink-0">
                    <span class="material-symbols-outlined text-[16px]">check_circle</span>
                    Confirm Direct Assignment & Generate Logistics
                </button>
            </div>
        </form>
    </div>

    <!-- Section: Candidate Applications Table -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-base text-slate-900">Applicant Faculty Pipeline</h3>
                <p class="text-xs text-slate-500">Trainers who have reviewed the curriculum scope and applied with custom honorarium proposals.</p>
            </div>
            <span class="bg-blue-50 text-blue-700 text-xs font-bold px-3 py-1 rounded-full"><?= count($applications) ?> Candidates</span>
        </div>

        <?php if (empty($applications)): ?>
            <div class="p-8 text-center text-xs text-slate-400">
                No trainers have applied to this opening yet. You can invite matched trainers or assign directly above.
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($applications as $ap): 
                    $t = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$ap['trainerId'])]) : null;
                    $u = ($t && $userCol && !empty($t['userId'])) ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$t['userId'])]) : null;
                    $trainerDoc = $docCol ? $docCol->findOne(['trainerId' => (string)$ap['trainerId'], 'type' => 'RESUME']) : null;
                    $appId = (string)$ap['_id'];
                ?>
                    <div class="py-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <img src="<?= htmlspecialchars($u['avatar'] ?? "https://avatar.vercel.sh/" . urlencode($u['name'] ?? 'T') . ".png") ?>" class="w-12 h-12 rounded-2xl object-cover border border-slate-200 shrink-0">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="font-bold text-sm text-slate-900"><?= htmlspecialchars($u['name'] ?? 'Trainer') ?></h4>
                                    <?= getStatusBadge($ap['status'] ?? 'PENDING') ?>
                                    <?= getAvailabilityBadge($t['availabilityStatus'] ?? 'AVAILABLE_NOW', $t['availableFromDate'] ?? null) ?>
                                    <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                        <?= htmlspecialchars($ap['matchScore'] ?? 95) ?>% Match
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    <?= htmlspecialchars($t['professionalTitle'] ?? 'Faculty') ?> • <?= htmlspecialchars($t['currentCity'] ?? 'India') ?> • <?= htmlspecialchars($t['totalExperienceYears'] ?? 0) ?> Yrs Exp
                                </p>
                                <p class="text-xs text-blue-700 font-bold mt-1">
                                    Proposed Rate: <strong><?= formatINR($ap['proposedDailyRate'] ?? 0) ?>/day</strong>
                                    <?php if (!empty($ap['coverNote'])): ?>
                                        <span class="text-slate-500 font-normal italic ml-2">"<?= htmlspecialchars($ap['coverNote']) ?>"</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Action Controls for Application -->
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <!-- View Trainer Dossier & Resume -->
                            <a href="/admin/trainer-view.php?id=<?= (string)$t['_id'] ?>" class="text-xs font-bold text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">description</span>
                                View Resume & CV
                            </a>

                            <?php if (($ap['status'] ?? 'PENDING') !== 'ACCEPTED'): ?>
                                <form action="/actions/update-application.php" method="POST" class="inline">
                                    <input type="hidden" name="applicationId" value="<?= $appId ?>">
                                    <input type="hidden" name="status" value="ACCEPTED">
                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-1.5 rounded-xl shadow-xs transition-colors flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                        Accept & Assign
                                    </button>
                                </form>

                                <?php if (($ap['status'] ?? 'PENDING') !== 'SHORTLISTED'): ?>
                                    <form action="/actions/update-application.php" method="POST" class="inline">
                                        <input type="hidden" name="applicationId" value="<?= $appId ?>">
                                        <input type="hidden" name="status" value="SHORTLISTED">
                                        <button type="submit" class="bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 font-bold text-xs px-3 py-1.5 rounded-xl transition-colors">
                                            Shortlist
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form action="/actions/update-application.php" method="POST" class="inline">
                                    <input type="hidden" name="applicationId" value="<?= $appId ?>">
                                    <input type="hidden" name="status" value="REJECTED">
                                    <button type="submit" class="bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 font-bold text-xs px-2.5 py-1.5 rounded-xl transition-colors" title="Reject Application">
                                        Reject
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-xl border border-emerald-200 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px]">done_all</span>
                                    Assigned & Confirmed
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section: Matching Algorithm Recommendations -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100">
            <div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#FE5E04] text-xl">psychology</span>
                    <h3 class="font-bold text-base text-slate-900">Suggested Matching Candidates (AI Match Index)</h3>
                </div>
                <p class="text-xs text-slate-500">Real-time candidate recommendations ranked by domain overlap, verified & resume skills, and experience.</p>
            </div>
            <span class="text-xs font-bold text-[#FE5E04] bg-[#FE5E04]/10 px-3 py-1 rounded-full border border-[#FE5E04]/20 self-start sm:self-auto">
                <?= count($matchedCandidates) ?> Matched Profiles
            </span>
        </div>

        <?php if (empty($matchedCandidates)): ?>
            <div class="p-8 text-center text-xs text-slate-400">
                No approved trainers currently match the domain or skill requirements.
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($matchedCandidates as $item): 
                    $mt = $item['trainer'];
                    $mu = $item['user'];
                    $match = $item['match'];
                    $score = $item['score'];
                    $mtId = (string)$mt['_id'];
                    $cleanPhone = preg_replace('/[^0-9]/', '', $mu['phone'] ?? $mt['phone'] ?? '919845012345');
                    if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;

                    $waMessage = rawurlencode("Hello " . ($mu['name'] ?? 'Trainer') . "! Mentry Solutions has an immediate training opportunity matching your profile: \"" . $opp['title'] . "\" in " . $opp['city'] . " (" . $opp['durationDays'] . " Days, ₹" . number_format($opp['dailyRateMin']) . "-₹" . number_format($opp['dailyRateMax']) . "/day). Are you available?");
                ?>
                    <div class="py-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4 hover:bg-slate-50/50 p-3 rounded-2xl transition-colors">
                        <div class="flex items-start gap-4">
                            <img src="<?= htmlspecialchars($mu['avatar'] ?? "https://avatar.vercel.sh/" . urlencode($mu['name'] ?? 'T') . ".png") ?>" class="w-12 h-12 rounded-2xl object-cover border border-slate-200 shrink-0 mt-0.5">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="font-bold text-sm text-slate-900"><?= htmlspecialchars($mu['name'] ?? 'Trainer') ?></h4>
                                    
                                    <!-- Match Score Pill -->
                                    <span class="inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-0.5 rounded-full <?= $score >= 80 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($score >= 60 ? 'bg-orange-50 text-[#FE5E04] border border-orange-200' : 'bg-slate-100 text-slate-600') ?>">
                                        <span class="material-symbols-outlined text-[13px]">bolt</span>
                                        <?= $score ?>% Match
                                    </span>

                                    <?= getAvailabilityBadge($mt['availabilityStatus'] ?? 'AVAILABLE_NOW', $mt['availableFromDate'] ?? null) ?>
                                    
                                    <?php if (!empty($match['isDomainMatch'])): ?>
                                        <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-blue-200">Domain Match</span>
                                    <?php endif; ?>
                                </div>

                                <p class="text-xs text-slate-500">
                                    <?= htmlspecialchars($mt['professionalTitle'] ?? 'Technical Faculty') ?> • 
                                    <strong><?= htmlspecialchars($mt['totalExperienceYears'] ?? 0) ?> Yrs Exp</strong> • 
                                    <?= htmlspecialchars($mt['currentCity'] ?? 'India') ?> • 
                                    Rate: <span class="font-bold text-slate-900"><?= formatINR($mt['dailyRateINR'] ?? 0) ?>/day</span>
                                </p>

                                <!-- Matched Skills Chips -->
                                <?php if (!empty($match['matchedSkills'])): ?>
                                    <div class="flex flex-wrap items-center gap-1 pt-0.5">
                                        <span class="text-[10px] text-slate-400 font-semibold mr-1">Skills:</span>
                                        <?php foreach (array_slice($match['matchedSkills'], 0, 5) as $ms): ?>
                                            <span class="inline-flex items-center gap-0.5 bg-emerald-50 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded border border-emerald-200">
                                                <span class="material-symbols-outlined text-[11px] text-emerald-600">check</span>
                                                <?= htmlspecialchars($ms) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Contact & Sourcing Controls for Admin & Staff -->
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <!-- WhatsApp Link -->
                            <a href="https://wa.me/<?= $cleanPhone ?>?text=<?= $waMessage ?>" target="_blank" class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-3 py-1.5 rounded-xl transition-all shadow-2xs" title="Chat on WhatsApp">
                                <span class="material-symbols-outlined text-[16px] text-emerald-600">chat</span>
                                WhatsApp
                            </a>

                            <!-- Email Contact Button -->
                            <button type="button" onclick="openContactModal('<?= $mtId ?>', '<?= htmlspecialchars(addslashes($mu['name'] ?? 'Candidate')) ?>', '<?= htmlspecialchars(addslashes($mu['email'] ?? '')) ?>')" class="inline-flex items-center gap-1 text-xs font-bold text-[#FE5E04] bg-orange-50 hover:bg-orange-100 border border-orange-200 px-3 py-1.5 rounded-xl transition-all shadow-2xs">
                                <span class="material-symbols-outlined text-[16px]">mail</span>
                                Contact / Invite
                            </button>

                            <!-- Profile View -->
                            <a href="/admin/trainer-view.php?id=<?= $mtId ?>" class="text-xs font-bold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-xl transition-colors">
                                Dossier
                            </a>

                            <!-- Direct Quick Assign -->
                            <form action="/actions/assign-trainer.php" method="POST" class="inline">
                                <input type="hidden" name="opportunityId" value="<?= $oppId ?>">
                                <input type="hidden" name="trainerId" value="<?= $mtId ?>">
                                <input type="hidden" name="agreedDailyRate" value="<?= htmlspecialchars($mt['dailyRateINR'] ?? 6000) ?>">
                                <input type="hidden" name="closeOpportunity" value="1">
                                <button type="submit" class="bg-slate-900 hover:bg-[#FE5E04] text-white font-bold text-xs px-3.5 py-1.5 rounded-xl shadow-xs transition-colors flex items-center gap-1" title="Assign trainer and close opportunity">
                                    <span class="material-symbols-outlined text-[15px]">assignment_ind</span>
                                    Assign & Close
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Contact Candidate via Email / Direct Dispatch -->
<div id="contactModal" class="fixed inset-0 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-3xl border border-slate-200 max-w-lg w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-orange-50 text-[#FE5E04] flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-lg">mail</span>
                </span>
                <div>
                    <h3 class="font-bold text-sm text-slate-900">Direct Candidate Contact</h3>
                    <p class="text-[11px] text-slate-500" id="contactCandidateSub">Send official opportunity invitation</p>
                </div>
            </div>
            <button type="button" onclick="closeContactModal()" class="text-slate-400 hover:text-slate-600">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <form action="/actions/contact-candidate.php" method="POST" class="space-y-3">
            <input type="hidden" name="opportunityId" value="<?= $oppId ?>">
            <input type="hidden" name="trainerId" id="modalTrainerId" value="">

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Candidate Email</label>
                <input type="text" id="modalCandidateEmail" disabled class="w-full bg-slate-100 border border-slate-200 rounded-xl p-2.5 text-xs text-slate-600 font-semibold cursor-not-allowed">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Custom Note / Logistics Instruction (Optional)</label>
                <textarea name="message" rows="3" placeholder="e.g. We loved your background in Docker and AWS. Campus dates are firm, executive lodging provided. Let us know if you can take this up!" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white focus:border-[#FE5E04]"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeContactModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-5 py-2 rounded-xl shadow-md shadow-orange-500/20 transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">send</span>
                    Send Official Invitation Email
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openContactModal(trainerId, candidateName, candidateEmail) {
    document.getElementById('modalTrainerId').value = trainerId;
    document.getElementById('modalCandidateEmail').value = candidateName + ' (' + candidateEmail + ')';
    document.getElementById('contactCandidateSub').textContent = 'Invite ' + candidateName + ' for this assignment';
    var modal = document.getElementById('contactModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeContactModal() {
    var modal = document.getElementById('contactModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>

</main>
</div>
</body>
</html>
