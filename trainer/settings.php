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
            'notify_new_opportunities' => $allNotifs && isset($_POST['notify_new_opportunities']),
            'notify_opportunity_matches' => $allNotifs && isset($_POST['notify_opportunity_matches']),
            'notify_trainer_selection' => $allNotifs && isset($_POST['notify_trainer_selection']),
            'notify_interview_updates' => $allNotifs && isset($_POST['notify_interview_updates']),
            'notify_training_reminders' => $allNotifs && isset($_POST['notify_training_reminders']),
            'notify_payment_updates' => $allNotifs && isset($_POST['notify_payment_updates']),
            'notify_document_updates' => $allNotifs && isset($_POST['notify_document_updates']),
            'notify_college_messages' => $allNotifs && isset($_POST['notify_college_messages']),
            'notify_system_alerts' => $allNotifs && isset($_POST['notify_system_alerts']),
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

$savedPrefs = $dbUser['notificationPreferences'] ?? ($dbTrainer['notificationPreferences'] ?? ($_SESSION['user']['notificationPreferences'] ?? []));
$prefs = array_merge([
    'all' => true,
    'push_notifications' => true,
    'notify_new_opportunities' => true,
    'notify_opportunity_matches' => true,
    'notify_trainer_selection' => true,
    'notify_interview_updates' => true,
    'notify_training_reminders' => true,
    'notify_payment_updates' => true,
    'notify_document_updates' => true,
    'notify_college_messages' => true,
    'notify_system_alerts' => true,
    'email_opportunities' => true,
    'email_assignments' => true,
    'email_applications' => true,
    'system_announcements' => true
], is_array($savedPrefs) ? $savedPrefs : []);

// Query device push subscriptions for user
$subCol = getCollection("PushSubscription");
$userPushSubs = [];
if ($subCol && !empty($user['id'])) {
    $userPushSubs = $subCol->find([
        'userId' => (string)$user['id'],
        'isActive' => ['$ne' => false]
    ], ['sort' => ['lastActiveAt' => -1]])->toArray();
}
$activeDeviceCount = count($userPushSubs);
$primaryDeviceName = !empty($userPushSubs) ? ($userPushSubs[0]['device'] ?? 'Mobile') . ' • ' . ($userPushSubs[0]['browser'] ?? 'Browser') : 'This Device';
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

    <!-- Notification Status Card (Requirement 15) -->
    <div class="bg-gradient-to-br from-slate-900 via-slate-900 to-[#0B132B] text-white p-5 sm:p-6 rounded-3xl border border-slate-800 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-[#FE5E04]/20 border border-[#FE5E04]/30 text-[#FE5E04] flex items-center justify-center shrink-0 shadow-sm">
                    <span class="material-symbols-outlined text-2xl">devices</span>
                </div>
                <div>
                    <h3 class="font-extrabold text-sm sm:text-base text-white">Push Notifications</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Background delivery to your phone and computer screen</p>
                </div>
            </div>
            <div>
                <span id="settingsPushStatusBadge" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span id="settingsPushStatusText">● Enabled</span>
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <div class="p-3 rounded-2xl bg-slate-800/60 border border-slate-700/60 flex items-center justify-between">
                <span class="text-xs text-slate-400 font-medium">Device:</span>
                <span id="settingsPushDeviceName" class="text-xs font-bold text-white font-mono"><?= htmlspecialchars($primaryDeviceName) ?></span>
            </div>
            <div class="p-3 rounded-2xl bg-slate-800/60 border border-slate-700/60 flex items-center justify-between">
                <span class="text-xs text-slate-400 font-medium">Last active:</span>
                <span class="text-xs font-bold text-slate-300 font-mono">Today</span>
            </div>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row items-center gap-3">
            <button type="button" id="btnSettingsTestPush" onclick="triggerSettingsPushTest()" class="w-full sm:w-auto bg-[#FE5E04] hover:bg-[#e04e00] text-white font-bold text-xs py-2.5 px-4 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">send_to_mobile</span>
                <span>Test Notification</span>
            </button>
            <span id="settingsTestResult" class="text-xs text-slate-300 hidden font-mono"></span>
        </div>
    </div>

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
                                <button type="button" onclick="if (typeof window.sendTestDeviceNotification === 'function' && window.Notification && window.Notification.permission === 'granted') { window.sendTestDeviceNotification(); } else if (typeof window.requestMentryDeviceNotifications === 'function') { window.requestMentryDeviceNotifications(); } else if (typeof window.enablePushNotifications === 'function') { window.enablePushNotifications(); }" class="text-[10px] font-bold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-2.5 py-0.5 rounded-md transition-colors cursor-pointer" title="Test mobile push alert">
                                    Test Alert on Phone
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
                <!-- Granular Notification Categories -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                    <!-- 1. New Opportunities -->
                    <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors">
                        <div>
                            <h5 class="font-bold text-xs text-slate-900">New Opportunities</h5>
                            <p class="text-[11px] text-slate-400">Newly posted college & corporate openings</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                            <input type="checkbox" name="notify_new_opportunities" value="1" <?= !empty($prefs['notify_new_opportunities']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#FE5E04]"></div>
                        </label>
                    </div>

                    <!-- 2. Opportunity Matches -->
                    <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors">
                        <div>
                            <h5 class="font-bold text-xs text-slate-900">Opportunity Matches</h5>
                            <p class="text-[11px] text-slate-400">Personalized high-match domain alerts</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                            <input type="checkbox" name="notify_opportunity_matches" value="1" <?= !empty($prefs['notify_opportunity_matches']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <!-- 3. Trainer Selection -->
                    <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors">
                        <div>
                            <h5 class="font-bold text-xs text-slate-900">Trainer Selection</h5>
                            <p class="text-[11px] text-slate-400">Formal assignment awards & selection notices</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                            <input type="checkbox" name="notify_trainer_selection" value="1" <?= !empty($prefs['notify_trainer_selection']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>

                    <!-- 4. Interview Updates -->
                    <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors">
                        <div>
                            <h5 class="font-bold text-xs text-slate-900">Interview Updates</h5>
                            <p class="text-[11px] text-slate-400">Scheduled interviews & panel timings</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                            <input type="checkbox" name="notify_interview_updates" value="1" <?= !empty($prefs['notify_interview_updates']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>

                    <!-- 5. Training Reminders -->
                    <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors">
                        <div>
                            <h5 class="font-bold text-xs text-slate-900">Training Reminders</h5>
                            <p class="text-[11px] text-slate-400">Session countdowns & day-of logistics</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                            <input type="checkbox" name="notify_training_reminders" value="1" <?= !empty($prefs['notify_training_reminders']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                        </label>
                    </div>

                    <!-- 6. Payment Updates -->
                    <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors">
                        <div>
                            <h5 class="font-bold text-xs text-slate-900">Payment Updates</h5>
                            <p class="text-[11px] text-slate-400">Honorarium processing & payout transfers</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                            <input type="checkbox" name="notify_payment_updates" value="1" <?= !empty($prefs['notify_payment_updates']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-teal-600"></div>
                        </label>
                    </div>

                    <!-- 7. Document Updates -->
                    <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors">
                        <div>
                            <h5 class="font-bold text-xs text-slate-900">Document Updates</h5>
                            <p class="text-[11px] text-slate-400">KYC, certifications & profile verifications</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                            <input type="checkbox" name="notify_document_updates" value="1" <?= !empty($prefs['notify_document_updates']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>

                    <!-- 8. College Messages -->
                    <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors">
                        <div>
                            <h5 class="font-bold text-xs text-slate-900">College Messages</h5>
                            <p class="text-[11px] text-slate-400">Direct inquiries from academic coordinators</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                            <input type="checkbox" name="notify_college_messages" value="1" <?= !empty($prefs['notify_college_messages']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-cyan-600"></div>
                        </label>
                    </div>

                    <!-- 9. System Alerts -->
                    <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50/50 transition-colors md:col-span-2">
                        <div>
                            <h5 class="font-bold text-xs text-slate-900">System Alerts</h5>
                            <p class="text-[11px] text-slate-400">Critical account security advisories & platform maintenance</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                            <input type="checkbox" name="notify_system_alerts" value="1" <?= !empty($prefs['notify_system_alerts']) ? 'checked' : '' ?> class="sr-only peer sub-notif-input">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-slate-800"></div>
                        </label>
                    </div>
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

function getAppBaseUrl() {
    const path = window.location.pathname;
    if (path.includes('/Mentry%20solution')) return '/Mentry%20solution';
    if (path.includes('/Mentry solution')) return '/Mentry solution';
    return '';
}

function updateSettingsDeviceStatus() {
    const badge = document.getElementById('settingsPushStatusBadge');
    const text = document.getElementById('settingsPushStatusText');
    const devLabel = document.getElementById('settingsPushDeviceName');

    const isMobile = /Android|iPhone|iPad|Mobile/i.test(navigator.userAgent);
    const browser = /Edg/i.test(navigator.userAgent) ? 'Edge' : (/Chrome/i.test(navigator.userAgent) ? 'Chrome' : (/Safari/i.test(navigator.userAgent) ? 'Safari' : (/Firefox/i.test(navigator.userAgent) ? 'Firefox' : 'Browser')));
    const platform = isMobile ? 'Android' : 'Desktop';
    if (devLabel) {
        devLabel.textContent = platform + ' • ' + browser;
    }

    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
        if (text) text.textContent = '○ Unsupported';
        if (badge) badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-400 border border-slate-700';
    } else if (Notification.permission === 'granted') {
        if (text) text.textContent = '● Enabled';
        if (badge) badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
    } else if (Notification.permission === 'denied') {
        if (text) text.textContent = '○ Blocked';
        if (badge) badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30';
    } else {
        if (text) text.textContent = '○ Action Needed';
        if (badge) badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30';
    }
}

async function triggerSettingsPushTest() {
    const btn = document.getElementById('btnSettingsTestPush');
    const res = document.getElementById('settingsTestResult');
    if (!btn || !res) return;

    if (!('Notification' in window)) {
        alert('Push notifications are not supported on this browser.');
        return;
    }

    if (Notification.permission !== 'granted') {
        const perm = await Notification.requestPermission();
        updateSettingsDeviceStatus();
        if (perm !== 'granted') {
            alert('Notifications are blocked or not allowed. Please allow notifications in browser settings to receive alerts.');
            return;
        }
    }

    res.classList.remove('hidden');
    res.className = 'text-xs text-slate-300 font-mono';
    res.textContent = 'Sending test notification...';

    try {
        const base = getAppBaseUrl();
        const fd = new FormData();
        fd.append('title', '🔔 Mentry Alert Test');
        fd.append('message', 'Test notification successfully delivered to your device!');

        const response = await fetch(base + '/actions/send-test-trainer-notification.php', {
            method: 'POST',
            body: fd
        });
        const data = await response.json();

        if (data.success) {
            res.className = 'text-xs text-emerald-400 font-mono font-bold';
            res.textContent = '✓ ' + (data.message || 'Notification sent!');
        } else {
            res.className = 'text-xs text-rose-400 font-mono';
            res.textContent = 'Error: ' + (data.error || 'Delivery failed');
        }
    } catch(e) {
        res.className = 'text-xs text-rose-400 font-mono';
        res.textContent = 'Network notice: ' + e.message;
    }
}

document.addEventListener('DOMContentLoaded', updateSettingsDeviceStatus);
</script>

</main>
</div>
</body>
</html>
