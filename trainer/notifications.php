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

    <!-- Trainer Device Push Notification Bar -->
    <div id="trainerDevicePushCard" class="bg-gradient-to-r from-slate-900 to-[#0B132B] border border-slate-800 text-white rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-md">
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <div id="pushStatusIconBox" class="w-10 h-10 rounded-xl bg-[#FE5E04]/20 border border-[#FE5E04]/30 text-[#FE5E04] flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]" id="pushStatusIcon">notifications_active</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="font-extrabold text-xs text-white">Mobile & Lock Screen Alerts</h3>
                    <span id="pushStatusBadge" class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">Checking...</span>
                </div>
                <p class="text-[11px] text-slate-300 mt-0.5" id="pushStatusDesc">Receive push notifications on your phone even when Mentry is closed.</p>
            </div>
        </div>
        <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
            <button type="button" id="btnSyncPush" onclick="syncTrainerPushDevice()" class="w-full sm:w-auto bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-sm">notifications</span>
                <span id="btnSyncPushText">Connect Phone Alerts</span>
            </button>
        </div>
    </div>

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

<script>
function getAppBaseUrl() {
    const path = window.location.pathname;
    if (path.includes('/Mentry%20solution')) return '/Mentry%20solution';
    if (path.includes('/Mentry solution')) return '/Mentry solution';
    return '';
}

function urlB64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

async function updatePushStatusUI() {
    const badge = document.getElementById('pushStatusBadge');
    const desc = document.getElementById('pushStatusDesc');
    const btn = document.getElementById('btnSyncPush');
    const btnText = document.getElementById('btnSyncPushText');
    const iconBox = document.getElementById('pushStatusIconBox');
    if (!badge || !btn) return;

    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
        badge.textContent = 'UNSUPPORTED';
        badge.className = 'text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700';
        desc.textContent = 'Push notifications are not supported on this browser.';
        btn.classList.add('hidden');
        return;
    }

    if (Notification.permission === 'denied') {
        badge.textContent = 'BLOCKED';
        badge.className = 'text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30';
        desc.textContent = 'Notifications blocked in browser. Tap the padlock/tune icon in your address bar to enable.';
        btn.classList.add('hidden');
        return;
    }

    if (Notification.permission === 'granted') {
        try {
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                // Verify with backend that this endpoint is active and not rejected
                const base = getAppBaseUrl();
                let serverCheck = null;
                try {
                    const chkRes = await fetch(base + '/actions/check-subscription-status.php?_t=' + Date.now(), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ endpoint: sub.endpoint })
                    });
                    serverCheck = await chkRes.json();
                } catch(e) {}

                if (serverCheck && serverCheck.success && serverCheck.active && !serverCheck.isDead && !serverCheck.requireRefresh) {
                    badge.textContent = '✓ ACTIVE ON THIS DEVICE';
                    badge.className = 'text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                    desc.textContent = 'Push alerts connected via production VAPID. Alerts arrive even when Mentry is closed.';
                    btnText.textContent = 'Send Test Alert';
                    btn.className = 'w-full sm:w-auto bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5 cursor-pointer border border-slate-700';
                    iconBox.className = 'w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 flex items-center justify-center shrink-0';
                    btn.onclick = sendTrainerTestPush;
                    return;
                } else {
                    // Gateway rejected or stale token: trigger automatic background renewal
                    badge.textContent = '● RENEWING TOKEN...';
                    badge.className = 'text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30';
                    desc.textContent = 'Refreshing push gateway token with production VAPID authority...';
                    btnText.textContent = 'Reconnect Alerts';
                    btn.onclick = () => forceRenewPushSubscription(true);
                    forceRenewPushSubscription(false);
                    return;
                }
            } else {
                // Permission granted but no subscription: auto-subscribe
                badge.textContent = '● CONNECTING...';
                badge.className = 'text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30';
                desc.textContent = 'Registering device with production VAPID push gateway...';
                btnText.textContent = 'Connecting...';
                forceRenewPushSubscription(false);
                return;
            }
        } catch(e) {}
    }

    badge.textContent = 'NOT CONNECTED';
    badge.className = 'text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30';
    desc.textContent = 'Receive push notifications on your phone lock screen outside the app.';
    btnText.textContent = 'Connect Phone Alerts';
    btn.onclick = () => forceRenewPushSubscription(true);
}

async function sendTrainerTestPush() {
    const btnText = document.getElementById('btnSyncPushText');
    const base = getAppBaseUrl();
    btnText.textContent = 'Sending...';

    try {
        const testRes = await fetch(base + '/actions/send-test-trainer-notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'title=' + encodeURIComponent('🔔 Mentry Test Alert') + 
                  '&message=' + encodeURIComponent('Live background alert confirmed! Notifications will reach you even when Mentry is closed.')
        });
        const testData = await testRes.json();
        btnText.textContent = 'Send Test Alert';
        if (testData.success && testData.pushDeliveredCount > 0) {
            alert('✓ ' + (testData.message || 'Test alert delivered!'));
        } else {
            alert('Push Gateway Response: ' + (testData.message || 'Delivery in progress'));
            // Re-verify UI in case token was invalidated
            updatePushStatusUI();
        }
    } catch(err) {
        btnText.textContent = 'Send Test Alert';
        alert('Test error: ' + err.message);
    }
}

async function forceRenewPushSubscription(showUserAlert = false) {
    const btnText = document.getElementById('btnSyncPushText');
    const base = getAppBaseUrl();
    if (btnText) btnText.textContent = 'Connecting...';

    try {
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') {
            if (showUserAlert) {
                alert('Notification permission was denied. Please enable notifications in your browser address bar.');
            }
            updatePushStatusUI();
            return;
        }

        const res = await fetch(base + '/actions/get-vapid-public-key.php?_t=' + Date.now(), { cache: 'no-store' });
        const data = await res.json();
        if (!data || !data.publicKey) throw new Error('Could not fetch server VAPID key');

        let reg = await navigator.serviceWorker.ready;
        if (!reg) {
            reg = await navigator.serviceWorker.register(base + '/sw.js');
        }

        const convertedKey = urlB64ToUint8Array(data.publicKey);
        const existingSub = await reg.pushManager.getSubscription();
        let oldEndpoint = null;

        if (existingSub) {
            oldEndpoint = existingSub.endpoint;
            try {
                await existingSub.unsubscribe();
            } catch(e) {}
        }

        // Fresh subscription with current production VAPID key
        const newSub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: convertedKey
        });

        const subJson = JSON.parse(JSON.stringify(newSub));
        subJson.platform = navigator.platform || 'Unknown';
        subJson.device = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent) ? 'Mobile' : 'Desktop';
        subJson.browser = navigator.userAgent;
        if (oldEndpoint) {
            subJson.oldEndpoint = oldEndpoint;
        }

        await fetch(base + '/actions/save-push-subscription.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(subJson)
        });

        updatePushStatusUI();

        if (showUserAlert) {
            alert('🎉 Phone Alerts Connected! You will receive push notifications even when Mentry is completely closed.');
        }
    } catch(err) {
        if (btnText) btnText.textContent = 'Retry Connection';
        if (showUserAlert) {
            alert('Push registration error: ' + err.message);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updatePushStatusUI();
});
</script>


</main>
</div>
</body>
</html>
