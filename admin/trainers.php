<?php
// admin/trainers.php - Premium Trainer Network Directory & Operations Hub
$pageTitle = "Trainer Network Directory";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$docCol = getCollection("Document");
$asgCol = getCollection("Assignment");

$statusFilter = $_GET['status'] ?? 'ALL';
$domainFilter = $_GET['domain'] ?? 'ALL';
$availFilter  = $_GET['avail'] ?? 'ALL';
$sortBy       = $_GET['sort'] ?? 'latest';
$viewMode     = $_GET['view'] ?? 'grid'; // 'grid' or 'table'
$search       = trim($_GET['search'] ?? '');

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

$userMatchIds = [];
if (!empty($search)) {
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
        ['currentState' => new MongoDB\BSON\Regex($search, 'i')],
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

// Determine Sorting
$sortCriteria = ['_id' => -1];
if ($sortBy === 'exp_desc') {
    $sortCriteria = ['totalExperienceYears' => -1, '_id' => -1];
} elseif ($sortBy === 'rate_asc') {
    $sortCriteria = ['dailyRateINR' => 1, '_id' => -1];
} elseif ($sortBy === 'rate_desc') {
    $sortCriteria = ['dailyRateINR' => -1, '_id' => -1];
} elseif ($sortBy === 'rating_desc') {
    $sortCriteria = ['adminRating' => -1, '_id' => -1];
} elseif ($sortBy === 'name_asc') {
    $sortCriteria = ['name' => 1, '_id' => -1];
}

$trainers = $trainerCol ? $trainerCol->find($filter, ['sort' => $sortCriteria])->toArray() : [];

// Global fallback if specific search produced 0 results due to active filters
if (empty($trainers) && !empty($search) && $trainerCol) {
    $fallbackOr = [
        ['trainerCode' => new MongoDB\BSON\Regex($search, 'i')],
        ['mentryId' => new MongoDB\BSON\Regex($search, 'i')]
    ];
    if (!empty($userMatchIds)) {
        $fallbackOr[] = ['userId' => ['$in' => $userMatchIds]];
    }
    $trainers = $trainerCol->find(['$or' => $fallbackOr], ['sort' => $sortCriteria])->toArray();
}

// Compute Statistics
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

$hasActiveFilters = ($statusFilter !== 'ALL' || $availFilter !== 'ALL' || $domainFilter !== 'ALL' || !empty($search) || $sortBy !== 'latest');

// Extract unique domains for dropdown
$popularDomains = [
    'Technical / IT',
    'Full Stack Development',
    'Cloud & DevOps',
    'Data Science & AI',
    'Cybersecurity',
    'Aptitude & Reasoning',
    'Soft Skills & Communication',
    'Core Engineering',
    'Java / Python'
];

// Preload resumes map to enable instant preview triggers
$trainerIds = array_map(function($t) { return (string)$t['_id']; }, $trainers);
$userMapIds = array_filter(array_map(function($t) { return (string)($t['userId'] ?? ''); }, $trainers));
$resumesMap = [];
if ($docCol && (!empty($trainerIds) || !empty($userMapIds))) {
    try {
        $docs = $docCol->find([
            'type' => 'RESUME',
            '$or' => [
                ['trainerId' => ['$in' => $trainerIds]],
                ['userId' => ['$in' => array_values($userMapIds)]]
            ]
        ], ['sort' => ['uploadedAt' => -1]])->toArray();
        foreach ($docs as $doc) {
            $tId = (string)($doc['trainerId'] ?? '');
            $uId = (string)($doc['userId'] ?? '');
            if ($tId && !isset($resumesMap[$tId])) {
                $resumesMap[$tId] = $doc;
            }
            if ($uId && !isset($resumesMap[$uId])) {
                $resumesMap[$uId] = $doc;
            }
        }
    } catch (\Throwable $e) {}
}
?>

<div class="space-y-6 max-w-7xl mx-auto pb-16">
    <!-- Top Breadcrumb & Title Area -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm relative overflow-hidden">
        <div class="absolute -right-16 -top-16 w-48 h-48 bg-orange-500/5 rounded-full blur-2xl pointer-events-none"></div>
        <div class="space-y-1 relative z-10">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 text-[11px] font-extrabold uppercase tracking-wider text-[#FE5E04] bg-orange-50 px-2.5 py-0.5 rounded-lg border border-orange-200/60">
                    <span class="material-symbols-outlined text-[14px]">groups</span>
                    Talent Network
                </span>
                <span class="text-xs font-bold text-slate-400">&bull;</span>
                <span class="text-xs font-semibold text-slate-500">All-India Verified Faculty</span>
            </div>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <h1 class="text-2xl lg:text-3xl font-black text-slate-900 tracking-tight">Trainer Directory</h1>
                <span class="bg-slate-100 text-slate-700 text-xs font-extrabold px-3 py-1 rounded-full border border-slate-200">
                    <?= count($trainers) ?> of <?= $totalTrainers ?> Trainers
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 max-w-2xl">
                Browse, screen, review resumes in real-time, and manage assignments for technical, aptitude, and soft-skill corporate faculty.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0 relative z-10">
            <!-- View Mode Switcher -->
            <div class="inline-flex p-1 bg-slate-100 rounded-2xl border border-slate-200/80">
                <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'grid'])) ?>" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?= $viewMode === 'grid' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' ?>"
                   title="Card Grid View">
                    <span class="material-symbols-outlined text-[16px]">grid_view</span>
                    <span class="hidden sm:inline">Cards</span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'table'])) ?>" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?= $viewMode === 'table' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' ?>"
                   title="Data Table View">
                    <span class="material-symbols-outlined text-[16px]">table_rows</span>
                    <span class="hidden sm:inline">Table</span>
                </a>
            </div>

            <?php if ($hasActiveFilters): ?>
                <a href="/admin/trainers.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200 px-3.5 py-2 rounded-xl transition-all shadow-2xs">
                    <span class="material-symbols-outlined text-[15px]">filter_alt_off</span>
                    Reset Filters
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Executive KPI Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Faculty -->
        <a href="/admin/trainers.php" class="group bg-white p-5 rounded-3xl border border-slate-200/90 shadow-sm hover:shadow-md hover:border-slate-300 transition-all block relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-slate-700 to-slate-900"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Network</span>
                <div class="w-9 h-9 rounded-2xl bg-slate-100 group-hover:bg-slate-900 group-hover:text-white text-slate-700 flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-lg">badge</span>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-slate-900"><?= $totalTrainers ?></span>
                <span class="text-[11px] font-semibold text-slate-400">faculty</span>
            </div>
            <p class="text-[11px] text-slate-500 mt-2 font-medium flex items-center gap-1">
                <span class="text-emerald-600 font-bold">100%</span> verified roster
            </p>
        </a>

        <!-- Card 2: Available Immediately -->
        <a href="/admin/trainers.php?avail=AVAILABLE_NOW" class="group bg-white p-5 rounded-3xl border border-emerald-200/90 shadow-sm hover:shadow-md hover:border-emerald-300 transition-all block relative overflow-hidden bg-gradient-to-b from-white to-emerald-50/20">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 to-emerald-600"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Available Now</span>
                <div class="w-9 h-9 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-lg">bolt</span>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-emerald-700"><?= $availableNowCount ?></span>
                <span class="text-[11px] font-semibold text-emerald-600">ready</span>
            </div>
            <p class="text-[11px] text-emerald-700 mt-2 font-medium flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                Ready for instant dispatch
            </p>
        </a>

        <!-- Card 3: Approved & Active -->
        <a href="/admin/trainers.php?status=APPROVED" class="group bg-white p-5 rounded-3xl border border-blue-200/90 shadow-sm hover:shadow-md hover:border-blue-300 transition-all block relative overflow-hidden bg-gradient-to-b from-white to-blue-50/20">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-bold uppercase tracking-wider text-blue-700">Approved & Active</span>
                <div class="w-9 h-9 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-lg">verified</span>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-blue-700"><?= $approvedTrainers ?></span>
                <span class="text-[11px] font-semibold text-blue-600">active</span>
            </div>
            <p class="text-[11px] text-blue-600 mt-2 font-medium">
                Deliveries & colleges active
            </p>
        </a>

        <!-- Card 4: Pending Review -->
        <a href="/admin/trainers.php?status=PENDING_APPROVAL" class="group bg-white p-5 rounded-3xl border <?= $pendingTrainers > 0 ? 'border-amber-300 bg-amber-50/20 ring-2 ring-amber-400/20' : 'border-slate-200/90' ?> shadow-sm hover:shadow-md transition-all block relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-400 to-orange-500"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Pending Review</span>
                <div class="w-9 h-9 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-lg">pending_actions</span>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-amber-700"><?= $pendingTrainers ?></span>
                <span class="text-[11px] font-semibold text-amber-600">need action</span>
            </div>
            <p class="text-[11px] text-amber-700 mt-2 font-medium">
                <?= $pendingTrainers > 0 ? 'Review & verify credentials' : 'Zero backlog pending' ?>
            </p>
        </a>
    </div>

    <!-- Power Search & Multi-tier Filter Toolbar -->
    <div class="bg-white p-5 rounded-3xl border border-slate-200/90 shadow-sm space-y-4">
        <!-- Row 1: Search, Domain Filter & Sort -->
        <form method="GET" action="/admin/trainers.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="avail" value="<?= htmlspecialchars($availFilter) ?>">
            <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">

            <!-- Search Input -->
            <div class="sm:col-span-6 relative">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">search</span>
                <input type="text" 
                       name="search" 
                       value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search by name, ID (e.g. MEN-TRN-1558), skills, city, domain..." 
                       class="w-full pl-10 pr-10 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:bg-white focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04] text-slate-900 transition-all placeholder:text-slate-400 font-medium">
                <?php if (!empty($search)): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 p-1">
                        <span class="material-symbols-outlined text-sm">cancel</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Domain Dropdown -->
            <div class="sm:col-span-3">
                <select name="domain" onchange="this.form.submit()" class="w-full px-3 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:bg-white focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04] text-slate-700 font-medium cursor-pointer">
                    <option value="ALL" <?= $domainFilter === 'ALL' ? 'selected' : '' ?>>All Domains (General)</option>
                    <?php foreach ($popularDomains as $pd): ?>
                        <option value="<?= htmlspecialchars($pd) ?>" <?= strcasecmp($domainFilter, $pd) === 0 ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pd) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sort Dropdown -->
            <div class="sm:col-span-3">
                <select name="sort" onchange="this.form.submit()" class="w-full px-3 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:bg-white focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04] text-slate-700 font-medium cursor-pointer">
                    <option value="latest" <?= $sortBy === 'latest' ? 'selected' : '' ?>>Sort: Newest Registered</option>
                    <option value="exp_desc" <?= $sortBy === 'exp_desc' ? 'selected' : '' ?>>Sort: Highest Experience</option>
                    <option value="rate_desc" <?= $sortBy === 'rate_desc' ? 'selected' : '' ?>>Sort: Daily Rate (High to Low)</option>
                    <option value="rate_asc" <?= $sortBy === 'rate_asc' ? 'selected' : '' ?>>Sort: Daily Rate (Low to High)</option>
                    <option value="rating_desc" <?= $sortBy === 'rating_desc' ? 'selected' : '' ?>>Sort: Highest Admin Rating</option>
                    <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Sort: Name (A-Z)</option>
                </select>
            </div>
        </form>

        <!-- Row 2: Status Chips & Availability Segmented Filters -->
        <div class="pt-3 border-t border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
            <!-- Status Tabs -->
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-[10px] font-black uppercase text-slate-400 mr-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">tune</span>
                    Status:
                </span>
                <?php
                $statusTabs = [
                    'ALL' => ['label' => 'All', 'count' => $totalTrainers, 'color' => 'slate'],
                    'APPROVED' => ['label' => 'Approved', 'count' => $approvedTrainers, 'color' => 'blue'],
                    'PENDING_APPROVAL' => ['label' => 'Pending Review', 'count' => $pendingTrainers, 'color' => 'amber'],
                    'SUSPENDED' => ['label' => 'Suspended', 'count' => $suspendedTrainers, 'color' => 'rose']
                ];
                foreach ($statusTabs as $k => $info):
                    $isActive = ($statusFilter === $k);
                ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['status' => $k])) ?>" 
                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl font-bold transition-all <?= $isActive ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                        <span><?= $info['label'] ?></span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full <?= $isActive ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-600' ?>">
                            <?= $info['count'] ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Availability Tabs -->
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-[10px] font-black uppercase text-emerald-700 mr-1 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Availability:
                </span>
                <a href="?<?= http_build_query(array_merge($_GET, ['avail' => 'ALL'])) ?>" 
                   class="px-2.5 py-1.5 rounded-xl font-bold transition-all <?= $availFilter === 'ALL' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' ?>">
                    All (<?= $totalTrainers ?>)
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['avail' => 'AVAILABLE_NOW'])) ?>" 
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl font-bold transition-all <?= $availFilter === 'AVAILABLE_NOW' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' ?>">
                    <span>🟢 Immediately</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full <?= $availFilter === 'AVAILABLE_NOW' ? 'bg-white/20 text-white' : 'bg-emerald-200 text-emerald-900' ?>"><?= $availableNowCount ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['avail' => 'FREE_FROM_DATE'])) ?>" 
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl font-bold transition-all <?= $availFilter === 'FREE_FROM_DATE' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 text-amber-800 hover:bg-amber-100' ?>">
                    <span>🟡 Free Later</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full <?= $availFilter === 'FREE_FROM_DATE' ? 'bg-white/20 text-white' : 'bg-amber-200 text-amber-900' ?>"><?= $freeAfterCount ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['avail' => 'BUSY_ON_ASSIGNMENT'])) ?>" 
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl font-bold transition-all <?= $availFilter === 'BUSY_ON_ASSIGNMENT' ? 'bg-blue-600 text-white shadow-xs' : 'bg-blue-50 text-blue-800 hover:bg-blue-100' ?>">
                    <span>🔵 In Delivery</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full <?= $availFilter === 'BUSY_ON_ASSIGNMENT' ? 'bg-white/20 text-white' : 'bg-blue-200 text-blue-900' ?>"><?= $busyCount ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN DIRECTORY CONTENT: GRID OR TABLE -->
    <?php if (empty($trainers)): ?>
        <!-- Empty State -->
        <div class="bg-white border border-slate-200/90 rounded-3xl shadow-sm p-16 text-center">
            <div class="max-w-md mx-auto space-y-4">
                <div class="w-16 h-16 bg-orange-50 text-[#FE5E04] rounded-3xl flex items-center justify-center mx-auto shadow-inner">
                    <span class="material-symbols-outlined text-3xl">person_search</span>
                </div>
                <h3 class="font-black text-lg text-slate-900">No Trainers Found</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    We couldn't find any trainers matching your current filters and search criteria. Try broadening your keywords or resetting filters.
                </p>
                <div class="pt-2">
                    <a href="/admin/trainers.php" class="inline-flex items-center gap-1.5 bg-[#FE5E04] hover:bg-orange-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-sm transition-all">
                        <span class="material-symbols-outlined text-sm">restart_alt</span>
                        Reset Directory Filters
                    </a>
                </div>
            </div>
        </div>
    <?php elseif ($viewMode === 'grid'): ?>
        <!-- ================= CARD GRID VIEW ================= -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php foreach ($trainers as $t): 
                $trainerId = (string)$t['_id'];
                $userId = (string)($t['userId'] ?? '');
                $u = null;
                if ($userCol && !empty($userId)) {
                    try { $u = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]); } catch (\Throwable $e) {}
                }

                $trainerCode = getMentryCode('TRAINER', $t);
                $trainerStatus = $t['status'] ?? 'PENDING_APPROVAL';
                $trainerName = $u['name'] ?? ($t['name'] ?? 'Trainer');
                $trainerEmail = $u['email'] ?? ($t['email'] ?? 'N/A');
                $trainerPhone = $u['phone'] ?? ($t['phone'] ?? '');
                $trainerRating = $t['adminRating'] ?? 4.9;
                $dailyRate = $t['dailyRateINR'] ?? 0;
                $city = $t['currentCity'] ?? 'India';
                $state = $t['currentState'] ?? '';
                $location = !empty($state) ? "{$city}, {$state}" : $city;

                // Resume document lookup
                $resumeDoc = $resumesMap[$trainerId] ?? ($resumesMap[$userId] ?? null);
                $hasResume = !empty($resumeDoc['fileUrl']);
                $resumeUrl = $hasResume ? $resumeDoc['fileUrl'] : '';
                $cleanTrainerName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $trainerName);
                $cleanCode = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $trainerCode);
                $downloadFilename = "{$cleanTrainerName}_{$cleanCode}_Resume";

                // Skills list
                $skillsArr = [];
                if (!empty($t['skills'])) {
                    if (is_array($t['skills'])) {
                        $skillsArr = $t['skills'];
                    } else {
                        $skillsArr = array_filter(array_map('trim', explode(',', (string)$t['skills'])));
                    }
                }
            ?>
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm hover:shadow-md hover:border-slate-300 transition-all flex flex-col justify-between overflow-hidden group">
                    <!-- Card Top Section -->
                    <div class="p-5 space-y-4">
                        <!-- Header with Avatar, Name, ID & Status -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="relative shrink-0">
                                    <img src="<?= htmlspecialchars(getUserAvatar($u ?? $t, 96)) ?>" 
                                         alt="<?= htmlspecialchars($trainerName) ?>" 
                                         class="w-13 h-13 rounded-2xl object-cover border border-slate-200 shadow-2xs group-hover:scale-105 transition-transform">
                                    <span class="absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full border-2 border-white <?= ($t['availabilityStatus'] ?? 'AVAILABLE_NOW') === 'AVAILABLE_NOW' ? 'bg-emerald-500' : (($t['availabilityStatus'] ?? '') === 'BUSY_ON_ASSIGNMENT' ? 'bg-blue-500' : 'bg-amber-500') ?>" 
                                          title="<?= htmlspecialchars($t['availabilityStatus'] ?? 'AVAILABLE_NOW') ?>"></span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <a href="/admin/trainer-view.php?id=<?= $trainerId ?>" class="font-extrabold text-sm text-slate-900 hover:text-[#FE5E04] transition-colors truncate">
                                            <?= htmlspecialchars($trainerName) ?>
                                        </a>
                                    </div>
                                    <p class="text-xs font-semibold text-blue-600 truncate mt-0.5">
                                        <?= htmlspecialchars($t['professionalTitle'] ?? 'Corporate Faculty') ?>
                                    </p>
                                    <div class="flex items-center gap-1 mt-1">
                                        <span class="font-mono text-[10px] font-extrabold text-[#FE5E04] bg-orange-50 border border-orange-200/70 px-2 py-0.5 rounded-md">
                                            <?= htmlspecialchars($trainerCode) ?>
                                        </span>
                                        <span class="inline-flex items-center gap-0.5 text-[10px] font-black text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded-md border border-amber-200">
                                            <span class="material-symbols-outlined text-[12px] fill text-amber-500">star</span>
                                            <?= htmlspecialchars($trainerRating) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0">
                                <?= getStatusBadge($trainerStatus) ?>
                            </div>
                        </div>

                        <!-- Domain & Availability Badges -->
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <span class="bg-slate-100 text-slate-800 text-[11px] font-extrabold px-2.5 py-1 rounded-xl border border-slate-200/80">
                                <?= htmlspecialchars($t['primaryDomain'] ?? 'Tech') ?>
                            </span>
                            <?= getAvailabilityBadge($t['availabilityStatus'] ?? 'AVAILABLE_NOW', $t['availableFromDate'] ?? null) ?>
                        </div>

                        <!-- Quick Meta Info (Location & Contacts) -->
                        <div class="space-y-1 text-xs text-slate-500 font-medium">
                            <div class="flex items-center gap-1.5 truncate">
                                <span class="material-symbols-outlined text-slate-400 text-[15px]">location_on</span>
                                <span class="truncate"><?= htmlspecialchars($location) ?></span>
                            </div>
                            <div class="flex items-center gap-1.5 truncate">
                                <span class="material-symbols-outlined text-slate-400 text-[15px]">mail</span>
                                <span class="truncate"><?= htmlspecialchars($trainerEmail) ?></span>
                            </div>
                        </div>

                        <!-- Experience & Rate Metric Strip -->
                        <div class="grid grid-cols-3 gap-2 bg-slate-50 p-2.5 rounded-2xl border border-slate-100 text-center">
                            <div>
                                <p class="text-[10px] font-bold uppercase text-slate-400">Total Exp</p>
                                <p class="text-xs font-black text-slate-900 mt-0.5"><?= htmlspecialchars($t['totalExperienceYears'] ?? 0) ?> yrs</p>
                            </div>
                            <div class="border-x border-slate-200/70">
                                <p class="text-[10px] font-bold uppercase text-slate-400">College Exp</p>
                                <p class="text-xs font-black text-slate-900 mt-0.5"><?= htmlspecialchars($t['collegeExperienceYears'] ?? 0) ?> yrs</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-slate-400">Daily Rate</p>
                                <p class="text-xs font-black text-[#FE5E04] mt-0.5"><?= formatINR($dailyRate) ?></p>
                            </div>
                        </div>

                        <!-- Skills Chips Preview -->
                        <?php if (!empty($skillsArr)): ?>
                            <div class="flex flex-wrap items-center gap-1 pt-0.5">
                                <?php foreach (array_slice($skillsArr, 0, 3) as $sk): ?>
                                    <span class="bg-white text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-lg border border-slate-200">
                                        <?= htmlspecialchars($sk) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($skillsArr) > 3): ?>
                                    <span class="text-[10px] font-extrabold text-slate-400 px-1">
                                        +<?= count($skillsArr) - 3 ?> more
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card Bottom Action Bar -->
                    <div class="px-5 py-3.5 bg-slate-50/80 border-t border-slate-100 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <?php if ($hasResume): ?>
                                <button type="button" 
                                        onclick="openAdminDocViewer('<?= htmlspecialchars($resumeUrl, ENT_QUOTES) ?>', 'Resume: <?= htmlspecialchars($trainerName, ENT_QUOTES) ?>', '<?= htmlspecialchars($downloadFilename, ENT_QUOTES) ?>')"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-xl transition-all shadow-2xs"
                                        title="Instant In-Browser Resume Preview (No download)">
                                    <span class="material-symbols-outlined text-[15px]">visibility</span>
                                    Preview Resume
                                </button>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 bg-slate-100 px-2.5 py-1.5 rounded-xl">
                                    <span class="material-symbols-outlined text-[14px]">description</span>
                                    No Resume
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <a href="/admin/trainer-view.php?id=<?= $trainerId ?>" 
                               class="inline-flex items-center gap-1 text-xs font-bold text-slate-700 hover:text-slate-900 bg-white hover:bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-xl transition-all shadow-2xs">
                                <span>Dossier</span>
                                <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                            </a>

                            <?php if ($trainerStatus === 'PENDING_APPROVAL'): ?>
                                <form action="/actions/update-trainer.php" method="POST" class="inline">
                                    <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                                    <input type="hidden" name="action_type" value="update_status">
                                    <input type="hidden" name="status" value="APPROVED">
                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs p-1.5 rounded-xl shadow-2xs transition-colors" title="Quick Approve Trainer">
                                        <span class="material-symbols-outlined text-[16px] block">check</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <!-- ================= MODERN DATA TABLE VIEW ================= -->
        <div class="bg-white border border-slate-200/90 rounded-3xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] text-slate-500 uppercase tracking-wider font-extrabold">
                            <th class="py-4 px-6 whitespace-nowrap">Faculty Profile</th>
                            <th class="py-4 px-4">Primary Domain</th>
                            <th class="py-4 px-4">Availability</th>
                            <th class="py-4 px-4">Location</th>
                            <th class="py-4 px-4">Experience</th>
                            <th class="py-4 px-4">Daily Rate</th>
                            <th class="py-4 px-4">Status</th>
                            <th class="py-4 px-6 text-right">Quick Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($trainers as $t): 
                            $trainerId = (string)$t['_id'];
                            $userId = (string)($t['userId'] ?? '');
                            $u = null;
                            if ($userCol && !empty($userId)) {
                                try { $u = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]); } catch (\Throwable $e) {}
                            }

                            $trainerCode = getMentryCode('TRAINER', $t);
                            $trainerStatus = $t['status'] ?? 'PENDING_APPROVAL';
                            $trainerName = $u['name'] ?? ($t['name'] ?? 'Trainer');
                            $trainerEmail = $u['email'] ?? ($t['email'] ?? 'N/A');
                            $dailyRate = $t['dailyRateINR'] ?? 0;

                            $resumeDoc = $resumesMap[$trainerId] ?? ($resumesMap[$userId] ?? null);
                            $hasResume = !empty($resumeDoc['fileUrl']);
                            $resumeUrl = $hasResume ? $resumeDoc['fileUrl'] : '';
                            $cleanTrainerName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $trainerName);
                            $cleanCode = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $trainerCode);
                            $downloadFilename = "{$cleanTrainerName}_{$cleanCode}_Resume";
                        ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- Profile Cell -->
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="relative shrink-0">
                                            <img src="<?= htmlspecialchars(getUserAvatar($u ?? $t, 80)) ?>" 
                                                 alt="<?= htmlspecialchars($trainerName) ?>" 
                                                 class="w-10 h-10 rounded-2xl object-cover border border-slate-200">
                                            <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border border-white <?= ($t['availabilityStatus'] ?? 'AVAILABLE_NOW') === 'AVAILABLE_NOW' ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <a href="/admin/trainer-view.php?id=<?= $trainerId ?>" class="font-extrabold text-slate-900 hover:text-[#FE5E04] transition-colors">
                                                    <?= htmlspecialchars($trainerName) ?>
                                                </a>
                                                <span class="font-mono text-[10px] font-extrabold text-[#FE5E04] bg-orange-50 border border-orange-200/70 px-2 py-0.2 rounded-md shrink-0">
                                                    <?= htmlspecialchars($trainerCode) ?>
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-slate-500 font-medium truncate">
                                                <?= htmlspecialchars($t['professionalTitle'] ?? $trainerEmail) ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Domain -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <span class="bg-blue-50 text-blue-700 font-bold px-2.5 py-1 rounded-lg text-[11px] border border-blue-200/60">
                                        <?= htmlspecialchars($t['primaryDomain'] ?? 'Tech') ?>
                                    </span>
                                </td>

                                <!-- Availability Status -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <?= getAvailabilityBadge($t['availabilityStatus'] ?? 'AVAILABLE_NOW', $t['availableFromDate'] ?? null) ?>
                                    <?php if (!empty($t['availabilityNotes'])): ?>
                                        <span class="text-[10px] text-slate-400 block truncate max-w-[140px] mt-0.5" title="<?= htmlspecialchars($t['availabilityNotes']) ?>">
                                            <?= htmlspecialchars($t['availabilityNotes']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Base City -->
                                <td class="py-4 px-4 text-slate-700 font-medium whitespace-nowrap">
                                    <?= htmlspecialchars($t['currentCity'] ?? 'India') ?>
                                    <?php if (!empty($t['currentState'])): ?>
                                        <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($t['currentState']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Experience -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <strong class="text-slate-800 font-black"><?= htmlspecialchars($t['totalExperienceYears'] ?? 0) ?>y Total</strong>
                                    <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($t['collegeExperienceYears'] ?? 0) ?>y College</span>
                                </td>

                                <!-- Daily Rate -->
                                <td class="py-4 px-4 font-black text-[#FE5E04] whitespace-nowrap">
                                    <?= formatINR($dailyRate) ?>/day
                                </td>

                                <!-- Status Badge -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <?= getStatusBadge($trainerStatus) ?>
                                </td>

                                <!-- Quick Actions -->
                                <td class="py-4 px-6 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($hasResume): ?>
                                            <button type="button" 
                                                    onclick="openAdminDocViewer('<?= htmlspecialchars($resumeUrl, ENT_QUOTES) ?>', 'Resume: <?= htmlspecialchars($trainerName, ENT_QUOTES) ?>', '<?= htmlspecialchars($downloadFilename, ENT_QUOTES) ?>')"
                                                    class="text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-2.5 py-1.5 rounded-xl transition-colors flex items-center gap-1"
                                                    title="Instant Resume Preview">
                                                <span class="material-symbols-outlined text-[15px]">visibility</span>
                                                <span class="hidden sm:inline">Resume</span>
                                            </button>
                                        <?php endif; ?>

                                        <a href="/admin/trainer-view.php?id=<?= $trainerId ?>" 
                                           class="text-xs font-bold text-slate-700 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                                            <span>Dossier</span>
                                            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                                        </a>

                                        <?php if ($trainerStatus === 'PENDING_APPROVAL'): ?>
                                            <form action="/actions/update-trainer.php" method="POST" class="inline">
                                                <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                                                <input type="hidden" name="action_type" value="update_status">
                                                <input type="hidden" name="status" value="APPROVED">
                                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] px-2.5 py-1.5 rounded-xl shadow-xs transition-colors" title="Approve Trainer">
                                                    Approve
                                                </button>
                                            </form>
                                            <form action="/actions/update-trainer.php" method="POST" class="inline" onsubmit="return confirm('Reject and suspend this trainer application?');">
                                                <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                                                <input type="hidden" name="action_type" value="update_status">
                                                <input type="hidden" name="status" value="SUSPENDED">
                                                <button type="submit" class="bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 font-bold text-[11px] px-2 py-1.5 rounded-xl transition-colors" title="Reject">
                                                    Reject
                                                </button>
                                            </form>
                                        <?php elseif ($trainerStatus === 'SUSPENDED'): ?>
                                            <form action="/actions/update-trainer.php" method="POST" class="inline">
                                                <input type="hidden" name="trainerId" value="<?= $trainerId ?>">
                                                <input type="hidden" name="action_type" value="update_status">
                                                <input type="hidden" name="status" value="APPROVED">
                                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] px-2.5 py-1.5 rounded-xl shadow-xs transition-colors" title="Re-activate Trainer">
                                                    Re-activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ================= MODAL: IN-BROWSER DOCUMENT & RESUME VIEWER ================= -->
<div id="documentViewerModal" class="hidden fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 transition-opacity">
    <div class="bg-white rounded-3xl max-w-5xl w-full h-[92vh] flex flex-col shadow-2xl overflow-hidden border border-slate-200">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-white shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center shrink-0 border border-blue-100">
                    <span class="material-symbols-outlined text-2xl">description</span>
                </div>
                <div>
                    <h3 id="adminDocViewerTitle" class="font-extrabold text-sm text-slate-900">Faculty Resume Viewer</h3>
                    <p class="text-[11px] text-slate-500">Live In-Browser Document Preview &bull; Fast render with zero auto-downloads</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a id="adminDocViewerDownload" href="#" target="_blank" download class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-3.5 py-2 rounded-xl transition-colors flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[15px]">download</span>
                    <span>Download</span>
                </a>
                <button type="button" onclick="closeAdminDocViewer()" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition-colors" title="Close Preview">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>

        <!-- Modal Iframe Body -->
        <div class="flex-1 bg-slate-100 p-2 sm:p-3 overflow-hidden relative">
            <iframe id="adminDocViewerIframe" src="" class="w-full h-full rounded-2xl border-0 bg-white shadow-inner"></iframe>
        </div>
    </div>
</div>

<script>
function openAdminDocViewer(url, title, downloadFilename) {
    const modal = document.getElementById('documentViewerModal');
    const iframe = document.getElementById('adminDocViewerIframe');
    const titleEl = document.getElementById('adminDocViewerTitle');
    const downloadEl = document.getElementById('adminDocViewerDownload');
    
    if (titleEl) titleEl.textContent = title || 'Document Preview';
    
    if (downloadEl) {
        const dlName = downloadFilename || 'document';
        downloadEl.href = '/actions/download-document.php?url=' + encodeURIComponent(url) + '&filename=' + encodeURIComponent(dlName);
        downloadEl.setAttribute('download', dlName);
        downloadEl.setAttribute('title', 'Download ' + dlName);
    }

    // Load through /actions/preview-doc.php so DOCX files render as formatted HTML and PDFs render inline without auto-download!
    const previewUrl = '/actions/preview-doc.php?url=' + encodeURIComponent(url) + '&title=' + encodeURIComponent(title || 'Document');
    if (iframe) iframe.src = previewUrl;
    if (modal) modal.classList.remove('hidden');
}

function closeAdminDocViewer() {
    const modal = document.getElementById('documentViewerModal');
    const iframe = document.getElementById('adminDocViewerIframe');
    if (iframe) iframe.src = 'about:blank';
    if (modal) modal.classList.add('hidden');
}

// Close modal on Escape key press
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdminDocViewer();
    }
});
</script>

</main>
</div>
</body>
</html>
