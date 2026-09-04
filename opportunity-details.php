<?php
// opportunity-details.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

$id = $_GET['id'] ?? '';
$opportunityCol = getCollection("Opportunity");

$opp = null;
if (!empty($id)) {
    try {
        $opp = $opportunityCol->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    } catch (Exception $e) {
        $opp = $opportunityCol->findOne(['jobId' => $id]);
    }
}

if (!$opp) {
    header("Location: /opportunities.php");
    exit();
}

$pageTitle = $opp['title'];
$skills = is_string($opp['skillsRequired']) ? json_decode($opp['skillsRequired'], true) : (array)$opp['skillsRequired'];
if (!$skills) $skills = explode(',', (string)$opp['skillsRequired']);

$user = getCurrentUser();

$existingApp = null;
if ($user) {
    $trainerCol = getCollection("Trainer");
    $appCol = getCollection("Application");
    $trainer = null;
    if ($trainerCol) {
        try {
            $trainer = $trainerCol->findOne([
                '$or' => [
                    ['userId' => $user['id']],
                    ['userId' => new MongoDB\BSON\ObjectId($user['id'])]
                ]
            ]);
        } catch (\Throwable $e) {
            $trainer = $trainerCol->findOne(['userId' => $user['id']]);
        }
    }
    if ($trainer && $appCol) {
        $existingApp = $appCol->findOne([
            'trainerId' => (string)$trainer['_id'],
            'opportunityId' => (string)$opp['_id']
        ]);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-50/50 min-h-screen py-10 md:py-14 border-b border-slate-100">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <!-- Back Link -->
        <a href="/opportunities.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-blue-600">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Opportunities
        </a>

        <!-- Main Spec Card -->
        <div class="bg-white rounded-3xl border border-slate-200/90 p-8 md:p-10 shadow-card space-y-8">
            <!-- Header Strip -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-100">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="bg-blue-50 text-blue-700 font-bold text-xs px-3 py-1 rounded-full border border-blue-200/60 uppercase">
                            <?= htmlspecialchars($opp['mode']) ?>
                        </span>
                        <span class="bg-slate-100 text-slate-700 font-semibold text-xs px-3 py-1 rounded-full uppercase">
                            <?= htmlspecialchars(str_replace('_', ' ', $opp['trainingType'] ?? 'COLLEGE')) ?>
                        </span>
                        <span class="text-xs font-mono font-bold text-slate-700 bg-slate-100 border border-slate-200 px-3 py-1 rounded-full shadow-2xs">
                            ID: <?= htmlspecialchars(getMentryCode('OPPORTUNITY', $opp)) ?>
                        </span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-950 leading-tight">
                        <?= htmlspecialchars($opp['title']) ?>
                    </h1>
                    <p class="text-sm text-slate-500 font-medium flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-blue-600 text-lg">location_on</span>
                        <?= htmlspecialchars($opp['city']) ?>, <?= htmlspecialchars($opp['state']) ?>
                    </p>
                </div>

                <div class="text-left md:text-right shrink-0 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Daily Remuneration</span>
                    <p class="text-2xl font-black text-blue-700">
                        <?= formatINR($opp['dailyRateMin']) ?> – <?= formatINR($opp['dailyRateMax']) ?>
                    </p>
                    <span class="text-xs text-slate-500">Per Day • Guaranteed Payout</span>
                </div>
            </div>

            <!-- Key Info Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <span class="text-xs font-semibold text-slate-400 block">Duration</span>
                    <p class="font-extrabold text-base text-slate-900 mt-1"><?= htmlspecialchars($opp['durationDays']) ?> Days</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <span class="text-xs font-semibold text-slate-400 block">Start Date</span>
                    <p class="font-extrabold text-base text-slate-900 mt-1"><?= formatDate($opp['startDate']) ?></p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <span class="text-xs font-semibold text-slate-400 block">Audience</span>
                    <p class="font-extrabold text-base text-slate-900 mt-1"><?= htmlspecialchars($opp['targetAudience'] ?? 'Engineering Students') ?></p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <span class="text-xs font-semibold text-slate-400 block">Min Experience</span>
                    <p class="font-extrabold text-base text-slate-900 mt-1"><?= htmlspecialchars($opp['minExperienceYears']) ?>+ Years</p>
                </div>
            </div>

            <!-- Description -->
            <div class="space-y-3">
                <h3 class="text-lg font-bold text-slate-900">Program Overview</h3>
                <div class="text-sm text-slate-600 leading-relaxed space-y-2">
                    <?= nl2br(htmlspecialchars($opp['description'] ?? 'High-impact technical workshop focused on hands-on practical implementation and student placement readiness.')) ?>
                </div>
            </div>

            <!-- Skills Required -->
            <div class="space-y-3">
                <h3 class="text-lg font-bold text-slate-900">Core Technologies & Topics</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($skills as $s): ?>
                        <span class="bg-blue-50 text-blue-700 font-semibold text-xs px-3.5 py-1.5 rounded-xl border border-blue-200/60">
                            <?= htmlspecialchars(trim($s)) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Logistics Included -->
            <div class="space-y-3">
                <h3 class="text-lg font-bold text-slate-900">Campus Logistics</h3>
                <div class="flex flex-wrap gap-3 text-xs font-medium text-slate-700">
                    <?php if (($opp['mode'] ?? 'OFFLINE') === 'ONLINE'): ?>
                        <span class="bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-xl border border-emerald-200">✓ Virtual Live Delivery</span>
                        <span class="bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-xl border border-emerald-200">✓ Digital Lab Environment</span>
                        <span class="bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-xl border border-emerald-200">✓ Zero Travel Required</span>
                    <?php else: ?>
                        <?php if ($opp['travelCovered'] ?? true): ?>
                            <span class="bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-xl border border-emerald-200">✓ Travel Logistics Covered</span>
                        <?php endif; ?>
                        <?php if ($opp['accommodationCovered'] ?? true): ?>
                            <span class="bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-xl border border-emerald-200">✓ On-Campus Accommodation</span>
                        <?php endif; ?>
                        <?php if ($opp['diningCovered'] ?? true): ?>
                            <span class="bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-xl border border-emerald-200">✓ Guest House Dining</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Apply CTA Strip -->
            <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">
                        <?= $existingApp ? 'Your application has been received' : 'Interested in taking this assignment?' ?>
                    </h4>
                    <p class="text-xs text-slate-500">
                        <?= $existingApp ? 'Mentry academic operations will review and coordinate campus schedule.' : 'Mentry will review your profile and coordinate schedule & logistics.' ?>
                    </p>
                </div>

                <?php if ($existingApp): ?>
                    <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto justify-end">
                        <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 font-bold text-xs px-4 py-2.5 rounded-xl border border-emerald-200 shadow-2xs">
                            <span class="material-symbols-outlined text-[18px]">verified</span>
                            Application <?= htmlspecialchars(strtoupper($existingApp['status'] ?? 'PENDING')) ?>
                        </span>
                        <a href="/trainer/applications.php" class="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all shadow-xs text-center">
                            Track in Portal →
                        </a>
                    </div>
                <?php else: ?>
                    <button onclick="document.getElementById('applyModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-8 py-3.5 rounded-xl transition-all shadow-md flex items-center gap-2 hover:-translate-y-0.5 w-full sm:w-auto justify-center">
                        Apply for this Assignment
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Apply Modal -->
<div id="applyModal" class="hidden fixed inset-0 z-[999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 md:p-8 shadow-2xl relative">
        <button onclick="document.getElementById('applyModal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-700">
            <span class="material-symbols-outlined text-xl">close</span>
        </button>

        <?php if (!$user): ?>
            <div class="text-center py-4 space-y-4">
                <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto">
                    <span class="material-symbols-outlined text-3xl">lock</span>
                </div>
                <div>
                    <h3 class="text-xl font-extrabold text-slate-900 mb-1">Trainer Login Required</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
                        To apply for "<?= htmlspecialchars($opp['title']) ?>", please log in to your trainer workspace or register with Mentry.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 justify-center pt-2">
                    <a href="/login.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-3 px-6 rounded-xl transition-all shadow-md text-center">Trainer Login</a>
                    <a href="/register.php" class="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs py-3 px-6 rounded-xl transition-all shadow-md text-center">Join Network</a>
                </div>
            </div>
        <?php else: ?>
            <form action="/actions/apply.php" method="POST" class="space-y-4">
                <input type="hidden" name="opportunityId" value="<?= (string)$opp['_id'] ?>">
                
                <div>
                    <span class="text-[10px] font-bold uppercase text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded">Application Intake</span>
                    <h3 class="text-lg font-extrabold text-slate-900 mt-1">Apply for <?= htmlspecialchars($opp['title']) ?></h3>
                    <p class="text-xs text-slate-500"><?= htmlspecialchars($opp['city']) ?> • <?= htmlspecialchars($opp['durationDays']) ?> Days</p>
                </div>

                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase font-bold">Standard Rate</span>
                        <p class="font-extrabold text-sm text-blue-700"><?= formatINR($opp['dailyRateMin']) ?> - <?= formatINR($opp['dailyRateMax']) ?> / Day</p>
                    </div>
                    <div class="text-right">
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Proposed Rate (₹)</label>
                        <input type="number" name="proposedDailyRate" value="<?= htmlspecialchars($opp['dailyRateMin']) ?>" class="w-28 text-right font-bold text-sm bg-white border border-slate-200 rounded-lg px-2.5 py-1 focus:ring-2 focus:ring-blue-500/20 outline-none" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Message to Mentry Academic Team (Optional)</label>
                    <textarea name="message" rows="3" placeholder="Confirm availability dates, past college batches handled, etc..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none"></textarea>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('applyModal').classList.add('hidden')" class="flex-1 border border-slate-200 text-slate-700 font-bold text-xs py-3 rounded-xl hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md">Submit Application</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
