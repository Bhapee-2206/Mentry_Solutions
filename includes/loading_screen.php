<?php
// includes/loading_screen.php - Premium Branded Mentry Loading Screen & Transition Preloader
// STRICTLY active for Standalone/Downloaded PWA users only. Regular browser visitors will not see this loading screen.
?>
<!-- Mentry Global Loading Screen (Strictly for standalone/downloaded PWA users) -->
<div id="mentryGlobalLoader" class="fixed inset-0 z-[99999] bg-gradient-to-b from-[#FFFFFF] via-[#F8FBFF] to-[#EFF6FF] flex flex-col items-center justify-between transition-opacity duration-300 opacity-0 pointer-events-none select-none overflow-hidden" style="display: none;">
    <!-- Ambient glowing light orbs and subtle dots -->
    <div class="absolute -top-20 -left-20 w-80 h-80 sm:w-96 sm:h-96 rounded-full bg-blue-100/50 blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/4 -right-16 w-72 h-72 rounded-full bg-sky-100/60 blur-3xl pointer-events-none"></div>
    <div class="absolute inset-0 m-auto w-80 h-80 sm:w-96 sm:h-96 rounded-full bg-blue-50/40 blur-2xl pointer-events-none"></div>

    <!-- Central Hero Branding & Spinner -->
    <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 text-center max-w-md w-full min-h-0 pt-6 pb-2">
        <!-- Authentic Mentry PNG Logo (Person + Star emblem with 3D gradient) -->
        <div class="relative flex items-center justify-center mb-2">
            <img src="/public/mentry-emblem.png" alt="Mentry Logo" class="w-28 h-28 sm:w-36 sm:h-36 object-contain drop-shadow-md transition-transform hover:scale-105">
        </div>

        <!-- "Mentry Solutions" Title -->
        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-[#0B1526] mt-1 mb-2">
            Mentry <span class="text-[#0284C7]">Solutions</span>
        </h1>

        <!-- Subtitle with side divider rules (Perfect horizontal alignment) -->
        <div class="flex items-center justify-center gap-3 w-full max-w-[320px] sm:max-w-sm px-2 mb-6">
            <div class="h-0.5 bg-[#A9C8E8] flex-1 rounded-full"></div>
            <div class="text-center shrink-0">
                <p class="text-xs sm:text-[13px] font-semibold text-[#38557F] tracking-wide leading-tight">Managed Trainer Network</p>
                <p class="text-xs sm:text-[13px] font-semibold text-[#38557F] tracking-wide leading-tight">& Professional Training Services</p>
            </div>
            <div class="h-0.5 bg-[#A9C8E8] flex-1 rounded-full"></div>
        </div>

        <!-- Circular Spinner & Loading Text (Sitting comfortably on clean background, never covered by waves) -->
        <div class="flex flex-col items-center justify-center gap-3 mt-3">
            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-full border-[4px] border-[#DBEEFF] border-t-[#087CFF] border-r-[#55C8F4] animate-spin"></div>
            <span id="mentryLoaderMessage" class="text-xs sm:text-sm font-semibold text-[#48688F] tracking-[0.2em] uppercase">Loading...</span>
        </div>
    </div>

    <!-- Layered Bottom Wave Graphics with Motto (Clean, responsive height, never overlaps content) -->
    <div class="relative w-full z-10 overflow-hidden leading-none select-none pointer-events-none mt-auto">
        <svg class="w-full h-32 sm:h-44 md:h-52 block" viewBox="0 0 1000 280" preserveAspectRatio="none">
            <defs>
                <linearGradient id="waveCyanGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#0284C7" />
                    <stop offset="50%" stop-color="#0077F5" />
                    <stop offset="100%" stop-color="#005BDB" />
                </linearGradient>
                <linearGradient id="waveRoyalGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#0052CC" />
                    <stop offset="60%" stop-color="#003D99" />
                    <stop offset="100%" stop-color="#082352" />
                </linearGradient>
                <linearGradient id="waveNavyGrad" x1="0%" y1="0%" x2="50%" y2="100%">
                    <stop offset="0%" stop-color="#082352" />
                    <stop offset="40%" stop-color="#051838" />
                    <stop offset="100%" stop-color="#030E24" />
                </linearGradient>
            </defs>

            <!-- Layer 1: Cyan / Vivid Blue Top Wave -->
            <path d="M0,130 C220,70 440,220 720,150 C860,110 950,150 1000,130 L1000,280 L0,280 Z" fill="url(#waveCyanGrad)" opacity="0.92"></path>

            <!-- Layer 2: Royal Blue Middle Wave -->
            <path d="M0,165 C250,110 490,240 770,180 C890,150 960,180 1000,165 L1000,280 L0,280 Z" fill="url(#waveRoyalGrad)" opacity="0.96"></path>

            <!-- Layer 3: Deep Navy Base Wave -->
            <path d="M0,200 C280,150 530,265 830,215 C920,195 970,215 1000,205 L1000,280 L0,280 Z" fill="url(#waveNavyGrad)"></path>
        </svg>

        <!-- Bottom Centered Motto Text (Safely padded inside navy base, never clipped) -->
        <div class="absolute bottom-4 sm:bottom-6 inset-x-0 text-center px-4">
            <p class="text-[10px] sm:text-[11px] font-bold text-white/95 tracking-[0.25em] uppercase drop-shadow-sm">
                LEARN &nbsp;&nbsp;•&nbsp;&nbsp; TEACH &nbsp;&nbsp;•&nbsp;&nbsp; GROW &nbsp;&nbsp;TOGETHER
            </p>
        </div>
    </div>
</div>

<script>
(function() {
    // 1. Strict PWA detection: Only users who installed/downloaded the app will see this loading screen
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

    const loader = document.getElementById('mentryGlobalLoader');
    const msgEl = document.getElementById('mentryLoaderMessage');

    // If NOT in standalone/installed PWA mode, completely deactivate loader and exit
    if (!isPwa) {
        window.showMentryLoader = function() {};
        window.hideMentryLoader = function() {
            if (loader) loader.style.display = 'none';
        };
        if (loader) loader.style.display = 'none';
        return; // Regular browser users will have completely instant zero-loader browsing!
    }

    // --- PWA MODE ONLY BELOW ---
    const splashStartTime = Date.now();
    const MIN_SPLASH_DISPLAY_MS = 2600; // Increased duration: keeps splash visible for ~2.6 seconds on launch!

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
        }, 300);
    };

    // Show initial splash loader in PWA on fresh page loads
    loader.style.display = 'flex';
    loader.classList.remove('opacity-0', 'pointer-events-none');
    loader.classList.add('opacity-100', 'pointer-events-auto');

    function dismissSplashWithMinimumDelay() {
        const elapsed = Date.now() - splashStartTime;
        const remaining = Math.max(0, MIN_SPLASH_DISPLAY_MS - elapsed);
        setTimeout(window.hideMentryLoader, remaining);
    }

    // Auto-hide after minimum display duration so user can actually see the branded splash screen
    if (document.readyState === 'complete') {
        dismissSplashWithMinimumDelay();
    } else {
        window.addEventListener('load', dismissSplashWithMinimumDelay);
        // Safety timeout in case load event hangs
        setTimeout(dismissSplashWithMinimumDelay, MIN_SPLASH_DISPLAY_MS + 800);
    }

    // Auto-hide on bfcache restore
    window.addEventListener('pageshow', () => {
        window.hideMentryLoader();
    });

    // PWA transitions for links
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

        setTimeout(() => {
            window.showMentryLoader("Loading...");
        }, 60);
    });

    // PWA transitions on form submissions
    document.addEventListener('submit', function(e) {
        if (e.target.getAttribute('target') === '_blank') return;
        window.showMentryLoader("Processing...");
    });
})();
</script>
