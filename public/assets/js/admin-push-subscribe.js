/**
 * Langganan Web Push admin + UI status di halaman notifikasi.
 */
(function () {
    'use strict';

    var panel = document.getElementById('admin-push-panel');
    if (!panel) {
        return;
    }

    var vapidUrl = panel.getAttribute('data-vapid-url');
    var subscribeUrl = panel.getAttribute('data-subscribe-url');
    var swUrl = panel.getAttribute('data-sw-url') || '/sw-admin-notifikasi.js';
    var btnAktifkan = document.getElementById('admin-push-aktifkan');
    var btnNonaktifkan = document.getElementById('admin-push-nonaktifkan');
    var statusEl = document.getElementById('admin-push-status');

    function setStatus(teks, jenis) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = teks;
        statusEl.className = 'admin-push-status admin-push-status--' + (jenis || 'netral');
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) {
            arr[i] = raw.charCodeAt(i);
        }
        return arr;
    }

    function cekDukungan() {
        return 'serviceWorker' in navigator
            && 'PushManager' in window
            && 'Notification' in window;
    }

    function ambilPublicKey() {
        return fetch(vapidUrl, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.json();
        }).then(function (data) {
            if (!data || !data.ok || !data.public_key) {
                throw new Error((data && data.pesan) || 'VAPID tidak tersedia');
            }
            return data.public_key;
        });
    }

    function kirimLangganan(subscription, aksi) {
        var body = aksi === 'unsubscribe'
            ? { aksi: 'unsubscribe', endpoint: subscription.endpoint }
            : subscription.toJSON();

        return fetch(subscribeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            },
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json();
        });
    }

    function perbaruiTombol(subscription) {
        if (btnAktifkan) {
            btnAktifkan.hidden = !!subscription;
        }
        if (btnNonaktifkan) {
            btnNonaktifkan.hidden = !subscription;
        }
        if (subscription) {
            setStatus('Push aktif di perangkat ini. Notifikasi akan muncul meski tab ditutup.', 'sukses');
        } else if (Notification.permission === 'denied') {
            setStatus('Izin notifikasi diblokir browser. Buka pengaturan situs lalu izinkan notifikasi.', 'error');
        } else {
            setStatus('Push belum diaktifkan di perangkat ini.', 'netral');
        }
    }

    function daftarSw() {
        return navigator.serviceWorker.register(swUrl, { scope: '/' })
            .then(function (reg) {
                if (reg.installing) {
                    return new Promise(function (resolve) {
                        reg.installing.addEventListener('statechange', function (e) {
                            if (e.target.state === 'activated') {
                                resolve(reg);
                            }
                        });
                    });
                }
                return reg.ready ? reg.ready : reg;
            });
    }

    function aktifkanPush() {
        if (!cekDukungan()) {
            setStatus('Browser tidak mendukung Web Push.', 'error');
            return;
        }

        setStatus('Meminta izin notifikasi…', 'netral');

        Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                setStatus('Izin notifikasi ditolak.', 'error');
                return null;
            }
            return daftarSw();
        }).then(function (reg) {
            if (!reg) {
                return null;
            }
            setStatus('Mendaftarkan push…', 'netral');
            return ambilPublicKey().then(function (publicKey) {
                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey)
                });
            });
        }).then(function (subscription) {
            if (!subscription) {
                return null;
            }
            return kirimLangganan(subscription, 'subscribe').then(function (hasil) {
                if (!hasil || !hasil.ok) {
                    throw new Error((hasil && hasil.pesan) || 'Gagal menyimpan langganan');
                }
                perbaruiTombol(subscription);
                setStatus('Push berhasil diaktifkan di perangkat ini.', 'sukses');
                return subscription;
            });
        }).catch(function (err) {
            setStatus(err && err.message ? err.message : 'Gagal mengaktifkan push.', 'error');
        });
    }

    function nonaktifkanPush() {
        daftarSw().then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(function (subscription) {
            if (!subscription) {
                perbaruiTombol(null);
                return null;
            }
            return kirimLangganan(subscription, 'unsubscribe').finally(function () {
                return subscription.unsubscribe();
            });
        }).then(function () {
            perbaruiTombol(null);
            setStatus('Push dinonaktifkan di perangkat ini.', 'netral');
        }).catch(function () {
            setStatus('Gagal menonaktifkan push.', 'error');
        });
    }

    if (btnAktifkan) {
        btnAktifkan.addEventListener('click', aktifkanPush);
    }
    if (btnNonaktifkan) {
        btnNonaktifkan.addEventListener('click', nonaktifkanPush);
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!cekDukungan()) {
            setStatus('Browser tidak mendukung Web Push (butuh HTTPS).', 'error');
            return;
        }
        daftarSw().then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(perbaruiTombol).catch(function () {
            setStatus('Service worker belum terdaftar.', 'netral');
        });
    });
})();