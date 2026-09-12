<?php
// trainer/notifications.php
$pageTitle = "Notifications";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
requireTrainer();

$user = getCurrentUser();
$notifCol = getCollection("Notification");

$userQuery = !empty($user['id']) ? [(string)$user['id']] : [];
if (!empty($user['id'])) {
    try {
        $userQuery[] = new MongoDB\BSON\ObjectId($user['id']);
    } catch (\Throwable $e) {}
}

// Handle Mark All As Read
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    if ($notifCol && !empty($userQuery)) {
        $notifCol->updateMany(
            ['userId' => ['$in' => $userQuery], '$or' => [['read' => false], ['read' => ['$exists' => false]]]],
            ['$set' => ['read' => true, 'readAt' => new MongoDB\BSON\UTCDateTime()]]
        );
    }
    header("Location: /trainer/notifications.php");
    exit();
}

// Handle Single Notification Click & Redirect
if (!empty($_GET['read_id'])) {
    try {
        $nid = new MongoDB\BSON\ObjectId($_GET['read_id']);
        if ($notifCol && !empty($userQuery)) {
            $notifCol->updateOne(
                ['_id' => $nid, 'userId' => ['$in' => $userQuery]],
                ['$set' => ['read' => true, 'readAt' => new MongoDB\BSON\UTCDateTime()]]
            );
        }
    } catch (\Throwable $e) {}

    $goto = $_GET['goto'] ?? '/trainer/notifications.php';
    if (empty($goto) || !str_starts_with($goto, '/') || str_starts_with($goto, '//')) {
        $goto = '/trainer/notifications.php';
    }
    header("Location: " . $goto);
    exit();
}

require_once __DIR__ . '/includes/sidebar.php';

$notifications = [];
$unreadCount = 0;
if ($notifCol && !empty($userQuery)) {
    $notifications = $notifCol->find(
        ['userId' => ['$in' => $userQuery]],
        ['sort' => ['createdAt' => -1], 'limit' => 50]
    )->toArray();

    $unreadCount = $notifCol->countDocuments([
        'userId' => ['$in' => $userQuery],
        '$or' => [['read' => false], ['read' => ['$exists' => false]]]
    ]);
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Notifications & Match Alerts</h1>
            <p class="text-xs text-slate-500 mt-0.5">Stay informed about new college requirements, direct invitations, and assignment updates.</p>
        </div>
        <?php if ($unreadCount > 0): ?>
            <a href="/trainer/notifications.php?action=mark_all_read" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#FE5E04] hover:text-[#E04E00] bg-orange-50 hover:bg-orange-100/80 border border-orange-200 px-4 py-2 rounded-xl transition-all shadow-2xs self-start sm:self-auto">
                <span class="material-symbols-outlined text-sm">done_all</span>
                <span>Mark all as read (<?= $unreadCount ?>)</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Real-Time Device / Mobile Push Notification Control Card -->
    <div id="mobilePushStatusCard" class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white rounded-2xl sm:rounded-3xl p-4 sm:p-5 border border-slate-700/80 shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5 min-w-0">
            <div id="mobilePushIconBox" data-push-icon="1" class="w-10 h-10 rounded-2xl bg-orange-500/20 text-[#FE5E04] border border-orange-500/30 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">notifications_active</span>
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-extrabold text-sm text-white">Mobile Device Push Notifications</h3>
                    <span id="mobilePushBadge" data-push-badge="1" class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">Action Required</span>
                </div>
                <p id="mobilePushDesc" data-push-desc="1" class="text-xs text-slate-300 mt-1 leading-relaxed">
                    Receive instant alerts on your phone screen outside the app when you are selected, shortlisted, or when new opportunities match.
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto">
            <button id="enableMobilePushBtn" data-push-enable-btn="1" type="button" onclick="requestMentryDeviceNotifications()" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all shadow-md flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">notifications</span>
                <span>Enable Mobile Alerts</span>
            </button>
            <button id="testMobilePushBtn" data-push-test-btn="1" type="button" onclick="sendTestDeviceNotification()" class="hidden bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">send_to_mobile</span>
                <span>Send Test Alert to Phone</span>
            </button>
        </div>
    </div>
    <script>
    (function() {
        try {
            if ('Notification' in window && Notification.permission === 'granted') {
                var b = document.getElementById('mobilePushBadge');
                var e = document.getElementById('enableMobilePushBtn');
                var t = document.getElementById('testMobilePushBtn');
                var d = document.getElementById('mobilePushDesc');
                var ic = document.getElementById('mobilePushIconBox');
                if (b) { b.textContent = '✓ ACTIVE ON THIS DEVICE'; b.className = 'text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'; }
                if (e) e.classList.add('hidden');
                if (t) t.classList.remove('hidden');
                if (d) d.textContent = 'Mobile push alerts are active! Real-time notifications will pop on your phone screen outside the app.';
                if (ic) ic.className = 'w-10 h-10 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center shrink-0';
            }
        } catch(e) {}
    })();
    </script>

    <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-card divide-y divide-slate-100 overflow-hidden min-w-0">
        <?php if (empty($notifications)): ?>
            <div class="p-12 text-center text-xs text-slate-400">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined text-2xl">notifications_off</span>
                </div>
                <p class="font-bold text-slate-700 text-sm">You're all caught up!</p>
                <p class="mt-1">No notifications or match alerts found at this time.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): 
                $isUnread = empty($n['read']);
                $type = $n['type'] ?? 'OPPORTUNITY_MATCH';
                
                // Extract opportunity ID if this notification is opportunity-related
                $oppId = $n['opportunityId'] ?? '';
                if (empty($oppId) && !empty($n['metadata']['opportunityId'])) {
                    $oppId = $n['metadata']['opportunityId'];
                }
                if (empty($oppId) && !empty($n['link']) && preg_match('/[?&]id=([a-f\d]{24}|[A-Za-z0-9_-]+)/i', $n['link'], $m)) {
                    $oppId = $m[1];
                }

                // Determine target destination (always keep trainer inside the Trainer Portal)
                $targetUrl = null;
                $actionLabel = 'View Details';

                if (!empty($oppId)) {
                    $targetUrl = '/trainer/opportunities.php?id=' . (string)$oppId;
                    $actionLabel = 'View Opportunity';
                } elseif (in_array($type, ['ASSIGNMENT_CONFIRMED', 'ASSIGNMENT_UPDATE'])) {
                    $targetUrl = '/trainer/assignments.php';
                    $actionLabel = 'View Assignment';
                } elseif ($type === 'APPLICATION_STATUS_UPDATE') {
                    $targetUrl = '/trainer/applications.php';
                    $actionLabel = 'View Application';
                } elseif ($type === 'PROFILE_VERIFIED') {
                    $targetUrl = '/trainer/profile.php';
                    $actionLabel = 'View Profile';
                } elseif ($type === 'DOCUMENT_APPROVED') {
                    $targetUrl = '/trainer/documents.php';
                    $actionLabel = 'View Documents';
                } elseif (!empty($n['link'])) {
                    $targetUrl = $n['link'];
                    if (strpos($targetUrl, '/opportunity-details.php') !== false) {
                        $targetUrl = str_replace('/opportunity-details.php', '/trainer/opportunities.php', $targetUrl);
                    }
                    $actionLabel = 'View Details';
                } else {
                    $targetUrl = '/trainer/opportunities.php';
                    $actionLabel = 'Browse Opportunities';
                }

                $clickUrl = "/trainer/notifications.php?read_id=" . (string)$n['_id'] . "&goto=" . urlencode($targetUrl);

                $icon = 'campaign';
                $iconBg = 'bg-blue-50 text-blue-600';
                $badgeText = 'Match Alert';
                $badgeClass = 'bg-blue-50 text-blue-700 border-blue-200/80';

                if ($type === 'DIRECT_INVITATION') {
                    $icon = 'mail';
                    $iconBg = 'bg-purple-50 text-purple-600';
                    $badgeText = 'Invitation';
                    $badgeClass = 'bg-purple-50 text-purple-700 border-purple-200/80';
                } elseif (in_array($type, ['ASSIGNMENT_CONFIRMED', 'ASSIGNMENT_UPDATE'])) {
                    $icon = 'event_available';
                    $iconBg = 'bg-emerald-50 text-emerald-600';
                    $badgeText = 'Assignment';
                    $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200/80';
                } elseif ($type === 'APPLICATION_STATUS_UPDATE') {
                    $icon = 'assignment_turned_in';
                    $iconBg = 'bg-amber-50 text-amber-600';
                    $badgeText = 'Application Update';
                    $badgeClass = 'bg-amber-50 text-amber-700 border-amber-200/80';
                } elseif ($type === 'PROFILE_VERIFIED') {
                    $icon = 'verified';
                    $iconBg = 'bg-teal-50 text-teal-600';
                    $badgeText = 'Verification';
                    $badgeClass = 'bg-teal-50 text-teal-700 border-teal-200/80';
                } elseif ($type === 'DOCUMENT_APPROVED') {
                    $icon = 'description';
                    $iconBg = 'bg-indigo-50 text-indigo-600';
                    $badgeText = 'Documents';
                    $badgeClass = 'bg-indigo-50 text-indigo-700 border-indigo-200/80';
                }
            ?>
                <a href="<?= htmlspecialchars($clickUrl) ?>" class="group block p-4 sm:p-5 transition-all min-w-0 <?= $isUnread ? 'bg-orange-50/25 border-l-4 border-l-[#FE5E04] hover:bg-orange-50/40' : 'hover:bg-slate-50/70' ?>" title="Click to view details">
                    <div class="flex items-start gap-3 sm:gap-4">
                        <div class="w-10 h-10 rounded-xl <?= $iconBg ?> flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform shadow-2xs">
                            <span class="material-symbols-outlined text-xl"><?= $icon ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md border <?= $badgeClass ?>">
                                    <?= $badgeText ?>
                                </span>
                                <?php if ($isUnread): ?>
                                    <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-[#FE5E04] text-white">
                                        NEW
                                    </span>
                                <?php endif; ?>
                                <span class="text-[11px] font-medium text-slate-400 ml-auto shrink-0">
                                    <?= formatRelativeTime($n['createdAt'] ?? null) ?>
                                </span>
                            </div>

                            <h4 class="font-bold text-xs sm:text-sm text-slate-900 group-hover:text-[#FE5E04] transition-colors leading-snug break-words">
                                <?= htmlspecialchars($n['title'] ?? 'Notification') ?>
                            </h4>

                            <p class="text-xs text-slate-600 mt-1 leading-relaxed break-words">
                                <?= htmlspecialchars($n['message'] ?? '') ?>
                            </p>

                            <div class="mt-3 flex items-center gap-1.5 text-xs font-bold text-blue-600 group-hover:text-[#FE5E04] transition-colors">
                                <span><?= htmlspecialchars($actionLabel) ?></span>
                                <span class="material-symbols-outlined text-sm transition-transform group-hover:translate-x-1">arrow_forward</span>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
