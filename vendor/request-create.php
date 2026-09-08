<?php
// vendor/request-create.php - Submit Private Job Request
$pageTitle = "Post Job Request";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireVendor();
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/locations.php';

$error = null;
$user = getCurrentUser();
$vendorId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $institutionName = trim($_POST['institutionName'] ?? '');
    $domain = trim($_POST['domain'] ?? 'Programming');
    $mode = trim($_POST['mode'] ?? 'OFFLINE');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? 'Tamil Nadu');
    list($city, $state) = normalizeIndiaLocation($city, $state);
    $startDate = trim($_POST['startDate'] ?? '');
    $endDate = trim($_POST['endDate'] ?? '');
    $durationDays = (int)($_POST['durationDays'] ?? 5);
    $studentCount = (int)($_POST['studentCount'] ?? 100);
    $budgetPerDay = (float)($_POST['budgetPerDay'] ?? 8000);
    $skillsRequired = trim($_POST['skillsRequired'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $accommodationDetails = trim($_POST['accommodationDetails'] ?? 'Campus Executive Guest House Provided');
    $travelDetails = trim($_POST['travelDetails'] ?? 'Travel allowance / tickets reimbursed');

    if (empty($title) || empty($institutionName) || empty($city) || empty($startDate) || empty($endDate)) {
        $error = "Please fill in all mandatory fields marked with an asterisk (*) including Start Date and End Date.";
    } elseif (strtotime($startDate) === false || strtotime($endDate) === false) {
        $error = "Please provide valid start and end dates.";
    } elseif (strtotime($startDate) < strtotime(date('Y-m-d'))) {
        $error = "Start date cannot be in the past. Please select today or a future date.";
    } elseif (strtotime($endDate) < strtotime($startDate)) {
        $error = "End date cannot be earlier than start date.";
    } else {
        $reqCol = getCollection("VendorRequest");
        if ($reqCol) {
            $skillsArray = array_values(array_filter(array_map('trim', explode(',', $skillsRequired))));

            $reqCol->insertOne([
                'vendorId' => $vendorId,
                'vendorName' => $user['organizationName'] ?? $user['name'],
                'vendorContactEmail' => $user['email'],
                'vendorContactPhone' => $user['phone'] ?? '',
                'title' => $title,
                'institutionName' => $institutionName,
                'domain' => $domain,
                'mode' => $mode,
                'city' => $city,
                'state' => $state,
                'startDate' => new MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000),
                'endDate' => new MongoDB\BSON\UTCDateTime(strtotime($endDate) * 1000),
                'durationDays' => $durationDays,
                'studentCount' => $studentCount,
                'budgetPerDay' => $budgetPerDay,
                'skillsRequired' => json_encode($skillsArray),
                'description' => $description,
                'accommodationDetails' => $accommodationDetails,
                'travelDetails' => $travelDetails,
                'status' => 'PENDING_ADMIN_REVIEW', // PRIVATE - Not visible on public or trainer boards!
                'adminContacted' => false,
                'adminNotes' => '',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            // Dispatch real-time Admin Notification
            require_once __DIR__ . '/../includes/notifications.php';
            $vName = $user['organizationName'] ?? ($user['name'] ?? 'Partner');
            notifyAdmin(
                'NEW_DEMAND',
                "New Private Demand: {$title}",
                "{$vName} posted a private training demand for {$institutionName} ({$domain}, {$durationDays} days, Mode: {$mode}) in {$city}.",
                "/admin/vendor-requests.php",
                [
                    'vendorId' => $vendorId,
                    'vendorName' => $vName,
                    'domain' => $domain,
                    'city' => $city
                ]
            );

            header("Location: /vendor/requests.php?success=1");
            exit();
        } else {
            $error = "Database connection error. Please try again.";
        }
    }
}

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="/vendor/dashboard.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-indigo-600">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Dashboard
        </a>
    </div>

    <div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Post Private Training Demand</h1>
        <p class="text-xs md:text-sm text-slate-500 mt-1">Submit your curriculum scope. Our operations desk will review, configure trainer honorariums, and confirm matching with you.</p>
    </div>

    <!-- Private Policy Alert -->
    <div class="p-4 bg-indigo-50 border border-indigo-200 rounded-2xl flex items-center gap-3 text-xs text-indigo-900">
        <span class="material-symbols-outlined text-indigo-600 text-xl shrink-0">shield</span>
        <p><strong>Strict Privacy Policy:</strong> This requirement will be submitted privately. It will <strong>NOT</strong> be displayed to trainers or on the public portal until the administrator contacts you and approves the scope.</p>
    </div>

    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/vendor/request-create.php" class="bg-white p-5 sm:p-8 rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-card space-y-6 min-w-0">
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Requirement Title *</label>
                <input type="text" name="title" required placeholder="e.g. 5-Day Python Full Stack & DSA Pre-Placement Bootcamp" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none font-bold text-slate-900">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Target College / Client Organization *</label>
                <input type="text" name="institutionName" required placeholder="e.g. BMS College of Engineering" value="<?= htmlspecialchars($_POST['institutionName'] ?? ($user['organizationName'] ?? '')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Curriculum Domain *</label>
                <select name="domain" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none">
                    <option value="Programming">Programming & Software (Java, Python, MERN)</option>
                    <option value="Data Science">Data Science & AI/ML</option>
                    <option value="Cloud">Cloud & DevOps (AWS, Azure, Docker)</option>
                    <option value="VLSI">VLSI & Embedded Systems</option>
                    <option value="Cybersecurity">Cybersecurity & Ethical Hacking</option>
                    <option value="Aptitude">Quantitative Aptitude & Soft Skills</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Training Mode *</label>
                <select name="mode" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none">
                    <option value="OFFLINE">Offline (On-Campus Labs)</option>
                    <option value="ONLINE">Online / Virtual</option>
                    <option value="HYBRID">Hybrid (Mix of Online & Campus)</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <?= renderStateDistrictSelectors('state', 'city', $_POST['state'] ?? 'Tamil Nadu', $_POST['city'] ?? '', true, 'Campus State *', 'Campus District / City *', 'focus:ring-indigo-500/20') ?>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tentative Start Date *</label>
                <input type="date" id="vendorStartDate" name="startDate" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['startDate'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tentative End Date *</label>
                <input type="date" id="vendorEndDate" name="endDate" required min="<?= htmlspecialchars($_POST['startDate'] ?? date('Y-m-d')) ?>" value="<?= htmlspecialchars($_POST['endDate'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                <span class="text-[10px] text-slate-400 mt-1 block">Expected completion date including weekend / scheduled breaks.</span>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Duration (Working Days) *</label>
                <input type="number" name="durationDays" value="<?= htmlspecialchars($_POST['durationDays'] ?? '5') ?>" min="1" max="180" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                <span class="text-[10px] text-slate-400 mt-1 block">Total instructional days excluding Saturday/Sunday breaks.</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Expected Student Batch Size</label>
                <input type="number" name="studentCount" value="120" min="1" max="1000" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Your Proposed Budget (₹/Day) *</label>
                <input type="number" name="budgetPerDay" value="9000" min="2000" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none font-black text-indigo-700">
                <span class="text-[10px] text-slate-400 mt-1 block">What your organization expects to invest per training day.</span>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Specific Skills & Topics (Comma-separated)</label>
                <input type="text" name="skillsRequired" placeholder="e.g. Python, Django, REST APIs, PostgreSQL, Git, Placement Problems" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Curriculum Scope & Syllabus Outline</label>
                <textarea name="description" rows="4" placeholder="Detailed syllabus breakdown, daily modules, project specifications, and student year..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Lodging & Accommodation</label>
                <input type="text" name="accommodationDetails" value="Campus Executive Guest House Provided" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Travel Reimbursement</label>
                <input type="text" name="travelDetails" value="Train/Flight tickets reimbursed on actuals" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
            </div>
        </div>

        <div class="pt-4 flex items-center justify-between border-t border-slate-100">
            <a href="/vendor/dashboard.php" class="text-xs font-bold text-slate-500 hover:text-slate-800">
                Cancel
            </a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-8 py-3.5 rounded-xl shadow-md transition-all flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">send</span>
                Submit Requirement for Admin Review
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startInput = document.getElementById('vendorStartDate');
    const endInput = document.getElementById('vendorEndDate');
    if (startInput && endInput) {
        startInput.addEventListener('change', function() {
            if (this.value) {
                endInput.min = this.value;
                if (endInput.value && endInput.value < this.value) {
                    endInput.value = this.value;
                }
            }
        });
    }
});
</script>

</main>
</div>
</body>
</html>
