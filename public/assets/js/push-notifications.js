/**
 * push-notifications.js
 * ─────────────────────
 * • Registers the Service Worker and subscribes to Web Push (VAPID)
 * • Implements the notification-bell dropdown: load, filter, mark-read
 * • Polls for new notifications every 30 s (fallback when push unavailable)
 */
(function () {
  'use strict';

  if (window.__BASEFY_PUSH_NOTIFICATIONS_INIT) return;
  window.__BASEFY_PUSH_NOTIFICATIONS_INIT = true;

  var BASE     = window.__BASE_PATH || '';
  var API      = BASE + '/api/notifications.php';
  var PUSH_API = BASE + '/api/push_subscribe.php';
  var NOTIF_ICON = window.location.origin + BASE + '/assets/img/icon-192.png';
  var NOTIF_BADGE = window.location.origin + BASE + '/assets/img/badge-72.png';

  /* ── State ────────────────────────────────────────────────────── */
  var lastSeenCount     = parseInt(document.getElementById('notifBadge')?.textContent || '0', 10);
  var currentFilter     = 'all';
  var soundEnabled      = localStorage.getItem('notif_sound') !== '0';
  var swReg             = null;
  var allItems          = [];       // cached notification list

  /* ══════════════════════════════════════════════════════════════
   * Service Worker + Push Subscription
   * ══════════════════════════════════════════════════════════════ */

  function registerSW() {
    if (!('serviceWorker' in navigator)) return;

    navigator.serviceWorker.register(BASE + '/sw.js')
      .then(function (reg) {
        swReg = reg;
        console.log('[Push] SW registered, scope:', reg.scope);
        // Force update check to pick up new SW versions
        reg.update().catch(function(){});
        if ('PushManager' in window) subscribeToPush();
      })
      .catch(function (err) {
        console.warn('[Push] SW registration failed:', err);
      });
  }

  function subscribeToPush() {
    if (!swReg) return;
    if (!('Notification' in window)) return;

    // Don't auto-prompt — wait for user gesture (see requestPermOnce below)
    if (Notification.permission !== 'granted') return;

    swReg.pushManager.getSubscription().then(function (sub) {
      if (sub) return; // already subscribed

      // Fetch VAPID public key from server
      return fetch(PUSH_API + '?action=vapid_key')
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok || !data.publicKey) return;
          var key = urlBase64ToUint8Array(data.publicKey);
          return swReg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
        })
        .then(function (newSub) {
          if (!newSub) return;
          var keys = newSub.toJSON().keys;
          return fetch(PUSH_API, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body   : JSON.stringify({
              action  : 'subscribe',
              endpoint: newSub.endpoint,
              p256dh  : keys.p256dh,
              auth    : keys.auth
            })
          });
        })
        .then(function () { console.log('[Push] Subscribed to push'); });
    }).catch(function (err) { console.warn('[Push] Subscribe error:', err); });
  }

  function urlBase64ToUint8Array(b64) {
    var padding = '='.repeat((4 - b64.length % 4) % 4);
    var base64  = (b64 + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw     = atob(base64);
    var out     = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }

  if (window.__BASEFY_PUSH_ONLY) {
    registerSW();
    document.addEventListener('click', requestPermissionOnce, { once: true });
    return;
  }

  /* ══════════════════════════════════════════════════════════════
   * Notification Bell Dropdown
   * ══════════════════════════════════════════════════════════════ */

  function loadNotifications() {
    fetch(API + '?action=list&limit=30')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        allItems = data.items || [];
        renderList(data.unread);
      })
      .catch(function (err) { console.error('[Notif] Load error:', err); });
  }

  function renderList(unread) {
    var container = document.getElementById('notifList');
    var badge     = document.getElementById('notifBadge');
    var bellBtn   = document.getElementById('notifBellBtn');

    // Badge
    if (badge) {
      badge.textContent = unread;
      badge.classList.toggle('hidden', unread === 0);
    }

    // Bell button highlight
    if (bellBtn) {
      if (unread > 0) {
        bellBtn.classList.add('border-yellow-400/30', 'bg-yellow-500/[0.06]', 'text-yellow-400');
        bellBtn.classList.remove('border-blackx3', 'text-zinc-400');
      } else {
        bellBtn.classList.remove('border-yellow-400/30', 'bg-yellow-500/[0.06]', 'text-yellow-400');
        bellBtn.classList.add('border-blackx3', 'text-zinc-400');
      }
    }

    if (!container) return;

    // Apply type filter
    var items = allItems;
    if (currentFilter !== 'all') {
      items = allItems.filter(function (i) { return i.tipo === currentFilter; });
    }

    if (items.length === 0) {
      container.innerHTML = '<div class="p-6 text-center text-zinc-500 text-sm">Nenhuma notificação</div>';
      return;
    }

    var icons = { anuncio: 'megaphone', venda: 'shopping-bag', chat: 'message-circle', ticket: 'flag' };

    container.innerHTML = items.map(function (n) {
      var unread = n.lida === false || n.lida === 'f' || n.lida === 0 || n.lida === '0';
      var icon   = icons[n.tipo] || 'bell';
      var time   = timeAgo(n.criado_em);
      var href   = n.link || '#';

      return '<a href="' + esc(href) + '" onclick="markNotifRead(' + n.id + ')" ' +
        'class="flex gap-3 px-3 py-2.5 hover:bg-white/[0.03] transition ' + (unread ? 'bg-white/[0.02]' : 'opacity-60') + '">' +
        '<div class="shrink-0 mt-0.5 w-8 h-8 rounded-lg flex items-center justify-center ' + (unread ? 'bg-greenx/10 text-greenx' : 'bg-white/5 text-zinc-500') + '">' +
          '<i data-lucide="' + icon + '" class="w-4 h-4"></i>' +
        '</div>' +
        '<div class="min-w-0 flex-1">' +
          '<p class="text-sm font-medium truncate ' + (unread ? 'text-white' : 'text-zinc-400') + '">' + esc(n.titulo) + '</p>' +
          (n.mensagem ? '<p class="text-xs text-zinc-500 truncate">' + esc(n.mensagem) + '</p>' : '') +
          '<p class="text-[10px] text-zinc-600 mt-0.5">' + time + '</p>' +
        '</div>' +
        (unread ? '<div class="shrink-0 w-2 h-2 rounded-full bg-greenx mt-2"></div>' : '') +
      '</a>';
    }).join('');

    // Re-render Lucide icons inside the dropdown
    if (window.lucide) window.lucide.createIcons({ nodes: [container] });
  }

  /* ── Helpers ──────────────────────────────────────────────────── */

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function timeAgo(str) {
    var d    = new Date(str);
    var diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 60)    return 'agora';
    if (diff < 3600)  return Math.floor(diff / 60) + 'm atrás';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h atrás';
    return Math.floor(diff / 86400) + 'd atrás';
  }

  function playSound() {
    try {
      // Simple beep using Web Audio API (no external file needed)
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = 880;
      gain.gain.value = 0.12;
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
      osc.stop(ctx.currentTime + 0.35);
    } catch (_) {}
  }

  /* ══════════════════════════════════════════════════════════════
   * Global Functions  (called from onclick in the HTML)
   * ══════════════════════════════════════════════════════════════ */

  window.filterNotif = function (type) {
    currentFilter = type;
    document.querySelectorAll('.notif-tab').forEach(function (tab) {
      var active = (tab.dataset.tab === type);
      tab.classList.toggle('bg-greenx/10', active);
      tab.classList.toggle('text-greenx', active);
      tab.classList.toggle('text-zinc-400', !active);
    });
    renderList(parseInt(document.getElementById('notifBadge')?.textContent || '0', 10));
  };

  window.markNotifRead = function (id) {
    var body = new FormData();
    body.append('action', 'read');
    body.append('id', id);
    fetch(API, { method: 'POST', body: body }).catch(function () {});
  };

  window.markAllNotifRead = function () {
    var body = new FormData();
    body.append('action', 'read_all');
    fetch(API, { method: 'POST', body: body })
      .then(function () { loadNotifications(); })
      .catch(function () {});
  };

  window.toggleNotifSound = function () {
    soundEnabled = !soundEnabled;
    localStorage.setItem('notif_sound', soundEnabled ? '1' : '0');
    var on  = document.querySelector('.notif-sound-on');
    var off = document.querySelector('.notif-sound-off');
    if (on)  on.classList.toggle('hidden', !soundEnabled);
    if (off) off.classList.toggle('hidden', soundEnabled);
  };

  /* ══════════════════════════════════════════════════════════════
   * Polling  (fallback + in-tab real-time badge update)
   * ══════════════════════════════════════════════════════════════ */

  function poll() {
    fetch(API + '?action=count')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        var n = data.total;

        if (n > lastSeenCount && lastSeenCount >= 0) {
          if (soundEnabled) playSound();

          // Browser notification (if push SW not handling it already)
          if (Notification.permission === 'granted' && !swReg) {
            var diff = n - lastSeenCount;
            new Notification('Basefy', {
              body: 'Você tem ' + diff + ' nova' + (diff > 1 ? 's' : '') + ' notificaç' + (diff > 1 ? 'ões' : 'ão'),
              icon: NOTIF_ICON,
              badge: NOTIF_BADGE,
              image: NOTIF_ICON
            });
          }
        }

        lastSeenCount = n;

        var badge = document.getElementById('notifBadge');
        if (badge) {
          badge.textContent = n;
          badge.classList.toggle('hidden', n === 0);
        }
      })
      .catch(function () {});
  }

  /* ══════════════════════════════════════════════════════════════
   * Init
   * ══════════════════════════════════════════════════════════════ */

  // Sound toggle UI init
  if (!soundEnabled) {
    var sOn  = document.querySelector('.notif-sound-on');
    var sOff = document.querySelector('.notif-sound-off');
    if (sOn)  sOn.classList.add('hidden');
    if (sOff) sOff.classList.remove('hidden');
  }

  // Load notifications when bell is clicked / dropdown opens
  var bell = document.getElementById('notifBellBtn');
  if (bell) {
    bell.addEventListener('click', function () { setTimeout(loadNotifications, 80); });
  }
  // also listen Alpine dispatch
  document.addEventListener('notif-open', function () { setTimeout(loadNotifications, 80); });

  // Poll every 30 s
  setInterval(poll, 30000);

  // Register Service Worker
  registerSW();

  // Ask notification permission on first user interaction
  function requestPermissionOnce() {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'default') {
      Notification.requestPermission().then(function (perm) {
        if (perm === 'granted') subscribeToPush();
      });
    }
  }

  document.addEventListener('click', requestPermissionOnce, { once: true });

})();
