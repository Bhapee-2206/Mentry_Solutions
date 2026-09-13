<?php
// admin/assignments.php - Assignments & Logistics Manager
$pageTitle = "Assignments & Logistics";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
syncAssignmentStatuses();
require_once __DIR__ . '/includes/sidebar.php';

$asgCol = getCollection("Assignment");
$oppCol = getCollection("Opportunity");
$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");

$statusFilter = $_GET['status'] ?? 'ALL';
$filter = [];
if ($statusFilter !== 'ALL') {
    $filter['status'] = $statusFilter;
}

$assignments = $asgCol ? $asgCol->find($filter, ['sort' => ['createdAt' => -1]])->toArray() : [];
?>

<div class="space-y-5 sm:space-y-6 max-w-full overflow-hidden">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Active Training Assignments & Logistics</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-0.5">Track live campus deliveries, modify guest house bookings, travel tickets, and delivery honorariums.</p>
        </div>
    </div>

    <!-- Filter Tabs (Fully Scrollable & Responsive) -->
    <div class="bg-white p-2.5 sm:p-3 rounded-2xl border border-slate-200/90 shadow-card flex items-center gap-1.5 sm:gap-2 overflow-x-auto max-w-full">
        <?php
        $tabs = [
            'ALL' => 'All Assignments',
            'SCHEDULED' => 'Scheduled',
            'IN_PROGRESS' => 'In Progress',
            'COMPLETED' => 'Completed',
            'RELIEVED' => 'Relieved',
            'CANCELLED' => 'Cancelled'
        ];
        foreach ($tabs as $k => $v): ?>
            <a href="/admin/assignments.php?status=<?= $k ?>" class="px-3 sm:px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 whitespace-nowrap <?= $statusFilter === $k ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
                <?= $v ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Assignments Cards List -->
    <div class="space-y-4">
        <?php if (empty($assignments)): ?>
            <div class="bg-white p-8 sm:p-12 rounded-3xl border border-slate-200/90 shadow-card text-center text-xs text-slate-400">
                No assignments found in this category. Accept applications or assign trainers directly from Opportunities.
            </div>
        <?php else: ?>
            <?php foreach ($assignments as $asg): 
                $asgId = (string)$asg['_id'];
                $opp = null;
                if (!empty($asg['opportunityId'])) {
                    try { $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$asg['opportunityId'])]); } catch (Exception $e) {}
                }
                $trainer = null;
                $trainerUser = null;
                if (!empty($asg['trainerId'])) {
                    try {
                        $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$asg['trainerId'])]);
                        if ($trainer && !empty($trainer['userId'])) {
                            $trainerUser = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainer['userId'])]);
                        }
                    } catch (Exception $e) {}
                }
            ?>
                <div class="bg-white p-4 sm:p-6 md:p-8 rounded-3xl border border-slate-200/90 shadow-card space-y-5 sm:space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 sm:gap-4 pb-4 border-b border-slate-100">
                        <div class="space-y-1 min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[10px] font-extrabold uppercase text-emerald-800 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">Assignment ID: <?= substr($asgId, -8) ?></span>
                                <?= getStatusBadge($asg['status'] ?? 'SCHEDULED') ?>
                            </div>
                            <h3 class="font-black text-base sm:text-lg text-slate-900 mt-1 break-words"><?= htmlspecialchars($opp['title'] ?? 'Custom Campus Training Engagement') ?></h3>
                            <p class="text-xs text-slate-500 font-medium">
                                <?= htmlspecialchars($asg['location'] ?? ($opp['city'] ?? 'India')) ?> • 
                                Duration: <strong><?= htmlspecialchars($asg['durationDays'] ?? 5) ?> Working Days</strong> • 
                                Starts <strong><?= formatDate($asg['startDate'] ?? ($opp['startDate'] ?? null)) ?></strong>
                            </p>
                        </div>

                        <div class="flex flex-col sm:items-end gap-2 shrink-0 w-full sm:w-auto">
                            <div class="text-left sm:text-right bg-slate-50 border border-slate-100 p-3 sm:p-3.5 rounded-2xl w-full sm:w-auto">
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Total Agreed Honorarium</span>
                                <p class="font-black text-lg sm:text-xl text-emerald-700"><?= formatINR($asg['agreedTotalFee'] ?? 0) ?></p>
                                <span class="text-[10px] text-slate-500 font-medium"><?= formatINR($asg['agreedDailyRate'] ?? 0) ?>/day</span>
                            </div>
                            <?php if (!in_array(strtoupper($asg['status'] ?? ''), ['RELIEVED', 'CANCELLED', 'COMPLETED'])): ?>
                                <button type="button" 
                                        onclick="openReliefModal('<?= $asgId ?>', '<?= htmlspecialchars(addslashes($trainerUser['name'] ?? 'Faculty'), ENT_QUOTES) ?>', '<?= (string)($trainer['_id'] ?? '') ?>', '<?= (string)($asg['opportunityId'] ?? '') ?>')" 
                                        class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 hover:border-rose-300 text-xs font-bold px-3.5 py-1.5 rounded-xl transition-all shadow-2xs flex items-center justify-center gap-1.5 cursor-pointer w-full sm:w-auto" 
                                        title="Relieve this trainer from assignment (last-minute dropout, emergency, or reassignment)">
                                    <span class="material-symbols-outlined text-[15px] text-rose-600">person_remove</span>
                                    Relieve Trainer
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Assigned Trainer & Logistics Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4 text-xs">
                        <!-- Trainer -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Assigned Faculty</span>
                            <?php if ($trainer && $trainerUser): ?>
                                <div class="flex items-center gap-3">
                                    <img src="<?= htmlspecialchars(getUserAvatar($trainerUser, 80)) ?>" 
                                         alt="<?= htmlspecialchars($trainerUser['name'] ?? 'Faculty') ?>"
                                         class="w-10 h-10 rounded-xl object-cover border border-slate-200 shrink-0" 
                                         style="object-position: center 15%;"
                                         referrerpolicy="no-referrer"
                                         loading="lazy"
                                         onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($trainerUser['name'] ?? 'Faculty') ?>&background=FE5E04&color=fff&size=80';">
                                    <div class="min-w-0">
                                        <a href="/admin/trainer-view.php?id=<?= (string)$trainer['_id'] ?>" class="font-bold text-slate-900 hover:text-blue-600 block truncate">
                                            <?= htmlspecialchars($trainerUser['name']) ?>
                                        </a>
                                        <p class="text-[11px] text-slate-500 truncate"><?= htmlspecialchars($trainer['professionalTitle'] ?? '') ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-slate-400 font-semibold">Trainer information unavailable</p>
                            <?php endif; ?>
                        </div>

                        <!-- Accommodation -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-1">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Accommodation & Lodging</span>
                            <p class="font-bold text-slate-800 break-words"><?= htmlspecialchars($asg['accommodationDetails'] ?? 'Campus Guest House Reserved') ?></p>
                        </div>

                        <!-- Travel -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-1 sm:col-span-2 md:col-span-1">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Travel Itinerary & Transport</span>
                            <p class="font-bold text-slate-800 break-words"><?= htmlspecialchars($asg['travelDetails'] ?? 'Flight / Train Tickets Arranged by Mentry') ?></p>
                        </div>
                    </div>

                    <!-- Update Assignment Logistics & Status Form -->
                    <form action="/actions/update-assignment.php" method="POST" class="bg-slate-50/70 p-3.5 sm:p-4 rounded-2xl border border-slate-200/80 space-y-3 sm:space-y-4">
                        <input type="hidden" name="assignmentId" value="<?= $asgId ?>">

                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-xs text-slate-800 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px] text-blue-600">tune</span>
                                Manage Assignment Terms & Delivery Status
                            </h4>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Status</label>
                                <select name="status" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-slate-800 outline-none">
                                    <option value="SCHEDULED" <?= ($asg['status'] ?? '') === 'SCHEDULED' ? 'selected' : '' ?>>SCHEDULED</option>
                                    <option value="IN_PROGRESS" <?= ($asg['status'] ?? '') === 'IN_PROGRESS' ? 'selected' : '' ?>>IN PROGRESS</option>
                                    <option value="COMPLETED" <?= ($asg['status'] ?? '') === 'COMPLETED' ? 'selected' : '' ?>>COMPLETED</option>
                                    <option value="RELIEVED" <?= ($asg['status'] ?? '') === 'RELIEVED' ? 'selected' : '' ?>>RELIEVED (Relieved from Duty)</option>
                                    <option value="CANCELLED" <?= ($asg['status'] ?? '') === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Agreed Daily Rate (₹)</label>
                                <input type="number" name="agreedDailyRate" value="<?= htmlspecialchars($asg['agreedDailyRate'] ?? 0) ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-slate-800 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Agreed Total Fee (₹)</label>
                                <input type="number" name="agreedTotalFee" value="<?= htmlspecialchars($asg['agreedTotalFee'] ?? 0) ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-emerald-700 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">College Feedback Rating (1-5)</label>
                                <input type="number" step="0.1" min="1" max="5" name="feedbackRating" value="<?= htmlspecialchars($asg['feedbackRating'] ?? 5.0) ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Accommodation Notes</label>
                                <input type="text" name="accommodationDetails" value="<?= htmlspecialchars($asg['accommodationDetails'] ?? '') ?>" placeholder="e.g. Executive Guest House Room #204" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Travel Tickets & Flight PNR</label>
                                <input type="text" name="travelDetails" value="<?= htmlspecialchars($asg['travelDetails'] ?? '') ?>" placeholder="e.g. Indigo 6E-204 / BLR to DEL" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs outline-none">
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between pt-2 gap-2 border-t border-slate-200/60">
                            <div>
                                <?php if (!in_array(strtoupper($asg['status'] ?? ''), ['RELIEVED', 'CANCELLED', 'COMPLETED'])): ?>
                                    <button type="button" 
                                            onclick="openReliefModal('<?= $asgId ?>', '<?= htmlspecialchars(addslashes($trainerUser['name'] ?? 'Faculty'), ENT_QUOTES) ?>', '<?= (string)($trainer['_id'] ?? '') ?>', '<?= (string)($asg['opportunityId'] ?? '') ?>')" 
                                            class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 hover:border-rose-300 text-xs font-bold px-3.5 py-2 rounded-xl transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer" 
                                            title="Relieve this trainer from assignment (reopens position for replacement)">
                                        <span class="material-symbols-outlined text-[16px] text-rose-600">person_remove</span>
                                        Relieve Trainer
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if (!empty($asg['opportunityId'])): ?>
                                    <a href="/admin/opportunity-view.php?id=<?= (string)$asg['opportunityId'] ?>" class="bg-white text-slate-700 hover:text-blue-600 border border-slate-200 hover:border-slate-300 text-xs font-bold px-3.5 py-2 rounded-xl transition-all flex items-center gap-1 shadow-2xs">
                                        <span class="material-symbols-outlined text-[15px]">visibility</span>
                                        View Program
                                    </a>
                                <?php endif; ?>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-xs cursor-pointer">
                                    Update Assignment Terms
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Relieve Faculty Member -->
<div id="reliefModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-3xl border border-slate-200 max-w-lg w-full p-6 sm:p-7 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold shrink-0 border border-rose-100">
                    <span class="material-symbols-outlined text-xl">person_remove</span>
                </span>
                <div>
                    <h3 class="font-bold text-base text-slate-900">Relieve Faculty Member</h3>
                    <p class="text-xs text-slate-500">Release trainer from this assignment and reopen opportunity slot</p>
                </div>
            </div>
            <button type="button" onclick="closeReliefModal()" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <form action="/actions/relieve-trainer.php" method="POST" class="space-y-4">
            <input type="hidden" name="assignmentId" id="reliefAssignmentId" value="">
            <input type="hidden" name="trainerId" id="reliefTrainerId" value="">
            <input type="hidden" name="opportunityId" id="reliefOpportunityId" value="">
            <input type="hidden" name="redirectUrl" value="/admin/assignments.php">

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
                <textarea name="reliefNotes" rows="2" placeholder="e.g. Trainer informed via phone due to last minute conflict. Reassigning..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white focus:ring-2 focus:ring-rose-500/20"></textarea>
            </div>

            <div class="space-y-2 pt-1 text-xs">
                <label class="flex items-center gap-2 cursor-pointer select-none text-slate-700 font-medium">
                    <input type="checkbox" name="reopenSlot" value="1" checked class="w-4 h-4 text-emerald-600 rounded border-slate-300">
                    <span>Automatically reopen position slot for replacement candidate</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer select-none text-slate-700 font-medium">
                    <input type="checkbox" name="notifyTrainer" value="1" checked class="w-4 h-4 text-blue-600 rounded border-slate-300">
                    <span>Send real-time relief notification & Web Push to the relieved faculty member</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeReliefModal()" class="text-xs font-bold text-slate-600 hover:text-slate-800 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 transition-colors cursor-pointer">
                    Keep Assignment
                </button>
                <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold px-5 py-2 rounded-xl shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">person_remove</span>
                    Confirm Relief & Open Slot
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openReliefModal(asgId, trainerName, trainerId, oppId) {
    const modal = document.getElementById('reliefModal');
    const asgIdInput = document.getElementById('reliefAssignmentId');
    const trainerIdInput = document.getElementById('reliefTrainerId');
    const oppIdInput = document.getElementById('reliefOpportunityId');
    const trainerNameEl = document.getElementById('reliefTrainerName');
    
    if (asgIdInput) asgIdInput.value = asgId || '';
    if (trainerIdInput) trainerIdInput.value = trainerId || '';
    if (oppIdInput) oppIdInput.value = oppId || '';
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
        closeReliefModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const relModal = document.getElementById('reliefModal');
    if (relModal) {
        relModal.addEventListener('click', function(e) {
            if (e.target === relModal) closeReliefModal();
        });
    }
});
</script>

</main>
</div>
</body>
</html>
