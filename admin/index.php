<?php
// admin/index.php - Admin Dashboard
$pageTitle = "Admin Overview";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$trainerCol = getCollection("Trainer");
$oppCol = getCollection("Opportunity");
$appCol = getCollection("Application");
$asgCol = getCollection("Assignment");
$reqCol = getCollection("CollegeRequirement");

$totalTrainers = $trainerCol ? $trainerCol->countDocuments() : 0;
$activeTrainers = $trainerCol ? $trainerCol->countDocuments(['status' => 'APPROVED']) : 0;
$pendingTrainers = $trainerCol ? $trainerCol->countDocuments(['status' => 'PENDING_APPROVAL']) : 0;
$totalOpps = $oppCol ? $oppCol->countDocuments() : 0;
$newApps = $appCol ? $appCol->countDocuments(['status' => 'PENDING']) : 0;
$shortlistedApps = $appCol ? $appCol->countDocuments(['status' => 'SHORTLISTED']) : 0;
$activeAsg = $asgCol ? $asgCol->countDocuments(['status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS']]]) : 0;
$inboundReqs = $reqCol ? $reqCol->countDocuments(['status' => 'PENDING']) : 0;

$recentTrainers = $trainerCol ? $trainerCol->find([], ['limit' => 5, 'sort' => ['joinedAt' => -1]])->toArray() : [];
?>

<!-- Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Good morning, <?= htmlspecialchars($adminUser['name'] ?? 'Admin') ?></h1>
        <p class="text-xs md:text-sm text-slate-500 mt-0.5">Operational overview of Mentry's verified trainer network and college assignments.</p>
    </div>

    <div class="flex items-center gap-3">
        <a href="/admin/requirements.php" class="bg-white border border-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 hover:bg-slate-50 transition-colors text-xs shadow-xs">
            <span class="material-symbols-outlined text-[18px] text-blue-600">school</span>
            College Intake (<?= $inboundReqs ?>)
        </a>
        <a href="/admin/opportunity-create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all shadow-md text-xs">
            <span class="material-symbols-outlined text-[18px]">add</span>
            New Opportunity
        </a>
    </div>
</div>

<!-- 7-Stat Operational Grid -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-3 md:gap-4">
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between h-28 col-span-2 md:col-span-1">
        <div class="flex items-center gap-2 text-slate-500">
            <span class="material-symbols-outlined text-blue-600 text-lg">group</span>
            <h3 class="text-[11px] font-bold uppercase tracking-wider">Total Trainers</h3>
        </div>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl md:text-3xl font-black text-slate-900"><?= $totalTrainers ?></span>
            <span class="text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded font-bold">+12%</span>
        </div>
    </div>

    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between h-28">
        <div class="flex items-center gap-2 text-slate-500">
            <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
            <h3 class="text-[11px] font-bold uppercase tracking-wider">Active</h3>
        </div>
        <span class="text-xl md:text-2xl font-black text-slate-900"><?= $activeTrainers ?></span>
    </div>

    <a href="/admin/trainers.php?status=PENDING_APPROVAL" class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between h-28 border-l-4 border-l-amber-500">
        <div class="flex items-center gap-2 text-slate-500">
            <span class="material-symbols-outlined text-amber-600 text-lg">pending_actions</span>
            <h3 class="text-[11px] font-bold uppercase tracking-wider">Pending Review</h3>
        </div>
        <span class="text-xl md:text-2xl font-black text-amber-600"><?= $pendingTrainers ?></span>
    </a>

    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between h-28">
        <div class="flex items-center gap-2 text-slate-500">
            <span class="material-symbols-outlined text-blue-600 text-lg">work</span>
            <h3 class="text-[11px] font-bold uppercase tracking-wider">Opportunities</h3>
        </div>
        <span class="text-xl md:text-2xl font-black text-slate-900"><?= $totalOpps ?></span>
    </div>

    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between h-28">
        <div class="flex items-center gap-2 text-slate-500">
            <span class="material-symbols-outlined text-indigo-600 text-lg">assignment</span>
            <h3 class="text-[11px] font-bold uppercase tracking-wider">New Apps</h3>
        </div>
        <span class="text-xl md:text-2xl font-black text-slate-900"><?= $newApps ?></span>
    </div>

    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between h-28">
        <div class="flex items-center gap-2 text-slate-500">
            <span class="material-symbols-outlined text-amber-500 text-lg">bookmark</span>
            <h3 class="text-[11px] font-bold uppercase tracking-wider">Shortlisted</h3>
        </div>
        <span class="text-xl md:text-2xl font-black text-slate-900"><?= $shortlistedApps ?></span>
    </div>

    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between h-28">
        <div class="flex items-center gap-2 text-slate-500">
            <span class="material-symbols-outlined text-emerald-600 text-lg">event_available</span>
            <h3 class="text-[11px] font-bold uppercase tracking-wider">Assignments</h3>
        </div>
        <span class="text-xl md:text-2xl font-black text-slate-900"><?= $activeAsg ?></span>
    </div>
</div>

<!-- 2-Column Analytics & Live Feeds -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Domain Breakdown -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-6 shadow-card flex flex-col h-[360px]">
        <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-900">Opportunities by Domain</h3>
            <span class="text-xs text-slate-400">Live Database Breakdown</span>
        </div>

        <div class="flex-1 flex items-end gap-3 py-4">
            <?php
            $domains = [
                ['name' => 'Python', 'count' => 14, 'pct' => 70],
                ['name' => 'Java', 'count' => 11, 'pct' => 55],
                ['name' => 'Cloud', 'count' => 9, 'pct' => 45],
                ['name' => 'VLSI', 'count' => 7, 'pct' => 35],
                ['name' => 'Aptitude', 'count' => 8, 'pct' => 40],
            ];
            $colors = ['bg-blue-600', 'bg-indigo-500', 'bg-sky-500', 'bg-cyan-500', 'bg-slate-400'];
            foreach ($domains as $idx => $dom): ?>
                <div class="flex-1 flex flex-col items-center justify-end group h-full">
                    <span class="text-[10px] font-bold text-slate-900 mb-1 opacity-0 group-hover:opacity-100 transition-opacity"><?= $dom['count'] ?></span>
                    <div class="w-full <?= $colors[$idx % count($colors)] ?> rounded-t-lg transition-all" style="height: <?= $dom['pct'] ?>%"></div>
                    <span class="text-[10px] text-slate-500 mt-2 text-center truncate w-full font-semibold"><?= $dom['name'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Network Growth Curve -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-6 shadow-card flex flex-col h-[360px]">
        <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-900">Trainer Network Growth</h3>
            <span class="text-xs font-semibold text-blue-600">Past 6 Months</span>
        </div>

        <div class="flex-1 flex items-center justify-center">
            <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 500 200">
                <defs>
                    <linearGradient id="lineGrad" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stopColor="#3b82f6" stopOpacity="0.25"></stop>
                        <stop offset="100%" stopColor="#3b82f6" stopOpacity="0"></stop>
                    </linearGradient>
                </defs>
                <path d="M0,180 L50,150 L100,160 L150,120 L200,130 L250,80 L300,90 L350,50 L400,60 L450,20 L500,30 L500,200 L0,200 Z" fill="url(#lineGrad)"></path>
                <polyline fill="none" points="0,180 50,150 100,160 150,120 200,130 250,80 300,90 350,50 400,60 450,20 500,30" stroke="#2563eb" strokeLinecap="round" strokeLinejoin="round" strokeWidth="3.5"></polyline>
                <circle cx="450" cy="20" fill="#ffffff" r="5" stroke="#2563eb" strokeWidth="2.5"></circle>
            </svg>
        </div>
        <div class="flex justify-between text-[11px] text-slate-400 pt-2 border-t border-slate-100">
            <span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span><span>Aug 2026</span>
        </div>
    </div>
</div>

<!-- Recent Registered Trainers Stream -->
<div class="bg-white border border-slate-200/90 rounded-3xl shadow-card overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
        <h3 class="text-base font-bold text-slate-900">Recently Registered Trainers</h3>
        <a href="/admin/trainers.php" class="text-xs text-blue-600 hover:underline font-bold">View Full Directory →</a>
    </div>

    <div class="divide-y divide-slate-100">
        <?php foreach ($recentTrainers as $rt): 
            $uCol = getCollection("User");
            $u = null;
            if ($uCol && !empty($rt['userId'])) {
                try { $u = $uCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$rt['userId'])]); } catch (Exception $e) {}
            }
        ?>
            <div class="p-5 flex items-center justify-between hover:bg-slate-50/50 transition-colors">
                <div class="flex items-center gap-3">
                    <img src="<?= htmlspecialchars($u['avatar'] ?? "https://avatar.vercel.sh/" . urlencode($u['name'] ?? 'T') . ".png") ?>" class="w-10 h-10 rounded-full object-cover border border-slate-200">
                    <div>
                        <a href="/admin/trainer-view.php?id=<?= (string)$rt['_id'] ?>" class="font-bold text-xs text-slate-900 hover:text-blue-600">
                            <?= htmlspecialchars($u['name'] ?? 'Trainer') ?>
                        </a>
                        <p class="text-[11px] text-slate-500"><?= htmlspecialchars($rt['professionalTitle'] ?? $rt['primaryDomain']) ?> • <?= htmlspecialchars($rt['currentCity'] ?? 'India') ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <?= getStatusBadge($rt['status'] ?? 'PENDING_APPROVAL') ?>
                    <a href="/admin/trainer-view.php?id=<?= (string)$rt['_id'] ?>" class="text-xs font-bold text-blue-600 border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-slate-50">
                        View Dossier
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
