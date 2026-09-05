<?php
// opportunities.php - Opportunities Catalog
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = "Training Opportunities";

$search = trim($_GET['search'] ?? '');
$selectedDomain = $_GET['domain'] ?? 'ALL';
$selectedLocation = $_GET['location'] ?? 'ALL';
$selectedMode = $_GET['mode'] ?? 'ALL';
$selectedType = $_GET['type'] ?? 'ALL';

$opportunityCol = getCollection("Opportunity");

$conditions = [
    ['status' => 'PUBLISHED'],
    ['status' => ['$nin' => ['CLOSED', 'MATCHED', 'COMPLETED', 'CANCELLED', 'DRAFT']]]
];

if ($selectedMode !== 'ALL') {
    $conditions[] = ['mode' => $selectedMode];
}
if ($selectedType !== 'ALL') {
    $conditions[] = ['trainingType' => $selectedType];
}
if ($selectedLocation !== 'ALL') {
    $conditions[] = ['city' => new MongoDB\BSON\Regex($selectedLocation, 'i')];
}
if ($selectedDomain !== 'ALL') {
    $domainRegex = new MongoDB\BSON\Regex($selectedDomain, 'i');
    $conditions[] = [
        '$or' => [
            ['domain' => $domainRegex],
            ['title' => $domainRegex],
            ['skillsRequired' => $domainRegex]
        ]
    ];
}
if (!empty($search)) {
    $searchRegex = new MongoDB\BSON\Regex($search, 'i');
    $conditions[] = [
        '$or' => [
            ['title' => $searchRegex],
            ['city' => $searchRegex],
            ['skillsRequired' => $searchRegex],
            ['jobId' => $searchRegex]
        ]
    ];
}

$filter = count($conditions) === 1 ? $conditions[0] : ['$and' => $conditions];

$rawOpportunities = $opportunityCol ? $opportunityCol->find($filter, ['sort' => ['createdAt' => -1]])->toArray() : [];
$opportunities = [];
foreach ($rawOpportunities as $op) {
    $opStatus = strtoupper($op['status'] ?? 'PUBLISHED');
    $isClosed = ($opStatus === 'CLOSED' || $opStatus === 'MATCHED' || !empty($op['assignedTrainerId']));
    if ($isClosed) continue;
    $opportunities[] = $op;
}
$totalCount = count($opportunities);

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-50/50 min-h-screen py-10 md:py-14 border-b border-slate-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-bold mb-2">
                <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                Verified Indian College Training Openings
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-slate-950 tracking-tight">
                Training Opportunities
            </h1>
            <p class="text-slate-600 text-sm md:text-base mt-1">
                Browse and apply for offline and online assignments across top institutions in India.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Filters Sidebar (Sticky on desktop) -->
            <aside class="lg:col-span-3 space-y-6 lg:sticky lg:top-24 self-start">
                <form method="GET" action="/opportunities.php" class="bg-white rounded-3xl border border-slate-200/90 p-6 shadow-card space-y-6">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h2 class="font-extrabold text-base text-slate-900">Filters</h2>
                        <a href="/opportunities.php" class="text-xs text-blue-600 hover:text-blue-700 font-bold">Reset All</a>
                    </div>

                    <!-- Mode Filter -->
                    <div class="space-y-2.5">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Training Mode</label>
                        <?php
                        $modes = ['ALL' => 'All Modes', 'OFFLINE' => 'Offline (On-Campus)', 'ONLINE' => 'Online / Virtual', 'HYBRID' => 'Hybrid'];
                        foreach ($modes as $k => $v): ?>
                            <label class="flex items-center gap-2.5 text-xs font-medium text-slate-700 cursor-pointer">
                                <input type="radio" name="mode" value="<?= $k ?>" <?= $selectedMode === $k ? 'checked' : '' ?> onchange="this.form.submit()" class="text-blue-600">
                                <span><?= $v ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Domain Filter -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Domain</label>
                        <select name="domain" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-xs rounded-xl p-2.5 outline-none focus:border-blue-500 font-medium">
                            <option value="ALL" <?= $selectedDomain === 'ALL' ? 'selected' : '' ?>>All Domains</option>
                            <option value="Programming" <?= $selectedDomain === 'Programming' ? 'selected' : '' ?>>Programming & Software</option>
                            <option value="Data Science" <?= $selectedDomain === 'Data Science' ? 'selected' : '' ?>>Data Science & AI</option>
                            <option value="Cloud" <?= $selectedDomain === 'Cloud' ? 'selected' : '' ?>>Cloud & DevOps</option>
                            <option value="VLSI" <?= $selectedDomain === 'VLSI' ? 'selected' : '' ?>>VLSI & Embedded</option>
                            <option value="Cybersecurity" <?= $selectedDomain === 'Cybersecurity' ? 'selected' : '' ?>>Cybersecurity</option>
                            <option value="Aptitude" <?= $selectedDomain === 'Aptitude' ? 'selected' : '' ?>>Aptitude & Placement</option>
                            <option value="Soft Skills" <?= $selectedDomain === 'Soft Skills' ? 'selected' : '' ?>>Soft Skills</option>
                            <option value="Management" <?= $selectedDomain === 'Management' ? 'selected' : '' ?>>Management</option>
                        </select>
                    </div>

                    <!-- Location Filter -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400 block">City / Location</label>
                        <select name="location" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-xs rounded-xl p-2.5 outline-none focus:border-blue-500 font-medium">
                            <option value="ALL" <?= $selectedLocation === 'ALL' ? 'selected' : '' ?>>All Cities in India</option>
                            <?php
                            $cities = ["Bangalore", "Chennai", "Coimbatore", "Hyderabad", "Pune", "Mumbai", "Delhi", "Kochi", "Salem", "Trichy", "Madurai"];
                            foreach ($cities as $c): ?>
                                <option value="<?= $c ?>" <?= $selectedLocation === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Type Filter -->
                    <div class="space-y-2.5">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Assignment Type</label>
                        <?php
                        $types = ['ALL' => 'All Types', 'COLLEGE' => 'College Training', 'PLACEMENT' => 'Placement Drive Prep', 'WORKSHOP' => 'Workshop / Bootcamp', 'CORPORATE' => 'Corporate Training'];
                        foreach ($types as $tk => $tv): ?>
                            <label class="flex items-center gap-2.5 text-xs font-medium text-slate-700 cursor-pointer">
                                <input type="radio" name="type" value="<?= $tk ?>" <?= $selectedType === $tk ? 'checked' : '' ?> onchange="this.form.submit()" class="text-blue-600">
                                <span><?= $tv ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </form>
            </aside>

            <!-- Results Canvas -->
            <div class="lg:col-span-9 space-y-6">
                <!-- Search Bar -->
                <form method="GET" action="/opportunities.php" class="relative w-full">
                    <input type="hidden" name="mode" value="<?= htmlspecialchars($selectedMode) ?>">
                    <input type="hidden" name="domain" value="<?= htmlspecialchars($selectedDomain) ?>">
                    <input type="hidden" name="location" value="<?= htmlspecialchars($selectedLocation) ?>">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($selectedType) ?>">

                    <div class="relative flex items-center">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by technology (e.g. Python, Java, AWS, VLSI) or city..." class="w-full pl-5 pr-24 py-3.5 rounded-2xl border border-slate-200/90 bg-white text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none text-xs sm:text-sm shadow-card">
                        <div class="absolute right-2 flex items-center gap-1.5">
                            <?php if (!empty($search)): ?>
                                <a href="/opportunities.php?mode=<?= urlencode($selectedMode) ?>&domain=<?= urlencode($selectedDomain) ?>&location=<?= urlencode($selectedLocation) ?>&type=<?= urlencode($selectedType) ?>" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg transition-colors" title="Clear search text">
                                    <span class="material-symbols-outlined text-[18px]">close</span>
                                </a>
                            <?php endif; ?>
                            <button type="submit" aria-label="Search" class="w-9 h-9 sm:w-10 sm:h-10 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white rounded-xl transition-all shadow-xs flex items-center justify-center cursor-pointer shrink-0" title="Search">
                                <span class="material-symbols-outlined text-[20px] leading-none">search</span>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Count -->
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500 px-1">
                    <span>Showing <strong><?= $totalCount ?></strong> opportunities</span>
                </div>

                <!-- Results List -->
                <?php if ($totalCount === 0): ?>
                    <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center space-y-4 shadow-card">
                        <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto text-slate-400">
                            <span class="material-symbols-outlined text-3xl">search_off</span>
                        </div>
                        <h3 class="font-bold text-lg text-slate-900">No matching training opportunities found</h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">Try clearing your search keyword or relaxing your location and domain filters.</p>
                        <a href="/opportunities.php" class="inline-block bg-slate-900 text-white text-xs font-bold px-6 py-2.5 rounded-xl hover:bg-slate-800">Clear All Filters</a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($opportunities as $opp): 
                            $skills = is_string($opp['skillsRequired']) ? json_decode($opp['skillsRequired'], true) : (array)$opp['skillsRequired'];
                            if (!$skills) $skills = explode(',', (string)$opp['skillsRequired']);
                            $oppId = (string)$opp['_id'];
                        ?>
                            <div class="bg-white rounded-2xl border border-slate-200/90 p-6 md:p-7 shadow-card hover:shadow-card-hover hover:border-blue-400 transition-all duration-300 group">
                                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                                    <div class="space-y-3 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 font-bold text-[11px] px-3 py-1 rounded-full border border-blue-200/60 uppercase">
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                                <?= htmlspecialchars($opp['mode']) ?>
                                            </span>
                                            <span class="bg-slate-100 text-slate-700 font-semibold text-[11px] px-2.5 py-1 rounded-full uppercase">
                                                <?= htmlspecialchars(str_replace('_', ' ', $opp['trainingType'] ?? 'COLLEGE')) ?>
                                            </span>
                                            <span class="text-[11px] font-mono font-bold text-slate-700 bg-slate-100 border border-slate-200 px-2.5 py-0.5 rounded-md shadow-2xs">
                                                ID: <?= htmlspecialchars(getMentryCode('OPPORTUNITY', $opp)) ?>
                                            </span>
                                            <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md">
                                                Active Opening
                                            </span>
                                        </div>

                                        <a href="/opportunity-details.php?id=<?= $oppId ?>" class="block">
                                            <h3 class="font-bold text-lg md:text-xl text-slate-900 group-hover:text-blue-600 transition-colors leading-snug">
                                                <?= htmlspecialchars($opp['title']) ?>
                                            </h3>
                                        </a>

                                        <div class="flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-slate-500 font-medium">
                                            <span class="flex items-center gap-1 text-slate-700 font-semibold">
                                                <span class="material-symbols-outlined text-blue-600 text-base">location_on</span>
                                                <?= htmlspecialchars($opp['city']) ?>, <?= htmlspecialchars($opp['state']) ?>
                                            </span>
                                            <span>•</span>
                                            <span class="flex items-center gap-1">
                                                <span class="material-symbols-outlined text-slate-400 text-base">calendar_today</span>
                                                Starts <?= formatDate($opp['startDate']) ?>
                                            </span>
                                            <span>•</span>
                                            <span><?= htmlspecialchars($opp['durationDays']) ?> Working Days</span>
                                            <span>•</span>
                                            <span><?= htmlspecialchars($opp['minExperienceYears']) ?>+ Yrs Exp</span>
                                        </div>

                                        <div class="flex flex-wrap gap-1.5 pt-1">
                                            <?php foreach (array_slice($skills, 0, 5) as $skill): ?>
                                                <span class="bg-slate-50 text-slate-700 font-medium text-xs px-2.5 py-1 rounded-md border border-slate-200/80">
                                                    <?= htmlspecialchars(trim($skill)) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="shrink-0 flex lg:flex-col items-center lg:items-end justify-between lg:justify-center gap-4 pt-4 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                                        <div class="text-left lg:text-right">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Daily Honorarium</span>
                                            <p class="font-extrabold text-xl md:text-2xl text-blue-700">
                                                <?= formatINR($opp['dailyRateMin']) ?> – <?= formatINR($opp['dailyRateMax']) ?>
                                            </p>
                                            <p class="text-[11px] text-slate-500">Per Day • Guaranteed Payout</p>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <a href="/opportunity-details.php?id=<?= $oppId ?>" class="px-4 py-2.5 rounded-xl border border-slate-200 hover:border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-bold transition-all">
                                                View Details
                                            </a>
                                            <a href="/opportunity-details.php?id=<?= $oppId ?>" class="bg-slate-900 hover:bg-blue-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-sm flex items-center gap-1 group-hover:bg-blue-600">
                                                Apply Now
                                                <span class="material-symbols-outlined text-base">arrow_forward</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
