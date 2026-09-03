<?php
// trainer/assignments.php
$pageTitle = "My Confirmed Assignments";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$assignmentCol = getCollection("Assignment");
$trainerCol = getCollection("Trainer");

$trainer = $trainerCol ? $trainerCol->findOne(['userId' => $user['id']]) : null;
$trainerId = $trainer ? (string)$trainer['_id'] : '';

$assignments = $assignmentCol ? $assignmentCol->find(
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
            <?php foreach ($assignments as $asg): ?>
                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-slate-100">
                        <div>
                            <span class="text-[10px] font-bold uppercase text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Confirmed Assignment</span>
                            <h3 class="font-bold text-base text-slate-900 mt-1">Assignment #<?= substr((string)$asg['_id'], -6) ?></h3>
                        </div>
                        <div class="text-left sm:text-right">
                            <span class="text-[10px] text-slate-400 uppercase font-bold block">Total Agreed Payout</span>
                            <p class="font-black text-lg text-emerald-700"><?= formatINR($asg['agreedTotalFee'] ?? 0) ?></p>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4 text-xs">
                        <div class="bg-slate-50 p-3.5 rounded-xl">
                            <span class="text-slate-400 block font-semibold">Accommodation Details</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($asg['accommodationDetails'] ?? 'Campus Guest House Reserved') ?></p>
                        </div>
                        <div class="bg-slate-50 p-3.5 rounded-xl">
                            <span class="text-slate-400 block font-semibold">Travel Itinerary</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($asg['travelDetails'] ?? 'Flight / Train Tickets Arranged by Mentry') ?></p>
                        </div>
                        <div class="bg-slate-50 p-3.5 rounded-xl">
                            <span class="text-slate-400 block font-semibold">Delivery Status</span>
                            <div class="mt-1"><?= getStatusBadge($asg['status'] ?? 'SCHEDULED') ?></div>
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
