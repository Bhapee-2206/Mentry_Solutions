// assets/js/opportunity-share.js - Authoritative Opportunity Share Experience
// Provides clean, professional WhatsApp, Telegram, LinkedIn, and Native Share previews.
// Strict rule: The opportunity URL appears ONLY ONCE. No duplication.

(function() {
    'use strict';

    const CANONICAL_DOMAIN = 'https://mentry-solutions.vercel.app';

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

    function extractOppId(rawUrl) {
        if (!rawUrl) return '';
        try {
            const parsed = new URL(rawUrl, CANONICAL_DOMAIN);
            return parsed.searchParams.get('id') || '';
        } catch (e) {
            const match = String(rawUrl).match(/[?&]id=([^&#]+)/);
            return match ? decodeURIComponent(match[1]) : '';
        }
    }

    function getCanonicalUrl(rawUrl, explicitId) {
        const id = explicitId || extractOppId(rawUrl);
        if (id) {
            return `${CANONICAL_DOMAIN}/opportunity-details.php?id=${encodeURIComponent(id)}`;
        }
        return `${CANONICAL_DOMAIN}/opportunities.php`;
    }

    /**
     * Builds structured clean message parts:
     * - fullMessage: for WhatsApp direct link (contains the URL once at the bottom)
     * - bodyWithoutUrl: for native navigator.share and Telegram text (where URL is passed separately)
     */
    function formatMessageParts(title, textOrObj, canonicalUrl) {
        let cleanTitle = (title || 'Technical Training Opportunity').trim();
        let location = '';
        let dates = '';
        let rate = '';

        if (typeof textOrObj === 'object' && textOrObj !== null) {
            cleanTitle = (textOrObj.title || cleanTitle).trim();
            location = (textOrObj.location || '').trim();
            dates = (textOrObj.dates || '').trim();
            rate = (textOrObj.rate || '').trim();
        } else if (typeof textOrObj === 'string' && textOrObj.trim().length > 0) {
            const lines = textOrObj.split('\n').map(l => l.trim()).filter(Boolean);
            for (const line of lines) {
                if (line.startsWith('📍')) {
                    location = line.replace(/^📍\s*/, '').trim();
                } else if (line.startsWith('Location:')) {
                    location = line.replace(/^Location:\s*/i, '').trim();
                } else if (line.startsWith('📅')) {
                    dates = line.replace(/^📅\s*/, '').trim();
                } else if (line.startsWith('Dates:')) {
                    dates = line.replace(/^Dates:\s*/i, '').trim();
                } else if (line.startsWith('💰')) {
                    rate = line.replace(/^💰\s*/, '').trim();
                } else if (line.startsWith('Remuneration:')) {
                    rate = line.replace(/^Remuneration:\s*/i, '').trim();
                } else if (!line.startsWith('📢') && !line.startsWith('New Mentry') && !line.startsWith('View full') && !line.startsWith('http')) {
                    if (!cleanTitle || cleanTitle === 'Technical Training Opportunity' || cleanTitle === 'Training Opportunity') {
                        cleanTitle = line;
                    }
                }
            }
        }

        const bodyLines = [
            '📢 New Mentry Solutions Training Opportunity',
            '',
            cleanTitle
        ];
        if (location) bodyLines.push(`📍 ${location}`);
        if (dates) bodyLines.push(`📅 ${dates}`);
        if (rate) bodyLines.push(`💰 ${rate}`);

        bodyLines.push('');
        bodyLines.push('View full details and apply here 👇');

        const bodyWithoutUrl = bodyLines.join('\n');
        const fullMessage = `${bodyWithoutUrl}\n${canonicalUrl}`;

        return {
            title: cleanTitle,
            location: location,
            dates: dates,
            rate: rate,
            bodyWithoutUrl: bodyWithoutUrl,
            fullMessage: fullMessage
        };
    }

    function showShareModal(msgParts, canonicalUrl, oppId) {
        const encodedFullMessage = encodeURIComponent(msgParts.fullMessage);
        const encodedUrl = encodeURIComponent(canonicalUrl);
        const encodedBodyWithoutUrl = encodeURIComponent(msgParts.bodyWithoutUrl);

        const links = [
            {
                name: 'WhatsApp',
                icon: 'chat',
                bg: 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-700/20',
                href: `https://wa.me/?text=${encodedFullMessage}`,
                channel: 'whatsapp'
            },
            {
                name: 'Telegram',
                icon: 'send',
                bg: 'bg-sky-500 hover:bg-sky-600 text-white shadow-sky-600/20',
                href: `https://t.me/share/url?url=${encodedUrl}&text=${encodedBodyWithoutUrl}`,
                channel: 'telegram'
            },
            {
                name: 'LinkedIn',
                icon: 'work',
                bg: 'bg-[#0077b5] hover:bg-[#005885] text-white shadow-blue-800/20',
                href: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
                channel: 'linkedin'
            }
        ];

        const existing = document.getElementById('opportunityShareDialog');
        if (existing) existing.remove();

        const dialog = document.createElement('div');
        dialog.id = 'opportunityShareDialog';
        dialog.className = 'fixed inset-0 z-[1000] flex items-center justify-center bg-slate-950/75 backdrop-blur-xs p-4 animate-in fade-in duration-200';
        dialog.innerHTML = `
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4 text-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-teal-50 text-teal-700 flex items-center justify-center font-bold">
                            <span class="material-symbols-outlined text-xl">share</span>
                        </div>
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Share Opportunity</h2>
                            <p class="text-[11px] text-slate-400">Professional rich preview card</p>
                        </div>
                    </div>
                    <button type="button" data-share-close class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl transition-colors cursor-pointer" aria-label="Close">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                <!-- Rich Preview Card Mockup -->
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-3.5 space-y-2 text-left shadow-2xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-teal-800 bg-emerald-100/70 border border-emerald-300/60 px-2 py-0.5 rounded-full">
                            TRAINING OPPORTUNITY
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">mentry-solutions.vercel.app</span>
                    </div>
                    <p class="text-xs font-black text-slate-900 line-clamp-2 leading-snug">${escapeHtml(msgParts.title)}</p>
                    <div class="text-[11px] text-slate-600 space-y-0.5 pt-0.5">
                        ${msgParts.location ? `<div class="flex items-center gap-1.5"><span class="text-xs">📍</span><span>${escapeHtml(msgParts.location)}</span></div>` : ''}
                        ${msgParts.dates ? `<div class="flex items-center gap-1.5"><span class="text-xs">📅</span><span>${escapeHtml(msgParts.dates)}</span></div>` : ''}
                        ${msgParts.rate ? `<div class="flex items-center gap-1.5 font-bold text-emerald-700"><span class="text-xs">💰</span><span>${escapeHtml(msgParts.rate)}</span></div>` : ''}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-2 pt-1">
                    ${links.map(l => `
                        <a href="${l.href}" target="_blank" rel="noopener noreferrer" data-channel="${l.channel}" class="flex items-center justify-between px-4 py-2.5 rounded-2xl ${l.bg} font-bold text-xs shadow-sm transition-all cursor-pointer">
                            <span class="flex items-center gap-2.5">
                                <span class="material-symbols-outlined text-base">${l.icon}</span>
                                <span>Share via ${l.name}</span>
                            </span>
                            <span class="material-symbols-outlined text-sm opacity-75">open_in_new</span>
                        </a>
                    `).join('')}
                    <button type="button" data-share-copy class="flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs py-2.5 px-4 rounded-2xl transition-all shadow-xs cursor-pointer">
                        <span class="material-symbols-outlined text-base">content_copy</span>
                        <span id="shareCopyBtnText">Copy Canonical Public Link</span>
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
                await navigator.clipboard.writeText(canonicalUrl);
                recordAnalytics(oppId, 'copy_link');
                if (copyText) copyText.textContent = 'Link Copied!';
                if (status) {
                    status.textContent = '✓ Public link copied to clipboard';
                    status.classList.remove('hidden');
                }
                setTimeout(() => {
                    if (copyText) copyText.textContent = 'Copy Canonical Public Link';
                }, 2500);
            } catch (err) {
                if (status) {
                    status.textContent = canonicalUrl;
                    status.classList.remove('hidden');
                }
            }
        };
    }

    /**
     * Primary Global Sharing Function.
     * Invoked from opportunity cards and detail modal.
     */
    window.shareOpportunity = async function(title, url, text) {
        const oppId = extractOppId(url);
        const canonicalUrl = getCanonicalUrl(url, oppId);
        const msgParts = formatMessageParts(title, text, canonicalUrl);

        // Native share handler: pass clean body text WITHOUT duplicating the URL
        if (navigator.share) {
            try {
                await navigator.share({
                    title: msgParts.title,
                    text: msgParts.bodyWithoutUrl,
                    url: canonicalUrl
                });
                recordAnalytics(oppId, 'native_share');
                return;
            } catch (error) {
                if (error && error.name === 'AbortError') return;
            }
        }

        showShareModal(msgParts, canonicalUrl, oppId);
    };
})();