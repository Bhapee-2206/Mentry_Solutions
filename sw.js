// sw.js - Mentry Solutions PWA Service Worker & Web Push Engine
const CACHE_NAME = 'mentry-pwa-v18';
const ASSETS_TO_PRECACHE = [
  './manifest.json',
  './public/icon-192.png',
  './public/icon-512.png',
  './public/icon-maskable-512.png',
  './public/notification-icon-192.png',
  './public/mentry-badge-96.png',
  './public/badge-96.png',
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

  // API endpoints, actions, and PHP scripts are always network-only
  if (url.pathname.includes('/api/') || url.pathname.includes('/actions/') || url.pathname.endsWith('.php')) {
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
//
// Authoritative Routing:
// - CASE A (Foreground): User currently has Mentry open and visible/focused.
//   Deliver to the active in-app window via postMessage AND ensure native OS notification triggers.
// - CASE B (Background): Mentry is closed, backgrounded, or screen locked.
//   Show exactly ONE native OS notification via self.registration.showNotification().
//
// Helper to safely resolve absolute asset URLs in Service Worker scope
function resolveSwAssetUrl(path, fallbackRelative) {
  let target = path || fallbackRelative || 'public/icon-192.png';
  if (/^https?:\/\//i.test(target)) {
    try {
      const parsed = new URL(target);
      const scopeUrl = new URL(self.registration.scope);
      if (scopeUrl.pathname && scopeUrl.pathname !== '/' && !parsed.pathname.startsWith(scopeUrl.pathname)) {
        const clean = parsed.pathname.replace(/^\/+/, '').replace(/^Mentry(?:%20|\s+)solution\/+/i, '');
        return new URL(clean, self.registration.scope).href;
      }
    } catch (e) {}
    return target;
  }
  if (/^data:/i.test(target)) {
    return target;
  }
  let clean = target.replace(/^\/+/, '');
  clean = clean.replace(/^Mentry(?:%20|\s+)solution\/+/i, '');
  try {
    return new URL(clean, self.registration.scope).href;
  } catch (e) {
    try {
      return new URL(clean, self.location.origin).href;
    } catch (err) {
      return target;
    }
  }
}

// 4. Push Event: Handle background Web Push Notifications from server
self.addEventListener('push', (event) => {
  let payload = {
    notification_id: 'mentry_' + Date.now(),
    type: 'GENERAL',
    title: 'Mentry Solutions',
    body: 'New update on your training portal.',
    icon: 'public/icon-192.png',
    badge: 'public/badge-96.png',
    url: '/'
  };

  if (event.data) {
    try {
      const data = event.data.json();
      payload = Object.assign(payload, data);
    } catch (e) {
      try {
        payload.body = event.data.text();
      } catch (err) {}
    }
  }

  const notificationId = String(payload.notification_id || payload.id || (payload.data && (payload.data.notification_id || payload.data.id)) || ('mentry_' + Date.now()));
  const notifType = payload.type || (payload.data && payload.data.type) || 'GENERAL';
  const targetUrl = payload.url || payload.link || (payload.data && (payload.data.url || payload.data.link)) || '/';

  // Safely resolve full URLs for icon and badge
  // icon = full-color Mentry logo (shown in notification panel)
  // badge = monochrome silhouette on transparent bg (Android status bar)
  const iconUrl = resolveSwAssetUrl(payload.icon, 'public/icon-192.png');
  const badgeUrl = resolveSwAssetUrl(payload.badge, 'public/badge-96.png');

  const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');

  const primaryOptions = {
    body: payload.body || 'New update on your training portal.',
    icon: iconUrl,
    badge: badgeUrl,
    tag: 'mentry-' + notificationId,
    renotify: true,
    vibrate: [200, 100, 200],
    data: {
      notification_id: notificationId,
      id: notificationId,
      type: notifType,
      url: targetUrl,
      opportunity_id: payload.opportunity_id || (payload.data && payload.data.opportunity_id) || null,
      timestamp: Date.now()
    }
  };

  // Only use requireInteraction on Desktop browsers (fails on mobile)
  if (!isMobile) {
    primaryOptions.requireInteraction = true;
  }

  // Safe notification dispatch with automatic fallback so push NEVER fails silently
  const showNotificationPromise = self.registration.showNotification(payload.title || 'Mentry Solutions', primaryOptions)
    .catch((err) => {
      console.warn('[Mentry SW] Primary showNotification failed, retrying minimal options:', err);
      return self.registration.showNotification(payload.title || 'Mentry Solutions', {
        body: payload.body || 'New update on your training portal.',
        icon: iconUrl,
        tag: 'mentry-' + notificationId,
        data: {
          notification_id: notificationId,
          url: targetUrl
        }
      }).catch((fallbackErr) => {
        console.error('[Mentry SW] Fallback showNotification failed:', fallbackErr);
      });
    });

  // Also notify any open client windows so in-app counters update simultaneously
  const notifyClientsPromise = self.clients.matchAll({ type: 'window', includeUncontrolled: true })
    .then((clientList) => {
      clientList.forEach((client) => {
        try {
          client.postMessage({
            type: 'PUSH_NOTIFICATION_DELIVERED',
            notification: {
              notification_id: notificationId,
              id: notificationId,
              type: notifType,
              title: payload.title,
              body: payload.body,
              message: payload.body,
              url: targetUrl,
              link: targetUrl,
              opportunity_id: payload.opportunity_id || null,
              matchScore: payload.matchScore || (payload.data && payload.data.matchScore) || null,
              tag: 'mentry-' + notificationId
            }
          });
        } catch (e) {}
      });
    }).catch(() => {});

  event.waitUntil(Promise.all([showNotificationPromise, notifyClientsPromise]));
});

// 5. Notification Click: Bring PWA to focus or navigate to target opportunity/page
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  let targetUrl = (event.notification.data && event.notification.data.url) 
    ? event.notification.data.url 
    : '/';

  // Ensure absolute URL matching the exact Service Worker registration scope
  let finalUrl;
  try {
    if (targetUrl.startsWith('http://') || targetUrl.startsWith('https://')) {
      finalUrl = targetUrl;
    } else {
      let cleanTarget = targetUrl.replace(/^\/+/, '');
      cleanTarget = cleanTarget.replace(/^Mentry(?:%20|\s+)solution\/+/i, '');
      finalUrl = new URL(cleanTarget, self.registration.scope).href;
    }
  } catch (e) {
    finalUrl = self.registration.scope;
  }

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // If a window is already open within our origin, focus it and navigate
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if (client.url && 'focus' in client) {
          if (client.url.includes(self.location.origin)) {
            client.focus();
            if (client.navigate) {
              return client.navigate(finalUrl);
            }
            return;
          }
        }
      }
      // Otherwise open a new standalone window
      if (self.clients.openWindow) {
        return self.clients.openWindow(finalUrl);
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
