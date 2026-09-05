<?php
// admin/notifications.php
$pageTitle = "Notification Center";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$notifCol = getCollection("Notification");

// Handle Mark All As Read
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    if ($notifCol) {
        $notifCol->updateMany(
            ['read' => false],
            ['$set' => ['read' => true, 'readAt' => new MongoDB\BSON\UTCDateTime()]]
        );
    }
    header("Location: /admin/notifications.php");
    exit();
}

// Handle Single Notification Click & Redirect
if (!empty($_GET['read_id'])) {
    try {
        $nid = new MongoDB\BSON\ObjectId($_GET['read_id']);
        if ($notifCol) {
            $notifCol->updateOne(
                ['_id' => $nid],
                ['$set' => ['read' => true, 'readAt' => new MongoDB\BSON\UTCDateTime()]]
            );
        }
    } catch (\Throwable $e) {}

    $goto = $_GET['goto'] ?? '/admin/notifications.php';
    header("Location: " . $goto);
    exit();
}

require_once __DIR__ . '/includes/sidebar.php';

// Active Category Filter
$activeFilter = $_GET['filter'] ?? 'ALL';

$mongoFilter = [];
if ($activeFilter === 'NEW_APPLICATION') {
    $mongoFilter['type'] = 'NEW_APPLICATION';
} elseif ($activeFilter === 'NEW_TRAINER') {
    $mongoFilter['type'] = 'NEW_TRAINER';
} elseif ($activeFilter === 'NEW_REQUIREMENT') {
    $mongoFilter['type'] = 'NEW_REQUIREMENT';
} elseif ($activeFilter === 'PARTNER') {
    $mongoFilter['type'] = ['$in' => ['NEW_VENDOR', 'NEW_DEMAND']];
} elseif ($activeFilter === 'UNREAD') {
    $mongoFilter['read'] = false;
}

$notifications = $notifCol ? $notifCol->find($mongoFilter, ['sort' => ['createdAt' => -1], 'limit' => 50])->toArray() : [];

// Compute category counts
$totalUnreadCount = 0;
$appCount = 0;
$trainerCount = 0;
$reqCount = 0;
$partnerCount = 0;

if ($notifCol) {
    try {
        $totalUnreadCount = $notifCol->countDocuments(['read' => false]);
        $appCount = $notifCol->countDocuments(['type' => 'NEW_APPLICATION']);
        $trainerCount = $notifCol->countDocuments(['type' => 'NEW_TRAINER']);
        $reqCount = $notifCol->countDocuments(['type' => 'NEW_REQUIREMENT']);
        $partnerCount = $notifCol->countDocuments(['type' => ['$in' => ['NEW_VENDOR', 'NEW_DEMAND']]]);
    } catch (\Throwable $e) {}
}

function getNotificationConfig($type) {
    switch ($type) {
        case 'NEW_APPLICATION':
            return [
                'icon' => 'assignment',
                'bg' => 'bg-emerald-50 text-emerald-600 border border-emerald-100',
                'badge' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/80',
                'label' => 'New Application'
            ];
        case 'NEW_TRAINER':
            return [
                'icon' => 'person_add',
                'bg' => 'bg-blue-50 text-blue-600 border border-blue-100',
                'badge' => 'bg-blue-50 text-blue-700 border border-blue-200/80',
                'label' => 'New Trainer'
            ];
        case 'NEW_REQUIREMENT':
            return [
                'icon' => 'school',
                'bg' => 'bg-indigo-50 text-indigo-600 border border-indigo-100',
                'badge' => 'bg-indigo-50 text-indigo-700 border border-indigo-200/80',
                'label' => 'College Requirement'
            ];
        case 'NEW_VENDOR':
            return [
                'icon' => 'corporate_fare',
                'bg' => 'bg-purple-50 text-purple-600 border border-purple-100',
                'badge' => 'bg-purple-50 text-purple-700 border border-purple-200/80',
                'label' => 'Partner Registered'
            ];
        case 'NEW_DEMAND':
            return [
                'icon' => 'assignment_late',
                'bg' => 'bg-amber-50 text-amber-600 border border-amber-100',
                'badge' => 'bg-amber-50 text-amber-700 border border-amber-200/80',
                'label' => 'Private Demand'
            ];
        case 'INQUIRY':
            return [
                'icon' => 'mail',
                'bg' => 'bg-rose-50 text-rose-600 border border-rose-100',
                'badge' => 'bg-rose-50 text-rose-700 border border-rose-200/80',
                'label' => 'Contact Inquiry'
            ];
        case 'OPPORTUNITY_MATCH':
            return [
                'icon' => 'campaign',
                'bg' => 'bg-sky-50 text-sky-600 border border-sky-100',
                'badge' => 'bg-sky-50 text-sky-700 border border-sky-200/80',
                'label' => 'Trainer Match'
            ];
        default:
            return [
                'icon' => 'notifications',
                'bg' => 'bg-slate-50 text-slate-600 border border-slate-200',
                'badge' => 'bg-slate-50 text-slate-700 border border-slate-200',
                'label' => 'System Alert'
            ];
    }
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Notification Center</h1>
                <?php if ($totalUnreadCount > 0): ?>
                    <span class="bg-[#FE5E04] text-white text-xs font-black px-2.5 py-0.5 rounded-full shadow-xs animate-pulse">
                        <?= $totalUnreadCount ?> New
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-xs md:text-sm text-slate-500 mt-1">Live administrative alerts for trainer signups, college requirements, job applications, and partner demands.</p>
        </div>

        <?php if ($totalUnreadCount > 0): ?>
            <a href="/admin/notifications.php?action=mark_all_read" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#FE5E04] hover:text-[#E04E00] bg-orange-50 hover:bg-orange-100/80 border border-orange-200 px-4 py-2 rounded-xl transition-all shadow-2xs self-start sm:self-auto">
                <span class="material-symbols-outlined text-base">done_all</span>
                Mark All as Read
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none text-xs font-bold">
        <a href="/admin/notifications.php" class="px-3.5 py-2 rounded-xl border transition-all whitespace-nowrap <?= $activeFilter === 'ALL' ? 'bg-[#FE5E04] text-white border-[#FE5E04] shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
            All Logs
        </a>
        <a href="/admin/notifications.php?filter=UNREAD" class="px-3.5 py-2 rounded-xl border transition-all whitespace-nowrap flex items-center gap-1.5 <?= $activeFilter === 'UNREAD' ? 'bg-[#FE5E04] text-white border-[#FE5E04] shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
            <span>Unread</span>
            <?php if ($totalUnreadCount > 0): ?>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $activeFilter === 'UNREAD' ? 'bg-white/20 text-white' : 'bg-[#FE5E04] text-white' ?>">
                    <?= $totalUnreadCount ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="/admin/notifications.php?filter=NEW_APPLICATION" class="px-3.5 py-2 rounded-xl border transition-all whitespace-nowrap flex items-center gap-1.5 <?= $activeFilter === 'NEW_APPLICATION' ? 'bg-[#FE5E04] text-white border-[#FE5E04] shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
            <span>Applications</span>
            <?php if ($appCount > 0): ?>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $activeFilter === 'NEW_APPLICATION' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' ?>">
                    <?= $appCount ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="/admin/notifications.php?filter=NEW_TRAINER" class="px-3.5 py-2 rounded-xl border transition-all whitespace-nowrap flex items-center gap-1.5 <?= $activeFilter === 'NEW_TRAINER' ? 'bg-[#FE5E04] text-white border-[#FE5E04] shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
            <span>Trainers</span>
            <?php if ($trainerCount > 0): ?>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $activeFilter === 'NEW_TRAINER' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' ?>">
                    <?= $trainerCount ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="/admin/notifications.php?filter=NEW_REQUIREMENT" class="px-3.5 py-2 rounded-xl border transition-all whitespace-nowrap flex items-center gap-1.5 <?= $activeFilter === 'NEW_REQUIREMENT' ? 'bg-[#FE5E04] text-white border-[#FE5E04] shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
            <span>College Intake</span>
            <?php if ($reqCount > 0): ?>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $activeFilter === 'NEW_REQUIREMENT' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' ?>">
                    <?= $reqCount ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="/admin/notifications.php?filter=PARTNER" class="px-3.5 py-2 rounded-xl border transition-all whitespace-nowrap flex items-center gap-1.5 <?= $activeFilter === 'PARTNER' ? 'bg-[#FE5E04] text-white border-[#FE5E04] shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
            <span>Partners & Demands</span>
            <?php if ($partnerCount > 0): ?>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $activeFilter === 'PARTNER' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' ?>">
                    <?= $partnerCount ?>
                </span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Notification Feed List -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card divide-y divide-slate-100 overflow-hidden">
        <?php if (empty($notifications)): ?>
            <div class="p-16 text-center space-y-3">
                <div class="w-14 h-14 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto">
                    <span class="material-symbols-outlined text-3xl">notifications_off</span>
                </div>
                <h3 class="text-sm font-bold text-slate-800">No Notifications Found</h3>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">There are no operational alerts matching the selected filter.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): 
                $isUnread = empty($n['read']);
                $type = $n['type'] ?? 'DEFAULT';
                $cfg = getNotificationConfig($type);
                $hasLink = !empty($n['link']);
                $directUrl = $hasLink ? "/admin/notifications.php?read_id=" . (string)$n['_id'] . "&goto=" . urlencode($n['link']) : null;
            ?>
                <div class="p-5 flex items-start gap-4 transition-colors <?= $isUnread ? 'bg-orange-50/20 border-l-4 border-l-[#FE5E04]' : 'hover:bg-slate-50/50' ?>">
                    <!-- Type Icon -->
                    <div class="w-10 h-10 rounded-2xl <?= $cfg['bg'] ?> flex items-center justify-center shrink-0 shadow-2xs">
                        <span class="material-symbols-outlined text-xl"><?= $cfg['icon'] ?></span>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md <?= $cfg['badge'] ?>">
                                <?= $cfg['label'] ?>
                            </span>

                            <?php if ($isUnread): ?>
                                <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded bg-[#FE5E04] text-white">
                                    NEW
                                </span>
                            <?php endif; ?>

                            <span class="text-[11px] font-medium text-slate-400 ml-auto shrink-0">
                                <?= formatRelativeTime($n['createdAt'] ?? null) ?>
                            </span>
                        </div>

                        <h4 class="font-bold text-xs sm:text-sm text-slate-900 leading-snug">
                            <?= htmlspecialchars($n['title'] ?? 'Operational Alert') ?>
                        </h4>

                        <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                            <?= htmlspecialchars($n['message'] ?? '') ?>
                        </p>

                        <?php if ($hasLink): ?>
                            <div class="mt-3 pt-2 flex items-center gap-3">
                                <a href="<?= htmlspecialchars($directUrl) ?>" class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-700 bg-blue-50/80 hover:bg-blue-100/80 border border-blue-200/70 px-3 py-1.5 rounded-xl transition-colors">
                                    <span>Review Record</span>
                                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
                                </a>

                                <?php if ($isUnread): ?>
                                    <a href="/admin/notifications.php?read_id=<?= (string)$n['_id'] ?>&goto=<?= urlencode('/admin/notifications.php') ?>" class="text-[11px] font-semibold text-slate-400 hover:text-slate-600 transition-colors">
                                        Mark as read
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
