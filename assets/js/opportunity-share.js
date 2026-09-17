(function() {
    'use strict';

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>'"]/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        }[character]));
    }

    function recordAnalytics(oppId, channel) {
        try {
            if (!oppId) return;
            const data = JSON.stringify({ opportunityId: oppId, channel: channel });
            if (navigator.sendBeacon) {
                const blob = new Blob([data], { type: 'application/json' });
                navigator.sendBeacon('/actions/share-analytics.php', blob);
            } else {
                fetch('/actions/share-analytics.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: data,
                    keepalive: true
                }).catch(() => {});
            }
        } catch (e) {}
    }

    function extractOppId(url) {
        try {
            const parsed = new URL(url, window.location.href);
            return parsed.searchParams.get('id') || '';
        } catch (e) {
            return '';
        }
    }

    function formatShareMessage(title, text, url) {
        if (text && text.includes('View details:')) {
            return text;
        }
        return `New Mentry Solutions Training Opportunity\n\n${title}\n\nView details:\n${url}`;
    }

    function showFallback(title, text, url, oppId) {
        const fullMessage = formatShareMessage(title, text, url);
        const encodedMessage = encodeURIComponent(fullMessage);
        const encodedUrl = encodeURIComponent(url);

        const links = [
            {
                name: 'WhatsApp',
                icon: 'chat',
                bg: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                href: `https://wa.me/?text=${encodedMessage}`,
                channel: 'whatsapp'
            },
            {
                name: 'Telegram',
                icon: 'send',
                bg: 'bg-sky-500 hover:bg-sky-600 text-white',
                href: `https://t.me/share/url?url=${encodedUrl}&text=${encodeURIComponent(title)}`,
                channel: 'telegram'
            },
            {
                name: 'LinkedIn',
                icon: 'work',
                bg: 'bg-blue-700 hover:bg-blue-800 text-white',
                href: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
                channel: 'linkedin'
            }
        ];

        const existing = document.getElementById('opportunityShareDialog');
        if (existing) existing.remove();

        const dialog = document.createElement('div');
        dialog.id = 'opportunityShareDialog';
        dialog.className = 'fixed inset-0 z-[1000] flex items-center justify-center bg-slate-950/70 backdrop-blur-xs p-4 animate-in fade-in duration-200';
        dialog.innerHTML = `
            <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4 text-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-orange-50 text-[#FE5E04] flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg">share</span>
                        </div>
                        <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Share Opportunity</h2>
                    </div>
                    <button type="button" data-share-close class="text-slate-400 hover:text-slate-600 p-1 rounded-xl transition-colors cursor-pointer" aria-label="Close">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-800 line-clamp-2">${escapeHtml(title)}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Share with fellow trainers and academic circles.</p>
                </div>
                <div class="grid grid-cols-1 gap-2.5 pt-1">
                    ${links.map(l => `
                        <a href="${l.href}" target="_blank" rel="noopener noreferrer" data-channel="${l.channel}" class="flex items-center justify-between px-4 py-2.5 rounded-2xl ${l.bg} font-bold text-xs shadow-xs transition-all cursor-pointer">
                            <span class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-base">${l.icon}</span>
                                <span>Share via ${l.name}</span>
                            </span>
                            <span class="material-symbols-outlined text-sm opacity-80">open_in_new</span>
                        </a>
                    `).join('')}
                    <button type="button" data-share-copy class="flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs py-2.5 px-4 rounded-2xl transition-all shadow-xs cursor-pointer">
                        <span class="material-symbols-outlined text-base">content_copy</span>
                        <span id="shareCopyBtnText">Copy Share Link</span>
                    </button>
                </div>
                <p data-share-status class="hidden text-center text-xs font-bold text-emerald-600"></p>
            </div>
        `;

        document.body.appendChild(dialog);

        dialog.querySelector('[data-share-close]').onclick = () => dialog.remove();
        dialog.onclick = (e) => { if (e.target === dialog) dialog.remove(); };

        dialog.querySelectorAll('[data-channel]').forEach(btn => {
            btn.onclick = () => {
                recordAnalytics(oppId, btn.getAttribute('data-channel'));
            };
        });

        const copyBtn = dialog.querySelector('[data-share-copy]');
        const copyText = dialog.querySelector('#shareCopyBtnText');
        const status = dialog.querySelector('[data-share-status]');

        copyBtn.onclick = async () => {
            try {
                await navigator.clipboard.writeText(url);
                recordAnalytics(oppId, 'copy_link');
                if (copyText) copyText.textContent = 'Link Copied!';
                if (status) {
                    status.textContent = '✓ Public link copied to clipboard';
                    status.classList.remove('hidden');
                }
                setTimeout(() => {
                    if (copyText) copyText.textContent = 'Copy Share Link';
                }, 2500);
            } catch (err) {
                if (status) {
                    status.textContent = url;
                    status.classList.remove('hidden');
                }
            }
        };
    }

    window.shareOpportunity = async function(title, url, text) {
        const absoluteUrl = url ? new URL(url, window.location.origin).href : window.location.href;
        const oppId = extractOppId(absoluteUrl);
        const fullMessage = formatShareMessage(title, text, absoluteUrl);

        if (navigator.share) {
            try {
                await navigator.share({
                    title: title || 'Mentry Training Opportunity',
                    text: fullMessage,
                    url: absoluteUrl
                });
                recordAnalytics(oppId, 'native_share');
                return;
            } catch (error) {
                if (error && error.name === 'AbortError') return;
            }
        }

        showFallback(title, text, absoluteUrl, oppId);
    };
})();