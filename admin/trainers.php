<?php
// admin/trainers.php - Trainer Directory
$pageTitle = "Trainers Directory";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$docCol = getCollection("Document");
$asgCol = getCollection("Assignment");

$statusFilter = $_GET['status'] ?? 'ALL';
$domainFilter = $_GET['domain'] ?? 'ALL';
$availFilter = $_GET['avail'] ?? 'ALL';
$search = trim($_GET['search'] ?? '');

$conditions = [];
if ($statusFilter !== 'ALL') {
    $conditions[] = ['status' => $statusFilter];
}
if ($domainFilter !== 'ALL') {
    $conditions[] = ['primaryDomain' => new MongoDB\BSON\Regex($domainFilter, 'i')];
}
if ($availFilter === 'AVAILABLE_NOW') {
    $conditions[] = [
        '$or' => [
            ['availabilityStatus' => 'AVAILABLE_NOW'],
            ['availabilityStatus' => ['$exists' => false]],
            ['availabilityStatus' => null],
            ['availabilityStatus' => '']
        ]
    ];
} elseif ($availFilter !== 'ALL') {
    $conditions[] = ['availabilityStatus' => $availFilter];
}

$isIdSearch = (bool)preg_match('/^(?:MEN-TRN-|\d{3,}|TRN-)/i', $search);

if (!empty($search)) {
    $userMatchIds = [];
    if ($userCol) {
        try {
            $userOrConditions = [
                ['name' => new MongoDB\BSON\Regex($search, 'i')],
                ['email' => new MongoDB\BSON\Regex($search, 'i')],
                ['phone' => new MongoDB\BSON\Regex($search, 'i')],
                ['trainerCode' => new MongoDB\BSON\Regex($search, 'i')],
                ['mentryId' => new MongoDB\BSON\Regex($search, 'i')]
            ];
            if (preg_match('/^[a-f0-9]{24}$/i', $search)) {
                $userOrConditions[] = ['_id' => new MongoDB\BSON\ObjectId($search)];
            }
            $matchedUsers = $userCol->find(['$or' => $userOrConditions])->toArray();
            foreach ($matchedUsers as $mu) {
                $userMatchIds[] = (string)$mu['_id'];
            }
        } catch (\Throwable $e) {}
    }

    $orConditions = [
        ['trainerCode' => new MongoDB\BSON\Regex($search, 'i')],
        ['mentryId' => new MongoDB\BSON\Regex($search, 'i')],
        ['name' => new MongoDB\BSON\Regex($search, 'i')],
        ['email' => new MongoDB\BSON\Regex($search, 'i')],
        ['phone' => new MongoDB\BSON\Regex($search, 'i')],
        ['professionalTitle' => new MongoDB\BSON\Regex($search, 'i')],
        ['primaryDomain' => new MongoDB\BSON\Regex($search, 'i')],
        ['currentCity' => new MongoDB\BSON\Regex($search, 'i')],
        ['skills' => new MongoDB\BSON\Regex($search, 'i')]
    ];
    if (preg_match('/^[a-f0-9]{24}$/i', $search)) {
        try {
            $orConditions[] = ['_id' => new MongoDB\BSON\ObjectId($search)];
            $orConditions[] = ['userId' => $search];
        } catch (\Throwable $e) {}
    }
    if (!empty($userMatchIds)) {
        $orConditions[] = ['userId' => ['$in' => $userMatchIds]];
    }
    $conditions[] = ['$or' => $orConditions];
}

$filter = !empty($conditions) ? (count($conditions) === 1 ? $conditions[0] : ['$and' => $conditions]) : [];

$trainers = $trainerCol ? $trainerCol->find($filter, ['sort' => ['_id' => -1]])->toArray() : [];

// If specific ID search produced 0 results due to active status/availability filters, search globally by ID
if (empty($trainers) && !empty($search) && $trainerCol) {
    $fallbackOr = [
        ['trainerCode' => new MongoDB\BSON\Regex($search, 'i')],
        ['mentryId' => new MongoDB\BSON\Regex($search, 'i')]
    ];
    if (!empty($userMatchIds)) {
        $fallbackOr[] = ['userId' => ['$in' => $userMatchIds]];
    }
    $trainers = $trainerCol->find(['$or' => $fallbackOr], ['sort' => ['_id' => -1]])->toArray();
}

$totalTrainers = $trainerCol ? $trainerCol->countDocuments() : 0;
$approvedTrainers = $trainerCol ? $trainerCol->countDocuments(['status' => 'APPROVED']) : 0;
$availableNowCount = $trainerCol ? $trainerCol->countDocuments([
    '$or' => [
        ['availabilityStatus' => 'AVAILABLE_NOW'],
        ['availabilityStatus' => ['$exists' => false]],
        ['availabilityStatus' => null],
        ['availabilityStatus' => '']
    ]
]) : 0;
$freeAfterCount = $trainerCol ? $trainerCol->countDocuments(['availabilityStatus' => 'FREE_FROM_DATE']) : 0;
$busyCount = $trainerCol ? $trainerCol->countDocuments(['availabilityStatus' => 'BUSY_ON_ASSIGNMENT']) : 0;
$pendingTrainers = $trainerCol ? $trainerCol->countDocuments(['status' => 'PENDING_APPROVAL']) : 0;
$suspendedTrainers = $trainerCol ? $trainerCol->countDocuments(['status' => 'SUSPENDED']) : 0;

$hasActiveFilters = ($statusFilter !== 'ALL' || $availFilter !== 'ALL' || $domainFilter !== 'ALL' || !empty($search));
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Trainer Network Directory</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">Review, verify, approve, view resumes, and manage verified faculty across India.</p>
        </div>
        <?php if ($hasActiveFilters): ?>
            <a href="/admin/trainers.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200 px-3.5 py-2 rounded-xl transition-colors shrink-0">
                <span class="material-symbols-outlined text-sm">filter_alt_off</span>
                Clear All Filters
            </a>
        <?php endif; ?>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="/admin/trainers.php" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-slate-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-slate-400">Total Registered</p>
            <p class="text-2xl font-black text-slate-900 mt-1"><?= $totalTrainers ?></p>
        </a>
        <a href="/admin/trainers.php?avail=AVAILABLE_NOW" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-emerald-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-emerald-600">Available Immediately</p>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?= $availableNowCount ?></p>
        </a>
        <a href="/admin/trainers.php?status=APPROVED" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-blue-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-blue-600">Approved & Active</p>
            <p class="text-2xl font-black text-blue-600 mt-1"><?= $approvedTrainers ?></p>
        </a>
        <a href="/admin/trainers.php?status=PENDING_APPROVAL" class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card hover:border-amber-300 transition-colors block">
            <p class="text-[11px] font-bold uppercase text-amber-600">Pending Review</p>
            <p class="text-2xl font-black text-amber-600 mt-1"><?= $pendingTrainers ?></p>
        </a>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card space-y-3">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <span class="text-[10px] font-black uppercase text-slate-400 mr-1">Status:</span>
                <?php
                $statuses = [
                    'ALL' => 'All Statuses (' . $totalTrainers . ')',
                    'APPROVED' => 'Approved (' . $approvedTrainers . ')',
                    'PENDING_APPROVAL' => 'Pending Review (' . $pendingTrainers . ')',
                    'SUSPENDED' => 'Suspended (' . $suspendedTrainers . ')'
                ];
                foreach ($statuses as $k => $v): ?>
                    <a href="/admin/trainers.php?status=<?= $k ?>&avail=<?= urlencode($availFilter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $statusFilter === $k ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
                        <?= $v ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="GET" action="/admin/trainers.php" class="relative w-full md:w-72">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <input type="hidden" name="avail" value="<?= htmlspecialchars($availFilter) ?>">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, domain, city, skills..." class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20 text-slate-900">
            </form>
        </div>

        <!-- Availability Filter Row -->
        <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center gap-2">
            <span class="text-[10px] font-black uppercase text-emerald-700 mr-1 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Availability:
            </span>
            <a href="/admin/trainers.php?status=<?= urlencode($statusFilter) ?>&avail=ALL&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $availFilter === 'ALL' ? 'bg-emerald-700 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' ?>">
                All Schedules (<?= $totalTrainers ?>)
            </a>
            <a href="/admin/trainers.php?status=<?= urlencode($statusFilter) ?>&avail=AVAILABLE_NOW&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $availFilter === 'AVAILABLE_NOW' ? 'bg-emerald-700 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' ?>">
                🟢 Available Immediately (<?= $availableNowCount ?>)
            </a>
            <a href="/admin/trainers.php?status=<?= urlencode($statusFilter) ?>&avail=FREE_FROM_DATE&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $availFilter === 'FREE_FROM_DATE' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100' ?>">
                🟡 Free After Date (<?= $freeAfterCount ?>)
            </a>
            <a href="/admin/trainers.php?status=<?= urlencode($statusFilter) ?>&avail=BUSY_ON_ASSIGNMENT&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded-xl text-xs font-bold transition-all <?= $availFilter === 'BUSY_ON_ASSIGNMENT' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-800 hover:bg-blue-100' ?>">
                🔵 Currently Delivering (<?= $busyCount ?>)
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
                        <tr>
                            <td colspan="8" class="p-12 text-center">
                                <div class="max-w-md mx-auto space-y-2">
                                    <span class="material-symbols-outlined text-4xl text-slate-300">person_search</span>
                                    <p class="font-bold text-sm text-slate-700">No trainers found matching the selected filter.</p>
                                    <p class="text-xs text-slate-400">Try adjusting your availability or status filters, or clear search keywords.</p>
                                    <?php if ($hasActiveFilters): ?>
                                        <div class="pt-2">
                                            <a href="/admin/trainers.php" class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
                                                Reset to full directory
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trainers as $t): 
                            $u = null;
                            if ($userCol && !empty($t['userId'])) {
                                try { $u = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$t['userId'])]); } catch (Exception $e) {}
                            }
                            $trainerId = (string)$t['_id'];
                            $trainerCode = getMentryCode('TRAINER', $t);
                            $trainerStatus = $t['status'] ?? 'PENDING_APPROVAL';
                        ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-5">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= htmlspecialchars(getUserAvatar($u ?? $t, 80)) ?>" class="w-10 h-10 rounded-2xl object-cover border border-slate-200">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <a href="/admin/trainer-view.php?id=<?= $trainerId ?>" class="font-bold text-slate-900 hover:text-blue-600">
                                                    <?= htmlspecialchars($u['name'] ?? ($t['name'] ?? 'Trainer')) ?>
                                                </a>
                                                <span class="font-mono text-[11px] font-bold text-[#FE5E04] bg-orange-50 border border-orange-200 px-2 py-0.5 rounded-lg shadow-2xs">
                                                    <?= htmlspecialchars($trainerCode) ?>
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($t['professionalTitle'] ?? ($u['email'] ?? ($t['email'] ?? 'Expert'))) ?></p>
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
                                        <span class="text-[10px] text-slate-400 block truncate max-w-[150px]" title="<?= htmlspecialchars($t['availabilityNotes']) ?>">
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
                                <td class="py-4 px-4"><?= getStatusBadge($trainerStatus) ?></td>
                                <td class="py-4 px-5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="/admin/trainer-view.php?id=<?= $trainerId ?>" class="text-xs font-bold text-blue-600 hover:bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px]">description</span>
                                            Dossier & Resume
                                        </a>

                                        <?php if ($trainerStatus === 'PENDING_APPROVAL'): ?>
                                            <form action="/actions/update-trainer.php" method="POST" class="inline">
                                                <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                                                <input type="hidden" name="action_type" value="update_status">
                                                <input type="hidden" name="status" value="APPROVED">
                                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] px-3 py-1.5 rounded-xl shadow-xs transition-colors" title="Approve Trainer">
                                                    Approve
                                                </button>
                                            </form>
                                            <form action="/actions/update-trainer.php" method="POST" class="inline" onsubmit="return confirm('Reject and suspend this trainer application?');">
                                                <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                                                <input type="hidden" name="action_type" value="update_status">
                                                <input type="hidden" name="status" value="SUSPENDED">
                                                <button type="submit" class="bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 font-bold text-[11px] px-2.5 py-1.5 rounded-xl transition-colors" title="Reject / Suspend">
                                                    Reject
                                                </button>
                                            </form>
                                        <?php elseif ($trainerStatus === 'SUSPENDED'): ?>
                                            <form action="/actions/update-trainer.php" method="POST" class="inline">
                                                <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                                                <input type="hidden" name="action_type" value="update_status">
                                                <input type="hidden" name="status" value="APPROVED">
                                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] px-3 py-1.5 rounded-xl shadow-xs transition-colors" title="Re-activate Trainer">
                                                    Re-activate
                                                </button>
                                            </form>
                                        <?php elseif ($trainerStatus === 'APPROVED'): ?>
                                            <form action="/actions/update-trainer.php" method="POST" class="inline" onsubmit="return confirm('Suspend this trainer?');">
                                                <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                                                <input type="hidden" name="action_type" value="update_status">
                                                <input type="hidden" name="status" value="SUSPENDED">
                                                <button type="submit" class="text-slate-400 hover:text-rose-600 hover:bg-rose-50 p-1.5 rounded-xl transition-colors" title="Suspend Trainer">
                                                    <span class="material-symbols-outlined text-[16px]">block</span>
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
