<?php
// vendor/assignments.php - Live Campus Deliveries & Post-Training Performance Evaluations
$pageTitle = "Campus Training Deliveries";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

syncAssignmentStatuses();

$vendorId = (string)($user['id'] ?? '');
$vendorEmail = (string)($user['email'] ?? '');
$orgName = (string)($user['organizationName'] ?? '');

$reqCol = getCollection("VendorRequest");
$asgCol = getCollection("Assignment");
$oppCol = getCollection("Opportunity");
$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");

$vendorQuery = [
    '$or' => array_values(array_filter([
        !empty($vendorId) ? ['vendorId' => $vendorId] : null,
        !empty($vendorEmail) ? ['vendorContactEmail' => $vendorEmail] : null,
        !empty($orgName) ? ['vendorName' => $orgName] : null,
        !empty($orgName) ? ['institutionName' => $orgName] : null
    ]))
];

$vendorReqs = $reqCol ? $reqCol->find($vendorQuery)->toArray() : [];
$opportunityIds = [];

foreach ($vendorReqs as $vr) {
    if (!empty($vr['convertedOpportunityId'])) {
        $opportunityIds[] = (string)$vr['convertedOpportunityId'];
    }
}

// Also find any Opportunity created directly with vendorId, vendorRequestId, or collegeName
if ($oppCol) {
    $directOpps = $oppCol->find([
        '$or' => array_values(array_filter([
            !empty($vendorId) ? ['vendorId' => $vendorId] : null,
            !empty($orgName) ? ['collegeName' => $orgName] : null
        ]))
    ])->toArray();
    foreach ($directOpps as $dOpp) {
        $opportunityIds[] = (string)$dOpp['_id'];
    }
}

$opportunityIds = array_values(array_unique(array_filter($opportunityIds)));

$assignments = [];
if (!empty($opportunityIds) && $asgCol) {
    $assignments = $asgCol->find(['opportunityId' => ['$in' => $opportunityIds]], ['sort' => ['createdAt' => -1]])->toArray();
}
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
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Confirmed Campus Deliveries</h1>
        <p class="text-xs md:text-sm text-slate-500 mt-1">Track faculty deployment, schedule timelines, and evaluate trainer performance for your institution.</p>
    </div>

    <div class="space-y-4">
        <?php if (empty($assignments)): ?>
            <div class="bg-white p-12 rounded-3xl border border-slate-200/90 shadow-card text-center text-xs text-slate-400">
                No live assignments confirmed yet. Once your submitted requirements are approved and matched with faculty, live delivery itineraries will appear here.
            </div>
        <?php else: ?>
            <?php foreach ($assignments as $asg): 
                $asgId = (string)$asg['_id'];
                $opp = null;
                if (!empty($asg['opportunityId']) && $oppCol) {
                    try {
                        $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$asg['opportunityId'])]);
                    } catch (\Throwable $e) {}
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

                $asgStatus = strtoupper($asg['status'] ?? 'SCHEDULED');
                $hasVendorFeedback = !empty($asg['vendorFeedback']);
                $hasTrainerFeedback = !empty($asg['trainerFeedback']);
            ?>
                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-extrabold uppercase text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-200">Assignment #<?= substr($asgId, -6) ?></span>
                                <?= getStatusBadge($asgStatus) ?>
                            </div>
                            <h3 class="font-bold text-base text-slate-900 mt-1">
                                <?= htmlspecialchars($opp['title'] ?? 'Live Faculty Deployment') ?>
                            </h3>
                            <p class="text-xs text-slate-500">
                                Location: <?= htmlspecialchars($asg['location'] ?? ($opp['city'] ?? 'Campus')) ?> • 
                                Duration: <?= htmlspecialchars($asg['durationDays'] ?? 5) ?> Days • 
                                Starts: <?= formatDate($asg['startDate'] ?? null) ?>
                                <?php if (!empty($asg['endDate'])): ?>
                                    • Ends: <?= formatDate($asg['endDate']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4 text-xs">
                        <div class="bg-slate-50 p-4 rounded-2xl flex items-center gap-3">
                            <img src="<?= htmlspecialchars(getUserAvatar($trainerUser, 80)) ?>" 
                                 alt="<?= htmlspecialchars($trainerUser['name'] ?? 'Faculty') ?>"
                                 class="w-10 h-10 rounded-xl object-cover border border-slate-200 shrink-0" 
                                 style="object-position: center 15%;"
                                 referrerpolicy="no-referrer"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($trainerUser['name'] ?? 'Faculty') ?>&background=FE5E04&color=fff&size=80';">
                            <div class="min-w-0">
                                <span class="text-slate-400 block font-bold uppercase text-[10px]">Assigned Faculty</span>
                                <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($trainerUser['name'] ?? 'Assigned Faculty') ?></p>
                                <span class="text-[11px] text-slate-500 truncate block"><?= htmlspecialchars($trainer['professionalTitle'] ?? '') ?></span>
                            </div>
                        </div>

                        <div class="bg-slate-50 p-4 rounded-2xl">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Campus Accommodation</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($asg['accommodationDetails'] ?? 'Executive Guest House') ?></p>
                        </div>

                        <div class="bg-slate-50 p-4 rounded-2xl">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Travel Itinerary</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($asg['travelDetails'] ?? 'Arranged by Mentry') ?></p>
                        </div>
                    </div>

                    <!-- Post-Delivery Evaluation for Completed Training -->
                    <?php if ($asgStatus === 'COMPLETED'): ?>
                        <?php if ($hasVendorFeedback): ?>
                            <div class="p-4 rounded-2xl bg-emerald-50/80 border border-emerald-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-2xs">
                                        <span class="material-symbols-outlined text-xl">verified</span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-xs text-slate-900">Your Trainer Evaluation Submitted</h4>
                                            <span class="text-amber-500 font-black text-xs flex items-center gap-0.5">
                                                ★ <?= htmlspecialchars($asg['vendorFeedback']['rating'] ?? 5) ?> / 5.0 Rating
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 mt-0.5 italic">"<?= htmlspecialchars($asg['vendorFeedback']['comments'] ?? 'Excellent training quality.') ?>"</p>
                                    </div>
                                </div>
                                <span class="text-[10px] font-bold text-emerald-800 bg-white px-3 py-1 rounded-xl border border-emerald-200 shrink-0 shadow-2xs">
                                    ✓ Rating Logged
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="p-4 rounded-2xl bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-2xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-[#FE5E04] text-white flex items-center justify-center shrink-0 shadow-xs">
                                        <span class="material-symbols-outlined text-xl">star_rate</span>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-xs text-slate-900">Training Finished • Rate Faculty Performance</h4>
                                        <p class="text-[11px] text-slate-600">Please provide your rating and review on <?= htmlspecialchars($trainerUser['name'] ?? 'the faculty') ?>'s technical delivery and professionalism.</p>
                                    </div>
                                </div>
                                <button onclick="openVendorFeedbackModal('<?= $asgId ?>', '<?= htmlspecialchars(addslashes($trainerUser['name'] ?? 'Trainer')) ?>', '<?= htmlspecialchars(addslashes($opp['title'] ?? 'Training Delivery')) ?>')" class="bg-[#FE5E04] hover:bg-orange-600 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-xs shrink-0 flex items-center gap-1.5 cursor-pointer">
                                    <span class="material-symbols-outlined text-sm">rate_review</span>
                                    Rate Trainer
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ================= MODAL: VENDOR FEEDBACK & TRAINER EVALUATION ================= -->
<div id="vendorFeedbackModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-5">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h3 class="font-black text-base text-slate-900">Evaluate Trainer Performance</h3>
                <p id="vfModalSubtitle" class="text-xs text-slate-500 mt-0.5">Faculty Evaluation</p>
            </div>
            <button onclick="closeVendorFeedbackModal()" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="/actions/submit-training-feedback.php" method="POST" class="space-y-4">
            <input type="hidden" name="assignmentId" id="vfAssignmentId" value="">
            <input type="hidden" name="feedbackType" value="VENDOR">
            <input type="hidden" name="redirectUrl" value="/vendor/assignments.php">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Overall Faculty Delivery Rating (1 to 5 Stars)</label>
                <select name="rating" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-slate-800 outline-none">
                    <option value="5.0">5.0 - Exceptional Performance</option>
                    <option value="4.5">4.5 - Exceeded Expectations</option>
                    <option value="4.0">4.0 - Good & Professional</option>
                    <option value="3.5">3.5 - Satisfactory</option>
                    <option value="3.0">3.0 - Adequate</option>
                    <option value="2.0">2.0 - Needs Improvement</option>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-2.5 text-xs">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 mb-1">Expertise (1-5)</label>
                    <select name="subjectExpertise" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-xs outline-none">
                        <option value="5">5 - Deep</option>
                        <option value="4">4 - Strong</option>
                        <option value="3">3 - Good</option>
                        <option value="2">2 - Basic</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 mb-1">Punctuality (1-5)</label>
                    <select name="punctuality" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-xs outline-none">
                        <option value="5">5 - On Time</option>
                        <option value="4">4 - Good</option>
                        <option value="3">3 - Fair</option>
                        <option value="2">2 - Delayed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 mb-1">Students (1-5)</label>
                    <select name="studentSatisfaction" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-xs outline-none">
                        <option value="5">5 - Delighted</option>
                        <option value="4">4 - Happy</option>
                        <option value="3">3 - Neutral</option>
                        <option value="2">2 - Mixed</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Detailed Feedback / Testimonial</label>
                <textarea name="comments" rows="3" required placeholder="Describe trainer's presentation style, student interaction, and overall satisfaction..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-800 outline-none focus:border-orange-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeVendorFeedbackModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="bg-[#FE5E04] hover:bg-orange-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-xs cursor-pointer flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">star</span>
                    Submit Evaluation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openVendorFeedbackModal(asgId, trainerName, oppTitle) {
    document.getElementById('vfAssignmentId').value = asgId;
    document.getElementById('vfModalSubtitle').textContent = trainerName + ' • ' + oppTitle;
    document.getElementById('vendorFeedbackModal').classList.remove('hidden');
}

function closeVendorFeedbackModal() {
    document.getElementById('vendorFeedbackModal').classList.add('hidden');
}
</script>

</main>
</div>
</body>
</html>
