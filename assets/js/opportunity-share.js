// assets/js/opportunity-share.js - Canonical Mentry Opportunity Sharing System
// Website + Mobile Website + PWA + Android WebView
// Strict Rules:
// 1. Opportunity URL appears exactly ONCE.
// 2. Main Mentry website URL appears exactly ONCE.
// 3. No URL duplication under any circumstances.
// 4. Always uses canonical domain: https://mentry-solutions.vercel.app
// 5. WhatsApp opens direct WhatsApp share URL: https://wa.me/?text=... (never routes to navigator.share).
// 6. Generic "More sharing options" uses navigator.share with text only (omitting url) to prevent duplication.

(function() {
    'use strict';

    const CANONICAL_DOMAIN = 'https://mentry-solutions.vercel.app';
    const CANONICAL_WEBSITE_URL = 'https://mentry-solutions.vercel.app/';
    const COMPANY_DESCRIPTION = 'Mentry Solutions connects skilled trainers with training opportunities across colleges and organizations. Join our trainer network and discover upcoming programs.';

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>'"]/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        }[character]));
    }

    function isMobileClient() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent || '');
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
            const id = parsed.searchParams.get('id');
            if (id) return id;
        } catch (e) {}
        const match = String(rawUrl).match(/[?&]id=([^&#]+)/);
        if (match) return decodeURIComponent(match[1]);
        const clean = String(rawUrl).trim();
        if (clean.startsWith('MEN-OPP-') || clean.startsWith('MEN-')) {
            return clean;
        }
        return '';
    }

    function formatINR(val) {
        const num = Number(val);
        if (isNaN(num) || num <= 0) return '';
        return '₹' + num.toLocaleString('en-IN');
    }

    /**
     * Shared Opportunity Data Normalization Layer.
     * Handles all variations of field names across cards, modals, and detail pages.
     */
    function normalizeOpportunityForShare(opportunity, fallbackTitle, fallbackUrl, fallbackText) {
        let title = '';
        let location = '';
        let dates = '';
        let rate = '';
        let jobId = '';

        if (typeof opportunity === 'object' && opportunity !== null) {
            title = (opportunity.title || opportunity.jobTitle || opportunity.course || opportunity.courseTitle || fallbackTitle || 'Technical Training Opportunity').trim();
            
            jobId = opportunity.jobId || opportunity.mentryId || extractOppId(opportunity.shareUrl || opportunity.url) || opportunity.id || opportunity.opportunityId || '';

            // Normalize location
            if (opportunity.location) {
                location = String(opportunity.location).trim();
            } else if (opportunity.city || opportunity.state) {
                const locParts = [opportunity.city, opportunity.state].map(s => String(s || '').trim()).filter(Boolean);
                location = locParts.join(', ');
            } else if (opportunity.campus) {
                location = String(opportunity.campus).trim();
            }

            // Normalize dates
            if (opportunity.dates) {
                dates = String(opportunity.dates).trim();
            } else if (opportunity.startDate || opportunity.fromDate) {
                const s = String(opportunity.startDate || opportunity.fromDate).trim();
                const e = String(opportunity.endDate || opportunity.toDate || '').trim();
                dates = s + (e && e !== s ? ' – ' + e : '');
            } else if (opportunity.durationDays) {
                dates = String(opportunity.durationDays) + ' Working Days';
            }

            // Normalize rate
            if (opportunity.rate) {
                rate = String(opportunity.rate).trim();
            } else if (opportunity.remuneration) {
                rate = String(opportunity.remuneration).trim();
            } else if (opportunity.dailyRateMin || opportunity.dailyRateMax) {
                const min = Number(opportunity.dailyRateMin || 0);
                const max = Number(opportunity.dailyRateMax || 0);
                if (min > 0 && max > 0 && min !== max) {
                    rate = `${formatINR(min)} – ${formatINR(max)}/day`;
                } else if (min > 0) {
                    rate = `${formatINR(min)}/day`;
                } else if (max > 0) {
                    rate = `${formatINR(max)}/day`;
                }
            } else if (opportunity.dailyRate) {
                rate = `${formatINR(opportunity.dailyRate)}/day`;
            }

            // Fallback parsing from existing share message text if any metadata was missing
            const fallbackContent = opportunity.shareMessage || opportunity.text || fallbackText || '';
            if (fallbackContent && (!location || !dates || !rate)) {
                const lines = fallbackContent.split('\n').map(l => l.trim()).filter(Boolean);
                for (const line of lines) {
                    if (!location && (line.startsWith('📍') || line.startsWith('Location:'))) {
                        location = line.replace(/^(📍|Location:)\s*/i, '').trim();
                    } else if (!dates && (line.startsWith('📅') || line.startsWith('Dates:'))) {
                        dates = line.replace(/^(📅|Dates:)\s*/i, '').trim();
                    } else if (!rate && (line.startsWith('💰') || line.startsWith('Remuneration:'))) {
                        rate = line.replace(/^(💰|Remuneration:)\s*/i, '').trim();
                    }
                }
            }
        } else if (typeof opportunity === 'string') {
            const rawStr = String(opportunity).trim();
            if (rawStr.startsWith('http://') || rawStr.startsWith('https://') || rawStr.startsWith('MEN-')) {
                jobId = extractOppId(rawStr) || rawStr;
                title = (fallbackTitle || 'Technical Training Opportunity').trim();
            } else {
                title = rawStr;
                jobId = extractOppId(fallbackUrl) || '';
            }

            const fallbackContent = fallbackText || '';
            if (fallbackContent) {
                const lines = fallbackContent.split('\n').map(l => l.trim()).filter(Boolean);
                for (const line of lines) {
                    if (!location && (line.startsWith('📍') || line.startsWith('Location:'))) {
                        location = line.replace(/^(📍|Location:)\s*/i, '').trim();
                    } else if (!dates && (line.startsWith('📅') || line.startsWith('Dates:'))) {
                        dates = line.replace(/^(📅|Dates:)\s*/i, '').trim();
                    } else if (!rate && (line.startsWith('💰') || line.startsWith('Remuneration:'))) {
                        rate = line.replace(/^(💰|Remuneration:)\s*/i, '').trim();
                    }
                }
            }
        }

        if (!location) location = 'Pan-India';

        return {
            title: title || 'Technical Training Opportunity',
            location: location,
            dates: dates,
            rate: rate,
            jobId: jobId
        };
    }

    /**
     * Returns the single authoritative canonical public URL for an opportunity.
     * Always uses: https://mentry-solutions.vercel.app/opportunity-details.php?id=<PUBLIC_JOB_ID>
     */
    function getCanonicalOpportunityUrl(opportunity) {
        let rawId = '';

        if (typeof opportunity === 'object' && opportunity !== null) {
            rawId = opportunity.jobId || opportunity.mentryId || '';
            if (!rawId && (opportunity.shareUrl || opportunity.url)) {
                rawId = extractOppId(opportunity.shareUrl || opportunity.url);
            }
            if (!rawId) {
                const cand = String(opportunity.id || opportunity.opportunityId || '').trim();
                if (cand) rawId = cand;
            }
        } else if (typeof opportunity === 'string') {
            const parsed = extractOppId(opportunity);
            if (parsed) {
                rawId = parsed;
            } else if (!opportunity.includes('/') && !opportunity.includes('?')) {
                rawId = opportunity.trim();
            }
        }

        const cleanId = String(rawId || '').trim();
        if (cleanId) {
            return `${CANONICAL_DOMAIN}/opportunity-details.php?id=${encodeURIComponent(cleanId)}`;
        }
        return `${CANONICAL_DOMAIN}/opportunities.php`;
    }

    /**
     * Canonical share message generator.
     * Exactly 1 Opportunity URL.
     * Exactly 1 Mentry Website URL.
     * Total URLs = 2.
     * Identical across Desktop, Mobile, PWA, and Android WebView.
     */
    function buildOpportunityShareMessage(opportunity) {
        const meta = normalizeOpportunityForShare(opportunity);
        const canonicalUrl = getCanonicalOpportunityUrl(opportunity);

        const lines = [
            '📢 NEW MENTRY SOLUTIONS TRAINING OPPORTUNITY',
            '',
            meta.title,
            ''
        ];

        const metaLines = [];
        if (meta.location) metaLines.push(`📍 ${meta.location}`);
        if (meta.dates) metaLines.push(`📅 ${meta.dates}`);
        if (meta.rate) metaLines.push(`💰 ${meta.rate}`);

        if (metaLines.length > 0) {
            lines.push(...metaLines);
            lines.push('');
        }

        lines.push('🔗 View Opportunity & Apply');
        lines.push(canonicalUrl);
        lines.push('');
        lines.push('🌐 Explore Mentry Solutions');
        lines.push(COMPANY_DESCRIPTION);
        lines.push('');
        lines.push(CANONICAL_WEBSITE_URL);

        return lines.join('\n');
    }

    /**
     * Builds message text for Telegram sharing where the opportunity URL
     * is passed separately via the dedicated Telegram "url" query parameter.
     * Guarantees 0 opportunity URLs in text + 1 website URL in text.
     */
    function buildTelegramShareText(opportunity) {
        const meta = normalizeOpportunityForShare(opportunity);

        const lines = [
            '📢 NEW MENTRY SOLUTIONS TRAINING OPPORTUNITY',
            '',
            meta.title,
            ''
        ];

        const metaLines = [];
        if (meta.location) metaLines.push(`📍 ${meta.location}`);
        if (meta.dates) metaLines.push(`📅 ${meta.dates}`);
        if (meta.rate) metaLines.push(`💰 ${meta.rate}`);

        if (metaLines.length > 0) {
            lines.push(...metaLines);
            lines.push('');
        }

        lines.push('🔗 View Opportunity & Apply');
        lines.push('');
        lines.push('🌐 Explore Mentry Solutions');
        lines.push(COMPANY_DESCRIPTION);
        lines.push('');
        lines.push(CANONICAL_WEBSITE_URL);

        return lines.join('\n');
    }

    /**
     * Generates direct WhatsApp share URL.
     * Uses universal https://wa.me/?text=... for consistent native app & web launching.
     */
    function getWhatsAppShareUrl(opportunity) {
        const message = buildOpportunityShareMessage(opportunity);
        const encoded = encodeURIComponent(message);
        return `https://wa.me/?text=${encoded}`;
    }

    /**
     * Generates Telegram share URL with url parameter and clean text parameter.
     */
    function getTelegramShareUrl(opportunity) {
        const canonicalUrl = getCanonicalOpportunityUrl(opportunity);
        const textWithoutOppUrl = buildTelegramShareText(opportunity);
        return `https://t.me/share/url?url=${encodeURIComponent(canonicalUrl)}&text=${encodeURIComponent(textWithoutOppUrl)}`;
    }

    /**
     * Generates LinkedIn share URL using canonical opportunity URL.
     */
    function getLinkedInShareUrl(opportunity) {
        const canonicalUrl = getCanonicalOpportunityUrl(opportunity);
        return `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(canonicalUrl)}`;
    }

    /**
     * Generic Native Share implementation.
     * Passes final formatted message in `text` and OMITS `url` to prevent
     * mobile operating systems from appending a duplicate URL.
     */
    async function shareOpportunityNative(opportunity) {
        const meta = normalizeOpportunityForShare(opportunity);
        const finalMessage = buildOpportunityShareMessage(opportunity);

        if (navigator.share) {
            try {
                await navigator.share({
                    title: meta.title,
                    text: finalMessage
                });
                recordAnalytics(meta.jobId, 'native_share');
                return true;
            } catch (err) {
                if (err && err.name === 'AbortError') return false;
            }
        }

        // Fallback if navigator.share fails or is unavailable
        try {
            await navigator.clipboard.writeText(finalMessage);
            recordAnalytics(meta.jobId, 'copy_message');
            return 'copied';
        } catch (e) {
            return false;
        }
    }

    /**
     * Copies ONLY the canonical opportunity URL.
     */
    async function copyOpportunityLink(opportunity) {
        const canonicalUrl = getCanonicalOpportunityUrl(opportunity);
        const meta = normalizeOpportunityForShare(opportunity);
        await navigator.clipboard.writeText(canonicalUrl);
        recordAnalytics(meta.jobId, 'copy_link');
        return canonicalUrl;
    }

    /**
     * Specialized WhatsApp Sharing Strategy (B6, B7, B10):
     * 1. Android WebView Native Bridge: window.MentryAndroid.shareOpportunity(imageUrl, caption)
     * 2. Mobile Browser/PWA with File Share support: Web Share API with approved share image + canonical caption
     * 3. Mobile Fallback (or unsupported file share): https://wa.me/?text=<ENCODED_MESSAGE>
     * 4. Desktop WhatsApp: https://web.whatsapp.com/send?text=<ENCODED_MESSAGE>
     */
    async function shareOpportunityToWhatsApp(opportunity) {
        const meta = normalizeOpportunityForShare(opportunity);
        const finalMessage = buildOpportunityShareMessage(opportunity);
        const oppId = meta.jobId;
        recordAnalytics(oppId, 'whatsapp');

        // B10: Check for native Android WebView bridge
        if (window.MentryAndroid && typeof window.MentryAndroid.shareOpportunity === 'function') {
            try {
                const imageUrl = `${CANONICAL_DOMAIN}/actions/share-opportunity-image.php?id=${encodeURIComponent(oppId)}`;
                window.MentryAndroid.shareOpportunity(imageUrl, finalMessage);
                return true;
            } catch (e) {
                console.warn('[Mentry Share] Android bridge error, falling back:', e);
            }
        }

        if (isMobileClient()) {
            // B7: Preferred Mobile Strategy
            // 1. Fetch approved opportunity share image
            // 2. If navigator.canShare({ files: [...] }), use Web Share API with image file + canonical caption
            let fileShared = false;
            if (navigator.share && typeof navigator.canShare === 'function') {
                try {
                    const imgRes = await fetch(`/actions/share-opportunity-image.php?id=${encodeURIComponent(oppId)}`, { cache: 'no-cache' });
                    if (imgRes.ok) {
                        const blob = await imgRes.blob();
                        const file = new File([blob], `mentry-opportunity-${oppId || 'share'}.png`, { type: 'image/png' });
                        if (navigator.canShare({ files: [file] })) {
                            await navigator.share({
                                files: [file],
                                title: meta.title,
                                text: finalMessage
                            });
                            fileShared = true;
                            return true;
                        }
                    }
                } catch (err) {
                    if (err && (err.name === 'AbortError' || String(err).includes('AbortError'))) {
                        return false;
                    }
                    console.warn('[Mentry Share] Web Share with file failed, falling back to wa.me:', err);
                }
            }

            // 3. Fallback to direct wa.me URL
            if (!fileShared) {
                window.location.href = `https://wa.me/?text=${encodeURIComponent(finalMessage)}`;
            }
        } else {
            // B6: Desktop WhatsApp: open WhatsApp Web with canonical final message
            window.open(`https://web.whatsapp.com/send?text=${encodeURIComponent(finalMessage)}`, '_blank', 'noopener,noreferrer');
        }
        return true;
    }

    /**
     * Renders and opens the Authoritative Opportunity Share Dialog.
     * Contains 5 dedicated, functional channels:
     * - WhatsApp (Direct wa.me URL or Web Share file attachment on mobile)
     * - Telegram (Direct t.me URL)
     * - LinkedIn (Direct LinkedIn URL)
     * - Copy Link (Canonical opportunity URL only)
     * - More sharing options (Generic navigator.share)
     */
    function showShareModal(opportunity) {
        const meta = normalizeOpportunityForShare(opportunity);
        const canonicalUrl = getCanonicalOpportunityUrl(opportunity);
        const finalMessage = buildOpportunityShareMessage(opportunity);
        const oppId = meta.jobId;

        const existing = document.getElementById('opportunityShareDialog');
        if (existing) existing.remove();

        const dialog = document.createElement('div');
        dialog.id = 'opportunityShareDialog';
        dialog.className = 'fixed inset-0 z-[1000] flex items-center justify-center bg-slate-950/75 backdrop-blur-xs p-4 animate-in fade-in duration-200';
        dialog.innerHTML = `
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4 text-slate-900">
                <!-- Modal Header -->
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-2xl bg-orange-50 text-[#FE5E04] flex items-center justify-center font-bold shadow-xs">
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

                <!-- Rich Opportunity Preview Card Mockup -->
                <div class="rounded-2xl border border-slate-200/90 bg-slate-50 p-4 space-y-2 text-left shadow-2xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-teal-800 bg-emerald-100/80 border border-emerald-300/60 px-2.5 py-0.5 rounded-full">
                            TRAINING OPPORTUNITY
                        </span>
                        <span class="text-[11px] font-mono font-bold text-slate-400">mentry-solutions.vercel.app</span>
                    </div>
                    <p class="text-xs sm:text-sm font-black text-slate-900 line-clamp-2 leading-snug">${escapeHtml(meta.title)}</p>
                    <div class="text-xs text-slate-600 space-y-1 pt-0.5">
                        <div class="flex items-center gap-2"><span class="text-xs">📍</span><span class="font-medium">${escapeHtml(meta.location)}</span></div>
                        ${meta.dates ? `<div class="flex items-center gap-2"><span class="text-xs">📅</span><span class="font-medium">${escapeHtml(meta.dates)}</span></div>` : ''}
                        ${meta.rate ? `<div class="flex items-center gap-2 font-bold text-emerald-700"><span class="text-xs">💰</span><span>${escapeHtml(meta.rate)}</span></div>` : ''}
                    </div>
                </div>

                <!-- Action Buttons: 5 Dedicated Share Options -->
                <div class="grid grid-cols-1 gap-2 pt-1">
                    <!-- 1. WhatsApp Button -->
                    <button type="button" data-action="whatsapp" class="flex items-center justify-between px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm shadow-emerald-700/20 transition-all cursor-pointer">
                        <span class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-lg">chat</span>
                            <span>Share on WhatsApp</span>
                        </span>
                        <span class="text-[10px] font-medium opacity-85">Direct • Rich Card</span>
                    </button>

                    <!-- 2. Telegram Button -->
                    <button type="button" data-action="telegram" class="flex items-center justify-between px-4 py-2.5 rounded-2xl bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs shadow-sm shadow-sky-600/20 transition-all cursor-pointer">
                        <span class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-lg">send</span>
                            <span>Share on Telegram</span>
                        </span>
                        <span class="material-symbols-outlined text-sm opacity-75">open_in_new</span>
                    </button>

                    <!-- 3. LinkedIn Button -->
                    <button type="button" data-action="linkedin" class="flex items-center justify-between px-4 py-2.5 rounded-2xl bg-[#0077b5] hover:bg-[#005885] text-white font-bold text-xs shadow-sm shadow-blue-800/20 transition-all cursor-pointer">
                        <span class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-lg">work</span>
                            <span>Share on LinkedIn</span>
                        </span>
                        <span class="material-symbols-outlined text-sm opacity-75">open_in_new</span>
                    </button>

                    <!-- 4. Copy Opportunity Link Button (Copies Opportunity URL ONLY) -->
                    <button type="button" data-action="copy-link" class="flex items-center justify-between px-4 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-all shadow-xs cursor-pointer">
                        <span class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-lg">content_copy</span>
                            <span id="shareCopyBtnText">Copy Opportunity Link</span>
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono">Public URL</span>
                    </button>

                    <!-- 5. More Sharing Options Button (Generic Native Share with no duplicate URL) -->
                    <button type="button" data-action="more-options" class="flex items-center justify-between px-4 py-2.5 rounded-2xl border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-all cursor-pointer">
                        <span class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-lg text-slate-500">share</span>
                            <span id="moreShareBtnText">More sharing options</span>
                        </span>
                        <span class="material-symbols-outlined text-sm text-slate-400">apps</span>
                    </button>
                </div>

                <p data-share-status class="hidden text-center text-xs font-bold text-emerald-600 transition-all"></p>
            </div>
        `;

        document.body.appendChild(dialog);

        const statusEl = dialog.querySelector('[data-share-status]');
        function showFeedback(text) {
            if (!statusEl) return;
            statusEl.textContent = text;
            statusEl.classList.remove('hidden');
            setTimeout(() => {
                statusEl.classList.add('hidden');
            }, 3500);
        }

        // Close handlers
        dialog.querySelector('[data-share-close]').onclick = () => dialog.remove();
        dialog.onclick = (e) => { if (e.target === dialog) dialog.remove(); };

        // 1. WhatsApp Action (B6, B7, B10)
        const waBtn = dialog.querySelector('[data-action="whatsapp"]');
        if (waBtn) {
            waBtn.onclick = async (e) => {
                e.preventDefault();
                await shareOpportunityToWhatsApp(opportunity);
            };
        }

        // 2. Telegram Action
        const tgBtn = dialog.querySelector('[data-action="telegram"]');
        if (tgBtn) {
            tgBtn.onclick = (e) => {
                e.preventDefault();
                recordAnalytics(oppId, 'telegram');
                const tgUrl = getTelegramShareUrl(opportunity);
                if (isMobileClient()) {
                    window.location.href = tgUrl;
                } else {
                    window.open(tgUrl, '_blank', 'noopener,noreferrer');
                }
            };
        }

        // 3. LinkedIn Action
        const liBtn = dialog.querySelector('[data-action="linkedin"]');
        if (liBtn) {
            liBtn.onclick = (e) => {
                e.preventDefault();
                recordAnalytics(oppId, 'linkedin');
                const liUrl = getLinkedInShareUrl(opportunity);
                window.open(liUrl, '_blank', 'noopener,noreferrer');
            };
        }

        // 4. Copy Opportunity Link (Canonical opportunity URL ONLY)
        const copyBtn = dialog.querySelector('[data-action="copy-link"]');
        const copyText = dialog.querySelector('#shareCopyBtnText');
        if (copyBtn) {
            copyBtn.onclick = async (e) => {
                e.preventDefault();
                try {
                    await copyOpportunityLink(opportunity);
                    if (copyText) copyText.textContent = 'Link Copied!';
                    showFeedback('✓ Public opportunity link copied to clipboard');
                    setTimeout(() => {
                        if (copyText) copyText.textContent = 'Copy Opportunity Link';
                    }, 2500);
                } catch (err) {
                    showFeedback(canonicalUrl);
                }
            };
        }

        // 5. More Sharing Options (Native share or full message copy)
        const moreBtn = dialog.querySelector('[data-action="more-options"]');
        const moreText = dialog.querySelector('#moreShareBtnText');
        if (moreBtn) {
            moreBtn.onclick = async (e) => {
                e.preventDefault();
                if (navigator.share) {
                    try {
                        await navigator.share({
                            title: meta.title,
                            text: finalMessage
                        });
                        recordAnalytics(oppId, 'native_share');
                    } catch (err) {
                        if (err && err.name === 'AbortError') return;
                    }
                } else {
                    try {
                        await navigator.clipboard.writeText(finalMessage);
                        recordAnalytics(oppId, 'copy_message');
                        if (moreText) moreText.textContent = 'Message Copied!';
                        showFeedback('✓ Complete share message copied to clipboard');
                        setTimeout(() => {
                            if (moreText) moreText.textContent = 'More sharing options';
                        }, 2500);
                    } catch (err) {
                        showFeedback('✓ Share message ready');
                    }
                }
            };
        }
    }

    /**
     * Primary Global Sharing Function.
     * Invoked from opportunity cards, detail pages, and modal views.
     * Supports both:
     * - shareOpportunity(opportunityObject)
     * - shareOpportunity(title, url, text)
     */
    window.shareOpportunity = function(opportunityOrTitle, url, text) {
        let oppObj;
        if (typeof opportunityOrTitle === 'object' && opportunityOrTitle !== null) {
            oppObj = opportunityOrTitle;
        } else {
            oppObj = {
                title: opportunityOrTitle,
                url: url,
                shareUrl: url,
                shareMessage: text,
                text: text
            };
        }
        showShareModal(oppObj);
    };

    // Export authoritative functions to global window object
    window.MENTRY_SHARE_VERSION = '20260920_v4';
    window.normalizeOpportunityForShare = normalizeOpportunityForShare;
    window.buildOpportunityShareMessage = buildOpportunityShareMessage;
    window.buildTelegramShareText = buildTelegramShareText;
    window.getCanonicalOpportunityUrl = getCanonicalOpportunityUrl;
    window.getWhatsAppShareUrl = getWhatsAppShareUrl;
    window.shareOpportunityToWhatsApp = shareOpportunityToWhatsApp;
    window.getTelegramShareUrl = getTelegramShareUrl;
    window.getLinkedInShareUrl = getLinkedInShareUrl;
    window.shareOpportunityNative = shareOpportunityNative;
    window.copyOpportunityLink = copyOpportunityLink;

})();