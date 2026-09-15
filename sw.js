// sw.js - Clean Service Worker for Mentry Solutions
// Authoritative Web Push implementation
// Version: mentry-push-v2

const MENTRY_SW_VERSION = 'mentry-push-v3';
const CACHE_NAME = 'mentry-push-v3';
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
    console.log('[Mentry SW] PUSH EVENT RECEIVED');
    event.waitUntil(handlePush(event));
});

async function handlePush(event) {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        console.error('[Mentry SW] Push payload parse failed', e);
    }

    const title = data.title || 'Mentry';
    const body = data.body || 'You have a new notification.';

    await self.registration.showNotification(title, {
        body,
        icon: '/public/push-icon.png',
        data: {
            id: data.id || null,
            type: data.type || 'GENERAL',
            url: data.url || '/trainer/notifications.php'
        }
    });
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
