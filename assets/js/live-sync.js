/**
 * assets/js/live-sync.js - Mentry Real-Time Live Sync & Notification Engine
 * Central Single Source of Truth for In-App Popups & PWA Push Notifications.
 */
(function() {
    // Avoid double initialization across multiple page components
    if (window._mentryLiveSyncInitialized) return;
    window._mentryLiveSyncInitialized = true;

    // Ensure Service Worker is registered immediately for mobile PWA push & notifications
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function(err) {
            console.warn('[Mentry LiveSync] SW registration note:', err);
        });
    }

    let lastSyncTs = Date.now() - 30000; // Baseline: last 30 seconds
    let pollTimer = null;
    let isPolling = false;

    // 1. Web Audio API Chime (Pure synthesized dual-tone chime - no external audio files)
    function playNotificationChime() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            if (ctx.state === 'suspended') {
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

    // 2. Toast Container Singleton (Mobile Centered with Safe Area / Desktop Top-Right)
    function getOrCreateToastContainer() {
        let container = document.getElementById('mentryLiveToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'mentryLiveToastContainer';
            container.className = 'fixed inset-x-3 max-w-[420px] mx-auto sm:inset-x-auto sm:right-6 sm:top-5 z-[999999] flex flex-col pointer-events-none select-none';
            container.style.top = 'calc(env(safe-area-inset-top, 0px) + 12px)';
            document.body.appendChild(container);
        }
        return container;
    }

    // 3. Central Notification Manager (Single Source of Truth)
    const NotificationManager = {
        queue: [],
        currentToast: null,
        currentNotifData: null,
        autoDismissTimer: null,
        storageKey: 'mentry_processed_notifications_v1',
        processedMap: new Map(),

        init() {
            // Load persistent deduplication map from localStorage
            try {
                const stored = JSON.parse(localStorage.getItem(this.storageKey) || '[]');
                const now = Date.now();
                const sevenDaysAgo = now - 7 * 86400 * 1000;
                if (Array.isArray(stored)) {
                    stored.forEach(item => {
                        if (item && item.id && item.ts && item.ts > sevenDaysAgo) {
                            this.processedMap.set(String(item.id), item.ts);
                        }
                    });
                }
            } catch(e) {}
            this.persist();

            // Keyboard Escape dismiss listener
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.currentToast) {
                    this.dismissCurrent('escape');
                }
            });

            // Listen for Service Worker foreground message (when SW push arrived while app was focused)
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data && event.data.type === 'PUSH_RECEIVED_IN_APP' && event.data.notification) {
                        this.handleIncomingNotification(event.data.notification, 'service_worker_push');
                    }
                });
            }
        },

        persist() {
            try {
                const items = [];
                this.processedMap.forEach((ts, id) => {
                    items.push({ id, ts });
                });
                if (items.length > 200) {
                    items.sort((a, b) => b.ts - a.ts);
                    items.length = 200;
                }
                localStorage.setItem(this.storageKey, JSON.stringify(items));
            } catch(e) {}
        },

        getCanonicalId(notif) {
            if (!notif) return null;
            if (notif.id) return String(notif.id);
            if (notif._id) return String(notif._id);
            if (notif.notificationId) return String(notif.notificationId);
            if (notif.tag) return String(notif.tag).replace(/^mentry-/, '');
            const key = (notif.title || '') + '|' + (notif.link || notif.url || '') + '|' + (notif.type || '');
            let hash = 0;
            for (let i = 0; i < key.length; i++) {
                hash = ((hash << 5) - hash) + key.charCodeAt(i);
                hash |= 0;
            }
            return 'h_' + Math.abs(hash);
        },

        isProcessed(id) {
            if (!id) return false;
            return this.processedMap.has(String(id));
        },

        markProcessed(id) {
            if (!id) return;
            this.processedMap.set(String(id), Date.now());
            this.persist();
        },

        isUserActive() {
            return !document.hidden && (typeof document.hasFocus === 'function' ? document.hasFocus() : true);
        },

        handleIncomingNotification(notif, source = 'poll') {
            const id = this.getCanonicalId(notif);
            if (this.isProcessed(id)) {
                // Drop duplicate event immediately
                return false;
            }
            this.markProcessed(id);

            // Normalized notification object
            const cleanNotif = {
                id: id,
                title: notif.title || 'Mentry Notification',
                message: notif.message || notif.body || '',
                link: notif.link || notif.url || '/trainer/notifications.php',
                type: notif.type || 'GENERAL',
                matchScore: notif.matchScore || null,
                createdAt: notif.createdAt || Date.now()
            };

            if (this.isUserActive()) {
                // User is actively using the website / PWA:
                // Show ONE in-app popup card via central queue.
                // Strictly DO NOT fire a native system/browser push notification.
                this.enqueue(cleanNotif);
            } else {
                // User is inactive or tab is backgrounded:
                // If it arrived via background poll (not via SW Push), trigger ONE native push if permission granted
                if (source === 'poll' && 'Notification' in window && Notification.permission === 'granted') {
                    triggerNativeSystemNotification(cleanNotif);
                }
            }
            return true;
        },

        enqueue(notif) {
            this.queue.push(notif);
            if (!this.currentToast) {
                this.displayNextInQueue();
            } else {
                this.updateQueueBadge();
            }
        },

        updateQueueBadge() {
            if (!this.currentToast) return;
            const badge = this.currentToast.querySelector('[data-notif-queue-badge]');
            if (!badge) return;
            const remaining = this.queue.length;
            if (remaining > 0) {
                badge.textContent = `+${remaining} more`;
                badge.classList.remove('hidden');
                badge.classList.add('scale-110');
                setTimeout(() => badge.classList.remove('scale-110'), 200);
            } else {
                badge.classList.add('hidden');
            }
        },

        displayNextInQueue() {
            if (this.queue.length === 0) {
                this.currentToast = null;
                this.currentNotifData = null;
                return;
            }

            const notif = this.queue.shift();
            this.currentNotifData = notif;
            this.renderToastCard(notif);
        },

        renderToastCard(notif) {
            const container = getOrCreateToastContainer();
            container.innerHTML = ''; // Keep strictly ONE active card

            const toast = document.createElement('div');
            this.currentToast = toast;

            // Mentry Visual Language: Dark Navy Card, Subtle Blue Gradient, Orange Accent, 20px radius
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            toast.className = 'pointer-events-auto relative w-full bg-[#0B132B]/95 text-white border border-slate-700/80 shadow-[0_20px_50px_rgba(0,0,0,0.5)] rounded-[20px] p-3.5 sm:p-4 backdrop-blur-2xl transform -translate-y-4 scale-95 opacity-0 transition-transform duration-300 ease-out cursor-pointer hover:border-[#FE5E04]/50 flex flex-col overflow-hidden select-none touch-pan-y';

            const isAccepted = notif.title && notif.title.toLowerCase().includes('accepted');
            const isShortlisted = notif.title && notif.title.toLowerCase().includes('shortlisted');
            const isMatch = notif.type === 'OPPORTUNITY_MATCH' || (notif.title && notif.title.toLowerCase().includes('match'));

            let iconName = 'notifications_active';
            let iconBoxClass = 'bg-[#FE5E04]/15 text-[#FE5E04] border-[#FE5E04]/30';
            let categoryTag = 'LIVE';
            let ctaText = 'Tap to view details →';

            if (isAccepted) {
                iconName = 'verified';
                iconBoxClass = 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30';
                categoryTag = 'ACCEPTED';
                ctaText = 'View Assignment →';
            } else if (isShortlisted) {
                iconName = 'star';
                iconBoxClass = 'bg-amber-500/15 text-amber-400 border-amber-500/30';
                categoryTag = 'SHORTLISTED';
                ctaText = 'View Opportunity →';
            } else if (isMatch) {
                iconName = 'bolt';
                iconBoxClass = 'bg-[#FE5E04]/20 text-[#FE5E04] border-[#FE5E04]/40';
                categoryTag = 'LIVE';
                ctaText = 'Tap to view details →';
            }

            const remainingCount = this.queue.length;

            toast.innerHTML = `
                <!-- Top Bar: Brand, Category, Queue Counter & Dismiss Button -->
                <div class="flex items-center justify-between gap-2 mb-2 pb-1.5 border-b border-slate-700/60">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
                        <span class="text-[10px] font-black uppercase tracking-wider text-white shrink-0">Mentry</span>
                        <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-slate-800 text-orange-300 border border-slate-700 shrink-0">${categoryTag}</span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span data-notif-queue-badge class="${remainingCount > 0 ? '' : 'hidden'} text-[10px] font-black bg-[#FE5E04] text-white px-2 py-0.5 rounded-full shadow-xs transition-transform">
                            +${remainingCount} more
                        </span>
                        <button type="button" data-notif-close-btn class="text-slate-400 hover:text-white p-1 -mr-1 rounded-full hover:bg-slate-800/80 transition-colors flex items-center justify-center cursor-pointer" aria-label="Dismiss notification">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </button>
                    </div>
                </div>

                <!-- Notification Body -->
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-2xl border flex items-center justify-center shrink-0 ${iconBoxClass} shadow-inner">
                        <span class="material-symbols-outlined text-[22px]">${iconName}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-extrabold text-xs sm:text-sm text-white tracking-tight leading-snug line-clamp-2">${escapeHtml(notif.title)}</h4>
                        <p class="text-[11px] sm:text-xs text-slate-300 leading-relaxed mt-1 line-clamp-2">${escapeHtml(notif.message)}</p>
                    </div>
                </div>

                <!-- Footer: CTA & Swipe Instruction -->
                <div class="mt-3 pt-2 flex items-center justify-between gap-2 text-xs">
                    <span class="text-[10px] text-slate-400 font-medium flex items-center gap-1">
                        <span class="material-symbols-outlined text-[13px]">swipe</span>
                        Swipe to dismiss
                    </span>
                    <span class="inline-flex items-center gap-1 font-bold text-xs text-[#FE5E04] hover:text-[#FF7A2F] transition-colors">
                        <span>${escapeHtml(ctaText)}</span>
                    </span>
                </div>

                <!-- Auto-dismiss Progress Bar -->
                <div class="toast-progress absolute bottom-0 left-0 h-[3px] bg-gradient-to-r from-[#FE5E04] to-amber-400 w-full transition-all duration-[8000ms] ease-linear pointer-events-none"></div>
            `;

            // Click handling
            toast.addEventListener('click', (e) => {
                if (e.target.closest('[data-notif-close-btn]')) {
                    e.stopPropagation();
                    this.dismissCurrent('close_button');
                    return;
                }
                if (notif.link) {
                    window.location.href = notif.link;
                }
            });

            // Attach touch swipe-to-dismiss gesture engine
            this.attachSwipeGestures(toast);

            container.appendChild(toast);

            // Animate card entrance
            requestAnimationFrame(() => {
                toast.classList.remove('-translate-y-4', 'scale-95', 'opacity-0');
                toast.classList.add('translate-y-0', 'scale-100', 'opacity-100');
                const progress = toast.querySelector('.toast-progress');
                if (progress) {
                    requestAnimationFrame(() => progress.style.width = '0%');
                }
            });

            // Start auto-dismiss timer (8 seconds)
            this.startAutoDismiss(toast);

            // Audio chime & haptic feedback
            playNotificationChime();
            if ('vibrate' in navigator) {
                try { navigator.vibrate([60, 40, 60]); } catch (e) {}
            }
        },

        startAutoDismiss(toast) {
            this.clearAutoDismiss();
            this.autoDismissTimer = setTimeout(() => {
                if (this.currentToast === toast) {
                    this.dismissCurrent('timeout');
                }
            }, 8000);

            // Pause on hover
            toast.addEventListener('mouseenter', () => this.clearAutoDismiss());
            toast.addEventListener('mouseleave', () => {
                this.clearAutoDismiss();
                this.autoDismissTimer = setTimeout(() => {
                    if (this.currentToast === toast) {
                        this.dismissCurrent('timeout');
                    }
                }, 3000);
            });
        },

        clearAutoDismiss() {
            if (this.autoDismissTimer) {
                clearTimeout(this.autoDismissTimer);
                this.autoDismissTimer = null;
            }
        },

        attachSwipeGestures(toast) {
            let startX = 0;
            let startY = 0;
            let currentDeltaX = 0;
            let currentDeltaY = 0;
            let isTracking = false;

            const onTouchStart = (e) => {
                if (e.touches.length !== 1) return;
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
                currentDeltaX = 0;
                currentDeltaY = 0;
                isTracking = true;
                this.clearAutoDismiss();
                toast.style.transition = 'none';
            };

            const onTouchMove = (e) => {
                if (!isTracking || e.touches.length !== 1) return;
                currentDeltaX = e.touches[0].clientX - startX;
                currentDeltaY = e.touches[0].clientY - startY;

                // Restrict downward pull, allow upward swipe
                const clampedY = currentDeltaY > 0 ? currentDeltaY * 0.2 : currentDeltaY;
                const cardWidth = toast.offsetWidth || 340;
                const progress = Math.min(1, Math.abs(currentDeltaX) / cardWidth);
                const opacity = Math.max(0.2, 1 - progress * 0.9);

                toast.style.transform = `translate3d(${currentDeltaX}px, ${clampedY}px, 0) rotate(${currentDeltaX * 0.04}deg)`;
                toast.style.opacity = String(opacity);
            };

            const onTouchEnd = () => {
                if (!isTracking) return;
                isTracking = false;

                const cardWidth = toast.offsetWidth || 340;
                const thresholdX = cardWidth * 0.35;
                const thresholdY = -40; // 40px swipe up

                toast.style.transition = 'transform 0.28s cubic-bezier(0.2, 0.9, 0.3, 1), opacity 0.28s ease-out';

                if (Math.abs(currentDeltaX) > thresholdX || currentDeltaY < thresholdY) {
                    // Passed threshold: animate out in swipe direction
                    const exitX = currentDeltaX > 0 ? (cardWidth + 80) : -(cardWidth + 80);
                    const exitY = currentDeltaY < thresholdY ? -120 : 0;
                    toast.style.transform = `translate3d(${exitX}px, ${exitY}px, 0)`;
                    toast.style.opacity = '0';
                    setTimeout(() => {
                        this.dismissCurrent('swipe');
                    }, 280);
                } else {
                    // Spring back to original position
                    toast.style.transform = 'translate3d(0, 0, 0) rotate(0deg)';
                    toast.style.opacity = '1';
                    this.startAutoDismiss(toast);
                }
            };

            toast.addEventListener('touchstart', onTouchStart, { passive: true });
            toast.addEventListener('touchmove', onTouchMove, { passive: true });
            toast.addEventListener('touchend', onTouchEnd, { passive: true });
            toast.addEventListener('touchcancel', onTouchEnd, { passive: true });
        },

        dismissCurrent(reason = 'close') {
            this.clearAutoDismiss();
            const el = this.currentToast;
            if (!el) return;

            this.currentToast = null;
            this.currentNotifData = null;

            el.style.transition = 'transform 0.25s ease-in, opacity 0.25s ease-in';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-16px) scale(0.95)';

            setTimeout(() => {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
                // Smoothly dequeue and show next notification if available
                this.displayNextInQueue();
            }, 260);
        }
    };

    // Initialize Notification Manager
    NotificationManager.init();

    // 4. Native OS System Notification Trigger (Used strictly when user is not active or explicitly requested)
    async function triggerNativeSystemNotification(options) {
        if (!('Notification' in window)) return false;
        if (Notification.permission !== 'granted') return false;

        const title = options.title || 'Mentry Solutions';
        const body = options.message || options.body || 'New update on your training portal.';
        const targetUrl = options.link || options.url || '/trainer/notifications.php';
        const notifId = NotificationManager.getCanonicalId(options);
        const tag = 'mentry-' + notifId;

        // A. Service Worker Registration showNotification (clean single PWA icon, no duplicate logo)
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
                        tag: tag,
                        renotify: false,
                        vibrate: [150, 60, 150],
                        data: { id: notifId, url: targetUrl }
                    });
                    return true;
                }
            } catch (err) {
                console.warn('[Mentry LiveSync] SW showNotification note:', err);
            }
        }

        // B. Desktop Notification constructor fallback ONLY if SW showNotification was unavailable
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
            return true;
        } catch (err) {}

        return false;
    }

    // 5. Update Header & Sidebar Notification Badges Live
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

    // 6. Update Application Statuses in DOM without refresh
    function handleApplicationUpdates(updates) {
        if (!Array.isArray(updates) || updates.length === 0) return;

        updates.forEach(app => {
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

                    appCard.classList.add('ring-2', 'ring-emerald-400');
                    setTimeout(() => appCard.classList.remove('ring-2', 'ring-emerald-400'), 3000);
                }
            }
        });
    }

    // 7. Handle New Opportunities Live Floating Chip
    function handleNewOpportunities(opps) {
        if (!Array.isArray(opps) || opps.length === 0) return;

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

    // 8. Core Background Poll
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
                if (data.serverTime) lastSyncTs = data.serverTime;

                // A. Live badge counts
                updateNotificationBadges(data.unreadCount);

                // B. Route new notifications strictly through NotificationManager (dedup + queue + active check)
                if (Array.isArray(data.newNotifications)) {
                    data.newNotifications.forEach(n => {
                        NotificationManager.handleIncomingNotification(n, 'poll');
                    });
                }

                // C. Application status changes
                if (data.applicationUpdates && Array.isArray(data.applicationUpdates)) {
                    handleApplicationUpdates(data.applicationUpdates);
                    data.applicationUpdates.forEach(app => {
                        const appKey = 'app_' + app.applicationId + '_' + app.status;
                        let notifTitle = 'Application Status Update';
                        let notifMsg = `Your application for ${app.opportunityTitle} is now ${app.status}.`;
                        if (app.status === 'ACCEPTED') {
                            notifTitle = `🎉 Application Accepted: ${app.opportunityTitle}`;
                            notifMsg = 'Congratulations! Your application has been ACCEPTED by Mentry Operations.';
                        } else if (app.status === 'SHORTLISTED') {
                            notifTitle = `⭐ Shortlisted: ${app.opportunityTitle}`;
                            notifMsg = 'Great news! You have been SHORTLISTED for this assignment.';
                        }
                        NotificationManager.handleIncomingNotification({
                            id: appKey,
                            title: notifTitle,
                            message: notifMsg,
                            link: '/trainer/applications.php',
                            type: 'APPLICATION_' + app.status
                        }, 'poll');
                    });
                }

                // D. New opportunity pill
                if (data.newOpportunities) {
                    handleNewOpportunities(data.newOpportunities);
                }
            }
        } catch(err) {
            // Swallow network hiccups; will retry on next schedule
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

    // Schedule: 6s when window is active, 15s when backgrounded
    function scheduleNextPoll() {
        const delay = document.hidden ? 15000 : 6000;
        clearTimeout(pollTimer);
        pollTimer = setTimeout(async () => {
            await pollLiveSync();
            scheduleNextPoll();
        }, delay);
    }

    // Start polling with initial delay
    setTimeout(pollLiveSync, 1200);
    scheduleNextPoll();

    // Re-check immediately when tab is brought back to focus
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pollLiveSync();
            scheduleNextPoll();
        }
    });

    // 9. Public API exports
    window.NotificationManager = NotificationManager;
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
            descs.forEach(d => d.textContent = 'Notifications are blocked in your browser settings. Please tap your browser address bar to allow notifications.');
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

        const testId = 'test_' + Date.now();
        // If user is actively looking at screen, show the in-app popup
        NotificationManager.handleIncomingNotification({
            id: testId,
            title: '🎉 Mentry Live Alert Active!',
            message: 'Your device is connected! Real-time match notifications and selection updates will appear here.',
            link: '/trainer/notifications.php',
            type: 'SYSTEM_ALERT'
        }, 'test');
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.updateDeviceNotificationUI === 'function') window.updateDeviceNotificationUI();
        });
    } else {
        if (typeof window.updateDeviceNotificationUI === 'function') window.updateDeviceNotificationUI();
    }
})();
