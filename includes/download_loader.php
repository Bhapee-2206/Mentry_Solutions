<?php
// includes/download_loader.php - Instant Mobile & Desktop Download Feedback Overlay
?>
<!-- Global Download Loading Overlay (Touch-friendly, mobile-responsive, zero dependencies) -->
<div id="mentryDownloadOverlay" class="fixed inset-0 z-[99999] bg-slate-950/75 backdrop-blur-sm flex items-center justify-center hidden opacity-0 transition-opacity duration-200 pointer-events-none" style="touch-action: none;" role="dialog" aria-modal="true" aria-labelledby="mentryDownloadTitle">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-sm w-[90%] mx-auto shadow-2xl border border-slate-100 text-center transform scale-95 transition-transform duration-200" id="mentryDownloadCard">
        <div class="relative w-16 h-16 mx-auto mb-4 flex items-center justify-center">
            <!-- Outer spinning gradient ring -->
            <div class="absolute inset-0 rounded-full border-4 border-orange-100 border-t-[#FE5E04] border-r-[#FE5E04] animate-spin"></div>
            <!-- Inner download pulse icon -->
            <span class="material-symbols-outlined text-[#FE5E04] text-2xl animate-pulse">download</span>
        </div>
        
        <h3 class="text-base sm:text-lg font-black text-slate-900 mb-1.5 tracking-tight" id="mentryDownloadTitle">
            Downloading Document...
        </h3>
        <p class="text-xs text-slate-500 leading-relaxed mb-4" id="mentryDownloadMessage">
            Preparing verified assets and generating official dossier. Your file download will begin shortly.
        </p>
        
        <!-- Animated loader progress bar -->
        <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3.5">
            <div class="h-full bg-gradient-to-r from-[#FE5E04] via-orange-400 to-[#FE5E04] rounded-full animate-pulse w-full"></div>
        </div>

        <button type="button" onclick="hideDownloadLoading()" class="text-[11px] font-bold text-slate-400 hover:text-slate-700 transition-colors uppercase tracking-wider py-1 px-3 rounded-lg hover:bg-slate-100 cursor-pointer">
            Close
        </button>
    </div>
</div>

<script>
(function() {
    window.showDownloadLoading = function(title, message) {
        const overlay = document.getElementById('mentryDownloadOverlay');
        const card = document.getElementById('mentryDownloadCard');
        const titleEl = document.getElementById('mentryDownloadTitle');
        const msgEl = document.getElementById('mentryDownloadMessage');
        
        if (overlay && card) {
            if (title && titleEl) titleEl.textContent = title;
            if (message && msgEl) msgEl.textContent = message;
            
            overlay.classList.remove('hidden');
            overlay.classList.remove('pointer-events-none');
            // Force browser repaint to trigger CSS animation smoothly on mobile
            void overlay.offsetWidth;
            overlay.classList.remove('opacity-0');
            overlay.classList.add('opacity-100');
            card.classList.remove('scale-95');
            card.classList.add('scale-100');
            
            // Auto-dismiss after 3.5s so UI is never stuck even on silent mobile downloads
            clearTimeout(window._mentryDlTimeout);
            window._mentryDlTimeout = setTimeout(function() {
                window.hideDownloadLoading();
            }, 3500);
        }
    };

    window.hideDownloadLoading = function() {
        const overlay = document.getElementById('mentryDownloadOverlay');
        const card = document.getElementById('mentryDownloadCard');
        if (overlay && card) {
            overlay.classList.remove('opacity-100');
            overlay.classList.add('opacity-0');
            card.classList.remove('scale-100');
            card.classList.add('scale-95');
            setTimeout(function() {
                overlay.classList.add('hidden');
                overlay.classList.add('pointer-events-none');
            }, 220);
        }
    };

    // Global interceptor for all profile and document download links
    function attachDownloadListeners() {
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a[href*="download-trainer-profile.php"], a[href*="download-document.php"], a[data-download-loader]');
            if (link) {
                const href = link.getAttribute('href') || '';
                let title = 'Downloading Profile...';
                let msg = 'Generating verified trainer profile dossier. Please wait...';
                
                if (href.includes('pic') || href.includes('photo') || href.includes('avatar')) {
                    title = 'Downloading Trainer Photo...';
                    msg = 'Fetching high-resolution verified profile photo...';
                } else if (href.includes('download-document.php')) {
                    title = 'Downloading Document...';
                    msg = 'Preparing document for download...';
                }
                
                window.showDownloadLoading(title, msg);
            }
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachDownloadListeners);
    } else {
        attachDownloadListeners();
    }
})();
</script>
