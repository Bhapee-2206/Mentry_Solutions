<?php
// vendor/dashboard.php - Vendor & Institutional Partner Dashboard
$pageTitle = "Partner Dashboard";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$vendorId = $user['id'];
$reqCol = getCollection("VendorRequest");
$oppCol = getCollection("Opportunity");
$asgCol = getCollection("Assignment");

$totalRequests = $reqCol ? $reqCol->countDocuments(['vendorId' => $vendorId]) : 0;
$pendingReview = $reqCol ? $reqCol->countDocuments(['vendorId' => $vendorId, 'status' => 'PENDING_ADMIN_REVIEW']) : 0;
$underDiscussion = $reqCol ? $reqCol->countDocuments(['vendorId' => $vendorId, 'status' => 'UNDER_DISCUSSION']) : 0;
$approvedLive = $reqCol ? $reqCol->countDocuments(['vendorId' => $vendorId, 'status' => 'APPROVED_PUBLISHED']) : 0;
$matchedAssigned = $reqCol ? $reqCol->countDocuments(['vendorId' => $vendorId, 'status' => 'MATCHED']) : 0;

$recentRequests = $reqCol ? $reqCol->find(
    ['vendorId' => $vendorId],
    ['sort' => ['createdAt' => -1], 'limit' => 5]
)->toArray() : [];
?>

<div class="space-y-8">
    <!-- Welcome Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Partner Operations Hub</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">Welcome, <strong><?= htmlspecialchars($user['organizationName'] ?? $user['name']) ?></strong>. Manage curriculum demands, private requests, and live trainer matching.</p>
        </div>

        <a href="/vendor/request-create.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-1.5 self-start">
            <span class="material-symbols-outlined text-base">post_add</span>
            Post New Job Request
        </a>
    </div>

    <!-- Private Intake Info Notice -->
    <div class="bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200/80 rounded-3xl p-6 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                <span class="material-symbols-outlined text-2xl">lock</span>
            </div>
            <div>
                <h3 class="font-bold text-sm text-slate-900">Private & Vetted Request Workflow</h3>
                <p class="text-xs text-slate-600 max-w-2xl mt-0.5 leading-relaxed">
                    When you submit a requirement, it is <strong>strictly private</strong>. Mentry's academic team contacts you to finalize syllabus specs, adjusts trainer remuneration, and approves it before publishing to our verified trainer network.
                </p>
            </div>
        </div>
        <a href="/vendor/request-create.php" class="bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-50 text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-xs shrink-0">
            Submit Private Scope →
        </a>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-slate-400">Total Demands Posted</p>
            <p class="text-2xl font-black text-slate-900 mt-1"><?= $totalRequests ?></p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-amber-600">Pending Admin Review</p>
            <p class="text-2xl font-black text-amber-600 mt-1"><?= $pendingReview + $underDiscussion ?></p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-indigo-600">Approved & Live</p>
            <p class="text-2xl font-black text-indigo-600 mt-1"><?= $approvedLive ?></p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-emerald-600">Faculty Assigned</p>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?= $matchedAssigned ?></p>
        </div>
    </div>

    <!-- Recent Job Requests Table -->
    <div class="bg-white border border-slate-200/90 rounded-3xl shadow-card overflow-hidden space-y-4 p-6">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-base text-slate-900">Recent Training Demands</h3>
                <p class="text-xs text-slate-500">Track approval progress and active deployments.</p>
            </div>
            <a href="/vendor/requests.php" class="text-xs font-bold text-indigo-600 hover:underline">
                View All Demands (<?= $totalRequests ?>) →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 uppercase tracking-wider font-bold">
                        <th class="py-3 px-4">Title & Client College</th>
                        <th class="py-3 px-4">Domain</th>
                        <th class="py-3 px-4">Mode & Location</th>
                        <th class="py-3 px-4">Offered Budget</th>
                        <th class="py-3 px-4">Duration</th>
                        <th class="py-3 px-4">Approval Status</th>
                        <th class="py-3 px-4 text-right">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($recentRequests)): ?>
                        <tr><td colspan="7" class="p-8 text-center text-slate-400">No training demands submitted yet. Click "Post New Job Request" to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentRequests as $rq): 
                            $rqId = (string)$rq['_id'];
                        ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3.5 px-4">
                                    <a href="/vendor/request-view.php?id=<?= $rqId ?>" class="font-bold text-slate-900 hover:text-indigo-600 block">
                                        <?= htmlspecialchars($rq['title']) ?>
                                    </a>
                                    <span class="text-[10px] text-slate-500 font-medium"><?= htmlspecialchars($rq['institutionName'] ?? 'Academic Campus') ?></span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-slate-700"><?= htmlspecialchars($rq['domain'] ?? 'Technical') ?></td>
                                <td class="py-3.5 px-4 text-slate-600">
                                    <?= htmlspecialchars($rq['city'] ?? 'India') ?>, <?= htmlspecialchars($rq['state'] ?? '') ?>
                                    <span class="text-[10px] text-indigo-600 font-bold block uppercase"><?= htmlspecialchars($rq['mode'] ?? 'OFFLINE') ?></span>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-indigo-700"><?= formatINR($rq['budgetPerDay'] ?? 0) ?>/day</td>
                                <td class="py-3.5 px-4 text-slate-600"><?= htmlspecialchars($rq['durationDays'] ?? 5) ?> Days</td>
                                <td class="py-3.5 px-4"><?= getStatusBadge($rq['status'] ?? 'PENDING_ADMIN_REVIEW') ?></td>
                                <td class="py-3.5 px-4 text-right">
                                    <a href="/vendor/request-view.php?id=<?= $rqId ?>" class="text-xs font-bold text-indigo-600 hover:underline">
                                        Inspect →
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
