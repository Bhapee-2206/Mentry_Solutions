<?php
// admin/trainers.php - Trainer Directory
$pageTitle = "Trainers Directory";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$docCol = getCollection("Document");

$statusFilter = $_GET['status'] ?? 'ALL';
$domainFilter = $_GET['domain'] ?? 'ALL';
$availFilter = $_GET['avail'] ?? 'ALL';
$search = trim($_GET['search'] ?? '');

$filter = [];
if ($statusFilter !== 'ALL') {
    $filter['status'] = $statusFilter;
}
if ($domainFilter !== 'ALL') {
    $filter['primaryDomain'] = new MongoDB\BSON\Regex($domainFilter, 'i');
}
if ($availFilter !== 'ALL') {
    $filter['availabilityStatus'] = $availFilter;
}
if (!empty($search)) {
    $filter['$or'] = [
        ['professionalTitle' => new MongoDB\BSON\Regex($search, 'i')],
        ['primaryDomain' => new MongoDB\BSON\Regex($search, 'i')],
        ['currentCity' => new MongoDB\BSON\Regex($search, 'i')]
    ];
}

$trainers = $trainerCol ? $trainerCol->find($filter, ['sort' => ['joinedAt' => -1]])->toArray() : [];

$totalTrainers = $trainerCol ? $trainerCol->countDocuments() : 0;
$approvedTrainers = $trainerCol ? $trainerCol->countDocuments(['status' => 'APPROVED']) : 0;
$availableNowCount = $trainerCol ? $trainerCol->countDocuments(['$or' => [['availabilityStatus' => 'AVAILABLE_NOW'], ['availabilityStatus' => ['$exists' => false]]]]) : 0;
$pendingTrainers = $trainerCol ? $trainerCol->countDocuments(['status' => 'PENDING_APPROVAL']) : 0;
$suspendedTrainers = $trainerCol ? $trainerCol->countDocuments(['status' => 'SUSPENDED']) : 0;
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Trainer Network Directory</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">Review, verify, approve, view resumes, and manage verified faculty across India.</p>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-slate-400">Total Registered</p>
            <p class="text-2xl font-black text-slate-900 mt-1"><?= $totalTrainers ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-emerald-600">Available Immediately</p>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?= $availableNowCount ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-blue-600">Approved & Active</p>
            <p class="text-2xl font-black text-blue-600 mt-1"><?= $approvedTrainers ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card">
            <p class="text-[11px] font-bold uppercase text-amber-600">Pending Review</p>
            <p class="text-2xl font-black text-amber-600 mt-1"><?= $pendingTrainers ?></p>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card space-y-3">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <span class="text-[10px] font-black uppercase text-slate-400 mr-1">Status:</span>
                <?php
                $statuses = [
                    'ALL' => 'All Statuses',
                    'APPROVED' => 'Approved',
                    'PENDING_APPROVAL' => 'Pending Review',
                    'SUSPENDED' => 'Suspended'
                ];
                foreach ($statuses as $k => $v): ?>
                    <a href="/admin/trainers.php?status=<?= $k ?>&avail=<?= urlencode($availFilter) ?>" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $statusFilter === $k ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
                        <?= $v ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="GET" action="/admin/trainers.php" class="relative w-full md:w-72">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <input type="hidden" name="avail" value="<?= htmlspecialchars($availFilter) ?>">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search title, city, domain..." class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20 text-slate-900">
            </form>
        </div>

        <!-- Availability Filter Row -->
        <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center gap-2">
            <span class="text-[10px] font-black uppercase text-emerald-700 mr-1 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Availability:
            </span>
            <a href="/admin/trainers.php?status=<?= urlencode($statusFilter) ?>&avail=ALL" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $availFilter === 'ALL' ? 'bg-emerald-700 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' ?>">
                All Schedules
            </a>
            <a href="/admin/trainers.php?status=<?= urlencode($statusFilter) ?>&avail=AVAILABLE_NOW" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $availFilter === 'AVAILABLE_NOW' ? 'bg-emerald-700 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' ?>">
                🟢 Available Immediately
            </a>
            <a href="/admin/trainers.php?status=<?= urlencode($statusFilter) ?>&avail=FREE_FROM_DATE" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $availFilter === 'FREE_FROM_DATE' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100' ?>">
                🟡 Free After Date
            </a>
            <a href="/admin/trainers.php?status=<?= urlencode($statusFilter) ?>&avail=BUSY_ON_ASSIGNMENT" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $availFilter === 'BUSY_ON_ASSIGNMENT' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-800 hover:bg-blue-100' ?>">
                🔵 Currently Delivering
            </a>
        </div>
    </div>

    <!-- Trainer Table -->
    <div class="bg-white border border-slate-200/90 rounded-3xl shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 uppercase tracking-wider font-bold">
                        <th class="py-4 px-5">Trainer Profile</th>
                        <th class="py-4 px-4">Domain</th>
                        <th class="py-4 px-4">Current Availability</th>
                        <th class="py-4 px-4">Base City</th>
                        <th class="py-4 px-4">Experience</th>
                        <th class="py-4 px-4">Daily Rate</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($trainers)): ?>
                        <tr><td colspan="8" class="p-8 text-center text-slate-400">No trainers found matching the selected filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($trainers as $t): 
                            $u = null;
                            if ($userCol && !empty($t['userId'])) {
                                try { $u = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$t['userId'])]); } catch (Exception $e) {}
                            }
                            $trainerId = (string)$t['_id'];
                            $hasResume = !empty($t['resumeUrl']) || ($docCol && $docCol->findOne(['trainerId' => $trainerId, 'type' => 'RESUME']));
                        ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-5">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= htmlspecialchars($u['avatar'] ?? "https://avatar.vercel.sh/" . urlencode($u['name'] ?? 'T') . ".png") ?>" class="w-10 h-10 rounded-2xl object-cover border border-slate-200">
                                        <div>
                                            <a href="/admin/trainer-view.php?id=<?= $trainerId ?>" class="font-bold text-slate-900 hover:text-blue-600">
                                                <?= htmlspecialchars($u['name'] ?? 'Trainer') ?>
                                            </a>
                                            <p class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($t['professionalTitle'] ?? ($u['email'] ?? 'Expert')) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="bg-blue-50 text-blue-700 font-semibold px-2 py-0.5 rounded text-[11px]">
                                        <?= htmlspecialchars($t['primaryDomain'] ?? 'Tech') ?>
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <?= getAvailabilityBadge($t['availabilityStatus'] ?? 'AVAILABLE_NOW', $t['availableFromDate'] ?? null) ?>
                                    <?php if (!empty($t['availabilityNotes'])): ?>
                                        <span class="text-[10px] text-slate-400 block truncate max-w-[130px]" title="<?= htmlspecialchars($t['availabilityNotes']) ?>">
                                            <?= htmlspecialchars($t['availabilityNotes']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4 text-slate-600 font-medium"><?= htmlspecialchars($t['currentCity'] ?? 'India') ?></td>
                                <td class="py-4 px-4 text-slate-600">
                                    <strong class="text-slate-800"><?= htmlspecialchars($t['totalExperienceYears'] ?? 0) ?>y Total</strong>
                                    <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($t['collegeExperienceYears'] ?? 0) ?>y College</span>
                                </td>
                                <td class="py-4 px-4 font-bold text-blue-700"><?= formatINR($t['dailyRateINR'] ?? 0) ?>/day</td>
                                <td class="py-4 px-4"><?= getStatusBadge($t['status'] ?? 'PENDING_APPROVAL') ?></td>
                                <td class="py-4 px-5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="/admin/trainer-view.php?id=<?= $trainerId ?>" class="text-xs font-bold text-blue-600 hover:bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px]">description</span>
                                            Dossier & Resume
                                        </a>

                                        <?php if (($t['status'] ?? 'PENDING_APPROVAL') === 'PENDING_APPROVAL'): ?>
                                            <form action="/actions/update-trainer.php" method="POST" class="inline">
                                                <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                                                <input type="hidden" name="action_type" value="update_status">
                                                <input type="hidden" name="status" value="APPROVED">
                                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] px-3 py-1.5 rounded-xl shadow-xs" title="Approve">
                                                    Approve
                                                </button>
                                            </form>
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
