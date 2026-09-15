// OneSignal Web Push SDK Worker Integration
importScripts("https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js");

const MENTRY_SW_VERSION = 'mentry-onesignal-v1';
const CACHE_NAME = 'mentry-onesignal-v1';
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
 */
self.addEventListener('push', event => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {}
    const testId = payload.id || ('push_' + Date.now());

    console.log('[Mentry PUSH DIAGNOSTIC] PUSH EVENT RECEIVED', testId);

    event.waitUntil(handlePush(event, payload, testId));
});

async function handlePush(event, data, testId) {
    if (!data) {
        try {
            data = event.data ? event.data.json() : {};
        } catch (e) {
            data = {};
        }
    }

    const title = data.title || 'Mentry';
    const body = data.body || 'You have a new notification.';

    let showSuccess = false;
    let showException = null;

    try {
        await self.registration.showNotification(title, {
            body,
            icon: '/public/push-icon.png',
            data: {
                id: data.id || null,
                type: data.type || 'GENERAL',
                url: data.url || '/trainer/notifications.php',
                testId: testId
            }
        });
        showSuccess = true;
    } catch (err) {
        showSuccess = false;
        showException = err.message || String(err);
        console.error('[Mentry PUSH DIAGNOSTIC] showNotification THREW:', err);
    }

    // Diagnostic logging strictly AFTER showNotification succeeds or throws
    const diagData = {
        testId: testId,
        receivedAt: new Date().toISOString(),
        showNotificationSuccess: showSuccess,
        showNotificationError: showException,
        swScope: self.registration.scope,
        scriptURL: self.location.href
    };

    try {
        const cache = await caches.open('mentry-push-diagnostics');
        await cache.put('/last-push-diag.json', new Response(JSON.stringify(diagData), {
            headers: { 'Content-Type': 'application/json' }
        }));
    } catch (e) {}

    try {
        await fetch('/actions/push/record-receipt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(diagData)
        });
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
