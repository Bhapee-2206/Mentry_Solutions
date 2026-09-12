<?php
// trainer/settings.php
$pageTitle = "Account & Notification Settings";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$updated = false;
$notifUpdated = false;
$error = null;

$userCol = getCollection("User");
$trainerCol = getCollection("Trainer");

$userQuery = ['_id' => (string)$user['id']];
if (preg_match('/^[a-f\d]{24}$/i', (string)$user['id'])) {
    try {
        $userQuery = ['$or' => [['_id' => (string)$user['id']], ['_id' => new MongoDB\BSON\ObjectId((string)$user['id'])]]];
    } catch (\Throwable $e) {}
}
$dbUser = $userCol ? $userCol->findOne($userQuery) : null;

$trainerQuery = ['userId' => (string)$user['id']];
if (preg_match('/^[a-f\d]{24}$/i', (string)$user['id'])) {
    try {
        $trainerQuery = ['$or' => [['userId' => (string)$user['id']], ['userId' => new MongoDB\BSON\ObjectId((string)$user['id'])]]];
    } catch (\Throwable $e) {}
}
$dbTrainer = $trainerCol ? $trainerCol->findOne($trainerQuery) : null;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'password';

    if ($action === 'notifications') {
        $allNotifs = isset($_POST['all_notifications']);
        $pushNotifs = $allNotifs && isset($_POST['push_notifications']);
        $emailOpp = $allNotifs && isset($_POST['email_opportunities']);
        $emailAssign = $allNotifs && isset($_POST['email_assignments']);
        $emailApp = $allNotifs && isset($_POST['email_applications']);
        $systemAnnounce = $allNotifs && isset($_POST['system_announcements']);

        $notifPrefs = [
            'all' => $allNotifs,
            'push_notifications' => $pushNotifs,
            'email_opportunities' => $emailOpp,
            'email_assignments' => $emailAssign,
            'email_applications' => $emailApp,
            'system_announcements' => $systemAnnounce,
            'updatedAt' => date('c')
        ];

        if ($userCol) {
            $userCol->updateOne($userQuery, [
                '$set' => [
                    'notificationPreferences' => $notifPrefs,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]
            ]);
        }

        if ($trainerCol) {
            $trainerCol->updateOne($trainerQuery, [
                '$set' => [
                    'notificationPreferences' => $notifPrefs,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]
            ]);
        }

        $_SESSION['user']['notificationPreferences'] = $notifPrefs;
        setPersistentSessionCookie($_SESSION['user']);
        $notifUpdated = true;

        // Refresh loaded user & trainer data
        $dbUser = $userCol ? $userCol->findOne($userQuery) : null;
        $dbTrainer = $trainerCol ? $trainerCol->findOne($trainerQuery) : null;
    } elseif ($action === 'password') {
        $currentPass = $_POST['currentPassword'] ?? '';
        $newPass = $_POST['newPassword'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            $error = "Both current and new passwords are required.";
        } else {
            if (!$dbUser || !verifyPassword($currentPass, $dbUser['password'])) {
                $error = "Current password is incorrect.";
            } else {
                $userCol->updateOne(
                    $userQuery,
                    ['$set' => ['password' => hashPassword($newPass), 'updatedAt' => new MongoDB\BSON\UTCDateTime()]]
                );
                $updated = true;
            }
        }
    }
}

// Load current notification preferences
$savedPrefs = $dbUser['notificationPreferences'] ?? ($dbTrainer['notificationPreferences'] ?? ($_SESSION['user']['notificationPreferences'] ?? []));
$prefs = array_merge([
    'all' => true,
    'push_notifications' => true,
    'email_opportunities' => true,
    'email_assignments' => true,
    'email_applications' => true,
    'system_announcements' => true
], is_array($savedPrefs) ? $savedPrefs : []);
?>

<div class="max-w-4xl mx-auto space-y-6 pb-12">
    <!-- Header Title -->
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Account & Notification Settings</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">Configure your real-time notification alerts, delivery channels, and account security.</p>
    </div>

    <!-- Alert Notifications -->
    <?php if ($notifUpdated): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 animate-in fade-in">
            <span class="material-symbols-outlined text-base text-emerald-600">check_circle</span>
            <span>Notification preferences updated successfully! Changes are active immediately.</span>
        </div>
    <?php endif; ?>

    <?php if ($updated): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 animate-in fade-in">
            <span class="material-symbols-outlined text-base text-emerald-600">check_circle</span>
            <span>Password updated successfully!</span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 animate-in fade-in">
            <span class="material-symbols-outlined text-base text-rose-600">error</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Card 1: Notification Settings (ON / OFF) -->
    <div class="bg-white p-5 sm:p-8 rounded-3xl border border-slate-200/90 shadow-card space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-orange-50 text-[#FE5E04] flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">notifications_active</span>
                </div>
                <div>
                    <h3 class="font-extrabold text-base text-slate-900">Notification Preferences</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Control which notifications and alerts you receive from Mentry.</p>
                </div>
            </div>

            <!-- Live Status Pill -->
            <div class="shrink-0">
                <?php if (!empty($prefs['all'])): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Notifications: ON (Active)
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        Notifications: OFF (Paused)
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" action="/trainer/settings.php" class="space-y-5">
            <input type="hidden" name="action" value="notifications">

            <!-- Master Toggle (All Notifications ON / OFF) -->
            <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-4 sm:p-5 flex items-center justify-between gap-4">
                <div class="space-y-1 pr-2">
                    <div class="flex items-center gap-2">
                        <span class="font-extrabold text-sm text-slate-900">Master Notifications Switch</span>
                        <span class="bg-[#FE5E04]/10 text-[#FE5E04] text-[10px] font-black uppercase px-2 py-0.5 rounded-full">All Alerts</span>
                    </div>
                    <p class="text-xs text-slate-500">Toggle all Mentry notifications ON or OFF across push, email, and portal alerts.</p>
                </div>

                <!-- Toggle Switch -->
                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" id="masterNotifToggle" name="all_notifications" value="1" <?= !empty($prefs['all']) ? 'checked' : '' ?> onchange="toggleAllNotifChildren(this.checked)" class="sr-only peer">
                    <div class="w-12 h-6.5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#FE5E04]"></div>
                </label>
            </div>

            <!-- Granular Channels -->
            <div id="subNotifContainer" class="space-y-3 transition-opacity duration-200 <?= empty($prefs['all']) ? 'opacity-40 pointer-events-none' : 'opacity-100' ?>">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block px-1">Alert Channels & Categories</span>

                <!-- 1. Web Push / Mobile Notifications -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-[20px]">notifications</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="font-bold text-xs sm:text-sm text-slate-900">Browser & PWA Push Notifications</h4>
                                <button type="button" onclick="window.enablePushNotifications()" class="text-[10px] font-bold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-2 py-0.5 rounded-md transition-colors cursor-pointer" title="Test browser permission">
                                    Test / Enable Push
                                </button>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">Receive real-time instant alerts on your phone or computer screen when openings match your skills.</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="push_notifications" value="1" <?= !empty($prefs['push_notifications']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- 2. Opportunity Match Emails -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-[20px]">work</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs sm:text-sm text-slate-900">New Opportunity Match Alerts</h4>
                            <p class="text-xs text-slate-500 mt-0.5">Get notified immediately when colleges or corporations post training requirements that match your domain.</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="email_opportunities" value="1" <?= !empty($prefs['email_opportunities']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>

                <!-- 3. Assignment Offers & Confirmations -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-[20px]">event_available</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs sm:text-sm text-slate-900">Assignment Offers & Deployment Schedules</h4>
                            <p class="text-xs text-slate-500 mt-0.5">Critical notifications when institutions confirm training dates, venue details, or issue formal assignments.</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="email_assignments" value="1" <?= !empty($prefs['email_assignments']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                </div>

                <!-- 4. Application Status Updates -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-[20px]">assignment_turned_in</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs sm:text-sm text-slate-900">Application Status & Admin Review</h4>
                            <p class="text-xs text-slate-500 mt-0.5">Receive updates when administrators shortlist, approve, or provide feedback on your submitted proposals.</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="email_applications" value="1" <?= !empty($prefs['email_applications']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                    </label>
                </div>

                <!-- 5. System Announcements -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-[20px]">campaign</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs sm:text-sm text-slate-900">System Advisories & Operational Announcements</h4>
                            <p class="text-xs text-slate-500 mt-0.5">Payment calendar notices, scheduled maintenance advisories, and trainer network guidelines.</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="system_announcements" value="1" <?= !empty($prefs['system_announcements']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-800"></div>
                    </label>
                </div>
            </div>

            <!-- Save Notification Preferences Button -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-6 py-3 rounded-xl shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[17px]">save</span>
                    <span>Save Notification Preferences</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Card 2: Password & Account Security -->
    <div class="bg-white p-5 sm:p-8 rounded-3xl border border-slate-200/90 shadow-card space-y-5">
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">lock</span>
            </div>
            <div>
                <h3 class="font-extrabold text-base text-slate-900">Account Password & Security</h3>
                <p class="text-xs text-slate-500 mt-0.5">Update your secure login password to keep your trainer account protected.</p>
            </div>
        </div>

        <form method="POST" action="/trainer/settings.php" class="space-y-4">
            <input type="hidden" name="action" value="password">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Current Password</label>
                <input type="password" name="currentPassword" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">New Password</label>
                <input type="password" name="newPassword" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>
            <div class="pt-2 flex items-center justify-end">
                <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs px-6 py-3 rounded-xl shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[17px]">vpn_key</span>
                    <span>Update Password</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAllNotifChildren(isEnabled) {
    const container = document.getElementById('subNotifContainer');
    if (container) {
        if (isEnabled) {
            container.classList.remove('opacity-40', 'pointer-events-none');
            container.classList.add('opacity-100');
            // Auto check sub-boxes if master is enabled
            document.querySelectorAll('.sub-notif-input').forEach(cb => cb.checked = true);
        } else {
            container.classList.remove('opacity-100');
            container.classList.add('opacity-40', 'pointer-events-none');
            // Uncheck sub-boxes if master is disabled
            document.querySelectorAll('.sub-notif-input').forEach(cb => cb.checked = false);
        }
    }
}
</script>

</main>
</div>
</body>
</html>
