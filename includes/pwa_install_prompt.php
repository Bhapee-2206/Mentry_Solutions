<?php
// includes/pwa_install_prompt.php - Progressive Web App (A2HS) Prompt & Push Notification Controller

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

$isAdminContext = false;
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
if (stripos($reqUri, '/admin') !== false) {
    $isAdminContext = true;
}
if (!empty($_SESSION['user']['role']) && in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPER_ADMIN', 'STAFF'])) {
    $isAdminContext = true;
}
if (function_exists('isAdminOrStaff') && isAdminOrStaff()) {
    $isAdminContext = true;
}

if ($isAdminContext) {
    echo '<script>window.promptPWAInstall = function() { /* PWA disabled for Admin */ };</script>';
    return;
}

$pwaUserId = null;
if (!empty($_SESSION['user']['id'])) {
    $pwaUserId = (string)$_SESSION['user']['id'];
} elseif (!empty($_SESSION['user']['_id'])) {
    $pwaUserId = (string)$_SESSION['user']['_id'];
} elseif (!empty($_SESSION['user_id'])) {
    $pwaUserId = (string)$_SESSION['user_id'];
} elseif (function_exists('getCurrentUser')) {
    $u = getCurrentUser();
    if (!empty($u['id'])) $pwaUserId = (string)$u['id'];
}
?>
<!-- PWA Install Floating Banner -->
<div id="mentryPwaBanner" class="fixed bottom-4 left-4 right-4 md:left-auto md:right-6 md:w-96 z-50 transform translate-y-32 opacity-0 transition-all duration-300 pointer-events-none select-none">
    <div class="bg-slate-950/95 text-white p-4 rounded-2xl border border-slate-800/90 shadow-2xl backdrop-blur-xl flex flex-col gap-3">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-white rounded-xl p-1.5 shrink-0 shadow-md border border-slate-200">
                    <img src="/public/mentry.png" alt="Mentry" class="w-full h-full object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h4 class="font-extrabold text-sm text-white tracking-tight">Install Mentry App</h4>
                        <span class="bg-[#FE5E04] text-[10px] font-black uppercase px-1.5 py-0.5 rounded text-white">App</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5 leading-snug">Add to your Home Screen for instant 1-tap access & real-time training alerts.</p>
                </div>
            </div>
            <button type="button" onclick="dismissPwaBanner()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition-colors" aria-label="Close banner">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <div class="flex items-center gap-2 pt-1 border-t border-slate-800/80">
            <button type="button" onclick="triggerPwaInstall()" class="flex-1 bg-[#FE5E04] hover:bg-[#e04e00] text-white font-bold text-xs py-2.5 px-4 rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">install_mobile</span>
                <span>Install to Home Screen</span>
            </button>
            <button type="button" onclick="dismissPwaBanner(true)" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs py-2.5 px-3 rounded-xl transition-colors cursor-pointer">
                Not now
            </button>
        </div>
    </div>
</div>

<!-- iOS PWA Manual Instructions Modal -->
<div id="mentryIosModal" class="fixed inset-0 z-[100] bg-black/80 backdrop-blur-sm hidden items-end sm:items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-sm w-full p-6 text-white text-center space-y-4 shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-[#FE5E04]/20 border border-[#FE5E04]/30 text-[#FE5E04] flex items-center justify-center shadow-inner">
            <span class="material-symbols-outlined text-[28px]">ios_share</span>
        </div>
        <div>
            <h3 class="font-extrabold text-base text-white">Install Mentry on iPhone</h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">Follow these 2 quick steps in Safari to add Mentry to your Home Screen:</p>
        </div>
        <div class="bg-slate-950/70 rounded-2xl p-4 text-left text-xs space-y-3 border border-slate-800/70">
            <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded-full bg-[#FE5E04] text-white font-black text-[11px] flex items-center justify-center shrink-0">1</span>
                <span class="text-slate-200">Tap the <strong class="text-white">Share</strong> button <span class="material-symbols-outlined text-[14px] align-middle text-blue-400">ios_share</span> at the bottom of your Safari screen.</span>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded-full bg-[#FE5E04] text-white font-black text-[11px] flex items-center justify-center shrink-0">2</span>
                <span class="text-slate-200">Scroll down and tap <strong class="text-white">Add to Home Screen</strong> <span class="material-symbols-outlined text-[14px] align-middle text-emerald-400">add_box</span>.</span>
            </div>
        </div>
        <button type="button" onclick="closeIosModal()" class="w-full bg-[#FE5E04] hover:bg-[#e04e00] text-white font-bold text-xs py-3 rounded-xl transition-all cursor-pointer">
            Got it, thanks!
        </button>
    </div>
</div>

<!-- Push Notification Permission Banner -->
<div id="mentryPushBanner" class="fixed bottom-6 right-4 sm:right-6 max-w-sm w-[calc(100%-2rem)] z-[80] bg-slate-950/95 text-white p-5 rounded-3xl border border-slate-800/90 shadow-2xl backdrop-blur-xl hidden select-none animate-in fade-in slide-in-from-bottom-4 duration-300">
    <div class="flex items-start gap-3.5">
        <div class="w-11 h-11 rounded-2xl bg-[#FE5E04]/20 border border-[#FE5E04]/30 text-[#FE5E04] flex items-center justify-center shrink-0 shadow-sm">
            <span class="material-symbols-outlined text-[24px] animate-pulse">notifications_active</span>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
                <h4 class="font-extrabold text-sm text-white tracking-tight">🔔 Stay Updated</h4>
                <button type="button" onclick="dismissPushBanner()" class="text-slate-400 hover:text-white p-1 -mr-1 rounded-lg hover:bg-slate-800/60 transition-colors cursor-pointer" aria-label="Dismiss">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
            <p class="text-xs font-semibold text-slate-200 mt-1">Never Miss a Training Opportunity</p>
            <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">Get instant alerts for new opportunities, selections, interviews and important updates.</p>
            <div class="flex items-center gap-2 mt-4">
                <button type="button" onclick="enablePushNotifications()" class="flex-1 bg-[#FE5E04] hover:bg-[#e04e00] text-white font-bold text-xs py-2.5 px-3.5 rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">notifications</span>
                    <span>Enable Notifications</span>
                </button>
                <button type="button" onclick="dismissPushBanner()" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs py-2.5 px-3.5 rounded-xl transition-colors cursor-pointer">
                    Maybe later
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const MENTRY_CURRENT_USER_ID = <?= json_encode($pwaUserId) ?>;
    let deferredPrompt = null;
    let activeSwReg = null;
    const banner = document.getElementById('mentryPwaBanner');
    const iosModal = document.getElementById('mentryIosModal');
    const pushBanner = document.getElementById('mentryPushBanner');

    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    // Check if user already installed / downloaded the PWA
    function isAppAlreadyInstalled() {
        return isStandalone || localStorage.getItem('mentry_pwa_installed') === 'true';
    }

    // Query browser for installed related apps if API is supported
    if ('getInstalledRelatedApps' in navigator) {
        navigator.getInstalledRelatedApps().then((apps) => {
            if (apps && apps.length > 0) {
                localStorage.setItem('mentry_pwa_installed', 'true');
            }
        }).catch(() => {});
    }

    function getPwaBaseUrl() {
        const path = window.location.pathname;
        if (path.includes('/Mentry%20solution') || path.includes('/Mentry solution')) {
            return path.includes('/Mentry%20solution') ? '/Mentry%20solution' : '/Mentry solution';
        }
        return '';
    }

    // Service worker registration is handled authoritatively by push-notifications.js

    // 2. Capture Chrome/Android/Edge beforeinstallprompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        // If user already downloaded the PWA, DO NOT SHOW POPUP
        if (isAppAlreadyInstalled()) {
            return;
        }

        // Show banner after 3 seconds if not recently dismissed
        const dismissed = localStorage.getItem('mentry_pwa_dismissed');
        if (!dismissed && banner) {
            setTimeout(() => {
                showPwaBanner();
            }, 3000);
        }
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        if (banner) {
            banner.classList.add('translate-y-32', 'opacity-0', 'pointer-events-none');
            banner.classList.remove('translate-y-0', 'opacity-100', 'pointer-events-auto');
        }
        localStorage.setItem('mentry_pwa_installed', 'true');
    });

    window.showPwaBanner = function() {
        // Guarantee: never show download popup if user already installed the app
        if (!banner || isAppAlreadyInstalled()) return;
        banner.classList.remove('translate-y-32', 'opacity-0', 'pointer-events-none');
        banner.classList.add('translate-y-0', 'opacity-100', 'pointer-events-auto');
    };

    window.dismissPwaBanner = function(persist = false) {
        if (!banner) return;
        banner.classList.add('translate-y-32', 'opacity-0', 'pointer-events-none');
        banner.classList.remove('translate-y-0', 'opacity-100', 'pointer-events-auto');
        if (persist) {
            localStorage.setItem('mentry_pwa_dismissed', Date.now().toString());
        }
    };

    window.triggerPwaInstall = function() {
        if (isIos) {
            dismissPwaBanner();
            if (iosModal) {
                iosModal.classList.remove('hidden');
                iosModal.classList.add('flex');
            }
            return;
        }

        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    dismissPwaBanner();
                    localStorage.setItem('mentry_pwa_installed', 'true');
                }
                deferredPrompt = null;
            });
        } else {
            alert('To install Mentry on your device, tap your browser menu (⋮ or Share) and choose "Install App" or "Add to Home Screen".');
        }
    };

    window.closeIosModal = function() {
        if (iosModal) {
            iosModal.classList.add('hidden');
            iosModal.classList.remove('flex');
        }
    };

    // Public hook for button triggers (e.g. header download button)
    window.promptPWAInstall = function() {
        if (isStandalone) {
            alert('Mentry is already installed and running in App Mode!');
            return;
        }
        if (isIos) {
            if (iosModal) {
                iosModal.classList.remove('hidden');
                iosModal.classList.add('flex');
            }
        } else if (deferredPrompt) {
            triggerPwaInstall();
        } else {
            showPwaBanner();
        }
    };

    // 3. Web Push Notification Integration (OneSignal + Mentry Push)
    window.enablePushNotifications = async function() {
        if (pushBanner) {
            pushBanner.classList.add('hidden');
            pushBanner.classList.remove('block');
        }
        if (window.OneSignal) {
            try {
                await window.OneSignal.User.PushSubscription.optIn();
            } catch (e) {}
        }
        if (window.MentryPush && typeof window.MentryPush.enable === 'function') {
            const success = await window.MentryPush.enable();
            if (!success && Notification.permission === 'denied') {
                localStorage.setItem('mentry_push_dismissed', Date.now().toString());
            }
        }
    };

    window.showPushBanner = function(force = false) {
        if (!pushBanner) return;
        if (!('Notification' in window)) return;
        if (Notification.permission === 'granted' && !force) return;
        pushBanner.classList.remove('hidden');
        pushBanner.classList.add('block');
    };

    window.dismissPushBanner = function() {
        if (pushBanner) {
            pushBanner.classList.add('hidden');
            pushBanner.classList.remove('block');
        }
        localStorage.setItem('mentry_push_dismissed', Date.now().toString());
    };

    // Auto-prompt banner if default and not dismissed
    if ('Notification' in window && Notification.permission === 'default') {
        const pushDismissed = localStorage.getItem('mentry_push_dismissed');
        const dismissedTime = parseInt(pushDismissed || '0', 10);
        if (!pushDismissed || (Date.now() - dismissedTime > 24 * 60 * 60 * 1000)) {
            setTimeout(() => {
                showPushBanner();
            }, 3000);
        }
    }
})();
</script>

<!-- OneSignal Web Push SDK v16 -->
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
window.OneSignalDeferred = window.OneSignalDeferred || [];
OneSignalDeferred.push(async function(OneSignal) {
    try {
        await OneSignal.init({
            appId: "e2443de9-128c-4e5f-a964-03260aa8c627",
            safari_web_id: "web.onesignal.auto.16fe94fe-85b7-4f18-b294-6465f1482156",
            serviceWorkerParam: { scope: "/" },
            serviceWorkerPath: "sw.js"
        });

        const currentPwaUserId = "<?= htmlspecialchars($pwaUserId ?? '') ?>";
        if (currentPwaUserId) {
            await OneSignal.login(currentPwaUserId);
            await OneSignal.User.addTags({
                role: 'TRAINER',
                mentry_user_id: currentPwaUserId
            });
            console.log('[OneSignal] Trainer identity registered:', currentPwaUserId);
        }
    } catch (osErr) {
        console.warn('[OneSignal Init Note]', osErr);
    }
});
</script>
<script src="/assets/js/push-notifications.js" defer></script>
