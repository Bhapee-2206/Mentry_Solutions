<?php
// admin/requirements.php - College Intake Requirements
$pageTitle = "College Intake Requirements";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$reqCol = getCollection("CollegeRequirement");
$requirements = $reqCol ? $reqCol->find([], ['sort' => ['createdAt' => -1]])->toArray() : [];
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">College Intake Requirements</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-0.5">Review private syllabus submissions from engineering colleges, tune trainer payout rates, and convert to live opportunities.</p>
        </div>
    </div>

    <!-- Private Notice -->
    <div class="bg-slate-900 text-white p-4 rounded-2xl flex items-center justify-between gap-4 shadow-sm text-xs">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-amber-400 text-xl">lock</span>
            <p>
                <strong>Private Intake Policy:</strong> Inbound requirements submitted by colleges on the public website are private and hidden until an administrator approves and configures the live trainer honorarium.
            </p>
        </div>
    </div>

    <div class="space-y-4">
        <?php if (empty($requirements)): ?>
            <div class="bg-white p-12 rounded-3xl border border-slate-200/90 shadow-card text-center text-xs text-slate-400">
                No inbound college requirements recorded yet. Submissions from the public "/submit-requirement.php" page will appear here.
            </div>
        <?php else: ?>
            <?php foreach ($requirements as $rq): 
                $rqId = (string)$rq['_id'];
                $isConverted = ($rq['status'] ?? '') === 'CONVERTED';
                $collegeBudget = (float)($rq['budgetPerDay'] ?? 6000);
                $recMin = max(4000, round($collegeBudget * 0.75 / 500) * 500);
                $recMax = max(5000, $collegeBudget);
            ?>
                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-[10px] font-bold text-[#FE5E04] bg-orange-50 border border-orange-200 px-2 py-0.5 rounded whitespace-nowrap shrink-0 inline-flex items-center">
                                    <?= htmlspecialchars(getMentryCode('REQUIREMENT', $rq)) ?>
                                </span>
                                <span class="text-[10px] font-bold uppercase text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200"><?= htmlspecialchars($rq['mode'] ?? 'OFFLINE') ?></span>
                            </div>
                            <h3 class="font-bold text-base text-slate-900 mt-1"><?= htmlspecialchars($rq['institutionName']) ?></h3>
                            <p class="text-xs text-slate-500">Contact: <?= htmlspecialchars($rq['contactPerson']) ?> • <?= htmlspecialchars($rq['email']) ?> • <?= htmlspecialchars($rq['phone']) ?></p>
                        </div>
                        <div class="flex items-center gap-3">
                            <?= getStatusBadge($rq['status'] ?? 'PENDING') ?>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-4 gap-4 text-xs">
                        <div class="bg-slate-50 p-3 rounded-xl">
                            <span class="text-slate-400 block font-semibold">Training Domain</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($rq['trainingDomain']) ?></p>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl">
                            <span class="text-slate-400 block font-semibold">Campus Location</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($rq['city']) ?>, <?= htmlspecialchars($rq['state'] ?? 'India') ?></p>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl">
                            <span class="text-slate-400 block font-semibold">Duration & Start</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($rq['durationDays'] ?? 5) ?> Days • <?= formatDate($rq['tentativeStartDate'] ?? null) ?></p>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl">
                            <span class="text-slate-400 block font-semibold">College Budget</span>
                            <p class="font-black text-indigo-700 mt-1"><?= formatINR($collegeBudget) ?>/day</p>
                        </div>
                    </div>

                    <?php if (!empty($rq['notes'])): ?>
                        <div class="p-3.5 bg-slate-50 rounded-xl text-xs text-slate-600">
                            <strong>Syllabus / Batch Notes:</strong> <?= htmlspecialchars($rq['notes']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isConverted): ?>
                        <!-- Price Adjustment & Convert Form -->
                        <form action="/actions/convert-requirement.php" method="POST" class="bg-gradient-to-r from-blue-50/60 to-slate-50 p-4 rounded-2xl border border-blue-200/80 space-y-3">
                            <input type="hidden" name="requirementId" value="<?= $rqId ?>">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-xs text-blue-950 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-blue-600">tune</span>
                                    Adjust Trainer Honorarium Payout & Publish to Opportunities
                                </h4>
                                <span class="text-[10px] text-slate-500">College Budget: <?= formatINR($collegeBudget) ?>/day</span>
                            </div>

                            <div class="grid sm:grid-cols-4 gap-3 text-xs">
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Live Opportunity Title</label>
                                    <input type="text" name="title" value="<?= htmlspecialchars($rq['durationDays']) ?>-Day <?= htmlspecialchars($rq['trainingDomain']) ?> Workshop for <?= htmlspecialchars($rq['institutionName']) ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-slate-800 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Trainer Min Rate (₹/day)</label>
                                    <input type="number" name="dailyRateMin" value="<?= $recMin ?>" class="w-full bg-white border border-blue-300 rounded-xl p-2 text-xs font-black text-blue-700 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Trainer Max Rate (₹/day)</label>
                                    <input type="number" name="dailyRateMax" value="<?= $recMax ?>" class="w-full bg-white border border-blue-300 rounded-xl p-2 text-xs font-black text-blue-700 outline-none">
                                </div>
                            </div>

                            <div class="flex justify-end pt-1">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-xs transition-colors flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px]">publish</span>
                                    Approve & Post to Live Opportunities
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
                            <span class="text-emerald-700 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                Approved & Published to Live Network
                            </span>
                            <?php if (!empty($rq['convertedOpportunityId'])): ?>
                                <a href="/admin/opportunity-view.php?id=<?= (string)$rq['convertedOpportunityId'] ?>" class="text-blue-600 font-bold hover:underline">
                                    View Live Opportunity & Match Faculty →
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
