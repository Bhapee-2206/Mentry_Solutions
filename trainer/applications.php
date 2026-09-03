<?php
// trainer/applications.php
$pageTitle = "My Applications";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$applicationCol = getCollection("Application");
$opportunityCol = getCollection("Opportunity");
$trainerCol = getCollection("Trainer");

$trainer = $trainerCol ? $trainerCol->findOne(['userId' => $user['id']]) : null;
$trainerId = $trainer ? (string)$trainer['_id'] : '';

$applications = $applicationCol ? $applicationCol->find(
    ['trainerId' => $trainerId],
    ['sort' => ['appliedAt' => -1]]
)->toArray() : [];
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">My Opportunity Applications</h1>
        <p class="text-xs text-slate-500 mt-0.5">Track review status, shortlisting, and final college selection.</p>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card overflow-hidden">
        <?php if (empty($applications)): ?>
            <div class="p-12 text-center text-xs text-slate-400">
                You have not submitted any applications yet. Browse the Opportunities tab to apply.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
                            <th class="py-4 px-5">Opportunity</th>
                            <th class="py-4 px-4">Applied Date</th>
                            <th class="py-4 px-4">Proposed Daily Rate</th>
                            <th class="py-4 px-4">Match Score</th>
                            <th class="py-4 px-4">Status</th>
                            <th class="py-4 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($applications as $app): 
                            $opp = null;
                            if ($opportunityCol && !empty($app['opportunityId'])) {
                                try {
                                    $opp = $opportunityCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$app['opportunityId'])]);
                                } catch (Exception $e) {}
                            }
                        ?>
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-4 px-5 font-bold text-slate-900">
                                    <?= htmlspecialchars($opp['title'] ?? 'Training Opportunity') ?>
                                    <span class="text-[10px] text-slate-400 block font-normal"><?= htmlspecialchars($opp['city'] ?? 'Location TBA') ?></span>
                                </td>
                                <td class="py-4 px-4 text-slate-500"><?= formatDate($app['appliedAt'] ?? null) ?></td>
                                <td class="py-4 px-4 font-bold text-blue-700"><?= formatINR($app['proposedDailyRate'] ?? 0) ?>/day</td>
                                <td class="py-4 px-4 font-semibold text-slate-700"><?= htmlspecialchars($app['matchScore'] ?? 90) ?>%</td>
                                <td class="py-4 px-4"><?= getStatusBadge($app['status'] ?? 'PENDING') ?></td>
                                <td class="py-4 px-5 text-right">
                                    <?php if ($opp): ?>
                                        <a href="/opportunity-details.php?id=<?= (string)$opp['_id'] ?>" class="text-blue-600 font-bold hover:underline">View Spec</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
