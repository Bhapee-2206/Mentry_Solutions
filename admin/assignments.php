<?php
// admin/assignments.php - Assignments & Logistics Manager
$pageTitle = "Assignments & Logistics";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$asgCol = getCollection("Assignment");
$oppCol = getCollection("Opportunity");
$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");

$statusFilter = $_GET['status'] ?? 'ALL';
$filter = [];
if ($statusFilter !== 'ALL') {
    $filter['status'] = $statusFilter;
}

$assignments = $asgCol ? $asgCol->find($filter, ['sort' => ['createdAt' => -1]])->toArray() : [];
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Active Training Assignments & Logistics</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-0.5">Track live campus deliveries, modify guest house bookings, flight/train travel tickets, and delivery honorariums.</p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-card flex items-center gap-2">
        <?php
        $tabs = [
            'ALL' => 'All Assignments',
            'SCHEDULED' => 'Scheduled',
            'IN_PROGRESS' => 'In Progress',
            'COMPLETED' => 'Completed',
            'CANCELLED' => 'Cancelled'
        ];
        foreach ($tabs as $k => $v): ?>
            <a href="/admin/assignments.php?status=<?= $k ?>" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all <?= $statusFilter === $k ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
                <?= $v ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Assignments Cards List -->
    <div class="space-y-4">
        <?php if (empty($assignments)): ?>
            <div class="bg-white p-12 rounded-3xl border border-slate-200/90 shadow-card text-center text-xs text-slate-400">
                No assignments found in this category. Accept applications or assign trainers directly from Opportunities.
            </div>
        <?php else: ?>
            <?php foreach ($assignments as $asg): 
                $asgId = (string)$asg['_id'];
                $opp = null;
                if (!empty($asg['opportunityId'])) {
                    try { $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$asg['opportunityId'])]); } catch (Exception $e) {}
                }
                $trainer = null;
                $trainerUser = null;
                if (!empty($asg['trainerId'])) {
                    try {
                        $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$asg['trainerId'])]);
                        if ($trainer && !empty($trainer['userId'])) {
                            $trainerUser = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainer['userId'])]);
                        }
                    } catch (Exception $e) {}
                }
            ?>
                <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/90 shadow-card space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-4 border-b border-slate-100">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-extrabold uppercase text-emerald-800 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">Assignment ID: <?= substr($asgId, -8) ?></span>
                                <?= getStatusBadge($asg['status'] ?? 'SCHEDULED') ?>
                            </div>
                            <h3 class="font-black text-lg text-slate-900 mt-1"><?= htmlspecialchars($opp['title'] ?? 'Custom Campus Training Engagement') ?></h3>
                            <p class="text-xs text-slate-500 font-medium">
                                <?= htmlspecialchars($asg['location'] ?? ($opp['city'] ?? 'India')) ?> • 
                                Duration: <strong><?= htmlspecialchars($asg['durationDays'] ?? 5) ?> Working Days</strong> • 
                                Starts <strong><?= formatDate($asg['startDate'] ?? ($opp['startDate'] ?? null)) ?></strong>
                            </p>
                        </div>

                        <div class="text-left sm:text-right shrink-0 bg-slate-50 border border-slate-100 p-3.5 rounded-2xl">
                            <span class="text-[10px] text-slate-400 uppercase font-bold block">Total Agreed Honorarium</span>
                            <p class="font-black text-xl text-emerald-700"><?= formatINR($asg['agreedTotalFee'] ?? 0) ?></p>
                            <span class="text-[10px] text-slate-500 font-medium"><?= formatINR($asg['agreedDailyRate'] ?? 0) ?>/day</span>
                        </div>
                    </div>

                    <!-- Assigned Trainer & Logistics Grid -->
                    <div class="grid md:grid-cols-3 gap-4 text-xs">
                        <!-- Trainer -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Assigned Faculty</span>
                            <?php if ($trainer && $trainerUser): ?>
                                <div class="flex items-center gap-3">
                                    <img src="<?= htmlspecialchars($trainerUser['avatar'] ?? "https://avatar.vercel.sh/" . urlencode($trainerUser['name']) . ".png") ?>" class="w-10 h-10 rounded-xl object-cover border border-slate-200">
                                    <div>
                                        <a href="/admin/trainer-view.php?id=<?= (string)$trainer['_id'] ?>" class="font-bold text-slate-900 hover:text-blue-600 block">
                                            <?= htmlspecialchars($trainerUser['name']) ?>
                                        </a>
                                        <p class="text-[11px] text-slate-500"><?= htmlspecialchars($trainer['professionalTitle'] ?? '') ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-slate-400 font-semibold">Trainer information unavailable</p>
                            <?php endif; ?>
                        </div>

                        <!-- Accommodation -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-1">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Accommodation & Lodging</span>
                            <p class="font-bold text-slate-800"><?= htmlspecialchars($asg['accommodationDetails'] ?? 'Campus Guest House Reserved') ?></p>
                        </div>

                        <!-- Travel -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-1">
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Travel Itinerary & Transport</span>
                            <p class="font-bold text-slate-800"><?= htmlspecialchars($asg['travelDetails'] ?? 'Flight / Train Tickets Arranged by Mentry') ?></p>
                        </div>
                    </div>

                    <!-- Update Assignment Logistics & Status Form -->
                    <form action="/actions/update-assignment.php" method="POST" class="bg-slate-50/70 p-4 rounded-2xl border border-slate-200/80 space-y-4">
                        <input type="hidden" name="assignmentId" value="<?= $asgId ?>">

                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-xs text-slate-800 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px] text-blue-600">tune</span>
                                Manage Assignment Terms & Delivery Status
                            </h4>
                        </div>

                        <div class="grid sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Status</label>
                                <select name="status" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-slate-800 outline-none">
                                    <option value="SCHEDULED" <?= ($asg['status'] ?? '') === 'SCHEDULED' ? 'selected' : '' ?>>SCHEDULED</option>
                                    <option value="IN_PROGRESS" <?= ($asg['status'] ?? '') === 'IN_PROGRESS' ? 'selected' : '' ?>>IN PROGRESS</option>
                                    <option value="COMPLETED" <?= ($asg['status'] ?? '') === 'COMPLETED' ? 'selected' : '' ?>>COMPLETED</option>
                                    <option value="CANCELLED" <?= ($asg['status'] ?? '') === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Agreed Daily Rate (₹)</label>
                                <input type="number" name="agreedDailyRate" value="<?= htmlspecialchars($asg['agreedDailyRate'] ?? 0) ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-slate-800 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Agreed Total Fee (₹)</label>
                                <input type="number" name="agreedTotalFee" value="<?= htmlspecialchars($asg['agreedTotalFee'] ?? 0) ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-emerald-700 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">College Feedback Rating (1-5)</label>
                                <input type="number" step="0.1" min="1" max="5" name="feedbackRating" value="<?= htmlspecialchars($asg['feedbackRating'] ?? 5.0) ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs outline-none">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Lodging & Guest House</label>
                                <input type="text" name="accommodationDetails" value="<?= htmlspecialchars($asg['accommodationDetails'] ?? 'Campus Guest House Reserved') ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs outline-none">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Travel Tickets & Itinerary</label>
                                <input type="text" name="travelDetails" value="<?= htmlspecialchars($asg['travelDetails'] ?? 'Flight / Train Tickets Arranged by Mentry') ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs outline-none">
                            </div>
                        </div>

                        <div class="flex justify-end pt-1">
                            <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs px-5 py-2 rounded-xl shadow-xs transition-colors">
                                Update Logistics & Status
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
