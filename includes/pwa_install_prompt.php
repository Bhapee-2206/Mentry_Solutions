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
            <button type="button" onclick="dismissPwaBanner(true)" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs py-2.5 px-3 rounded-xl transition-colors">
                Later
            </button>
        </div>
    </div>
</div>

<!-- iOS Safari "Add to Home Screen" Instructions Modal -->
<div id="mentryIosModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm hidden items-end sm:items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-sm w-full text-white shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white rounded-xl p-1 shrink-0 border border-slate-200">
                    <img src="/public/mentry.png" alt="Mentry" class="w-full h-full object-contain">
                </div>
                <div>
                    <h3 class="font-extrabold text-sm text-white">Install Mentry on iPhone / iPad</h3>
                    <p class="text-[11px] text-slate-400">Add to Home Screen in 3 steps</p>
                </div>
            </div>
            <button type="button" onclick="closeIosModal()" class="text-slate-400 hover:text-white p-1">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <div class="space-y-3 text-xs text-slate-300">
            <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-800/70 border border-slate-700/60">
                <div class="w-7 h-7 rounded-lg bg-blue-500/20 text-blue-400 flex items-center justify-center font-bold text-xs shrink-0">1</div>
                <div class="flex-1">
                    Tap the <strong class="text-white font-semibold">Share</strong> button in Safari's toolbar:
                    <span class="inline-flex items-center align-middle mx-1 text-blue-400">
                        <span class="material-symbols-outlined text-[18px]">ios_share</span>
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-800/70 border border-slate-700/60">
                <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-xs shrink-0">2</div>
                <div class="flex-1">
                    Scroll down and tap <strong class="text-white font-semibold">"Add to Home Screen"</strong>:
                    <span class="inline-flex items-center align-middle mx-1 text-amber-400">
                        <span class="material-symbols-outlined text-[18px]">add_box</span>
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-800/70 border border-slate-700/60">
                <div class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs shrink-0">3</div>
                <div class="flex-1">
                    Tap <strong class="text-white font-semibold">"Add"</strong> in the top-right corner. Mentry is now installed as an app!
                </div>
            </div>
        </div>

        <button type="button" onclick="closeIosModal()" class="w-full bg-[#FE5E04] hover:bg-[#e04e00] text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md">
            Got It!
        </button>
    </div>
</div>

<!-- Push Notification Permission Banner -->
<div id="mentryPushBanner" class="fixed top-20 right-4 md:right-6 max-w-sm w-[calc(100%-2rem)] z-40 bg-slate-900 text-white p-4 rounded-2xl border border-slate-800 shadow-xl backdrop-blur-md hidden select-none animate-in fade-in slide-in-from-top-4 duration-300">
    <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-[#FE5E04]/20 text-[#FE5E04] flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">notifications_active</span>
        </div>
        <div class="flex-1">
            <h4 class="font-bold text-xs text-white">Enable Real-Time Alerts</h4>
            <p class="text-[11px] text-slate-400 mt-0.5">Get instant notifications for new training opportunities, confirmed assignments, and urgent requirements.</p>
            <div class="flex items-center gap-2 mt-3">
                <button type="button" onclick="enablePushNotifications()" class="bg-[#FE5E04] hover:bg-[#e04e00] text-white font-bold text-[11px] px-3.5 py-1.5 rounded-lg transition-colors">
                    Enable Alerts
                </button>
                <button type="button" onclick="dismissPushBanner()" class="text-slate-400 hover:text-white font-semibold text-[11px] px-2 py-1.5">
                    Not Now
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let deferredPrompt = null;
    const banner = document.getElementById('mentryPwaBanner');
    const iosModal = document.getElementById('mentryIosModal');
    const pushBanner = document.getElementById('mentryPushBanner');

    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    // 1. Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .then((reg) => {
                    // Check push subscription after registration
                    checkPushPermission(reg);
                })
                .catch((err) => {
                    console.log('[Mentry PWA] SW registration notice:', err);
                });
        });
    }

    // 2. Capture Chrome/Android/Edge beforeinstallprompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        // Show banner after 3 seconds if not recently dismissed
        const dismissed = localStorage.getItem('mentry_pwa_dismissed');
        if (!dismissed && !isStandalone && banner) {
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
        if (!banner || isStandalone) return;
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
                }
                deferredPrompt = null;
            });
        } else {
            // Fallback for browsers without direct prompt access
            alert('To install Mentry on your device, tap your browser menu (⋮ or Share) and choose "Install App" or "Add to Home Screen".');
        }
    };

    window.closeIosModal = function() {
        if (iosModal) {
            iosModal.classList.add('hidden');
            iosModal.classList.remove('flex');
        }
    };

    // Public hook for button triggers (e.g. sidebar "Install Mobile App")
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

    // 3. Web Push Notification Logic
    function checkPushPermission(swReg) {
        if (!('Notification' in window)) return;

        if (Notification.permission === 'default') {
            const pushDismissed = localStorage.getItem('mentry_push_dismissed');
            if (!pushDismissed && pushBanner) {
                setTimeout(() => {
                    pushBanner.classList.remove('hidden');
                }, 5000);
            }
        } else if (Notification.permission === 'granted') {
            subscribeUserToPush(swReg);
        }
    }

    window.dismissPushBanner = function() {
        if (pushBanner) pushBanner.classList.add('hidden');
        localStorage.setItem('mentry_push_dismissed', Date.now().toString());
    };

    window.enablePushNotifications = function() {
        if (!('Notification' in window)) {
            alert('Push notifications are not supported by this browser.');
            return;
        }

        Notification.requestPermission().then((permission) => {
            if (pushBanner) pushBanner.classList.add('hidden');
            if (permission === 'granted') {
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.ready.then((reg) => {
                        subscribeUserToPush(reg);
                        // Display welcome notification
                        reg.showNotification('Mentry Notifications Active! 🔔', {
                            body: 'You will now receive real-time alerts for opportunities and assignments.',
                            icon: '/public/mentry.png',
                            badge: '/public/mentry.png'
                        });
                    });
                }
            }
        });
    };

    function subscribeUserToPush(swReg) {
        if (!swReg || !swReg.pushManager) return;

        swReg.pushManager.getSubscription().then((existingSub) => {
            if (existingSub) {
                sendSubscriptionToServer(existingSub);
                return existingSub;
            }

            // Public application server key (VAPID) placeholder or default
            return swReg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlB64ToUint8Array('BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U')
            }).then((newSub) => {
                sendSubscriptionToServer(newSub);
            }).catch((err) => {
                // Browsers in dev or sandbox without VAPID will catch cleanly
                console.log('[Mentry Push] Subscription notice:', err.message);
            });
        });
    }

    function sendSubscriptionToServer(sub) {
        if (!sub) return;
        fetch('/actions/save-push-subscription.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(sub)
        }).catch(() => {});
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
})();
</script>
