<?php
// admin/opportunities.php - Opportunity Manager with Authoritative Lifecycle & Matching Separation
$pageTitle = "Opportunity Manager";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
checkOpportunityScheduleMilestones();
require_once __DIR__ . '/includes/sidebar.php';

$oppCol = getCollection("Opportunity");
$appCol = getCollection("Application");

$statusFilter = strtoupper(trim($_GET['status'] ?? 'ALL'));
$matchingFilter = strtoupper(trim($_GET['matching'] ?? 'ALL'));
$domainFilter = trim($_GET['domain'] ?? 'ALL');
$search = trim($_GET['search'] ?? '');

// Fetch all opportunities to compute accurate lifecycle stats and separate matching filters
$allRawOpportunities = $oppCol ? $oppCol->find([], ['sort' => ['_id' => -1]])->toArray() : [];

// Compute accurate statistics using centralized Asia/Kolkata lifecycle evaluation
$totalCount = count($allRawOpportunities);
$publishedCount = 0;
$inProgressCount = 0;
$completedCount = 0;
$closedCount = 0;
$draftCount = 0;

$matchedCount = 0;
$assignedCount = 0;
$unmatchedCount = 0;

foreach ($allRawOpportunities as $op) {
    $lc = getOpportunityLifecycleStatus($op);
    if ($lc === 'PUBLISHED') $publishedCount++;
    elseif ($lc === 'IN_PROGRESS') $inProgressCount++;
    elseif ($lc === 'COMPLETED') $completedCount++;
    elseif ($lc === 'CLOSED') $closedCount++;
    elseif ($lc === 'DRAFT') $draftCount++;

    $mc = getOpportunityMatchingStatus($op);
    if ($mc === 'ASSIGNED') $assignedCount++;
    elseif ($mc === 'MATCHED') $matchedCount++;
    else $unmatchedCount++;
}

// Filter opportunities based on active parameters
$opportunities = [];
foreach ($allRawOpportunities as $op) {
    $lc = getOpportunityLifecycleStatus($op);
    $mc = getOpportunityMatchingStatus($op);

    // Lifecycle Status Filter
    if ($statusFilter !== 'ALL' && $lc !== $statusFilter) {
        continue;
    }

    // Matching Status Filter
    if ($matchingFilter !== 'ALL' && $mc !== $matchingFilter) {
        continue;
    }

    // Domain Filter
    if ($domainFilter !== 'ALL') {
        $dom = $op['domain'] ?? '';
        $ttl = $op['title'] ?? '';
        if (stripos($dom, $domainFilter) === false && stripos($ttl, $domainFilter) === false) {
            continue;
        }
    }

    // Text Search Filter
    if (!empty($search)) {
        $searchHaystack = ($op['title'] ?? '') . ' ' .
                          ($op['city'] ?? '') . ' ' .
                          ($op['state'] ?? '') . ' ' .
                          ($op['jobId'] ?? '') . ' ' .
                          ($op['mentryId'] ?? '') . ' ' .
                          ($op['collegeName'] ?? '') . ' ' .
                          ($op['domain'] ?? '') . ' ' .
                          ((string)$op['_id']);
        if (stripos($searchHaystack, $search) === false) {
            continue;
        }
    }

    $opportunities[] = $op;
}

$hasActiveFilters = ($statusFilter !== 'ALL' || $matchingFilter !== 'ALL' || $domainFilter !== 'ALL' || !empty($search));
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">College Opportunities</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">Manage training engagements with date-driven lifecycle rules and independent trainer matching.</p>
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

    <?php if (!empty($_SESSION['flash_success']) || !empty($_GET['success'])): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-600 text-base">check_circle</span>
                <span><?= htmlspecialchars($_SESSION['flash_success'] ?? 'Action completed successfully.') ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error']) || !empty($_GET['error'])): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-rose-600 text-base">error</span>
                <span><?= htmlspecialchars($_SESSION['flash_error'] ?? $_GET['error']) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-700"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Quick Stats Metric Cards (Calculated from Real Lifecycle & Dates) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="/admin/opportunities.php" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-slate-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-slate-400">Total Opportunities</p>
            <p class="text-2xl font-black text-slate-900 mt-1"><?= $totalCount ?></p>
        </a>
        <a href="/admin/opportunities.php?status=PUBLISHED" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-blue-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-blue-600">Published / Open</p>
            <p class="text-2xl font-black text-blue-600 mt-1"><?= $publishedCount ?></p>
        </a>
        <a href="/admin/opportunities.php?status=IN_PROGRESS" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-amber-400 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-amber-600">In Progress</p>
            <p class="text-2xl font-black text-amber-600 mt-1"><?= $inProgressCount ?></p>
        </a>
        <a href="/admin/opportunities.php?status=COMPLETED" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-purple-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-purple-600">Completed</p>
            <p class="text-2xl font-black text-purple-600 mt-1"><?= $completedCount ?></p>
        </a>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card flex flex-col lg:flex-row items-center justify-between gap-4">
        <!-- Lifecycle Tabs -->
        <div class="flex flex-wrap items-center gap-1.5 w-full lg:w-auto">
            <?php
            $statuses = [
                'ALL' => 'All (' . $totalCount . ')',
                'PUBLISHED' => 'Published (' . $publishedCount . ')',
                'IN_PROGRESS' => 'In Progress (' . $inProgressCount . ')',
                'COMPLETED' => 'Completed (' . $completedCount . ')',
                'CLOSED' => 'Closed (' . $closedCount . ')',
                'DRAFT' => 'Draft (' . $draftCount . ')'
            ];
            foreach ($statuses as $k => $v): ?>
                <a href="/admin/opportunities.php?status=<?= $k ?>&matching=<?= urlencode($matchingFilter) ?>&domain=<?= urlencode($domainFilter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?= $statusFilter === $k ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
                    <?= $v ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Matching Filter Dropdown + Search Form -->
        <form method="GET" action="/admin/opportunities.php" class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="domain" value="<?= htmlspecialchars($domainFilter) ?>">

            <select name="matching" onchange="this.form.submit()" class="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl outline-none font-bold text-slate-700">
                <option value="ALL" <?= $matchingFilter === 'ALL' ? 'selected' : '' ?>>All Matching (<?= $totalCount ?>)</option>
                <option value="ASSIGNED" <?= $matchingFilter === 'ASSIGNED' ? 'selected' : '' ?>>Assigned (<?= $assignedCount ?>)</option>
                <option value="MATCHED" <?= $matchingFilter === 'MATCHED' ? 'selected' : '' ?>>Matched (<?= $matchedCount ?>)</option>
                <option value="NOT_MATCHED" <?= $matchingFilter === 'NOT_MATCHED' ? 'selected' : '' ?>>Unmatched (<?= $unmatchedCount ?>)</option>
            </select>

            <div class="relative flex-1 sm:w-64">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search title, city, job ID..." class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20 text-slate-900">
            </div>
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
                        <th class="py-4 px-4">Dates</th>
                        <th class="py-4 px-4">Duration</th>
                        <th class="py-4 px-4">Daily Rate</th>
                        <th class="py-4 px-4">Applicants</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4">Matching</th>
                        <th class="py-4 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($opportunities)): ?>
                        <tr><td colspan="9" class="p-8 text-center text-slate-400">No opportunities found matching your criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($opportunities as $op): 
                            $opId = (string)$op['_id'];
                            $applicantCount = $appCol ? $appCol->countDocuments(['opportunityId' => $opId]) : 0;
                            $lifecycleStatus = getOpportunityLifecycleStatus($op);
                            $matchingStatus = getOpportunityMatchingStatus($op);
                            $startDateFormatted = !empty($op['startDate']) ? formatDate($op['startDate']) : 'TBD';
                            $endDateFormatted = !empty($op['endDate']) ? formatDate($op['endDate']) : '';
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
                                <td class="py-4 px-4 text-slate-600 font-medium">
                                    <div class="leading-tight">
                                        <span class="font-bold text-slate-800"><?= $startDateFormatted ?></span>
                                        <?php if (!empty($endDateFormatted) && $endDateFormatted !== $startDateFormatted): ?>
                                            <span class="text-slate-400 block text-[11px]">to <?= $endDateFormatted ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-slate-600 font-medium">
                                    <?= formatOpportunityDuration($op) ?>
                                </td>
                                <td class="py-4 px-4 font-bold text-blue-700 whitespace-nowrap">
                                    <?= formatINR($op['dailyRateMin'] ?? 0) ?> - <?= formatINR($op['dailyRateMax'] ?? 0) ?>
                                </td>
                                <td class="py-4 px-4">
                                    <a href="/admin/opportunity-view.php?id=<?= $opId ?>" class="inline-flex items-center gap-1 font-bold text-xs <?= $applicantCount > 0 ? 'text-blue-600 hover:underline' : 'text-slate-400' ?>">
                                        <span class="material-symbols-outlined text-[15px]">person</span>
                                        <?= $applicantCount ?>
                                    </a>
                                </td>
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <?= renderLifecycleBadge($lifecycleStatus) ?>
                                </td>
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <?= renderMatchingBadge($matchingStatus) ?>
                                </td>
                                <td class="py-4 px-5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="/admin/opportunity-view.php?id=<?= $opId ?>" class="p-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors" title="View & Match">
                                            <span class="material-symbols-outlined text-[18px]">visibility</span>
                                        </a>
                                        <a href="/admin/opportunity-edit.php?id=<?= $opId ?>" class="p-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors" title="Edit Opportunity">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </a>

                                        <?php if ($lifecycleStatus === 'CLOSED' || $lifecycleStatus === 'COMPLETED'): ?>
                                            <form action="/actions/toggle-opportunity-status.php" method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                                <input type="hidden" name="opportunityId" value="<?= $opId ?>">
                                                <input type="hidden" name="action" value="reopen">
                                                <button type="submit" class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition-colors" title="Reopen Opportunity (Requires Future Dates)">
                                                    <span class="material-symbols-outlined text-[18px]">lock_open</span>
                                                </button>
                                            </form>
                                        <?php elseif ($lifecycleStatus === 'PUBLISHED' || $lifecycleStatus === 'IN_PROGRESS'): ?>
                                            <form action="/actions/toggle-opportunity-status.php" method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                                <input type="hidden" name="opportunityId" value="<?= $opId ?>">
                                                <input type="hidden" name="action" value="close">
                                                <button type="submit" onclick="return confirm('Close opportunity \'<?= htmlspecialchars(addslashes($op['title'])) ?>\'?');" class="p-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-rose-50 hover:text-rose-600 transition-colors" title="Close Opportunity">
                                                    <span class="material-symbols-outlined text-[18px]">lock</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form action="/actions/delete-opportunity.php" method="POST" class="inline" onsubmit="return confirm('Delete opportunity \'<?= htmlspecialchars(addslashes($op['title'])) ?>\'?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
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
