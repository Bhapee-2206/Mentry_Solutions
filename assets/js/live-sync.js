/**
 * assets/js/live-sync.js - Mentry Real-Time Live Sync & Notification Engine
 * Zero dependencies, battery-optimized, lightweight background polling.
 */
(function() {
    // Avoid double initialization
    if (window._mentryLiveSyncInitialized) return;
    window._mentryLiveSyncInitialized = true;

    // Ensure Service Worker is registered immediately for mobile PWA push & notifications
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function(err) {
            console.warn('[Mentry LiveSync] SW registration note:', err);
        });
    }

    let lastSyncTs = Date.now() - 30000; // Start with last 30 seconds
    const seenNotifIds = new Set();
    try {
        const storedSeen = JSON.parse(sessionStorage.getItem('mentry_seen_notif_ids') || '[]');
        if (Array.isArray(storedSeen)) {
            storedSeen.forEach(id => seenNotifIds.add(id));
        }
    } catch(e) {}

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

    // 2. Native Mobile / OS System Notification Trigger (PWA notification drawer & lock screen outside the app)
    async function triggerNativeSystemNotification(options) {
        if (!('Notification' in window)) return false;
        if (Notification.permission !== 'granted') return false;

        const title = options.title || 'Mentry Solutions';
        const body = options.message || options.body || 'New update on your training portal.';
        const targetUrl = options.link || options.url || '/trainer/notifications.php';
        const tag = 'mentry-' + (options.id || Date.now());

        const notifPayload = {
            title: title,
            body: body,
            icon: '/public/icon-192.png',
            badge: '/public/icon-192.png',
            tag: tag,
            renotify: true,
            vibrate: [200, 100, 200],
            url: targetUrl
        };

        let displayed = false;

        // A. Primary: Service Worker Registration showNotification (works in Android & iOS PWA notification shade outside the app)
        if ('serviceWorker' in navigator) {
            try {
                let reg = await navigator.serviceWorker.getRegistration('/');
                if (!reg) {
                    reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                }
                if (reg && reg.showNotification) {
                    await reg.showNotification(title, {
                        body: body,
                        icon: '/public/icon-192.png',
                        badge: '/public/icon-192.png',
                        tag: tag,
                        renotify: true,
                        vibrate: [200, 100, 200],
                        data: { url: targetUrl }
                    });
                    displayed = true;
                }
                // Also broadcast message to SW
                if (navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({
                        type: 'SHOW_NOTIFICATION',
                        payload: notifPayload
                    });
                }
            } catch (err) {
                console.warn('[Mentry LiveSync] SW showNotification error:', err);
            }
        }

        // B. Secondary: Notification constructor fallback (Desktop Chrome/Firefox/Safari)
        if (!displayed) {
            try {
                const n = new Notification(title, {
                    body: body,
                    icon: '/public/icon-192.png',
                    tag: tag
                });
                n.onclick = function() {
                    window.focus();
                    window.location.href = targetUrl;
                };
                displayed = true;
            } catch (err) {
                // Illegal constructor on mobile Chrome is expected
            }
        }

        return displayed;
    }

    // 3. Modern Floating Toast Notification Container (Mobile Centered / Desktop Right Aligned)
    function getOrCreateToastContainer() {
        let container = document.getElementById('mentryLiveToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'mentryLiveToastContainer';
            container.className = 'fixed top-3 inset-x-3 max-w-[430px] mx-auto sm:inset-x-auto sm:right-6 sm:top-5 z-[999999] flex flex-col gap-3 pointer-events-none select-none';
            document.body.appendChild(container);
        }
        return container;
    }

    // 3. Show modern, user-friendly interactive glassmorphic push notification toast
    function showLiveToast(options) {
        const container = getOrCreateToastContainer();
        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto bg-white/95 text-slate-900 border border-slate-200/90 shadow-[0_16px_40px_rgba(15,23,42,0.16)] rounded-2xl sm:rounded-3xl p-3.5 sm:p-4 backdrop-blur-2xl transform -translate-y-4 scale-95 opacity-0 transition-all duration-300 ease-out cursor-pointer hover:border-orange-300 flex flex-col relative overflow-hidden';

        const isAccepted = options.title && options.title.toLowerCase().includes('accepted');
        const isShortlisted = options.title && options.title.toLowerCase().includes('shortlisted');
        const isMatch = options.type === 'OPPORTUNITY_MATCH';

        let iconName = 'notifications_active';
        let iconBg = 'bg-orange-50 text-[#FE5E04] border-orange-200';
        let categoryTag = 'Live Alert';

        if (isAccepted) {
            iconName = 'verified';
            iconBg = 'bg-emerald-50 text-emerald-600 border-emerald-200';
            categoryTag = 'Accepted 🎉';
        } else if (isShortlisted) {
            iconName = 'star';
            iconBg = 'bg-amber-50 text-amber-600 border-amber-200';
            categoryTag = 'Shortlisted ⭐';
        } else if (isMatch) {
            iconName = 'bolt';
            iconBg = 'bg-[#FE5E04]/10 text-[#FE5E04] border-[#FE5E04]/30';
            categoryTag = 'New Match ⚡';
        }

        const actionText = isMatch ? 'View Opportunity' : (isAccepted ? 'View Assignment' : 'Open Details');

        toast.innerHTML = `
            <!-- Top branding & dismissal bar -->
            <div class="flex items-center justify-between gap-2 mb-2 pb-1.5 border-b border-slate-100">
                <div class="flex items-center gap-1.5">
                    <img src="/public/mentry.png" class="w-4 h-4 object-contain rounded" alt="Mentry">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-800">Mentry Alert</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded-full bg-slate-100 text-slate-600 border border-slate-200">${categoryTag}</span>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-700 p-1 -mr-1 rounded-full hover:bg-slate-100 transition-colors flex items-center justify-center cursor-pointer" title="Dismiss">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <!-- Main notification body -->
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-2xl border flex items-center justify-center shrink-0 ${iconBg} shadow-2xs">
                    <span class="material-symbols-outlined text-[22px]">${iconName}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-extrabold text-xs sm:text-sm text-slate-900 tracking-tight leading-snug line-clamp-2">${escapeHtml(options.title || 'Notification')}</h4>
                    <p class="text-[11px] sm:text-xs text-slate-500 leading-relaxed mt-0.5 line-clamp-2">${escapeHtml(options.message || '')}</p>
                </div>
            </div>

            <!-- Action footer CTA -->
            <div class="mt-3 pt-2 flex items-center justify-between gap-2">
                <span class="text-[10px] text-slate-400 font-medium flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px]">swipe_up</span>
                    Swipe or tap to open
                </span>
                ${options.link ? `
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-[#FE5E04] hover:bg-[#E04E00] px-3.5 py-1.5 rounded-xl shadow-xs transition-colors">
                        <span>${actionText}</span>
                        <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                    </span>
                ` : ''}
            </div>

            <!-- Auto-dismiss progress countdown indicator -->
            <div class="toast-progress absolute bottom-0 left-0 h-1 bg-gradient-to-r from-[#FE5E04] to-amber-400 w-full transition-all duration-[8000ms] ease-linear"></div>
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

        // Touch swipe-to-dismiss gesture handling
        let touchStartY = 0;
        let touchStartX = 0;
        toast.addEventListener('touchstart', (e) => {
            touchStartY = e.touches[0].clientY;
            touchStartX = e.touches[0].clientX;
        }, { passive: true });

        toast.addEventListener('touchend', (e) => {
            const deltaY = e.changedTouches[0].clientY - touchStartY;
            const deltaX = Math.abs(e.changedTouches[0].clientX - touchStartX);
            if (deltaY < -30 || deltaX > 60) {
                dismissToast(toast);
            }
        }, { passive: true });

        container.appendChild(toast);

        // Animate entrance
        requestAnimationFrame(() => {
            toast.classList.remove('-translate-y-4', 'scale-95', 'opacity-0');
            toast.classList.add('translate-y-0', 'scale-100', 'opacity-100');
            const progress = toast.querySelector('.toast-progress');
            if (progress) {
                requestAnimationFrame(() => progress.style.width = '0%');
            }
        });

        // Auto-dismiss after 8 seconds (pauses on hover)
        let dismissTimer = setTimeout(() => dismissToast(toast), 8000);

        toast.addEventListener('mouseenter', () => clearTimeout(dismissTimer));
        toast.addEventListener('mouseleave', () => {
            clearTimeout(dismissTimer);
            dismissTimer = setTimeout(() => dismissToast(toast), 3000);
        });

        function dismissToast(el) {
            clearTimeout(dismissTimer);
            el.classList.remove('translate-y-0', 'scale-100', 'opacity-100');
            el.classList.add('-translate-y-4', 'scale-95', 'opacity-0');
            setTimeout(() => {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 300);
        }

        // Haptic feedback & audio
        if ('vibrate' in navigator) {
            try { navigator.vibrate([60, 40, 60]); } catch (e) {}
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
                headerBadge.classList.add('scale-125');
                setTimeout(() => headerBadge.classList.remove('scale-125'), 250);
            } else {
                headerBadge.classList.add('hidden');
            }
        } else {
            // Explicit header bell button only
            const bellBtn = document.getElementById('headerNotifBellBtn');
            if (bellBtn && count > 0) {
                let badge = bellBtn.querySelector('#headerNotifBadge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.id = 'headerNotifBadge';
                    badge.className = 'absolute -top-1 -right-1 min-w-[16px] sm:min-w-[18px] h-4 sm:h-[18px] px-1 bg-[#FE5E04] text-white text-[9px] sm:text-[10px] font-black rounded-full flex items-center justify-center shadow-xs leading-none transition-transform scale-125';
                    bellBtn.appendChild(badge);
                }
                badge.textContent = displayCount;
                badge.classList.remove('hidden');
                setTimeout(() => badge.classList.remove('scale-125'), 250);
            }
        }

        // B. Sidebar notification badges in Trainer / Admin portals (strictly excluding header bell buttons)
        const sidebarNavItems = document.querySelectorAll('aside a[href*="notifications.php"], #adminSidebar a[href*="notifications.php"], nav a[href*="notifications.php"]');
        sidebarNavItems.forEach(link => {
            if (link.id === 'headerNotifBellBtn' || link.closest('header') || link.classList.contains('rounded-full')) return;
            let badge = link.querySelector('span[data-live-notif-badge], span.bg-\\[\\#FE5E04\\]');
            if (count > 0) {
                if (badge) {
                    badge.textContent = displayCount;
                    badge.classList.remove('hidden');
                } else {
                    badge = document.createElement('span');
                    badge.setAttribute('data-live-notif-badge', '1');
                    badge.className = 'bg-[#FE5E04] text-white text-[10px] font-black px-1.5 py-0.5 rounded-full ml-auto shrink-0 transition-transform scale-125';
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

                // B. Pop live toasts AND native mobile OS notifications
                if (Array.isArray(data.newNotifications)) {
                    data.newNotifications.forEach(n => {
                        if (!seenNotifIds.has(n.id)) {
                            seenNotifIds.add(n.id);
                            showLiveToast(n);
                            triggerNativeSystemNotification(n);
                        }
                    });
                }

                // C. Update application cards live AND trigger native device notification
                if (data.applicationUpdates && Array.isArray(data.applicationUpdates)) {
                    handleApplicationUpdates(data.applicationUpdates);
                    data.applicationUpdates.forEach(app => {
                        const appKey = 'app_' + app.applicationId + '_' + app.status;
                        if (!seenNotifIds.has(appKey)) {
                            seenNotifIds.add(appKey);
                            let notifTitle = 'Application Status Update';
                            let notifMsg = `Your application for ${app.opportunityTitle} is now ${app.status}.`;
                            if (app.status === 'ACCEPTED') {
                                notifTitle = `🎉 Application Accepted: ${app.opportunityTitle}`;
                                notifMsg = 'Congratulations! Your application has been ACCEPTED by Mentry Operations.';
                            } else if (app.status === 'SHORTLISTED') {
                                notifTitle = `⭐ Shortlisted: ${app.opportunityTitle}`;
                                notifMsg = 'Great news! You have been SHORTLISTED for this assignment.';
                            }
                            triggerNativeSystemNotification({
                                id: appKey,
                                title: notifTitle,
                                message: notifMsg,
                                link: '/trainer/applications.php'
                            });
                        }
                    });
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

    // Dynamic Interval: 6 seconds active, 15 seconds when tab/PWA is in background
    function scheduleNextPoll() {
        const delay = document.hidden ? 15000 : 6000;
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

    // Expose utility to trigger immediate check and test mobile push
    window.triggerLiveSyncCheck = pollLiveSync;
    window.triggerNativeNotification = triggerNativeSystemNotification;

    window.updateDeviceNotificationUI = function() {
        const badges = document.querySelectorAll('#mobilePushBadge, [data-push-badge]');
        const descs = document.querySelectorAll('#mobilePushDesc, [data-push-desc]');
        const enableBtns = document.querySelectorAll('#enableMobilePushBtn, [data-push-enable-btn]');
        const testBtns = document.querySelectorAll('#testMobilePushBtn, [data-push-test-btn]');
        const iconBoxes = document.querySelectorAll('#mobilePushIconBox, [data-push-icon]');

        if (badges.length === 0) return;

        if (!('Notification' in window)) {
            badges.forEach(b => {
                b.textContent = 'NOT SUPPORTED';
                b.className = 'text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-slate-700 text-slate-300';
            });
            descs.forEach(d => d.textContent = 'This browser does not support web notifications.');
            enableBtns.forEach(btn => btn.classList.add('hidden'));
            testBtns.forEach(btn => btn.classList.add('hidden'));
            return;
        }

        if (Notification.permission === 'granted') {
            badges.forEach(b => {
                b.textContent = '✓ ACTIVE ON THIS DEVICE';
                b.className = 'text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
            });
            descs.forEach(d => d.textContent = 'Mobile push alerts are active! Real-time notifications will pop on your phone screen outside the app.');
            enableBtns.forEach(btn => btn.classList.add('hidden'));
            testBtns.forEach(btn => btn.classList.remove('hidden'));
            iconBoxes.forEach(box => {
                box.className = 'w-10 h-10 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center shrink-0';
            });
        } else if (Notification.permission === 'denied') {
            badges.forEach(b => {
                b.textContent = 'BLOCKED IN BROWSER';
                b.className = 'text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30';
            });
            descs.forEach(d => d.textContent = 'Notifications are blocked in your browser settings. Please tap your browser address bar (lock/settings icon) to allow notifications.');
            enableBtns.forEach(btn => btn.classList.add('hidden'));
            testBtns.forEach(btn => btn.classList.add('hidden'));
        } else {
            badges.forEach(b => {
                b.textContent = 'ACTION REQUIRED';
                b.className = 'text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30';
            });
            descs.forEach(d => d.textContent = 'Tap Enable to receive alerts on your phone lock screen & notification bar outside the app.');
            enableBtns.forEach(btn => btn.classList.remove('hidden'));
            testBtns.forEach(btn => btn.classList.add('hidden'));
        }
    };

    window.requestMentryDeviceNotifications = function() {
        if (!('Notification' in window)) {
            alert('Notifications are not supported on this browser.');
            return;
        }

        Notification.requestPermission().then(async (permission) => {
            if (typeof window.updateDeviceNotificationUI === 'function') {
                window.updateDeviceNotificationUI();
            }
            if (permission === 'granted') {
                if ('serviceWorker' in navigator) {
                    try {
                        await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                    } catch(e) {}
                }
                window.sendTestDeviceNotification();
            } else if (permission === 'denied') {
                alert('Notifications are blocked in browser settings. Please tap the lock icon in your address bar to allow notifications.');
            }
        });
    };

    window.sendTestDeviceNotification = async function() {
        if (!('Notification' in window)) {
            alert('Notifications not supported by this browser.');
            return;
        }

        if (Notification.permission !== 'granted') {
            window.requestMentryDeviceNotifications();
            return;
        }

        await triggerNativeSystemNotification({
            id: 'test_' + Date.now(),
            title: '🎉 Mentry Notification Active!',
            message: 'You will now receive alerts on your phone screen outside the app when selected or matched.',
            link: '/trainer/notifications.php'
        });

        showLiveToast({
            title: 'Mobile Alert Sent',
            message: 'Check your phone notification drawer or lock screen outside the app!',
            link: '/trainer/notifications.php'
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.updateDeviceNotificationUI === 'function') window.updateDeviceNotificationUI();
        });
    } else {
        if (typeof window.updateDeviceNotificationUI === 'function') window.updateDeviceNotificationUI();
    }
})();
