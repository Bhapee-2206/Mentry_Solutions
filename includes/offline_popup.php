<?php
// includes/offline_popup.php - Global Offline Network Monitor & Modal Alert
?>
<!-- Offline Alert Modal Backdrop -->
<div id="mentryOfflineBackdrop" class="fixed inset-0 z-[999999] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 transition-all duration-300 opacity-0 pointer-events-none select-none" style="display: none;">
    <!-- Centered Card -->
    <div id="mentryOfflineModalCard" class="bg-white rounded-3xl border border-slate-200/90 shadow-2xl max-w-sm w-full p-6 text-center space-y-4 transform scale-95 transition-all duration-300">
        <!-- Animated Icon Container with Pulse -->
        <div class="relative w-16 h-16 rounded-2xl bg-orange-50 border border-orange-200/70 flex items-center justify-center mx-auto text-[#FE5E04] shadow-xs">
            <span class="material-symbols-outlined text-[32px]">wifi_off</span>
            <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-[#FE5E04]"></span>
            </span>
        </div>

        <!-- Title & Description -->
        <div class="space-y-1.5">
            <h3 class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight">No Internet Connection</h3>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                You are currently offline. Please check your mobile data or Wi-Fi connection to continue using Mentry Solutions.
            </p>
        </div>

        <!-- Live Connection Status Badge -->
        <div class="flex items-center justify-center">
            <div id="mentryOfflineStatusBadge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200/80 transition-all">
                <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                <span id="mentryOfflineStatusText">Offline — Waiting for network</span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-2 flex flex-col gap-2">
            <!-- Retry Button -->
            <button id="mentryOfflineRetryBtn" type="button" onclick="window.checkMentryConnection(true)" class="w-full bg-gradient-to-r from-[#FE5E04] via-[#FF6A00] to-[#FF7D1A] hover:opacity-95 text-white font-bold text-sm py-3 px-4 rounded-2xl shadow-md shadow-orange-500/20 transition-all flex items-center justify-center gap-2 cursor-pointer">
                <span id="mentryRetryIcon" class="material-symbols-outlined text-[18px]">refresh</span>
                <span id="mentryRetryLabel">Retry Connection</span>
            </button>

            <!-- Dismiss / Offline Mode Button -->
            <button id="mentryOfflineDismissBtn" type="button" onclick="window.dismissOfflineModal()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs py-2.5 px-4 rounded-xl transition-colors cursor-pointer">
                Dismiss &amp; View Cached Pages
            </button>
        </div>
    </div>
</div>

<!-- Persistent Floating Pill Banner (Visible when modal is dismissed while offline) -->
<div id="mentryOfflineFloatingBanner" class="fixed top-4 inset-x-0 mx-auto max-w-md z-[999998] px-3 hidden pointer-events-auto select-none transition-all duration-300">
    <div class="bg-slate-900/95 text-white border border-slate-800 shadow-xl backdrop-blur-md rounded-2xl px-4 py-2.5 flex items-center justify-between gap-2.5">
        <div class="flex items-center gap-2 min-w-0">
            <span class="material-symbols-outlined text-[18px] text-[#FE5E04] shrink-0">wifi_off</span>
            <span class="text-xs font-semibold text-slate-200 truncate">You are offline — Some features may be unavailable</span>
        </div>
        <div class="flex items-center gap-1.5 shrink-0">
            <button type="button" onclick="window.checkMentryConnection(true)" class="px-2.5 py-1 rounded-lg bg-[#FE5E04] hover:bg-[#e04e00] text-white text-[11px] font-bold transition-colors cursor-pointer">
                Retry
            </button>
            <button type="button" onclick="window.showOfflineModal()" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors" title="View details">
                <span class="material-symbols-outlined text-[16px]">info</span>
            </button>
        </div>
    </div>
</div>

<!-- Reconnection Success Toast -->
<div id="mentryOnlineToast" class="fixed top-4 inset-x-0 mx-auto max-w-xs z-[999998] px-3 hidden opacity-0 -translate-y-2 pointer-events-auto select-none transition-all duration-300">
    <div class="bg-emerald-600 text-white shadow-xl rounded-2xl px-4 py-2.5 flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-[18px]">wifi</span>
        <span class="text-xs font-bold">Back Online! Connection restored.</span>
    </div>
</div>

<script>
(function() {
    const backdrop = document.getElementById('mentryOfflineBackdrop');
    const card = document.getElementById('mentryOfflineModalCard');
    const floatingBanner = document.getElementById('mentryOfflineFloatingBanner');
    const onlineToast = document.getElementById('mentryOnlineToast');
    const statusBadge = document.getElementById('mentryOfflineStatusBadge');
    const statusText = document.getElementById('mentryOfflineStatusText');
    const retryIcon = document.getElementById('mentryRetryIcon');
    const retryLabel = document.getElementById('mentryRetryLabel');

    let isOfflineModalOpen = false;
    let isChecking = false;
    let wasOffline = !navigator.onLine; // Only true if the user was genuinely offline
    let toastTimer = null;
    let onlineDebounceTimer = null;

    function showOnlineToast() {
        if (!onlineToast) return;
        if (toastTimer) clearTimeout(toastTimer);

        onlineToast.classList.remove('hidden');
        requestAnimationFrame(() => {
            onlineToast.classList.remove('opacity-0', '-translate-y-2');
            onlineToast.classList.add('opacity-100', 'translate-y-0');
        });

        toastTimer = setTimeout(() => {
            onlineToast.classList.remove('opacity-100', 'translate-y-0');
            onlineToast.classList.add('opacity-0', '-translate-y-2');
            setTimeout(() => {
                onlineToast.classList.add('hidden');
            }, 300);
        }, 3000);
    }

    // Open Offline Modal
    window.showOfflineModal = function() {
        if (!backdrop) return;
        isOfflineModalOpen = true;
        wasOffline = true;
        if (floatingBanner) floatingBanner.classList.add('hidden');

        // If loading splash screen is currently active, dismiss it so user isn't stuck on spinner
        if (window.hideMentryLoader) {
            window.hideMentryLoader();
        }

        backdrop.style.display = 'flex';
        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0', 'pointer-events-none');
            backdrop.classList.add('opacity-100', 'pointer-events-auto');
            if (card) {
                card.classList.remove('scale-95');
                card.classList.add('scale-100');
            }
        });
    };

    // Dismiss modal and show floating top banner
    window.dismissOfflineModal = function() {
        if (!backdrop) return;
        isOfflineModalOpen = false;
        backdrop.classList.remove('opacity-100', 'pointer-events-auto');
        backdrop.classList.add('opacity-0', 'pointer-events-none');
        if (card) {
            card.classList.remove('scale-100');
            card.classList.add('scale-95');
        }
        setTimeout(() => {
            if (!isOfflineModalOpen && backdrop) {
                backdrop.style.display = 'none';
            }
        }, 280);

        // Show non-intrusive floating banner if still offline
        if (!navigator.onLine && floatingBanner) {
            floatingBanner.classList.remove('hidden');
        }
    };

    // Actively test HTTP reachability to confirm real internet connection
    window.checkMentryConnection = function(isUserTriggered) {
        if (isChecking) return;
        isChecking = true;

        if (retryIcon) retryIcon.classList.add('animate-spin');
        if (retryLabel) retryLabel.textContent = 'Testing connection...';

        if (statusBadge && statusText) {
            statusBadge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200/80 transition-all';
            statusText.textContent = 'Testing connection...';
        }

        // Test with a quick cache-busted fetch
        const pingUrl = '/favicon.ico?_ping=' + Date.now();
        fetch(pingUrl, { method: 'HEAD', cache: 'no-store' })
            .then((response) => {
                isChecking = false;
                if (retryIcon) retryIcon.classList.remove('animate-spin');
                if (retryLabel) retryLabel.textContent = 'Retry Connection';

                if (response.ok || response.status > 0) {
                    handleConnectionRestored();
                } else {
                    handleConnectionFailed();
                }
            })
            .catch(() => {
                isChecking = false;
                if (retryIcon) retryIcon.classList.remove('animate-spin');
                if (retryLabel) retryLabel.textContent = 'Retry Connection';
                handleConnectionFailed();
            });
    };

    function handleConnectionRestored() {
        if (statusBadge && statusText) {
            statusBadge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80 transition-all';
            statusText.textContent = 'Connected! Reconnecting...';
        }

        if (retryLabel) retryLabel.textContent = 'Connected!';

        const shouldShowToast = wasOffline;
        wasOffline = false; // Reset state immediately so it will never repeat or blink

        // Smoothly close offline modal
        setTimeout(() => {
            window.dismissOfflineModal();
            if (floatingBanner) floatingBanner.classList.add('hidden');

            // Show green reconnected toast ONCE only if genuinely previously offline
            if (shouldShowToast) {
                showOnlineToast();
            }
        }, 400);
    }

    function handleConnectionFailed() {
        wasOffline = true; // Genuinely confirmed offline
        if (statusBadge && statusText) {
            statusBadge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200/80 transition-all';
            statusText.textContent = 'Still Offline — Network Unreachable';
        }

        // Shake card subtly
        if (card) {
            card.classList.add('animate-shake');
            setTimeout(() => card.classList.remove('animate-shake'), 400);
        }
    }

    // Event listener: browser offline event
    window.addEventListener('offline', function() {
        wasOffline = true;
        window.showOfflineModal();
    });

    // Event listener: browser online event (debounced to avoid multiple firings on mobile/Wi-Fi switch)
    window.addEventListener('online', function() {
        if (!wasOffline) return; // Ignore if user was never offline
        if (onlineDebounceTimer) clearTimeout(onlineDebounceTimer);
        onlineDebounceTimer = setTimeout(() => {
            window.checkMentryConnection(false);
        }, 500);
    });

    // Check initial connection state on load
    if (!navigator.onLine) {
        wasOffline = true;
        setTimeout(window.showOfflineModal, 150);
    }

    // Periodic heartbeat check ONLY when offline to auto-recover when user reconnects
    setInterval(function() {
        if (wasOffline && navigator.onLine && !isChecking) {
            window.checkMentryConnection(false);
        }
    }, 5000);
})();
</script>
