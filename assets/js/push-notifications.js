/**
 * assets/js/push-notifications.js - Clean Push Notification Controller
 * Responsibilities:
 * - Register service worker
 * - Manage explicit states: UNSUPPORTED, DEFAULT, REQUESTING, CONNECTING, CONNECTED, BLOCKED, ERROR
 * - Request permission strictly on user gesture
 * - Fetch public key from /actions/push/public-key.php
 * - Save subscription to /actions/push/subscribe.php
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

    async function getOrRegisterServiceWorker() {
        let reg = await navigator.serviceWorker.getRegistration('/');
        if (!reg) {
            reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        }
        if (reg.waiting) {
            reg.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
        return await navigator.serviceWorker.ready;
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
     * Check current subscription status without triggering any prompts
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

        // Permission is granted: verify existing subscription
        try {
            const reg = await getOrRegisterServiceWorker();
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
     * Enable push notifications on explicit user action (click)
     */
    async function enable() {
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
            const reg = await getOrRegisterServiceWorker();
            const vapidKey = await getVapidPublicKey();
            const convertedKey = urlB64ToUint8Array(vapidKey);

            let sub = await reg.pushManager.getSubscription();

            // Step 3: If subscription exists, verify it matches the current VAPID key
            if (sub) {
                const subKey = sub.options && sub.options.applicationServerKey;
                let keyMatches = false;
                if (subKey) {
                    const subBytes = new Uint8Array(subKey);
                    if (subBytes.length === convertedKey.length) {
                        keyMatches = subBytes.every((v, i) => v === convertedKey[i]);
                    }
                }
                if (!keyMatches) {
                    // Unsubscribe old/stale subscription tied to outdated key or registration
                    try { await sub.unsubscribe(); } catch (e) {}
                    sub = null;
                }
            }

            if (!sub) {
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedKey
                });
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
        enable,
        isSupported: isPushSupported
    };

    // Auto-check on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkStatus);
    } else {
        checkStatus();
    }
})();
