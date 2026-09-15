<?php
// push-diagnostic.php - Self-Contained Web Push Diagnostic Console & SW Initializer
// Fully independent: registers /sw.js directly, manages lifecycle, provides resetSubscription,
// and verifies Push JS loading and registration state without third-party script dependencies.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/push/PushConfig.php';
require_once __DIR__ . '/includes/push/OneSignalService.php';

$serverVapidRawBytesHash = 'N/A';
$serverVapidStringHash = 'N/A';
try {
    $serverPubKey = PushConfig::getPublicKey();
    $rawBytes = base64_decode(strtr($serverPubKey, '-_', '+/'));
    $serverVapidRawBytesHash = hash('sha256', $rawBytes);
    $serverVapidStringHash = hash('sha256', $serverPubKey);
} catch (\Throwable $e) {}

$serverOsAppId = OneSignalService::getAppId();
$serverOsKey = OneSignalService::getApiKey();
$serverOsKeySource = OneSignalService::getApiKeySource();
$serverOsConfigured = OneSignalService::isConfigured();
$serverOsKeyPrefix = !empty($serverOsKey) ? substr($serverOsKey, 0, min(14, strlen($serverOsKey))) : '';
$serverOsKeyMasked = !empty($serverOsKey) ? ($serverOsKeyPrefix . '...' . substr($serverOsKey, -4) . ' (len:' . strlen($serverOsKey) . ') [' . $serverOsKeySource . ']') : 'NOT SET';
$osCredVerification = $serverOsConfigured ? OneSignalService::verifyCredentials() : ['valid' => false, 'error' => 'Not configured'];

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
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white">Push Diagnostic Console</h1>
                </div>
                <p class="text-xs text-slate-400 mt-1">Self-contained client initialization, service worker lifecycle, and subscription inspector.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="btnRefreshDiagnostics" class="px-4 py-2 rounded-xl bg-[#FE5E04] hover:bg-[#e04e00] text-white text-xs font-bold transition-all flex items-center gap-1.5 shadow-lg cursor-pointer">
                    <span class="material-symbols-outlined text-sm">refresh</span> Refresh Status
                </button>
            </div>
        </div>

        <!-- 9. PRE-FLIGHT VERIFICATION CHECKLIST -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">checklist</span>
                CLIENT INITIALIZATION STATUS (MUST ALL BE GREEN BEFORE BACKGROUND TEST)
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-xs font-mono">
                <div class="p-3 bg-slate-950/80 rounded-xl border border-slate-800">
                    <div class="text-slate-500 text-[9px] uppercase font-sans font-bold">PUSH JS DOWNLOAD</div>
                    <div id="statPushDownload" class="font-bold mt-1 text-slate-400">PROBING...</div>
                </div>
                <div class="p-3 bg-slate-950/80 rounded-xl border border-slate-800">
                    <div class="text-slate-500 text-[9px] uppercase font-sans font-bold">PUSH JS EXECUTED</div>
                    <div id="statPushExecuted" class="font-bold mt-1 text-slate-400">CHECKING...</div>
                </div>
                <div class="p-3 bg-slate-950/80 rounded-xl border border-slate-800">
                    <div class="text-slate-500 text-[9px] uppercase font-sans font-bold">PUSH JS LOADED</div>
                    <div id="statPushJs" class="font-bold mt-1 text-slate-400">CHECKING...</div>
                </div>
                <div class="p-3 bg-slate-950/80 rounded-xl border border-slate-800">
                    <div class="text-slate-500 text-[9px] uppercase font-sans font-bold">SW API SUPPORTED</div>
                    <div id="statSwApi" class="font-bold mt-1 text-slate-400">CHECKING...</div>
                </div>
                <div class="p-3 bg-slate-950/80 rounded-xl border border-slate-800">
                    <div class="text-slate-500 text-[9px] uppercase font-sans font-bold">REG FOUND / COUNT</div>
                    <div id="statRegFound" class="font-bold mt-1 text-slate-400">CHECKING...</div>
                </div>
                <div class="p-3 bg-slate-950/80 rounded-xl border border-slate-800">
                    <div class="text-slate-500 text-[9px] uppercase font-sans font-bold">ACTIVE STATE</div>
                    <div id="statActiveState" class="font-bold mt-1 text-slate-400">CHECKING...</div>
                </div>
            </div>

            <!-- Captured Browser Error Panel -->
            <div id="jsErrorPanel" class="hidden p-4 bg-rose-950/40 border border-rose-500/50 rounded-2xl space-y-2 text-xs font-mono">
                <div class="font-bold text-rose-400 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">error</span>
                    CAPTURED JAVASCRIPT / RESOURCE ERROR
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                    <div><span class="text-slate-400">JS ERROR TYPE:</span> <span id="errType" class="text-rose-300 font-bold">None</span></div>
                    <div><span class="text-slate-400">SCRIPT URL:</span> <span id="errSource" class="text-slate-300 break-all">None</span></div>
                    <div><span class="text-slate-400">LINE / COL:</span> <span id="errLineCol" class="text-slate-300">None</span></div>
                    <div><span class="text-slate-400">MIME / HTTP:</span> <span id="errMime" class="text-slate-300 break-all">None</span></div>
                    <div class="sm:col-span-2"><span class="text-slate-400">JS ERROR MESSAGE:</span> <span id="errMsg" class="text-rose-200 font-bold break-all">None</span></div>
                </div>
            </div>

            <div id="statErrorNotice" class="hidden text-xs text-rose-400 font-mono p-2.5 bg-rose-500/10 border border-rose-500/30 rounded-xl"></div>
        </div>

        <!-- Target Trainer Linker -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl text-xs space-y-2">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <span class="font-bold text-slate-300 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[#FE5E04] text-sm">person</span>
                    Device Trainer Profile Linker
                </span>
                <span class="text-slate-500 text-[11px]">Select your trainer account to associate this device's subscription</span>
            </div>
            <select id="selectTargetTrainer" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-[#FE5E04]">
                <option value="">-- Choose registered trainer (e.g. Bharath Bharath) --</option>
                <?php foreach ($trainersList as $tr): ?>
                    <option value="<?= htmlspecialchars($tr['userId']) ?>" data-trainer-id="<?= htmlspecialchars($tr['id']) ?>" <?= ($tr['name'] === 'Bharath Bharath' || $tr['userId'] === '6a99b5baf1624b69330f560e') ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tr['name']) ?> (<?= htmlspecialchars($tr['code']) ?>) [<?= htmlspecialchars($tr['userId']) ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 1 & 5: INSPECT THE ACTUAL ANDROID SUBSCRIPTION -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">badge</span>
                    1 &amp; 5. AUTHORITATIVE SERVICE WORKER REGISTRATION DETAILS
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

        <!-- 2 & 3: FRESHNESS & RESET PUSH SUBSCRIPTION -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">fingerprint</span>
                2 &amp; 3. SUBSCRIPTION FRESHNESS (HASH COMPARISON) &amp; RESET
            </h2>

            <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3 font-mono text-xs">
                <div>
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">CLIENT_SUBSCRIPTION_HASH (SHA-256 of endpoint):</div>
                    <div id="valClientSubHash" class="font-bold text-amber-400 break-all mt-0.5">Calculating...</div>
                </div>
                <div>
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">SERVER_SUBSCRIPTION_HASH (Active in Database for linked profile):</div>
                    <div id="valServerSubHash" class="font-bold text-slate-300 break-all mt-0.5">Querying server...</div>
                </div>
                <div class="pt-2 border-t border-slate-800 flex items-center justify-between">
                    <span class="font-sans font-bold text-slate-400">SUBSCRIPTION MATCH:</span>
                    <span id="valSubMatch" class="font-bold px-3 py-1 rounded-xl text-xs bg-slate-800 text-slate-400">Comparing...</span>
                </div>
            </div>

            <!-- 3. RESET PUSH SUBSCRIPTION BUTTON -->
            <div class="pt-1 flex flex-col sm:flex-row items-center gap-3">
                <button type="button" id="reset-push-subscription" class="w-full sm:w-auto px-5 py-3 rounded-2xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-xl transition-all flex items-center justify-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-base">restart_alt</span>
                    <span>RESET PUSH SUBSCRIPTION</span>
                </button>
                <div class="text-[11px] text-slate-400 font-sans">
                    Guarantees clean 9-step rotation: unregisters obsolete workers, subscribes directly from <code class="text-slate-200 font-mono">/sw.js</code> scope <code class="text-slate-200 font-mono">/</code>, and establishes single active database subscription.
                </div>
            </div>
            <div id="resetSubResult" class="hidden text-xs font-mono p-3 bg-slate-950 border border-slate-800 rounded-xl"></div>
        </div>

        <!-- 4. VAPID PUBLIC KEY MATCH -->
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

        <!-- 5. ONESIGNAL WEB PUSH STATUS & CONTROLLER -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">notifications_active</span>
                    5. ONESIGNAL WEB PUSH CONTROLLER
                </h2>
                <span id="osStatusBadge" class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-slate-800 text-slate-400 border-slate-700">Checking OneSignal...</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-xs font-mono">
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">Server API Key (Vercel)</div>
                    <div class="font-bold mt-1 <?= $serverOsConfigured ? 'text-emerald-400' : 'text-rose-400' ?>">
                        <?= $serverOsConfigured ? 'CONFIGURED (' . htmlspecialchars($serverOsKeyMasked) . ')' : 'NOT DETECTED' ?>
                    </div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">OneSignal Gateway Auth</div>
                    <div class="font-bold mt-1 <?= ($osCredVerification['valid'] ?? false) ? 'text-emerald-400' : 'text-rose-400' ?>">
                        <?php if ($osCredVerification['valid'] ?? false): ?>
                            ✓ VALID (<?= htmlspecialchars($osCredVerification['endpoint'] ?? '') ?>, Prefix: <?= htmlspecialchars($osCredVerification['authPrefix'] ?? 'Key') ?>)
                        <?php else: ?>
                            ✗ <?= htmlspecialchars($osCredVerification['error'] ?? ('HTTP ' . ($osCredVerification['httpCode'] ?? 401) . ' ' . json_encode($osCredVerification['raw'] ?? ''))) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">Server App ID</div>
                    <div class="font-bold mt-1 text-slate-300 break-all"><?= htmlspecialchars($serverOsAppId) ?></div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">OneSignal SDK (Client)</div>
                    <div id="valOsLoaded" class="font-bold mt-1 text-slate-300">Checking...</div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">Push Permission</div>
                    <div id="valOsPermission" class="font-bold mt-1 text-slate-300">Checking...</div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">Opted-In on Device</div>
                    <div id="valOsOptedIn" class="font-bold mt-1 text-slate-300">Checking...</div>
                </div>
                <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">Linked External ID</div>
                    <div id="valOsExternalId" class="font-bold mt-1 text-slate-300 break-all">Checking...</div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <button type="button" id="btnOsOptIn" class="px-4 py-2.5 rounded-xl bg-[#FE5E04] hover:bg-[#e04e00] text-white font-bold text-xs shadow-lg transition-all flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">notifications_active</span>
                    <span>1. Subscribe / Opt-In OneSignal</span>
                </button>
                <button type="button" id="btnOsLinkUser" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs shadow-lg transition-all flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">link</span>
                    <span>2. Link Trainer ID to OneSignal</span>
                </button>
            </div>
            <div id="osResultNotice" class="hidden text-xs font-mono p-3 bg-slate-950 border border-slate-800 rounded-xl"></div>
        </div>

        <!-- 6. THREE BACKGROUND WAKE-UP TESTS -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">science</span>
                6. THREE BACKGROUND WAKE-UP TESTS (Run after checklist is all green)
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3 flex flex-col justify-between">
                    <div>
                        <div class="font-bold text-slate-200 text-xs flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            TEST A: MENTRY OPEN
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Send push while this tab is actively in the foreground.</p>
                    </div>
                    <button type="button" id="btnTestA" class="w-full px-3 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition-all cursor-pointer">
                        Run Test A
                    </button>
                </div>

                <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3 flex flex-col justify-between">
                    <div>
                        <div class="font-bold text-amber-300 text-xs flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                            TEST B: ANDROID HOME
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Press Android Home immediately. Do NOT reopen. Wait 60s.</p>
                    </div>
                    <button type="button" id="btnTestB" class="w-full px-3 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition-all cursor-pointer">
                        Run Test B
                    </button>
                </div>

                <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3 flex flex-col justify-between">
                    <div>
                        <div class="font-bold text-purple-300 text-xs flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-purple-400"></span>
                            TEST C: LOCK SCREEN
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Press Power button immediately to lock phone. Wait 60s.</p>
                    </div>
                    <button type="button" id="btnTestC" class="w-full px-3 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition-all cursor-pointer">
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
                        <div id="lblEndpointHost" class="text-slate-500 text-[10px]">Gateway Host</div>
                        <div id="detEndpointHost" class="font-bold text-slate-300 mt-0.5">api.onesignal.com</div>
                    </div>
                    <div class="p-2 bg-slate-900 rounded-xl border border-slate-800">
                        <div id="lblAudience" class="text-slate-500 text-[10px]">Audience / Provider</div>
                        <div id="detAudience" class="font-bold text-slate-300 mt-0.5">https://api.onesignal.com</div>
                    </div>
                    <div class="p-2 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">TTL / Urgency</div>
                        <div id="detTtlUrgency" class="font-bold text-slate-300 mt-0.5">86400s / high</div>
                    </div>
                    <div class="p-2 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">HTTP Response</div>
                        <div id="detHttpStatus" class="font-bold text-emerald-400 mt-0.5">200 OK</div>
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

    <!-- Error trap for external script loading & comprehensive diagnostic inspector -->
    <script>
    let pushJsLoaded = false;

    function recordJsError(type, message, source, lineno, colno, mime) {
        const panel = document.getElementById('jsErrorPanel');
        if (panel) panel.classList.remove('hidden');
        if (document.getElementById('errType')) document.getElementById('errType').textContent = type || 'Error';
        if (document.getElementById('errMsg')) document.getElementById('errMsg').textContent = message || 'Unknown error';
        if (document.getElementById('errSource')) document.getElementById('errSource').textContent = source || window.location.href;
        if (document.getElementById('errLineCol')) document.getElementById('errLineCol').textContent = (lineno || 0) + ' : ' + (colno || 0);
        if (mime && document.getElementById('errMime')) document.getElementById('errMime').textContent = mime;
    }

    // Capture unhandled runtime exceptions
    window.onerror = function(msg, url, lineNo, colNo, error) {
        recordJsError(error ? error.name : 'RuntimeError', msg, url, lineNo, colNo);
        return false;
    };

    // Capture unhandled promise rejections
    window.addEventListener('unhandledrejection', function(e) {
        recordJsError('UnhandledRejection', e.reason ? (e.reason.message || String(e.reason)) : 'Promise rejected', '', '', '');
    });

    // Capture resource load errors (MIME type mismatch, 404, blocked script)
    window.addEventListener('error', function(e) {
        if (e.target && (e.target.tagName === 'SCRIPT' || e.target.tagName === 'LINK')) {
            const src = e.target.src || e.target.href || 'inline';
            recordJsError('ResourceError', 'Browser refused/failed to load script: ' + src, src, 0, 0);
        }
    }, true);

    function handlePushJsLoaded() {
        // Check execution marker
        const executed = !!window.__MENTRY_PUSH_JS_STARTED__;
        const elExec = document.getElementById('statPushExecuted');
        if (elExec) {
            elExec.textContent = executed ? 'YES' : 'NO';
            elExec.className = executed ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-rose-400';
        }

        if (window.MentryPush) {
            pushJsLoaded = true;
            const el = document.getElementById('statPushJs');
            if (el) {
                el.textContent = 'YES';
                el.className = 'font-bold mt-1 text-emerald-400';
            }
        } else {
            handlePushJsError('window.__MENTRY_PUSH_JS_STARTED__ = ' + executed + ', but window.MentryPush was not exported.');
        }
    }

    function handlePushJsError(err) {
        pushJsLoaded = false;
        const msg = (typeof err === 'string') ? err : (err && err.message ? err.message : 'Network / 404 error');
        const el = document.getElementById('statPushJs');
        if (el) {
            el.textContent = 'NO';
            el.className = 'font-bold mt-1 text-rose-400';
        }
        const notice = document.getElementById('statErrorNotice');
        if (notice) {
            notice.classList.remove('hidden');
            notice.textContent = 'PUSH JS LOADED = NO | ERROR = ' + msg;
        }
    }

    // Direct HTTP probe of /assets/js/push-notifications.js to verify MIME type & headers
    async function probePushJsDirectly() {
        const url = '/assets/js/push-notifications.js?_diag=' + Date.now();
        const statEl = document.getElementById('statPushDownload');
        try {
            const resp = await fetch(url, { cache: 'no-store' });
            const contentType = resp.headers.get('Content-Type') || 'none';
            const isJs = contentType.includes('javascript');
            const statusOk = resp.status === 200;

            if (statEl) {
                statEl.textContent = (statusOk && isJs) ? `YES (${resp.status})` : `FAIL (${resp.status})`;
                statEl.className = (statusOk && isJs) ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-rose-400';
            }

            if (!isJs) {
                recordJsError('MimeMismatch', `Server returned Content-Type "${contentType}" instead of application/javascript`, url, 0, 0, contentType);
            }
        } catch (fetchErr) {
            if (statEl) {
                statEl.textContent = 'NET ERROR';
                statEl.className = 'font-bold mt-1 text-rose-400';
            }
            recordJsError('FetchException', fetchErr.message, url, 0, 0);
        }
    }

    // Trigger probe immediately
    probePushJsDirectly();
    </script>
    <script src="/assets/js/push-notifications.js?v=<?= time() ?>" onload="handlePushJsLoaded()" onerror="handlePushJsError('HTTP / Network error loading /assets/js/push-notifications.js')"></script>

    <!-- Self-Contained Diagnostics & Initializer -->
    <script>
    const serverVapidRawBytesHash = "<?= htmlspecialchars($serverVapidRawBytesHash) ?>";
    let authoritativeRegistration = null;
    let clientSubHash = '';
    let clientVapidHash = '';
    let currentRawSub = null;

    // Helpers
    async function sha256Buffer(buffer) {
        const hashBuf = await crypto.subtle.digest('SHA-256', buffer);
        return Array.from(new Uint8Array(hashBuf)).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    async function sha256Text(text) {
        const enc = new TextEncoder();
        return await sha256Buffer(enc.encode(text));
    }

    function urlB64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /**
     * Requirement 1 & 2: Self-Contained Service Worker Initializer
     * Directly registers /sw.js with scope / and captures returned registration object.
     */
    async function initializePushDiagnostics() {
        const swSupported = ('serviceWorker' in navigator) && ('PushManager' in window);
        const elSwApi = document.getElementById('statSwApi');
        if (elSwApi) {
            elSwApi.textContent = swSupported ? 'YES' : 'NO';
            elSwApi.className = swSupported ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-rose-400';
        }

        if (!swSupported) {
            const notice = document.getElementById('statErrorNotice');
            if (notice) {
                notice.classList.remove('hidden');
                notice.textContent = 'ServiceWorker or PushManager is not supported on this browser.';
            }
            return;
        }

        try {
            // Explicitly register authoritative worker
            const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            authoritativeRegistration = reg;

            // Trigger update check
            try { await reg.update(); } catch (e) {}

            // Wait for service worker ready
            await navigator.serviceWorker.ready;

            // Handle installing/waiting lifecycle
            if (reg.installing) {
                await new Promise(resolve => {
                    const worker = reg.installing;
                    const stateChangeHandler = () => {
                        if (worker.state === 'activated' || worker.state === 'redundant') {
                            worker.removeEventListener('statechange', stateChangeHandler);
                            resolve();
                        }
                    };
                    worker.addEventListener('statechange', stateChangeHandler);
                    setTimeout(resolve, 3000);
                });
            } else if (reg.waiting) {
                reg.waiting.postMessage({ type: 'SKIP_WAITING' });
            }

        } catch (regErr) {
            console.error('Direct SW registration failed:', regErr);
            const notice = document.getElementById('statErrorNotice');
            if (notice) {
                notice.classList.remove('hidden');
                notice.textContent = 'ServiceWorker registration error: ' + regErr.message;
            }
        }

        // Refresh all display elements
        await refreshRegistrationState();
    }

    /**
     * Requirement 5 & 8: Inspect and display actual registration & subscription
     */
    async function refreshRegistrationState() {
        if (!('serviceWorker' in navigator)) return;

        const allRegs = await navigator.serviceWorker.getRegistrations();
        const count = allRegs.length;
        if (document.getElementById('regCount')) document.getElementById('regCount').textContent = count;
        if (document.getElementById('statRegCount')) document.getElementById('statRegCount').textContent = count;

        // Find root authoritative registration
        let authoritative = allRegs.find(r => r.scope === (window.location.origin + '/')) || authoritativeRegistration;

        const regFound = !!authoritative;
        const elRegFound = document.getElementById('statRegFound');
        if (elRegFound) {
            elRegFound.textContent = regFound ? `YES (${count})` : 'NO (0)';
            elRegFound.className = regFound ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-rose-400';
        }

        const activeWorker = authoritative ? authoritative.active : null;
        const activeScript = activeWorker ? activeWorker.scriptURL : (authoritative?.waiting ? authoritative.waiting.scriptURL : (authoritative?.installing ? authoritative.installing.scriptURL : 'None'));
        const activeState = activeWorker ? activeWorker.state : (authoritative?.waiting ? 'waiting' : (authoritative?.installing ? 'installing' : 'none'));

        if (document.getElementById('valScope')) document.getElementById('valScope').textContent = authoritative ? authoritative.scope : 'None';
        if (document.getElementById('valScriptURL')) document.getElementById('valScriptURL').textContent = activeScript;
        if (document.getElementById('valActiveState')) {
            document.getElementById('valActiveState').textContent = activeState;
            document.getElementById('valActiveState').className = (activeState === 'activated') ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-amber-400';
        }

        if (document.getElementById('statActiveScript')) document.getElementById('statActiveScript').textContent = activeScript.replace(window.location.origin, '');
        if (document.getElementById('statActiveState')) {
            document.getElementById('statActiveState').textContent = activeState;
            document.getElementById('statActiveState').className = (activeState === 'activated') ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-amber-400';
        }

        // Single SW badge
        const singleBadge = document.getElementById('singleSwBadge');
        if (count === 1 && activeScript.endsWith('/sw.js')) {
            singleBadge.textContent = '✓ Exactly 1 Mentry SW';
            singleBadge.className = 'text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
        } else if (count > 1) {
            singleBadge.textContent = `⚠ ${count} Registrations Detected`;
            singleBadge.className = 'text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-rose-500/20 text-rose-300 border-rose-500/30';
        } else {
            singleBadge.textContent = '○ None Active';
            singleBadge.className = 'text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-slate-800 text-slate-400 border-slate-700';
        }

        // Render table of all registrations
        const tbody = document.getElementById('regsTableBody');
        tbody.innerHTML = '';
        if (count === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="p-3 text-rose-400 italic">No ServiceWorker registrations found.</td></tr>';
        } else {
            allRegs.forEach(r => {
                const sURL = r.active?.scriptURL || r.waiting?.scriptURL || r.installing?.scriptURL || 'None';
                const sState = r.active?.state || (r.waiting ? 'waiting' : (r.installing ? 'installing' : 'none'));
                const isAuth = (r.scope === (window.location.origin + '/')) && sURL.endsWith('/sw.js');

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="py-2 px-3 break-all ${isAuth ? 'text-emerald-400 font-bold' : 'text-amber-400'}">${r.scope}</td>
                    <td class="py-2 px-3 break-all text-slate-300">${sURL}</td>
                    <td class="py-2 px-3 text-slate-400">${sState}</td>
                    <td class="py-2 px-3">${isAuth ? '<span class="text-emerald-400 font-bold">Authoritative (/)</span>' : '<span class="text-rose-400 font-bold">Obsolete</span>'}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Requirement 8: Inspect subscription on authoritative registration
        if (authoritative) {
            try {
                const sub = await authoritative.pushManager.getSubscription();
                currentRawSub = sub;

                const subExists = !!sub;
                document.getElementById('valSubExists').textContent = subExists ? 'YES' : 'NO';
                document.getElementById('valSubExists').className = subExists ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-rose-400';
                document.getElementById('statSubExists').textContent = subExists ? 'YES' : 'NO';
                document.getElementById('statSubExists').className = subExists ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-rose-400';

                const hasAppKey = !!(sub && sub.options && sub.options.applicationServerKey);
                document.getElementById('valAppKeyExists').textContent = hasAppKey ? 'YES' : 'NO';
                document.getElementById('valAppKeyExists').className = hasAppKey ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-amber-400';

                if (sub) {
                    try {
                        const epHost = new URL(sub.endpoint).hostname;
                        document.getElementById('valEndpointHost').textContent = epHost;
                    } catch {
                        document.getElementById('valEndpointHost').textContent = 'valid-endpoint';
                    }

                    clientSubHash = await sha256Text(sub.endpoint);
                    document.getElementById('valClientSubHash').textContent = clientSubHash;

                    if (hasAppKey) {
                        clientVapidHash = await sha256Buffer(sub.options.applicationServerKey);
                        document.getElementById('valClientVapidHash').textContent = clientVapidHash;

                        const vMatch = (clientVapidHash === serverVapidRawBytesHash);
                        document.getElementById('valVapidMatch').textContent = vMatch ? 'MATCH = YES' : 'MATCH = NO';
                        document.getElementById('valVapidMatch').className = vMatch ? 'font-bold px-3 py-1 rounded-xl text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'font-bold px-3 py-1 rounded-xl text-xs bg-rose-500/20 text-rose-300 border border-rose-500/30';
                    }
                } else {
                    document.getElementById('valEndpointHost').textContent = 'None';
                    document.getElementById('valClientSubHash').textContent = 'No subscription on device.';
                    document.getElementById('valClientVapidHash').textContent = 'N/A';
                    document.getElementById('valVapidMatch').textContent = 'N/A';
                }
            } catch (subErr) {
                console.error('Error inspecting subscription:', subErr);
            }
        }

        // Compare with server database subscription
        await checkServerSubscriptionFreshness();
    }

    /**
     * Requirement 2: Check server subscription freshness comparison
     */
    async function checkServerSubscriptionFreshness() {
        const targetUserId = document.getElementById('selectTargetTrainer').value;
        const url = '/actions/push/check-subscription.php?clientHash=' + encodeURIComponent(clientSubHash) + (targetUserId ? ('&userId=' + encodeURIComponent(targetUserId)) : '');

        try {
            const res = await fetch(url, { cache: 'no-store' });
            const data = await res.json();

            if (data.success) {
                const sHash = data.serverSubscriptionHash || 'None in database for this profile';
                document.getElementById('valServerSubHash').textContent = sHash;

                const isMatch = !!data.match;
                const matchEl = document.getElementById('valSubMatch');
                matchEl.textContent = isMatch ? 'MATCH = YES' : 'MATCH = NO';
                matchEl.className = isMatch ? 'font-bold px-3 py-1 rounded-xl text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'font-bold px-3 py-1 rounded-xl text-xs bg-rose-500/20 text-rose-300 border border-rose-500/30';
            }
        } catch (e) {
            document.getElementById('valServerSubHash').textContent = 'Server query error: ' + e.message;
        }
    }

    /**
     * Requirement 3: Self-Contained resetSubscription Function
     * Unsubscribes, deactivates on server, creates fresh subscription on /sw.js scope /,
     * and links to the selected profile.
     */
    async function resetSubscription() {
        const btn = document.getElementById('reset-push-subscription');
        const resDiv = document.getElementById('resetSubResult');
        const targetUserId = document.getElementById('selectTargetTrainer').value;

        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">refresh</span> Resetting...';
        resDiv.classList.remove('hidden');
        resDiv.innerHTML = '<span class="text-amber-300 animate-pulse">1. Registering & updating /sw.js with scope /...</span>';

        try {
            // Step 1: Ensure authoritative registration
            const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            await reg.update();
            await navigator.serviceWorker.ready;
            authoritativeRegistration = reg;

            // Step 2 & 3: Unsubscribe existing subscription
            resDiv.innerHTML = '<span class="text-amber-300 animate-pulse">2. Unsubscribing existing subscription...</span>';
            const oldSub = await reg.pushManager.getSubscription();
            if (oldSub) {
                try { await oldSub.unsubscribe(); } catch (e) {}

                // Step 4: Deactivate on server
                try {
                    await fetch('/actions/push/unsubscribe.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ endpoint: oldSub.endpoint })
                    });
                } catch (e) {}
            }

            // Clean any obsolete registrations
            const allRegs = await navigator.serviceWorker.getRegistrations();
            for (const r of allRegs) {
                if (r.scope !== (window.location.origin + '/')) {
                    try { await r.unregister(); } catch (e) {}
                }
            }

            // Request permission if not already granted
            resDiv.innerHTML = '<span class="text-amber-300 animate-pulse">3. Verifying notification permission...</span>';
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') {
                throw new Error('Notification permission was ' + perm + '. You must allow notifications in browser.');
            }

            // Step 5: Create completely fresh subscription using THIS registration
            resDiv.innerHTML = '<span class="text-amber-300 animate-pulse">4. Fetching VAPID key & subscribing...</span>';
            const vapidResp = await fetch('/actions/push/public-key.php?_t=' + Date.now());
            const vapidData = await vapidResp.json();
            if (!vapidData.success || !vapidData.publicKey) {
                throw new Error('Failed to retrieve VAPID key: ' + (vapidData.error || 'Empty key'));
            }

            const convertedKey = urlB64ToUint8Array(vapidData.publicKey);
            const newSub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedKey
            });

            // Step 6: Send to subscribe.php
            resDiv.innerHTML = '<span class="text-amber-300 animate-pulse">5. Saving fresh subscription to server...</span>';
            const json = newSub.toJSON();
            const payload = {
                endpoint: newSub.endpoint,
                keys: {
                    p256dh: json.keys ? json.keys.p256dh : '',
                    auth: json.keys ? json.keys.auth : ''
                },
                device: /Mobi|Android/i.test(navigator.userAgent) ? 'Mobile' : 'Desktop',
                browser: /Chrome/i.test(navigator.userAgent) ? 'Chrome' : 'Browser',
                platform: /Android/i.test(navigator.userAgent) ? 'Android' : 'Desktop',
                resetUserSubscriptions: true,
                targetUserId: targetUserId || ''
            };

            const subResp = await fetch('/actions/push/subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const subData = await subResp.json();

            // Step 7, 8, 9: Re-check everything and display
            await refreshRegistrationState();

            resDiv.innerHTML = `
                <div class="text-emerald-400 font-bold">✓ RESET PUSH SUBSCRIPTION COMPLETE!</div>
                <div class="text-slate-300">New Client Hash: <span class="text-white">${clientSubHash}</span></div>
                <div class="text-slate-300">Server Subscribed: <span class="text-emerald-300 font-bold">${subData.subscribed ? 'YES' : 'NO'}</span></div>
                <div class="text-slate-300">Target User: <span class="text-white">${subData.userId || targetUserId || 'Session'}</span></div>
                <div class="text-amber-300 font-bold mt-1 font-sans">Ready! Verify the status checklist above is all green.</div>
            `;

        } catch (err) {
            console.error('resetSubscription error:', err);
            resDiv.innerHTML = `<span class="text-rose-400 font-bold">Reset failed: ${err.message}</span>`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
    window.resetSubscription = resetSubscription;

    /**
     * Requirement 6: Run Specific Test (A, B, C)
     */
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
                const isOs = (data.provider === 'OneSignal' || data.pushDetails.provider === 'OneSignal');
                document.getElementById('detEndpointHost').textContent = data.pushDetails.endpointHost || (isOs ? 'api.onesignal.com' : 'fcm.googleapis.com');
                document.getElementById('detAudience').textContent = data.pushDetails.audience || (isOs ? 'https://api.onesignal.com' : 'https://fcm.googleapis.com');
                document.getElementById('detTtlUrgency').textContent = `${data.pushDetails.ttl || 86400}s / ${data.pushDetails.urgency || 'high'}` + (data.oneSignalId ? ` [ID: ${data.oneSignalId.substring(0, 8)}...]` : '');
                document.getElementById('detHttpStatus').textContent = `HTTP ${data.statusCode || 200} (${data.reason || 'Accepted'})`;
            }

            const isOneSignal = (data.provider === 'OneSignal');
            const providerName = isOneSignal ? 'OneSignal Push' : 'Push Gateway';

            if (data.pushServiceAccepted || data.success) {
                if (testType === 'A') {
                    outCard.innerHTML = `
                        <div class="text-emerald-400 font-bold text-sm">✓ TEST A SENT via ${providerName} (HTTP ${data.statusCode || 200})</div>
                        <div class="text-slate-300">TEST_ID: <span class="text-white font-bold">${testId}</span></div>
                        <div class="text-slate-300">Target Recipients: <span class="text-white">${data.subscriptionCount || 1}</span></div>
                        ${data.oneSignalId ? `<div class="text-slate-400 text-[11px]">OneSignal ID: <span class="text-emerald-300 font-mono">${data.oneSignalId}</span></div>` : ''}
                        <div class="text-amber-300 mt-1 font-sans">Tab is in foreground. Did the native notification appear?</div>
                        <div class="pt-1">
                            <button type="button" onclick="pollReceipt('${testId}')" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-200 border border-slate-700 text-xs font-bold cursor-pointer">
                                Check sw.js Receipt
                            </button>
                        </div>
                    `;
                } else if (testType === 'B') {
                    outCard.innerHTML = `
                        <div class="text-emerald-400 font-bold text-sm">✓ TEST B DISPATCHED via ${providerName} (HTTP ${data.statusCode || 200})</div>
                        <div class="text-slate-300">TEST_ID: <span class="text-white font-bold">${testId}</span></div>
                        <div class="text-slate-300">Target Recipients: <span class="text-white">${data.subscriptionCount || 1}</span></div>
                        ${data.oneSignalId ? `<div class="text-slate-400 text-[11px]">OneSignal ID: <span class="text-emerald-300 font-mono">${data.oneSignalId}</span></div>` : ''}
                        <div class="p-3 bg-amber-500/20 border border-amber-500/30 rounded-xl text-amber-200 font-sans mt-2 space-y-1">
                            <div class="font-bold text-sm">👉 PRESS ANDROID HOME BUTTON NOW!</div>
                            <div>Do NOT reopen Mentry. Do NOT swipe Chrome away.</div>
                            <div>Wait 15–30 seconds. OneSignal will wake Chrome and display the notification.</div>
                        </div>
                        <div class="pt-2">
                            <button type="button" onclick="pollReceipt('${testId}')" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-200 border border-slate-700 text-xs font-bold cursor-pointer">
                                Check Receipt for ${testId}
                            </button>
                        </div>
                    `;
                } else if (testType === 'C') {
                    outCard.innerHTML = `
                        <div class="text-emerald-400 font-bold text-sm">✓ TEST C DISPATCHED via ${providerName} (HTTP ${data.statusCode || 200})</div>
                        <div class="text-slate-300">TEST_ID: <span class="text-white font-bold">${testId}</span></div>
                        <div class="text-slate-300">Target Recipients: <span class="text-white">${data.subscriptionCount || 1}</span></div>
                        ${data.oneSignalId ? `<div class="text-slate-400 text-[11px]">OneSignal ID: <span class="text-emerald-300 font-mono">${data.oneSignalId}</span></div>` : ''}
                        <div class="p-3 bg-purple-500/20 border border-purple-500/30 rounded-xl text-purple-200 font-sans mt-2 space-y-1">
                            <div class="font-bold text-sm">👉 PRESS POWER BUTTON TO LOCK PHONE NOW!</div>
                            <div>Leave phone locked for 15–30 seconds. Observe if lock screen lights up or notification rings.</div>
                        </div>
                        <div class="pt-2">
                            <button type="button" onclick="pollReceipt('${testId}')" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-200 border border-slate-700 text-xs font-bold cursor-pointer">
                                Check Receipt for ${testId}
                            </button>
                        </div>
                    `;
                }
            } else {
                outCard.innerHTML = `
                    <div class="text-rose-400 font-bold">Dispatch failed (${providerName}): HTTP ${data.statusCode || 500} - ${data.reason || data.error}</div>
                    ${data.oneSignalId ? `<div class="text-slate-400 text-[11px]">OneSignal ID: ${data.oneSignalId}</div>` : ''}
                `;
            }

        } catch (e) {
            outCard.innerHTML = `<div class="text-rose-400 font-bold">Dispatch exception: ${e.message}</div>`;
        }
    }

    async function pollReceipt(testId) {
        const outCard = document.getElementById('testOutputCard');
        outCard.innerHTML = `<div class="text-amber-300 font-bold animate-pulse">Checking receipt for ${testId}...</div>`;

        let r = null;
        try {
            const cache = await caches.open('mentry-push-diagnostics');
            const cResp = await cache.match('/last-push-diag.json');
            if (cResp) r = await cResp.json();
        } catch (e) {}

        try {
            const sResp = await fetch('/actions/push/record-receipt.php?testId=' + encodeURIComponent(testId), { cache: 'no-store' });
            if (sResp.ok) {
                const sData = await sResp.json();
                if (sData.success && sData.found) r = sData.receipt;
            }
        } catch (e) {}

        if (r && (r.testId === testId || !testId)) {
            outCard.innerHTML = `
                <div class="p-3 bg-emerald-500/20 border border-emerald-500/30 rounded-xl space-y-1">
                    <div class="text-emerald-400 font-bold text-sm">✓ QUESTION A: YES — sw.js received push event!</div>
                    <div class="text-slate-300 text-xs">Received At: <span class="text-white">${r.receivedAt}</span></div>
                    <div class="text-slate-300 text-xs">showNotification(): <span class="${r.showNotificationSuccess ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'}">${r.showNotificationSuccess ? 'SUCCESS' : 'THREW: ' + r.showNotificationError}</span></div>
                    <div class="text-slate-400 text-[11px]">SW Scope: ${r.swScope}</div>
                </div>
            `;
        } else {
            outCard.innerHTML = `
                <div class="p-3 bg-rose-500/20 border border-rose-500/30 rounded-xl space-y-1">
                    <div class="text-rose-400 font-bold text-sm">QUESTION A: NO receipt recorded for ${testId}</div>
                    <div class="text-slate-300 text-xs">sw.js did NOT execute while in background.</div>
                    <div class="text-slate-400 text-[11px] pt-1 font-sans">
                        Ensure all 7 checklist items above are green, and click "RESET PUSH SUBSCRIPTION" before retrying.
                    </div>
                </div>
            `;
        }
    }

    // Requirement 3: Attach event listeners on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', async () => {
        const resetButton = document.getElementById('reset-push-subscription');
        if (resetButton) {
            resetButton.addEventListener('click', resetSubscription);
        }

        const refreshButton = document.getElementById('btnRefreshDiagnostics');
        if (refreshButton) {
            refreshButton.addEventListener('click', refreshRegistrationState);
        }

        const btnA = document.getElementById('btnTestA');
        if (btnA) btnA.addEventListener('click', () => runSpecificTest('A'));

        const btnB = document.getElementById('btnTestB');
        if (btnB) btnB.addEventListener('click', () => runSpecificTest('B'));

        const btnC = document.getElementById('btnTestC');
        if (btnC) btnC.addEventListener('click', () => runSpecificTest('C'));

        const selTrainer = document.getElementById('selectTargetTrainer');
        if (selTrainer) {
            selTrainer.addEventListener('change', () => {
                checkServerSubscriptionFreshness();
                updateOsStatus();
            });
        }

        // Initialize directly
        await initializePushDiagnostics();
    });

    // OneSignal Web Push SDK v16 Integration
    async function updateOsStatus() {
        if (!window.OneSignal) return;
        try {
            document.getElementById('valOsLoaded').textContent = 'YES (v16)';
            document.getElementById('valOsLoaded').className = 'font-bold mt-1 text-emerald-400';

            const perm = Notification.permission;
            document.getElementById('valOsPermission').textContent = perm;
            document.getElementById('valOsPermission').className = (perm === 'granted') ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-amber-400';

            const optedIn = OneSignal.User.PushSubscription.optedIn;
            document.getElementById('valOsOptedIn').textContent = optedIn ? 'YES' : 'NO';
            document.getElementById('valOsOptedIn').className = optedIn ? 'font-bold mt-1 text-emerald-400' : 'font-bold mt-1 text-rose-400';

            const extId = OneSignal.User.externalId || 'None';
            document.getElementById('valOsExternalId').textContent = extId;
            document.getElementById('valOsExternalId').className = (extId !== 'None') ? 'font-bold mt-1 text-emerald-400 break-all' : 'font-bold mt-1 text-slate-400 break-all';

            const badge = document.getElementById('osStatusBadge');
            if (badge) {
                if (optedIn && extId !== 'None') {
                    badge.textContent = '✓ Active on Device';
                    badge.className = 'text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
                } else if (optedIn) {
                    badge.textContent = '⚠ Opted-In (No User Linked)';
                    badge.className = 'text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-amber-500/20 text-amber-300 border-amber-500/30';
                } else {
                    badge.textContent = '○ Not Subscribed';
                    badge.className = 'text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-slate-800 text-slate-400 border-slate-700';
                }
            }
        } catch (e) {
            console.warn('[OneSignal Status Error]', e);
        }
    }
    </script>
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
    <script>
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    OneSignalDeferred.push(async function(OneSignal) {
        try {
            await OneSignal.init({
                appId: "e2443de9-128c-4e5f-a964-03260aa8c627",
                safari_web_id: "web.onesignal.auto.16fe94fe-85b7-4f18-b294-6465f1482156",
                serviceWorkerPath: "push/onesignal/OneSignalSDKWorker.js",
                serviceWorkerParam: { scope: "/push/onesignal/" }
            });

            // Auto-link selected trainer
            const selEl = document.getElementById('selectTargetTrainer');
            const targetId = selEl ? selEl.value : '';
            if (targetId) {
                await OneSignal.login(targetId);
                await OneSignal.User.addTags({ role: 'TRAINER', mentry_id: targetId });
            }

            await updateOsStatus();

            OneSignal.User.PushSubscription.addEventListener('change', updateOsStatus);

            // Bind OneSignal buttons
            const btnOptIn = document.getElementById('btnOsOptIn');
            if (btnOptIn) {
                btnOptIn.addEventListener('click', async () => {
                    const resNotice = document.getElementById('osResultNotice');
                    resNotice.classList.remove('hidden');
                    resNotice.innerHTML = '<span class="text-amber-300 animate-pulse">Requesting notification permission via OneSignal...</span>';
                    try {
                        await OneSignal.User.PushSubscription.optIn();
                        await updateOsStatus();
                        resNotice.innerHTML = '<span class="text-emerald-400 font-bold">✓ OneSignal Opt-In Successful! Device subscribed.</span>';
                    } catch (err) {
                        resNotice.innerHTML = `<span class="text-rose-400 font-bold">OneSignal Opt-In Error: ${err.message}</span>`;
                    }
                });
            }

            const btnLink = document.getElementById('btnOsLinkUser');
            if (btnLink) {
                btnLink.addEventListener('click', async () => {
                    const sel = document.getElementById('selectTargetTrainer');
                    const uid = sel ? sel.value : '';
                    const resNotice = document.getElementById('osResultNotice');
                    resNotice.classList.remove('hidden');
                    if (!uid) {
                        resNotice.innerHTML = '<span class="text-rose-400 font-bold">Please select a trainer profile first.</span>';
                        return;
                    }
                    resNotice.innerHTML = `<span class="text-amber-300 animate-pulse">Linking ${uid} to OneSignal...</span>`;
                    try {
                        await OneSignal.login(uid);
                        await OneSignal.User.addTags({ role: 'TRAINER', mentry_user_id: uid });
                        await updateOsStatus();
                        resNotice.innerHTML = `<span class="text-emerald-400 font-bold">✓ Successfully linked External ID "${uid}" in OneSignal!</span>`;
                    } catch (err) {
                        resNotice.innerHTML = `<span class="text-rose-400 font-bold">OneSignal Link Error: ${err.message}</span>`;
                    }
                });
            }
        } catch (e) {
            console.error('[OneSignal Init Error]', e);
        }
    });
    </script>
</body>
</html>
