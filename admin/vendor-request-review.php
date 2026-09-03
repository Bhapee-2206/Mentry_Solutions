<?php
// admin/vendor-request-review.php - Admin Review, Price Adjustment & Approval
$pageTitle = "Review Vendor Demand";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$id = $_GET['id'] ?? '';
$reqCol = getCollection("VendorRequest");
$oppCol = getCollection("Opportunity");

$req = null;
if (!empty($id)) {
    try {
        $req = $reqCol->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    } catch (Exception $e) {}
}

if (!$req) {
    header("Location: /admin/vendor-requests.php");
    exit();
}

$reqId = (string)$req['_id'];
$isConverted = ($req['status'] ?? '') === 'APPROVED_PUBLISHED';

$skills = [];
if (!empty($req['skillsRequired'])) {
    $skills = is_array($req['skillsRequired']) ? $req['skillsRequired'] : json_decode($req['skillsRequired'], true);
    if (!is_array($skills)) {
        $skills = explode(',', $req['skillsRequired']);
    }
}
$skillsString = implode(', ', (array)$skills);

$startDateVal = '';
if (!empty($req['startDate']) && $req['startDate'] instanceof MongoDB\BSON\UTCDateTime) {
    $startDateVal = $req['startDate']->toDateTime()->format('Y-m-d');
} elseif (!empty($req['startDate']) && is_string($req['startDate'])) {
    $startDateVal = date('Y-m-d', strtotime($req['startDate']));
}

// Default recommended trainer payout based on vendor's offered budget (typically 70-80%)
$vendorBudget = (float)($req['budgetPerDay'] ?? 8000);
$defaultMinRate = max(4000, round($vendorBudget * 0.70 / 500) * 500);
$defaultMaxRate = max(5000, round($vendorBudget * 0.85 / 500) * 500);
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="/admin/vendor-requests.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-blue-600">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Vendor Demands List
        </a>

        <div class="flex items-center gap-2">
            <span class="text-xs font-mono text-slate-400">Demand ID: <?= substr($reqId, -8) ?></span>
            <?= getStatusBadge($req['status'] ?? 'PENDING_ADMIN_REVIEW') ?>
        </div>
    </div>

    <!-- Top Vendor Info & Contact Banner -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-8 shadow-card space-y-6">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-6 border-b border-slate-100">
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <span class="bg-indigo-50 text-indigo-700 font-bold text-[11px] px-2.5 py-0.5 rounded-full uppercase">Vendor Client Demand</span>
                    <span class="text-slate-400 text-xs font-medium">Submitted <?= formatDate($req['createdAt'] ?? null) ?></span>
                </div>

                <h1 class="text-2xl md:text-3xl font-black text-slate-900 leading-tight"><?= htmlspecialchars($req['title']) ?></h1>
                <p class="text-xs text-slate-600 font-medium">
                    Target Client: <strong class="text-slate-900"><?= htmlspecialchars($req['institutionName']) ?></strong> • 
                    Campus: <?= htmlspecialchars($req['city']) ?>, <?= htmlspecialchars($req['state'] ?? 'India') ?> • 
                    Duration: <?= htmlspecialchars($req['durationDays'] ?? 5) ?> Days • 
                    Batch Size: <?= htmlspecialchars($req['studentCount'] ?? 100) ?> Students
                </p>
            </div>

            <!-- Client's Offered Budget -->
            <div class="bg-indigo-50/80 border border-indigo-200/80 p-4 rounded-2xl min-w-[220px] text-left md:text-right shrink-0 space-y-0.5">
                <span class="text-[10px] text-indigo-900 uppercase font-black tracking-wider block">Client Offered Budget</span>
                <p class="text-2xl font-black text-indigo-700"><?= formatINR($vendorBudget) ?></p>
                <span class="text-[10px] text-indigo-600 font-semibold block">per instructional day</span>
            </div>
        </div>

        <!-- Coordinator Contact Details -->
        <div class="grid sm:grid-cols-3 gap-4 bg-slate-50 p-4 rounded-2xl text-xs border border-slate-100">
            <div>
                <span class="text-slate-400 block font-bold uppercase text-[10px]">Partner Organization</span>
                <p class="font-bold text-slate-900 mt-0.5"><?= htmlspecialchars($req['vendorName'] ?? 'Partner') ?></p>
            </div>
            <div>
                <span class="text-slate-400 block font-bold uppercase text-[10px]">Official Work Email</span>
                <a href="mailto:<?= htmlspecialchars($req['vendorContactEmail'] ?? '') ?>" class="font-bold text-blue-600 hover:underline mt-0.5 block">
                    <?= htmlspecialchars($req['vendorContactEmail'] ?? 'N/A') ?>
                </a>
            </div>
            <div>
                <span class="text-slate-400 block font-bold uppercase text-[10px]">Contact Phone</span>
                <a href="tel:<?= htmlspecialchars($req['vendorContactPhone'] ?? '') ?>" class="font-bold text-blue-600 hover:underline mt-0.5 block flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px]">call</span>
                    <?= htmlspecialchars($req['vendorContactPhone'] ?? 'N/A') ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Review, Price Configuration & Approval Form -->
    <form action="/actions/process-vendor-request.php" method="POST" class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-8 space-y-6">
        <input type="hidden" name="requestId" value="<?= $reqId ?>">

        <div class="border-b border-slate-100 pb-4">
            <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600">tune</span>
                Commercial Price Adjustment & Publishing Configuration
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Adjust the daily honorarium offered to trainers on the live opportunity board to control commercial margin and market competitiveness.</p>
        </div>

        <!-- Pricing Adjustment Section (Key Feature) -->
        <div class="p-6 bg-gradient-to-r from-blue-50/70 to-indigo-50/70 border border-blue-200 rounded-2xl space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-black uppercase text-blue-900 tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px] text-blue-600">payments</span>
                    Commercial Remuneration Tuning
                </span>
                <span class="text-[11px] font-bold text-slate-600 bg-white px-2.5 py-1 rounded-lg border border-slate-200">
                    Client Budget: <strong><?= formatINR($vendorBudget) ?>/day</strong>
                </span>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                        Trainer Minimum Daily Rate (₹) *
                    </label>
                    <input type="number" name="dailyRateMin" required value="<?= htmlspecialchars($req['adjustedDailyRateMin'] ?? $defaultMinRate) ?>" class="w-full bg-white border border-blue-300 rounded-xl p-3 text-xs font-black text-blue-700 focus:ring-2 focus:ring-blue-500/20 outline-none">
                    <span class="text-[10px] text-slate-500 mt-1 block">Visible to trainers on public opportunities feed as base honorarium.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                        Trainer Maximum Daily Rate (₹) *
                    </label>
                    <input type="number" name="dailyRateMax" required value="<?= htmlspecialchars($req['adjustedDailyRateMax'] ?? $defaultMaxRate) ?>" class="w-full bg-white border border-blue-300 rounded-xl p-3 text-xs font-black text-blue-700 focus:ring-2 focus:ring-blue-500/20 outline-none">
                    <span class="text-[10px] text-slate-500 mt-1 block">Maximum compensation ceiling for senior lead faculty.</span>
                </div>
            </div>
        </div>

        <!-- Scope & Details Customization -->
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Live Opportunity Title *</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($req['title']) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Curriculum Domain</label>
                <select name="domain" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white">
                    <option value="Programming" <?= ($req['domain'] ?? '') === 'Programming' ? 'selected' : '' ?>>Programming & Software</option>
                    <option value="Data Science" <?= ($req['domain'] ?? '') === 'Data Science' ? 'selected' : '' ?>>Data Science & AI/ML</option>
                    <option value="Cloud" <?= ($req['domain'] ?? '') === 'Cloud' ? 'selected' : '' ?>>Cloud & DevOps</option>
                    <option value="VLSI" <?= ($req['domain'] ?? '') === 'VLSI' ? 'selected' : '' ?>>VLSI & Embedded</option>
                    <option value="Cybersecurity" <?= ($req['domain'] ?? '') === 'Cybersecurity' ? 'selected' : '' ?>>Cybersecurity</option>
                    <option value="Aptitude" <?= ($req['domain'] ?? '') === 'Aptitude' ? 'selected' : '' ?>>Aptitude & Soft Skills</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Training Mode</label>
                <select name="mode" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white">
                    <option value="OFFLINE" <?= ($req['mode'] ?? '') === 'OFFLINE' ? 'selected' : '' ?>>Offline (On-Campus Labs)</option>
                    <option value="ONLINE" <?= ($req['mode'] ?? '') === 'ONLINE' ? 'selected' : '' ?>>Online / Virtual</option>
                    <option value="HYBRID" <?= ($req['mode'] ?? '') === 'HYBRID' ? 'selected' : '' ?>>Hybrid</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Campus City *</label>
                <input type="text" name="city" required value="<?= htmlspecialchars($req['city']) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Campus State *</label>
                <input type="text" name="state" required value="<?= htmlspecialchars($req['state'] ?? 'Karnataka') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Start Date *</label>
                <input type="date" name="startDate" required value="<?= htmlspecialchars($startDateVal) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Duration (Working Days) *</label>
                <input type="number" name="durationDays" value="<?= htmlspecialchars($req['durationDays'] ?? 5) ?>" min="1" max="180" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Required Skills (Comma-separated)</label>
                <input type="text" name="skillsRequired" value="<?= htmlspecialchars($skillsString) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Published Program Description & Syllabus</label>
                <textarea name="description" rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white"><?= htmlspecialchars($req['description'] ?? '') ?></textarea>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Admin Internal Audit / Negotiation Notes</label>
                <textarea name="adminNotes" rows="2" placeholder="e.g. Spoke with coordinator, agreed to 5-day offline schedule with guest house reserved..." class="w-full bg-amber-50/50 border border-amber-200 rounded-xl p-3 text-xs outline-none focus:bg-white"><?= htmlspecialchars($req['adminNotes'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Action Controls -->
        <div class="pt-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-100">
            <div class="flex items-center gap-2">
                <button type="submit" name="action" value="save_discussion" class="bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs px-4 py-3 rounded-xl transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">phone_in_talk</span>
                    Save Notes & Mark Under Discussion
                </button>

                <button type="submit" name="action" value="reject" onclick="return confirm('Are you sure you want to reject this demand?');" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold text-xs px-4 py-3 rounded-xl transition-colors">
                    Reject
                </button>
            </div>

            <button type="submit" name="action" value="approve_publish" class="bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs px-8 py-3.5 rounded-xl shadow-md transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-base">publish</span>
                Approve & Post to Live Opportunities →
            </button>
        </div>
    </form>
</div>

</main>
</div>
</body>
</html>
