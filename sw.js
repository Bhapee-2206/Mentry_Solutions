// sw.js - Mentry Solutions PWA Service Worker & Web Push Engine
const CACHE_NAME = 'mentry-pwa-v7';
const ASSETS_TO_PRECACHE = [
  './manifest.json',
  './public/icon-192.png',
  './public/icon-512.png',
  './public/icon-maskable-512.png',
  './public/mentry-emblem.png',
  './favicon.ico',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap',
  'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap'
];

// 1. Install: Precache essential brand and shell assets
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      const scopeUrl = self.registration.scope;
      const urls = ASSETS_TO_PRECACHE.map(path => {
        return path.startsWith('http') ? path : new URL(path, scopeUrl).href;
      });
      return cache.addAll(urls).catch((err) => {
        console.warn('[Mentry SW] Precache partial fallback:', err);
      });
    })
  );
});

// 2. Activate: Purge obsolete caches and claim clients immediately
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// 3. Fetch: Network-first for dynamic routes, cache-first for static media
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Skip non-GET requests and browser extensions
  if (req.method !== 'GET' || !url.protocol.startsWith('http')) {
    return;
  }

  // API endpoints and actions are always network-only
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/actions/')) {
    return;
  }

  // Static assets (images, fonts, css, js): Cache first with network fallback
  if (/\.(png|jpg|jpeg|svg|ico|webp|woff2|woff|ttf|css)$/i.test(url.pathname)) {
    event.respondWith(
      caches.match(req).then((cached) => {
        if (cached) return cached;
        return fetch(req).then((response) => {
          if (response && response.status === 200) {
            const respClone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, respClone));
          }
          return response;
        }).catch(() => caches.match('/public/mentry.png'));
      })
    );
    return;
  }

  // HTML and Dynamic Pages: Network first, fall back to cache
  event.respondWith(
    fetch(req).then((response) => {
      if (response && response.status === 200) {
        const respClone = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(req, respClone));
      }
      return response;
    }).catch(() => {
      return caches.match(req).then((cached) => {
        return cached || caches.match('/');
      });
    })
  );
});

// 4. Push Event: Handle background Web Push Notifications from server
self.addEventListener('push', (event) => {
  let payload = {
    title: 'Mentry Solutions',
    body: 'New update on your training portal.',
    icon: '/public/icon-192.png',
    url: '/',
    tag: 'mentry-general'
  };

  if (event.data) {
    try {
      const data = event.data.json();
      payload = Object.assign(payload, data);
    } catch (e) {
      payload.body = event.data.text();
    }
  }

  const notificationId = payload.id || (payload.data && payload.data.id) || ('mentry-' + Date.now());
  const targetUrl = payload.url || (payload.data && payload.data.url) || '/';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      const iconUrl = payload.icon ? new URL(payload.icon, self.registration.scope).href : new URL('public/icon-192.png', self.registration.scope).href;
      const notificationOptions = {
        body: payload.body,
        icon: iconUrl,
        badge: iconUrl,
        tag: 'mentry-' + notificationId,
        renotify: true,
        vibrate: [200, 100, 200],
        data: {
          id: notificationId,
          url: targetUrl
        },
        actions: [
          { action: 'open', title: 'Open Mentry' }
        ]
      };

      // Always display native system push notification on device
      const showPromise = self.registration.showNotification(payload.title, notificationOptions);

      // Also forward payload to open window clients for in-app UI update
      const activeClient = clientList.find(c => c.visibilityState === 'visible');
      if (activeClient) {
        activeClient.postMessage({
          type: 'PUSH_RECEIVED_IN_APP',
          notification: {
            id: notificationId,
            title: payload.title,
            body: payload.body,
            message: payload.body,
            url: targetUrl,
            link: targetUrl,
            type: payload.type || (payload.data && payload.data.type) || 'GENERAL',
            matchScore: payload.matchScore || (payload.data && payload.data.matchScore) || null
          }
        });
      }

      return showPromise;
    })
  );
});

// 5. Notification Click: Bring PWA to focus or navigate to target opportunity/page
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) 
    ? event.notification.data.url 
    : '/';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // If a window is already open within our origin, focus it and navigate
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if (client.url && 'focus' in client) {
          if (client.url.includes(self.location.origin)) {
            client.focus();
            if (client.navigate) {
              return client.navigate(targetUrl);
            }
            return;
          }
        }
      }
      // Otherwise open a new standalone window
      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl);
      }
    })
  );
});

// 6. Service Worker Message Listener (Handles skipWaiting and client coordination)
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
