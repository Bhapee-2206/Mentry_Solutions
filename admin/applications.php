<?php
// admin/applications.php - Application Pipeline
$pageTitle = "Applications Pipeline";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$appCol = getCollection("Application");
$oppCol = getCollection("Opportunity");
$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");

$statusFilter = $_GET['status'] ?? 'ALL';
$filter = [];
if ($statusFilter !== 'ALL') {
    $filter['status'] = $statusFilter;
}

$applications = $appCol ? $appCol->find($filter, ['sort' => ['appliedAt' => -1]])->toArray() : [];

$totalApps = $appCol ? $appCol->countDocuments() : 0;
$pendingApps = $appCol ? $appCol->countDocuments(['status' => 'PENDING']) : 0;
$shortlistedApps = $appCol ? $appCol->countDocuments(['status' => 'SHORTLISTED']) : 0;
$acceptedApps = $appCol ? $appCol->countDocuments(['status' => 'ACCEPTED']) : 0;
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Trainer Applications Pipeline</h1>
        <p class="text-xs md:text-sm text-slate-500 mt-0.5">Review trainer applications, inspect resumes, shortlist candidates, and confirm assignments.</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-slate-400">Total Applications</p>
            <p class="text-2xl font-black text-slate-900 mt-1"><?= $totalApps ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-amber-600">Pending Review</p>
            <p class="text-2xl font-black text-amber-600 mt-1"><?= $pendingApps ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-blue-600">Shortlisted</p>
            <p class="text-2xl font-black text-blue-600 mt-1"><?= $shortlistedApps ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-emerald-600">Accepted & Assigned</p>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?= $acceptedApps ?></p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card flex items-center gap-2">
        <?php
        $tabs = [
            'ALL' => 'All Applications',
            'PENDING' => 'Pending',
            'SHORTLISTED' => 'Shortlisted',
            'ACCEPTED' => 'Accepted',
            'REJECTED' => 'Rejected'
        ];
        foreach ($tabs as $k => $v): ?>
            <a href="/admin/applications.php?status=<?= $k ?>" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all <?= $statusFilter === $k ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
                <?= $v ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="bg-white border border-slate-200/90 rounded-3xl shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 uppercase tracking-wider font-bold">
                        <th class="py-4 px-5">Candidate</th>
                        <th class="py-4 px-4">Opportunity</th>
                        <th class="py-4 px-4">Proposed Rate</th>
                        <th class="py-4 px-4">Match Score</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4">Applied Date</th>
                        <th class="py-4 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="7" class="p-8 text-center text-slate-400">No applications found in this status category.</td></tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): 
                            $appId = (string)$app['_id'];
                            $t = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$app['trainerId'])]) : null;
                            $u = ($t && $userCol && !empty($t['userId'])) ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$t['userId'])]) : null;
                            $op = $oppCol ? $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$app['opportunityId'])]) : null;
                        ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-5">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= htmlspecialchars($u['avatar'] ?? "https://avatar.vercel.sh/" . urlencode($u['name'] ?? 'T') . ".png") ?>" class="w-10 h-10 rounded-2xl object-cover border border-slate-200">
                                        <div>
                                            <a href="/admin/trainer-view.php?id=<?= (string)$t['_id'] ?>" class="font-bold text-slate-900 hover:text-blue-600 block">
                                                <?= htmlspecialchars($u['name'] ?? 'Trainer') ?>
                                            </a>
                                            <span class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($t['professionalTitle'] ?? 'Faculty') ?> • <?= htmlspecialchars($t['currentCity'] ?? 'India') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <a href="/admin/opportunity-view.php?id=<?= (string)$app['opportunityId'] ?>" class="font-semibold text-slate-800 hover:text-blue-600 block">
                                        <?= htmlspecialchars($op['title'] ?? 'Training Opportunity') ?>
                                    </a>
                                    <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($op['city'] ?? '') ?>, <?= htmlspecialchars($op['mode'] ?? 'OFFLINE') ?></span>
                                </td>
                                <td class="py-4 px-4 font-black text-blue-700"><?= formatINR($app['proposedDailyRate'] ?? 0) ?>/day</td>
                                <td class="py-4 px-4">
                                    <span class="bg-emerald-50 text-emerald-700 font-extrabold text-[11px] px-2 py-0.5 rounded border border-emerald-200">
                                        <?= htmlspecialchars($app['matchScore'] ?? 92) ?>% Match
                                    </span>
                                </td>
                                <td class="py-4 px-4"><?= getStatusBadge($app['status'] ?? 'PENDING') ?></td>
                                <td class="py-4 px-4 text-slate-500"><?= formatDate($app['appliedAt'] ?? null) ?></td>
                                <td class="py-4 px-5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="/admin/trainer-view.php?id=<?= (string)$t['_id'] ?>" class="p-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors" title="View Resume & CV">
                                            <span class="material-symbols-outlined text-[17px]">description</span>
                                        </a>

                                        <?php if (($app['status'] ?? 'PENDING') !== 'ACCEPTED'): ?>
                                            <form action="/actions/update-application.php" method="POST" class="inline">
                                                <input type="hidden" name="applicationId" value="<?= $appId ?>">
                                                <input type="hidden" name="status" value="ACCEPTED">
                                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] px-3 py-1.5 rounded-xl shadow-xs" title="Accept & Assign to Job">
                                                    Accept
                                                </button>
                                            </form>

                                            <?php if (($app['status'] ?? 'PENDING') !== 'SHORTLISTED'): ?>
                                                <form action="/actions/update-application.php" method="POST" class="inline">
                                                    <input type="hidden" name="applicationId" value="<?= $appId ?>">
                                                    <input type="hidden" name="status" value="SHORTLISTED">
                                                    <button type="submit" class="bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 font-bold text-[11px] px-2.5 py-1.5 rounded-xl" title="Shortlist Candidate">
                                                        Shortlist
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form action="/actions/update-application.php" method="POST" class="inline">
                                                <input type="hidden" name="applicationId" value="<?= $appId ?>">
                                                <input type="hidden" name="status" value="REJECTED">
                                                <button type="submit" class="text-rose-600 hover:bg-rose-50 border border-rose-200 font-bold text-[11px] px-2 py-1.5 rounded-xl" title="Reject Candidate">
                                                    Reject
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-emerald-700 font-bold text-xs">Assigned ✓</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</main>
</div>
</body>
</html>
