/*  sw.js — Service Worker for Web Push Notifications
 *  Handles push events (tickle) and notification clicks.
 *  v2 – cache-bust: navigate fix + force update
 */

const API_BASE = self.location.pathname.replace(/\/sw\.js$/, '') || '';

self.addEventListener('push', function (event) {
  let title   = 'Nova notificação';
  let options = {
    body   : 'Você tem uma nova notificação.',
    icon   : API_BASE + '/assets/img/icon-192.png',
    badge  : API_BASE + '/assets/img/badge-72.png',
    tag    : 'mercado-push',
    renotify: true,
    data   : { url: '/' }
  };

  /* If the push payload has JSON data (encrypted push), use it directly */
  if (event.data) {
    try {
      const d = event.data.json();
      title        = d.title || title;
      options.body = d.body  || options.body;
      if (d.url)  options.data = { url: d.url };
      if (d.icon) options.icon = d.icon;
    } catch (_) {
      /* Not JSON – attempt as plain text */
      const txt = event.data.text();
      if (txt) options.body = txt;
    }

    event.waitUntil(self.registration.showNotification(title, options));
    return;
  }

  /* Tickle push (no payload) — fetch latest notification from the API */
  event.waitUntil(
    fetch(API_BASE + '/api/notifications.php?action=list&limit=1', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (data.ok && data.items && data.items.length > 0) {
          const n = data.items[0];
          title        = n.titulo  || title;
          options.body = n.mensagem || options.body;
          if (n.link) options.data = { url: n.link };
        }
        return self.registration.showNotification(title, options);
      })
      .catch(() => self.registration.showNotification(title, options))
  );
});

/* ── Notification click — open/focus the relevant page ──────────── */
self.addEventListener('notificationclick', function (event) {
  event.notification.close();

  const rawUrl = event.notification.data?.url || '/';
  let target = new URL('/', self.location.origin);
  try {
    const candidate = new URL(rawUrl, self.location.origin);
    if (candidate.origin === self.location.origin) {
      target = candidate;
    }
  } catch (_) {}
  const fullUrl = target.href;

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      // Try to find an existing window on the same origin
      for (const client of list) {
        if (client.url.startsWith(self.location.origin) && 'navigate' in client) {
          return client.navigate(fullUrl).then(c => c.focus());
        }
      }
      // No existing window — open a new one
      return clients.openWindow(fullUrl);
    })
  );
});

/* ── Keep SW alive ─────────────────────────────────────────────── */
self.addEventListener('install',  () => self.skipWaiting());
self.addEventListener('activate', () => self.clients.claim());
