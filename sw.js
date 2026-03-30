// CEAS Service Worker v1.0
const CACHE_NAME = 'ceas-v1';
const CACHE_URLS = ['/login.php', '/ceas-dashboard.php', '/manifest.json'];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(CACHE_URLS).catch(() => {}))
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request).catch(() =>
      caches.match(event.request)
    )
  );
});

// Handle push notifications
self.addEventListener('push', event => {
  if (!event.data) return;
  const data = event.data.json();
  event.waitUntil(
    self.registration.showNotification(data.title || 'CEAS Alert', {
      body: data.body,
      icon: '/icons/icon-192.png',
      badge: '/icons/badge.png',
      tag: data.tag || 'ceas-alert',
      requireInteraction: data.severity === 'severe' || data.severity === 'high',
      vibrate: [200, 100, 200, 100, 200],
      data: { url: data.url || '/ceas-dashboard.php' }
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then(clientList => {
      for (const client of clientList) {
        if ('focus' in client) return client.focus();
      }
      if (clients.openWindow) return clients.openWindow('/ceas-dashboard.php');
    })
  );
});
