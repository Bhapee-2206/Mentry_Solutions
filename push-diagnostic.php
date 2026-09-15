<?php
// push-diagnostic.php - Dedicated Real-Device Web Push Diagnostic Console
// Provides instant on-device verification of ServiceWorker registrations,
// PushSubscription ownership, and background push receipt logging.

$pageTitle = 'Mentry Push Diagnostics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
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
                    <span class="material-symbols-outlined text-[#FE5E04] text-3xl">perm_device_information</span>
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white">Background Push Diagnostic</h1>
                </div>
                <p class="text-xs text-slate-400 mt-1">Real-device ServiceWorker registration and background push event verification.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="runFullDiagnostic()" class="px-4 py-2 rounded-xl bg-[#FE5E04] hover:bg-[#e04e00] text-white text-xs font-bold transition-all flex items-center gap-1.5 shadow-lg cursor-pointer">
                    <span class="material-symbols-outlined text-sm">refresh</span> Refresh Status
                </button>
            </div>
        </div>

        <!-- TEST 2: Verify Subscription Owner & SW Registrations -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">verified_user</span>
                    TEST 2 — SUBSCRIPTION OWNER & SERVICE WORKER STATE
                </h2>
                <span id="singleSwBadge" class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-slate-800 text-slate-400 border-slate-700">Checking...</span>
            </div>

            <!-- Active Registration Inspection Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-xs font-mono">
                <div class="bg-slate-950/80 p-3.5 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">registration.scope</div>
                    <div id="valScope" class="font-bold mt-1 text-slate-300 break-all">Inspecting...</div>
                </div>
                <div class="bg-slate-950/80 p-3.5 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">registration.active.scriptURL</div>
                    <div id="valScriptURL" class="font-bold mt-1 text-slate-300 break-all">Inspecting...</div>
                </div>
                <div class="bg-slate-950/80 p-3.5 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">registration.active.state</div>
                    <div id="valActiveState" class="font-bold mt-1 text-slate-300">Inspecting...</div>
                </div>
                <div class="bg-slate-950/80 p-3.5 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">subscription exists</div>
                    <div id="valSubExists" class="font-bold mt-1 text-slate-300">Inspecting...</div>
                </div>
                <div class="bg-slate-950/80 p-3.5 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">subscription.options.applicationServerKey</div>
                    <div id="valAppKeyExists" class="font-bold mt-1 text-slate-300">Inspecting...</div>
                </div>
                <div class="bg-slate-950/80 p-3.5 rounded-2xl border border-slate-800">
                    <div class="text-slate-500 text-[10px] uppercase font-sans font-bold">subscription endpoint hostname</div>
                    <div id="valEndpointHost" class="font-bold mt-1 text-slate-300 break-all">Inspecting...</div>
                </div>
            </div>

            <!-- Enumeration of navigator.serviceWorker.getRegistrations() -->
            <div class="space-y-2 pt-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                    <span>navigator.serviceWorker.getRegistrations() [<span id="regCount">0</span> found]</span>
                    <button type="button" onclick="cleanRegistrationsAndResub()" id="btnCleanRegs" class="text-[11px] text-amber-400 hover:text-amber-300 underline cursor-pointer">
                        Unregister obsolete & Re-subscribe
                    </button>
                </div>
                <div class="overflow-x-auto border border-slate-800 rounded-2xl bg-slate-950/80">
                    <table class="w-full text-left text-xs font-mono">
                        <thead>
                            <tr class="text-slate-500 border-b border-slate-800 text-[11px]">
                                <th class="py-2.5 px-3">Scope</th>
                                <th class="py-2.5 px-3">Active ScriptURL</th>
                                <th class="py-2.5 px-3">Active State</th>
                                <th class="py-2.5 px-3">Target</th>
                            </tr>
                        </thead>
                        <tbody id="regsTableBody" class="divide-y divide-slate-800/60 text-slate-300">
                            <tr><td colspan="4" class="p-3 text-slate-500 italic">Inspecting registrations...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TEST 3 & 4: Protocol For Background Test & Receipt Result -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-[#FE5E04] flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">touch_app</span>
                TEST 3 & 4 — BACKGROUND PUSH TEST PROTOCOL
            </h2>

            <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3 text-xs">
                <div class="font-semibold text-slate-300">1. Current Test ID to Record Before Backgrounding:</div>
                <div class="flex items-center gap-2">
                    <input type="text" id="inputTestId" readonly class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 font-mono text-emerald-400 text-xs focus:outline-none select-all" />
                    <button type="button" onclick="generateNewTestId()" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-300 border border-slate-700 cursor-pointer whitespace-nowrap">
                        New ID
                    </button>
                </div>

                <div class="font-semibold text-slate-300 pt-1">2. Record Before Closing/Backgrounding:</div>
                <div id="preBackgroundRecord" class="p-3 bg-slate-900/90 rounded-xl font-mono text-[11px] text-slate-400 space-y-0.5 border border-slate-800 select-all">
                    Loading record...
                </div>

                <div class="font-semibold text-slate-300 pt-1">3. Physical Steps on Device:</div>
                <ol class="list-decimal list-inside space-y-1 text-slate-400 text-[11px] pl-1 font-sans">
                    <li><strong class="text-slate-200">Press Android Home button</strong> now (Mentry is now backgrounded).</li>
                    <li><strong class="text-slate-200">DO NOT swipe Chrome away yet</strong>. Do not open Mentry again.</li>
                    <li>Trigger the test push from Admin Panel, or click button below before pressing Home.</li>
                </ol>

                <div class="pt-2 flex flex-col sm:flex-row gap-2">
                    <button type="button" id="btnSelfTestPush" onclick="triggerSelfTestPush()" class="px-4 py-2.5 rounded-xl bg-[#FE5E04] hover:bg-[#e04e00] text-white font-bold text-xs shadow-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">send</span>
                        <span>Send Background Push for this Test ID</span>
                    </button>
                    <button type="button" id="btnCheckReceipt" onclick="checkTestReceipt()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">receipt_long</span>
                        <span>Check Receipt (Did sw.js execute?)</span>
                    </button>
                </div>
            </div>

            <!-- Receipt & Layer Failure Analysis Card -->
            <div id="receiptResultCard" class="p-4 bg-slate-950 border border-slate-800 rounded-2xl text-xs space-y-3">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-[#FE5E04]">troubleshoot</span>
                    DIAGNOSTIC LAYER ANALYSIS: QUESTION A (Did sw.js receive push?)
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-2 font-mono text-[11px]">
                    <div class="p-2.5 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">1. SERVER → FCM</div>
                        <div id="layer1Status" class="font-bold text-slate-300 mt-0.5">UNKNOWN</div>
                    </div>
                    <div class="p-2.5 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">2. FCM → CHROME</div>
                        <div id="layer2Status" class="font-bold text-slate-300 mt-0.5">UNKNOWN</div>
                    </div>
                    <div class="p-2.5 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">3. CHROME → SW</div>
                        <div id="layer3Status" class="font-bold text-slate-300 mt-0.5">UNKNOWN</div>
                    </div>
                    <div class="p-2.5 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">4. SW → NOTIFICATION</div>
                        <div id="layer4Status" class="font-bold text-slate-300 mt-0.5">UNKNOWN</div>
                    </div>
                    <div class="p-2.5 bg-slate-900 rounded-xl border border-slate-800">
                        <div class="text-slate-500 text-[10px]">5. ANDROID DISPLAY</div>
                        <div id="layer5Status" class="font-bold text-slate-300 mt-0.5">UNKNOWN</div>
                    </div>
                </div>

                <div id="receiptDetail" class="text-[11px] text-slate-400 font-mono">
                    No receipt queried yet. Click "Check Receipt" after sending the push.
                </div>
            </div>
        </div>

        <!-- Android Device Checks -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-3 text-xs">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-[#FE5E04]">settings</span>
                ANDROID SYSTEM CHECKLIST (Do NOT Force-Stop Chrome)
            </h2>
            <ul class="space-y-1.5 text-slate-400 text-[11px]">
                <li class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <strong>Chrome App Notifications:</strong> Settings &gt; Apps &gt; Chrome &gt; Notifications &gt; Enabled
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <strong>Site Notifications:</strong> Chrome &gt; Settings &gt; Site settings &gt; Notifications &gt; mentry-solutions.vercel.app allowed
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <strong>Chrome Battery:</strong> Settings &gt; Apps &gt; Chrome &gt; Battery &gt; Set to <em>Optimized</em> or <em>Unrestricted</em> (NEVER "Restricted")
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <strong>Background Data:</strong> Settings &gt; Apps &gt; Chrome &gt; Mobile data &amp; Wi-Fi &gt; Allow background data: Enabled
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <strong>Notification Channel:</strong> Site notification category must not be set to "Silent" or "Deliver quietly"
                </li>
            </ul>
        </div>

    </div>

    <script src="/assets/js/push-notifications.js?v=<?= time() ?>"></script>
    <script>
    let activeTestId = 'bg_test_' + Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 7);

    function generateNewTestId() {
        activeTestId = 'bg_test_' + Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 7);
        document.getElementById('inputTestId').value = activeTestId;
        updatePreBackgroundRecord();
    }

    let lastDiagInfo = null;

    async function runFullDiagnostic() {
        document.getElementById('inputTestId').value = activeTestId;

        if (!window.MentryPush || typeof window.MentryPush.getDiagnostics !== 'function') {
            alert('MentryPush controller not ready yet.');
            return;
        }

        try {
            const diag = await window.MentryPush.getDiagnostics();
            lastDiagInfo = diag;

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

            if (regs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="p-3 text-rose-400 italic">No ServiceWorker registrations found.</td></tr>';
            } else {
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
            }

            updatePreBackgroundRecord();

        } catch (e) {
            console.error('runFullDiagnostic error:', e);
        }
    }

    function updatePreBackgroundRecord() {
        const pre = document.getElementById('preBackgroundRecord');
        if (!lastDiagInfo) {
            pre.textContent = 'Run diagnostic first...';
            return;
        }

        pre.innerHTML = `
TEST_ID: ${activeTestId}
SW_SCOPE: ${lastDiagInfo.scope || 'N/A'}
SW_SCRIPT: ${lastDiagInfo.activeScriptURL || 'N/A'}
SW_STATE: ${lastDiagInfo.activeState || 'N/A'}
SUBSCRIPTION_EXISTS: ${lastDiagInfo.subscriptionExists ? 'YES' : 'NO'}
APP_SERVER_KEY_EXISTS: ${lastDiagInfo.applicationServerKeyExists ? 'YES' : 'NO'}
SUBSCRIPTION_ENDPOINT_HOST: ${lastDiagInfo.endpointHostname || 'none'}
        `.trim();
    }

    async function cleanRegistrationsAndResub() {
        const btn = document.getElementById('btnCleanRegs');
        btn.textContent = 'Cleaning & migrating...';
        try {
            if (window.MentryPush && typeof window.MentryPush.migrateSubscription === 'function') {
                await window.MentryPush.migrateSubscription();
                await runFullDiagnostic();
                alert('Clean migration complete! Exactly one registration active on /sw.js with scope /.');
            }
        } catch (e) {
            alert('Migration error: ' + (e.message || String(e)));
        } finally {
            btn.textContent = 'Unregister obsolete & Re-subscribe';
        }
    }

    async function triggerSelfTestPush() {
        const btn = document.getElementById('btnSelfTestPush');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">refresh</span> Dispatching...';

        document.getElementById('layer1Status').textContent = 'SENDING...';
        document.getElementById('layer1Status').className = 'font-bold text-amber-400 mt-0.5';

        try {
            // Self push endpoint: calls /actions/push/test.php or subscribe with testId
            const fd = new FormData();
            fd.append('testId', activeTestId);
            fd.append('isBackgroundTest', '1');

            const res = await fetch('/actions/push/test.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (data.pushServiceAccepted || data.success) {
                document.getElementById('layer1Status').textContent = `PASS (HTTP ${data.statusCode || 201})`;
                document.getElementById('layer1Status').className = 'font-bold text-emerald-400 mt-0.5';
                document.getElementById('receiptDetail').innerHTML = `
                    <div class="text-emerald-400 font-bold">✓ SERVER → FCM: Accepted by push service (HTTP ${data.statusCode || 201})</div>
                    <div class="text-slate-300">Test ID: <span class="text-white font-bold">${data.testId || activeTestId}</span></div>
                    <div class="text-amber-300 mt-1 font-sans">Now press Android HOME immediately. Do not swipe Chrome away. After 15 seconds, check if notification appeared, then re-open this page and click "Check Receipt".</div>
                `;
            } else {
                document.getElementById('layer1Status').textContent = `FAIL (HTTP ${data.statusCode || 500})`;
                document.getElementById('layer1Status').className = 'font-bold text-rose-400 mt-0.5';
                document.getElementById('receiptDetail').textContent = 'Server push failed: ' + (data.reason || data.error || 'Unknown error');
            }
        } catch (e) {
            document.getElementById('layer1Status').textContent = 'FAIL (Exception)';
            document.getElementById('layer1Status').className = 'font-bold text-rose-400 mt-0.5';
            document.getElementById('receiptDetail').textContent = 'Network error: ' + e.message;
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    async function checkTestReceipt() {
        const btn = document.getElementById('btnCheckReceipt');
        btn.disabled = true;
        btn.textContent = 'Checking...';

        try {
            const receiptRes = await window.MentryPush.getPushReceipt(activeTestId);
            const cacheR = receiptRes.cacheReceipt;
            const serverR = receiptRes.serverReceipt;

            const r = serverR || cacheR;

            if (r && (r.testId === activeTestId || !activeTestId)) {
                // sw.js executed!
                document.getElementById('layer2Status').textContent = 'PASS';
                document.getElementById('layer2Status').className = 'font-bold text-emerald-400 mt-0.5';

                document.getElementById('layer3Status').textContent = 'PASS';
                document.getElementById('layer3Status').className = 'font-bold text-emerald-400 mt-0.5';

                if (r.showNotificationSuccess) {
                    document.getElementById('layer4Status').textContent = 'PASS';
                    document.getElementById('layer4Status').className = 'font-bold text-emerald-400 mt-0.5';
                } else {
                    document.getElementById('layer4Status').textContent = 'FAIL';
                    document.getElementById('layer4Status').className = 'font-bold text-rose-400 mt-0.5';
                }

                document.getElementById('receiptDetail').innerHTML = `
                    <div class="text-emerald-400 font-bold text-sm">QUESTION A RESULT: YES — sw.js DID receive the push event!</div>
                    <div class="text-slate-300">Received At: <span class="text-white">${r.receivedAt}</span></div>
                    <div class="text-slate-300">Test ID: <span class="text-white">${r.testId}</span></div>
                    <div class="text-slate-300">showNotification(): <span class="${r.showNotificationSuccess ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'}">${r.showNotificationSuccess ? 'SUCCESS' : 'THREW: ' + r.showNotificationError}</span></div>
                    <div class="text-slate-300">SW Scope: <span class="text-white">${r.swScope}</span></div>
                    <div class="text-[11px] text-amber-300 mt-2 font-sans">If showNotification() is SUCCESS but no popup appeared on your phone screen, the failure is strictly at Layer 5 (Android Notification Channel / OS Battery Restriction).</div>
                `;
            } else {
                // sw.js did NOT execute or receipt not received
                document.getElementById('layer2Status').textContent = 'UNKNOWN';
                document.getElementById('layer2Status').className = 'font-bold text-amber-400 mt-0.5';

                document.getElementById('layer3Status').textContent = 'FAIL / NO RECEIPT';
                document.getElementById('layer3Status').className = 'font-bold text-rose-400 mt-0.5';

                document.getElementById('layer4Status').textContent = 'NOT REACHED';
                document.getElementById('layer4Status').className = 'font-bold text-slate-500 mt-0.5';

                document.getElementById('receiptDetail').innerHTML = `
                    <div class="text-rose-400 font-bold text-sm">QUESTION A RESULT: NO receipt recorded for Test ID: ${activeTestId}</div>
                    <div class="text-slate-400 mt-1">sw.js did NOT run while backgrounded.</div>
                    <div class="text-slate-300 mt-1 font-sans">The failure point is BEFORE sw.js (FCM → Chrome or Chrome waking the Service Worker). Check:</div>
                    <ul class="list-disc list-inside text-[11px] text-slate-400 pl-1 mt-1 font-sans space-y-0.5">
                        <li>Chrome background battery is set to "Restricted" by Android OS</li>
                        <li>Subscription was created under an obsolete registration (run "Unregister obsolete & Re-subscribe" above)</li>
                        <li>Chrome background data is disabled in Android Settings</li>
                    </ul>
                `;
            }
        } catch (e) {
            document.getElementById('receiptDetail').textContent = 'Error checking receipt: ' + e.message;
        } finally {
            btn.disabled = false;
            btn.textContent = 'Check Receipt (Did sw.js execute?)';
        }
    }

    window.addEventListener('DOMContentLoaded', () => {
        generateNewTestId();
        runFullDiagnostic();
    });
    </script>
</body>
</html>
