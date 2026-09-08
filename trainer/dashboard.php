<?php
// trainer/dashboard.php
$pageTitle = "Trainer Dashboard";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
checkOpportunityScheduleMilestones();
require_once __DIR__ . '/includes/sidebar.php';

$trainerCol = getCollection("Trainer");
$applicationCol = getCollection("Application");
$assignmentCol = getCollection("Assignment");
$opportunityCol = getCollection("Opportunity");

$userId = $user['id'];
$trainer = $trainerCol ? $trainerCol->findOne(['userId' => $userId]) : null;
$trainerId = $trainer ? (string)$trainer['_id'] : '';

$appCount = $applicationCol ? $applicationCol->countDocuments(['trainerId' => $trainerId]) : 0;
$assignmentCount = $assignmentCol ? $assignmentCol->countDocuments(['trainerId' => $trainerId]) : 0;

$recentApplications = $applicationCol ? $applicationCol->find(
    ['trainerId' => $trainerId],
    ['limit' => 5, 'sort' => ['appliedAt' => -1]]
)->toArray() : [];

$allPublishedOpps = $opportunityCol ? $opportunityCol->find(
    ['status' => 'PUBLISHED'],
    ['sort' => ['createdAt' => -1]]
)->toArray() : [];

$recommendedOpportunities = [];
foreach ($allPublishedOpps as $op) {
    $opStatus = strtoupper($op['status'] ?? 'PUBLISHED');
    if ($opStatus === 'CLOSED' || $opStatus === 'MATCHED' || !empty($op['assignedTrainerId']) || isOpportunityPastCutoff($op)) continue;
    $recommendedOpportunities[] = $op;
    if (count($recommendedOpportunities) >= 3) break;
}

$availStatus = $trainer['availabilityStatus'] ?? 'AVAILABLE_NOW';
$availFromDate = $trainer['availableFromDate'] ?? null;
if ($availFromDate instanceof MongoDB\BSON\UTCDateTime) {
    $availFromDateStr = $availFromDate->toDateTime()->format('Y-m-d');
} elseif (!empty($availFromDate)) {
    if (is_numeric($availFromDate)) {
        $ts = (float)$availFromDate;
        if ($ts > 20000000000) $ts = round($ts / 1000);
        if ($ts > 946684800) $availFromDateStr = date('Y-m-d', (int)$ts);
    } else {
        $parsed = strtotime((string)$availFromDate);
        if ($parsed !== false && $parsed > 946684800) {
            $availFromDateStr = date('Y-m-d', $parsed);
        }
    }
}
$availNotes = $trainer['availabilityNotes'] ?? '';
$mobilityPref = $trainer['travelPreference'] ?? 'PAN_INDIA';
$availUpdatedTime = $trainer['availabilityUpdatedAt'] ?? null;

$skillCol = getCollection("Skill");
$docCol = getCollection("Document");
$expCol = getCollection("Experience");

$hasTitle = !empty($trainer['professionalTitle']);
$hasSkills = $skillCol ? ($skillCol->countDocuments(['trainerId' => $trainerId]) > 0) : false;
$hasDoc = $docCol ? ($docCol->countDocuments(['trainerId' => $trainerId]) > 0) : false;
$hasExp = $expCol ? ($expCol->countDocuments(['trainerId' => $trainerId]) > 0) : false;
$hasPhoto = !empty($user['avatar']) && strpos($user['avatar'], 'avatar.vercel.sh') === false;

$completedSteps = 1; // Basic account info
if ($hasTitle) $completedSteps++;
if ($hasSkills) $completedSteps++;
if ($hasDoc) $completedSteps++;
if ($hasExp) $completedSteps++;
if ($hasPhoto) $completedSteps++;

$completionPercentage = min(100, round(($completedSteps / 6) * 100));
if ($completedSteps === 6) $completionPercentage = 100;

$isProfileIncomplete = ($completionPercentage < 100);
$isNewSignup = isset($_GET['new_signup']);
?>

<div class="space-y-8">
    <?php if (isset($_GET['avail_updated'])): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl text-xs font-bold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
                <span>Availability status updated! Administrators and hiring coordinators can now see your updated schedule.</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900 font-bold text-xs">✕</button>
        </div>
    <?php endif; ?>

    <?php if ($isProfileIncomplete): ?>
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200/80 rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-2xs">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black text-xs shrink-0 shadow-xs">
                    <?= $completionPercentage ?>%
                </div>
                <div>
                    <h4 class="text-xs font-bold text-slate-900">Your trainer profile is <?= $completionPercentage ?>% complete</h4>
                    <p class="text-[11px] text-slate-600">Add your verified skills, teaching bio, and resume to unlock instant college assignment matching.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" onclick="document.getElementById('onboardingProfileModal').classList.remove('hidden'); document.getElementById('onboardingProfileModal').classList.add('flex');" class="text-xs font-bold text-blue-700 hover:underline px-2 py-1">View Checklist</button>
                <a href="/trainer/profile.php" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1">
                    <span>Complete Profile</span>
                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($trainer && $trainer['status'] === 'PENDING_APPROVAL'): ?>
        <div class="bg-amber-50 border border-amber-200 p-4 rounded-2xl flex items-center justify-between gap-4 text-amber-900">
            <div class="flex items-center gap-2 text-xs md:text-sm">
                <span class="material-symbols-outlined text-amber-600 text-lg">hourglass_top</span>
                <span><strong>Profile Pending Review:</strong> Your trainer profile is being verified by our academic panel. You will be notified upon activation.</span>
            </div>
            <a href="/trainer/profile.php" class="text-xs font-bold text-amber-900 underline shrink-0 hover:text-amber-700">Complete Profile</a>
        </div>
    <?php endif; ?>

    <!-- Welcome Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 min-w-0">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 tracking-tight break-words">
                    Welcome back, <?= htmlspecialchars($user['name']) ?>
                </h1>
                <span class="font-mono text-xs font-black text-[#FE5E04] bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-lg shadow-2xs shrink-0">
                    <?= htmlspecialchars(getMentryCode('TRAINER', $trainer ?? $user)) ?>
                </span>
            </div>
            <p class="text-xs md:text-sm text-slate-500 mt-1 break-words">
                Track your college training applications, campus schedule, and availability.
            </p>
        </div>
        <div class="flex items-center gap-2.5 shrink-0 w-full sm:w-auto">
            <a href="/trainer/opportunities.php" class="w-full sm:w-auto justify-center bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base">search</span>
                Explore Assignments
            </a>
        </div>
    </div>

    <!-- Real-Time Availability Widget Card -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-950 text-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-xl border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-5 sm:gap-6 min-w-0">
        <div class="space-y-2 min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 bg-slate-800/80 px-2.5 py-0.5 rounded-md border border-slate-700 shrink-0">
                    Live Booking Status
                </span>
                <span class="text-xs text-slate-400">
                    Last updated: <strong class="text-slate-300"><?= formatRelativeTime($availUpdatedTime) ?></strong>
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <div class="text-base sm:text-lg md:text-xl font-black flex items-center gap-2 break-words">
                    <?php if ($availStatus === 'AVAILABLE_NOW'): ?>
                        <span class="w-3.5 h-3.5 rounded-full bg-emerald-400 animate-pulse ring-4 ring-emerald-500/20 shrink-0"></span>
                        <span class="text-emerald-400">Available Immediately</span>
                    <?php elseif ($availStatus === 'FREE_FROM_DATE'): ?>
                        <span class="w-3.5 h-3.5 rounded-full bg-amber-400 ring-4 ring-amber-500/20 shrink-0"></span>
                        <span class="text-amber-300">Free from <?= formatDate($availFromDate) ?></span>
                    <?php elseif ($availStatus === 'BUSY_ON_ASSIGNMENT'): ?>
                        <span class="w-3.5 h-3.5 rounded-full bg-blue-400 ring-4 ring-blue-500/20 shrink-0"></span>
                        <span class="text-blue-300">Delivering Workshop <?= $availFromDate ? '(Free after ' . formatDate($availFromDate) . ')' : '' ?></span>
                    <?php else: ?>
                        <span class="w-3.5 h-3.5 rounded-full bg-slate-500 ring-4 ring-slate-500/20 shrink-0"></span>
                        <span class="text-slate-400">Temporarily Unavailable</span>
                    <?php endif; ?>
                </div>

                <span class="text-xs text-slate-400 border-l border-slate-700 pl-3">
                    Mobility: <strong class="text-white"><?= htmlspecialchars(str_replace('_', ' ', $mobilityPref)) ?></strong>
                </span>
            </div>

            <?php if (!empty($availNotes)): ?>
                <p class="text-xs text-slate-300 italic pt-0.5 break-words">"<?= htmlspecialchars($availNotes) ?>"</p>
            <?php endif; ?>
        </div>

        <button type="button" onclick="openAvailabilityModal()" class="w-full md:w-auto justify-center bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-slate-950 font-black text-xs px-5 py-3 rounded-2xl shadow-lg transition-all flex items-center gap-2 shrink-0">
            <span class="material-symbols-outlined text-[18px]">calendar_today</span>
            <span>Set Schedule & Free Date</span>
        </button>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 min-w-0">
        <div class="bg-white p-3.5 sm:p-5 rounded-2xl border border-slate-200/90 shadow-card min-w-0">
            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-400 block truncate">Total Applications</span>
            <p class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 mt-1.5 sm:mt-2"><?= $appCount ?></p>
            <span class="text-[10px] sm:text-[11px] text-blue-600 font-semibold mt-1 block truncate">Active Pipeline</span>
        </div>
        <div class="bg-white p-3.5 sm:p-5 rounded-2xl border border-slate-200/90 shadow-card min-w-0">
            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-400 block truncate">Confirmed Assignments</span>
            <p class="text-xl sm:text-2xl md:text-3xl font-black text-emerald-600 mt-1.5 sm:mt-2"><?= $assignmentCount ?></p>
            <span class="text-[10px] sm:text-[11px] text-emerald-600 font-semibold mt-1 block truncate">Upcoming Deliveries</span>
        </div>
        <div class="bg-white p-3.5 sm:p-5 rounded-2xl border border-slate-200/90 shadow-card min-w-0">
            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-400 block truncate">Profile Match Score</span>
            <p class="text-xl sm:text-2xl md:text-3xl font-black text-blue-600 mt-1.5 sm:mt-2"><?= $trainer['profileCompletion'] ?? 90 ?>%</p>
            <span class="text-[10px] sm:text-[11px] text-slate-500 font-medium mt-1 block truncate">Algorithm Affinity</span>
        </div>
        <div class="bg-white p-3.5 sm:p-5 rounded-2xl border border-slate-200/90 shadow-card min-w-0">
            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-400 block truncate">Verification Status</span>
            <p class="text-sm sm:text-base font-extrabold text-slate-900 mt-1.5 sm:mt-2 truncate"><?= htmlspecialchars(str_replace('_', ' ', $trainer['status'] ?? 'APPROVED')) ?></p>
            <span class="text-[10px] sm:text-[11px] text-slate-500 font-medium mt-1 block truncate">Academic Panel</span>
        </div>
    </div>

    <!-- Recommended Feed & Recent Applications -->
    <div class="grid lg:grid-cols-2 gap-6 sm:gap-8 min-w-0">
        <!-- Recommended Openings -->
        <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200/90 p-4 sm:p-6 shadow-card space-y-4 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="material-symbols-outlined text-blue-600 shrink-0">stars</span>
                    <h3 class="font-bold text-slate-900 text-sm sm:text-base truncate">Recommended Openings</h3>
                </div>
                <a href="/trainer/opportunities.php" class="text-xs font-bold text-blue-600 hover:underline shrink-0">View All →</a>
            </div>

            <div class="space-y-3">
                <?php if (empty($recommendedOpportunities)): ?>
                    <p class="text-xs text-slate-400 text-center py-6">No matching openings at the moment.</p>
                <?php else: ?>
                    <?php foreach ($recommendedOpportunities as $opp): 
                        $oppId = (string)$opp['_id'];
                    ?>
                        <div class="p-3.5 sm:p-4 rounded-2xl bg-slate-50/80 border border-slate-200/80 hover:border-blue-300 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 min-w-0">
                            <div class="space-y-1 min-w-0 flex-1">
                                <span class="text-[10px] font-bold uppercase text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100 inline-block">
                                    <?= htmlspecialchars($opp['domain'] ?? 'General') ?>
                                </span>
                                <h4 class="font-bold text-xs text-slate-900 leading-snug break-words"><?= htmlspecialchars($opp['title']) ?></h4>
                                <p class="text-[11px] text-slate-500 break-words">
                                    <?= htmlspecialchars($opp['city'] ?? 'India') ?> • <?= formatINR($opp['dailyRateMin'] ?? 0) ?> – <?= formatINR($opp['dailyRateMax'] ?? 0) ?>/day
                                </p>
                            </div>
                            <a href="/opportunity-details.php?id=<?= $oppId ?>" class="w-full sm:w-auto text-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl transition-all shadow-xs shrink-0">
                                Apply →
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Applications -->
        <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200/90 p-4 sm:p-6 shadow-card space-y-4 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="material-symbols-outlined text-indigo-600 shrink-0">assignment</span>
                    <h3 class="font-bold text-slate-900 text-sm sm:text-base truncate">Recent Applications</h3>
                </div>
                <a href="/trainer/applications.php" class="text-xs font-bold text-blue-600 hover:underline shrink-0">Track Pipeline →</a>
            </div>

            <div class="space-y-3">
                <?php if (empty($recentApplications)): ?>
                    <p class="text-xs text-slate-400 text-center py-6">You have not submitted any applications yet.</p>
                <?php else: ?>
                    <?php foreach ($recentApplications as $app): 
                        $opp = null;
                        if (!empty($app['opportunityId'])) {
                            $opp = $opportunityCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$app['opportunityId'])]);
                        }
                    ?>
                        <div class="p-3.5 sm:p-4 rounded-2xl bg-slate-50/80 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 sm:gap-4 min-w-0">
                            <div class="space-y-0.5 min-w-0 flex-1">
                                <h4 class="font-bold text-xs text-slate-900 break-words">
                                    <?= htmlspecialchars($opp['title'] ?? 'Training Opportunity') ?>
                                </h4>
                                <p class="text-[11px] text-slate-500 break-words">
                                    Proposed Rate: <strong class="text-slate-800"><?= formatINR($app['proposedDailyRate'] ?? 0) ?>/day</strong>
                                </p>
                            </div>
                            <div class="shrink-0 self-start sm:self-auto">
                                <?= getStatusBadge($app['status'] ?? 'PENDING') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Trainer Availability Modal / Popup -->
<div id="availabilityModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden overflow-y-auto p-4 sm:p-6 items-center justify-center">
    <div class="fixed inset-0" onclick="closeAvailabilityModal()" aria-hidden="true"></div>
    <div class="relative bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl flex flex-col max-h-[90vh] my-auto animate-scaleIn z-10 overflow-hidden" style="max-height: 90vh; max-height: 90dvh; display: flex; flex-direction: column;">
        <!-- Pinned Header -->
        <div class="flex items-center justify-between p-5 sm:p-6 pb-4 border-b border-slate-100 shrink-0 bg-white">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-xl">event_available</span>
                </div>
                <div>
                    <h3 class="font-black text-slate-900 text-lg leading-tight">Update My Availability</h3>
                    <p class="text-xs text-slate-500">Help college coordinators match you for upcoming training dates.</p>
                </div>
            </div>
            <button type="button" onclick="closeAvailabilityModal()" aria-label="Close availability modal" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold text-sm shrink-0 cursor-pointer">
                ✕
            </button>
        </div>

        <!-- Form: Scrollable Content + Pinned Footer -->
        <form action="/actions/update-trainer-availability.php" method="POST" class="flex flex-col flex-1 min-h-0 overflow-hidden" style="display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; overflow: hidden;">
            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-4" style="overflow-y: auto; -webkit-overflow-scrolling: touch; min-height: 0; flex: 1 1 auto;">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-2">My Current Booking Status *</label>
                    <div class="grid grid-cols-1 gap-2">
                        <label class="flex items-center gap-3 p-3 rounded-2xl border border-slate-200 hover:border-emerald-500 cursor-pointer bg-slate-50/50 has-checked:bg-emerald-50/70 has-checked:border-emerald-500 transition-all">
                            <input type="radio" name="availabilityStatus" value="AVAILABLE_NOW" <?= ($availStatus === 'AVAILABLE_NOW') ? 'checked' : '' ?> onchange="toggleDateRequirement(false)" class="text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <strong class="text-xs font-bold text-slate-900 block flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    Available Immediately
                                </strong>
                                <span class="text-[11px] text-slate-500">I am ready to accept campus or online workshop assignments right now.</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-2xl border border-slate-200 hover:border-amber-500 cursor-pointer bg-slate-50/50 has-checked:bg-amber-50/70 has-checked:border-amber-500 transition-all">
                            <input type="radio" name="availabilityStatus" value="FREE_FROM_DATE" <?= ($availStatus === 'FREE_FROM_DATE') ? 'checked' : '' ?> onchange="toggleDateRequirement(true)" class="text-amber-600 focus:ring-amber-500">
                            <div>
                                <strong class="text-xs font-bold text-slate-900 block flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    Free After a Specific Date
                                </strong>
                                <span class="text-[11px] text-slate-500">I am completing commitments and free to start new batches after this date.</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-2xl border border-slate-200 hover:border-blue-500 cursor-pointer bg-slate-50/50 has-checked:bg-blue-50/70 has-checked:border-blue-500 transition-all">
                            <input type="radio" name="availabilityStatus" value="BUSY_ON_ASSIGNMENT" <?= ($availStatus === 'BUSY_ON_ASSIGNMENT') ? 'checked' : '' ?> onchange="toggleDateRequirement(true)" class="text-blue-600 focus:ring-blue-500">
                            <div>
                                <strong class="text-xs font-bold text-slate-900 block flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    Currently Delivering Workshop
                                </strong>
                                <span class="text-[11px] text-slate-500">Delivering in-person campus labs; available for next booking after completion.</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-2xl border border-slate-200 hover:border-slate-400 cursor-pointer bg-slate-50/50 has-checked:bg-slate-100 transition-all">
                            <input type="radio" name="availabilityStatus" value="UNAVAILABLE" <?= ($availStatus === 'UNAVAILABLE') ? 'checked' : '' ?> onchange="toggleDateRequirement(false)" class="text-slate-600 focus:ring-slate-500">
                            <div>
                                <strong class="text-xs font-bold text-slate-900 block">Temporarily Unavailable</strong>
                                <span class="text-[11px] text-slate-500">Taking personal leave or not accepting assignments at this time.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Date Picker for "Free From / After Date" -->
                <div id="datePickerContainer" class="p-4 rounded-2xl bg-amber-50/60 border border-amber-200/80 space-y-1 <?= in_array($availStatus, ['FREE_FROM_DATE', 'BUSY_ON_ASSIGNMENT']) ? '' : 'hidden' ?>">
                    <label class="block text-xs font-bold text-amber-950 uppercase">Available / Free From Date *</label>
                    <input type="date" name="availableFromDate" id="availableFromDateInput" value="<?= htmlspecialchars($availFromDateStr) ?>" min="<?= date('Y-m-d') ?>" class="w-full bg-white border border-amber-300 rounded-xl p-2.5 text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-amber-500/20">
                    <span class="text-[10px] text-amber-800 block">College administrators will prioritize your profile for openings scheduled on or after this date.</span>
                </div>

                <!-- Mobility Preference -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Campus Travel Preference</label>
                    <select name="mobilityPreference" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white text-slate-800 font-medium">
                        <option value="PAN_INDIA" <?= ($mobilityPref === 'PAN_INDIA') ? 'selected' : '' ?>>PAN India (Open to travel anywhere in India with accommodation)</option>
                        <option value="STATE_ONLY" <?= ($mobilityPref === 'STATE_ONLY') ? 'selected' : '' ?>>Within Home State Only</option>
                        <option value="CITY_ONLY" <?= ($mobilityPref === 'CITY_ONLY') ? 'selected' : '' ?>>Within Current Metro City Only (No Outstation)</option>
                        <option value="REMOTE_ONLY" <?= ($mobilityPref === 'REMOTE_ONLY') ? 'selected' : '' ?>>Virtual / Online Only</option>
                    </select>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Availability Notes (Optional)</label>
                    <input type="text" name="availabilityNotes" value="<?= htmlspecialchars($availNotes) ?>" placeholder="e.g. Free for 5-day sprints starting every Monday..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white text-slate-800">
                </div>
            </div>

            <!-- Pinned Footer -->
            <div class="p-4 sm:px-6 bg-slate-50 border-t border-slate-100 rounded-b-3xl flex items-center justify-end gap-3 shrink-0">
                <button type="button" onclick="closeAvailabilityModal()" class="text-xs font-bold text-slate-500 hover:text-slate-800 px-4 py-2.5 rounded-xl cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-6 py-3 rounded-xl shadow-md transition-all flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">save</span>
                    Save Availability Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: COMPLETE YOUR TRAINER PROFILE ================= -->
<div id="onboardingProfileModal" class="<?= ($isNewSignup || ($isProfileIncomplete && !isset($_COOKIE['hide_profile_popup']))) ? 'flex' : 'hidden' ?> fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs overflow-y-auto items-center justify-center p-4 sm:p-6">
    <div class="fixed inset-0" onclick="dismissOnboardingModal()" aria-hidden="true"></div>
    <div class="relative bg-white rounded-3xl max-w-lg w-full flex flex-col max-h-[90vh] my-auto shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-200 z-10 overflow-hidden" style="max-height: 90vh; max-height: 90dvh; display: flex; flex-direction: column;">
        <!-- Pinned Header -->
        <div class="p-6 sm:p-8 pb-4 border-b border-slate-100 flex items-start justify-between gap-4 shrink-0 bg-white">
            <div class="space-y-1">
                <span class="bg-orange-50 text-[#FE5E04] text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider border border-orange-200">
                    Quick Profile Setup
                </span>
                <h3 class="text-xl font-black text-slate-900 tracking-tight">
                    Welcome to Mentry, <?= htmlspecialchars(explode(' ', $user['name'] ?? 'Trainer')[0]) ?>! 🎉
                </h3>
                <p class="text-xs text-slate-500">
                    Finish completing your trainer profile to get verified and shortlisted for high-paying college assignments across India.
                </p>
            </div>
            <button onclick="dismissOnboardingModal()" class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 sm:p-8 pt-4 space-y-5" style="overflow-y: auto; -webkit-overflow-scrolling: touch; min-height: 0; flex: 1 1 auto;">
            <!-- Progress Bar -->
            <div class="space-y-2 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <div class="flex items-center justify-between text-xs font-bold">
                    <span class="text-slate-700">Profile Completion</span>
                    <span class="text-blue-600 font-extrabold"><?= $completionPercentage ?>%</span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                    <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-500" style="width: <?= $completionPercentage ?>%"></div>
                </div>
            </div>

            <!-- Checklist -->
            <div class="space-y-2">
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Pending Profile Steps:</h4>
                
                <div class="flex items-center justify-between p-3 rounded-xl <?= $hasTitle ? 'bg-emerald-50/60 border border-emerald-200/60' : 'bg-slate-50 border border-slate-100' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-base <?= $hasTitle ? 'text-emerald-600' : 'text-slate-400' ?>">
                            <?= $hasTitle ? 'check_circle' : 'radio_button_unchecked' ?>
                        </span>
                        <span class="text-xs font-semibold <?= $hasTitle ? 'text-emerald-950' : 'text-slate-700' ?>">Professional Title & Domain</span>
                    </div>
                    <span class="text-[11px] font-bold <?= $hasTitle ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $hasTitle ? 'Done' : '+15%' ?></span>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl <?= $hasSkills ? 'bg-emerald-50/60 border border-emerald-200/60' : 'bg-slate-50 border border-slate-100' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-base <?= $hasSkills ? 'text-emerald-600' : 'text-slate-400' ?>">
                            <?= $hasSkills ? 'check_circle' : 'radio_button_unchecked' ?>
                        </span>
                        <span class="text-xs font-semibold <?= $hasSkills ? 'text-emerald-950' : 'text-slate-700' ?>">Verified Technical Skills (Python, Java, Cloud...)</span>
                    </div>
                    <span class="text-[11px] font-bold <?= $hasSkills ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $hasSkills ? 'Done' : '+20%' ?></span>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl <?= $hasDoc ? 'bg-emerald-50/60 border border-emerald-200/60' : 'bg-slate-50 border border-slate-100' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-base <?= $hasDoc ? 'text-emerald-600' : 'text-slate-400' ?>">
                            <?= $hasDoc ? 'check_circle' : 'radio_button_unchecked' ?>
                        </span>
                        <span class="text-xs font-semibold <?= $hasDoc ? 'text-emerald-950' : 'text-slate-700' ?>">Upload Resume / CV</span>
                    </div>
                    <span class="text-[11px] font-bold <?= $hasDoc ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $hasDoc ? 'Done' : '+20%' ?></span>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl <?= $hasPhoto ? 'bg-emerald-50/60 border border-emerald-200/60' : 'bg-slate-50 border border-slate-100' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-base <?= $hasPhoto ? 'text-emerald-600' : 'text-slate-400' ?>">
                            <?= $hasPhoto ? 'check_circle' : 'radio_button_unchecked' ?>
                        </span>
                        <span class="text-xs font-semibold <?= $hasPhoto ? 'text-emerald-950' : 'text-slate-700' ?>">Profile Headshot (Optional: Initials used)</span>
                    </div>
                    <span class="text-[11px] font-bold <?= $hasPhoto ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $hasPhoto ? 'Done' : '+15%' ?></span>
                </div>
            </div>
        </div>

        <!-- Pinned Footer -->
        <div class="p-4 sm:px-8 bg-slate-50 border-t border-slate-100 rounded-b-3xl flex flex-col sm:flex-row items-center justify-end gap-2.5 shrink-0">
            <button type="button" onclick="dismissOnboardingModal()" class="w-full sm:w-auto px-4 py-2.5 text-xs font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer">
                I'll do this later
            </button>
            <a href="/trainer/profile.php" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5">
                <span>Complete Profile Now</span>
                <span class="material-symbols-outlined text-base">arrow_forward</span>
            </a>
        </div>
    </div>
</div>

<script>
function openAvailabilityModal() {
    const modal = document.getElementById('availabilityModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }
}

function closeAvailabilityModal() {
    const modal = document.getElementById('availabilityModal');
    if (modal) {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
}

function toggleDateRequirement(show) {
    const container = document.getElementById('datePickerContainer');
    if (show) {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
}

function dismissOnboardingModal() {
    const modal = document.getElementById('onboardingProfileModal');
    if (modal) {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    document.cookie = "hide_profile_popup=1; path=/; max-age=86400";
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAvailabilityModal();
        dismissOnboardingModal();
    }
});
</script>

</main>
</div>
</body>
</html>
