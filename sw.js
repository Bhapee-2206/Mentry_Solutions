// sw.js - Clean Service Worker for Mentry Solutions
// Authoritative Web Push implementation
// Version: mentry-push-v1

const CACHE_NAME = 'mentry-push-v1';
const PRECACHE_ASSETS = [
    './manifest.json',
    './public/push-icon.png',
    './favicon.ico'
];

self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(PRECACHE_ASSETS).catch(() => {});
        })
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            caches.keys().then(keys => {
                return Promise.all(
                    keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
                );
            })
        ])
    );
});

/*
 * ========================================================
 * AUTHORITATIVE PUSH EVENT
 * ========================================================
 * Native notification creation is unconditional and executes FIRST.
 * Does NOT depend on window focus, client visibility, or page state.
 */
self.addEventListener('push', event => {
    event.waitUntil(handlePush(event));
});

async function handlePush(event) {
    let payload = {};

    try {
        if (event.data) {
            payload = event.data.json();
        }
    } catch (e) {
        try {
            payload = {
                body: event.data ? event.data.text() : 'You have a new notification.'
            };
        } catch {
            payload = {
                body: 'You have a new notification.'
            };
        }
    }

    const notificationId = payload.id || ('mentry_' + Date.now());
    const title = payload.title || 'Mentry Solutions';
    const body = payload.body || 'You have a new notification.';
    const targetUrl = payload.url || '/';

    let safeUrl;
    try {
        safeUrl = new URL(targetUrl, self.registration.scope);
        const scopeUrl = new URL(self.registration.scope);
        if (safeUrl.origin !== scopeUrl.origin) {
            safeUrl = new URL('/', self.registration.scope);
        }
    } catch {
        safeUrl = new URL('/', self.registration.scope);
    }

    // Unconditional Native Notification First
    // Phase 6 & Phase 13: Badge is omitted to prevent Android notification bar white squares.
    // Clean transparent 192x192 icon used.
    try {
        await self.registration.showNotification(title, {
            body: body,
            icon: '/public/push-icon.png',
            tag: 'mentry-' + notificationId,
            data: {
                id: notificationId,
                type: payload.type || 'GENERAL',
                url: safeUrl.href
            }
        });
        console.log('[Mentry SW] Native notification shown:', notificationId);
    } catch (err) {
        console.error('[Mentry SW] showNotification error:', err);
        // Fallback minimal notification without image if asset fails on older Android webview
        try {
            await self.registration.showNotification(title, {
                body: body,
                tag: 'mentry-' + notificationId,
                data: {
                    id: notificationId,
                    type: payload.type || 'GENERAL',
                    url: safeUrl.href
                }
            });
        } catch (fallbackErr) {
            console.error('[Mentry SW] Fallback showNotification failed:', fallbackErr);
        }
    }

    // Optional: Notify open client windows AFTER native notification has resolved
    try {
        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of clients) {
            client.postMessage({
                type: 'PUSH_RECEIVED',
                notification: {
                    id: notificationId,
                    title: title,
                    body: body,
                    url: safeUrl.href,
                    type: payload.type || 'GENERAL'
                }
            });
        }
    } catch (e) {}
}

/*
 * ========================================================
 * NOTIFICATION CLICK EVENT
 * ========================================================
 */
self.addEventListener('notificationclick', event => {
    event.notification.close();

    const notifData = event.notification.data || {};
    const rawUrl = notifData.url || '/';

    let targetUrl;
    try {
        targetUrl = new URL(rawUrl, self.registration.scope);
        const scopeUrl = new URL(self.registration.scope);
        if (targetUrl.origin !== scopeUrl.origin) {
            targetUrl = new URL('/', self.registration.scope);
        }
    } catch {
        targetUrl = new URL('/', self.registration.scope);
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clients => {
            // If window already open on same origin, focus it and navigate
            for (const client of clients) {
                try {
                    const clientUrl = new URL(client.url);
                    if (clientUrl.origin === targetUrl.origin && 'focus' in client) {
                        client.navigate(targetUrl.href);
                        return client.focus();
                    }
                } catch (e) {}
            }
            // Otherwise open a new window
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl.href);
            }
        })
    );
});
