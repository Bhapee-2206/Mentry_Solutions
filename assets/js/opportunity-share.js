(function() {
    'use strict';

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        }[character]));
    }

    function showFallback(title, text, url) {
        const encoded = encodeURIComponent(`${text}\n${url}`);
        const links = [
            ['WhatsApp', `https://wa.me/?text=${encoded}`],
            ['Telegram', `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`],
            ['LinkedIn', `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`]
        ];
        const existing = document.getElementById('opportunityShareDialog');
        if (existing) existing.remove();
        const dialog = document.createElement('div');
        dialog.id = 'opportunityShareDialog';
        dialog.className = 'fixed inset-0 z-[1000] flex items-center justify-center bg-slate-950/60 p-4';
        dialog.innerHTML = `<div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl"><div class="flex items-center justify-between gap-3"><h2 class="text-base font-bold text-slate-900">Share opportunity</h2><button type="button" data-share-close class="text-slate-500" aria-label="Close share dialog">&times;</button></div><p class="mt-2 text-xs text-slate-500">${escapeHtml(title)}</p><div class="mt-4 grid gap-2">${links.map(([label, href]) => `<a class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" target="_blank" rel="noopener" href="${href}">${label}</a>`).join('')}<button type="button" data-share-copy class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Copy Link</button></div><p data-share-status class="mt-3 text-xs text-slate-500"></p></div>`;
        document.body.appendChild(dialog);
        dialog.querySelector('[data-share-close]').onclick = () => dialog.remove();
        dialog.querySelector('[data-share-copy]').onclick = async () => {
            try { await navigator.clipboard.writeText(url); dialog.querySelector('[data-share-status]').textContent = 'Link copied.'; }
            catch (error) { dialog.querySelector('[data-share-status]').textContent = url; }
        };
    }

    window.shareOpportunity = async function(title, url, text) {
        const shareText = text || `Explore this opportunity on Mentry Solutions: ${title}`;
        if (navigator.share) {
            try { await navigator.share({ title, text: shareText, url }); return; }
            catch (error) { if (error && error.name === 'AbortError') return; }
        }
        showFallback(title, shareText, url);
    };
})();