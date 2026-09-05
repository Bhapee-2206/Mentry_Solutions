<?php
// admin/opportunity-edit.php - Edit Opportunity
$pageTitle = "Edit Opportunity";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();

$id = $_GET['id'] ?? '';
$oppCol = getCollection("Opportunity");

$opp = null;
if (!empty($id)) {
    try {
        $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    } catch (Exception $e) {}
}

if (!$opp) {
    header("Location: /admin/opportunities.php");
    exit();
}

require_once __DIR__ . '/includes/sidebar.php';

$oppId = (string)$opp['_id'];
$skills = [];
if (!empty($opp['skillsRequired'])) {
    $skills = is_array($opp['skillsRequired']) ? $opp['skillsRequired'] : json_decode($opp['skillsRequired'], true);
    if (!is_array($skills)) {
        $skills = explode(',', $opp['skillsRequired']);
    }
}
$skillsString = implode(', ', (array)$skills);
$startDateVal = '';
if (!empty($opp['startDate'])) {
    $sd = $opp['startDate'];
    if ($sd instanceof MongoDB\BSON\UTCDateTime) {
        $startDateVal = $sd->toDateTime()->format('Y-m-d');
    } elseif (is_numeric($sd)) {
        $ts = ($sd > 20000000000) ? round($sd / 1000) : (int)$sd;
        $startDateVal = date('Y-m-d', $ts);
    } elseif (is_array($sd) || is_object($sd)) {
        $arr = (array)$sd;
        if (isset($arr['$date'])) {
            $raw = is_array($arr['$date']) ? ($arr['$date']['$numberLong'] ?? 0) : $arr['$date'];
            $ts = is_numeric($raw) && $raw > 20000000000 ? round($raw / 1000) : (int)$raw;
            $startDateVal = date('Y-m-d', $ts);
        }
    } elseif (is_string($sd)) {
        $startDateVal = date('Y-m-d', strtotime($sd));
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="/admin/opportunity-view.php?id=<?= $oppId ?>" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-blue-600">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Opportunity View
        </a>

        <form action="/actions/delete-opportunity.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this opportunity and its applications?');">
            <input type="hidden" name="id" value="<?= $oppId ?>">
            <button type="submit" class="text-rose-600 hover:bg-rose-50 border border-rose-200 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">delete</span>
                Delete Opportunity
            </button>
        </form>
    </div>

    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Edit Training Opportunity</h1>
        <p class="text-xs text-slate-500 mt-0.5">Job ID: <span class="font-mono font-bold text-slate-700"><?= htmlspecialchars($opp['jobId'] ?? $oppId) ?></span></p>
    </div>

    <form method="POST" action="/actions/update-opportunity.php" class="bg-white p-8 rounded-3xl border border-slate-200/90 shadow-card space-y-6">
        <input type="hidden" name="id" value="<?= $oppId ?>">

        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Opportunity Title *</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($opp['title'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none font-bold text-slate-900">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Status</label>
                <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-bold text-slate-800 outline-none">
                    <option value="PUBLISHED" <?= ($opp['status'] ?? '') === 'PUBLISHED' ? 'selected' : '' ?>>PUBLISHED (Open for Trainer Applications)</option>
                    <option value="CLOSED" <?= ($opp['status'] ?? '') === 'CLOSED' ? 'selected' : '' ?>>CLOSED (Closed for Applications / Position Filled)</option>
                    <option value="MATCHED" <?= ($opp['status'] ?? '') === 'MATCHED' ? 'selected' : '' ?>>MATCHED (Trainer Assigned)</option>
                    <option value="IN_PROGRESS" <?= ($opp['status'] ?? '') === 'IN_PROGRESS' ? 'selected' : '' ?>>IN PROGRESS (Workshop Live)</option>
                    <option value="COMPLETED" <?= ($opp['status'] ?? '') === 'COMPLETED' ? 'selected' : '' ?>>COMPLETED</option>
                    <option value="CANCELLED" <?= ($opp['status'] ?? '') === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
                    <option value="DRAFT" <?= ($opp['status'] ?? '') === 'DRAFT' ? 'selected' : '' ?>>DRAFT (Hidden)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Curriculum Domain</label>
                <select name="domain" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none">
                    <option value="Programming" <?= ($opp['domain'] ?? '') === 'Programming' ? 'selected' : '' ?>>Programming & Software</option>
                    <option value="Data Science" <?= ($opp['domain'] ?? '') === 'Data Science' ? 'selected' : '' ?>>Data Science & AI/ML</option>
                    <option value="Cloud" <?= ($opp['domain'] ?? '') === 'Cloud' ? 'selected' : '' ?>>Cloud & DevOps</option>
                    <option value="Database" <?= ($opp['domain'] ?? '') === 'Database' ? 'selected' : '' ?>>Databases & Big Data</option>
                    <option value="VLSI" <?= ($opp['domain'] ?? '') === 'VLSI' ? 'selected' : '' ?>>VLSI & Embedded</option>
                    <option value="Cybersecurity" <?= ($opp['domain'] ?? '') === 'Cybersecurity' ? 'selected' : '' ?>>Cybersecurity</option>
                    <option value="Aptitude" <?= ($opp['domain'] ?? '') === 'Aptitude' ? 'selected' : '' ?>>Aptitude & Placement</option>
                    <option value="Management" <?= ($opp['domain'] ?? '') === 'Management' ? 'selected' : '' ?>>Soft Skills & Leadership</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Training Mode</label>
                <select name="mode" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none">
                    <option value="OFFLINE" <?= ($opp['mode'] ?? '') === 'OFFLINE' ? 'selected' : '' ?>>Offline (On-Campus)</option>
                    <option value="ONLINE" <?= ($opp['mode'] ?? '') === 'ONLINE' ? 'selected' : '' ?>>Online / Virtual</option>
                    <option value="HYBRID" <?= ($opp['mode'] ?? '') === 'HYBRID' ? 'selected' : '' ?>>Hybrid</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Institution / College Name</label>
                <input type="text" name="collegeName" value="<?= htmlspecialchars($opp['collegeName'] ?? '') ?>" placeholder="e.g. RV College of Engineering" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">City *</label>
                <input type="text" name="city" required value="<?= htmlspecialchars($opp['city'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">State *</label>
                <input type="text" name="state" required value="<?= htmlspecialchars($opp['state'] ?? 'Karnataka') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Start Date *</label>
                <input type="date" name="startDate" required value="<?= htmlspecialchars($startDateVal) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Duration (Working Days) *</label>
                <input type="number" name="durationDays" value="<?= htmlspecialchars($opp['durationDays'] ?? 5) ?>" min="1" max="180" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Min Daily Rate (₹)</label>
                <input type="number" name="dailyRateMin" value="<?= htmlspecialchars($opp['dailyRateMin'] ?? 5000) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none font-bold text-blue-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Max Daily Rate (₹)</label>
                <input type="number" name="dailyRateMax" value="<?= htmlspecialchars($opp['dailyRateMax'] ?? 7000) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none font-bold text-blue-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Student Batch Size</label>
                <input type="number" name="studentCount" value="<?= htmlspecialchars($opp['studentCount'] ?? 100) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Min Trainer Experience (Years)</label>
                <input type="number" name="minExperienceYears" value="<?= htmlspecialchars($opp['minExperienceYears'] ?? 3) ?>" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Skills & Topics (Comma separated)</label>
                <input type="text" name="skillsRequired" value="<?= htmlspecialchars($skillsString) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Program Syllabus & Description</label>
                <textarea name="description" rows="5" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none"><?= htmlspecialchars($opp['description'] ?? '') ?></textarea>
            </div>

            <!-- Campus Logistics Options -->
            <div class="sm:col-span-2 bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-2">
                <label class="block text-xs font-bold text-slate-900 uppercase">Campus Logistics Covered</label>
                <p class="text-[11px] text-slate-500 mb-2">Select the logistics perks arranged for the trainer by Mentry / host institution:</p>
                <div class="flex flex-wrap gap-4 text-xs font-semibold text-slate-700">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="travelCovered" value="1" <?= ($opp['travelCovered'] ?? ($opp['mode'] !== 'ONLINE')) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 rounded border-slate-300">
                        <span>✓ Travel Logistics Covered</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="accommodationCovered" value="1" <?= ($opp['accommodationCovered'] ?? ($opp['mode'] !== 'ONLINE')) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 rounded border-slate-300">
                        <span>✓ On-Campus Accommodation</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="diningCovered" value="1" <?= ($opp['diningCovered'] ?? ($opp['mode'] !== 'ONLINE')) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 rounded border-slate-300">
                        <span>✓ Guest House Dining</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="pt-4 flex items-center justify-between border-t border-slate-100">
            <a href="/admin/opportunity-view.php?id=<?= $oppId ?>" class="text-xs font-bold text-slate-500 hover:text-slate-800">
                Cancel
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-8 py-3.5 rounded-xl shadow-md transition-all">
                Save & Update Opportunity
            </button>
        </div>
    </form>
</div>

</main>
</div>
</body>
</html>
