<?php
// admin/opportunity-create.php
$pageTitle = "Create Opportunity";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $domain = trim($_POST['domain'] ?? 'Programming');
    $mode = trim($_POST['mode'] ?? 'OFFLINE');
    $trainingType = trim($_POST['trainingType'] ?? 'COLLEGE');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? 'Karnataka');
    $startDate = trim($_POST['startDate'] ?? '');
    $durationDays = (int)($_POST['durationDays'] ?? 5);
    $dailyRateMin = (float)($_POST['dailyRateMin'] ?? 5000);
    $dailyRateMax = (float)($_POST['dailyRateMax'] ?? 7000);
    $minExperienceYears = (int)($_POST['minExperienceYears'] ?? 3);
    $skillsRequired = trim($_POST['skillsRequired'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $travelCovered = isset($_POST['travelCovered']) ? true : ($mode !== 'ONLINE');
    $accommodationCovered = isset($_POST['accommodationCovered']) ? true : ($mode !== 'ONLINE');
    $diningCovered = isset($_POST['diningCovered']) ? true : ($mode !== 'ONLINE');

    if (empty($title) || empty($city) || empty($startDate)) {
        $error = "Please fill in all mandatory fields.";
    } else {
        $oppCol = getCollection("Opportunity");
        $jobId = getNextSequentialMentryId('OPPORTUNITY');

        if ($oppCol) {
            $insertResult = $oppCol->insertOne([
                'jobId' => $jobId,
                'mentryId' => $jobId,
                'title' => $title,
                'domain' => $domain,
                'mode' => $mode,
                'trainingType' => $trainingType,
                'city' => $city,
                'state' => $state,
                'startDate' => new MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000),
                'durationDays' => $durationDays,
                'dailyRateMin' => $dailyRateMin,
                'dailyRateMax' => $dailyRateMax,
                'minExperienceYears' => $minExperienceYears,
                'skillsRequired' => json_encode(array_map('trim', explode(',', $skillsRequired))),
                'description' => $description,
                'travelCovered' => $travelCovered,
                'accommodationCovered' => $accommodationCovered,
                'diningCovered' => $diningCovered,
                'status' => 'PUBLISHED',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            $newOppId = (string)$insertResult->getInsertedId();

            // Send notifications to matching trainers
            require_once __DIR__ . '/../includes/notifications.php';
            notifyMatchingTrainersForOpportunity($newOppId);

            header("Location: /admin/opportunity-view.php?id=" . $newOppId . "&created=1");
            exit();
        }
    }
}

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <a href="/admin/opportunities.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-blue-600">
        <span class="material-symbols-outlined text-base">arrow_back</span>
        Back to Opportunities
    </a>

    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Create College Training Opportunity</h1>
        <p class="text-xs text-slate-500 mt-0.5">Publish a structured training requirement for automatic matching across the trainer network.</p>
    </div>

    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/opportunity-create.php" class="bg-white p-8 rounded-3xl border border-slate-200/90 shadow-card space-y-6">
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Opportunity Title *</label>
                <input type="text" name="title" required placeholder="e.g. 5-Day Python Full Stack & DSA Workshop for 120 Students" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none font-bold text-slate-900">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Curriculum Domain</label>
                <select name="domain" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none">
                    <option value="Programming">Programming & Software</option>
                    <option value="Data Science">Data Science & AI/ML</option>
                    <option value="Cloud">Cloud & DevOps</option>
                    <option value="Database">Databases & Big Data</option>
                    <option value="VLSI">VLSI & Embedded</option>
                    <option value="Cybersecurity">Cybersecurity</option>
                    <option value="Aptitude">Aptitude & Placement</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Training Mode</label>
                <select name="mode" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none">
                    <option value="OFFLINE">Offline (On-Campus)</option>
                    <option value="ONLINE">Online / Virtual</option>
                    <option value="HYBRID">Hybrid</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">City *</label>
                <input type="text" name="city" required placeholder="e.g. Bangalore" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">State *</label>
                <input type="text" name="state" required value="Karnataka" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Start Date *</label>
                <input type="date" name="startDate" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Duration (Working Days) *</label>
                <input type="number" name="durationDays" value="5" min="1" max="60" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Min Daily Rate (₹)</label>
                <input type="number" name="dailyRateMin" value="5000" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none font-bold text-blue-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Max Daily Rate (₹)</label>
                <input type="number" name="dailyRateMax" value="7500" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none font-bold text-blue-700">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Skills & Topics (Comma separated)</label>
                <input type="text" name="skillsRequired" value="Python, Django, PostgreSQL, REST APIs, Git" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Program Syllabus Description</label>
                <textarea name="description" rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">High-impact pre-placement workshop focused on core data structures, backend frameworks, and hands-on coding labs.</textarea>
            </div>

            <!-- Campus Logistics Options -->
            <div class="sm:col-span-2 bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-2">
                <label class="block text-xs font-bold text-slate-900 uppercase">Campus Logistics Covered</label>
                <p class="text-[11px] text-slate-500 mb-2">Select the logistics perks arranged for the trainer by Mentry / host institution:</p>
                <div class="flex flex-wrap gap-4 text-xs font-semibold text-slate-700">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="travelCovered" value="1" checked class="w-4 h-4 text-blue-600 rounded border-slate-300">
                        <span>✓ Travel Logistics Covered</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="accommodationCovered" value="1" checked class="w-4 h-4 text-blue-600 rounded border-slate-300">
                        <span>✓ On-Campus Accommodation</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="diningCovered" value="1" checked class="w-4 h-4 text-blue-600 rounded border-slate-300">
                        <span>✓ Guest House Dining</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="pt-4 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-8 py-3.5 rounded-xl shadow-md transition-all">
                Publish Opportunity
            </button>
        </div>
    </form>
</div>

</main>
</div>
</body>
</html>
