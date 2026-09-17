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

// Capacity and active assignments calculation
$trainersNeeded = max(1, (int)($opp['trainersNeeded'] ?? 1));

$oppQueryIds = [(string)$oppId];
try {
    $oppQueryIds[] = new MongoDB\BSON\ObjectId((string)$oppId);
} catch (\Throwable $e) {}

// Get all active assignments for this opportunity
$activeAssignments = $asgCol ? $asgCol->find([
    'opportunityId' => ['$in' => $oppQueryIds],
    'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS', 'CONFIRMED', 'ASSIGNED', 'ACCEPTED']]
])->toArray() : [];

$assignedTrainerIds = [];
$assignedFacultyCards = [];
foreach ($activeAssignments as $act) {
    $tId = (string)($act['trainerId'] ?? '');
    if (!empty($tId)) {
        $assignedTrainerIds[] = $tId;
        $tDoc = null;
        $uDoc = null;
        try {
            $tDoc = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($tId)]) : null;
            if ($tDoc && !empty($tDoc['userId']) && $userCol) {
                $uDoc = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$tDoc['userId'])]);
            }
        } catch (\Throwable $e) {}
        $assignedFacultyCards[] = [
            'assignment' => $act,
            'trainer' => $tDoc,
            'user' => $uDoc
        ];
    }
}

// Fallback: If opportunity has assigned trainers in its document that didn't have an active assignment record
$oppAssignedTrainerIds = [];
if (!empty($opp['assignedTrainerIds']) && is_array($opp['assignedTrainerIds'])) {
    foreach ($opp['assignedTrainerIds'] as $tid) {
        if (!empty($tid)) $oppAssignedTrainerIds[] = (string)$tid;
    }
}
if (!empty($opp['assignedTrainerId'])) {
    $oppAssignedTrainerIds[] = (string)$opp['assignedTrainerId'];
}
$oppAssignedTrainerIds = array_values(array_unique($oppAssignedTrainerIds));

foreach ($oppAssignedTrainerIds as $tid) {
    if (!in_array($tid, $assignedTrainerIds)) {
        $tDoc = null;
        $uDoc = null;
        try {
            $tDoc = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($tid)]) : null;
            if ($tDoc && !empty($tDoc['userId']) && $userCol) {
                $uDoc = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$tDoc['userId'])]);
            }
        } catch (\Throwable $e) {}

        if ($tDoc) {
            $assignedTrainerIds[] = $tid;
            $assignedFacultyCards[] = [
                'assignment' => [
                    '_id' => 'direct_' . $tid,
                    'opportunityId' => (string)$oppId,
                    'trainerId' => $tid,
                    'status' => 'ASSIGNED',
                    'agreedDailyRate' => (float)($opp['dailyRateMax'] ?? ($opp['dailyRateMin'] ?? 6000)),
                    'agreedTotalFee' => ((int)($opp['durationDays'] ?? 5)) * (float)($opp['dailyRateMax'] ?? ($opp['dailyRateMin'] ?? 6000))
                ],
                'trainer' => $tDoc,
                'user' => $uDoc
            ];
        }
    }
}

$assignedCount = count($assignedFacultyCards);
$openSlots = max(0, $trainersNeeded - $assignedCount);
$isFullyStaffed = ($assignedCount >= $trainersNeeded);

// Get relieved assignments for audit trail
$relievedAssignments = $asgCol ? $asgCol->find([
    'opportunityId' => $oppId,
    'status' => 'RELIEVED'
], ['sort' => ['relievedAt' => -1, 'updatedAt' => -1]])->toArray() : [];

// Legacy fallback for single trainer reference
$assignment = !empty($activeAssignments) ? $activeAssignments[0] : null;
$assignedTrainer = !empty($assignedFacultyCards) ? $assignedFacultyCards[0]['trainer'] : null;
$assignedUser = !empty($assignedFacultyCards) ? $assignedFacultyCards[0]['user'] : null;

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
        <a href="/admin/opportunities.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }" class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 hover:text-blue-600 bg-white border border-slate-200 hover:border-slate-300 px-3.5 py-2 rounded-xl transition-all shadow-2xs shrink-0 cursor-pointer">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            <span>Back to Opportunities</span>
        </a>

        <div class="flex items-center gap-2">
            <?php 
            $currStatus = strtoupper($opp['status'] ?? 'PUBLISHED');
            $isClosedOrMatched = ($currStatus === 'CLOSED' || $currStatus === 'MATCHED' || $isFullyStaffed);
            ?>

            <?php if (!empty($assignedFacultyCards)): ?>
                <?php if (count($assignedFacultyCards) === 1): 
                    $singleCard = $assignedFacultyCards[0];
                    $sAsgId = (string)$singleCard['assignment']['_id'];
                    $sTId = (string)($singleCard['trainer']['_id'] ?? '');
                    $sName = $singleCard['user']['name'] ?? ($singleCard['trainer']['name'] ?? 'Trainer');
                ?>
                    <button type="button" 
                            onclick="openReliefModal('<?= $sAsgId ?>', '<?= htmlspecialchars(addslashes($sName), ENT_QUOTES) ?>', '<?= $sTId ?>')" 
                            class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 hover:border-rose-300 text-xs font-bold px-3.5 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5 cursor-pointer"
                            title="Relieve <?= htmlspecialchars($sName) ?> from this assignment">
                        <span class="material-symbols-outlined text-[16px] text-rose-600">person_remove</span>
                        Relieve Trainer
                    </button>
                <?php else: ?>
                    <a href="#assignedFacultySection" 
                       class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 hover:border-rose-300 text-xs font-bold px-3.5 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5 cursor-pointer"
                       title="Scroll to assigned faculty members to relieve a trainer">
                        <span class="material-symbols-outlined text-[16px] text-rose-600">person_remove</span>
                        Relieve Faculty (<?= count($assignedFacultyCards) ?>)
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <form action="/actions/toggle-opportunity-status.php" method="POST" class="inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="id" value="<?= $oppId ?>">
                <button type="submit" class="bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 text-xs font-bold px-3.5 py-2 rounded-xl transition-all flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                    Delete
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success']) || !empty($_GET['relieved'])): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-600 text-base">check_circle</span>
                <span><?= htmlspecialchars($_SESSION['flash_success'] ?? 'Trainer relieved successfully. The assignment slot has been reopened.') ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-rose-600 text-base">error</span>
                <span><?= htmlspecialchars($_GET['error']) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-700"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
    <?php endif; ?>

    <!-- Prominent Capacity & Quota Metric Banner -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <span class="w-12 h-12 rounded-2xl <?= $isFullyStaffed ? 'bg-emerald-50 border border-emerald-200 text-emerald-600' : ($assignedCount > 0 ? 'bg-blue-50 border border-blue-200 text-blue-600' : 'bg-slate-100 border border-slate-200 text-slate-500') ?> flex items-center justify-center shrink-0 shadow-2xs">
                    <span class="material-symbols-outlined text-2xl"><?= $isFullyStaffed ? 'verified_user' : 'group_add' ?></span>
                </span>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-extrabold text-base text-slate-900">Faculty Allocation & Quota</h3>
                        <?php if ($isFullyStaffed): ?>
                            <span class="bg-emerald-100 text-emerald-800 font-black text-[10px] px-2.5 py-0.5 rounded-full uppercase tracking-wider flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs">check_circle</span> Fully Staffed (<?= $trainersNeeded ?>/<?= $trainersNeeded ?>)
                            </span>
                        <?php elseif ($assignedCount > 0): ?>
                            <span class="bg-blue-100 text-blue-800 font-black text-[10px] px-2.5 py-0.5 rounded-full uppercase tracking-wider flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs">pending</span> Partially Staffed (<?= $assignedCount ?>/<?= $trainersNeeded ?>)
                            </span>
                        <?php else: ?>
                            <span class="bg-amber-100 text-amber-900 font-black text-[10px] px-2.5 py-0.5 rounded-full uppercase tracking-wider flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs">hourglass_empty</span> Open for Sourcing (0/<?= $trainersNeeded ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Client requires <strong><?= $trainersNeeded ?> Faculty Member<?= $trainersNeeded > 1 ? 's' : '' ?></strong> for this engagement. 
                        <strong><?= $assignedCount ?></strong> assigned, <strong><?= $openSlots ?></strong> slot<?= $openSlots == 1 ? '' : 's' ?> remaining.
                        <?php if ($openSlots > 0): ?>
                            <span class="text-blue-600 font-semibold ml-1">You can assign <?= $openSlots ?> more faculty.</span>
                        <?php else: ?>
                            <span class="text-slate-500 italic ml-1">To assign a replacement, relieve an existing faculty member below.</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                <div class="bg-slate-50 border border-slate-200 px-3.5 py-2 rounded-xl text-center min-w-[75px]">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Required</span>
                    <span class="font-black text-sm text-slate-800"><?= $trainersNeeded ?></span>
                </div>
                <div class="bg-emerald-50 border border-emerald-200 px-3.5 py-2 rounded-xl text-center min-w-[75px]">
                    <span class="text-[10px] uppercase font-bold text-emerald-600 block">Assigned</span>
                    <span class="font-black text-sm text-emerald-700"><?= $assignedCount ?></span>
                </div>
                <div class="<?= $openSlots > 0 ? 'bg-amber-50 border border-amber-200' : 'bg-slate-100 border border-slate-200' ?> px-3.5 py-2 rounded-xl text-center min-w-[75px]">
                    <span class="text-[10px] uppercase font-bold <?= $openSlots > 0 ? 'text-amber-700' : 'text-slate-400' ?> block">Open Slots</span>
                    <span class="font-black text-sm <?= $openSlots > 0 ? 'text-amber-800' : 'text-slate-400' ?>"><?= $openSlots ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isClosedOrMatched && $isFullyStaffed): ?>
        <!-- Prominent Closed Opportunity Notice -->
        <div class="bg-slate-900 border border-slate-800 text-white rounded-3xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-lg">
            <div class="flex items-center gap-3.5">
                <span class="w-10 h-10 rounded-2xl bg-amber-400/20 border border-amber-400/30 flex items-center justify-center text-amber-400 shrink-0">
                    <span class="material-symbols-outlined text-2xl">lock</span>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-extrabold text-sm text-white">This Training Opportunity is Fully Staffed</h3>
                        <span class="bg-amber-400 text-slate-950 font-black text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider">Quota Met</span>
                    </div>
                    <p class="text-xs text-slate-300 mt-0.5">
                        All <?= $trainersNeeded ?> required trainer positions have been assigned. This opportunity is closed to additional candidate applications.
                    </p>
                </div>
            </div>
            <form action="/actions/toggle-opportunity-status.php" method="POST" class="shrink-0 w-full sm:w-auto">
                <input type="hidden" name="opportunityId" value="<?= $oppId ?>">
                <input type="hidden" name="action" value="reopen">
                <button type="submit" class="w-full sm:w-auto bg-white hover:bg-slate-100 text-slate-950 text-xs font-bold px-4 py-2 rounded-xl transition-colors shadow-xs flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-emerald-600">lock_open</span>
                    Force Reopen
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
                    <?= htmlspecialchars($opp['durationDays'] ?? 5) ?> Working Days • 
                    <?= !empty($opp['endDate']) ? 'Starts <strong>' . formatDate($opp['startDate'] ?? null) . '</strong> • Ends <strong>' . formatDate($opp['endDate']) . '</strong>' : 'Starts <strong>' . formatDate($opp['startDate'] ?? null) . '</strong>' ?> • 
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

        <!-- Campus Logistics Covered -->
        <div class="space-y-2 pt-1">
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Campus Logistics Covered</h4>
            <div class="flex flex-wrap gap-2 text-xs">
                <?php if (($opp['mode'] ?? '') === 'ONLINE'): ?>
                    <span class="bg-blue-50 text-blue-700 border border-blue-200 px-3 py-1.5 rounded-xl font-semibold">✓ Virtual Live Delivery (Zero Travel Required)</span>
                <?php else: ?>
                    <?php
                    $hasAnyLog = false;
                    $isTravel = array_key_exists('travelCovered', (array)$opp) ? filter_var($opp['travelCovered'], FILTER_VALIDATE_BOOLEAN) : true;
                    $isAccom = array_key_exists('accommodationCovered', (array)$opp) ? filter_var($opp['accommodationCovered'], FILTER_VALIDATE_BOOLEAN) : true;
                    $isDining = array_key_exists('diningCovered', (array)$opp) ? filter_var($opp['diningCovered'], FILTER_VALIDATE_BOOLEAN) : true;
                    ?>
                    <?php if ($isTravel): $hasAnyLog = true; ?>
                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-xl font-semibold">✓ Travel Logistics Covered</span>
                    <?php endif; ?>
                    <?php if ($isAccom): $hasAnyLog = true; ?>
                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-xl font-semibold">✓ On-Campus Accommodation</span>
                    <?php endif; ?>
                    <?php if ($isDining): $hasAnyLog = true; ?>
                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-xl font-semibold">✓ Guest House Dining</span>
                    <?php endif; ?>
                    <?php if (!$hasAnyLog): ?>
                        <span class="bg-slate-50 text-slate-500 border border-slate-200 px-3 py-1.5 rounded-xl font-medium">Standard Assignment (No Campus Logistics Arranged)</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-1 pt-2">
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Syllabus & Assignment Description</h4>
            <p class="text-xs text-slate-600 leading-relaxed bg-slate-50/50 p-4 rounded-2xl border border-slate-100"><?= nl2br(htmlspecialchars($opp['description'] ?? '')) ?></p>
        </div>
    </div>

    <!-- Active Confirmed Trainer Assignments (Multi-Trainer Support with Relief Action) -->
    <?php if (!empty($assignedFacultyCards)): ?>
        <div id="assignedFacultySection" class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-base text-slate-900 flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-600">verified</span>
                        Assigned Faculty Members (<?= count($assignedFacultyCards) ?> / <?= $trainersNeeded ?>)
                    </h3>
                    <p class="text-xs text-slate-500">Trainers confirmed for campus delivery. If a trainer reports an emergency or drops out, use "Relieve Trainer" to reopen the position.</p>
                </div>
                <span class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full">
                    <?= $openSlots === 0 ? 'All Positions Filled' : ($openSlots . ' Slot' . ($openSlots > 1 ? 's' : '') . ' Remaining') ?>
                </span>
            </div>

            <div class="grid gap-4">
                <?php foreach ($assignedFacultyCards as $idx => $card): 
                    $asg = $card['assignment'];
                    $asgId = (string)$asg['_id'];
                    $t = $card['trainer'];
                    $u = $card['user'];
                    $tName = $u['name'] ?? ($t['name'] ?? 'Faculty');
                    $tId = (string)($t['_id'] ?? '');
                ?>
                    <div class="bg-gradient-to-r from-emerald-50/70 to-teal-50/50 border border-emerald-200 rounded-3xl p-6 shadow-sm space-y-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <img src="<?= htmlspecialchars(getUserAvatar($u ?: $t, 120)) ?>" class="w-14 h-14 rounded-2xl object-cover border-2 border-emerald-300 shadow-sm" style="object-position: center 15%;">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="bg-emerald-600 text-white text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md">Trainer Slot #<?= $idx + 1 ?></span>
                                        <h4 class="font-black text-base text-slate-900"><?= htmlspecialchars($tName) ?></h4>
                                        <?= getStatusBadge($asg['status'] ?? 'SCHEDULED') ?>
                                    </div>
                                    <p class="text-xs text-slate-600 font-medium mt-0.5">
                                        <?= htmlspecialchars($t['professionalTitle'] ?? 'Lead Faculty') ?> • <?= htmlspecialchars($t['currentCity'] ?? 'India') ?>
                                        <?php if (!empty($u['phone'])): ?> • <span class="font-mono text-slate-700"><?= htmlspecialchars($u['phone']) ?></span><?php endif; ?>
                                        <?php if (!empty($u['email'])): ?> • <span class="text-slate-500"><?= htmlspecialchars($u['email']) ?></span><?php endif; ?>
                                    </p>
                                    <p class="text-xs text-emerald-800 font-bold mt-1">
                                        Confirmed Rate: <?= formatINR($asg['agreedDailyRate'] ?? 0) ?>/day (Total Honorarium: <?= formatINR($asg['agreedTotalFee'] ?? 0) ?>)
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <a href="/admin/trainer-view.php?id=<?= $tId ?>" target="_blank" class="bg-white text-slate-700 border border-slate-200 text-xs font-bold px-3.5 py-2 rounded-xl hover:bg-slate-50 transition-colors shadow-2xs flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px] text-slate-400">person</span>
                                    Dossier
                                </a>
                                <a href="/admin/assignments.php" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-3.5 py-2 rounded-xl transition-colors shadow-xs flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px]">local_shipping</span>
                                    Logistics
                                </a>
                                <button type="button" 
                                        onclick="openReliefModal('<?= $asgId ?>', '<?= htmlspecialchars(addslashes($tName), ENT_QUOTES) ?>', '<?= $tId ?>')" 
                                        class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 hover:border-rose-300 text-xs font-bold px-3.5 py-2 rounded-xl transition-all flex items-center gap-1.5 shadow-2xs cursor-pointer"
                                        title="Relieve this trainer from assignment (last-minute dropout, personal emergency, or client request)">
                                    <span class="material-symbols-outlined text-[16px] text-rose-600">person_remove</span>
                                    Relieve Trainer
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($asg['vendorFeedback']) || !empty($asg['trainerFeedback'])): ?>
                            <div class="pt-3 border-t border-emerald-200/80 grid sm:grid-cols-2 gap-3 text-xs">
                                <?php if (!empty($asg['vendorFeedback'])): ?>
                                    <div class="bg-white/80 p-3 rounded-xl border border-emerald-200">
                                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Institution Review: ★ <?= htmlspecialchars($asg['vendorFeedback']['rating'] ?? 5) ?>/5.0</span>
                                        <p class="text-[11px] text-slate-700 italic mt-0.5">"<?= htmlspecialchars($asg['vendorFeedback']['comments'] ?? '') ?>"</p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($asg['trainerFeedback'])): ?>
                                    <div class="bg-white/80 p-3 rounded-xl border border-emerald-200">
                                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Trainer Campus Feedback: ★ <?= htmlspecialchars($asg['trainerFeedback']['rating'] ?? 5) ?>/5.0</span>
                                        <p class="text-[11px] text-slate-700 italic mt-0.5">"<?= htmlspecialchars($asg['trainerFeedback']['comments'] ?? '') ?>"</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Section: Direct Trainer Assignment Tool (With Quota Enforcement) -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-base text-slate-900">Direct Trainer Assignment</h3>
                <p class="text-xs text-slate-500">
                    <?php if ($isFullyStaffed): ?>
                        Position quota reached (<?= $trainersNeeded ?> of <?= $trainersNeeded ?>). To assign a replacement, relieve a trainer above.
                    <?php else: ?>
                        Assign verified faculty from the network. <?= $openSlots ?> slot<?= $openSlots > 1 ? 's' : '' ?> available.
                    <?php endif; ?>
                </p>
            </div>
            <span class="material-symbols-outlined <?= $isFullyStaffed ? 'text-slate-400' : 'text-blue-600' ?> text-2xl">how_to_reg</span>
        </div>

        <?php if ($isFullyStaffed): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-amber-600 text-xl shrink-0">lock</span>
                    <div>
                        <strong>Quota Full (<?= $trainersNeeded ?> of <?= $trainersNeeded ?> Faculty Assigned):</strong>
                        <span>All required slots for this opportunity have been filled. You cannot assign additional faculty unless an existing assigned trainer is relieved.</span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <form action="/actions/assign-trainer.php" method="POST" class="grid sm:grid-cols-3 gap-4 pt-2">
                <input type="hidden" name="opportunityId" value="<?= $oppId ?>">

                <div class="sm:col-span-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Verified Trainer *</label>
                    <select name="trainerId" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs font-medium outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">-- Choose Trainer (Slot <?= $assignedCount + 1 ?> of <?= $trainersNeeded ?>) --</option>
                        <?php foreach ($allApprovedTrainers as $at): 
                            $atIdStr = (string)$at['_id'];
                            if (in_array($atIdStr, $assignedTrainerIds)) continue; // Skip already assigned trainers!

                            $atu = null;
                            if (!empty($at['userId'])) {
                                try { $atu = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$at['userId'])]); } catch (Exception $e) {}
                            }
                            $atEff = getTrainerEffectiveAvailability($at);
                            $atAvail = '🟢 Available Now';
                            if ($atEff['status'] === 'FREE_FROM_DATE' && !empty($atEff['date'])) {
                                $atAvail = '🟡 Free from ' . formatDate($atEff['date']);
                            } elseif (($atEff['status'] === 'BUSY_ON_ASSIGNMENT' || $atEff['status'] === 'DELIVERING') && !empty($atEff['date'])) {
                                $atAvail = '🔵 Delivering (until ' . formatDate($atEff['date']) . ')';
                            } elseif ($atEff['status'] === 'UNAVAILABLE') {
                                $atAvail = '⚪ Unavailable';
                            }
                        ?>
                            <option value="<?= $atIdStr ?>">
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
                        <input type="checkbox" name="closeOpportunity" value="1" <?= ($assignedCount + 1 >= $trainersNeeded) ? 'checked' : '' ?> class="w-4 h-4 text-emerald-600 rounded">
                        <span>Close opportunity when all <?= $trainersNeeded ?> slots are assigned (hides from public & trainer feeds)</span>
                    </label>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow-xs transition-colors flex items-center gap-1.5 shrink-0">
                        <span class="material-symbols-outlined text-[16px]">check_circle</span>
                        Confirm Assignment (Slot <?= $assignedCount + 1 ?> of <?= $trainersNeeded ?>)
                    </button>
                </div>
            </form>
        <?php endif; ?>
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
                    $appId = (string)$ap['_id'];
                    $apTrainerId = (string)($ap['trainerId'] ?? '');
                    $apUserId = ($t && !empty($t['userId'])) ? (string)$t['userId'] : '';

                    // Robust query across Document collection
                    $docQueryOr = [];
                    if (!empty($apTrainerId)) {
                        $docQueryOr[] = ['trainerId' => $apTrainerId, 'type' => 'RESUME'];
                        if (preg_match('/^[a-f0-9]{24}$/i', $apTrainerId)) {
                            $docQueryOr[] = ['trainerId' => new MongoDB\BSON\ObjectId($apTrainerId), 'type' => 'RESUME'];
                        }
                    }
                    if (!empty($apUserId)) {
                        $docQueryOr[] = ['userId' => $apUserId, 'type' => 'RESUME'];
                        $docQueryOr[] = ['trainerId' => $apUserId, 'type' => 'RESUME'];
                    }
                    if (!empty($apTrainerId)) {
                        $docQueryOr[] = ['trainerId' => $apTrainerId];
                        if (preg_match('/^[a-f0-9]{24}$/i', $apTrainerId)) {
                            $docQueryOr[] = ['trainerId' => new MongoDB\BSON\ObjectId($apTrainerId)];
                        }
                    }
                    if (!empty($apUserId)) {
                        $docQueryOr[] = ['userId' => $apUserId];
                    }

                    $trainerDoc = (!empty($docQueryOr) && $docCol) ? $docCol->findOne(['$or' => $docQueryOr], ['sort' => ['uploadedAt' => -1]]) : null;

                    $activeResumeUrl = $ap['resumeUrl'] ?? ($t['resumeUrl'] ?? ($trainerDoc['fileUrl'] ?? ''));
                    if (empty($activeResumeUrl) && !empty($apTrainerId)) {
                        $activeResumeUrl = '/actions/download-trainer-profile.php?id=' . urlencode($apTrainerId) . '&view=1';
                    }

                    $candidateName = $u['name'] ?? ($t['name'] ?? 'Trainer');
                    $cleanCandidateName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($candidateName));
                    $cleanTrainerCode = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim(getMentryCode('TRAINER', $t ?: ['_id' => $apTrainerId])));
                    $resumeExt = !empty($activeResumeUrl) ? (strtolower(pathinfo(parse_url($activeResumeUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'pdf') : 'pdf';
                    $downloadFilename = "{$cleanCandidateName}_Resume_{$cleanTrainerCode}.{$resumeExt}";
                ?>
                    <div class="py-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <a href="/admin/trainer-view.php?id=<?= $apTrainerId ?>" target="_blank" class="shrink-0 group">
                                <img src="<?= htmlspecialchars(getUserAvatar(!empty($t['avatar']) ? $t : ($u ?: 'Trainer'), 100)) ?>" 
                                     alt="<?= htmlspecialchars($candidateName) ?>"
                                     class="w-12 h-12 rounded-2xl object-cover border border-slate-200 shrink-0 group-hover:border-blue-400 transition-colors"
                                     style="object-position: center 15%;"
                                     referrerpolicy="no-referrer"
                                     loading="lazy"
                                     onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($candidateName) ?>&background=FE5E04&color=fff&size=100';">
                            </a>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="/admin/trainer-view.php?id=<?= $apTrainerId ?>" target="_blank" class="hover:text-blue-600 transition-colors">
                                        <h4 class="font-bold text-sm text-slate-900"><?= htmlspecialchars($candidateName) ?></h4>
                                    </a>
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
                            <!-- View Resume & CV -->
                            <button type="button" 
                                    onclick="openAdminDocViewer('<?= htmlspecialchars($activeResumeUrl, ENT_QUOTES) ?>', 'Resume & CV - <?= htmlspecialchars(addslashes($candidateName), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($downloadFilename), ENT_QUOTES) ?>')" 
                                    class="text-xs font-bold text-slate-700 bg-slate-50 hover:bg-slate-100 hover:text-blue-600 border border-slate-200 px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1 cursor-pointer shadow-2xs">
                                <span class="material-symbols-outlined text-[16px] text-blue-600">description</span>
                                View Resume & CV
                            </button>

                            <!-- Profile Link -->
                            <a href="/admin/trainer-view.php?id=<?= $apTrainerId ?>" target="_blank" class="text-xs font-bold text-slate-600 bg-white hover:bg-slate-50 border border-slate-200 px-2.5 py-1.5 rounded-xl transition-colors flex items-center gap-1 shadow-2xs" title="Open Full Trainer Dossier in new tab">
                                <span class="material-symbols-outlined text-[15px] text-slate-400">person</span>
                                Profile
                            </a>

                            <?php if (($ap['status'] ?? 'PENDING') !== 'ACCEPTED'): ?>
                                <?php if ($isFullyStaffed): ?>
                                    <button type="button" disabled class="bg-slate-100 text-slate-400 border border-slate-200 font-bold text-xs px-3 py-1.5 rounded-xl cursor-not-allowed flex items-center gap-1 shadow-2xs" title="Position quota full (<?= $trainersNeeded ?>/<?= $trainersNeeded ?> filled). Relieve an assigned trainer first to accept new candidates.">
                                        <span class="material-symbols-outlined text-[15px] text-slate-400">lock</span>
                                        Quota Full
                                    </button>
                                <?php else: ?>
                                    <form action="/actions/update-application.php" method="POST" class="inline">
                                        <input type="hidden" name="applicationId" value="<?= $appId ?>">
                                        <input type="hidden" name="status" value="ACCEPTED">
                                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-1.5 rounded-xl shadow-xs transition-colors flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                            Accept & Assign
                                        </button>
                                    </form>
                                <?php endif; ?>

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
                            <?php else: 
                                $matchingAsgId = 'direct_' . $apTrainerId;
                                foreach ($activeAssignments as $actAsg) {
                                    if ((string)($actAsg['trainerId'] ?? '') === (string)$apTrainerId) {
                                        $matchingAsgId = (string)$actAsg['_id'];
                                        break;
                                    }
                                }
                            ?>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-200 flex items-center gap-1 shadow-2xs">
                                        <span class="material-symbols-outlined text-[16px]">done_all</span>
                                        Assigned & Confirmed
                                    </span>
                                    <button type="button" 
                                            onclick="openReliefModal('<?= $matchingAsgId ?>', '<?= htmlspecialchars(addslashes($candidateName), ENT_QUOTES) ?>', '<?= $apTrainerId ?>')" 
                                            class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 hover:border-rose-300 text-xs font-bold px-2.5 py-1.5 rounded-xl transition-all flex items-center gap-1 shadow-2xs cursor-pointer" 
                                            title="Relieve <?= htmlspecialchars($candidateName) ?> from this assignment">
                                        <span class="material-symbols-outlined text-[15px] text-rose-600">person_remove</span>
                                        Relieve
                                    </button>
                                </div>
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
                            <img src="<?= htmlspecialchars(getUserAvatar(!empty($mt['avatar']) ? $mt : ($mu ?: 'Trainer'), 100)) ?>" 
                                 alt="<?= htmlspecialchars($mu['name'] ?? 'Trainer') ?>"
                                 class="w-12 h-12 rounded-2xl object-cover border border-slate-200 shrink-0 mt-0.5"
                                 style="object-position: center 15%;"
                                 referrerpolicy="no-referrer"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($mu['name'] ?? 'Trainer') ?>&background=FE5E04&color=fff&size=100';">
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
                            <?php if (in_array($mtId, $assignedTrainerIds)): ?>
                                <span class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-xl flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px]">check_circle</span>
                                    Assigned
                                </span>
                            <?php elseif ($isFullyStaffed): ?>
                                <button type="button" disabled class="bg-slate-100 text-slate-400 border border-slate-200 font-bold text-xs px-3 py-1.5 rounded-xl cursor-not-allowed flex items-center gap-1 shadow-2xs" title="Position quota full (<?= $trainersNeeded ?>/<?= $trainersNeeded ?> filled). Relieve an assigned trainer first to assign new faculty.">
                                    <span class="material-symbols-outlined text-[15px] text-slate-400">lock</span>
                                    Quota Full
                                </button>
                            <?php else: ?>
                                <form action="/actions/assign-trainer.php" method="POST" class="inline">
                                    <input type="hidden" name="opportunityId" value="<?= $oppId ?>">
                                    <input type="hidden" name="trainerId" value="<?= $mtId ?>">
                                    <input type="hidden" name="agreedDailyRate" value="<?= htmlspecialchars($mt['dailyRateINR'] ?? 6000) ?>">
                                    <input type="hidden" name="closeOpportunity" value="<?= ($assignedCount + 1 >= $trainersNeeded) ? '1' : '0' ?>">
                                    <button type="submit" class="bg-slate-900 hover:bg-[#FE5E04] text-white font-bold text-xs px-3.5 py-1.5 rounded-xl shadow-xs transition-colors flex items-center gap-1" title="Assign trainer to open slot">
                                        <span class="material-symbols-outlined text-[15px]">assignment_ind</span>
                                        Assign (Slot <?= $assignedCount + 1 ?>)
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($relievedAssignments)): ?>
        <!-- Relieved Faculty Audit History -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-600 text-xl">history_toggle_off</span>
                    <h3 class="font-bold text-base text-slate-900">Relieved Faculty History (<?= count($relievedAssignments) ?>)</h3>
                </div>
                <span class="text-xs text-slate-400 font-medium">Log of trainers relieved from this opportunity</span>
            </div>

            <div class="divide-y divide-slate-100 text-xs">
                <?php foreach ($relievedAssignments as $rasg): 
                    $rtId = (string)($rasg['trainerId'] ?? '');
                    $rt = null;
                    $ru = null;
                    if ($trainerCol && !empty($rtId)) {
                        try {
                            $rt = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($rtId)]);
                            if ($rt && !empty($rt['userId']) && $userCol) {
                                $ru = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$rt['userId'])]);
                            }
                        } catch (\Throwable $e) {}
                    }
                    $rtName = $ru['name'] ?? ($rt['name'] ?? 'Faculty Member');
                ?>
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center font-bold shrink-0">
                                <span class="material-symbols-outlined text-base">person_remove</span>
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="font-bold text-slate-900"><?= htmlspecialchars($rtName) ?></h4>
                                    <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-md">RELIEVED</span>
                                    <span class="text-[10px] text-slate-400">Relieved <?= formatDate($rasg['relievedAt'] ?? $rasg['updatedAt'] ?? null) ?></span>
                                </div>
                                <p class="text-slate-600 mt-0.5">
                                    <strong>Reason:</strong> <?= htmlspecialchars($rasg['reliefReason'] ?? 'Dropout / Unavailability') ?>
                                    <?php if (!empty($rasg['reliefNotes'])): ?>
                                        • <span class="italic text-slate-500">"<?= htmlspecialchars($rasg['reliefNotes']) ?>"</span>
                                    <?php endif; ?>
                                    <?php if (!empty($rasg['relievedByName'])): ?>
                                        • <span class="text-slate-400">By <?= htmlspecialchars($rasg['relievedByName']) ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-xl border border-emerald-200 self-start sm:self-auto">
                            Slot Reopened
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
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

<!-- ================= MODAL: IN-BROWSER RESUME VIEWER (NO DOWNLOAD) ================= -->
<div id="documentViewerModal" class="hidden fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-xs items-center justify-center p-3 sm:p-6 transition-opacity">
    <div class="bg-white rounded-3xl max-w-5xl w-full h-[92vh] flex flex-col shadow-2xl overflow-hidden border border-slate-200">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-white shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center shrink-0 border border-blue-100">
                    <span class="material-symbols-outlined text-2xl">description</span>
                </div>
                <div>
                    <h3 id="adminDocViewerTitle" class="font-extrabold text-sm text-slate-900">Faculty Resume Viewer</h3>
                    <p class="text-[11px] text-slate-500">Live In-Browser Document Preview &bull; Fast render with zero auto-downloads</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a id="adminDocViewerDownload" href="#" target="_blank" download class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-3.5 py-2 rounded-xl transition-colors flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[15px]">download</span>
                    <span>Download</span>
                </a>
                <button type="button" onclick="closeAdminDocViewer()" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition-colors" title="Close Preview">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>

        <!-- Modal Iframe Body -->
        <div class="flex-1 bg-slate-100 p-2 sm:p-3 overflow-hidden relative">
            <iframe id="adminDocViewerIframe" src="" class="w-full h-full rounded-2xl border-0 bg-white shadow-inner"></iframe>
        </div>
    </div>
</div>

<script>
const APP_BASE_URL = '<?= function_exists('getAppBaseUrl') ? getAppBaseUrl() : '' ?>';

function getAppUrl(path) {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    if (APP_BASE_URL && !path.startsWith(APP_BASE_URL)) {
        return APP_BASE_URL + (path.startsWith('/') ? path : '/' + path);
    }
    return path;
}

function openAdminDocViewer(url, title, downloadFilename) {
    const modal = document.getElementById('documentViewerModal');
    const iframe = document.getElementById('adminDocViewerIframe');
    const titleEl = document.getElementById('adminDocViewerTitle');
    const downloadEl = document.getElementById('adminDocViewerDownload');
    
    if (titleEl) titleEl.textContent = title || 'Candidate Resume & CV';
    
    if (downloadEl) {
        const dlName = downloadFilename || 'Candidate_Resume';
        if (url.indexOf('download-trainer-profile.php') !== -1) {
            const cleanUrl = url.replace(/&view=1|view=1&?/, '').replace(/&inline=1|inline=1&?/, '');
            downloadEl.href = getAppUrl(cleanUrl);
        } else {
            downloadEl.href = getAppUrl('/actions/download-document.php?url=' + encodeURIComponent(url) + '&filename=' + encodeURIComponent(dlName));
        }
        downloadEl.setAttribute('download', dlName);
        downloadEl.setAttribute('title', 'Download ' + dlName);
    }

    let previewUrl = '';
    if (url.indexOf('download-trainer-profile.php') !== -1) {
        previewUrl = getAppUrl(url);
    } else {
        previewUrl = getAppUrl('/actions/preview-doc.php?url=' + encodeURIComponent(url) + '&title=' + encodeURIComponent(title || 'Candidate Resume'));
    }

    if (iframe) iframe.src = previewUrl;
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeAdminDocViewer() {
    const modal = document.getElementById('documentViewerModal');
    const iframe = document.getElementById('adminDocViewerIframe');
    if (iframe) iframe.src = 'about:blank';
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Relief Modal Handlers
function openReliefModal(asgId, trainerName, trainerId) {
    const modal = document.getElementById('reliefModal');
    const asgIdInput = document.getElementById('reliefAssignmentId');
    const trainerIdInput = document.getElementById('reliefTrainerId');
    const trainerNameEl = document.getElementById('reliefTrainerName');
    
    if (asgIdInput) asgIdInput.value = asgId || '';
    if (trainerIdInput) trainerIdInput.value = trainerId || '';
    if (trainerNameEl) trainerNameEl.textContent = trainerName || 'Trainer';
    
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeReliefModal() {
    const modal = document.getElementById('reliefModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdminDocViewer();
        closeReliefModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const docModal = document.getElementById('documentViewerModal');
    if (docModal) {
        docModal.addEventListener('click', function(e) {
            if (e.target === docModal) closeAdminDocViewer();
        });
    }

    const relModal = document.getElementById('reliefModal');
    if (relModal) {
        relModal.addEventListener('click', function(e) {
            if (e.target === relModal) closeReliefModal();
        });
    }
});
</script>

<!-- Modal: Relieve Assigned Faculty -->
<div id="reliefModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-3xl border border-slate-200 max-w-lg w-full p-6 sm:p-7 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold shrink-0 border border-rose-100">
                    <span class="material-symbols-outlined text-xl">person_remove</span>
                </span>
                <div>
                    <h3 class="font-bold text-base text-slate-900">Relieve Faculty Member</h3>
                    <p class="text-xs text-slate-500">Release trainer from this opportunity and reopen slot</p>
                </div>
            </div>
            <button type="button" onclick="closeReliefModal()" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <form action="/actions/relieve-trainer.php" method="POST" class="space-y-4">
            <input type="hidden" name="assignmentId" id="reliefAssignmentId" value="">
            <input type="hidden" name="trainerId" id="reliefTrainerId" value="">
            <input type="hidden" name="opportunityId" value="<?= $oppId ?>">

            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-900 space-y-1">
                <div class="font-bold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-amber-700 text-base">warning</span>
                    Relieving: <span id="reliefTrainerName" class="text-slate-900 font-extrabold underline decoration-amber-400">Trainer</span>
                </div>
                <p class="text-[11px] text-amber-800 leading-relaxed">
                    This will mark the assignment as <strong>RELIEVED</strong>, restore the trainer's availability to <strong>Available Now</strong>, and immediately reopen the opportunity position so you can assign a replacement.
                </p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Reason for Relief *</label>
                <select name="reliefReason" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs font-medium text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-rose-500/20">
                    <option value="Trainer dropped out last minute (unavailability / personal emergency)">Trainer dropped out last minute (unavailability / personal emergency)</option>
                    <option value="Health / Medical emergency">Health / Medical emergency</option>
                    <option value="Scheduling / Calendar conflict">Scheduling / Calendar conflict</option>
                    <option value="Client / College requested replacement">Client / College requested replacement</option>
                    <option value="Performance / Syllabus misalignment">Performance / Syllabus misalignment</option>
                    <option value="Other operational reassignment">Other operational reassignment</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Operational Remarks / Admin Notes</label>
                <textarea name="reliefNotes" rows="2" placeholder="e.g. Trainer informed via phone at 9 AM due to fever. Reassigning slot..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white focus:ring-2 focus:ring-rose-500/20"></textarea>
            </div>

            <div class="space-y-2 pt-1 text-xs">
                <label class="flex items-center gap-2 cursor-pointer select-none text-slate-700 font-medium">
                    <input type="checkbox" name="reopenSlot" value="1" checked class="w-4 h-4 text-emerald-600 rounded border-slate-300">
                    <span>Automatically reopen position slot and show on sourcing feeds</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer select-none text-slate-700 font-medium">
                    <input type="checkbox" name="notifyTrainer" value="1" checked class="w-4 h-4 text-blue-600 rounded border-slate-300">
                    <span>Send real-time relief notification & Web Push to the relieved faculty member</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeReliefModal()" class="text-xs font-bold text-slate-600 hover:text-slate-800 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 transition-colors">
                    Keep Assignment
                </button>
                <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold px-5 py-2 rounded-xl shadow-xs transition-colors flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">person_remove</span>
                    Confirm Relief & Open Slot
                </button>
            </div>
        </form>
    </div>
</div>

</main>
</div>
</body>
</html>
