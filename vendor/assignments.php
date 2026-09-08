<?php
// vendor/assignments.php - Live Campus Deliveries for Vendor
$pageTitle = "Campus Training Deliveries";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$vendorId = $user['id'];
$reqCol = getCollection("VendorRequest");
$asgCol = getCollection("Assignment");
$oppCol = getCollection("Opportunity");
$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");

$vendorId = (string)($user['id'] ?? '');
$vendorEmail = (string)($user['email'] ?? '');
$orgName = (string)($user['organizationName'] ?? '');

$vendorQuery = [
    '$or' => array_values(array_filter([
        !empty($vendorId) ? ['vendorId' => $vendorId] : null,
        !empty($vendorEmail) ? ['vendorContactEmail' => $vendorEmail] : null,
        !empty($orgName) ? ['vendorName' => $orgName] : null,
        !empty($orgName) ? ['institutionName' => $orgName] : null
    ]))
];

$vendorReqs = $reqCol ? $reqCol->find($vendorQuery)->toArray() : [];
$opportunityIds = [];

foreach ($vendorReqs as $vr) {
    if (!empty($vr['convertedOpportunityId'])) {
        $opportunityIds[] = (string)$vr['convertedOpportunityId'];
    }
}

// Also find any Opportunity created directly with vendorId, vendorRequestId, or collegeName
if ($oppCol) {
    $directOpps = $oppCol->find([
        '$or' => array_values(array_filter([
            !empty($vendorId) ? ['vendorId' => $vendorId] : null,
            !empty($orgName) ? ['collegeName' => $orgName] : null
        ]))
    ])->toArray();
    foreach ($directOpps as $dOpp) {
        $opportunityIds[] = (string)$dOpp['_id'];
    }
}

$opportunityIds = array_values(array_unique(array_filter($opportunityIds)));

$assignments = [];
if (!empty($opportunityIds) && $asgCol) {
    $assignments = $asgCol->find(['opportunityId' => ['$in' => $opportunityIds]], ['sort' => ['createdAt' => -1]])->toArray();
}
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Confirmed Campus Deliveries</h1>
        <p class="text-xs md:text-sm text-slate-500 mt-1">Track faculty attendance, syllabus delivery, and campus logistics for your institutional clients.</p>
    </div>

    <div class="space-y-4">
        <?php if (empty($assignments)): ?>
            <div class="bg-white p-12 rounded-3xl border border-slate-200/90 shadow-card text-center text-xs text-slate-400">
                No live assignments confirmed yet. Once your submitted requirements are approved and matched with faculty, live delivery itineraries will appear here.
            </div>
        <?php else: ?>
            <?php foreach ($assignments as $asg): 
                $asgId = (string)$asg['_id'];
                $trainer = null;
                $trainerUser = null;
                if (!empty($asg['trainerId'])) {
                    try {
                        $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$asg['trainerId'])]);
                        if ($trainer && !empty($trainer['userId'])) {
                            $trainerUser = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainer['userId'])]);
                        }
                    } catch (Exception $e) {}
                }
            ?>
                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-200">Assignment #<?= substr($asgId, -6) ?></span>
                            <h3 class="font-bold text-base text-slate-900 mt-1">Live Faculty Deployment</h3>
                            <p class="text-xs text-slate-500">Location: <?= htmlspecialchars($asg['location'] ?? 'Campus') ?> • Duration: <?= htmlspecialchars($asg['durationDays'] ?? 5) ?> Days • Starts <?= formatDate($asg['startDate'] ?? null) ?></p>
                        </div>
                        <div>
                            <?= getStatusBadge($asg['status'] ?? 'SCHEDULED') ?>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4 text-xs">
                        <div class="bg-slate-50 p-4 rounded-2xl">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Assigned Faculty</span>
                            <p class="font-bold text-slate-900 mt-1"><?= htmlspecialchars($trainerUser['name'] ?? 'Assigned Faculty') ?></p>
                            <span class="text-[11px] text-slate-500"><?= htmlspecialchars($trainer['professionalTitle'] ?? '') ?></span>
                        </div>

                        <div class="bg-slate-50 p-4 rounded-2xl">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Campus Accommodation</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($asg['accommodationDetails'] ?? 'Executive Guest House') ?></p>
                        </div>

                        <div class="bg-slate-50 p-4 rounded-2xl">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Travel Itinerary</span>
                            <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars($asg['travelDetails'] ?? 'Arranged by Mentry') ?></p>
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
