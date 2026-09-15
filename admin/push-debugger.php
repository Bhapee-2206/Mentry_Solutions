<?php
// admin/push-debugger.php - Clean Web Push Diagnostics & Live Testing
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/push/PushConfig.php';
require_once __DIR__ . '/../includes/push/PushSubscriptionRepository.php';

requireAdminOrStaff();

$pageTitle = 'Web Push Diagnostics & Live Testing';
$user = getCurrentUser();

$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$subCol = getCollection("PushSubscription");
$transportLogCol = getCollection("PushTransportLog");

$trainersList = [];
if ($trainerCol) {
    $trainersCursor = $trainerCol->find([], ['sort' => ['name' => 1], 'limit' => 50]);
    foreach ($trainersCursor as $t) {
        $uDoc = ($userCol && !empty($t['userId'])) ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$t['userId'])]) : null;
        $tName = $uDoc['name'] ?? ($t['name'] ?? 'Trainer');
        $tUserId = (string)($t['userId'] ?? '');
        $tId = (string)$t['_id'];

        $deviceCount = 0;
        if ($subCol) {
            $deviceCount = $subCol->countDocuments([
                'isActive' => true,
                'isDead' => ['$ne' => true],
                '$or' => [
                    ['userId' => $tUserId],
                    ['trainerId' => $tId]
                ]
            ]);
        }

        $trainersList[] = [
            'id' => $tId,
            'userId' => $tUserId,
            'code' => $t['trainerCode'] ?? ($t['mentryId'] ?? 'N/A'),
            'name' => $tName,
            'activeDevices' => $deviceCount
        ];
    }
}

// System checks
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) || 
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);

$vapidValid = false;
$vapidPublicKey = '';
$vapidError = '';
try {
    $vapidPublicKey = PushConfig::getPublicKey();
    $vapidValid = !empty($vapidPublicKey);
} catch (\Throwable $e) {
    $vapidError = $e->getMessage();
}

$webPushInstalled = class_exists('\Minishlink\WebPush\WebPush');
$totalActiveSubs = $subCol ? $subCol->countDocuments(['isActive' => true, 'isDead' => ['$ne' => true]]) : 0;

// Step 12: Fetch recent server-side push transport logs
$recentLogs = [];
if ($transportLogCol) {
    $cursor = $transportLogCol->find([], ['sort' => ['_id' => -1], 'limit' => 8]);
    foreach ($cursor as $doc) {
        $recentLogs[] = [
            'timestamp' => $doc['isoTimestamp'] ?? '',
            'userId' => (string)($doc['userId'] ?? ''),
            'subscriptionHash' => (string)($doc['subscriptionHash'] ?? ''),
            'httpStatus' => (int)($doc['httpStatus'] ?? 0),
            'endpointHostname' => (string)($doc['endpointHostname'] ?? ''),
            'reason' => (string)($doc['reason'] ?? ''),
            'accepted' => !empty($doc['accepted'])
        ];
    }
}

// Step 11: Active registered subscriptions (Database view - NEVER expose secret keys)
$activeSubscriptions = [];
if ($subCol) {
    $subCursor = $subCol->find(['isActive' => true, 'isDead' => ['$ne' => true]], ['sort' => ['updatedAt' => -1], 'limit' => 10]);
    foreach ($subCursor as $s) {
        $ep = $s['endpoint'] ?? '';
        $activeSubscriptions[] = [
            'userId' => (string)($s['userId'] ?? ($s['trainerId'] ?? 'N/A')),
            'endpointHostname' => parse_url($ep, PHP_URL_HOST) ?: 'unknown',
            'subscriptionHash' => substr(hash('sha256', $ep), 0, 14),
            'device' => $s['meta']['device'] ?? ($s['device'] ?? 'Unknown'),
            'browser' => $s['meta']['browser'] ?? ($s['browser'] ?? 'Unknown'),
            'platform' => $s['meta']['platform'] ?? ($s['platform'] ?? 'Unknown'),
            'updatedAt' => isset($s['updatedAt']) ? (is_object($s['updatedAt']) ? $s['updatedAt']->toDateTime()->format('M d, H:i') : (string)$s['updatedAt']) : 'N/A'
        ];
    }
}

include __DIR__ . '/includes/sidebar.php';
?>

<div class="p-6 max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FE5E04] text-2xl">send_to_mobile</span>
                <h1 class="text-xl font-bold text-white tracking-tight">Web Push Diagnostics</h1>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-[#FE5E04]/20 text-[#FE5E04] border border-[#FE5E04]/30">RFC 8291 / 8292</span>
            </div>
            <p class="text-xs text-slate-400 mt-1">Direct testing for browser push gateway delivery without artificial retry heuristics.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-xs font-mono text-slate-300">
                <span class="w-2 h-2 rounded-full <?= $totalActiveSubs > 0 ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' ?>"></span>
                <?= $totalActiveSubs ?> Active Subscriptions
            </span>
        </div>
    </div>

    <!-- System Status Card -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
            <span class="material-symbols-outlined text-sm text-[#FE5E04]">dns</span>
            SYSTEM CONFIGURATION
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div class="bg-slate-950/60 p-4 rounded-2xl border border-slate-800">
                <div class="text-slate-400">HTTPS Transport</div>
                <div class="font-bold mt-1 <?= $isHttps ? 'text-emerald-400' : 'text-rose-400' ?>">
                    <?= $isHttps ? '✓ Secure HTTPS' : '✗ Insecure (HTTP)' ?>
                </div>
            </div>
            <div class="bg-slate-950/60 p-4 rounded-2xl border border-slate-800">
                <div class="text-slate-400">Service Worker</div>
                <div class="font-bold mt-1 text-emerald-400">✓ /sw.js Available</div>
            </div>
            <div class="bg-slate-950/60 p-4 rounded-2xl border border-slate-800">
                <div class="text-slate-400">VAPID Keys</div>
                <div class="font-bold mt-1 <?= $vapidValid ? 'text-emerald-400' : 'text-rose-400' ?>">
                    <?= $vapidValid ? '✓ Validated (Stable)' : '✗ ' . htmlspecialchars($vapidError) ?>
                </div>
            </div>
            <div class="bg-slate-950/60 p-4 rounded-2xl border border-slate-800">
                <div class="text-slate-400">Minishlink WebPush</div>
                <div class="font-bold mt-1 <?= $webPushInstalled ? 'text-emerald-400' : 'text-rose-400' ?>">
                    <?= $webPushInstalled ? '✓ Library Loaded' : '✗ Missing' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 11: Real-Time Client Service Worker & Subscription Diagnostics -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-[#FE5E04]">phonelink_setup</span>
                LOCAL CLIENT SERVICE WORKER & SUBSCRIPTION DIAGNOSTICS
            </h2>
            <div class="flex items-center gap-2">
                <button type="button" onclick="loadClientDiagnostics()" class="px-3 py-1 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs text-slate-300 border border-slate-700 transition-colors flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-[15px]">refresh</span> Refresh
                </button>
                <button type="button" onclick="triggerClientMigration()" id="btnMigrate" class="px-3 py-1 rounded-xl bg-[#FE5E04]/20 hover:bg-[#FE5E04]/30 text-[#FE5E04] border border-[#FE5E04]/40 text-xs font-bold transition-colors flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-[15px]">sync</span> Run Migration
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 text-xs font-mono">
            <div class="bg-slate-950/70 p-3.5 rounded-2xl border border-slate-800/80">
                <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">Active SW Script URL</div>
                <div id="diagActiveScript" class="font-bold mt-1 text-slate-300 truncate">Inspecting...</div>
            </div>
            <div class="bg-slate-950/70 p-3.5 rounded-2xl border border-slate-800/80">
                <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">SW Scope</div>
                <div id="diagScope" class="font-bold mt-1 text-slate-300 truncate">Inspecting...</div>
            </div>
            <div class="bg-slate-950/70 p-3.5 rounded-2xl border border-slate-800/80">
                <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">Subscription Exists</div>
                <div id="diagSubExists" class="font-bold mt-1 text-slate-300">Inspecting...</div>
            </div>
            <div class="bg-slate-950/70 p-3.5 rounded-2xl border border-slate-800/80">
                <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">Endpoint Hostname</div>
                <div id="diagEndpointHost" class="font-bold mt-1 text-slate-300 truncate">Inspecting...</div>
            </div>
            <div class="bg-slate-950/70 p-3.5 rounded-2xl border border-slate-800/80">
                <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">Migrated / Updated At</div>
                <div id="diagMigratedAt" class="font-bold mt-1 text-slate-300 truncate">Inspecting...</div>
            </div>
        </div>
        <div id="diagNotice" class="text-[11px] text-slate-400 font-sans italic hidden"></div>
    </div>

    <!-- Testing & Dispatch Card -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Target Selector Form -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-[#FE5E04]">person_search</span>
                SELECT TRAINER FOR TEST PUSH
            </h2>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Target Trainer</label>
                    <select id="trainerSelector" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-[#FE5E04]">
                        <option value="">-- Choose a registered trainer --</option>
                        <?php foreach ($trainersList as $tr): ?>
                            <option value="<?= htmlspecialchars($tr['userId']) ?>" data-trainer-id="<?= htmlspecialchars($tr['id']) ?>" data-devices="<?= $tr['activeDevices'] ?>">
                                <?= htmlspecialchars($tr['name']) ?> (<?= htmlspecialchars($tr['code']) ?>) — <?= $tr['activeDevices'] ?> device(s)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="trainerDeviceInfo" class="hidden p-3.5 bg-slate-950/70 border border-slate-800 rounded-xl text-xs space-y-1">
                    <div class="text-slate-400">Active Devices: <span id="deviceCountSpan" class="font-bold text-white">0</span></div>
                    <div class="text-slate-500 text-[11px]">Push notifications will be sent directly to registered endpoints via Google FCM / browser push service.</div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                    <button type="button" id="btnSendTestPush" onclick="dispatchTestPush(false)" disabled class="w-full bg-[#FE5E04] hover:bg-[#e04e00] disabled:bg-slate-800 disabled:text-slate-500 text-white font-bold text-xs py-3 px-3 rounded-xl shadow-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-[17px]">send</span>
                        <span>Send Normal Test</span>
                    </button>
                    <button type="button" id="btnSendBgTestPush" onclick="dispatchTestPush(true)" disabled class="w-full bg-slate-800 hover:bg-slate-700 disabled:bg-slate-800 disabled:text-slate-500 text-amber-300 font-bold text-xs py-3 px-3 rounded-xl border border-amber-500/30 shadow-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-[17px]">phonelink_ring</span>
                        <span>BACKGROUND ONLY TEST</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Live Response Output -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4 flex flex-col justify-between">
            <div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-[#FE5E04]">terminal</span>
                    LAST SEND RESULT
                </h2>
                <div id="testResultContainer" class="mt-4 p-4 bg-slate-950 border border-slate-800 rounded-2xl min-h-[140px] flex flex-col justify-center text-xs font-mono text-slate-400 space-y-2">
                    <div class="text-slate-500 italic">No push sent yet in this session. Select a trainer and click a send button.</div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-800/80 text-[11px] text-slate-500">
                <span>Note: Acceptance by the push service (HTTP 201 Created) confirms successful receipt and forwarding by the browser vendor's push gateway.</span>
            </div>
        </div>
    </div>

    <!-- Step 12: Server-Side Dispatch Logs Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
            <span class="material-symbols-outlined text-sm text-[#FE5E04]">history_edu</span>
            RECENT SERVER-SIDE PUSH DISPATCH LOGS
        </h2>
        <?php if (empty($recentLogs)): ?>
            <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl text-xs text-slate-500 italic">
                No server dispatch logs recorded yet. Send a test push to populate.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-500 border-b border-slate-800 font-mono">
                            <th class="py-2.5 px-3">Timestamp</th>
                            <th class="py-2.5 px-3">User ID</th>
                            <th class="py-2.5 px-3">Subscription Hash</th>
                            <th class="py-2.5 px-3">HTTP Status</th>
                            <th class="py-2.5 px-3">Gateway Host</th>
                            <th class="py-2.5 px-3">Gateway Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-mono text-slate-300">
                        <?php foreach ($recentLogs as $rl): ?>
                            <tr>
                                <td class="py-2 px-3 text-slate-400"><?= htmlspecialchars(substr($rl['timestamp'], 0, 19)) ?></td>
                                <td class="py-2 px-3"><?= htmlspecialchars($rl['userId']) ?></td>
                                <td class="py-2 px-3 text-slate-400"><?= htmlspecialchars($rl['subscriptionHash']) ?></td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-0.5 rounded font-bold text-[11px] <?= $rl['accepted'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' ?>">
                                        <?= $rl['httpStatus'] ?>
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-slate-400"><?= htmlspecialchars($rl['endpointHostname']) ?></td>
                                <td class="py-2 px-3 text-slate-400"><?= htmlspecialchars($rl['reason']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Active Subscriptions in Database (Safe view: zero secrets) -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
            <span class="material-symbols-outlined text-sm text-[#FE5E04]">devices</span>
            REGISTERED SUBSCRIPTIONS IN DATABASE (HASHED SAFE VIEW)
        </h2>
        <?php if (empty($activeSubscriptions)): ?>
            <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl text-xs text-slate-500 italic">
                No active push subscriptions found in database.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs font-mono">
                    <thead>
                        <tr class="text-slate-500 border-b border-slate-800">
                            <th class="py-2.5 px-3">User ID</th>
                            <th class="py-2.5 px-3">Sub Hash (sha256)</th>
                            <th class="py-2.5 px-3">Push Gateway</th>
                            <th class="py-2.5 px-3">Device / Browser</th>
                            <th class="py-2.5 px-3">Updated At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        <?php foreach ($activeSubscriptions as $as): ?>
                            <tr>
                                <td class="py-2 px-3"><?= htmlspecialchars($as['userId']) ?></td>
                                <td class="py-2 px-3 text-slate-400"><?= htmlspecialchars($as['subscriptionHash']) ?></td>
                                <td class="py-2 px-3 text-emerald-400"><?= htmlspecialchars($as['endpointHostname']) ?></td>
                                <td class="py-2 px-3 text-slate-400"><?= htmlspecialchars($as['device'] . ' • ' . $as['browser']) ?></td>
                                <td class="py-2 px-3 text-slate-500"><?= htmlspecialchars($as['updatedAt']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/push-notifications.js?v=<?= time() ?>"></script>
<script>
const trainerSelector = document.getElementById('trainerSelector');
const btnSendTestPush = document.getElementById('btnSendTestPush');
const deviceInfo = document.getElementById('trainerDeviceInfo');
const deviceCountSpan = document.getElementById('deviceCountSpan');
const resultContainer = document.getElementById('testResultContainer');
const btnSendBgTestPush = document.getElementById('btnSendBgTestPush');

trainerSelector.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (this.value) {
        const devices = parseInt(opt.getAttribute('data-devices') || '0', 10);
        deviceCountSpan.textContent = devices;
        deviceInfo.classList.remove('hidden');
        btnSendTestPush.disabled = false;
        if (btnSendBgTestPush) btnSendBgTestPush.disabled = false;
    } else {
        deviceInfo.classList.add('hidden');
        btnSendTestPush.disabled = true;
        if (btnSendBgTestPush) btnSendBgTestPush.disabled = true;
    }
});

async function dispatchTestPush(isBackground = false) {
    const opt = trainerSelector.options[trainerSelector.selectedIndex];
    const userId = trainerSelector.value;
    const trainerId = opt.getAttribute('data-trainer-id') || '';

    if (!userId) return;

    const targetBtn = isBackground ? btnSendBgTestPush : btnSendTestPush;
    const origHtml = targetBtn ? targetBtn.innerHTML : '';
    btnSendTestPush.disabled = true;
    if (btnSendBgTestPush) btnSendBgTestPush.disabled = true;
    if (targetBtn) {
        targetBtn.innerHTML = '<span class="material-symbols-outlined text-[17px] animate-spin">refresh</span><span>Dispatching...</span>';
    }

    resultContainer.innerHTML = `<div class="text-blue-400 animate-pulse">Dispatching ${isBackground ? 'BACKGROUND ONLY TEST' : 'Web Push'} via gateway...</div>`;

    try {
        const fd = new FormData();
        fd.append('userId', userId);
        fd.append('trainerId', trainerId);
        if (isBackground) {
            fd.append('isBackgroundTest', '1');
        }

        const res = await fetch('/actions/push/test.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data.pushServiceAccepted || data.success) {
            resultContainer.innerHTML = `
                <div class="text-emerald-400 font-bold text-sm">✓ Push Service Accepted ${isBackground ? 'BACKGROUND ONLY TEST' : 'Request'}</div>
                <div class="text-slate-300">HTTP Status: <span class="text-emerald-300 font-bold">${data.statusCode || 201}</span></div>
                <div class="text-slate-300">Gateway Reason: <span class="text-slate-400">${data.reason || 'Accepted'}</span></div>
                <div class="text-slate-300">Target Devices: <span class="text-slate-400">${data.subscriptionCount || 1}</span></div>
                <div class="text-[11px] text-amber-300/80 mt-2 font-sans">${isBackground ? '✓ BACKGROUND TEST payload sent. If Mentry is closed or screen is locked, Android should wake up and show the notification.' : 'The notification has been queued for immediate native display by the trainer Service Worker.'}</div>
            `;
        } else {
            resultContainer.innerHTML = `
                <div class="text-rose-400 font-bold text-sm">✗ Push Gateway Dispatch Failed</div>
                <div class="text-slate-300">HTTP Status: <span class="text-rose-300 font-bold">${data.statusCode || 500}</span></div>
                <div class="text-slate-300">Reason: <span class="text-rose-200">${data.reason || data.error || 'Failed'}</span></div>
                <div class="text-slate-300">Registered Devices: <span class="text-slate-400">${data.subscriptionCount || 0}</span></div>
            `;
        }
    } catch (e) {
        resultContainer.innerHTML = `
            <div class="text-rose-400 font-bold text-sm">✗ Network / Script Exception</div>
            <div class="text-rose-300">${e.message || 'Request failed'}</div>
        `;
    } finally {
        btnSendTestPush.disabled = false;
        if (btnSendBgTestPush) btnSendBgTestPush.disabled = false;
        if (targetBtn) targetBtn.innerHTML = origHtml;
    }
}

// Step 11: Real-time Client Diagnostics Inspector
async function loadClientDiagnostics() {
    if (!window.MentryPush || typeof window.MentryPush.getDiagnostics !== 'function') {
        document.getElementById('diagActiveScript').textContent = 'MentryPush not ready';
        return;
    }

    try {
        const diag = await window.MentryPush.getDiagnostics();
        document.getElementById('diagActiveScript').textContent = diag.activeScriptURL ? diag.activeScriptURL.replace(window.location.origin, '') : 'None';
        document.getElementById('diagActiveScript').className = diag.activeScriptURL ? 'font-bold mt-1 text-emerald-400 truncate' : 'font-bold mt-1 text-rose-400';

        document.getElementById('diagScope').textContent = diag.scope ? diag.scope.replace(window.location.origin, '') : 'None';
        document.getElementById('diagSubExists').textContent = diag.subscriptionExists ? '✓ Yes' : '○ No';
        document.getElementById('diagSubExists').className = diag.subscriptionExists ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-amber-400';

        document.getElementById('diagEndpointHost').textContent = diag.endpointHostname || 'None';
        document.getElementById('diagMigratedAt').textContent = diag.migratedTimestamp ? diag.migratedTimestamp.replace('T', ' ').substring(0, 19) : 'Not recorded';

        const notice = document.getElementById('diagNotice');
        if (diag.totalRegistrations > 1) {
            notice.classList.remove('hidden');
            notice.textContent = `Notice: ${diag.totalRegistrations} Service Worker registrations detected. Clicking "Run Migration" will unregister non-authoritative registrations and re-subscribe cleanly.`;
        } else {
            notice.classList.add('hidden');
        }
    } catch (e) {
        console.error('Error loading client diagnostics:', e);
    }
}

async function triggerClientMigration() {
    const btn = document.getElementById('btnMigrate');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-[15px] animate-spin">refresh</span> Migrating...';

    try {
        if (window.MentryPush && typeof window.MentryPush.migrateSubscription === 'function') {
            const ok = await window.MentryPush.migrateSubscription();
            await loadClientDiagnostics();
            alert(ok ? 'Subscription migration complete! Fresh subscription registered on /sw.js with scope /.' : 'Migration did not complete. Check notification permissions.');
        }
    } catch (e) {
        alert('Migration error: ' + (e.message || String(e)));
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

window.addEventListener('DOMContentLoaded', loadClientDiagnostics);
setTimeout(loadClientDiagnostics, 1000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
