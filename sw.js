// sw.js - Clean Service Worker for Mentry Solutions
// Authoritative Web Push implementation
// Version: mentry-push-v2

const MENTRY_SW_VERSION = 'mentry-push-v2';
const CACHE_NAME = 'mentry-push-v2';
const PRECACHE_ASSETS = [
    './manifest.json',
    './public/push-icon.png',
    './favicon.ico'
];

self.addEventListener('install', event => {
    console.log('[Mentry SW] INSTALLING:', MENTRY_SW_VERSION);
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(PRECACHE_ASSETS).catch(() => {});
        })
    );
});

self.addEventListener('activate', event => {
    console.log('[Mentry SW] ACTIVATING:', MENTRY_SW_VERSION);
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
 * AUTHORITATIVE BACKGROUND PUSH EVENT (MINIMAL & ROBUST)
 * ========================================================
 * Must NOT depend on:
 * - clients.matchAll()
 * - visibilityState
 * - focused
 * - window / document
 * - whether PWA is open / closed / backgrounded
 */
self.addEventListener('push', event => {
    console.log('[Mentry SW] PUSH EVENT RECEIVED');

    event.waitUntil(
        handlePush(event)
    );
});

async function handlePush(event) {
    const receivedTs = Date.now();
    let payload = {};

    try {
        if (event.data) {
            payload = event.data.json();
        }
    } catch (error) {
        console.error('[Mentry SW] Payload JSON failed', error);
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

    const notificationId = payload.id || ('push-' + receivedTs);
    const title = payload.title || 'Mentry Solutions';
    const body = payload.body || payload.message || 'You have a new notification.';
    const url = payload.url || '/';

    console.log('[Mentry SW] Preparing native notification', {
        id: notificationId,
        title,
        body,
        url
    });

    let safeUrl;
    try {
        safeUrl = new URL(url, self.registration.scope);
        const scopeUrl = new URL(self.registration.scope);
        if (safeUrl.origin !== scopeUrl.origin) {
            safeUrl = new URL('/', self.registration.scope);
        }
    } catch {
        safeUrl = new URL('/', self.registration.scope);
    }

    // Step 10: Safe Diagnostic Logging (records ONLY timing and safe ID, zero credentials)
    const diagStartTs = Date.now();
    try {
        await self.registration.showNotification(
            title,
            {
                body: body,
                icon: '/public/push-icon.png',
                tag: 'mentry-' + notificationId,
                data: {
                    id: notificationId,
                    type: payload.type || 'GENERAL',
                    url: safeUrl.href
                }
            }
        );

        console.log('[Mentry SW] showNotification RESOLVED', {
            id: notificationId,
            durationMs: Date.now() - diagStartTs
        });

        // Record diagnostic state in background cache/store for inspectability
        recordDiagnosticState({
            notificationId,
            receivedAt: receivedTs,
            showNotificationStartedAt: diagStartTs,
            showNotificationResolvedAt: Date.now(),
            status: 'RESOLVED',
            error: null
        });

    } catch (err) {
        console.error('[Mentry SW] showNotification FAILED', err);

        // Record failed state
        recordDiagnosticState({
            notificationId,
            receivedAt: receivedTs,
            showNotificationStartedAt: diagStartTs,
            showNotificationFailedAt: Date.now(),
            status: 'FAILED',
            error: err.message || String(err)
        });

        // Fallback minimal notification without image if asset decoding fails on Android
        try {
            await self.registration.showNotification(
                title,
                {
                    body: body,
                    tag: 'mentry-' + notificationId,
                    data: {
                        id: notificationId,
                        type: payload.type || 'GENERAL',
                        url: safeUrl.href
                    }
                }
            );
            console.log('[Mentry SW] Fallback showNotification RESOLVED');
        } catch (fallbackErr) {
            console.error('[Mentry SW] Fallback showNotification also failed:', fallbackErr);
        }
    }
}

/**
 * Record safe diagnostic state (zero endpoints, zero keys, zero payloads)
 */
async function recordDiagnosticState(diag) {
    try {
        const cache = await caches.open('mentry-sw-diagnostics');
        const resp = new Response(JSON.stringify(diag), {
            headers: { 'Content-Type': 'application/json' }
        });
        await cache.put('/sw-diag-last.json', resp);
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
            for (const client of clients) {
                try {
                    const clientUrl = new URL(client.url);
                    if (clientUrl.origin === targetUrl.origin && 'focus' in client) {
                        client.navigate(targetUrl.href);
                        return client.focus();
                    }
                } catch (e) {}
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl.href);
            }
        })
    );
});
