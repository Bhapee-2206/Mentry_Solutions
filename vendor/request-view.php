<?php
// vendor/request-view.php - Vendor Demand Dossier View
$pageTitle = "Requirement Dossier";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$id = $_GET['id'] ?? '';
$vendorId = $user['id'];
$reqCol = getCollection("VendorRequest");
$oppCol = getCollection("Opportunity");
$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$asgCol = getCollection("Assignment");

$req = null;
if (!empty($id)) {
    try {
        $req = $reqCol->findOne([
            '_id' => new MongoDB\BSON\ObjectId($id),
            'vendorId' => $vendorId
        ]);
    } catch (Exception $e) {}
}

if (!$req) {
    header("Location: /vendor/requests.php");
    exit();
}

$reqId = (string)$req['_id'];
$linkedOpp = null;
if (!empty($req['convertedOpportunityId'])) {
    try {
        $linkedOpp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$req['convertedOpportunityId'])]);
    } catch (Exception $e) {}
}

$assignedTrainer = null;
$assignedUser = null;
$assignment = null;

$trainerIdToFind = $req['assignedTrainerId'] ?? null;
if (empty($trainerIdToFind) && !empty($linkedOpp)) {
    $trainerIdToFind = $linkedOpp['assignedTrainerId'] ?? null;
    if (empty($trainerIdToFind) && $asgCol) {
        $asg = $asgCol->findOne(['opportunityId' => (string)$linkedOpp['_id']]);
        if ($asg && !empty($asg['trainerId'])) {
            $trainerIdToFind = $asg['trainerId'];
        }
    }
}

if (!empty($trainerIdToFind)) {
    try {
        $assignedTrainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainerIdToFind)]);
        if ($assignedTrainer && !empty($assignedTrainer['userId'])) {
            $assignedUser = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$assignedTrainer['userId'])]);
        }
    } catch (Exception $e) {}

    // Ensure status reflects MATCHED
    $req['status'] = 'MATCHED';
    $req['assignedTrainerId'] = $trainerIdToFind;
}

$skills = [];
if (!empty($req['skillsRequired'])) {
    $skills = is_array($req['skillsRequired']) ? $req['skillsRequired'] : json_decode($req['skillsRequired'], true);
    if (!is_array($skills)) {
        $skills = explode(',', $req['skillsRequired']);
    }
}
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="/vendor/requests.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-indigo-600">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Requirements List
        </a>

        <div class="flex items-center gap-2">
            <span class="text-xs font-mono text-slate-400">ID: <?= substr($reqId, -8) ?></span>
        </div>
    </div>

    <!-- Header Card -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-8 shadow-card space-y-6">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-6 border-b border-slate-100">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="bg-indigo-50 text-indigo-700 font-bold text-xs px-2.5 py-0.5 rounded-full uppercase tracking-wider"><?= htmlspecialchars($req['domain']) ?></span>
                    <span class="bg-slate-100 text-slate-700 font-semibold text-xs px-2.5 py-0.5 rounded-full"><?= htmlspecialchars($req['mode'] ?? 'OFFLINE') ?></span>
                    <?= getStatusBadge($req['status'] ?? 'PENDING_ADMIN_REVIEW') ?>
                </div>

                <h1 class="text-2xl md:text-3xl font-black text-slate-900 leading-tight"><?= htmlspecialchars($req['title']) ?></h1>
                <p class="text-xs text-slate-500 font-medium">
                    Target Institution: <strong class="text-slate-800"><?= htmlspecialchars($req['institutionName']) ?></strong> • 
                    <?= htmlspecialchars($req['city']) ?>, <?= htmlspecialchars($req['state'] ?? 'India') ?> • 
                    <?= htmlspecialchars($req['durationDays'] ?? 5) ?> Days • 
                    Starts <strong><?= formatDate($req['startDate'] ?? null) ?></strong> • 
                    Batch Size: <strong><?= htmlspecialchars($req['studentCount'] ?? 100) ?> Students</strong>
                </p>
            </div>

            <div class="text-left md:text-right shrink-0 bg-slate-50 border border-slate-100 p-4 rounded-2xl min-w-[200px]">
                <span class="text-[10px] text-slate-400 font-bold uppercase block tracking-wider">Your Proposed Budget</span>
                <p class="text-2xl font-black text-indigo-700"><?= formatINR($req['budgetPerDay'] ?? 0) ?></p>
                <span class="text-[10px] text-slate-500">per instructional day</span>
            </div>
        </div>

        <!-- Dynamic Approval Banner based on Status -->
        <?php if (($req['status'] ?? '') === 'PENDING_ADMIN_REVIEW'): ?>
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-start gap-3">
                <span class="material-symbols-outlined text-amber-600 text-xl shrink-0 mt-0.5">hourglass_top</span>
                <div>
                    <h4 class="font-bold text-amber-950">Pending Administrative Review (Private)</h4>
                    <p class="mt-0.5 text-amber-800 leading-relaxed">
                        This requirement is currently private. A Mentry technical coordinator is reviewing the syllabus, lab infrastructure, and budget terms. We will contact your primary coordinator at <strong class="font-bold"><?= htmlspecialchars($req['vendorContactPhone'] ?? $user['phone']) ?></strong>.
                    </p>
                </div>
            </div>
        <?php elseif (($req['status'] ?? '') === 'UNDER_DISCUSSION'): ?>
            <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-xs text-blue-900 flex items-start gap-3">
                <span class="material-symbols-outlined text-blue-600 text-xl shrink-0 mt-0.5">phone_in_talk</span>
                <div>
                    <h4 class="font-bold text-blue-950">Under Active Discussion</h4>
                    <p class="mt-0.5 text-blue-800 leading-relaxed">
                        Mentry operations has contacted your organization. We are finalizing trainer daily honorarium adjustments and lab schedule confirmation.
                    </p>
                </div>
            </div>
        <?php elseif (($req['status'] ?? '') === 'APPROVED_PUBLISHED'): ?>
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 flex items-start gap-3">
                <span class="material-symbols-outlined text-emerald-600 text-xl shrink-0 mt-0.5">check_circle</span>
                <div class="flex-1">
                    <h4 class="font-bold text-emerald-950">Approved & Live on Trainer Network</h4>
                    <p class="mt-0.5 text-emerald-800 leading-relaxed">
                        The administrator has reviewed and published this opening to our network of verified faculty. Trainers are currently submitting their candidacies.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Assigned Trainer Profile (if matched) -->
        <?php if ($assignedTrainer && $assignedUser): ?>
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-2xl p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase text-white bg-emerald-600 px-2.5 py-0.5 rounded-full">Assigned Lead Faculty</span>
                    <span class="text-xs font-bold text-emerald-800">Deployment Confirmed ✓</span>
                </div>

                <div class="flex items-center gap-4 pt-1">
                    <img src="<?= htmlspecialchars($assignedUser['avatar'] ?? "https://avatar.vercel.sh/" . urlencode($assignedUser['name']) . ".png") ?>" class="w-14 h-14 rounded-2xl object-cover border-2 border-emerald-300">
                    <div>
                        <h4 class="font-black text-base text-slate-900"><?= htmlspecialchars($assignedUser['name']) ?></h4>
                        <p class="text-xs text-slate-600 font-medium"><?= htmlspecialchars($assignedTrainer['professionalTitle'] ?? 'Senior Faculty') ?> • <?= htmlspecialchars($assignedTrainer['currentCity'] ?? '') ?></p>
                        <p class="text-xs text-emerald-800 font-bold mt-0.5"><?= htmlspecialchars($assignedTrainer['totalExperienceYears'] ?? 0) ?>+ Years Experience • Mentry Verified Rating: <?= htmlspecialchars($assignedTrainer['adminRating'] ?? 4.9) ?>/5.0 ★</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Skills & Syllabus Breakdown -->
        <div class="space-y-4 pt-2">
            <div>
                <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Technical Skills & Topics</h4>
                <div class="flex flex-wrap gap-1.5">
                    <?php if (empty($skills)): ?>
                        <span class="text-xs text-slate-400">No specific skills listed.</span>
                    <?php else: ?>
                        <?php foreach ($skills as $s): ?>
                            <span class="bg-indigo-50 text-indigo-700 border border-indigo-100 text-xs font-bold px-3 py-1 rounded-xl">
                                <?= htmlspecialchars(trim($s)) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-1">
                <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Curriculum Scope & Syllabus Outline</h4>
                <p class="text-xs text-slate-600 leading-relaxed bg-slate-50 p-4 rounded-2xl border border-slate-100"><?= nl2br(htmlspecialchars($req['description'] ?? 'No additional description provided.')) ?></p>
            </div>

            <!-- Logistics Specs -->
            <div class="grid sm:grid-cols-2 gap-3 pt-2 text-xs">
                <div class="p-3.5 bg-slate-50 border border-slate-100 rounded-xl">
                    <strong class="text-slate-400 block uppercase text-[10px]">Campus Lodging</strong>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($req['accommodationDetails'] ?? 'Provided by College') ?></span>
                </div>
                <div class="p-3.5 bg-slate-50 border border-slate-100 rounded-xl">
                    <strong class="text-slate-400 block uppercase text-[10px]">Travel Logistics</strong>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($req['travelDetails'] ?? 'Reimbursed on actuals') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

</main>
</div>
</body>
</html>
