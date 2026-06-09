/**
 * Notifikasi live admin: polling pembayaran masuk + getar + bunyi.
 */
(function () {
    'use strict';

    var POLL_MS = 12000;
    var STORAGE_KEY = 'easenikers_admin_notif_since';
    var pollUrl = document.body.getAttribute('data-admin-notif-poll');
    if (!pollUrl) {
        return;
    }

    var since = 0;
    try {
        since = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10) || 0;
    } catch (e) {
        since = 0;
    }

    var toast = document.getElementById('admin-notif-toast');
    var toastTimer = null;
    var timer = null;
    var sedangPoll = false;

    function formatRupiah(n) {
        try {
            return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
        } catch (e) {
            return 'Rp ' + n;
        }
    }

    function bunyiNotifikasi() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) {
                return;
            }
            var ctx = new Ctx();
            function beep(freq, start, dur) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.value = 0.12;
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + start);
                osc.stop(ctx.currentTime + start + dur);
            }
            beep(880, 0, 0.12);
            beep(1175, 0.16, 0.14);
            beep(880, 0.34, 0.12);
        } catch (e) {
            // Abaikan jika browser memblokir audio
        }
    }

    function getarNotifikasi() {
        if (navigator.vibrate) {
            navigator.vibrate([180, 90, 180, 90, 220]);
        }
    }

    function tampilkanToast(ev) {
        if (!toast) {
            return;
        }
        var nama = ev.customer_name ? ' · ' + ev.customer_name : '';
        toast.innerHTML = ''
            + '<strong>Pembayaran masuk</strong>'
            + '<span>Pesanan #' + ev.order_id + nama + ' — ' + formatRupiah(ev.total_price) + '</span>'
            + '<a href="' + ev.url + '">Lihat pesanan</a>';
        toast.hidden = false;
        toast.classList.add('admin-notif-toast--tampil');
        if (toastTimer) {
            clearTimeout(toastTimer);
        }
        toastTimer = window.setTimeout(function () {
            toast.classList.remove('admin-notif-toast--tampil');
            toast.hidden = true;
        }, 12000);
    }

    function prosesEvent(ev) {
        getarNotifikasi();
        bunyiNotifikasi();
        tampilkanToast(ev);
        if (typeof document.title === 'string' && document.hidden) {
            document.title = '(💰) ' + document.title.replace(/^\([^)]*\)\s*/, '');
        }
    }

    function simpanSince(id) {
        since = id;
        try {
            localStorage.setItem(STORAGE_KEY, String(id));
        } catch (e) {
            // Abaikan
        }
    }

    function poll() {
        if (sedangPoll || document.hidden) {
            return;
        }
        sedangPoll = true;
        var url = pollUrl + (pollUrl.indexOf('?') >= 0 ? '&' : '?') + 'since=' + encodeURIComponent(String(since));
        fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.ok) {
                    return;
                }
                if (data.browser_aktif === false) {
                    return;
                }
                var events = Array.isArray(data.events) ? data.events : [];
                if (events.length === 0) {
                    if (data.max_id > since) {
                        simpanSince(data.max_id);
                    }
                    return;
                }
                events.forEach(prosesEvent);
                if (data.max_id > since) {
                    simpanSince(data.max_id);
                }
            })
            .catch(function () {
                // Diam — poll berikutnya
            })
            .finally(function () {
                sedangPoll = false;
            });
    }

    function jadwalkan() {
        if (timer) {
            clearInterval(timer);
        }
        timer = window.setInterval(poll, POLL_MS);
    }

    document.addEventListener('DOMContentLoaded', function () {
        poll();
        jadwalkan();
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                poll();
                jadwalkan();
                document.title = document.title.replace(/^\(💰\)\s*/, '');
            } else if (timer) {
                clearInterval(timer);
                timer = null;
            }
        });
    });
})();