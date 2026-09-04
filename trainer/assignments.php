<?php
// trainer/assignments.php
$pageTitle = "My Confirmed Assignments";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

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
    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Confirmed Training Assignments</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manage live campus schedules, guest house details, and payout tracking.</p>
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
            ?>
                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pb-4 border-b border-slate-100">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[10px] font-bold uppercase text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">Assignment #<?= substr($asgId, -6) ?></span>
                                <?= getStatusBadge($asg['status'] ?? 'SCHEDULED') ?>
                                <?php if ($opp): ?>
                                    <span class="text-[10px] font-mono text-slate-400">Job ID: <?= htmlspecialchars($opp['jobId'] ?? substr((string)$opp['_id'], -6)) ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="font-extrabold text-base sm:text-lg text-slate-900 mt-1">
                                <?= htmlspecialchars($opp['title'] ?? 'Technical Training Delivery') ?>
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">
                                <span><i class="material-symbols-outlined text-[13px] align-middle text-slate-400">location_on</i> <?= htmlspecialchars($asg['location'] ?? ($opp['city'] ?? 'Location TBA')) ?></span> • 
                                <span>Duration: <strong><?= htmlspecialchars($asg['durationDays'] ?? ($opp['durationDays'] ?? 5)) ?> Working Days</strong></span> • 
                                <span>Starts <strong><?= formatDate($asg['startDate'] ?? ($opp['startDate'] ?? null)) ?></strong></span>
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
                                <a href="/opportunity-details.php?id=<?= (string)$opp['_id'] ?>" class="text-[11px] text-blue-600 font-bold hover:underline mt-2 inline-flex items-center gap-1">
                                    View Program Scope <span class="material-symbols-outlined text-[13px]">arrow_forward</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
