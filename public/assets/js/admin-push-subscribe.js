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
    var bantuanEl = document.getElementById('admin-push-bantuan');

    function setStatus(teks, jenis) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = teks;
        statusEl.className = 'admin-push-status admin-push-status--' + (jenis || 'netral');
    }

    function tampilkanBantuan(tampil, html) {
        if (!bantuanEl) {
            return;
        }
        if (!tampil) {
            bantuanEl.hidden = true;
            bantuanEl.innerHTML = '';
            return;
        }
        bantuanEl.hidden = false;
        bantuanEl.innerHTML = html || '';
    }

    function pesanIzinDitolak() {
        return 'Izin notifikasi diblokir browser. Reset lewat pengaturan situs (lihat petunjuk di bawah), lalu muat ulang halaman.';
    }

    function pesanIzinDitutup() {
        return 'Popup izin ditutup. Klik "Aktifkan push" lagi, lalu pilih **Izinkan** / **Allow** pada popup browser.';
    }

    function htmlBantuanResetIzin() {
        return ''
            + '<p><strong>Cara mengizinkan notifikasi lagi:</strong></p>'
            + '<ol>'
            + '<li>Klik ikon <strong>gembok / info</strong> di kiri address bar (easenikers.shop).</li>'
            + '<li>Buka <strong>Pengaturan situs</strong> atau <strong>Site settings</strong>.</li>'
            + '<li><strong>Notifikasi</strong> → pilih <strong>Izinkan</strong> / <strong>Allow</strong>.</li>'
            + '<li>Muat ulang halaman (F5), centang Web Push, simpan, lalu klik Aktifkan push lagi.</li>'
            + '</ol>'
            + '<p class="admin-push-bantuan__catatan">Chrome/Edge di HP: juga cek Notifikasi untuk Chrome di pengaturan Android/iOS. Hindari mode penyamaran.</p>';
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
        if (!window.isSecureContext) {
            return { ok: false, pesan: 'Web Push butuh HTTPS. Buka situs lewat https://www.easenikers.shop' };
        }
        if (!('serviceWorker' in navigator)) {
            return { ok: false, pesan: 'Browser tidak mendukung Service Worker. Coba Chrome, Edge, atau Firefox terbaru.' };
        }
        if (!('PushManager' in window)) {
            return { ok: false, pesan: 'Browser ini belum mendukung Web Push. Gunakan Chrome/Edge/Firefox (desktop atau Android).' };
        }
        if (!('Notification' in window)) {
            return { ok: false, pesan: 'Browser tidak mendukung notifikasi desktop.' };
        }
        return { ok: true, pesan: '' };
    }

    function mintaIzinNotifikasi() {
        var izin = Notification.permission;
        if (izin === 'granted') {
            return Promise.resolve('granted');
        }
        if (izin === 'denied') {
            return Promise.resolve('denied');
        }
        try {
            if (Notification.requestPermission.length === 1) {
                return Notification.requestPermission().then(function (hasil) {
                    return hasil || Notification.permission;
                });
            }
        } catch (e) {
            // Lanjut ke callback style
        }
        return new Promise(function (resolve) {
            Notification.requestPermission(function (hasil) {
                resolve(hasil || Notification.permission);
            });
        });
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
                throw new Error((data && data.pesan) || 'VAPID tidak tersedia. Centang Web Push, simpan pengaturan, lalu coba lagi.');
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
            btnAktifkan.disabled = Notification.permission === 'denied';
        }
        if (btnNonaktifkan) {
            btnNonaktifkan.hidden = !subscription;
        }
        if (subscription) {
            tampilkanBantuan(false);
            setStatus('Push aktif di perangkat ini. Notifikasi akan muncul meski tab ditutup.', 'sukses');
        } else if (Notification.permission === 'denied') {
            setStatus(pesanIzinDitolak(), 'error');
            tampilkanBantuan(true, htmlBantuanResetIzin());
        } else {
            tampilkanBantuan(false);
            setStatus('Push belum diaktifkan. Klik tombol di bawah — saat popup muncul, pilih Izinkan.', 'netral');
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
                if (reg.waiting) {
                    reg.waiting.postMessage({ type: 'SKIP_WAITING' });
                }
                return reg.ready ? reg.ready : reg;
            });
    }

    function aktifkanPush() {
        var dukung = cekDukungan();
        if (!dukung.ok) {
            setStatus(dukung.pesan, 'error');
            return;
        }

        if (Notification.permission === 'denied') {
            setStatus(pesanIzinDitolak(), 'error');
            tampilkanBantuan(true, htmlBantuanResetIzin());
            return;
        }

        setStatus('Mendaftarkan service worker…', 'netral');
        tampilkanBantuan(false);

        daftarSw()
            .then(function () {
                setStatus('Meminta izin notifikasi… Pilih **Izinkan** pada popup browser.', 'netral');
                return mintaIzinNotifikasi();
            })
            .then(function (permission) {
                if (permission === 'denied') {
                    setStatus(pesanIzinDitolak(), 'error');
                    tampilkanBantuan(true, htmlBantuanResetIzin());
                    return null;
                }
                if (permission !== 'granted') {
                    setStatus(pesanIzinDitutup(), 'error');
                    return null;
                }
                return daftarSw();
            })
            .then(function (reg) {
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
            })
            .then(function (subscription) {
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
            })
            .catch(function (err) {
                var msg = err && err.message ? err.message : 'Gagal mengaktifkan push.';
                if (msg.indexOf('permission') >= 0 || Notification.permission === 'denied') {
                    setStatus(pesanIzinDitolak(), 'error');
                    tampilkanBantuan(true, htmlBantuanResetIzin());
                } else {
                    setStatus(msg, 'error');
                }
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
        var dukung = cekDukungan();
        if (!dukung.ok) {
            setStatus(dukung.pesan, 'error');
            if (btnAktifkan) {
                btnAktifkan.disabled = true;
            }
            return;
        }
        daftarSw().then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(perbaruiTombol).catch(function () {
            perbaruiTombol(null);
            setStatus('Siap. Klik Aktifkan push — pilih Izinkan saat browser meminta.', 'netral');
        });
    });
})();