<?php
// vendor/requests.php - Vendor's Submitted Requirements
$pageTitle = "My Submitted Requirements";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$vendorId = (string)($user['id'] ?? '');
$vendorEmail = (string)($user['email'] ?? '');
$orgName = (string)($user['organizationName'] ?? '');

$reqCol = getCollection("VendorRequest");
$oppCol = getCollection("Opportunity");
$asgCol = getCollection("Assignment");

$vendorQuery = [
    '$or' => array_values(array_filter([
        !empty($vendorId) ? ['vendorId' => $vendorId] : null,
        !empty($vendorEmail) ? ['vendorContactEmail' => $vendorEmail] : null,
        !empty($orgName) ? ['vendorName' => $orgName] : null,
        !empty($orgName) ? ['institutionName' => $orgName] : null
    ]))
];

$allRequests = $reqCol ? $reqCol->find($vendorQuery, ['sort' => ['createdAt' => -1]])->toArray() : [];

// Auto-resolve matched/assigned status across converted Opportunities & Assignments
foreach ($allRequests as &$rq) {
    $isAssigned = (!empty($rq['assignedTrainerId']) || ($rq['status'] ?? '') === 'MATCHED');
    $assignedTrainerId = $rq['assignedTrainerId'] ?? null;
    $convertedOppId = (string)($rq['convertedOpportunityId'] ?? '');

    if (!$isAssigned && !empty($convertedOppId) && $oppCol) {
        try {
            $opp = $oppCol->findOne([
                '$or' => [
                    ['_id' => new MongoDB\BSON\ObjectId($convertedOppId)],
                    ['_id' => $convertedOppId],
                    ['vendorRequestId' => (string)($rq['_id'] ?? '')]
                ]
            ]);
            if ($opp && (!empty($opp['assignedTrainerId']) || in_array($opp['status'] ?? '', ['CLOSED', 'MATCHED']))) {
                $isAssigned = true;
                $assignedTrainerId = $opp['assignedTrainerId'] ?? null;
            }
        } catch (\Throwable $e) {}
    }

    if (!$isAssigned && $asgCol) {
        $asgCheck = [];
        if (!empty($convertedOppId)) $asgCheck[] = ['opportunityId' => $convertedOppId];
        if (!empty($rq['_id'])) $asgCheck[] = ['vendorRequestId' => (string)$rq['_id']];
        if (!empty($asgCheck)) {
            $asg = $asgCol->findOne(['$or' => $asgCheck]);
            if ($asg && !empty($asg['trainerId'])) {
                $isAssigned = true;
                $assignedTrainerId = $asg['trainerId'];
            }
        }
    }

    if ($isAssigned) {
        $rq['status'] = 'MATCHED';
        $rq['assignedTrainerId'] = $assignedTrainerId;
        if ($reqCol && !empty($rq['_id'])) {
            try {
                $reqCol->updateOne(
                    ['_id' => $rq['_id']],
                    ['$set' => [
                        'status' => 'MATCHED',
                        'assignedTrainerId' => $assignedTrainerId,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
            } catch (\Throwable $e) {}
        }
    }
}
unset($rq);

$statusFilter = $_GET['status'] ?? 'ALL';
$requests = [];
foreach ($allRequests as $item) {
    if ($statusFilter === 'ALL' || ($item['status'] ?? '') === $statusFilter) {
        $requests[] = $item;
    }
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">My Training Requirements</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">Track administrator vetting, pricing configuration, and live trainer matching.</p>
        </div>

        <a href="/vendor/request-create.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-1.5 self-start">
            <span class="material-symbols-outlined text-base">add</span>
            Post New Demand
        </a>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card flex flex-wrap items-center gap-2">
        <?php
        $statuses = [
            'ALL' => 'All Demands',
            'PENDING_ADMIN_REVIEW' => 'Pending Review',
            'UNDER_DISCUSSION' => 'Under Discussion',
            'APPROVED_PUBLISHED' => 'Approved & Live',
            'MATCHED' => 'Trainer Assigned',
            'COMPLETED' => 'Completed'
        ];
        foreach ($statuses as $k => $v): ?>
            <a href="/vendor/requests.php?status=<?= $k ?>" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all <?= $statusFilter === $k ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
                <?= $v ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Requests Table -->
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
                        <th class="py-4 px-4">Approval State</th>
                        <th class="py-4 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="p-8 text-center text-slate-400">No requirements found matching the selected filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $rq): 
                            $rqId = (string)$rq['_id'];
                        ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-5">
                                    <a href="/vendor/request-view.php?id=<?= $rqId ?>" class="font-bold text-slate-900 hover:text-indigo-600 block">
                                        <?= htmlspecialchars($rq['title']) ?>
                                    </a>
                                    <span class="text-[10px] text-slate-500 font-medium">Institution: <?= htmlspecialchars($rq['institutionName'] ?? 'Academic Campus') ?></span>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="bg-indigo-50 text-indigo-700 font-bold px-2 py-0.5 rounded text-[11px]"><?= htmlspecialchars($rq['domain']) ?></span>
                                    <span class="text-[10px] text-slate-400 block mt-0.5"><?= htmlspecialchars($rq['mode'] ?? 'OFFLINE') ?> • <?= htmlspecialchars($rq['city']) ?></span>
                                </td>
                                <td class="py-4 px-4 font-black text-indigo-700"><?= formatINR($rq['budgetPerDay'] ?? 0) ?>/day</td>
                                <td class="py-4 px-4 text-slate-600 font-medium"><?= !empty($rq['endDate']) ? formatDate($rq['startDate'] ?? null) . ' – ' . formatDate($rq['endDate']) : formatDate($rq['startDate'] ?? null) ?></td>
                                <td class="py-4 px-4 text-slate-600 font-medium"><?= htmlspecialchars($rq['durationDays'] ?? 5) ?> Working Days</td>
                                <td class="py-4 px-4"><?= getStatusBadge($rq['status'] ?? 'PENDING_ADMIN_REVIEW') ?></td>
                                <td class="py-4 px-5 text-right">
                                    <a href="/vendor/request-view.php?id=<?= $rqId ?>" class="bg-slate-50 hover:bg-indigo-50 text-indigo-600 border border-slate-200 hover:border-indigo-200 px-3 py-1.5 rounded-xl font-bold text-xs transition-colors">
                                        View Dossier →
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
