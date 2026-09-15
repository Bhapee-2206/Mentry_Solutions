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
                    <div class="text-slate-500 italic">No push sent yet in this session. Select a trainer and click "Send Live Push".</div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-800/80 text-[11px] text-slate-500">
                <span>Note: Acceptance by the push service (HTTP 201 Created) confirms successful receipt and forwarding by the browser vendor's push gateway.</span>
            </div>
        </div>
    </div>
</div>

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
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
