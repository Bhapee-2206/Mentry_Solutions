<?php
// includes/loading_screen.php - Premium Branded Mentry Loading Screen & Transition Preloader
// Active for both Website and Standalone PWA users.
if (file_exists(__DIR__ . '/offline_popup.php')) include_once __DIR__ . '/offline_popup.php';
?>
<!-- Mentry Global Loading Screen (Active for Website & PWA) -->
<div id="mentryGlobalLoader" class="fixed inset-0 z-[99999] bg-gradient-to-b from-[#FFFFFF] via-[#F8FBFF] to-[#EFF6FF] flex flex-col items-center justify-between transition-opacity duration-300 opacity-100 select-none overflow-hidden" style="display: flex;">
    <!-- Ambient glowing light orbs and subtle dots -->
    <div class="absolute -top-20 -left-20 w-80 h-80 sm:w-96 sm:h-96 rounded-full bg-orange-100/40 blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/4 -right-16 w-72 h-72 rounded-full bg-blue-100/50 blur-3xl pointer-events-none"></div>
    <div class="absolute inset-0 m-auto w-80 h-80 sm:w-96 sm:h-96 rounded-full bg-slate-50/60 blur-2xl pointer-events-none"></div>

    <!-- Central Hero Branding & Spinner -->
    <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 text-center max-w-md w-full min-h-0 pt-6 pb-2">
        <!-- Authentic Mentry PNG Logo -->
        <div class="relative flex items-center justify-center mb-3">
            <img src="/public/mentry.png" alt="Mentry Logo" class="h-20 sm:h-24 w-auto object-contain drop-shadow-md transition-transform hover:scale-105" onerror="this.onerror=null;this.src='/public/mentry-emblem.png';">
        </div>

        <!-- "Mentry Solutions" Title -->
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-[#0B1526] mt-1 mb-2">
            <span class="text-[#0B1526]">MENTRY</span> <span class="text-[#FE5E04]">Solutions</span>
        </h1>

        <!-- Subtitle with side divider rules -->
        <div class="flex items-center justify-center gap-3 w-full max-w-[320px] sm:max-w-sm px-2 mb-6">
            <div class="h-0.5 bg-[#FE5E04]/25 flex-1 rounded-full"></div>
            <div class="text-center shrink-0">
                <p class="text-xs sm:text-[13px] font-bold text-[#182A4A] tracking-wide leading-tight">Managed Trainer Network</p>
                <p class="text-[11px] sm:text-xs font-semibold text-slate-500 tracking-wide leading-tight mt-0.5">&amp; Professional Training Services</p>
            </div>
            <div class="h-0.5 bg-[#FE5E04]/25 flex-1 rounded-full"></div>
        </div>

        <!-- Circular Spinner & Loading Text -->
        <div class="flex flex-col items-center justify-center gap-3 mt-2">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full border-[3.5px] border-orange-100 border-t-[#FE5E04] border-r-[#FF7D1A] animate-spin"></div>
            <span id="mentryLoaderMessage" class="text-xs sm:text-sm font-bold text-[#182A4A] tracking-[0.2em] uppercase">Loading...</span>
        </div>
    </div>

    <!-- Layered Bottom Wave Graphics with Motto -->
    <div class="relative w-full z-10 overflow-hidden leading-none select-none pointer-events-none mt-auto">
        <svg class="w-full h-28 sm:h-36 md:h-44 block" viewBox="0 0 1000 280" preserveAspectRatio="none">
            <defs>
                <linearGradient id="waveCyanGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#FE5E04" />
                    <stop offset="50%" stop-color="#FF7D1A" />
                    <stop offset="100%" stop-color="#0284C7" />
                </linearGradient>
                <linearGradient id="waveRoyalGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#0284C7" />
                    <stop offset="60%" stop-color="#0052CC" />
                    <stop offset="100%" stop-color="#182A4A" />
                </linearGradient>
                <linearGradient id="waveNavyGrad" x1="0%" y1="0%" x2="50%" y2="100%">
                    <stop offset="0%" stop-color="#182A4A" />
                    <stop offset="40%" stop-color="#0F1B30" />
                    <stop offset="100%" stop-color="#080F1D" />
                </linearGradient>
            </defs>

            <!-- Layer 1: Orange/Cyan Accent Top Wave -->
            <path d="M0,130 C220,70 440,220 720,150 C860,110 950,150 1000,130 L1000,280 L0,280 Z" fill="url(#waveCyanGrad)" opacity="0.9"></path>

            <!-- Layer 2: Royal Blue Middle Wave -->
            <path d="M0,165 C250,110 490,240 770,180 C890,150 960,180 1000,165 L1000,280 L0,280 Z" fill="url(#waveRoyalGrad)" opacity="0.95"></path>

            <!-- Layer 3: Deep Navy Base Wave -->
            <path d="M0,200 C280,150 530,265 830,215 C920,195 970,215 1000,205 L1000,280 L0,280 Z" fill="url(#waveNavyGrad)"></path>
        </svg>

        <!-- Bottom Centered Motto Text -->
        <div class="absolute bottom-4 sm:bottom-5 inset-x-0 text-center px-4">
            <p class="text-[10px] sm:text-[11px] font-bold text-white/95 tracking-[0.25em] uppercase drop-shadow-sm">
                LEARN &nbsp;&nbsp;•&nbsp;&nbsp; TEACH &nbsp;&nbsp;•&nbsp;&nbsp; GROW &nbsp;&nbsp;TOGETHER
            </p>
        </div>
    </div>
</div>

<script>
(function() {
    const loader = document.getElementById('mentryGlobalLoader');
    const msgEl = document.getElementById('mentryLoaderMessage');
    if (!loader) return;

    // Detect if standalone PWA or standard browser
    let isPwa = false;
    try {
        const isStandaloneMatch = window.matchMedia && (
            window.matchMedia('(display-mode: standalone)').matches ||
            window.matchMedia('(display-mode: window-controls-overlay)').matches ||
            window.matchMedia('(display-mode: fullscreen)').matches ||
            window.matchMedia('(display-mode: minimal-ui)').matches
        );
        const isIosStandalone = window.navigator && window.navigator.standalone === true;
        const isUrlPwa = new URLSearchParams(window.location.search).get('source') === 'pwa';
        const isReferrerPwa = document.referrer && document.referrer.indexOf('android-app://') !== -1;
        const isSessionPwa = sessionStorage.getItem('mentry_app_installed_pwa') === '1';

        if (isStandaloneMatch || isIosStandalone || isUrlPwa || isReferrerPwa || isSessionPwa) {
            isPwa = true;
            sessionStorage.setItem('mentry_app_installed_pwa', '1');
        }
    } catch(e) {
        isPwa = false;
    }

    const splashStartTime = Date.now();
    // Timing: On fresh visits ~2000ms. On page navigation ~1200ms
    const isFirstVisit = !sessionStorage.getItem('mentry_visited_session');
    sessionStorage.setItem('mentry_visited_session', '1');
    const MIN_SPLASH_DISPLAY_MS = isFirstVisit ? 2000 : 1200;

    window.showMentryLoader = function(msg) {
        if (!loader) return;
        if (msg && msgEl) msgEl.textContent = msg;
        loader.style.display = 'flex';
        requestAnimationFrame(() => {
            loader.classList.remove('opacity-0', 'pointer-events-none');
            loader.classList.add('opacity-100', 'pointer-events-auto');
        });
    };

    window.hideMentryLoader = function() {
        if (!loader) return;
        loader.classList.remove('opacity-100', 'pointer-events-auto');
        loader.classList.add('opacity-0', 'pointer-events-none');
        setTimeout(() => {
            if (loader && loader.classList.contains('opacity-0')) {
                loader.style.display = 'none';
            }
        }, 320);
    };

    // If opened with no network connection: dismiss splash and show offline modal immediately
    if (!navigator.onLine) {
        setTimeout(() => {
            window.hideMentryLoader();
            if (window.showOfflineModal) {
                window.showOfflineModal();
            }
        }, 500);
        return;
    }

    function dismissSplashWithMinimumDelay() {
        const elapsed = Date.now() - splashStartTime;
        const remaining = Math.max(0, MIN_SPLASH_DISPLAY_MS - elapsed);
        setTimeout(window.hideMentryLoader, remaining);
    }

    // Auto-hide after minimum display duration so user can actually enjoy the branded splash screen
    if (document.readyState === 'complete') {
        dismissSplashWithMinimumDelay();
    } else {
        window.addEventListener('load', dismissSplashWithMinimumDelay);
        // Safety timeout in case external assets or scripts hang
        setTimeout(dismissSplashWithMinimumDelay, MIN_SPLASH_DISPLAY_MS + 800);
    }

    // Auto-hide on bfcache restore (browser back/forward buttons)
    window.addEventListener('pageshow', () => {
        window.hideMentryLoader();
    });

    // Smooth page transitions for internal links
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (!link) return;
        const href = link.getAttribute('href');
        const target = link.getAttribute('target');
        const download = link.hasAttribute('download');

        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:') || target === '_blank' || download) {
            return;
        }
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;

        // If offline when clicking a link: prevent navigation hang and show offline modal
        if (!navigator.onLine) {
            e.preventDefault();
            if (window.showOfflineModal) {
                window.showOfflineModal();
            }
            return;
        }

        setTimeout(() => {
            window.showMentryLoader("Loading...");
        }, 60);
    });

    // Smooth transitions on form submissions
    document.addEventListener('submit', function(e) {
        if (e.target.getAttribute('target') === '_blank') return;

        if (!navigator.onLine) {
            e.preventDefault();
            if (window.showOfflineModal) {
                window.showOfflineModal();
            }
            return;
        }

        window.showMentryLoader("Processing...");
    });
})();
</script>
