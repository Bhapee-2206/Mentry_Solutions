<?php
// push-diagnostic.php - Dedicated Real-Device Web Push Diagnostic Console
// Provides instant on-device verification of ServiceWorker registrations,
// PushSubscription ownership, freshness comparison, VAPID key matching,
// clean subscription rotation, and real-time receipt checking.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/push/PushConfig.php';

$serverVapidRawBytesHash = 'N/A';
$serverVapidStringHash = 'N/A';
try {
    $serverPubKey = PushConfig::getPublicKey();
    $rawBytes = base64_decode(strtr($serverPubKey, '-_', '+/'));
    $serverVapidRawBytesHash = hash('sha256', $rawBytes);
    $serverVapidStringHash = hash('sha256', $serverPubKey);
} catch (\Throwable $e) {}

$trainerCol = getCollection("Trainer");
$userCol = getCollection("User");
$trainersList = [];
if ($trainerCol) {
    $cursor = $trainerCol->find([], ['sort' => ['name' => 1], 'limit' => 50]);
    foreach ($cursor as $t) {
        $uDoc = ($userCol && !empty($t['userId'])) ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$t['userId'])]) : null;
        $name = $uDoc['name'] ?? ($t['name'] ?? 'Trainer');
        $trainersList[] = [
            'id' => (string)$t['_id'],
            'userId' => (string)($t['userId'] ?? ''),
            'name' => $name,
            'code' => $t['trainerCode'] ?? ($t['mentryId'] ?? 'N/A')
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentry Push Diagnostic Console</title>
    <link rel="icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 sm:p-6 font-sans antialiased">
    <div class="max-w-4xl mx-auto space-y-6">

        <!-- Header -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#FE5E04] text-3xl">troubleshoot</span>
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white">Push Diagnostics Console</h1>
                </div>
                <p class="text-xs text-slate-400 mt-1">Real-device inspection: subscription freshness, VAPID key validation, and background wake-up verification.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="runFullDiagnostic()" class="px-4 py-2 rounded-xl bg-[#FE5E04] hover:bg-[#e04e00] text-white text-xs font-bold transition-all flex items-center gap-1.5 shadow-lg cursor-pointer">
                    <span class="material-symbols-outlined text-sm">refresh</span> Refresh Status
                </button>
            </div>
        </div>

        <!-- Target Trainer Linker -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl text-xs space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <span class="font-bold text-slate-300 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[#FE5E04] text-sm">person</span>
                    Device Profile Linker
                </span>
                <span class="text-slate-500 text-[11px]">Select your trainer account to link this device's subscription</span>
            </div>
            <select id="selectTargetTrainer" onchange="runFullDiagnostic()" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-[#FE5E04]">
                <option value="">-- Choose registered trainer (e.g. Bharath Bharath) --</option>
                <?php foreach ($trainersList as $tr): ?>
                    <option value="<?= htmlspecialchars($tr['userId']) ?>" data-trainer-id="<?= htmlspecialchars($tr['id']) ?>">
                        <?= htmlspecialchars($tr['name']) ?> (<?= htmlspecialchars($tr['code']) ?>) [<?= htmlspecialchars($tr['userId']) ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 1. INSPECT THE ACTUAL ANDROID SUBSCRIPTION -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">badge</span>
                    1. ACTUAL ANDROID SUBSCRIPTION & SERVICE WORKER
                </h2>
                <span id="singleSwBadge" class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-slate-800 text-slate-400 border-slate-700">Checking...</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-xs font-mono">
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">registration.scope</div>
                    <div id="valScope" class="font-bold mt-1 text-slate-300 break-all">Inspecting...</div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">registration.active.scriptURL</div>
                    <div id="valScriptURL" class="font-bold mt-1 text-slate-300 break-all">Inspecting...</div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">registration.active.state</div>
                    <div id="valActiveState" class="font-bold mt-1 text-slate-300">Inspecting...</div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">PushSubscription exists</div>
                    <div id="valSubExists" class="font-bold mt-1 text-slate-300">Inspecting...</div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">options.applicationServerKey exists</div>
                    <div id="valAppKeyExists" class="font-bold mt-1 text-slate-300">Inspecting...</div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">endpoint hostname</div>
                    <div id="valEndpointHost" class="font-bold mt-1 text-slate-300 break-all">Inspecting...</div>
                </div>
            </div>

            <!-- Enumeration of navigator.serviceWorker.getRegistrations() -->
            <div class="space-y-2 pt-1">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                    <span>navigator.serviceWorker.getRegistrations() [<span id="regCount">0</span> found]</span>
                </div>
                <div class="overflow-x-auto border border-slate-800 rounded-2xl bg-slate-950/80">
                    <table class="w-full text-left text-xs font-mono">
                        <thead>
                            <tr class="text-slate-500 border-b border-slate-800 text-[11px]">
                                <th class="py-2 px-3">Scope</th>
                                <th class="py-2 px-3">Active ScriptURL</th>
                                <th class="py-2 px-3">Active State</th>
                                <th class="py-2 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody id="regsTableBody" class="divide-y divide-slate-800/60 text-slate-300">
                            <tr><td colspan="4" class="p-3 text-slate-500 italic">Inspecting registrations...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. VERIFY SUBSCRIPTION IS FRESH (CLIENT VS SERVER HASH) -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">fingerprint</span>
                2. SUBSCRIPTION FRESHNESS VERIFICATION (HASH COMPARISON)
            </h2>

            <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3 font-mono text-xs">
                <div>
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">CLIENT_SUBSCRIPTION_HASH (SHA-256 of endpoint):</div>
                    <div id="valClientSubHash" class="font-bold text-amber-400 break-all mt-0.5">Calculating...</div>
                </div>
                <div>
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">SERVER_SUBSCRIPTION_HASH (Active in Database):</div>
                    <div id="valServerSubHash" class="font-bold text-slate-300 break-all mt-0.5">Querying server...</div>
                </div>
                <div class="pt-2 border-t border-slate-800 flex items-center justify-between">
                    <span class="font-sans font-bold text-slate-400">SUBSCRIPTION MATCH:</span>
                    <span id="valSubMatch" class="font-bold px-3 py-1 rounded-xl text-xs bg-slate-800 text-slate-400">Comparing...</span>
                </div>
            </div>

            <!-- 3. FORCE A CLEAN SUBSCRIPTION ROTATION BUTTON -->
            <div class="pt-1 flex flex-col sm:flex-row items-center gap-3">
                <button type="button" onclick="executeCleanSubscriptionRotation()" id="btnResetSub" class="w-full sm:w-auto px-5 py-3 rounded-2xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-xl transition-all flex items-center justify-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-base">restart_alt</span>
                    <span>RESET PUSH SUBSCRIPTION</span>
                </button>
                <div class="text-[11px] text-slate-400 font-sans">
                    Unsubscribes old worker, deactivates old DB entry, creates a brand-new subscription on <code class="text-slate-200 font-mono">/sw.js</code>, and confirms exactly 1 active subscription.
                </div>
            </div>
            <div id="resetSubResult" class="hidden text-xs font-mono p-3 bg-slate-950 border border-slate-800 rounded-xl"></div>
        </div>

        <!-- 4. CHECK VAPID PUBLIC KEY MATCH -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">key</span>
                4. VAPID PUBLIC KEY MATCH
            </h2>

            <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3 font-mono text-xs">
                <div>
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">CLIENT_VAPID_PUBLIC_KEY_HASH (SHA-256 of applicationServerKey EC bytes):</div>
                    <div id="valClientVapidHash" class="font-bold text-slate-300 break-all mt-0.5">Calculating...</div>
                </div>
                <div>
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">SERVER_CONFIGURED_VAPID_PUBLIC_KEY_HASH:</div>
                    <div id="valServerVapidHash" class="font-bold text-slate-300 break-all mt-0.5"><?= htmlspecialchars($serverVapidRawBytesHash) ?></div>
                </div>
                <div class="pt-2 border-t border-slate-800 flex items-center justify-between">
                    <span class="font-sans font-bold text-slate-400">VAPID MATCH:</span>
                    <span id="valVapidMatch" class="font-bold px-3 py-1 rounded-xl text-xs bg-slate-800 text-slate-400">Comparing...</span>
                </div>
            </div>
        </div>

        <!-- 6. TEST WITH BRAND NEW SUBSCRIPTION -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">science</span>
                6. THREE BACKGROUND WAKE-UP TESTS
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <!-- Test A -->
                <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3 flex flex-col justify-between">
                    <div>
                        <div class="font-bold text-slate-200 text-xs flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            TEST A: MENTRY OPEN
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Send push while this tab is actively in the foreground. Should display immediately.</p>
                    </div>
                    <button type="button" onclick="runSpecificTest('A')" id="btnTestA" class="w-full px-3 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition-all cursor-pointer">
                        Run Test A
                    </button>
                </div>

                <!-- Test B -->
                <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3 flex flex-col justify-between">
                    <div>
                        <div class="font-bold text-amber-300 text-xs flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                            TEST B: ANDROID HOME
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Press Android Home immediately. Do NOT reopen. Wait 60s.</p>
                    </div>
                    <button type="button" onclick="runSpecificTest('B')" id="btnTestB" class="w-full px-3 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition-all cursor-pointer">
                        Run Test B
                    </button>
                </div>

                <!-- Test C -->
                <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3 flex flex-col justify-between">
                    <div>
                        <div class="font-bold text-purple-300 text-xs flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-purple-400"></span>
                            TEST C: LOCK SCREEN
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Press Power button immediately to lock phone. Wait 60s.</p>
                    </div>
                    <button type="button" onclick="runSpecificTest('C')" id="btnTestC" class="w-full px-3 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition-all cursor-pointer">
                        Run Test C
                    </button>
                </div>
            </div>

            <!-- Live Test Output -->
            <div id="testOutputCard" class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-2 text-xs font-mono">
                <div class="text-slate-500 italic">No test executed yet. Click one of the test buttons above.</div>
            </div>

            <!-- 5. CHECK PUSH SERVICE DETAILS -->
            <div id="pushServiceDetailsCard" class="hidden p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-2 text-xs font-mono">
                <div class="text-[10px] font-sans font-bold uppercase tracking-wider text-slate-400">5. EXACT PUSH SERVICE DETAILS (SERVER → GATEWAY)</div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                    <div class="p-2 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">Endpoint Host</div>
                        <div id="detEndpointHost" class="font-bold text-slate-300 mt-0.5">fcm.googleapis.com</div>
                    </div>
                    <div class="p-2 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">VAPID Audience</div>
                        <div id="detAudience" class="font-bold text-slate-300 mt-0.5">https://fcm.googleapis.com</div>
                    </div>
                    <div class="p-2 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">TTL / Urgency</div>
                        <div id="detTtlUrgency" class="font-bold text-slate-300 mt-0.5">86400s / high</div>
                    </div>
                    <div class="p-2 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">HTTP Response</div>
                        <div id="detHttpStatus" class="font-bold text-emerald-400 mt-0.5">201 Created</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. ANDROID SYSTEM SETTINGS CHECKLIST -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-3 text-xs">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-[#FE5E04]">settings_suggest</span>
                7. ANDROID/CHROME PHYSICAL SETTINGS CHECKLIST
            </h2>
            <ul class="space-y-1.5 text-slate-400 text-[11px]">
                <li class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <strong>Chrome Notifications:</strong> Settings &gt; Apps &gt; Chrome &gt; Notifications &gt; Enabled
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <strong>Site Permission:</strong> Chrome &gt; Settings &gt; Site settings &gt; Notifications &gt; mentry-solutions.vercel.app = Allowed
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <strong>Chrome Battery:</strong> Settings &gt; Apps &gt; Chrome &gt; App battery usage &gt; Set to <em>Optimized</em> or <em>Unrestricted</em> (NEVER "Restricted")
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <strong>Background Data:</strong> Settings &gt; Apps &gt; Chrome &gt; Mobile data &amp; Wi-Fi &gt; Background data = Enabled
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <strong>Data Saver &amp; Battery Saver:</strong> Disabled during diagnostic tests.
                </li>
            </ul>
        </div>

    </div>

    <script src="/assets/js/push-notifications.js?v=<?= time() ?>"></script>
    <script>
    const serverVapidRawBytesHash = "<?= htmlspecialchars($serverVapidRawBytesHash) ?>";
    let clientSubHash = '';
    let clientVapidHash = '';
    let currentRawSub = null;

    async function sha256Buffer(buffer) {
        const hashBuf = await crypto.subtle.digest('SHA-256', buffer);
        return Array.from(new Uint8Array(hashBuf)).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    async function sha256Text(text) {
        const enc = new TextEncoder();
        return await sha256Buffer(enc.encode(text));
    }

    async function runFullDiagnostic() {
        if (!window.MentryPush || typeof window.MentryPush.getDiagnostics !== 'function') {
            return;
        }

        try {
            const diag = await window.MentryPush.getDiagnostics();

            document.getElementById('valScope').textContent = diag.scope || 'None';
            document.getElementById('valScriptURL').textContent = diag.activeScriptURL || 'None';
            document.getElementById('valActiveState').textContent = diag.activeState || 'None';
            document.getElementById('valActiveState').className = (diag.activeState === 'activated') ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-amber-400';

            document.getElementById('valSubExists').textContent = diag.subscriptionExists ? '✓ Yes' : '○ No';
            document.getElementById('valSubExists').className = diag.subscriptionExists ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-rose-400';

            document.getElementById('valAppKeyExists').textContent = diag.applicationServerKeyExists ? '✓ Yes' : '○ No';
            document.getElementById('valAppKeyExists').className = diag.applicationServerKeyExists ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-amber-400';

            document.getElementById('valEndpointHost').textContent = diag.endpointHostname || 'None';

            // Registrations table
            const regs = diag.allRegistrations || [];
            document.getElementById('regCount').textContent = regs.length;

            const tbody = document.getElementById('regsTableBody');
            tbody.innerHTML = '';

            const singleBadge = document.getElementById('singleSwBadge');
            const isSingleAuthoritative = (regs.length === 1) && regs[0].isAuthoritative;

            if (isSingleAuthoritative) {
                singleBadge.textContent = '✓ Exactly 1 Mentry SW';
                singleBadge.className = 'text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
            } else {
                singleBadge.textContent = (regs.length > 1) ? `⚠ ${regs.length} Registrations Detected` : '⚠ No SW Active';
                singleBadge.className = 'text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-rose-500/20 text-rose-300 border-rose-500/30';
            }

            regs.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="py-2 px-3 break-all ${r.isAuthoritative ? 'text-emerald-400 font-bold' : 'text-amber-400'}">${r.scope}</td>
                    <td class="py-2 px-3 break-all text-slate-300">${r.activeScript || 'N/A'}</td>
                    <td class="py-2 px-3 text-slate-400">${r.activeState || 'unknown'}</td>
                    <td class="py-2 px-3">${r.isAuthoritative ? '<span class="text-emerald-400 font-bold">Authoritative (/)</span>' : '<span class="text-rose-400 font-bold">Obsolete</span>'}</td>
                `;
                tbody.appendChild(tr);
            });

            // Inspect actual subscription from SW registration
            if ('serviceWorker' in navigator) {
                const reg = await navigator.serviceWorker.getRegistration('/');
                if (reg) {
                    const sub = await reg.pushManager.getSubscription();
                    currentRawSub = sub;

                    if (sub) {
                        clientSubHash = await sha256Text(sub.endpoint);
                        document.getElementById('valClientSubHash').textContent = clientSubHash;

                        // Check VAPID key
                        if (sub.options && sub.options.applicationServerKey) {
                            clientVapidHash = await sha256Buffer(sub.options.applicationServerKey);
                            document.getElementById('valClientVapidHash').textContent = clientVapidHash;

                            const vMatch = (clientVapidHash === serverVapidRawBytesHash);
                            document.getElementById('valVapidMatch').textContent = vMatch ? 'MATCH = YES' : 'MATCH = NO';
                            document.getElementById('valVapidMatch').className = vMatch ? 'font-bold px-3 py-1 rounded-xl text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'font-bold px-3 py-1 rounded-xl text-xs bg-rose-500/20 text-rose-300 border border-rose-500/30';
                        }
                    } else {
                        document.getElementById('valClientSubHash').textContent = 'No subscription exists on client.';
                    }
                }
            }

            // Check server subscription hash
            await checkServerSubscriptionFreshness();

        } catch (e) {
            console.error('runFullDiagnostic error:', e);
        }
    }

    async function checkServerSubscriptionFreshness() {
        const targetUserId = document.getElementById('selectTargetTrainer').value;
        const url = '/actions/push/check-subscription.php?clientHash=' + encodeURIComponent(clientSubHash) + (targetUserId ? ('&userId=' + encodeURIComponent(targetUserId)) : '');

        try {
            const res = await fetch(url, { cache: 'no-store' });
            const data = await res.json();

            if (data.success) {
                const sHash = data.serverSubscriptionHash || 'None registered in database';
                document.getElementById('valServerSubHash').textContent = sHash;

                const isMatch = !!data.match;
                const matchEl = document.getElementById('valSubMatch');
                matchEl.textContent = isMatch ? 'MATCH = YES' : 'MATCH = NO';
                matchEl.className = isMatch ? 'font-bold px-3 py-1 rounded-xl text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'font-bold px-3 py-1 rounded-xl text-xs bg-rose-500/20 text-rose-300 border border-rose-500/30';
            }
        } catch (e) {
            document.getElementById('valServerSubHash').textContent = 'Error checking server: ' + e.message;
        }
    }

    async function executeCleanSubscriptionRotation() {
        const btn = document.getElementById('btnResetSub');
        const resDiv = document.getElementById('resetSubResult');
        const targetUserId = document.getElementById('selectTargetTrainer').value;

        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">refresh</span> Rotating...';
        resDiv.classList.remove('hidden');
        resDiv.innerHTML = '<span class="text-amber-300">Executing clean 9-step rotation...</span>';

        try {
            if (!window.MentryPush || typeof window.MentryPush.resetSubscription !== 'function') {
                throw new Error('resetSubscription function not loaded.');
            }

            const rotRes = await window.MentryPush.resetSubscription(targetUserId);
            await runFullDiagnostic();

            resDiv.innerHTML = `
                <div class="text-emerald-400 font-bold">✓ RESET PUSH SUBSCRIPTION COMPLETE!</div>
                <div class="text-slate-300">New Client Hash: <span class="text-white">${clientSubHash}</span></div>
                <div class="text-slate-300">Server Subscribed: <span class="text-white">${rotRes.serverResult && rotRes.serverResult.subscribed ? 'YES' : 'NO'}</span></div>
                <div class="text-slate-300">Linked User ID: <span class="text-white">${rotRes.serverResult.userId || targetUserId || 'Session User'}</span></div>
            `;
        } catch (e) {
            resDiv.innerHTML = `<span class="text-rose-400 font-bold">Reset failed: ${e.message}</span>`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    async function runSpecificTest(testType) {
        const targetUserId = document.getElementById('selectTargetTrainer').value;
        const outCard = document.getElementById('testOutputCard');
        const detCard = document.getElementById('pushServiceDetailsCard');

        const testId = `bg_test_${testType.toLowerCase()}_` + Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 6);

        outCard.innerHTML = `
            <div class="text-amber-400 font-bold">DISPATCHING TEST ${testType}...</div>
            <div class="text-slate-300">TEST_ID: <span class="text-white font-bold">${testId}</span></div>
        `;

        try {
            const fd = new FormData();
            fd.append('testId', testId);
            fd.append('isBackgroundTest', '1');
            if (targetUserId) {
                fd.append('userId', targetUserId);
            }

            const res = await fetch('/actions/push/test.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            // Populate push service details
            if (data.pushDetails) {
                detCard.classList.remove('hidden');
                document.getElementById('detEndpointHost').textContent = data.pushDetails.endpointHost || 'fcm.googleapis.com';
                document.getElementById('detAudience').textContent = data.pushDetails.audience || 'https://fcm.googleapis.com';
                document.getElementById('detTtlUrgency').textContent = `${data.pushDetails.ttl || 86400}s / ${data.pushDetails.urgency || 'high'}`;
                document.getElementById('detHttpStatus').textContent = `${data.statusCode || 201} (${data.reason || 'Accepted'})`;
            }

            if (data.pushServiceAccepted || data.success) {
                if (testType === 'A') {
                    outCard.innerHTML = `
                        <div class="text-emerald-400 font-bold text-sm">✓ TEST A SENT (HTTP ${data.statusCode || 201})</div>
                        <div class="text-slate-300">TEST_ID: <span class="text-white font-bold">${testId}</span></div>
                        <div class="text-slate-300">Target Devices: <span class="text-white">${data.subscriptionCount || 1}</span></div>
                        <div class="text-amber-300 mt-1 font-sans">Tab is in foreground. Did the native notification appear?</div>
                        <div class="pt-1">
                            <button type="button" onclick="pollReceipt('${testId}', 1)" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-200 border border-slate-700 text-xs font-bold cursor-pointer">
                                Check sw.js Receipt
                            </button>
                        </div>
                    `;
                } else if (testType === 'B') {
                    outCard.innerHTML = `
                        <div class="text-emerald-400 font-bold text-sm">✓ TEST B DISPATCHED (HTTP ${data.statusCode || 201})</div>
                        <div class="text-slate-300">TEST_ID: <span class="text-white font-bold">${testId}</span></div>
                        <div class="p-3 bg-amber-500/20 border border-amber-500/30 rounded-xl text-amber-200 font-sans mt-2 space-y-1">
                            <div class="font-bold text-sm">👉 PRESS ANDROID HOME BUTTON NOW!</div>
                            <div>Do NOT reopen Mentry. Do NOT swipe Chrome away.</div>
                            <div>Wait 60 seconds. Observe if status bar notification appears.</div>
                        </div>
                        <div class="pt-2">
                            <button type="button" onclick="pollReceipt('${testId}', 60)" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-200 border border-slate-700 text-xs font-bold cursor-pointer">
                                Check Receipt for ${testId}
                            </button>
                        </div>
                    `;
                } else if (testType === 'C') {
                    outCard.innerHTML = `
                        <div class="text-emerald-400 font-bold text-sm">✓ TEST C DISPATCHED (HTTP ${data.statusCode || 201})</div>
                        <div class="text-slate-300">TEST_ID: <span class="text-white font-bold">${testId}</span></div>
                        <div class="p-3 bg-purple-500/20 border border-purple-500/30 rounded-xl text-purple-200 font-sans mt-2 space-y-1">
                            <div class="font-bold text-sm">👉 PRESS POWER BUTTON TO LOCK PHONE NOW!</div>
                            <div>Leave phone locked for 60 seconds. Observe if lock screen lights up or notification rings.</div>
                        </div>
                        <div class="pt-2">
                            <button type="button" onclick="pollReceipt('${testId}', 60)" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-200 border border-slate-700 text-xs font-bold cursor-pointer">
                                Check Receipt for ${testId}
                            </button>
                        </div>
                    `;
                }
            } else {
                outCard.innerHTML = `<div class="text-rose-400 font-bold">Push Gateway dispatch failed: ${data.reason || data.error}</div>`;
            }

        } catch (e) {
            outCard.innerHTML = `<div class="text-rose-400 font-bold">Dispatch exception: ${e.message}</div>`;
        }
    }

    async function pollReceipt(testId, waitSeconds = 0) {
        const outCard = document.getElementById('testOutputCard');
        outCard.innerHTML = `<div class="text-amber-300 font-bold animate-pulse">Checking receipt for ${testId}...</div>`;

        try {
            const receiptRes = await window.MentryPush.getPushReceipt(testId);
            const r = receiptRes.serverReceipt || receiptRes.cacheReceipt;

            if (r && (r.testId === testId || !testId)) {
                outCard.innerHTML = `
                    <div class="p-3 bg-emerald-500/20 border border-emerald-500/30 rounded-xl space-y-1">
                        <div class="text-emerald-400 font-bold text-sm">✓ QUESTION A: YES — sw.js received push event!</div>
                        <div class="text-slate-300 text-xs">Received At: <span class="text-white">${r.receivedAt}</span></div>
                        <div class="text-slate-300 text-xs">showNotification(): <span class="${r.showNotificationSuccess ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'}">${r.showNotificationSuccess ? 'SUCCESS' : 'THREW: ' + r.showNotificationError}</span></div>
                        <div class="text-slate-400 text-[11px]">SW Scope: ${r.swScope}</div>
                        <div class="text-[11px] text-amber-200 pt-1 font-sans">
                            Result: Server → FCM: PASS, FCM → Chrome: PASS, Chrome → SW: PASS.
                            If no popup appeared, the issue is strictly Android Notification Channel or Battery restriction.
                        </div>
                    </div>
                `;
            } else {
                outCard.innerHTML = `
                    <div class="p-3 bg-rose-500/20 border border-rose-500/30 rounded-xl space-y-1">
                        <div class="text-rose-400 font-bold text-sm">QUESTION A: NO receipt recorded for ${testId}</div>
                        <div class="text-slate-300 text-xs">sw.js did NOT execute while in background.</div>
                        <div class="text-slate-400 text-[11px] pt-1 font-sans">
                            Failure layer is BEFORE sw.js (FCM → Chrome or Chrome background wake-up).
                            Ensure:
                            1. Subscription Freshness Match is YES above.
                            2. VAPID Match is YES above.
                            3. Android Chrome battery is NOT set to "Restricted".
                            4. Click "RESET PUSH SUBSCRIPTION" above and re-test.
                        </div>
                    </div>
                `;
            }
        } catch (e) {
            outCard.innerHTML = `<div class="text-rose-400">Error polling receipt: ${e.message}</div>`;
        }
    }

    window.addEventListener('DOMContentLoaded', runFullDiagnostic);
    </script>
</body>
</html>
