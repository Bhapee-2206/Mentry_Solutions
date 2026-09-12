<?php
// trainer/assignments.php - Confirmed Training Assignments & Post-Training Feedback
$pageTitle = "My Confirmed Assignments";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

syncAssignmentStatuses();

$assignmentCol = getCollection("Assignment");
$opportunityCol = getCollection("Opportunity");
$trainerCol = getCollection("Trainer");

$trainer = null;
if ($trainerCol) {
    try {
        $trainer = $trainerCol->findOne([
            '$or' => [
                ['userId' => $user['id']],
                ['userId' => new MongoDB\BSON\ObjectId($user['id'])]
            ]
        ]);
    } catch (\Throwable $e) {
        $trainer = $trainerCol->findOne(['userId' => $user['id']]);
    }
}
$trainerId = $trainer ? (string)$trainer['_id'] : '';

$assignments = ($assignmentCol && !empty($trainerId)) ? $assignmentCol->find(
    ['trainerId' => $trainerId],
    ['sort' => ['createdAt' => -1]]
)->toArray() : [];
?>

<div class="space-y-6">
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-emerald-600">check_circle</span>
                <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Confirmed Training Assignments</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manage live campus schedules, guest house details, payout tracking, and post-delivery reviews.</p>
    </div>

    <div class="space-y-4">
        <?php if (empty($assignments)): ?>
            <div class="bg-white p-12 rounded-3xl border border-slate-200/90 shadow-card text-center text-xs text-slate-400">
                No confirmed assignments active currently. When a college selects your profile, your assignment itinerary will appear here.
            </div>
        <?php else: ?>
            <?php foreach ($assignments as $asg): 
                $asgId = (string)$asg['_id'];
                $opp = null;
                if ($opportunityCol && !empty($asg['opportunityId'])) {
                    try {
                        $opp = $opportunityCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$asg['opportunityId'])]);
                    } catch (\Throwable $e) {}
                }
                $asgStatus = strtoupper($asg['status'] ?? 'SCHEDULED');
                $hasTrainerFeedback = !empty($asg['trainerFeedback']);
                $hasVendorFeedback = !empty($asg['vendorFeedback']);
            ?>
                <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-card space-y-4 min-w-0">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pb-4 border-b border-slate-100">
                        <div class="space-y-1 min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[10px] font-bold uppercase text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200 shrink-0">Assignment #<?= substr($asgId, -6) ?></span>
                                <?= getStatusBadge($asgStatus) ?>
                                <?php if ($opp): ?>
                                    <span class="text-[10px] font-mono text-slate-400 shrink-0">Job ID: <?= htmlspecialchars($opp['jobId'] ?? substr((string)$opp['_id'], -6)) ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="font-extrabold text-base sm:text-lg text-slate-900 mt-1 break-words">
                                <?= htmlspecialchars($opp['title'] ?? 'Technical Training Delivery') ?>
                            </h3>
                            <p class="text-xs text-slate-500 font-medium break-words">
                                <span><i class="material-symbols-outlined text-[13px] align-middle text-slate-400">location_on</i> <?= htmlspecialchars($asg['location'] ?? ($opp['city'] ?? 'Location TBA')) ?></span> • 
                                <span>Duration: <strong><?= htmlspecialchars($asg['durationDays'] ?? ($opp['durationDays'] ?? 5)) ?> Working Days</strong></span> • 
                                <span>Starts <strong><?= formatDate($asg['startDate'] ?? ($opp['startDate'] ?? null)) ?></strong></span>
                                <?php if (!empty($asg['endDate'])): ?>
                                    • <span>Ends <strong><?= formatDate($asg['endDate']) ?></strong></span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="text-left sm:text-right shrink-0 bg-slate-50 border border-slate-100 p-3 rounded-2xl">
                            <span class="text-[10px] text-slate-400 uppercase font-bold block">Total Agreed Payout</span>
                            <p class="font-black text-lg sm:text-xl text-emerald-700"><?= formatINR($asg['agreedTotalFee'] ?? 0) ?></p>
                            <span class="text-[10px] text-slate-500 font-medium"><?= formatINR($asg['agreedDailyRate'] ?? 0) ?>/day</span>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-3.5 text-xs">
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Campus Accommodation</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($asg['accommodationDetails'] ?? 'Executive Guest House Reserved') ?></p>
                        </div>
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Travel Itinerary & PNR</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($asg['travelDetails'] ?? 'Flight / Train Tickets Arranged by Mentry') ?></p>
                        </div>
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100 flex flex-col justify-between">
                            <div>
                                <span class="text-slate-400 block font-bold uppercase text-[10px]">Curriculum Spec</span>
                                <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($opp['domain'] ?? 'Technical Workshop') ?></p>
                            </div>
                            <?php if ($opp): ?>
                                <a href="/trainer/opportunities.php?id=<?= (string)$opp['_id'] ?>" class="text-[11px] text-blue-600 font-bold hover:underline mt-2 inline-flex items-center gap-1">
                                    View Program Scope <span class="material-symbols-outlined text-[13px]">arrow_forward</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Post-Delivery Feedback Section for Completed Assignments -->
                    <?php if ($asgStatus === 'COMPLETED'): ?>
                        <?php if ($hasTrainerFeedback): ?>
                            <div class="p-4 rounded-2xl bg-emerald-50/80 border border-emerald-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-2xs">
                                        <span class="material-symbols-outlined text-xl">reviews</span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-xs text-slate-900">Your Campus Feedback Submitted</h4>
                                            <span class="text-amber-500 font-black text-xs flex items-center gap-0.5">
                                                <span class="material-symbols-outlined text-sm fill text-amber-500">star</span>
                                                <?= htmlspecialchars($asg['trainerFeedback']['rating'] ?? 5) ?> / 5.0
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 mt-0.5 italic">"<?= htmlspecialchars($asg['trainerFeedback']['comments'] ?? 'All modules delivered smoothly.') ?>"</p>
                                    </div>
                                </div>
                                <span class="text-[10px] font-bold text-emerald-800 bg-white px-3 py-1 rounded-xl border border-emerald-200 shrink-0 shadow-2xs">
                                    ✓ Submitted <?= formatDate($asg['trainerFeedback']['submittedAt'] ?? null) ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="p-4 rounded-2xl bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-2xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                                        <span class="material-symbols-outlined text-xl">rate_review</span>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-xs text-slate-900">Training Delivered • Feedback Required</h4>
                                        <p class="text-[11px] text-slate-600">Please provide your feedback on student engagement and college facilities to conclude this assignment.</p>
                                    </div>
                                </div>
                                <button onclick="openTrainerFeedbackModal('<?= $asgId ?>', '<?= htmlspecialchars(addslashes($opp['title'] ?? 'Training Delivery')) ?>')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-xs shrink-0 flex items-center gap-1.5 cursor-pointer">
                                    <span class="material-symbols-outlined text-sm">edit_note</span>
                                    Submit Feedback
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if ($hasVendorFeedback): ?>
                            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-3 text-xs">
                                <div class="flex items-center gap-2.5">
                                    <span class="material-symbols-outlined text-amber-500 text-xl">verified</span>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] uppercase font-bold text-slate-500">Institution Evaluation Received</span>
                                            <span class="font-extrabold text-amber-600 text-xs flex items-center">
                                                ★ <?= htmlspecialchars($asg['vendorFeedback']['rating'] ?? 5) ?> / 5.0
                                            </span>
                                        </div>
                                        <?php if (!empty($asg['vendorFeedback']['comments'])): ?>
                                            <p class="text-[11px] text-slate-600 italic mt-0.5">"<?= htmlspecialchars($asg['vendorFeedback']['comments']) ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="text-[10px] font-bold text-slate-500 bg-white px-2 py-0.5 rounded-lg border border-slate-200 shrink-0">
                                    Evaluated by Client
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ================= MODAL: TRAINER FEEDBACK ================= -->
<div id="trainerFeedbackModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-5">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h3 class="font-black text-base text-slate-900">Post-Training Campus Feedback</h3>
                <p id="tfModalTitle" class="text-xs text-slate-500 mt-0.5 truncate">Academic Delivery</p>
            </div>
            <button onclick="closeTrainerFeedbackModal()" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="/actions/submit-training-feedback.php" method="POST" class="space-y-4">
            <input type="hidden" name="assignmentId" id="tfAssignmentId" value="">
            <input type="hidden" name="feedbackType" value="TRAINER">
            <input type="hidden" name="redirectUrl" value="/trainer/assignments.php">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Overall Campus Experience Rating (1 to 5 Stars)</label>
                <div class="flex items-center gap-3">
                    <select name="rating" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-slate-800 outline-none">
                        <option value="5.0">5.0 - Outstanding Experience</option>
                        <option value="4.5">4.5 - Very Good</option>
                        <option value="4.0">4.0 - Good / Satisfactory</option>
                        <option value="3.5">3.5 - Average</option>
                        <option value="3.0">3.0 - Needs Improvement</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Student Engagement (1-5)</label>
                    <select name="studentEngagement" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-xs outline-none">
                        <option value="5">5 - Highly Engaged</option>
                        <option value="4">4 - Active Participants</option>
                        <option value="3">3 - Moderate</option>
                        <option value="2">2 - Low Engagement</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Labs & Infrastructure (1-5)</label>
                    <select name="infrastructureRating" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-xs outline-none">
                        <option value="5">5 - Well Equipped</option>
                        <option value="4">4 - Good Amenities</option>
                        <option value="3">3 - Adequate</option>
                        <option value="2">2 - Needs Upgrades</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Session Remarks & Outcome Observations</label>
                <textarea name="comments" rows="3" required placeholder="Share brief remarks on curriculum coverage, student performance, lab readiness, or guest house support..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-800 outline-none focus:border-blue-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeTrainerFeedbackModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-xs cursor-pointer flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">send</span>
                    Submit Feedback
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openTrainerFeedbackModal(asgId, title) {
    document.getElementById('tfAssignmentId').value = asgId;
    document.getElementById('tfModalTitle').textContent = title || 'Academic Training Delivery';
    document.getElementById('trainerFeedbackModal').classList.remove('hidden');
}

function closeTrainerFeedbackModal() {
    document.getElementById('trainerFeedbackModal').classList.add('hidden');
}
</script>

</main>
</div>
</body>
</html>
