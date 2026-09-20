// Mentry Solutions Service Worker (PWA App Shell, Caching & Offline Capabilities)
const MENTRY_SW_VERSION = 'mentry-pwa-v4';
const CACHE_NAME = 'mentry-pwa-v4';
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
                    keys.filter(k => k !== CACHE_NAME).map(k => {
                        console.log('[Mentry SW] Deleting stale cache:', k);
                        return caches.delete(k);
                    })
                );
            })
        ])
    );
});

// Cache-first for precached static assets, network-first for navigation
// Never cache opportunity share script or dynamic endpoints
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    // Pass through non-GET and cross-origin requests directly to network
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }
    // Force network fetch for opportunity-share.js to ensure immediate PWA freshness
    if (url.pathname.includes('opportunity-share.js')) {
        event.respondWith(fetch(event.request, { cache: 'no-cache' }));
        return;
    }
    // Precached assets served from cache with network fallback
    if (PRECACHE_ASSETS.some(asset => url.pathname.endsWith(asset.replace('./', '')))) {
        event.respondWith(
            caches.match(event.request).then(cached => cached || fetch(event.request))
        );
    }
});
