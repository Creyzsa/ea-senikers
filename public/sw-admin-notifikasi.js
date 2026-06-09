/**
 * Service Worker — Web Push notifikasi admin EA SENIKERS.
 * Getar + bunyi kustom (via tab terbuka) / bunyi OS (tab tertutup).
 */
'use strict';

var SW_VERSI = '2026-06-10-sound2';
var VIBRATE_POLA = [180, 90, 180, 90, 220];
var NOTIF_SOUND_URL = '/assets/sounds/admin-notif.mp3';

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

function parsePayload(event) {
    if (!event.data) {
        return null;
    }
    try {
        return event.data.json();
    } catch (e) {
        try {
            return JSON.parse(event.data.text());
        } catch (e2) {
            return null;
        }
    }
}

self.addEventListener('push', function (event) {
    var data = parsePayload(event) || {
        title: 'EA SENIKERS',
        body: 'Pembayaran masuk',
        url: '/admin/notifikasi_admin.php',
        tag: 'easenikers-push',
        event_id: 0
    };

    var title = data.title || 'Pembayaran masuk';
    var body = data.body || '';
    var url = data.url || '/admin/notifikasi_admin.php';
    var tag = data.tag || ('easenikers-event-' + (data.event_id || data.order_id || Date.now()));

    var soundUrl = data.sound_url || NOTIF_SOUND_URL;
    var notifOpts = {
        body: body,
        tag: tag,
        renotify: true,
        vibrate: VIBRATE_POLA,
        icon: '/assets/images/easenikers.png',
        badge: '/assets/images/easenikers.png',
        data: { url: url, event_id: data.event_id || 0, order_id: data.order_id || 0, sound_url: soundUrl },
        requireInteraction: false,
        silent: false
    };
    if (soundUrl) {
        notifOpts.sound = soundUrl;
    }

    var promise = self.registration.showNotification(title, notifOpts).then(function () {
        return self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    }).then(function (clients) {
        clients.forEach(function (client) {
            client.postMessage({
                type: 'easenikers-admin-push',
                payload: data,
                sound_url: soundUrl,
                play_sound: true
            });
        });
    });

    if (event.waitUntil) {
        event.waitUntil(promise);
    }
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/admin/notifikasi_admin.php';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
            for (var i = 0; i < clients.length; i++) {
                var client = clients[i];
                if ('focus' in client) {
                    if (client.url.indexOf(url) !== -1 || client.url.indexOf('/admin/') !== -1) {
                        return client.focus();
                    }
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});