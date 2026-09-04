<?php
// submit-requirement.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = "Submit Training Requirement - For Colleges";

$submitted = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $institutionName = trim($_POST['institutionName'] ?? '');
    $contactPerson = trim($_POST['contactPerson'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? 'Karnataka');
    $trainingDomain = trim($_POST['trainingDomain'] ?? '');
    $mode = trim($_POST['mode'] ?? 'OFFLINE');
    $durationDays = (int)($_POST['durationDays'] ?? 5);
    $tentativeStartDate = trim($_POST['tentativeStartDate'] ?? '');
    $budgetPerDay = !empty($_POST['budgetPerDay']) ? (float)$_POST['budgetPerDay'] : null;
    $notes = trim($_POST['notes'] ?? '');

    if (empty($institutionName) || empty($contactPerson) || empty($email) || empty($phone) || empty($trainingDomain)) {
        $error = "Please fill in all mandatory fields marked with an asterisk (*).";
    } else {
        $requirementCol = getCollection("CollegeRequirement");
        if ($requirementCol) {
            $requestCode = getNextSequentialMentryId('REQUIREMENT');
            $requirementCol->insertOne([
                'requestCode' => $requestCode,
                'mentryId' => $requestCode,
                'institutionName' => $institutionName,
                'contactPerson' => $contactPerson,
                'email' => strtolower($email),
                'phone' => $phone,
                'city' => $city,
                'state' => $state,
                'trainingDomain' => $trainingDomain,
                'mode' => $mode,
                'durationDays' => $durationDays,
                'tentativeStartDate' => !empty($tentativeStartDate) ? new MongoDB\BSON\UTCDateTime(strtotime($tentativeStartDate) * 1000) : null,
                'budgetPerDay' => $budgetPerDay,
                'notes' => $notes,
                'status' => 'PENDING',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);
            $submitted = true;
        } else {
            $error = "Database connection error. Please try again or email mentry.training@gmail.com directly.";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-50/50 min-h-screen py-12 md:py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div class="text-center space-y-3">
            <span class="text-blue-600 font-bold text-xs uppercase tracking-wider bg-blue-50 px-3.5 py-1.5 rounded-full border border-blue-100">
                Institutional Partnership
            </span>
            <h1 class="text-3xl sm:text-4xl font-black text-slate-950 tracking-tight">
                Submit College Training Requirement
            </h1>
            <p class="text-sm md:text-base text-slate-600 max-w-2xl mx-auto leading-relaxed">
                Connect with verified technical trainers, corporate practitioners, and soft-skills experts for placement bootcamps, workshops, and faculty training.
            </p>
        </div>

        <?php if ($submitted): ?>
            <div class="bg-emerald-50 border border-emerald-200 rounded-3xl p-8 text-center space-y-4 shadow-sm">
                <div class="w-16 h-16 bg-emerald-500 text-white rounded-2xl flex items-center justify-center mx-auto shadow-md">
                    <span class="material-symbols-outlined text-3xl">check_circle</span>
                </div>
                <h3 class="text-2xl font-bold text-emerald-950">Requirement Submitted Successfully!</h3>
                <p class="text-sm text-emerald-800 max-w-md mx-auto leading-relaxed">
                    Thank you! Our academic coordination team will review your syllabus and match verified trainers within 24 business hours.
                </p>
                <div class="pt-2">
                    <a href="/index.php" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-6 py-3 rounded-xl inline-block shadow-md">
                        Return to Homepage
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/submit-requirement.php" class="bg-white rounded-3xl border border-slate-200/90 p-8 md:p-10 shadow-card space-y-6">
                <!-- Institution & Contact -->
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 mb-4 pb-2 border-b border-slate-100">
                        1. Institution & Contact Details
                    </h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">College / Institution Name *</label>
                            <input type="text" name="institutionName" required placeholder="e.g. RV College of Engineering" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Contact Person & Designation *</label>
                            <input type="text" name="contactPerson" required placeholder="e.g. Dr. Rajesh Kumar (Placement Head)" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Official Email Address *</label>
                            <input type="email" name="email" required placeholder="placements@college.edu.in" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Phone / Mobile Number *</label>
                            <input type="tel" name="phone" required placeholder="+91 98765 43210" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Training Specifics -->
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 mb-4 pb-2 border-b border-slate-100">
                        2. Program Specifics
                    </h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Technical Domain / Syllabus *</label>
                            <input type="text" name="trainingDomain" required placeholder="e.g. Full Stack Java, Python AI/ML, Cloud DevOps" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Training Mode *</label>
                            <select name="mode" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none font-medium">
                                <option value="OFFLINE">Offline (On-Campus)</option>
                                <option value="ONLINE">Online / Virtual</option>
                                <option value="HYBRID">Hybrid</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Campus City *</label>
                            <input type="text" name="city" required placeholder="e.g. Bangalore, Chennai, Coimbatore" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Duration (Days)</label>
                            <input type="number" name="durationDays" value="5" min="1" max="60" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tentative Start Date</label>
                            <input type="date" name="tentativeStartDate" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Estimated Daily Budget (₹/Day)</label>
                            <input type="number" name="budgetPerDay" placeholder="e.g. 7000" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Syllabus / Notes -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Additional Requirements / Batch Size / Syllabus Notes</label>
                    <textarea name="notes" rows="4" placeholder="Detail the student batch size (e.g. 120 students in 2 batches), semester, lab prerequisites, or custom topics required..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none"></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-slate-900 hover:bg-blue-600 text-white font-bold text-sm py-4 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                        Submit Training Requirement
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
