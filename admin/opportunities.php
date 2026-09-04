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

$conditions = [];
if ($statusFilter !== 'ALL') {
    $conditions[] = ['status' => $statusFilter];
}
if ($domainFilter !== 'ALL') {
    $domainRegex = new MongoDB\BSON\Regex($domainFilter, 'i');
    $conditions[] = [
        '$or' => [
            ['domain' => $domainRegex],
            ['title' => $domainRegex]
        ]
    ];
}
if (!empty($search)) {
    $orSearch = [
        ['title' => new MongoDB\BSON\Regex($search, 'i')],
        ['city' => new MongoDB\BSON\Regex($search, 'i')],
        ['jobId' => new MongoDB\BSON\Regex($search, 'i')],
        ['mentryId' => new MongoDB\BSON\Regex($search, 'i')],
        ['collegeName' => new MongoDB\BSON\Regex($search, 'i')],
        ['domain' => new MongoDB\BSON\Regex($search, 'i')]
    ];
    if (preg_match('/^[a-f0-9]{24}$/i', $search)) {
        try {
            $orSearch[] = ['_id' => new MongoDB\BSON\ObjectId($search)];
        } catch (\Throwable $e) {}
    }
    $conditions[] = ['$or' => $orSearch];
}

$filter = !empty($conditions) ? (count($conditions) === 1 ? $conditions[0] : ['$and' => $conditions]) : [];

$opportunities = $oppCol ? $oppCol->find($filter, ['sort' => ['_id' => -1]])->toArray() : [];

// If specific ID search produced 0 results due to active status/domain filters, search globally by ID
if (empty($opportunities) && !empty($search) && $oppCol) {
    $fallbackOr = [
        ['jobId' => new MongoDB\BSON\Regex($search, 'i')],
        ['mentryId' => new MongoDB\BSON\Regex($search, 'i')]
    ];
    if (preg_match('/^[a-f0-9]{24}$/i', $search)) {
        try {
            $fallbackOr[] = ['_id' => new MongoDB\BSON\ObjectId($search)];
        } catch (\Throwable $e) {}
    }
    $opportunities = $oppCol->find(['$or' => $fallbackOr], ['sort' => ['_id' => -1]])->toArray();
}

$totalCount = $oppCol ? $oppCol->countDocuments() : 0;
$publishedCount = $oppCol ? $oppCol->countDocuments(['status' => 'PUBLISHED']) : 0;
$matchedCount = $oppCol ? $oppCol->countDocuments(['status' => 'MATCHED']) : 0;
$inProgressCount = $oppCol ? $oppCol->countDocuments(['status' => 'IN_PROGRESS']) : 0;
$completedCount = $oppCol ? $oppCol->countDocuments(['status' => 'COMPLETED']) : 0;
$draftCount = $oppCol ? $oppCol->countDocuments(['status' => 'DRAFT']) : 0;

$hasActiveFilters = ($statusFilter !== 'ALL' || $domainFilter !== 'ALL' || !empty($search));
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">College Opportunities</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">Create, edit, match trainers, and manage campus training engagements.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 self-start">
            <?php if ($hasActiveFilters): ?>
                <a href="/admin/opportunities.php" class="bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 font-bold text-xs px-3.5 py-2 rounded-xl transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    Clear Filters
                </a>
            <?php endif; ?>
            <a href="/admin/opportunity-create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base">add</span>
                Create New Opportunity
            </a>
        </div>
    </div>

    <!-- Quick Stats Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="/admin/opportunities.php" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-slate-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-slate-400">Total Openings</p>
            <p class="text-2xl font-black text-slate-900 mt-1"><?= $totalCount ?></p>
        </a>
        <a href="/admin/opportunities.php?status=PUBLISHED" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-blue-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-blue-600">Published & Open</p>
            <p class="text-2xl font-black text-blue-600 mt-1"><?= $publishedCount ?></p>
        </a>
        <a href="/admin/opportunities.php?status=MATCHED" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-emerald-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-emerald-600">Trainer Matched</p>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?= $matchedCount ?></p>
        </a>
        <a href="/admin/opportunities.php?status=COMPLETED" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-purple-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-purple-600">Completed</p>
            <p class="text-2xl font-black text-purple-600 mt-1"><?= $completedCount ?></p>
        </a>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
            <?php
            $statuses = [
                'ALL' => 'All (' . $totalCount . ')',
                'PUBLISHED' => 'Published (' . $publishedCount . ')',
                'MATCHED' => 'Matched (' . $matchedCount . ')',
                'IN_PROGRESS' => 'In Progress (' . $inProgressCount . ')',
                'COMPLETED' => 'Completed (' . $completedCount . ')',
                'DRAFT' => 'Draft (' . $draftCount . ')'
            ];
            foreach ($statuses as $k => $v): ?>
                <a href="/admin/opportunities.php?status=<?= $k ?>&domain=<?= urlencode($domainFilter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $statusFilter === $k ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
                    <?= $v ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" action="/admin/opportunities.php" class="relative w-full md:w-72">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="domain" value="<?= htmlspecialchars($domainFilter) ?>">
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
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="font-mono text-[10px] font-bold text-[#FE5E04] bg-orange-50 border border-orange-200 px-2 py-0.5 rounded-md whitespace-nowrap shrink-0 inline-flex items-center">
                                            <?= htmlspecialchars(getMentryCode('OPPORTUNITY', $op)) ?>
                                        </span>
                                        <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md font-semibold"><?= htmlspecialchars($op['domain'] ?? 'General') ?></span>
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
