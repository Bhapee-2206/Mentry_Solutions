<?php
// admin/opportunities.php - Opportunity Manager
$pageTitle = "Opportunity Manager";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$oppCol = getCollection("Opportunity");
$appCol = getCollection("Application");

$statusFilter = $_GET['status'] ?? 'ALL';
$domainFilter = $_GET['domain'] ?? 'ALL';
$search = trim($_GET['search'] ?? '');

$filter = [];
if ($statusFilter !== 'ALL') {
    $filter['status'] = $statusFilter;
}
if ($domainFilter !== 'ALL') {
    $filter['domain'] = $domainFilter;
}
if (!empty($search)) {
    $filter['$or'] = [
        ['title' => new MongoDB\BSON\Regex($search, 'i')],
        ['city' => new MongoDB\BSON\Regex($search, 'i')],
        ['jobId' => new MongoDB\BSON\Regex($search, 'i')],
        ['collegeName' => new MongoDB\BSON\Regex($search, 'i')]
    ];
}

$opportunities = $oppCol ? $oppCol->find($filter, ['sort' => ['createdAt' => -1]])->toArray() : [];

$totalCount = $oppCol ? $oppCol->countDocuments() : 0;
$publishedCount = $oppCol ? $oppCol->countDocuments(['status' => 'PUBLISHED']) : 0;
$matchedCount = $oppCol ? $oppCol->countDocuments(['status' => 'MATCHED']) : 0;
$completedCount = $oppCol ? $oppCol->countDocuments(['status' => 'COMPLETED']) : 0;
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">College Opportunities</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">Create, edit, match trainers, and manage campus training engagements.</p>
        </div>

        <a href="/admin/opportunity-create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-1.5 self-start">
            <span class="material-symbols-outlined text-base">add</span>
            Create New Opportunity
        </a>
    </div>

    <!-- Quick Stats Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-slate-400">Total Openings</p>
            <p class="text-2xl font-black text-slate-900 mt-1"><?= $totalCount ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-blue-600">Published & Open</p>
            <p class="text-2xl font-black text-blue-600 mt-1"><?= $publishedCount ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-emerald-600">Trainer Matched</p>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?= $matchedCount ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-purple-600">Completed</p>
            <p class="text-2xl font-black text-purple-600 mt-1"><?= $completedCount ?></p>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
            <?php
            $statuses = [
                'ALL' => 'All Statuses',
                'PUBLISHED' => 'Published',
                'MATCHED' => 'Matched',
                'IN_PROGRESS' => 'In Progress',
                'COMPLETED' => 'Completed',
                'DRAFT' => 'Draft'
            ];
            foreach ($statuses as $k => $v): ?>
                <a href="/admin/opportunities.php?status=<?= $k ?>&domain=<?= urlencode($domainFilter) ?>" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all <?= $statusFilter === $k ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
                    <?= $v ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" action="/admin/opportunities.php" class="relative w-full md:w-72">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search title, city, job ID..." class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20 text-slate-900">
        </form>
    </div>

    <!-- Opportunities Table -->
    <div class="bg-white border border-slate-200/90 rounded-3xl shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 uppercase tracking-wider font-bold">
                        <th class="py-4 px-5">Job Details</th>
                        <th class="py-4 px-4">Location & Mode</th>
                        <th class="py-4 px-4">Start Date</th>
                        <th class="py-4 px-4">Duration</th>
                        <th class="py-4 px-4">Daily Rate</th>
                        <th class="py-4 px-4">Applicants</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($opportunities)): ?>
                        <tr><td colspan="8" class="p-8 text-center text-slate-400">No opportunities found matching your criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($opportunities as $op): 
                            $opId = (string)$op['_id'];
                            $applicantCount = $appCol ? $appCol->countDocuments(['opportunityId' => $opId]) : 0;
                        ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-5">
                                    <a href="/admin/opportunity-view.php?id=<?= $opId ?>" class="font-bold text-slate-900 hover:text-blue-600 block">
                                        <?= htmlspecialchars($op['title']) ?>
                                    </a>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[10px] text-slate-400 font-mono">ID: <?= htmlspecialchars($op['jobId'] ?? $opId) ?></span>
                                        <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.2 rounded font-semibold"><?= htmlspecialchars($op['domain'] ?? 'General') ?></span>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-slate-600">
                                    <span class="font-semibold text-slate-800 block"><?= htmlspecialchars($op['city']) ?>, <?= htmlspecialchars($op['state']) ?></span>
                                    <span class="text-[10px] text-blue-600 font-bold uppercase"><?= htmlspecialchars($op['mode'] ?? 'OFFLINE') ?></span>
                                </td>
                                <td class="py-4 px-4 text-slate-600"><?= formatDate($op['startDate'] ?? null) ?></td>
                                <td class="py-4 px-4 text-slate-600 font-medium"><?= htmlspecialchars($op['durationDays'] ?? 5) ?> Days</td>
                                <td class="py-4 px-4 font-bold text-blue-700"><?= formatINR($op['dailyRateMin'] ?? 0) ?> - <?= formatINR($op['dailyRateMax'] ?? 0) ?></td>
                                <td class="py-4 px-4">
                                    <a href="/admin/opportunity-view.php?id=<?= $opId ?>" class="inline-flex items-center gap-1 font-bold text-xs <?= $applicantCount > 0 ? 'text-blue-600 hover:underline' : 'text-slate-400' ?>">
                                        <span class="material-symbols-outlined text-[15px]">person</span>
                                        <?= $applicantCount ?>
                                    </a>
                                </td>
                                <td class="py-4 px-4"><?= getStatusBadge($op['status'] ?? 'PUBLISHED') ?></td>
                                <td class="py-4 px-5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="/admin/opportunity-view.php?id=<?= $opId ?>" class="p-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors" title="View & Match">
                                            <span class="material-symbols-outlined text-[18px]">visibility</span>
                                        </a>
                                        <a href="/admin/opportunity-edit.php?id=<?= $opId ?>" class="p-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors" title="Edit Opportunity">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </a>
                                        <form action="/actions/delete-opportunity.php" method="POST" class="inline" onsubmit="return confirm('Delete opportunity \'<?= htmlspecialchars(addslashes($op['title'])) ?>\'?');">
                                            <input type="hidden" name="id" value="<?= $opId ?>">
                                            <button type="submit" class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 transition-colors" title="Delete">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        </form>
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
