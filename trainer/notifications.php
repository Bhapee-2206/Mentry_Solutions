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

    <!-- Trainer Device Push Notification Card (One-Tap & Smart Recovery) -->
    <style>
        .notification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 2px 8px;
            border-radius: 9999px;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .notification-badge.default {
            background-color: rgba(51, 65, 85, 0.6);
            color: #cbd5e1;
            border-color: rgba(71, 85, 105, 0.8);
        }
        .notification-badge.connecting {
            background-color: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            border-color: rgba(59, 130, 246, 0.3);
        }
        .notification-badge.connected {
            background-color: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
            border-color: rgba(16, 185, 129, 0.3);
        }
        .notification-badge.blocked {
            background-color: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }
        .notification-badge.error {
            background-color: rgba(245, 158, 11, 0.2);
            color: #fcd34d;
            border-color: rgba(245, 158, 11, 0.3);
        }
    </style>

    <div id="mobileNotificationCard" class="bg-gradient-to-br from-slate-950 via-slate-900 to-[#0B132B] border border-slate-800 text-white rounded-2xl p-4 sm:p-5 shadow-lg transition-all duration-300">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div id="notificationStatusIconBox" class="w-10 h-10 rounded-xl bg-[#FE5E04]/20 border border-[#FE5E04]/30 text-[#FE5E04] flex items-center justify-center shrink-0 mt-0.5 sm:mt-0 transition-colors">
                    <span class="material-symbols-outlined text-[22px]" id="notificationStatusIcon">notifications_active</span>
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-extrabold text-xs sm:text-sm text-white tracking-tight">Mobile & Lock Screen Alerts</h3>
                        <span id="notificationConnectionBadge" class="notification-badge default">NOT CONNECTED</span>
                    </div>
                    <div id="notificationConnectionMessage" class="text-xs text-slate-300 leading-relaxed">
                        Get instant alerts for:
                        <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 mt-1 text-[11px] text-slate-300 font-medium">
                            <span>• New opportunities</span>
                            <span>• Assignment updates</span>
                            <span>• Interview schedules</span>
                            <span>• Training reminders</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto shrink-0 pt-1 sm:pt-0">
                <button type="button" id="enableNotificationsBtn" onclick="enableNotifications()" class="w-full sm:w-auto bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[17px]">notifications</span>
                    <span id="enableNotificationsBtnText">Enable Notifications</span>
                </button>
                <button type="button" id="sendTestNotificationBtn" onclick="sendTestNotification()" class="hidden w-full sm:w-auto bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl transition-all shadow-sm flex items-center justify-center gap-1.5 cursor-pointer border border-slate-700">
                    <span class="material-symbols-outlined text-[17px]">send_to_mobile</span>
                    <span>Send Test Alert</span>
                </button>
            </div>
        </div>
    </div>

    <!-- In-App Notification Settings Recovery Modal (No Browser Alerts) -->
    <div id="notificationSettingsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-4 animate-in fade-in duration-200">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5 text-slate-900">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[22px]">notifications_off</span>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-base text-slate-900">Notifications are blocked</h3>
                        <p class="text-xs text-slate-500">Allow notifications in Chrome to receive alerts</p>
                    </div>
                </div>
                <button type="button" onclick="closeNotificationSettingsModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-xl cursor-pointer">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 text-xs space-y-2.5">
                <p class="font-bold text-slate-800">To receive alerts when Mentry is closed:</p>
                <ol class="list-decimal list-inside space-y-2 text-slate-600 font-medium leading-relaxed pl-1">
                    <li>Tap the browser menu <strong class="text-slate-900">⋮</strong> (or padlock <strong class="text-slate-900">🔒</strong> in address bar)</li>
                    <li>Open <strong class="text-slate-900">Site settings</strong> / Permissions</li>
                    <li>Tap <strong class="text-slate-900">Notifications</strong></li>
                    <li>Select <strong class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">Allow</strong></li>
                    <li>Return to Mentry</li>
                    <li>Tap <strong class="text-slate-900">"Check Again"</strong></li>
                </ol>
            </div>

            <div id="modalRecoveryStatus" class="hidden text-xs text-center font-bold"></div>

            <div class="flex items-center gap-2.5 pt-1">
                <button type="button" onclick="handleCheckAgainFromModal()" id="btnModalCheckAgain" class="flex-1 bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs py-2.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[17px]">refresh</span>
                    <span>Check Again</span>
                </button>
                <button type="button" onclick="closeNotificationSettingsModal()" class="px-4 py-2.5 text-xs font-bold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all cursor-pointer">
                    Close
                </button>
            </div>
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

                $isAssignmentNotif = (
                    in_array($type, ['ASSIGNMENT_CONFIRMED', 'ASSIGNMENT_CREATED', 'ASSIGNMENT_UPDATE', 'TRAINER_SELECTED', 'TRAINER_ASSIGNED', 'APPLICATION_ACCEPTED']) ||
                    (!empty($n['link']) && strpos($n['link'], 'assignments') !== false) ||
                    (!empty($n['title']) && (stripos($n['title'], 'Selected') !== false || stripos($n['title'], 'Assignment') !== false || stripos($n['title'], 'Accepted') !== false)) ||
                    (!empty($n['message']) && (stripos($n['message'], 'selected for') !== false || stripos($n['message'], 'assignment is scheduled') !== false || stripos($n['message'], 'confirmed as the faculty') !== false))
                );

                if ($isAssignmentNotif) {
                    $targetUrl = '/trainer/assignments.php';
                    $actionLabel = 'View Assignment';
                } elseif ($type === 'APPLICATION_STATUS_UPDATE' || strpos($type, 'APPLICATION_') === 0) {
                    if (stripos($n['title'] ?? '', 'Accepted') !== false || stripos($n['message'] ?? '', 'ACCEPTED') !== false) {
                        $targetUrl = '/trainer/assignments.php';
                        $actionLabel = 'View Assignment';
                    } else {
                        $targetUrl = '/trainer/applications.php';
                        $actionLabel = 'View Application';
                    }
                } elseif ($type === 'DIRECT_INVITATION') {
                    $targetUrl = !empty($oppId) ? ('/trainer/opportunities.php?id=' . (string)$oppId) : '/trainer/opportunities.php';
                    $actionLabel = 'View Invitation';
                } elseif (!empty($oppId)) {
                    $targetUrl = '/trainer/opportunities.php?id=' . (string)$oppId;
                    $actionLabel = 'View Opportunity';
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

                if ($isAssignmentNotif) {
                    $icon = 'event_available';
                    $iconBg = 'bg-emerald-50 text-emerald-600';
                    $badgeText = 'Assignment';
                    $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200/80';
                } elseif ($type === 'DIRECT_INVITATION') {
                    $icon = 'mail';
                    $iconBg = 'bg-purple-50 text-purple-600';
                    $badgeText = 'Invitation';
                    $badgeClass = 'bg-purple-50 text-purple-700 border-purple-200/80';
                } elseif ($type === 'APPLICATION_STATUS_UPDATE' || strpos($type, 'APPLICATION_') === 0) {
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

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function getOrCreateDeviceId() {
    try {
        let id = localStorage.getItem('mentry_device_id');
        if (!id || typeof id !== 'string' || id.length < 10) {
            id = 'dev_' + (window.crypto && crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : (Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 10)));
            localStorage.setItem('mentry_device_id', id);
        }
        return id;
    } catch(e) {
        return 'dev_anon_' + Date.now().toString(36);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Step 11: Async operation timeout wrapper (Default: 15s)
 * Prevents UI from ever hanging in "Connecting..." indefinitely.
 */
function withTimeout(promise, milliseconds = 15000) {
    return Promise.race([
        promise,
        new Promise(function(_, reject) {
            setTimeout(function() {
                reject(new Error('Notification setup timed out. Please try again.'));
            }, milliseconds);
        })
    ]);
}

/**
 * Step 5: Smart Notification States
 * Supported states: 'connected', 'connecting', 'blocked', 'unsupported', 'error', 'default'
 */
function showNotificationState(state, error = null) {
    const card = document.querySelector('#mobileNotificationCard');
    const button = document.querySelector('#enableNotificationsBtn');
    const buttonText = document.querySelector('#enableNotificationsBtnText');
    const badge = document.querySelector('#notificationConnectionBadge');
    const status = document.querySelector('#notificationConnectionMessage');
    const testBtn = document.querySelector('#sendTestNotificationBtn');
    const iconBox = document.querySelector('#notificationStatusIconBox');
    const icon = document.querySelector('#notificationStatusIcon');

    if (!card || !button || !badge || !status) {
        return;
    }

    if (testBtn) testBtn.classList.add('hidden');

    /*
     * -----------------------------------------------
     * CONNECTED
     * -----------------------------------------------
     */
    if (state === 'connected') {
        badge.textContent = '✓ CONNECTED';
        badge.className = 'notification-badge connected';
        status.textContent = 'You will receive alerts even when Mentry is closed.';

        button.disabled = true;
        button.className = 'w-full sm:w-auto bg-emerald-600/20 border border-emerald-500/30 text-emerald-300 font-bold text-xs px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-1.5 cursor-default';
        if (buttonText) buttonText.textContent = '✓ Notifications Enabled';
        else button.innerHTML = '✓ Notifications Enabled';

        if (iconBox) iconBox.className = 'w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 flex items-center justify-center shrink-0 transition-colors';
        if (icon) icon.textContent = 'verified';

        if (testBtn) {
            testBtn.classList.remove('hidden');
        }
        return;
    }

    /*
     * -----------------------------------------------
     * CONNECTING
     * -----------------------------------------------
     */
    if (state === 'connecting') {
        badge.textContent = 'CONNECTING';
        badge.className = 'notification-badge connecting';
        status.textContent = 'Setting up notifications...';

        button.disabled = true;
        button.className = 'w-full sm:w-auto bg-blue-600/30 border border-blue-500/30 text-blue-200 font-bold text-xs px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-1.5 cursor-wait';
        if (buttonText) buttonText.textContent = '🔄 Connecting...';
        else button.innerHTML = '🔄 Connecting...';
        return;
    }

    /*
     * -----------------------------------------------
     * BLOCKED
     * -----------------------------------------------
     */
    if (state === 'blocked') {
        badge.textContent = '⚠ BLOCKED';
        badge.className = 'notification-badge blocked';
        status.innerHTML = `
            Notifications are blocked in your browser.
            <br>
            <small class="text-slate-400">
                Allow notifications for Mentry to receive alerts outside the app.
            </small>
        `;

        button.disabled = false;
        button.className = 'w-full sm:w-auto bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5 cursor-pointer';
        button.onclick = openNotificationSettings;
        if (buttonText) buttonText.textContent = '⚙️ Open Notification Settings';
        else button.innerHTML = '⚙️ Open Notification Settings';

        if (iconBox) iconBox.className = 'w-10 h-10 rounded-xl bg-rose-500/20 border border-rose-500/30 text-rose-400 flex items-center justify-center shrink-0 transition-colors';
        if (icon) icon.textContent = 'notifications_off';
        return;
    }

    /*
     * -----------------------------------------------
     * UNSUPPORTED
     * -----------------------------------------------
     */
    if (state === 'unsupported') {
        badge.textContent = 'NOT SUPPORTED';
        badge.className = 'notification-badge blocked';
        status.textContent = 'This browser does not support push notifications.';

        button.disabled = true;
        button.className = 'w-full sm:w-auto bg-slate-800/60 border border-slate-800 text-slate-500 font-bold text-xs px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-1.5 cursor-not-allowed';
        if (buttonText) buttonText.textContent = 'Notifications Unsupported';
        else button.innerHTML = 'Notifications Unsupported';
        return;
    }

    /*
     * -----------------------------------------------
     * ERROR
     * -----------------------------------------------
     */
    if (state === 'error') {
        badge.textContent = 'NOT CONNECTED';
        badge.className = 'notification-badge error';
        const errMsg = (error && error.message) ? error.message : 'Please try again.';
        status.innerHTML = `
            We couldn't connect notifications right now.
            <br>
            <small class="text-amber-300/80">
                ${escapeHtml(errMsg)}
            </small>
        `;

        button.disabled = false;
        button.className = 'w-full sm:w-auto bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5 cursor-pointer';
        button.onclick = enableNotifications;
        if (buttonText) buttonText.textContent = '🔔 Try Again';
        else button.innerHTML = '🔔 Try Again';
        return;
    }

    /*
     * -----------------------------------------------
     * DEFAULT / NOT CONNECTED (State 1)
     * -----------------------------------------------
     */
    badge.textContent = 'NOT CONNECTED';
    badge.className = 'notification-badge default';
    status.innerHTML = `
        Get instant alerts for:
        <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 mt-1 text-[11px] text-slate-300 font-medium">
            <span>• New opportunities</span>
            <span>• Assignment updates</span>
            <span>• Interview schedules</span>
            <span>• Training reminders</span>
        </div>
    `;

    button.disabled = false;
    button.className = 'w-full sm:w-auto bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5 cursor-pointer';
    button.onclick = enableNotifications;
    if (buttonText) buttonText.textContent = '🔔 Enable Notifications';
    else button.innerHTML = '🔔 Enable Notifications';

    if (iconBox) iconBox.className = 'w-10 h-10 rounded-xl bg-[#FE5E04]/20 border border-[#FE5E04]/30 text-[#FE5E04] flex items-center justify-center shrink-0 transition-colors';
    if (icon) icon.textContent = 'notifications_active';
}

/**
 * Step 3: One-Tap Enable Flow
 * Invoked ONLY when user explicitly taps "Enable Notifications"
 */
async function enableNotifications() {
    const button = document.querySelector('#enableNotificationsBtn');

    try {
        if (button) button.disabled = true;
        showNotificationState('connecting');

        if (window.MentryPush && typeof window.MentryPush.enable === 'function') {
            const success = await window.MentryPush.enable();
            if (success) {
                showNotificationState('connected');
            } else if (Notification.permission === 'denied') {
                showNotificationState('blocked');
            } else {
                showNotificationState('default');
            }
            return;
        }

        if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            showNotificationState('unsupported');
            return;
        }

        let permission = Notification.permission;
        if (permission === 'default') {
            permission = await withTimeout(Notification.requestPermission(), 15000);
        }

        if (permission === 'granted') {
            await connectPushSubscription();
            showNotificationState('connected');
            return;
        }

        if (permission === 'denied') {
            showNotificationState('blocked');
            return;
        }

        showNotificationState('default');
    } catch (error) {
        console.error('[Mentry Notifications]', error);
        showNotificationState('error', error);
    } finally {
        if (button && Notification.permission !== 'granted') {
            button.disabled = false;
        }
    }
}

/**
 * Step 4: Connect Push Subscription
 * Authoritative push registration and backend persistence
 */
async function connectPushSubscription() {
    const base = getAppBaseUrl();

    /*
     * Wait for the active Service Worker with timeout.
     */
    let registration = null;
    try {
        registration = await withTimeout(navigator.serviceWorker.ready, 15000);
    } catch(e) {
        registration = await withTimeout(navigator.serviceWorker.register(base + '/sw.js'), 15000);
        registration = await withTimeout(navigator.serviceWorker.ready, 10000);
    }

    if (!registration) {
        throw new Error('Service Worker is not ready.');
    }

    /*
     * --------------------------------------------------
     * GET VAPID PUBLIC KEY
     * --------------------------------------------------
     */
    const vapidResponse = await withTimeout(
        fetch(base + '/actions/get-vapid-public-key.php', {
            credentials: 'same-origin',
            cache: 'no-store'
        }),
        15000
    );

    if (!vapidResponse.ok) {
        throw new Error('Unable to load notification configuration.');
    }

    const vapidData = await vapidResponse.json();
    const publicKey = vapidData.publicKey;

    if (!publicKey) {
        throw new Error('Notification configuration is unavailable.');
    }

    const convertedKey = urlBase64ToUint8Array(publicKey);

    /*
     * --------------------------------------------------
     * CHECK EXISTING SUBSCRIPTION
     * --------------------------------------------------
     */
    let subscription = await withTimeout(registration.pushManager.getSubscription(), 15000);

    if (subscription) {
        const subKey = subscription.options && subscription.options.applicationServerKey;
        if (subKey) {
            const subBytes = new Uint8Array(subKey);
            let match = subBytes.length === convertedKey.length;
            if (match) {
                for (let i = 0; i < subBytes.length; i++) {
                    if (subBytes[i] !== convertedKey[i]) { match = false; break; }
                }
            }
            if (!match) {
                try { await subscription.unsubscribe(); } catch(e) {}
                subscription = null;
            }
        }
    }

    /*
     * --------------------------------------------------
     * CREATE ONLY IF NEEDED
     * --------------------------------------------------
     */
    if (!subscription) {
        subscription = await withTimeout(
            registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedKey
            }),
            15000
        );
    }

    if (!subscription) {
        throw new Error('Browser could not create a push subscription.');
    }

    /*
     * --------------------------------------------------
     * SAVE TO SERVER
     * --------------------------------------------------
     */
    const subscriptionJson = subscription.toJSON();

    if (
        !subscriptionJson.endpoint ||
        !subscriptionJson.keys ||
        !subscriptionJson.keys.p256dh ||
        !subscriptionJson.keys.auth
    ) {
        throw new Error('Browser returned an invalid push subscription.');
    }

    const deviceId = getOrCreateDeviceId();

    const response = await withTimeout(
        fetch(base + '/actions/save-push-subscription.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                endpoint: subscriptionJson.endpoint,
                keys: {
                    p256dh: subscriptionJson.keys.p256dh,
                    auth: subscriptionJson.keys.auth
                },
                deviceId: deviceId,
                device: /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent) ? 'Mobile' : 'Desktop',
                platform: navigator.platform || 'Unknown',
                browser: navigator.userAgent,
                userId: <?= json_encode((string)$user['id']) ?>
            })
        }),
        15000
    );

    const result = await response.json();

    if (!response.ok || !result.success) {
        throw new Error(result.message || result.error || 'Unable to save notification subscription.');
    }

    return {
        subscription,
        server: result
    };
}

/**
 * Step 6: Smart Browser Settings Handling (In-App Modal)
 */
function openNotificationSettings() {
    showNotificationSettingsModal();
}

function showNotificationSettingsModal() {
    const modal = document.getElementById('notificationSettingsModal');
    const status = document.getElementById('modalRecoveryStatus');
    if (status) status.classList.add('hidden');
    if (modal) modal.classList.remove('hidden');
}

function closeNotificationSettingsModal() {
    const modal = document.getElementById('notificationSettingsModal');
    if (modal) modal.classList.add('hidden');
}

async function handleCheckAgainFromModal() {
    const btn = document.getElementById('btnModalCheckAgain');
    const status = document.getElementById('modalRecoveryStatus');
    if (btn) btn.disabled = true;
    if (status) {
        status.className = 'text-xs text-center text-blue-600 font-bold';
        status.textContent = 'Checking browser permissions...';
        status.classList.remove('hidden');
    }

    await refreshNotificationStatus();

    if (Notification.permission === 'granted') {
        if (status) {
            status.className = 'text-xs text-center text-emerald-600 font-bold';
            status.textContent = '✓ Notifications allowed! Connected successfully.';
        }
        setTimeout(() => {
            closeNotificationSettingsModal();
            if (btn) btn.disabled = false;
        }, 800);
    } else {
        if (status) {
            status.className = 'text-xs text-center text-rose-600 font-bold';
            status.textContent = 'Still blocked in Chrome. Please set Notifications to "Allow" and try again.';
        }
        if (btn) btn.disabled = false;
    }
}

/**
 * Step 8: Refresh Status Function
 * Called passively on load, visibilitychange, and pageshow.
 * NEVER calls Notification.requestPermission() automatically.
 */
async function refreshNotificationStatus() {
    try {
        if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            showNotificationState('unsupported');
            return;
        }

        const permission = Notification.permission;

        if (permission === 'denied') {
            showNotificationState('blocked');
            return;
        }

        if (permission === 'default') {
            showNotificationState('default');
            return;
        }

        // Permission is 'granted'
        const registration = await withTimeout(navigator.serviceWorker.ready, 10000);
        let subscription = await withTimeout(registration.pushManager.getSubscription(), 10000);

        if (!subscription) {
            // Step 14: Stale / missing subscription recovery
            try {
                const conn = await connectPushSubscription();
                subscription = conn.subscription;
            } catch (recoveryErr) {
                console.warn('[Mentry Notifications] Background recovery attempt:', recoveryErr);
            }
        }

        if (subscription) {
            showNotificationState('connected');
        } else {
            showNotificationState('default');
        }
    } catch (error) {
        console.error('[Mentry Notifications] Status check failed:', error);
        if ('Notification' in window && Notification.permission === 'default') {
            showNotificationState('default');
        } else {
            showNotificationState('error', error);
        }
    }
}

/**
 * Step 17: Safe Self-Test Notification (Server-Side Web Push)
 */
async function sendTestNotification() {
    const btn = document.getElementById('sendTestNotificationBtn');
    const base = getAppBaseUrl();
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[17px] animate-spin">refresh</span><span>Sending...</span>';
    }

    try {
        const res = await withTimeout(
            fetch(base + '/actions/send-test-trainer-notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': '<?= getCsrfToken() ?>'
                },
                body: 'csrf_token=' + encodeURIComponent('<?= getCsrfToken() ?>') +
                      '&title=' + encodeURIComponent('Mentry Notifications Enabled') +
                      '&message=' + encodeURIComponent('Your Mentry notifications are working.')
            }),
            15000
        );

        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-[17px] text-emerald-400">check_circle</span><span>✓ Alert Sent!</span>';
            setTimeout(() => {
                if (btn) {
                    btn.innerHTML = '<span class="material-symbols-outlined text-[17px]">send_to_mobile</span><span>Send Test Alert</span>';
                }
            }, 3500);
        }
    } catch (err) {
        console.error('[Mentry Notifications] Test push error:', err);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-[17px] text-amber-400">error</span><span>Retry Test</span>';
        }
    }
}

/*
 * Step 7: Automatic Recheck Listeners
 */
document.addEventListener('visibilitychange', async function() {
    if (document.visibilityState !== 'visible') {
        return;
    }
    await refreshNotificationStatus();
});

window.addEventListener('pageshow', async function() {
    await refreshNotificationStatus();
});

/*
 * Step 9: Initial Page Load Check
 */
document.addEventListener('DOMContentLoaded', async function() {
    await refreshNotificationStatus();
});
</script>
<script src="/assets/js/push-notifications.js" defer></script>


</main>
</div>
</body>
</html>
