/**
 * assets/js/live-sync.js - Mentry Real-Time Live Sync & Notification Engine
 * Zero dependencies, battery-optimized, lightweight background polling.
 */
(function() {
    // Avoid double initialization
    if (window._mentryLiveSyncInitialized) return;
    window._mentryLiveSyncInitialized = true;

    let lastSyncTs = Date.now() - 30000; // Start with last 30 seconds
    const seenNotifIds = new Set();
    let pollTimer = null;
    let isPolling = false;

    // 1. Synthesize subtle audio chime (Web Audio API - no external assets needed)
    function playNotificationChime() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            if (ctx.state === 'suspended') {
                // Resume if user has interacted with document
                ctx.resume().catch(() => {});
            }
            const now = ctx.currentTime;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            // Dual-tone chime: D5 (587.33Hz) -> A5 (880Hz)
            osc.frequency.setValueAtTime(587.33, now);
            osc.frequency.setValueAtTime(880, now + 0.12);

            gain.gain.setValueAtTime(0.06, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.45);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(now);
            osc.stop(now + 0.5);
        } catch(e) {
            // Audio policy blocked or unsupported; fail silently
        }
    }

    // 2. Floating Toast Notification Container
    function getOrCreateToastContainer() {
        let container = document.getElementById('mentryLiveToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'mentryLiveToastContainer';
            container.className = 'fixed top-4 right-4 sm:top-5 sm:right-6 z-[999999] flex flex-col gap-2.5 max-w-[360px] w-[92vw] sm:w-full pointer-events-none select-none';
            document.body.appendChild(container);
        }
        return container;
    }

    // 3. Show modern interactive glassmorphic toast
    function showLiveToast(options) {
        const container = getOrCreateToastContainer();
        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto bg-slate-900/95 text-white border border-slate-700/80 shadow-2xl rounded-2xl p-3.5 sm:p-4 backdrop-blur-xl transform translate-y-3 opacity-0 transition-all duration-300 flex items-start gap-3 cursor-pointer hover:border-[#FE5E04] hover:bg-slate-900';

        const isAccepted = options.title && options.title.toLowerCase().includes('accepted');
        const isShortlisted = options.title && options.title.toLowerCase().includes('shortlisted');
        const isMatch = options.type === 'OPPORTUNITY_MATCH';

        let iconName = 'notifications';
        let iconBg = 'bg-orange-500/20 text-[#FE5E04] border-orange-500/30';
        if (isAccepted) {
            iconName = 'check_circle';
            iconBg = 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
        } else if (isShortlisted) {
            iconName = 'star';
            iconBg = 'bg-amber-500/20 text-amber-400 border-amber-500/30';
        } else if (isMatch) {
            iconName = 'bolt';
            iconBg = 'bg-[#FE5E04]/20 text-[#FE5E04] border-[#FE5E04]/40';
        }

        toast.innerHTML = `
            <div class="w-9 h-9 rounded-xl border flex items-center justify-center shrink-0 ${iconBg}">
                <span class="material-symbols-outlined text-[20px]">${iconName}</span>
            </div>
            <div class="flex-1 min-w-0 pr-1">
                <div class="flex items-center justify-between gap-1.5 mb-0.5">
                    <h4 class="font-extrabold text-xs text-white tracking-tight truncate">${escapeHtml(options.title || 'Notification')}</h4>
                    <span class="text-[9px] font-bold text-orange-400 uppercase tracking-wider shrink-0">Live</span>
                </div>
                <p class="text-[11px] text-slate-300 leading-snug line-clamp-2">${escapeHtml(options.message || '')}</p>
                ${options.link ? `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-[#FE5E04] mt-1.5 hover:underline">Tap to view details <span class="material-symbols-outlined text-[12px]">arrow_forward</span></span>` : ''}
            </div>
            <button type="button" class="text-slate-400 hover:text-white p-1 -mr-1 -mt-1 rounded-lg transition-colors" title="Dismiss">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        `;

        // Click on toast navigates to destination
        toast.addEventListener('click', (e) => {
            if (e.target.closest('button')) {
                dismissToast(toast);
                return;
            }
            if (options.link) {
                window.location.href = options.link;
            }
        });

        container.appendChild(toast);

        // Animate entrance
        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-3', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
        });

        // Auto-dismiss after 7.5 seconds
        const dismissTimer = setTimeout(() => {
            dismissToast(toast);
        }, 7500);

        function dismissToast(el) {
            clearTimeout(dismissTimer);
            el.classList.remove('translate-y-0', 'opacity-100');
            el.classList.add('-translate-y-2', 'opacity-0');
            setTimeout(() => {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 300);
        }

        playNotificationChime();
    }

    // 4. Update Header & Sidebar Notification Badges Live
    function updateNotificationBadges(unreadCount) {
        const count = parseInt(unreadCount, 10) || 0;
        const displayCount = count > 99 ? '99+' : String(count);

        // A. Header notification bell badge
        const headerBadge = document.getElementById('headerNotifBadge');
        if (headerBadge) {
            if (count > 0) {
                headerBadge.textContent = displayCount;
                headerBadge.classList.remove('hidden');
                // Trigger pop animation
                headerBadge.classList.add('scale-125');
                setTimeout(() => headerBadge.classList.remove('scale-125'), 250);
            } else {
                headerBadge.classList.add('hidden');
            }
        } else {
            // Fallback: If header badge wasn't rendered initially because count was 0
            const bellBtn = document.getElementById('headerNotifBellBtn') || document.querySelector('a[href*="notifications.php"]');
            if (bellBtn && count > 0 && !bellBtn.querySelector('#headerNotifBadge')) {
                const newBadge = document.createElement('span');
                newBadge.id = 'headerNotifBadge';
                newBadge.className = 'absolute -top-1 -right-1 min-w-[16px] sm:min-w-[18px] h-4 sm:h-[18px] px-1 bg-[#FE5E04] text-white text-[9px] sm:text-[10px] font-black rounded-full flex items-center justify-center shadow-xs leading-none transition-transform scale-125';
                newBadge.textContent = displayCount;
                bellBtn.appendChild(newBadge);
                setTimeout(() => newBadge.classList.remove('scale-125'), 250);
            }
        }

        // B. Sidebar notification badges in Trainer / Admin portals
        const sidebarLinks = document.querySelectorAll('a[href$="/notifications.php"]');
        sidebarLinks.forEach(link => {
            let badge = link.querySelector('span.bg-\\[\\#FE5E04\\], span[data-live-notif-badge]');
            if (count > 0) {
                if (badge) {
                    badge.textContent = displayCount;
                    badge.classList.remove('hidden');
                } else {
                    badge = document.createElement('span');
                    badge.setAttribute('data-live-notif-badge', '1');
                    badge.className = 'bg-[#FE5E04] text-white text-[10px] font-black px-1.5 py-0.5 rounded-full ml-1.5 shrink-0 transition-transform scale-125';
                    badge.textContent = displayCount;
                    link.appendChild(badge);
                    setTimeout(() => badge.classList.remove('scale-125'), 250);
                }
            } else if (badge) {
                badge.classList.add('hidden');
            }
        });
    }

    // 5. Update Application Statuses in DOM without refresh
    function handleApplicationUpdates(updates) {
        if (!Array.isArray(updates) || updates.length === 0) return;

        updates.forEach(app => {
            // Find application container or card in trainer/applications.php
            const appCard = document.querySelector(`[data-application-id="${app.applicationId}"]`) ||
                            document.querySelector(`a[href*="${app.opportunityId}"]`)?.closest('.bg-white');

            if (appCard) {
                const badge = appCard.querySelector('.app-status-badge') || appCard.querySelector('span[class*="rounded-full"]');
                if (badge) {
                    let badgeClass = '';
                    let badgeText = app.status;

                    if (app.status === 'ACCEPTED') {
                        badgeClass = 'bg-emerald-100 text-emerald-800 border border-emerald-300';
                        badgeText = 'Accepted & Scheduled';
                    } else if (app.status === 'SHORTLISTED') {
                        badgeClass = 'bg-amber-100 text-amber-800 border border-amber-300';
                        badgeText = 'Shortlisted';
                    } else if (app.status === 'REJECTED') {
                        badgeClass = 'bg-rose-100 text-rose-800 border border-rose-300';
                        badgeText = 'Not Selected';
                    }

                    badge.className = `inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all duration-300 ${badgeClass}`;
                    badge.innerHTML = `<span class="w-2 h-2 rounded-full bg-current animate-pulse"></span> ${badgeText}`;

                    // Flash highlight on card
                    appCard.classList.add('ring-2', 'ring-emerald-400');
                    setTimeout(() => appCard.classList.remove('ring-2', 'ring-emerald-400'), 3000);
                }
            }
        });
    }

    // 6. Handle New Opportunities Live Floating Chip
    function handleNewOpportunities(opps) {
        if (!Array.isArray(opps) || opps.length === 0) return;

        // If on opportunities page, display a non-intrusive floating pill banner
        if (window.location.pathname.includes('opportunities.php') || window.location.pathname.includes('/trainer/opportunities.php')) {
            let pill = document.getElementById('mentryNewOppsLivePill');
            if (!pill) {
                pill = document.createElement('div');
                pill.id = 'mentryNewOppsLivePill';
                pill.className = 'fixed top-20 inset-x-0 mx-auto w-fit z-40 bg-[#182A4A] text-white px-4 py-2 rounded-full shadow-xl border border-orange-500/50 flex items-center gap-2 text-xs font-bold transition-all transform -translate-y-4 opacity-0 cursor-pointer hover:bg-[#0F1B30]';
                pill.innerHTML = `
                    <span class="w-2 h-2 rounded-full bg-[#FE5E04] animate-ping"></span>
                    <span>⚡ <strong class="text-[#FE5E04]">${opps.length} New ${opps.length === 1 ? 'Opening' : 'Openings'}</strong> posted just now &mdash; Tap to view</span>
                    <span class="material-symbols-outlined text-[16px]">arrow_upward</span>
                `;
                pill.addEventListener('click', () => {
                    window.location.reload();
                });
                document.body.appendChild(pill);

                requestAnimationFrame(() => {
                    pill.classList.remove('-translate-y-4', 'opacity-0');
                    pill.classList.add('translate-y-0', 'opacity-100');
                });
            }
        }
    }

    // 7. Core Background Poll
    async function pollLiveSync() {
        if (isPolling) return;
        // If tab is hidden in background, slow down polling to save resources
        if (document.hidden) return;

        isPolling = true;
        try {
            const context = window.location.pathname;
            const res = await fetch(`/actions/live-sync-api.php?last_sync=${lastSyncTs}&context=${encodeURIComponent(context)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            if (data.success) {
                // Update server timestamp baseline
                if (data.serverTime) lastSyncTs = data.serverTime;

                // A. Update live badge counts
                updateNotificationBadges(data.unreadCount);

                // B. Pop live toasts for new notifications
                if (Array.isArray(data.newNotifications)) {
                    data.newNotifications.forEach(n => {
                        if (!seenNotifIds.has(n.id)) {
                            seenNotifIds.add(n.id);
                            showLiveToast(n);
                        }
                    });
                }

                // C. Update application cards live
                if (data.applicationUpdates) {
                    handleApplicationUpdates(data.applicationUpdates);
                }

                // D. Update new opportunities
                if (data.newOpportunities) {
                    handleNewOpportunities(data.newOpportunities);
                }
            }
        } catch(err) {
            // Silently swallow network hiccups; will retry on next tick
        } finally {
            isPolling = false;
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Start polling with initial delay
    setTimeout(pollLiveSync, 1500);

    // Dynamic Interval: 7 seconds active, 25 seconds when in background
    function scheduleNextPoll() {
        const delay = document.hidden ? 25000 : 7000;
        clearTimeout(pollTimer);
        pollTimer = setTimeout(async () => {
            await pollLiveSync();
            scheduleNextPoll();
        }, delay);
    }

    scheduleNextPoll();

    // Re-check immediately when tab is brought back to focus
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pollLiveSync();
            scheduleNextPoll();
        }
    });

    // Expose utility to trigger immediate check
    window.triggerLiveSyncCheck = pollLiveSync;
})();
