<?php
// admin/push-debugger.php - Web Push Notification Diagnostic & Testing Tool
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/PushNotificationService.php';

requireAdminOrStaff();

$pageTitle = 'Web Push Diagnostics & Live Testing';
$user = getCurrentUser();

$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$subCol = getCollection("PushSubscription");
$logCol = getCollection("PushDeliveryLog");

$trainersList = [];
if ($trainerCol) {
    $trainersCursor = $trainerCol->find([], ['sort' => ['name' => 1], 'limit' => 50]);
    foreach ($trainersCursor as $t) {
        $uDoc = ($userCol && !empty($t['userId'])) ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$t['userId'])]) : null;
        $tName = $uDoc['name'] ?? ($t['name'] ?? 'Trainer');
        $trainersList[] = [
            'id' => (string)$t['_id'],
            'userId' => (string)($t['userId'] ?? ''),
            'code' => $t['trainerCode'] ?? ($t['mentryId'] ?? 'N/A'),
            'name' => $tName
        ];
    }
}

// Total active push subscriptions count
$totalActiveSubs = $subCol ? $subCol->countDocuments(['isActive' => ['$ne' => false]]) : 0;
$totalTrainerSubs = $subCol ? $subCol->countDocuments(['isActive' => ['$ne' => false], 'userRole' => 'TRAINER']) : 0;

// Recent delivery logs
$recentLogs = [];
if ($logCol) {
    $recentLogs = $logCol->find([], ['sort' => ['sentAt' => -1], 'limit' => 15])->toArray();
}

$vapidPublicKey = PushNotificationService::getPublicKey();

// 10-Point System Diagnostic Calculations (Requirement 16)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) || 
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
$httpsStatus = $isHttps ? 'PASS' : 'FAIL';

$hasOpenSslEc = function_exists('openssl_pkey_new') && function_exists('openssl_sign');
$hasCurl = function_exists('curl_init');
$backendPushServiceStatus = ($hasOpenSslEc && $hasCurl) ? 'CONNECTED' : 'ERROR';
$vapidStatus = !empty($vapidPublicKey) ? 'CONFIGURED' : 'MISSING';

$lastLog = !empty($recentLogs) ? $recentLogs[0] : null;
$lastPushAttempt = 'Never';
if ($lastLog && isset($lastLog['sentAt'])) {
    if ($lastLog['sentAt'] instanceof MongoDB\BSON\UTCDateTime) {
        $lastPushAttempt = $lastLog['sentAt']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M, h:i:s A');
    } else {
        $lastPushAttempt = 'Recently';
    }
}
$lastPushResult = $lastLog ? (!empty($lastLog['success']) ? 'SUCCESS' : 'FAILED') : 'N/A';

$lastErrorLog = null;
if ($logCol) {
    $lastErrorLog = $logCol->findOne(['success' => false], ['sort' => ['sentAt' => -1]]);
}
$lastErrorText = $lastErrorLog['error'] ?? 'None (Nominal)';

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto space-y-6 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Web Push Diagnostics & Testing</h1>
                <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 border border-blue-200">RFC 8292 VAPID</span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">Live developer tool to inspect device push subscriptions, verify FCM handshake, and test background delivery.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-600 bg-slate-100 px-3 py-1.5 rounded-xl font-bold flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span><?= $totalActiveSubs ?> Total Devices (<?= $totalTrainerSubs ?> Trainers)</span>
            </span>
        </div>
    </div>

    <!-- 10-Point Production Diagnostic Matrix (Requirement 16) -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600 text-[22px]">health_and_safety</span>
                <h3 class="font-extrabold text-sm text-slate-900 tracking-tight">System Diagnostic Status Matrix</h3>
            </div>
            <span class="text-[11px] font-mono text-slate-400 uppercase tracking-wider">Production Verification</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-xs">
            <!-- 1. HTTPS -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                <span class="text-slate-500 font-semibold text-[11px]">HTTPS:</span>
                <span id="matrixHttps" class="font-mono font-bold mt-2 text-xs <?= $isHttps ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $httpsStatus ?></span>
            </div>

            <!-- 2. Service Worker -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                <span class="text-slate-500 font-semibold text-[11px]">Service Worker:</span>
                <span id="matrixSw" class="font-mono font-bold mt-2 text-xs text-slate-400">CHECKING...</span>
            </div>

            <!-- 3. Notification Permission -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                <span class="text-slate-500 font-semibold text-[11px]">Notification Permission:</span>
                <span id="matrixPerm" class="font-mono font-bold mt-2 text-xs text-slate-400">CHECKING...</span>
            </div>

            <!-- 4. Push API -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                <span class="text-slate-500 font-semibold text-[11px]">Push API:</span>
                <span id="matrixPushApi" class="font-mono font-bold mt-2 text-xs text-slate-400">CHECKING...</span>
            </div>

            <!-- 5. Subscription -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                <span class="text-slate-500 font-semibold text-[11px]">Subscription:</span>
                <span id="matrixSub" class="font-mono font-bold mt-2 text-xs text-slate-400">CHECKING...</span>
            </div>

            <!-- 6. Backend Push Service -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                <span class="text-slate-500 font-semibold text-[11px]">Backend Push Service:</span>
                <span class="font-mono font-bold mt-2 text-xs <?= $backendPushServiceStatus === 'CONNECTED' ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $backendPushServiceStatus ?></span>
            </div>

            <!-- 7. VAPID -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                <span class="text-slate-500 font-semibold text-[11px]">VAPID:</span>
                <span class="font-mono font-bold mt-2 text-xs <?= $vapidStatus === 'CONFIGURED' ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $vapidStatus ?></span>
            </div>

            <!-- 8. Last Push Attempt -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                <span class="text-slate-500 font-semibold text-[11px]">Last Push Attempt:</span>
                <span class="font-mono font-bold mt-2 text-slate-800 text-[11px] truncate"><?= htmlspecialchars($lastPushAttempt) ?></span>
            </div>

            <!-- 9. Last Push Result -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                <span class="text-slate-500 font-semibold text-[11px]">Last Push Result:</span>
                <span class="font-mono font-bold mt-2 text-xs <?= $lastPushResult === 'SUCCESS' ? 'text-emerald-600' : ($lastPushResult === 'FAILED' ? 'text-rose-600' : 'text-slate-500') ?>"><?= $lastPushResult ?></span>
            </div>

            <!-- 10. Last Error -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                <span class="text-slate-500 font-semibold text-[11px]">Last Error:</span>
                <span class="font-mono font-bold mt-2 text-slate-600 text-[11px] truncate" title="<?= htmlspecialchars($lastErrorText) ?>"><?= htmlspecialchars(substr($lastErrorText, 0, 24)) ?></span>
            </div>
        </div>
    </div>

    <!-- Diagnostic Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- 1. Current Device Diagnostics -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-extrabold text-sm text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600">devices</span>
                    This Browser / Device Status
                </h3>
                <button type="button" onclick="refreshDeviceDiagnostics()" class="text-xs text-blue-600 hover:text-blue-800 font-bold flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">refresh</span> Refresh
                </button>
            </div>

            <div class="space-y-2.5 text-xs">
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-slate-600 font-semibold">Web Push API Supported:</span>
                    <span id="diagPushSupported" class="font-mono font-bold text-slate-400">Checking...</span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-slate-600 font-semibold">Browser Notification Permission:</span>
                    <span id="diagPermission" class="font-mono font-bold text-slate-400">Checking...</span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-slate-600 font-semibold">Service Worker Registration:</span>
                    <span id="diagSwStatus" class="font-mono font-bold text-slate-400">Checking...</span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-slate-600 font-semibold">Device Push Token (FCM/Mozilla):</span>
                    <span id="diagSubStatus" class="font-mono font-bold text-slate-400">Checking...</span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 space-y-1">
                    <span class="text-slate-600 font-semibold block">VAPID Public Key:</span>
                    <input type="text" readonly value="<?= htmlspecialchars($vapidPublicKey) ?>" class="w-full text-[10px] font-mono bg-white border border-slate-200 px-2 py-1 rounded text-slate-600 truncate select-all">
                </div>
            </div>

            <div class="pt-2 flex flex-wrap gap-2">
                <button type="button" id="btnSubscribeThisDevice" onclick="subscribeThisDevice()" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">notifications</span>
                    <span>Enable / Register This Device</span>
                </button>
                <button type="button" id="btnTestThisDevice" onclick="sendTestPushToThisDevice()" class="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">send_to_mobile</span>
                    <span>Send Test Push to This Device</span>
                </button>
            </div>
            <div id="thisDeviceTestResult" class="hidden p-3 rounded-xl text-xs font-mono"></div>
        </div>

        <!-- 2. Send Test Push to Specific Trainer -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-4">
            <h3 class="font-extrabold text-sm text-slate-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-orange-600">person_search</span>
                Send Live Push to Registered Trainer
            </h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                Test sending a real RFC 8292 encrypted Web Push alert to a trainer's phone even if their PWA/browser is currently closed.
            </p>

            <form id="trainerTestPushForm" onsubmit="handleSendTrainerTestPush(event)" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Select Trainer:</label>
                    <select id="targetTrainerSelect" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04]">
                        <option value="">-- Choose a Trainer --</option>
                        <?php foreach ($trainersList as $tr): ?>
                            <option value="<?= htmlspecialchars($tr['userId']) ?>" data-trainer-id="<?= htmlspecialchars($tr['id']) ?>">
                                <?= htmlspecialchars($tr['name']) ?> (<?= htmlspecialchars($tr['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Notification Title:</label>
                    <input type="text" id="customNotifTitle" value="🎉 Selected: Technical Campus Training" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Notification Message:</label>
                    <textarea id="customNotifBody" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:bg-white">Congratulations! You have been selected for the campus training batch. Honorarium and travel logistics confirmed.</textarea>
                </div>

                <div class="pt-1">
                    <button type="submit" id="btnSendTrainerPush" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-2.5 rounded-xl transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">send</span>
                        <span>Dispatch Encrypted Web Push to Devices</span>
                    </button>
                </div>
            </form>

            <div id="trainerTestResult" class="hidden p-3 rounded-xl text-xs font-mono"></div>
        </div>
    </div>

    <!-- 3. Push Delivery Logs Table -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-extrabold text-sm text-slate-900">Push Delivery Audit Logs</h3>
                <p class="text-xs text-slate-400 mt-0.5">Real-time HTTP delivery responses from Google FCM, Apple, and Mozilla push gateways</p>
            </div>
            <button type="button" onclick="window.location.reload()" class="text-xs text-slate-500 hover:text-slate-900 font-bold flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">refresh</span> Refresh Logs
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="p-3 pl-5">Status</th>
                        <th class="p-3">HTTP Code</th>
                        <th class="p-3">Title</th>
                        <th class="p-3">Endpoint</th>
                        <th class="p-3 pr-5 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                    <?php if (empty($recentLogs)): ?>
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-400 text-xs">
                                No push delivery logs recorded yet. Send a test push above to verify.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): 
                            $isOk = !empty($log['success']);
                            $code = $log['statusCode'] ?? 0;
                            $codeClass = ($code >= 200 && $code < 300) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200';
                            $timeStr = ($log['sentAt'] instanceof MongoDB\BSON\UTCDateTime) 
                                ? $log['sentAt']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M, h:i:s A') 
                                : 'Just now';
                        ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-3 pl-5">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $codeClass ?>">
                                        <?= $isOk ? 'DELIVERED' : 'FAILED' ?>
                                    </span>
                                </td>
                                <td class="p-3 font-mono font-bold text-slate-800">
                                    HTTP <?= $code ?>
                                </td>
                                <td class="p-3 font-bold text-slate-900 truncate max-w-xs">
                                    <?= htmlspecialchars($log['title'] ?? 'N/A') ?>
                                </td>
                                <td class="p-3 font-mono text-[11px] text-slate-500 truncate max-w-xs">
                                    <?= htmlspecialchars($log['endpoint'] ?? '') ?>
                                </td>
                                <td class="p-3 pr-5 text-right font-mono text-[11px] text-slate-400 whitespace-nowrap">
                                    <?= $timeStr ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let currentSub = null;

async function refreshDeviceDiagnostics() {
    const elSupported = document.getElementById('diagPushSupported');
    const elPerm = document.getElementById('diagPermission');
    const elSw = document.getElementById('diagSwStatus');
    const elSub = document.getElementById('diagSubStatus');

    const mHttps = document.getElementById('matrixHttps');
    const mSw = document.getElementById('matrixSw');
    const mPerm = document.getElementById('matrixPerm');
    const mPush = document.getElementById('matrixPushApi');
    const mSub = document.getElementById('matrixSub');

    const isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    if (mHttps) {
        mHttps.textContent = isSecure ? 'PASS' : 'FAIL';
        mHttps.className = 'font-mono font-bold mt-2 text-xs ' + (isSecure ? 'text-emerald-600' : 'text-rose-600');
    }

    const hasPush = ('PushManager' in window) && ('serviceWorker' in navigator);
    elSupported.textContent = hasPush ? 'YES (Supported)' : 'NO (Unsupported)';
    elSupported.className = hasPush ? 'font-mono font-bold text-emerald-600' : 'font-mono font-bold text-rose-600';
    if (mPush) {
        mPush.textContent = hasPush ? 'SUPPORTED' : 'NOT SUPPORTED';
        mPush.className = 'font-mono font-bold mt-2 text-xs ' + (hasPush ? 'text-emerald-600' : 'text-rose-600');
    }

    if (!('Notification' in window)) {
        elPerm.textContent = 'UNSUPPORTED';
        elPerm.className = 'font-mono font-bold text-slate-400';
        if (mPerm) {
            mPerm.textContent = 'UNSUPPORTED';
            mPerm.className = 'font-mono font-bold mt-2 text-xs text-slate-400';
        }
    } else {
        const pUpper = Notification.permission.toUpperCase();
        elPerm.textContent = pUpper;
        if (mPerm) mPerm.textContent = pUpper;
        if (Notification.permission === 'granted') {
            elPerm.className = 'font-mono font-bold text-emerald-600';
            if (mPerm) mPerm.className = 'font-mono font-bold mt-2 text-xs text-emerald-600';
        } else if (Notification.permission === 'denied') {
            elPerm.className = 'font-mono font-bold text-rose-600';
            if (mPerm) mPerm.className = 'font-mono font-bold mt-2 text-xs text-rose-600';
        } else {
            elPerm.className = 'font-mono font-bold text-amber-600';
            if (mPerm) mPerm.className = 'font-mono font-bold mt-2 text-xs text-amber-600';
        }
    }

    if ('serviceWorker' in navigator) {
        try {
            const reg = await navigator.serviceWorker.getRegistration();
            if (reg) {
                elSw.textContent = 'ACTIVE (Scope: ' + reg.scope + ')';
                elSw.className = 'font-mono font-bold text-emerald-600';
                if (mSw) {
                    mSw.textContent = 'ACTIVE';
                    mSw.className = 'font-mono font-bold mt-2 text-xs text-emerald-600';
                }

                const sub = await reg.pushManager.getSubscription();
                currentSub = sub;
                if (sub) {
                    elSub.textContent = 'SUBSCRIBED (' + sub.endpoint.substring(0, 35) + '...)';
                    elSub.className = 'font-mono font-bold text-emerald-600';
                    if (mSub) {
                        mSub.textContent = 'ACTIVE';
                        mSub.className = 'font-mono font-bold mt-2 text-xs text-emerald-600';
                    }
                } else {
                    elSub.textContent = 'NOT SUBSCRIBED';
                    elSub.className = 'font-mono font-bold text-amber-600';
                    if (mSub) {
                        mSub.textContent = 'MISSING';
                        mSub.className = 'font-mono font-bold mt-2 text-xs text-amber-600';
                    }
                }
            } else {
                elSw.textContent = 'NO REGISTRATION FOUND';
                elSw.className = 'font-mono font-bold text-amber-600';
                elSub.textContent = 'NOT SUBSCRIBED';
                if (mSw) {
                    mSw.textContent = 'INACTIVE';
                    mSw.className = 'font-mono font-bold mt-2 text-xs text-rose-600';
                }
                if (mSub) {
                    mSub.textContent = 'MISSING';
                    mSub.className = 'font-mono font-bold mt-2 text-xs text-amber-600';
                }
            }
        } catch(e) {
            elSw.textContent = 'ERROR: ' + e.message;
            if (mSw) {
                mSw.textContent = 'INACTIVE';
                mSw.className = 'font-mono font-bold mt-2 text-xs text-rose-600';
            }
        }
    }
}

function getAppBaseUrl() {
    const path = window.location.pathname;
    if (path.includes('/Mentry%20solution')) return '/Mentry%20solution';
    if (path.includes('/Mentry solution')) return '/Mentry solution';
    return '';
}

async function subscribeThisDevice() {
    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
        alert('Push notifications are not supported on this browser.');
        return;
    }

    const perm = await Notification.requestPermission();
    if (perm !== 'granted') {
        alert('Notification permission was ' + perm);
        refreshDeviceDiagnostics();
        return;
    }

    try {
        const base = getAppBaseUrl();
        const res = await fetch(base + '/actions/get-vapid-public-key.php');
        const data = await res.json();
        if (!data || !data.publicKey) throw new Error('Could not fetch VAPID key');

        let reg = await navigator.serviceWorker.ready;
        if (!reg) {
            reg = await navigator.serviceWorker.register(base + '/sw.js');
        }

        const convertedKey = urlB64ToUint8Array(data.publicKey);
        const existingSub = await reg.pushManager.getSubscription();
        let oldEndpoint = null;
        if (existingSub) {
            oldEndpoint = existingSub.endpoint;
            try { await existingSub.unsubscribe(); } catch(e) {}
        }

        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: convertedKey
        });

        const subJson = JSON.parse(JSON.stringify(sub));
        subJson.platform = navigator.platform || 'Unknown';
        subJson.device = 'Desktop/Dev';
        subJson.browser = navigator.userAgent;
        if (oldEndpoint) {
            subJson.oldEndpoint = oldEndpoint;
        }

        const saveRes = await fetch(base + '/actions/save-push-subscription.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(subJson)
        });
        const saveResult = await saveRes.json();

        alert('Subscription registered successfully for this device!');
        refreshDeviceDiagnostics();
    } catch(err) {
        alert('Subscription error: ' + err.message);
    }

}

async function sendTestPushToThisDevice() {
    const resBox = document.getElementById('thisDeviceTestResult');
    resBox.classList.remove('hidden');
    resBox.className = 'p-3 rounded-xl text-xs font-mono bg-slate-100 text-slate-700';
    resBox.textContent = 'Sending test Web Push through PushNotificationService...';

    try {
        const base = getAppBaseUrl();
        const formData = new FormData();
        formData.append('title', '🎉 Mentry Live VAPID Alert');
        formData.append('message', 'Direct RFC 8292 encrypted Web Push test delivered to your device!');

        const res = await fetch(base + '/actions/send-test-trainer-notification.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            resBox.className = 'p-3 rounded-xl text-xs font-mono bg-emerald-50 text-emerald-800 border border-emerald-200';
            resBox.innerHTML = '<strong>SUCCESS:</strong> ' + data.message + '<br>Target User ID: ' + (data.targetUserId || 'current');
        } else {
            resBox.className = 'p-3 rounded-xl text-xs font-mono bg-rose-50 text-rose-800 border border-rose-200';
            resBox.innerHTML = '<strong>FAILED:</strong> ' + (data.error || 'Unknown error');
        }
    } catch(e) {
        resBox.className = 'p-3 rounded-xl text-xs font-mono bg-rose-50 text-rose-800 border border-rose-200';
        resBox.textContent = 'Error: ' + e.message;
    }
}

async function handleSendTrainerTestPush(e) {
    e.preventDefault();
    const userId = document.getElementById('targetTrainerSelect').value;
    const title = document.getElementById('customNotifTitle').value;
    const body = document.getElementById('customNotifBody').value;
    const resBox = document.getElementById('trainerTestResult');

    if (!userId) {
        alert('Please select a trainer');
        return;
    }

    resBox.classList.remove('hidden');
    resBox.className = 'p-3 rounded-xl text-xs font-mono bg-slate-100 text-slate-700';
    resBox.textContent = 'Encrypting and sending RFC 8291 Web Push payload...';

    try {
        const base = getAppBaseUrl();
        const formData = new FormData();
        formData.append('targetUserId', userId);
        formData.append('title', title);
        formData.append('message', body);

        const res = await fetch(base + '/actions/send-test-trainer-notification.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            const hasDelivered = data.pushDeliveredCount > 0;
            const statusBoxClass = hasDelivered 
                ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                : 'bg-amber-50 text-amber-800 border-amber-200';
            resBox.className = 'p-3 rounded-xl text-xs font-mono border ' + statusBoxClass;
            resBox.innerHTML = '<strong>' + (hasDelivered ? 'DELIVERED:' : 'NOTICE:') + '</strong> ' + data.message + 
                '<br>Trainer: ' + (data.targetName || 'Trainer') + 
                '<br>Devices Found: ' + (data.devicesFound || 0) + 
                '<br>Push Delivered: ' + (data.pushDeliveredCount || 0) + 
                (data.details ? '<br><span class="text-[11px] text-slate-600">' + data.details + '</span>' : '');
        } else {
            resBox.className = 'p-3 rounded-xl text-xs font-mono bg-rose-50 text-rose-800 border border-rose-200';
            resBox.innerHTML = '<strong>ERROR:</strong> ' + (data.error || 'Delivery failed');
        }
    } catch(err) {
        resBox.className = 'p-3 rounded-xl text-xs font-mono bg-rose-50 text-rose-800 border border-rose-200';
        resBox.textContent = 'Request failed: ' + err.message;
    }
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

document.addEventListener('DOMContentLoaded', refreshDeviceDiagnostics);
</script>

</main>
</div>
</body>
</html>
