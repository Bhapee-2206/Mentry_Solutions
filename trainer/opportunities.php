<?php
// trainer/opportunities.php
$pageTitle = "Training Opportunities Feed";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$opportunityCol = getCollection("Opportunity");
$applicationCol = getCollection("Application");
$trainerCol = getCollection("Trainer");
$skillCol = getCollection("Skill");

$trainer = $trainerCol ? $trainerCol->findOne(['userId' => $user['id']]) : null;
$trainerId = $trainer ? (string)$trainer['_id'] : '';

$appliedOppIds = [];
if ($applicationCol && $trainerId) {
    $myApps = $applicationCol->find(['trainerId' => $trainerId])->toArray();
    foreach ($myApps as $ma) {
        $appliedOppIds[] = (string)$ma['opportunityId'];
    }
}

// Filter parameters
$search = trim($_GET['search'] ?? '');
$domainFilter = trim($_GET['domain'] ?? 'ALL');
$modeFilter = trim($_GET['mode'] ?? 'ALL');
$statusFilter = trim($_GET['status'] ?? 'ALL');

$conditions = [['status' => 'PUBLISHED']];

if ($domainFilter !== 'ALL') {
    $domainRegex = new MongoDB\BSON\Regex($domainFilter, 'i');
    $conditions[] = [
        '$or' => [
            ['domain' => $domainRegex],
            ['title' => $domainRegex],
            ['skillsRequired' => $domainRegex]
        ]
    ];
}

if ($modeFilter !== 'ALL') {
    $conditions[] = ['mode' => $modeFilter];
}

if (!empty($search)) {
    $searchRegex = new MongoDB\BSON\Regex($search, 'i');
    $conditions[] = [
        '$or' => [
            ['title' => $searchRegex],
            ['city' => $searchRegex],
            ['state' => $searchRegex],
            ['skillsRequired' => $searchRegex],
            ['jobId' => $searchRegex]
        ]
    ];
}

$queryFilter = count($conditions) === 1 ? $conditions[0] : ['$and' => $conditions];

$rawOpportunities = $opportunityCol ? $opportunityCol->find(
    $queryFilter,
    ['sort' => ['createdAt' => -1]]
)->toArray() : [];

// Filter by application status in PHP
$opportunities = [];
foreach ($rawOpportunities as $opp) {
    $oppId = (string)$opp['_id'];
    $hasApplied = in_array($oppId, $appliedOppIds);
    if ($statusFilter === 'NOT_APPLIED' && $hasApplied) continue;
    if ($statusFilter === 'APPLIED' && !$hasApplied) continue;
    $opportunities[] = $opp;
}

$hasActiveFilters = (!empty($search) || $domainFilter !== 'ALL' || $modeFilter !== 'ALL' || $statusFilter !== 'ALL');
$mySkills = ($skillCol && $trainerId) ? $skillCol->find(['trainerId' => $trainerId])->toArray() : [];
require_once __DIR__ . '/../includes/matching_engine.php';
?>

<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Active Training Opportunities</h1>
            <p class="text-xs text-slate-500 mt-0.5">Explore open college assignments matching your domain and apply instantly.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold px-3 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                <?= count($opportunities) ?> Openings Available
            </span>
        </div>
    </div>

    <?php if (isset($_GET['applied'])): ?>
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-xs font-bold text-emerald-800 flex items-center gap-2 shadow-xs">
            <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
            <span>Application submitted successfully! Our academic team will review your proposal and contact you.</span>
        </div>
    <?php endif; ?>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200/90 p-4 shadow-card">
        <form method="GET" action="/trainer/opportunities.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
            <!-- Keyword Search -->
            <div class="lg:col-span-4 relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search title, skills, city, or ID..." class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:bg-white focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04] text-slate-800 font-medium">
            </div>

            <!-- Domain Dropdown -->
            <div class="lg:col-span-3">
                <select name="domain" onchange="this.form.submit()" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:bg-white focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04] text-slate-800 font-medium">
                    <option value="ALL" <?= $domainFilter === 'ALL' ? 'selected' : '' ?>>All Technical Domains</option>
                    <option value="Programming" <?= $domainFilter === 'Programming' ? 'selected' : '' ?>>Programming & Software</option>
                    <option value="Data Science" <?= $domainFilter === 'Data Science' ? 'selected' : '' ?>>Data Science & AI/ML</option>
                    <option value="Cloud" <?= $domainFilter === 'Cloud' ? 'selected' : '' ?>>Cloud & DevOps</option>
                    <option value="VLSI" <?= $domainFilter === 'VLSI' ? 'selected' : '' ?>>VLSI & Embedded</option>
                    <option value="Cybersecurity" <?= $domainFilter === 'Cybersecurity' ? 'selected' : '' ?>>Cybersecurity</option>
                    <option value="Aptitude" <?= $domainFilter === 'Aptitude' ? 'selected' : '' ?>>Aptitude & Placement</option>
                    <option value="Soft Skills" <?= $domainFilter === 'Soft Skills' ? 'selected' : '' ?>>Soft Skills & Personality</option>
                </select>
            </div>

            <!-- Mode Dropdown -->
            <div class="lg:col-span-2">
                <select name="mode" onchange="this.form.submit()" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:bg-white focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04] text-slate-800 font-medium">
                    <option value="ALL" <?= $modeFilter === 'ALL' ? 'selected' : '' ?>>All Modes</option>
                    <option value="OFFLINE" <?= $modeFilter === 'OFFLINE' ? 'selected' : '' ?>>Offline (On-Campus)</option>
                    <option value="ONLINE" <?= $modeFilter === 'ONLINE' ? 'selected' : '' ?>>Online / Virtual</option>
                    <option value="HYBRID" <?= $modeFilter === 'HYBRID' ? 'selected' : '' ?>>Hybrid</option>
                </select>
            </div>

            <!-- Application Status Dropdown -->
            <div class="lg:col-span-2">
                <select name="status" onchange="this.form.submit()" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:bg-white focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04] text-slate-800 font-medium">
                    <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All Openings</option>
                    <option value="NOT_APPLIED" <?= $statusFilter === 'NOT_APPLIED' ? 'selected' : '' ?>>Not Yet Applied</option>
                    <option value="APPLIED" <?= $statusFilter === 'APPLIED' ? 'selected' : '' ?>>Already Applied</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="lg:col-span-1 flex items-center gap-1.5 justify-end">
                <button type="submit" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white p-2 rounded-xl text-xs font-bold transition-colors flex items-center justify-center shadow-xs" title="Filter Results">
                    <span class="material-symbols-outlined text-[18px]">filter_list</span>
                </button>
                <?php if ($hasActiveFilters): ?>
                    <a href="/trainer/opportunities.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 p-2 rounded-xl text-xs font-bold transition-colors flex items-center justify-center shrink-0" title="Clear Filters">
                        <span class="material-symbols-outlined text-[18px]">filter_alt_off</span>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Opportunities List -->
    <div class="space-y-4">
        <?php if (empty($opportunities)): ?>
            <div class="bg-white p-12 rounded-3xl border border-slate-200/90 shadow-card text-center space-y-3">
                <span class="material-symbols-outlined text-4xl text-slate-300">work_off</span>
                <h3 class="font-bold text-sm text-slate-800">No Matching Opportunities Found</h3>
                <p class="text-xs text-slate-500 max-w-md mx-auto">
                    <?= $hasActiveFilters ? 'No opportunities match your current filter criteria. Try clearing or adjusting filters to view more assignments.' : 'New academic and corporate training requirements from universities across India are published frequently. Check back soon or ensure your skill stack is up to date.' ?>
                </p>
                <div class="pt-2 flex items-center justify-center gap-3">
                    <?php if ($hasActiveFilters): ?>
                        <a href="/trainer/opportunities.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-xl transition-colors">
                            Clear Filters
                        </a>
                    <?php endif; ?>
                    <a href="/trainer/expertise.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:underline">
                        Update Verified Skill Stack →
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($opportunities as $opp): 
                $oppId = (string)$opp['_id'];
                $hasApplied = in_array($oppId, $appliedOppIds);
                $skills = is_string($opp['skillsRequired']) ? json_decode($opp['skillsRequired'], true) : (array)$opp['skillsRequired'];
                if (!$skills) $skills = explode(',', (string)$opp['skillsRequired']);

                $match = ($trainer) ? MatchingEngine::evaluateMatch($opp, $trainer, $mySkills) : ['score' => 75];
                $matchScore = $match['score'] ?? 75;

                // Data for inline modal
                $oppDataJson = htmlspecialchars(json_encode([
                    'id' => $oppId,
                    'jobId' => $opp['jobId'] ?? $oppId,
                    'title' => $opp['title'] ?? '',
                    'mode' => $opp['mode'] ?? 'OFFLINE',
                    'trainingType' => $opp['trainingType'] ?? 'COLLEGE',
                    'city' => $opp['city'] ?? '',
                    'state' => $opp['state'] ?? 'India',
                    'durationDays' => $opp['durationDays'] ?? 5,
                    'startDate' => formatDate($opp['startDate'] ?? null),
                    'dailyRateMin' => (float)($opp['dailyRateMin'] ?? 5000),
                    'dailyRateMax' => (float)($opp['dailyRateMax'] ?? 7000),
                    'skills' => array_values(array_filter(array_map('trim', $skills))),
                    'description' => $opp['description'] ?? '',
                    'travelCovered' => $opp['travelCovered'] ?? ($opp['mode'] !== 'ONLINE'),
                    'accommodationCovered' => $opp['accommodationCovered'] ?? ($opp['mode'] !== 'ONLINE'),
                    'diningCovered' => $opp['diningCovered'] ?? ($opp['mode'] !== 'ONLINE'),
                    'matchScore' => $matchScore
                ]), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-card hover:shadow-card-hover transition-all flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                    <div class="space-y-2 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="bg-orange-50 text-[#FE5E04] font-bold text-[10px] px-2.5 py-0.5 rounded-full uppercase"><?= htmlspecialchars($opp['mode']) ?></span>
                            
                            <!-- Personalized Match Badge -->
                            <span class="inline-flex items-center gap-0.5 text-[10px] font-black px-2.5 py-0.5 rounded-full <?= $matchScore >= 80 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-orange-50 text-[#FE5E04] border border-orange-200' ?>">
                                <span class="material-symbols-outlined text-[12px]">bolt</span>
                                <?= $matchScore ?>% Match
                            </span>

                            <span class="text-[11px] font-mono text-slate-400">ID: <?= htmlspecialchars($opp['jobId'] ?? $oppId) ?></span>
                            <?php if ($hasApplied): ?>
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold px-2 py-0.5 rounded-full">Applied</span>
                            <?php endif; ?>
                        </div>
                        <h2 class="font-bold text-base text-slate-900 cursor-pointer hover:text-[#FE5E04] transition-colors block" onclick='openOppModal(<?= $oppDataJson ?>)'>
                            <?= htmlspecialchars($opp['title']) ?>
                        </h2>
                        <p class="text-xs text-slate-500">
                            <?= htmlspecialchars($opp['city']) ?>, <?= htmlspecialchars($opp['state']) ?> • <?= htmlspecialchars($opp['durationDays']) ?> Days • Starts <?= formatDate($opp['startDate']) ?>
                        </p>
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <?php foreach (array_slice($skills, 0, 4) as $s): ?>
                                <span class="bg-slate-50 text-slate-700 text-[11px] px-2 py-0.5 rounded border border-slate-200"><?= htmlspecialchars(trim($s)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="shrink-0 flex lg:flex-col items-center lg:items-end justify-between gap-3 pt-3 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                        <div class="text-left lg:text-right">
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Remuneration</span>
                            <p class="font-black text-lg text-[#FE5E04]"><?= formatINR($opp['dailyRateMin']) ?> – <?= formatINR($opp['dailyRateMax']) ?> / day</p>
                        </div>

                        <?php if ($hasApplied): ?>
                            <a href="/trainer/applications.php" class="bg-slate-100 text-slate-700 text-xs font-bold px-4 py-2 rounded-xl hover:bg-slate-200 transition-colors">
                                View Application
                            </a>
                        <?php else: ?>
                            <button type="button" onclick='openOppModal(<?= $oppDataJson ?>)' class="bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-5 py-2 rounded-xl transition-all shadow-md shadow-orange-500/20 cursor-pointer">
                                View & Apply
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- In-Portal View & Apply Assignment Modal -->
<div id="trainerOppModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-slate-200 animate-fadeIn">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
            <div class="flex items-center gap-2">
                <span id="modalModeBadge" class="bg-orange-50 text-[#FE5E04] font-bold text-[10px] px-2.5 py-0.5 rounded-full uppercase"></span>
                <span id="modalIdBadge" class="text-[11px] font-mono text-slate-400"></span>
            </div>
            <button type="button" onclick="closeOppModal()" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg cursor-pointer">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="p-6 overflow-y-auto space-y-6">
            <div>
                <h3 id="modalTitle" class="text-xl font-extrabold text-slate-900 leading-tight"></h3>
                <p id="modalSub" class="text-xs text-slate-500 mt-1"></p>
            </div>

            <!-- Rate & Logistics Grid -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80">
                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Standard Remuneration</span>
                    <p id="modalRate" class="font-black text-base text-[#FE5E04] mt-0.5"></p>
                    <span class="text-[10px] text-slate-400">Per Day • Guaranteed Payout</span>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80">
                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Logistics Coverage</span>
                    <div id="modalLogistics" class="flex flex-wrap gap-1 mt-1 text-[11px] font-semibold text-emerald-700"></div>
                </div>
            </div>

            <!-- Skills Required -->
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1.5">Required Skill Competencies</span>
                <div id="modalSkills" class="flex flex-wrap gap-1.5"></div>
            </div>

            <!-- Description -->
            <div id="modalDescSection" class="hidden">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Requirement Scope & Curriculum</span>
                <p id="modalDesc" class="text-xs text-slate-600 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-200/70 whitespace-pre-line"></p>
            </div>

            <!-- Application Form -->
            <form action="/actions/apply.php" method="POST" class="space-y-4 pt-4 border-t border-slate-100">
                <input type="hidden" id="modalOppId" name="opportunityId" value="">

                <div class="bg-orange-50/50 border border-orange-200/60 p-4 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-slate-900 block">Your Proposed Daily Rate (₹)</span>
                            <span class="text-[11px] text-slate-500">Mentry handles invoicing and guarantees timely disbursement.</span>
                        </div>
                        <input type="number" id="modalProposedRate" name="proposedDailyRate" required class="w-28 text-right font-black text-sm bg-white border border-slate-300 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-[#FE5E04]/20 outline-none text-[#FE5E04]">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Trainer Availability & Note to Academic Team (Optional)</label>
                    <textarea name="message" rows="3" placeholder="Confirm availability dates, relevant batch experience, or custom notes..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-[#FE5E04]/20 outline-none"></textarea>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" onclick="closeOppModal()" class="flex-1 border border-slate-200 text-slate-700 font-bold text-xs py-3 rounded-xl hover:bg-slate-50 transition-colors cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md shadow-orange-500/20 cursor-pointer">
                        Submit Application Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openOppModal(data) {
    document.getElementById('modalOppId').value = data.id;
    document.getElementById('modalTitle').textContent = data.title;
    document.getElementById('modalModeBadge').textContent = data.mode;
    document.getElementById('modalIdBadge').textContent = 'ID: ' + data.jobId;
    document.getElementById('modalSub').textContent = data.city + ', ' + data.state + ' • ' + data.durationDays + ' Days • Starts ' + data.startDate;
    document.getElementById('modalRate').textContent = '₹' + Number(data.dailyRateMin).toLocaleString('en-IN') + ' – ₹' + Number(data.dailyRateMax).toLocaleString('en-IN');
    document.getElementById('modalProposedRate').value = data.dailyRateMin;

    // Logistics badges
    const logContainer = document.getElementById('modalLogistics');
    logContainer.innerHTML = '';
    if (data.travelCovered) logContainer.innerHTML += '<span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded">✓ Travel</span> ';
    if (data.accommodationCovered) logContainer.innerHTML += '<span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded">✓ Accommodation</span> ';
    if (data.diningCovered) logContainer.innerHTML += '<span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded">✓ Dining</span> ';
    if (!data.travelCovered && !data.accommodationCovered && !data.diningCovered) {
        logContainer.innerHTML = '<span class="text-slate-400 text-xs">Standard Assignment</span>';
    }

    // Skills
    const skillsContainer = document.getElementById('modalSkills');
    skillsContainer.innerHTML = '';
    (data.skills || []).forEach(s => {
        if (s) skillsContainer.innerHTML += '<span class="bg-slate-100 text-slate-700 text-xs px-2.5 py-1 rounded-lg border border-slate-200">' + s + '</span>';
    });

    // Description
    const descSec = document.getElementById('modalDescSection');
    if (data.description && data.description.trim()) {
        document.getElementById('modalDesc').textContent = data.description;
        descSec.classList.remove('hidden');
    } else {
        descSec.classList.add('hidden');
    }

    document.getElementById('trainerOppModal').classList.remove('hidden');
}

function closeOppModal() {
    document.getElementById('trainerOppModal').classList.add('hidden');
}

// Close on backdrop click
document.getElementById('trainerOppModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeOppModal();
});
</script>

</main>
</div>
</body>
</html>

