/**
 * assets/js/push-notifications.js - Authoritative Push Notification Controller & SW Manager
 * 
 * Guarantees:
 * - Single authoritative ServiceWorkerRegistration: /sw.js with scope: '/'
 * - Clean migration from obsolete/competing registrations
 * - Subscription created strictly on the authoritative registration reference
 * - Safe diagnostic logging (zero secrets, zero private keys, hostname only)
 * - Explicit state lifecycle management
 */
(function() {
    'use strict';

    const PushStates = {
        UNSUPPORTED: 'UNSUPPORTED',
        DEFAULT: 'DEFAULT',
        REQUESTING: 'REQUESTING',
        CONNECTING: 'CONNECTING',
        CONNECTED: 'CONNECTED',
        BLOCKED: 'BLOCKED',
        ERROR: 'ERROR'
    };

    let currentState = PushStates.DEFAULT;
    const stateListeners = [];

    function notifyState(state, detail = null) {
        currentState = state;
        stateListeners.forEach(fn => {
            try { fn(state, detail); } catch (e) {}
        });
    }

    function isPushSupported() {
        return ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);
    }

    function urlB64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async function getVapidPublicKey() {
        const res = await fetch('/actions/push/public-key.php?_t=' + Date.now(), { cache: 'no-store' });
        if (!res.ok) {
            throw new Error('Failed to fetch VAPID public key: HTTP ' + res.status);
        }
        const data = await res.json();
        if (!data.success || !data.publicKey) {
            throw new Error(data.error || 'VAPID public key not found in server response.');
        }
        return data.publicKey;
    }

    /**
     * Inspect all active SW registrations, identify obsolete scopes,
     * safely log diagnostic values (NEVER expose secrets).
     */
    async function inspectAllRegistrations() {
        if (!('serviceWorker' in navigator)) return [];

        const rootScopeUrl = new URL('/', window.location.origin).href;
        const registrations = await navigator.serviceWorker.getRegistrations();
        const results = [];

        for (const reg of registrations) {
            const isRootScope = (reg.scope === rootScopeUrl);
            const activeScript = reg.active ? reg.active.scriptURL : null;
            const waitingScript = reg.waiting ? reg.waiting.scriptURL : null;
            const installingScript = reg.installing ? reg.installing.scriptURL : null;

            let hasSub = false;
            let endpointHostname = 'none';

            try {
                const sub = await reg.pushManager.getSubscription();
                if (sub && sub.endpoint) {
                    hasSub = true;
                    try {
                        endpointHostname = new URL(sub.endpoint).hostname;
                    } catch {
                        endpointHostname = 'invalid-url';
                    }
                }
            } catch (e) {}

            const isAuthoritative = isRootScope && (
                (activeScript && activeScript.endsWith('/sw.js')) ||
                (waitingScript && waitingScript.endsWith('/sw.js')) ||
                (installingScript && installingScript.endsWith('/sw.js'))
            );

            const info = {
                scope: reg.scope,
                activeScript: activeScript,
                waitingScript: waitingScript,
                installingScript: installingScript,
                hasSubscription: hasSub,
                endpointHostname: endpointHostname,
                isAuthoritative: isAuthoritative,
                rawRegistration: reg
            };

            // Safe diagnostic console log (NO auth, NO p256dh, NO private keys)
            console.log('[Mentry SW Diagnostic]', {
                scope: info.scope,
                activeScript: info.activeScript,
                waitingScript: info.waitingScript,
                installingScript: info.installingScript,
                subscriptionExists: info.hasSubscription,
                endpointHostname: info.endpointHostname,
                isAuthoritative: info.isAuthoritative
            });

            results.push(info);
        }

        return results;
    }

    /**
     * Clean up obsolete Mentry registrations.
     * Only unregisters workers that conflict with root /sw.js.
     */
    async function cleanObsoleteRegistrations() {
        const inspected = await inspectAllRegistrations();
        let unregisteredCount = 0;

        for (const item of inspected) {
            if (!item.isAuthoritative) {
                console.warn('[Mentry SW Migration] Unregistering obsolete registration:', item.scope, item.activeScript);
                try {
                    await item.rawRegistration.unregister();
                    unregisteredCount++;
                } catch (e) {
                    console.error('[Mentry SW Migration] Error unregistering obsolete worker:', e);
                }
            }
        }

        return unregisteredCount;
    }

    /**
     * Authoritative Service Worker registration.
     * Guaranteed to use navigator.serviceWorker.register('/sw.js', { scope: '/' })
     * and wait for active state before returning.
     */
    async function getAuthoritativeRegistration() {
        if (!('serviceWorker' in navigator)) {
            throw new Error('Service Workers are not supported in this browser.');
        }

        // 1. Clean conflicting/obsolete registrations first
        await cleanObsoleteRegistrations();

        // 2. Register strictly with root scope
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });

        // 3. Ensure worker activates
        if (registration.installing) {
            await new Promise(resolve => {
                const worker = registration.installing;
                const stateListener = () => {
                    if (worker.state === 'activated' || worker.state === 'redundant') {
                        worker.removeEventListener('statechange', stateListener);
                        resolve();
                    }
                };
                worker.addEventListener('statechange', stateListener);
                setTimeout(resolve, 3000); // 3s fallback
            });
        } else if (registration.waiting) {
            registration.waiting.postMessage({ type: 'SKIP_WAITING' });
        }

        try { registration.update(); } catch (e) {}

        return registration;
    }

    async function sendSubscriptionToServer(subscription) {
        const json = subscription.toJSON();
        const payload = {
            endpoint: subscription.endpoint,
            keys: {
                p256dh: json.keys ? json.keys.p256dh : '',
                auth: json.keys ? json.keys.auth : ''
            },
            platform: navigator.platform || 'Unknown',
            device: /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent) ? 'Mobile' : 'Desktop',
            browser: /Edg/i.test(navigator.userAgent) ? 'Edge' :
                     /Chrome/i.test(navigator.userAgent) ? 'Chrome' :
                     /Safari/i.test(navigator.userAgent) ? 'Safari' :
                     /Firefox/i.test(navigator.userAgent) ? 'Firefox' : 'Browser'
        };

        const res = await fetch('/actions/push/subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!res.ok) {
            throw new Error('Server returned HTTP ' + res.status + ' while saving subscription.');
        }

        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to register subscription on server.');
        }

        return data;
    }

    /**
     * Check current subscription status without prompting user
     */
    async function checkStatus() {
        if (!isPushSupported()) {
            notifyState(PushStates.UNSUPPORTED);
            return PushStates.UNSUPPORTED;
        }

        if (Notification.permission === 'denied') {
            notifyState(PushStates.BLOCKED);
            return PushStates.BLOCKED;
        }

        if (Notification.permission === 'default') {
            notifyState(PushStates.DEFAULT);
            return PushStates.DEFAULT;
        }

        try {
            const reg = await getAuthoritativeRegistration();
            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                notifyState(PushStates.CONNECTED, sub);
                return PushStates.CONNECTED;
            } else {
                notifyState(PushStates.DEFAULT);
                return PushStates.DEFAULT;
            }
        } catch (e) {
            notifyState(PushStates.ERROR, e);
            return PushStates.ERROR;
        }
    }

    /**
     * Enable or migrate push notifications on user gesture.
     * Strictly creates the subscription on the authoritative registration reference.
     */
    async function enable(forceMigrate = false) {
        if (!isPushSupported()) {
            notifyState(PushStates.UNSUPPORTED);
            return false;
        }

        if (Notification.permission === 'denied') {
            notifyState(PushStates.BLOCKED);
            return false;
        }

        try {
            notifyState(PushStates.REQUESTING);
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                notifyState(permission === 'denied' ? PushStates.BLOCKED : PushStates.DEFAULT);
                return false;
            }

            notifyState(PushStates.CONNECTING);

            // Authoritative registration reference
            const registration = await getAuthoritativeRegistration();
            const vapidKey = await getVapidPublicKey();
            const convertedKey = urlB64ToUint8Array(vapidKey);

            // Inspect subscription on THIS registration
            let sub = await registration.pushManager.getSubscription();

            if (sub) {
                const subKey = sub.options && sub.options.applicationServerKey;
                let keyMatches = false;
                if (subKey) {
                    const subBytes = new Uint8Array(subKey);
                    if (subBytes.length === convertedKey.length) {
                        keyMatches = subBytes.every((v, i) => v === convertedKey[i]);
                    }
                }

                if (forceMigrate || !keyMatches) {
                    console.log('[Mentry SW Migration] Unsubscribing stale subscription...');
                    try { await sub.unsubscribe(); } catch (e) {}
                    sub = null;
                }
            }

            if (!sub) {
                console.log('[Mentry SW Migration] Creating fresh subscription on authoritative registration...');
                // Strictly registration.pushManager.subscribe(...)
                sub = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedKey
                });
                localStorage.setItem('mentry_push_migrated_at', Date.now().toString());
            }

            await sendSubscriptionToServer(sub);
            notifyState(PushStates.CONNECTED, sub);
            return true;
        } catch (err) {
            console.error('[Mentry Push Controller] Subscription failed:', err);
            notifyState(PushStates.ERROR, err);
            return false;
        }
    }

    /**
     * Get diagnostic info safely for Admin Push Debugger or diagnostics UI.
     * NEVER returns private keys, auth, or full endpoints.
     */
    async function getDiagnostics() {
        if (!isPushSupported()) {
            return { supported: false };
        }

        try {
            const inspected = await inspectAllRegistrations();
            const authoritative = inspected.find(i => i.isAuthoritative) || null;
            const migratedAt = localStorage.getItem('mentry_push_migrated_at');

            return {
                supported: true,
                permission: Notification.permission,
                totalRegistrations: inspected.length,
                authoritativeExists: !!authoritative,
                activeScriptURL: authoritative?.activeScript || null,
                waitingScriptURL: authoritative?.waitingScript || null,
                installingScriptURL: authoritative?.installingScript || null,
                scope: authoritative?.scope || null,
                subscriptionExists: authoritative?.hasSubscription || false,
                endpointHostname: authoritative?.endpointHostname || 'none',
                migratedTimestamp: migratedAt ? new Date(parseInt(migratedAt, 10)).toISOString() : null,
                allRegistrations: inspected.map(i => ({
                    scope: i.scope,
                    activeScript: i.activeScript,
                    hasSubscription: i.hasSubscription,
                    endpointHostname: i.endpointHostname,
                    isAuthoritative: i.isAuthoritative
                }))
            };
        } catch (e) {
            return {
                supported: true,
                error: e.message || String(e)
            };
        }
    }

    // Expose global controller
    window.MentryPush = {
        States: PushStates,
        getState: () => currentState,
        onStateChange: (listener) => {
            if (typeof listener === 'function') {
                stateListeners.push(listener);
                listener(currentState);
            }
        },
        checkStatus,
        enable: (force = false) => enable(force),
        migrateSubscription: () => enable(true),
        getDiagnostics,
        isSupported: isPushSupported
    };

    // Auto-check on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkStatus);
    } else {
        checkStatus();
    }
})();
