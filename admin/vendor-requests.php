<?php
// admin/vendor-requests.php - Vendor & Institutional Job Requests
$pageTitle = "Vendor Job Requests";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$reqCol = getCollection("VendorRequest");

$statusFilter = $_GET['status'] ?? 'ALL';
$filter = [];
if ($statusFilter !== 'ALL') {
    $filter['status'] = $statusFilter;
}

$requests = $reqCol ? $reqCol->find($filter, ['sort' => ['createdAt' => -1]])->toArray() : [];

$totalCount = $reqCol ? $reqCol->countDocuments() : 0;
$pendingCount = $reqCol ? $reqCol->countDocuments(['status' => 'PENDING_ADMIN_REVIEW']) : 0;
$discussionCount = $reqCol ? $reqCol->countDocuments(['status' => 'UNDER_DISCUSSION']) : 0;
$approvedCount = $reqCol ? $reqCol->countDocuments(['status' => 'APPROVED_PUBLISHED']) : 0;
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Vendor & College Job Demands</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">Review private client requirements, contact coordinators, configure trainer payouts, and approve for publishing.</p>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-slate-400">Total Client Demands</p>
            <p class="text-2xl font-black text-slate-900 mt-1"><?= $totalCount ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-amber-600">Pending Admin Review</p>
            <p class="text-2xl font-black text-amber-600 mt-1"><?= $pendingCount ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-blue-600">Under Discussion</p>
            <p class="text-2xl font-black text-blue-600 mt-1"><?= $discussionCount ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-emerald-600">Approved & Live</p>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?= $approvedCount ?></p>
        </div>
    </div>

    <!-- Privacy Notice -->
    <div class="bg-slate-900 text-white p-4 rounded-2xl flex items-center justify-between gap-4 shadow-sm text-xs">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-amber-400 text-xl">lock</span>
            <p>
                <strong>Private Intake Workflow:</strong> Job demands listed below are private and <strong>NOT</strong> visible on public or trainer boards until you review, adjust the trainer honorarium, and approve them.
            </p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card flex flex-wrap items-center gap-2">
        <?php
        $statuses = [
            'ALL' => 'All Demands',
            'PENDING_ADMIN_REVIEW' => 'Pending Review',
            'UNDER_DISCUSSION' => 'Under Discussion',
            'APPROVED_PUBLISHED' => 'Approved & Live',
            'MATCHED' => 'Trainer Matched',
            'REJECTED' => 'Rejected'
        ];
        foreach ($statuses as $k => $v): ?>
            <a href="/admin/vendor-requests.php?status=<?= $k ?>" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all <?= $statusFilter === $k ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
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
                        <th class="py-4 px-5">Requirement & Client</th>
                        <th class="py-4 px-4">Domain & Mode</th>
                        <th class="py-4 px-4">Offered Budget</th>
                        <th class="py-4 px-4">Start Date</th>
                        <th class="py-4 px-4">Duration</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="p-8 text-center text-slate-400">No client demands found in this category.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $rq): 
                            $rqId = (string)$rq['_id'];
                        ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-5">
                                    <a href="/admin/vendor-request-review.php?id=<?= $rqId ?>" class="font-bold text-slate-900 hover:text-blue-600 block">
                                        <?= htmlspecialchars($rq['title']) ?>
                                    </a>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="font-mono text-[10px] font-bold text-[#FE5E04] bg-orange-50 border border-orange-200 px-1.5 py-0.2 rounded">
                                            <?= htmlspecialchars(getMentryCode('VENDOR', $rq)) ?>
                                        </span>
                                        <span class="text-[10px] text-slate-500">
                                            Vendor: <strong class="text-slate-800"><?= htmlspecialchars($rq['vendorName'] ?? 'Partner') ?></strong> • 
                                            College: <?= htmlspecialchars($rq['institutionName'] ?? '') ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="bg-blue-50 text-blue-700 font-bold px-2 py-0.5 rounded text-[11px]"><?= htmlspecialchars($rq['domain']) ?></span>
                                    <span class="text-[10px] text-slate-400 block mt-0.5"><?= htmlspecialchars($rq['mode'] ?? 'OFFLINE') ?> • <?= htmlspecialchars($rq['city']) ?></span>
                                </td>
                                <td class="py-4 px-4 font-black text-emerald-700"><?= formatINR($rq['budgetPerDay'] ?? 0) ?>/day</td>
                                <td class="py-4 px-4 text-slate-600"><?= formatDate($rq['startDate'] ?? null) ?></td>
                                <td class="py-4 px-4 text-slate-600 font-medium"><?= htmlspecialchars($rq['durationDays'] ?? 5) ?> Days</td>
                                <td class="py-4 px-4"><?= getStatusBadge($rq['status'] ?? 'PENDING_ADMIN_REVIEW') ?></td>
                                <td class="py-4 px-5 text-right">
                                    <a href="/admin/vendor-request-review.php?id=<?= $rqId ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-3.5 py-1.5 rounded-xl shadow-xs transition-colors inline-flex items-center gap-1">
                                        <span>Review & Adjust Price</span>
                                        <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                                    </a>
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
