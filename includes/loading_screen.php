<?php
// includes/loading_screen.php - Premium Branded Mentry Loading Screen & Transition Preloader
?>
<!-- Mentry Global Loading Screen -->
<div id="mentryGlobalLoader" class="fixed inset-0 z-[99999] bg-gradient-to-b from-[#FFFFFF] via-[#F8FAFC] to-[#EFF6FF] flex flex-col items-center justify-between transition-opacity duration-300 opacity-100 pointer-events-auto select-none overflow-hidden" style="display: flex;">
    <!-- Ambient glowing light orbs -->
    <div class="absolute -top-28 -left-28 w-80 h-80 sm:w-96 sm:h-96 rounded-full bg-sky-200/30 blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/4 -right-20 w-72 h-72 rounded-full bg-blue-100/40 blur-3xl pointer-events-none"></div>
    <div class="absolute inset-0 m-auto w-80 h-80 sm:w-96 sm:h-96 rounded-full bg-sky-100/40 blur-2xl pointer-events-none"></div>

    <!-- Central Hero Branding & Spinner -->
    <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 text-center max-w-sm w-full min-h-0 pt-8 pb-4">
        <!-- Official Mentry Icon Logo with Person & Star -->
        <div class="relative flex items-center justify-center mb-1">
            <img src="/public/mentry.png" alt="Mentry Logo" class="w-28 h-28 sm:w-36 sm:h-36 object-contain drop-shadow-sm transition-transform hover:scale-105">
        </div>

        <!-- "Mentry Solutions" Title -->
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 mt-2 mb-2">
            <span class="text-[#0B1526]">Mentry</span> <span class="text-[#0284C7]">Solutions</span>
        </h1>

        <!-- Subtitle with side divider rules -->
        <div class="flex items-center justify-center gap-2.5 w-full max-w-[280px] sm:max-w-xs px-1 mb-8">
            <div class="h-px bg-slate-300/80 flex-1"></div>
            <div class="text-center shrink-0">
                <p class="text-[11px] sm:text-xs font-semibold text-slate-600 tracking-normal leading-tight">Managed Trainer Network</p>
                <p class="text-[11px] sm:text-xs font-semibold text-slate-600 tracking-normal leading-tight">& Professional Training Services</p>
            </div>
            <div class="h-px bg-slate-300/80 flex-1"></div>
        </div>

        <!-- Circular Spinner & Loading Text -->
        <div class="flex flex-col items-center justify-center gap-2.5 mt-2">
            <div class="w-9 h-9 rounded-full border-[3.5px] border-sky-100 border-t-[#0284C7] animate-spin"></div>
            <span id="mentryLoaderMessage" class="text-xs font-medium text-slate-400 tracking-wider">Loading...</span>
        </div>
    </div>

    <!-- Layered Bottom Wave Graphics with Motto -->
    <div class="relative w-full z-10 overflow-hidden leading-none select-none pointer-events-none mt-auto">
        <svg class="w-full h-36 sm:h-48 md:h-56 block" viewBox="0 0 1000 280" preserveAspectRatio="none">
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

        <!-- Bottom Centered Motto Text -->
        <div class="absolute bottom-4 sm:bottom-6 inset-x-0 text-center px-4">
            <p class="text-[9px] sm:text-[11px] font-bold text-white/90 tracking-[0.25em] uppercase drop-shadow-xs">
                LEARN &nbsp;&nbsp;•&nbsp;&nbsp; TEACH &nbsp;&nbsp;•&nbsp;&nbsp; GROW &nbsp;&nbsp;TOGETHER
            </p>
        </div>
    </div>
</div>

<script>
(function() {
    const loader = document.getElementById('mentryGlobalLoader');
    const msgEl = document.getElementById('mentryLoaderMessage');

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
        }, 280);
    };

    // Auto-hide when DOM is ready
    if (document.readyState === 'complete') {
        setTimeout(window.hideMentryLoader, 80);
    } else {
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(window.hideMentryLoader, 100);
        });
        window.addEventListener('load', () => {
            setTimeout(window.hideMentryLoader, 120);
        });
        // Strict safety fallback: never trap the screen if a remote font or analytics hangs
        setTimeout(window.hideMentryLoader, 800);
    }

    // Automatically hide on page restored from browser bfcache (Back/Forward)
    window.addEventListener('pageshow', (event) => {
        window.hideMentryLoader();
    });

    // Attach smooth transition trigger on link navigation
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (!link) return;
        const href = link.getAttribute('href');
        const target = link.getAttribute('target');
        const download = link.hasAttribute('download');

        // Ignore hash anchors, javascript links, downloads, new tabs, or external protocols
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:') || target === '_blank' || download) {
            return;
        }

        // Ignore if user held Ctrl, Cmd, or Shift to open in new tab
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;

        // Display loader with subtle delay to prevent flicker on instant cached loads
        setTimeout(() => {
            window.showMentryLoader("Loading page...");
        }, 60);
    });

    // Attach on form submissions
    document.addEventListener('submit', function(e) {
        if (e.target.getAttribute('target') === '_blank') return;
        window.showMentryLoader("Processing request...");
    });
})();
</script>
