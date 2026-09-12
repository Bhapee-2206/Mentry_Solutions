// sw.js - Mentry Solutions PWA Service Worker & Web Push Engine
const CACHE_NAME = 'mentry-pwa-v4';
const ASSETS_TO_PRECACHE = [
  '/',
  '/manifest.json',
  '/public/icon-192.png',
  '/public/icon-512.png',
  '/public/icon-maskable-512.png',
  '/public/mentry-emblem.png',
  '/favicon.ico',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap',
  'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap'
];

// 1. Install: Precache essential brand and shell assets
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_PRECACHE).catch((err) => {
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
    icon: '/public/mentry.png',
    badge: '/public/mentry.png',
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

  const notificationOptions = {
    body: payload.body,
    icon: payload.icon || '/public/mentry.png',
    badge: payload.badge || '/public/mentry.png',
    tag: payload.tag || 'mentry-notification',
    renotify: true,
    vibrate: [100, 50, 100],
    data: {
      url: payload.url || '/'
    },
    actions: [
      { action: 'open', title: 'Open Mentry' }
    ]
  };

  event.waitUntil(
    self.registration.showNotification(payload.title, notificationOptions)
  );
});

// 5. Notification Click: Bring PWA to focus or navigate to target page
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // If a window is already open, focus it and navigate
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
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
});

// 6. Direct Client Notification Dispatch: Show native mobile/desktop notifications
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SHOW_NOTIFICATION') {
    const payload = event.data.payload || {};
    const notificationOptions = {
      body: payload.body || 'New update on your training portal.',
      icon: payload.icon || '/public/icon-192.png',
      badge: payload.badge || '/public/icon-192.png',
      tag: payload.tag || ('mentry-' + Date.now()),
      renotify: true,
      vibrate: [200, 100, 200],
      data: {
        url: payload.url || '/'
      },
      actions: [
        { action: 'open', title: 'Open Mentry' }
      ]
    };

    event.waitUntil(
      self.registration.showNotification(payload.title || 'Mentry Solutions', notificationOptions)
    );
  }
});

