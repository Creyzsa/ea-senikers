/**
 * Service Worker — Web Push notifikasi admin EA SENIKERS.
 * Getar via pola vibrate; bunyi lewat notifikasi sistem OS.
 */
'use strict';

var VIBRATE_POLA = [180, 90, 180, 90, 220];

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

    var promise = self.registration.showNotification(title, {
        body: body,
        tag: tag,
        renotify: true,
        vibrate: VIBRATE_POLA,
        icon: '/assets/images/easenikers.png',
        badge: '/assets/images/easenikers.png',
        data: { url: url, event_id: data.event_id || 0, order_id: data.order_id || 0 },
        requireInteraction: false
    }).then(function () {
        return self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    }).then(function (clients) {
        clients.forEach(function (client) {
            client.postMessage({
                type: 'easenikers-admin-push',
                payload: data
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