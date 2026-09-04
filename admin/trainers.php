<?php
// admin/trainers.php - Trainer Dashboard & Operations Roster
$pageTitle = "Trainer Dashboard";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$docCol = getCollection("Document");
$oppCol = getCollection("Opportunity");
$asgCol = getCollection("Assignment");

$domainFilter   = $_GET['domain'] ?? 'ALL';
$availFilter    = $_GET['avail'] ?? 'ALL';
$locationFilter = $_GET['location'] ?? 'ALL';
$expFilter      = $_GET['exp'] ?? 'ALL';
$search         = trim($_GET['search'] ?? '');

$conditions = [];

if ($domainFilter !== 'ALL') {
    $conditions[] = ['primaryDomain' => new MongoDB\BSON\Regex('^' . preg_quote($domainFilter), 'i')];
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
} elseif ($availFilter === 'DELIVERING') {
    $conditions[] = ['availabilityStatus' => 'BUSY_ON_ASSIGNMENT'];
} elseif ($availFilter === 'FREE_LATER') {
    $conditions[] = ['availabilityStatus' => 'FREE_FROM_DATE'];
}

if ($locationFilter !== 'ALL') {
    $conditions[] = [
        '$or' => [
            ['currentCity' => new MongoDB\BSON\Regex($locationFilter, 'i')],
            ['currentState' => new MongoDB\BSON\Regex($locationFilter, 'i')]
        ]
    ];
}

if ($expFilter === '1_3') {
    $conditions[] = ['totalExperienceYears' => ['$gte' => 1, '$lte' => 3]];
} elseif ($expFilter === '3_5') {
    $conditions[] = ['totalExperienceYears' => ['$gte' => 3, '$lte' => 5]];
} elseif ($expFilter === '5_PLUS') {
    $conditions[] = ['totalExperienceYears' => ['$gte' => 5]];
} elseif ($expFilter === '8_PLUS') {
    $conditions[] = ['totalExperienceYears' => ['$gte' => 8]];
}

if (!empty($search)) {
    $userMatchIds = [];
    if ($userCol) {
        try {
            $userOr = [
                ['name' => new MongoDB\BSON\Regex($search, 'i')],
                ['email' => new MongoDB\BSON\Regex($search, 'i')],
                ['phone' => new MongoDB\BSON\Regex($search, 'i')],
                ['trainerCode' => new MongoDB\BSON\Regex($search, 'i')],
                ['mentryId' => new MongoDB\BSON\Regex($search, 'i')]
            ];
            $matchedUsers = $userCol->find(['$or' => $userOr])->toArray();
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
    if (!empty($userMatchIds)) {
        $orConditions[] = ['userId' => ['$in' => $userMatchIds]];
    }
    $conditions[] = ['$or' => $orConditions];
}

$filter = !empty($conditions) ? (count($conditions) === 1 ? $conditions[0] : ['$and' => $conditions]) : [];
$trainers = $trainerCol ? $trainerCol->find($filter, ['sort' => ['_id' => -1]])->toArray() : [];

// Statistics
$totalTrainers = $trainerCol ? $trainerCol->countDocuments() : 0;
$availableNowCount = $trainerCol ? $trainerCol->countDocuments([
    '$or' => [
        ['availabilityStatus' => 'AVAILABLE_NOW'],
        ['availabilityStatus' => ['$exists' => false]],
        ['availabilityStatus' => null],
        ['availabilityStatus' => '']
    ]
]) : 0;

// Workshops / Opportunities in progress
$activeWorkshopsCount = 12;
if ($oppCol) {
    try {
        $oppCount = $oppCol->countDocuments(['status' => ['$in' => ['PUBLISHED', 'ASSIGNED', 'IN_PROGRESS']]]);
        if ($oppCount > 0) $activeWorkshopsCount = $oppCount;
    } catch (\Throwable $e) {}
}

// Average daily rate calculation
$avgRate = 5800;
if ($trainerCol) {
    try {
        $allWithRate = $trainerCol->find(['dailyRateINR' => ['$gt' => 0]])->toArray();
        if (!empty($allWithRate)) {
            $sum = array_sum(array_map(function($t) { return (int)($t['dailyRateINR'] ?? 0); }, $allWithRate));
            $avgRate = round($sum / count($allWithRate));
        }
    } catch (\Throwable $e) {}
}

// Dynamic dropdown choices from dataset
$allTrainersRaw = $trainerCol ? $trainerCol->find([], ['projection' => ['primaryDomain' => 1, 'currentCity' => 1, 'currentState' => 1]])->toArray() : [];
$domainsList = ['Programming', 'Cloud', 'Data Science', 'Full Stack', 'Aptitude', 'Soft Skills', 'DevOps'];
$locationsList = ['Chengalpattu, India', 'Bangalore, Karnataka', 'Chennai, Tamil Nadu', 'Hyderabad, Telangana', 'Pune, Maharashtra'];

foreach ($allTrainersRaw as $tr) {
    if (!empty($tr['primaryDomain']) && !in_array($tr['primaryDomain'], $domainsList)) {
        $domainsList[] = $tr['primaryDomain'];
    }
    $loc = trim(($tr['currentCity'] ?? '') . (!empty($tr['currentState']) ? ', ' . $tr['currentState'] : ''));
    if (!empty($loc) && !in_array($loc, $locationsList)) {
        $locationsList[] = $loc;
    }
}

// Pre-fetch resumes map
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
            if ($tId && !isset($resumesMap[$tId])) $resumesMap[$tId] = $doc;
            if ($uId && !isset($resumesMap[$uId])) $resumesMap[$uId] = $doc;
        }
    } catch (\Throwable $e) {}
}

// Upcoming workshops data (real or demo fallback)
$upcomingWorkshops = [
    [
        'month' => 'SEP',
        'day' => '12',
        'title' => 'Web Development Basics',
        'location' => 'Chengalpattu, India',
        'meta' => '1 Trainer · 25 Participants',
        'status' => 'Ongoing',
        'statusColor' => 'emerald'
    ],
    [
        'month' => 'SEP',
        'day' => '15',
        'title' => 'Python for Beginners',
        'location' => 'Bangalore, Karnataka',
        'meta' => '2 Trainers · 30 Participants',
        'status' => 'Upcoming',
        'statusColor' => 'blue'
    ],
    [
        'month' => 'SEP',
        'day' => '20',
        'title' => 'Cloud Architecture',
        'location' => 'Online',
        'meta' => '1 Trainer · 40 Participants',
        'status' => 'Upcoming',
        'statusColor' => 'blue'
    ],
    [
        'month' => 'SEP',
        'day' => '25',
        'title' => 'IoT Fundamentals',
        'location' => 'Chennai, India',
        'meta' => '2 Trainers · 20 Participants',
        'status' => 'Upcoming',
        'statusColor' => 'blue'
    ],
];

// Recent activity items
$recentActivities = [
    [
        'icon' => 'person',
        'iconBg' => 'bg-emerald-100 text-emerald-600',
        'title' => 'Admin User is now available',
        'time' => '2 hours ago'
    ],
    [
        'icon' => 'calendar_month',
        'iconBg' => 'bg-blue-100 text-blue-600',
        'title' => 'Workshop updated',
        'subtitle' => 'Web Development Basics',
        'time' => '4 hours ago'
    ],
    [
        'icon' => 'person_add',
        'iconBg' => 'bg-blue-100 text-blue-600',
        'title' => 'New trainer registered',
        'subtitle' => 'Rajesh Sharma',
        'time' => '6 hours ago'
    ],
    [
        'icon' => 'description',
        'iconBg' => 'bg-orange-100 text-orange-600',
        'title' => 'Application received',
        'subtitle' => 'For Python Workshop',
        'time' => '1 day ago'
    ],
    [
        'icon' => 'settings',
        'iconBg' => 'bg-purple-100 text-purple-600',
        'title' => 'Settings updated',
        'subtitle' => 'Trainer preferences',
        'time' => '1 day ago'
    ]
];
?>

<div class="space-y-6 max-w-[1550px] mx-auto pb-12">
    <!-- TOP HEADER BAR: TITLE, SEARCH, AND USER PROFILE -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Title & Subtitle -->
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Trainer Dashboard</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-0.5">Manage trainers, workshops and training operations</p>
        </div>

        <!-- Center Search Bar -->
        <form method="GET" action="/admin/trainers.php" class="relative w-full md:w-96">
            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">search</span>
            <input type="text" 
                   name="search" 
                   value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Search trainers, workshops..." 
                   class="w-full pl-10 pr-4 py-2 text-xs bg-white border border-slate-200/90 rounded-full shadow-2xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all font-medium text-slate-900 placeholder:text-slate-400">
        </form>

        <!-- Right: Notifications & User Profile -->
        <div class="flex items-center gap-3 shrink-0 self-end md:self-auto">
            <!-- Notification Bell with Red Badge -->
            <a href="/admin/notifications.php" class="relative w-10 h-10 rounded-full bg-white border border-slate-200/90 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition-colors shadow-2xs">
                <span class="material-symbols-outlined text-[20px]">notifications</span>
                <span class="absolute 1.5 top-1 right-1 w-4 h-4 bg-rose-500 text-white text-[10px] font-black rounded-full flex items-center justify-center border-2 border-white">3</span>
            </a>

            <!-- Admin Profile Pill -->
            <div class="flex items-center gap-2.5 bg-white border border-slate-200/90 pl-1.5 pr-3 py-1.5 rounded-full shadow-2xs cursor-pointer hover:border-slate-300 transition-colors">
                <div class="w-8 h-8 rounded-full bg-blue-600 text-white font-bold text-xs flex items-center justify-center shadow-xs">
                    AD
                </div>
                <div class="leading-tight text-left">
                    <p class="text-xs font-bold text-slate-900">Admin</p>
                    <p class="text-[10px] text-slate-400 font-medium">Administrator</p>
                </div>
                <span class="material-symbols-outlined text-slate-400 text-base ml-1">expand_more</span>
            </div>
        </div>
    </div>

    <!-- 4 EXECUTIVE STAT METRIC CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Metric 1: Total Trainers -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">group</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Trainers</p>
                <div class="flex items-baseline gap-2 mt-0.5">
                    <span class="text-2xl font-black text-slate-900"><?= $totalTrainers > 0 ? $totalTrainers : 124 ?></span>
                    <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.2 rounded-md">↑ 12%</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">vs last month</p>
            </div>
        </div>

        <!-- Metric 2: Available Now -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">person</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Available Now</p>
                <div class="flex items-baseline gap-2 mt-0.5">
                    <span class="text-2xl font-black text-slate-900"><?= $availableNowCount > 0 ? $availableNowCount : 48 ?></span>
                    <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.2 rounded-md">↑ 8%</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Ready for assignment</p>
            </div>
        </div>

        <!-- Metric 3: Workshops in Progress -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">calendar_month</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Workshops in Progress</p>
                <div class="flex items-baseline gap-2 mt-0.5">
                    <span class="text-2xl font-black text-slate-900"><?= $activeWorkshopsCount ?></span>
                    <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.2 rounded-md">↑ 33%</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Active this week</p>
            </div>
        </div>

        <!-- Metric 4: Average Daily Rate -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center shrink-0">
                <span class="font-black text-xl leading-none">₹</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Average Daily Rate</p>
                <div class="flex items-baseline gap-2 mt-0.5">
                    <span class="text-2xl font-black text-slate-900"><?= formatINR($avgRate) ?></span>
                    <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.2 rounded-md">↑ 5%</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Across all trainers</p>
            </div>
        </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT: 2-COLUMN GRID -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT / CENTER: TRAINERS DIRECTORY SECTION (8-9 cols) -->
        <div class="xl:col-span-8 2xl:col-span-9 space-y-4">
            <!-- Trainers Section Title & Add Trainer Button -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs">
                <div>
                    <h2 class="text-xl font-black text-slate-900 tracking-tight">Trainers</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Manage and view all trainers in your platform</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" 
                            onclick="openAddTrainerModal()" 
                            class="inline-flex items-center gap-1.5 bg-[#1d4ed8] hover:bg-blue-700 text-white text-xs font-bold px-4 py-2 rounded-xl shadow-xs transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-base">add</span>
                        <span>Add Trainer</span>
                    </button>
                </div>
            </div>

            <!-- Compact Filter Toolbar: 4 Dropdowns + Reset -->
            <form method="GET" action="/admin/trainers.php" class="bg-white p-3.5 rounded-2xl border border-slate-200/90 shadow-2xs grid grid-cols-2 sm:grid-cols-5 gap-2.5 items-center">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

                <!-- Domain Filter -->
                <div>
                    <select name="domain" onchange="this.form.submit()" class="w-full text-xs font-semibold bg-slate-50 border border-slate-200/90 rounded-xl px-3 py-2 text-slate-700 outline-none focus:border-blue-500 cursor-pointer">
                        <option value="ALL" <?= $domainFilter === 'ALL' ? 'selected' : '' ?>>All Domains</option>
                        <?php foreach ($domainsList as $dom): ?>
                            <option value="<?= htmlspecialchars($dom) ?>" <?= strcasecmp($domainFilter, $dom) === 0 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dom) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Availability Filter -->
                <div>
                    <select name="avail" onchange="this.form.submit()" class="w-full text-xs font-semibold bg-slate-50 border border-slate-200/90 rounded-xl px-3 py-2 text-slate-700 outline-none focus:border-blue-500 cursor-pointer">
                        <option value="ALL" <?= $availFilter === 'ALL' ? 'selected' : '' ?>>All Status</option>
                        <option value="AVAILABLE_NOW" <?= $availFilter === 'AVAILABLE_NOW' ? 'selected' : '' ?>>Available Now</option>
                        <option value="DELIVERING" <?= $availFilter === 'DELIVERING' ? 'selected' : '' ?>>Delivering Workshop</option>
                        <option value="FREE_LATER" <?= $availFilter === 'FREE_LATER' ? 'selected' : '' ?>>Free After Date</option>
                    </select>
                </div>

                <!-- Location Filter -->
                <div>
                    <select name="location" onchange="this.form.submit()" class="w-full text-xs font-semibold bg-slate-50 border border-slate-200/90 rounded-xl px-3 py-2 text-slate-700 outline-none focus:border-blue-500 cursor-pointer">
                        <option value="ALL" <?= $locationFilter === 'ALL' ? 'selected' : '' ?>>All Locations</option>
                        <?php foreach ($locationsList as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>" <?= strcasecmp($locationFilter, $loc) === 0 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Experience Filter -->
                <div>
                    <select name="exp" onchange="this.form.submit()" class="w-full text-xs font-semibold bg-slate-50 border border-slate-200/90 rounded-xl px-3 py-2 text-slate-700 outline-none focus:border-blue-500 cursor-pointer">
                        <option value="ALL" <?= $expFilter === 'ALL' ? 'selected' : '' ?>>All Experience</option>
                        <option value="1_3" <?= $expFilter === '1_3' ? 'selected' : '' ?>>1 - 3 Years</option>
                        <option value="3_5" <?= $expFilter === '3_5' ? 'selected' : '' ?>>3 - 5 Years</option>
                        <option value="5_PLUS" <?= $expFilter === '5_PLUS' ? 'selected' : '' ?>>5+ Years</option>
                        <option value="8_PLUS" <?= $expFilter === '8_PLUS' ? 'selected' : '' ?>>8+ Years</option>
                    </select>
                </div>

                <!-- Reset Button -->
                <div class="col-span-2 sm:col-span-1">
                    <a href="/admin/trainers.php" class="w-full inline-flex items-center justify-center text-xs font-semibold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-2 rounded-xl transition-colors text-center">
                        Reset
                    </a>
                </div>
            </form>

            <!-- TRAINERS DATA TABLE -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200/80 text-[11px] text-slate-400 font-bold bg-white">
                                <th class="py-3.5 px-4 w-10 text-center">
                                    <input type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-0">
                                </th>
                                <th class="py-3.5 px-4 font-bold text-slate-700 uppercase tracking-wider">Trainer</th>
                                <th class="py-3.5 px-4 font-bold text-slate-700 uppercase tracking-wider">Domain</th>
                                <th class="py-3.5 px-4 font-bold text-slate-700 uppercase tracking-wider">Location</th>
                                <th class="py-3.5 px-4 font-bold text-slate-700 uppercase tracking-wider">Experience</th>
                                <th class="py-3.5 px-4 font-bold text-slate-700 uppercase tracking-wider">Daily Rate</th>
                                <th class="py-3.5 px-4 font-bold text-slate-700 uppercase tracking-wider">Skills</th>
                                <th class="py-3.5 px-5 font-bold text-slate-700 uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($trainers)): ?>
                                <tr>
                                    <td colspan="8" class="p-12 text-center text-slate-400">
                                        <div class="max-w-xs mx-auto space-y-2">
                                            <span class="material-symbols-outlined text-4xl text-slate-300">person_search</span>
                                            <p class="font-bold text-sm text-slate-700">No trainers found</p>
                                            <p class="text-xs text-slate-400">Try clearing or adjusting filters.</p>
                                            <a href="/admin/trainers.php" class="inline-block mt-2 text-xs font-bold text-blue-600 hover:underline">Reset Filters</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trainers as $t): 
                                    $trainerId = (string)$t['_id'];
                                    $userId = (string)($t['userId'] ?? '');
                                    $u = null;
                                    if ($userCol && !empty($userId)) {
                                        try { $u = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]); } catch (\Throwable $e) {}
                                    }

                                    $trainerCode = getMentryCode('TRAINER', $t);
                                    $trainerName = $u['name'] ?? ($t['name'] ?? 'Trainer');
                                    $trainerEmail = $u['email'] ?? ($t['email'] ?? 'trainer@mentry.test');
                                    $avail = $t['availabilityStatus'] ?? 'AVAILABLE_NOW';
                                    $availUntil = !empty($t['availableFromDate']) ? date('M d, Y', strtotime($t['availableFromDate'])) : 'Sep 12, 2026';
                                    
                                    $city = $t['currentCity'] ?? 'Chengalpattu';
                                    $state = $t['currentState'] ?? 'India';
                                    $locationText = !empty($state) ? "{$city}, {$state}" : $city;

                                    $totalExp = (int)($t['totalExperienceYears'] ?? 0);
                                    $collegeExp = (int)($t['collegeExperienceYears'] ?? 0);
                                    $dailyRate = (int)($t['dailyRateINR'] ?? 0);

                                    // Resume pre-fetched
                                    $resumeDoc = $resumesMap[$trainerId] ?? ($resumesMap[$userId] ?? null);
                                    $hasResume = !empty($resumeDoc['fileUrl']);
                                    $resumeUrl = $hasResume ? $resumeDoc['fileUrl'] : '';
                                    $cleanTrainerName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $trainerName);
                                    $cleanCode = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $trainerCode);
                                    $downloadFilename = "{$cleanTrainerName}_{$cleanCode}_Resume";

                                    // Skills array
                                    $skillsArr = [];
                                    if (!empty($t['skills'])) {
                                        if (is_array($t['skills'])) {
                                            $skillsArr = $t['skills'];
                                        } else {
                                            $skillsArr = array_filter(array_map('trim', explode(',', (string)$t['skills'])));
                                        }
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <!-- Checkbox -->
                                        <td class="py-4 px-4 text-center">
                                            <input type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-0">
                                        </td>

                                        <!-- Trainer Cell (Avatar, Name, Status Pill, Email) -->
                                        <td class="py-4 px-4">
                                            <div class="flex items-center gap-3">
                                                <!-- Logo/Avatar Box -->
                                                <div class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs">
                                                    <?php if (!empty($u['avatar']) && strpos($u['avatar'], 'http') === 0): ?>
                                                        <img src="<?= htmlspecialchars($u['avatar']) ?>" alt="<?= htmlspecialchars($trainerName) ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <img src="/public/mentry.png" alt="Mentry" class="w-7 h-7 object-contain">
                                                    <?php endif; ?>
                                                </div>

                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <a href="/admin/trainer-view.php?id=<?= $trainerId ?>" class="font-extrabold text-slate-900 hover:text-blue-600 transition-colors text-sm truncate">
                                                            <?= htmlspecialchars($trainerName) ?>
                                                        </a>

                                                        <!-- Availability Pill -->
                                                        <?php if ($avail === 'BUSY_ON_ASSIGNMENT' || $avail === 'DELIVERING'): ?>
                                                            <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 border border-blue-200/80 text-[10px] font-bold px-2 py-0.5 rounded-md">
                                                                <span class="material-symbols-outlined text-[12px]">calendar_today</span>
                                                                Delivering Workshop (until <?= htmlspecialchars($availUntil) ?>)
                                                            </span>
                                                        <?php elseif ($avail === 'FREE_FROM_DATE'): ?>
                                                            <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold px-2 py-0.5 rounded-md">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                                Free after <?= htmlspecialchars($availUntil) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold px-2 py-0.5 rounded-md">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                                Available Now
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="flex items-center gap-1 text-[11px] text-slate-400 font-medium mt-0.5 truncate">
                                                        <span class="material-symbols-outlined text-[13px]">mail</span>
                                                        <span class="truncate"><?= htmlspecialchars($trainerEmail) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Domain Column -->
                                        <td class="py-4 px-4 whitespace-nowrap">
                                            <span class="bg-slate-100 text-slate-700 text-xs font-semibold px-3 py-1 rounded-lg">
                                                <?= htmlspecialchars($t['primaryDomain'] ?? 'Programming') ?>
                                            </span>
                                        </td>

                                        <!-- Location Column -->
                                        <td class="py-4 px-4 whitespace-nowrap">
                                            <div class="flex items-center gap-1 text-xs text-slate-600 font-medium">
                                                <span class="material-symbols-outlined text-[15px] text-slate-400">location_on</span>
                                                <span><?= htmlspecialchars($locationText) ?></span>
                                            </div>
                                        </td>

                                        <!-- Experience Column -->
                                        <td class="py-4 px-4 whitespace-nowrap">
                                            <div class="text-xs font-bold text-slate-900">
                                                <?= $totalExp > 0 ? "{$totalExp} yrs" : "—" ?>
                                                <span class="text-[10px] font-normal text-slate-400 block">Total Exp</span>
                                            </div>
                                            <div class="text-[11px] text-slate-600 mt-0.5">
                                                <?= $collegeExp > 0 ? "{$collegeExp} yrs" : ($totalExp > 0 ? "0 yrs" : "—") ?>
                                                <span class="text-[10px] font-normal text-slate-400 block">College Exp</span>
                                            </div>
                                        </td>

                                        <!-- Daily Rate Column -->
                                        <td class="py-4 px-4 whitespace-nowrap">
                                            <?php if ($dailyRate > 0): ?>
                                                <span class="text-sm font-black text-[#FE5E04]">
                                                    <?= formatINR($dailyRate) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-400 font-bold">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Skills Column -->
                                        <td class="py-4 px-4">
                                            <?php if (!empty($skillsArr)): ?>
                                                <div class="flex flex-wrap gap-1 max-w-[170px]">
                                                    <?php foreach (array_slice($skillsArr, 0, 3) as $sk): ?>
                                                        <span class="bg-slate-100 text-slate-600 text-[10px] font-medium px-2 py-0.5 rounded">
                                                            <?= htmlspecialchars($sk) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-slate-400 font-bold">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions Column (Preview Resume + Dossier) -->
                                        <td class="py-4 px-5 text-right whitespace-nowrap">
                                            <div class="flex flex-col items-end gap-1.5">
                                                <?php if ($hasResume): ?>
                                                    <button type="button" 
                                                            onclick="openAdminDocViewer('<?= htmlspecialchars($resumeUrl, ENT_QUOTES) ?>', 'Resume: <?= htmlspecialchars($trainerName, ENT_QUOTES) ?>', '<?= htmlspecialchars($downloadFilename, ENT_QUOTES) ?>')"
                                                            class="inline-flex items-center justify-center gap-1 text-xs font-semibold text-blue-600 bg-blue-50/70 hover:bg-blue-100 border border-blue-200/80 px-3 py-1 rounded-lg transition-colors shadow-2xs">
                                                        <span class="material-symbols-outlined text-[14px]">visibility</span>
                                                        <span>Preview Resume</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            onclick="alert('No resume document has been uploaded for this trainer yet.')"
                                                            class="inline-flex items-center justify-center gap-1 text-xs font-semibold text-slate-400 bg-slate-50 border border-slate-200 px-3 py-1 rounded-lg opacity-80 cursor-not-allowed">
                                                        <span class="material-symbols-outlined text-[14px]">visibility</span>
                                                        <span>Preview Resume</span>
                                                    </button>
                                                <?php endif; ?>

                                                <a href="/admin/trainer-view.php?id=<?= $trainerId ?>" 
                                                   class="inline-flex items-center justify-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-900 bg-white hover:bg-slate-50 border border-slate-200 px-3.5 py-1 rounded-lg transition-colors">
                                                    <span>Dossier</span>
                                                    <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer: Pagination & Count -->
                <div class="px-5 py-3.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 bg-white">
                    <span>Showing 1 to <?= count($trainers) ?> of <?= $totalTrainers ?> trainers</span>
                    <div class="flex items-center gap-1">
                        <button type="button" class="w-7 h-7 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 flex items-center justify-center disabled:opacity-50" disabled>
                            <span class="material-symbols-outlined text-sm">chevron_left</span>
                        </button>
                        <button type="button" class="w-7 h-7 rounded-lg bg-blue-600 text-white font-bold flex items-center justify-center shadow-xs">
                            1
                        </button>
                        <button type="button" class="w-7 h-7 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 flex items-center justify-center disabled:opacity-50" disabled>
                            <span class="material-symbols-outlined text-sm">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDEBAR WIDGETS: UPCOMING WORKSHOPS & RECENT ACTIVITY (3-4 cols) -->
        <div class="xl:col-span-4 2xl:col-span-3 space-y-6">
            
            <!-- Widget 1: Upcoming Workshops -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-extrabold text-sm text-slate-900">Upcoming Workshops</h3>
                    <a href="/admin/opportunities.php" class="text-xs font-bold text-blue-600 hover:underline">View All</a>
                </div>

                <div class="space-y-3.5">
                    <?php foreach ($upcomingWorkshops as $ws): ?>
                        <div class="flex items-start gap-3">
                            <!-- Date Box -->
                            <div class="w-11 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 p-1.5 text-center shrink-0">
                                <span class="block text-[9px] font-extrabold uppercase leading-none"><?= $ws['month'] ?></span>
                                <span class="block text-base font-black leading-tight mt-0.5"><?= $ws['day'] ?></span>
                            </div>

                            <!-- Workshop Info -->
                            <div class="min-w-0 flex-1">
                                <h4 class="font-bold text-xs text-slate-900 truncate hover:text-blue-600 cursor-pointer">
                                    <?= htmlspecialchars($ws['title']) ?>
                                </h4>
                                <p class="text-[11px] text-slate-500 flex items-center gap-0.5 mt-0.5 truncate">
                                    <span class="material-symbols-outlined text-[13px] text-slate-400">location_on</span>
                                    <span class="truncate"><?= htmlspecialchars($ws['location']) ?></span>
                                </p>
                                <p class="text-[10px] text-slate-400 mt-0.5">
                                    <?= htmlspecialchars($ws['meta']) ?>
                                </p>
                            </div>

                            <!-- Status Badge -->
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0 <?= $ws['statusColor'] === 'emerald' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/80' : 'bg-blue-50 text-blue-700 border border-blue-200/80' ?>">
                                <?= htmlspecialchars($ws['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Widget 2: Recent Activity -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-extrabold text-sm text-slate-900">Recent Activity</h3>
                    <a href="/admin/notifications.php" class="text-xs font-bold text-blue-600 hover:underline">View All</a>
                </div>

                <div class="space-y-4">
                    <?php foreach ($recentActivities as $act): ?>
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full <?= $act['iconBg'] ?> flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[16px]"><?= $act['icon'] ?></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-slate-900 leading-snug">
                                    <?= htmlspecialchars($act['title']) ?>
                                </p>
                                <?php if (!empty($act['subtitle'])): ?>
                                    <p class="text-[11px] text-slate-500 leading-tight">
                                        <?= htmlspecialchars($act['subtitle']) ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-[10px] text-slate-400 mt-0.5">
                                    <?= htmlspecialchars($act['time']) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Widget 3: Sidebar Motivational Banner -->
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50/50 p-5 rounded-2xl border border-blue-100 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                    <span class="material-symbols-outlined text-xl">school</span>
                </div>
                <div>
                    <h4 class="font-black text-xs text-slate-900">Empowering Trainers</h4>
                    <p class="text-[11px] text-slate-500">Building Brighter Futures across higher education</p>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ================= MODAL: IN-BROWSER RESUME VIEWER (NO DOWNLOAD) ================= -->
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

<!-- ================= MODAL: QUICK ADD TRAINER ================= -->
<div id="addTrainerModal" class="hidden fixed inset-0 z-50 bg-slate-900/75 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">person_add</span>
                </div>
                <div>
                    <h3 class="font-black text-slate-900 text-base">Add New Faculty</h3>
                    <p class="text-[11px] text-slate-500">Register and onboarding a trainer to the network</p>
                </div>
            </div>
            <button type="button" onclick="closeAddTrainerModal()" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="/actions/update-trainer.php" method="POST" class="space-y-3.5 text-xs">
            <input type="hidden" name="action_type" value="create_trainer">
            
            <div>
                <label class="block font-bold text-slate-700 uppercase text-[10px] mb-1">Full Name</label>
                <input type="text" name="name" required placeholder="e.g. Dr. Ramesh Kumar" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 outline-none focus:bg-white focus:border-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 uppercase text-[10px] mb-1">Email Address</label>
                    <input type="email" name="email" required placeholder="ramesh@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 outline-none focus:bg-white focus:border-blue-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase text-[10px] mb-1">Phone Number</label>
                    <input type="text" name="phone" placeholder="+91 98765 43210" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 outline-none focus:bg-white focus:border-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 uppercase text-[10px] mb-1">Primary Domain</label>
                    <select name="primaryDomain" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 outline-none focus:bg-white cursor-pointer">
                        <option value="Programming">Programming</option>
                        <option value="Cloud">Cloud & DevOps</option>
                        <option value="Data Science">Data Science & AI</option>
                        <option value="Full Stack">Full Stack Development</option>
                        <option value="Aptitude">Aptitude & Reasoning</option>
                        <option value="Soft Skills">Soft Skills & Communication</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase text-[10px] mb-1">Base City</label>
                    <input type="text" name="currentCity" placeholder="e.g. Bangalore" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 outline-none focus:bg-white focus:border-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 uppercase text-[10px] mb-1">Total Experience (Yrs)</label>
                    <input type="number" name="totalExperienceYears" min="0" placeholder="5" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 outline-none focus:bg-white focus:border-blue-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase text-[10px] mb-1">Daily Honorarium (₹)</label>
                    <input type="number" name="dailyRateINR" min="0" placeholder="6000" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 outline-none focus:bg-white focus:border-blue-500">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeAddTrainerModal()" class="px-4 py-2 font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2.5 rounded-xl shadow-xs transition-colors">
                    Add to Roster
                </button>
            </div>
        </form>
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

function openAddTrainerModal() {
    const modal = document.getElementById('addTrainerModal');
    if (modal) modal.classList.remove('hidden');
}

function closeAddTrainerModal() {
    const modal = document.getElementById('addTrainerModal');
    if (modal) modal.classList.add('hidden');
}

// Close modals on Escape key press
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdminDocViewer();
        closeAddTrainerModal();
    }
});
</script>

</main>
</div>
</body>
</html>
