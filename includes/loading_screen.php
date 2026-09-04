<?php
// includes/loading_screen.php - Premium Branded Mentry Loading Screen & Transition Preloader
?>
<!-- Mentry Global Loading Screen -->
<div id="mentryGlobalLoader" class="fixed inset-0 z-[99999] bg-slate-950/80 backdrop-blur-md flex flex-col items-center justify-center transition-opacity duration-300 opacity-100 pointer-events-auto" style="display: flex;">
    <div class="relative flex flex-col items-center justify-center space-y-5 px-6 text-center">
        <!-- Logo with Glow & Pulsing Ring -->
        <div class="relative flex items-center justify-center">
            <!-- Pulsing outer glow aura -->
            <div class="absolute w-24 h-24 rounded-full bg-[#FE5E04]/25 blur-xl animate-pulse"></div>
            
            <!-- Rotating gradient spinner border -->
            <div class="w-20 h-20 rounded-2xl p-[2px] bg-gradient-to-tr from-[#FE5E04] via-amber-400 to-transparent animate-spin">
                <div class="w-full h-full bg-slate-900 rounded-[14px]"></div>
            </div>

            <!-- Central Brand Logo -->
            <div class="absolute inset-0 m-auto w-14 h-14 bg-white rounded-xl shadow-lg p-1.5 flex items-center justify-center overflow-hidden border border-slate-200">
                <img src="/public/mentry.png" alt="Mentry Logo" class="w-full h-full object-contain">
            </div>
        </div>

        <!-- Brand Loading Text -->
        <div class="space-y-1.5">
            <h2 class="text-sm font-black tracking-wider uppercase text-white flex items-center justify-center gap-1.5">
                <span>Mentry Solutions</span>
            </h2>
            <p id="mentryLoaderMessage" class="text-xs font-semibold text-slate-400 animate-pulse">Loading workspace...</p>
        </div>

        <!-- Subtle Slim Progress Indicator -->
        <div class="w-36 h-1 bg-slate-800 rounded-full overflow-hidden border border-slate-700/50">
            <div class="h-full bg-gradient-to-r from-[#FE5E04] to-amber-400 rounded-full w-2/3 animate-[mentryLoaderBar_1.2s_ease-in-out_infinite]"></div>
        </div>
    </div>
</div>

<style>
@keyframes mentryLoaderBar {
    0% { transform: translateX(-100%); width: 30%; }
    50% { width: 70%; }
    100% { transform: translateX(250%); width: 40%; }
}
</style>

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
            if (loader.classList.contains('opacity-0')) {
                loader.style.display = 'none';
            }
        }, 320);
    };

    // Auto-hide when DOM is ready or after window load
    if (document.readyState === 'complete') {
        setTimeout(window.hideMentryLoader, 120);
    } else {
        window.addEventListener('load', () => {
            setTimeout(window.hideMentryLoader, 150);
        });
        // Strict safety fallback: never trap the screen if a remote font or script hangs
        setTimeout(window.hideMentryLoader, 1200);
    }

    // Automatically hide on page restored from bfcache
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

        // Display loader with subtle delay to prevent flicker on cached instant loads
        setTimeout(() => {
            window.showMentryLoader("Loading page...");
        }, 80);
    });

    // Attach on form submissions
    document.addEventListener('submit', function(e) {
        // Skip forms that open in new window
        if (e.target.getAttribute('target') === '_blank') return;
        window.showMentryLoader("Processing request...");
    });
})();
</script>
