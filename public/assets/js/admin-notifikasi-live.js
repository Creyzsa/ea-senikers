/**
 * Notifikasi live admin: lonceng + panel + polling + Web Push.
 */
(function () {
    'use strict';

    var POLL_MS = 12000;
    var DEDUPE_MS = 15000;
    var STORAGE_ALERT_SINCE = 'easenikers_admin_notif_since';
    var STORAGE_READ_UNTIL = 'easenikers_admin_notif_read_until';

    var pollUrl = document.body.getAttribute('data-admin-notif-poll');
    var panelUrl = document.body.getAttribute('data-admin-notif-panel');
    var soundUrl = document.body.getAttribute('data-admin-notif-sound') || '/assets/sounds/admin-notif.mp3';
    if (!pollUrl) {
        return;
    }

    var audioNotif = null;

    var alertSince = 0;
    var readUntil = 0;
    var maxId = 0;
    var terakhirAlert = {};
    var timer = null;
    var sedangPoll = false;
    var panelBuka = false;
    var daftarCache = [];

    var bilah = document.getElementById('admin-notif-bilah');
    var btnToggle = document.getElementById('admin-notif-toggle');
    var btnTutup = document.getElementById('admin-notif-tutup');
    var panel = document.getElementById('admin-notif-panel');
    var badge = document.getElementById('admin-notif-badge');
    var listEl = document.getElementById('admin-notif-list');

    try {
        alertSince = parseInt(localStorage.getItem(STORAGE_ALERT_SINCE) || '0', 10) || 0;
        readUntil = parseInt(localStorage.getItem(STORAGE_READ_UNTIL) || '0', 10) || 0;
    } catch (e) {
        alertSince = 0;
        readUntil = 0;
    }

    function formatRupiah(n) {
        try {
            return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
        } catch (e) {
            return 'Rp ' + n;
        }
    }

    function formatWaktu(iso) {
        if (!iso) {
            return '';
        }
        try {
            var d = new Date(iso);
            if (isNaN(d.getTime())) {
                return '';
            }
            return d.toLocaleString('id-ID', {
                day: 'numeric',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return '';
        }
    }

    function bunyiNotifikasiFallback() {
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
            // Abaikan
        }
    }

    function ambilAudioNotif() {
        if (!soundUrl) {
            return null;
        }
        if (!audioNotif) {
            audioNotif = new Audio(soundUrl);
            audioNotif.preload = 'auto';
        }
        return audioNotif;
    }

    function bunyiNotifikasi() {
        var audio = ambilAudioNotif();
        if (!audio) {
            bunyiNotifikasiFallback();
            return;
        }
        try {
            audio.currentTime = 0;
            var playPromise = audio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () {
                    bunyiNotifikasiFallback();
                });
            }
        } catch (e) {
            bunyiNotifikasiFallback();
        }
    }

    function getarNotifikasi() {
        if (navigator.vibrate) {
            navigator.vibrate([180, 90, 180, 90, 220]);
        }
    }

    function simpanAlertSince(id) {
        alertSince = id;
        try {
            localStorage.setItem(STORAGE_ALERT_SINCE, String(id));
        } catch (e) {
            // Abaikan
        }
    }

    function simpanReadUntil(id) {
        readUntil = id;
        try {
            localStorage.setItem(STORAGE_READ_UNTIL, String(id));
        } catch (e) {
            // Abaikan
        }
    }

    function hitungUnreadLokal() {
        var count = 0;
        daftarCache.forEach(function (item) {
            if ((item.id || 0) > readUntil) {
                count++;
            }
        });
        return count;
    }

    function perbaruiBadge(count) {
        if (!badge) {
            return;
        }
        var n = typeof count === 'number' ? count : hitungUnreadLokal();
        if (n > 0) {
            badge.textContent = n > 99 ? '99+' : String(n);
            badge.hidden = false;
        } else {
            badge.hidden = true;
            badge.textContent = '0';
        }
        if (btnToggle) {
            btnToggle.classList.toggle('admin-notif-bilah__tombol--ada', n > 0);
        }
    }

    function renderDaftar(items) {
        if (!listEl) {
            return;
        }
        if (!Array.isArray(items) || items.length === 0) {
            listEl.innerHTML = '<li class="admin-notif-panel__kosong">Belum ada pembayaran masuk.</li>';
            return;
        }

        listEl.innerHTML = items.map(function (item) {
            var nama = item.customer_name ? '<span class="admin-notif-item__nama">' + escapeHtml(item.customer_name) + '</span>' : '';
            var unread = (item.id || 0) > readUntil;
            return ''
                + '<li class="admin-notif-item' + (unread ? ' admin-notif-item--baru' : '') + '">'
                + '<a class="admin-notif-item__link" href="' + escapeAttr(item.url) + '" data-event-id="' + (item.id || 0) + '">'
                + '<span class="admin-notif-item__judul">Pembayaran masuk · #' + (item.order_id || 0) + '</span>'
                + '<span class="admin-notif-item__detail">' + formatRupiah(item.total_price) + nama + '</span>'
                + '<span class="admin-notif-item__waktu">' + escapeHtml(formatWaktu(item.created_at)) + '</span>'
                + '</a></li>';
        }).join('');

        listEl.querySelectorAll('.admin-notif-item__link').forEach(function (link) {
            link.addEventListener('click', function () {
                var eid = parseInt(link.getAttribute('data-event-id') || '0', 10) || 0;
                if (eid > readUntil) {
                    simpanReadUntil(Math.max(readUntil, eid));
                    perbaruiBadge(0);
                }
                tutupPanel();
            });
        });
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/'/g, '&#39;');
    }

    function muatPanel() {
        if (!panelUrl) {
            return Promise.resolve();
        }
        var url = panelUrl
            + (panelUrl.indexOf('?') >= 0 ? '&' : '?')
            + 'read_until=' + encodeURIComponent(String(readUntil))
            + '&limit=20';
        return fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
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
                daftarCache = Array.isArray(data.recent) ? data.recent : [];
                maxId = Math.max(maxId, parseInt(data.max_id || 0, 10) || 0);
                perbaruiBadge(parseInt(data.unread_count || 0, 10) || 0);
                if (panelBuka) {
                    renderDaftar(daftarCache);
                }
            })
            .catch(function () {
                // Diam
            });
    }

    function tandaiSemuaDibaca() {
        if (maxId > readUntil) {
            simpanReadUntil(maxId);
        }
        perbaruiBadge(0);
        if (panelBuka) {
            renderDaftar(daftarCache);
        }
    }

    function bukaPanel() {
        if (!panel || !btnToggle) {
            return;
        }
        panelBuka = true;
        panel.hidden = false;
        panel.classList.add('admin-notif-panel--buka');
        btnToggle.setAttribute('aria-expanded', 'true');
        bilah && bilah.classList.add('admin-notif-bilah--buka');
        muatPanel().then(function () {
            renderDaftar(daftarCache);
            tandaiSemuaDibaca();
        });
    }

    function tutupPanel() {
        if (!panel || !btnToggle) {
            return;
        }
        panelBuka = false;
        panel.classList.remove('admin-notif-panel--buka');
        panel.hidden = true;
        btnToggle.setAttribute('aria-expanded', 'false');
        bilah && bilah.classList.remove('admin-notif-bilah--buka');
    }

    function togglePanel() {
        if (panelBuka) {
            tutupPanel();
        } else {
            bukaPanel();
        }
    }

    function kunciEvent(ev) {
        var id = ev.event_id || ev.id || ev.order_id || 0;
        return String(id) + ':' + (ev.order_id || 0);
    }

    function sudahDiAlert(ev) {
        var eventId = parseInt(ev.event_id || ev.id || 0, 10) || 0;
        if (eventId > 0 && eventId <= alertSince) {
            return true;
        }
        var kunci = kunciEvent(ev);
        var terakhir = terakhirAlert[kunci] || 0;
        if (Date.now() - terakhir < DEDUPE_MS) {
            return true;
        }
        terakhirAlert[kunci] = Date.now();
        return false;
    }

    function normalisasiEvent(ev) {
        return {
            id: ev.event_id || ev.id || 0,
            event_id: ev.event_id || ev.id || 0,
            order_id: ev.order_id || 0,
            total_price: ev.total_price || 0,
            payment_method: ev.payment_method || '',
            customer_name: ev.customer_name || '',
            created_at: ev.created_at || new Date().toISOString(),
            url: ev.url || ('admin/detail_pesanan_admin.php?id=' + (ev.order_id || 0))
        };
    }

    function tambahKeDaftar(ev) {
        var norm = normalisasiEvent(ev);
        if (!norm.id) {
            return;
        }
        var ada = false;
        daftarCache.forEach(function (item) {
            if ((item.id || 0) === norm.id) {
                ada = true;
            }
        });
        if (!ada) {
            daftarCache.unshift({
                id: norm.id,
                order_id: norm.order_id,
                total_price: norm.total_price,
                payment_method: norm.payment_method,
                customer_name: norm.customer_name,
                created_at: norm.created_at,
                url: norm.url,
                unread: norm.id > readUntil
            });
            if (daftarCache.length > 20) {
                daftarCache.length = 20;
            }
        }
        maxId = Math.max(maxId, norm.id);
        if (panelBuka) {
            renderDaftar(daftarCache);
        }
    }

    function prosesEvent(ev, dariPush) {
        var norm = normalisasiEvent(ev);
        if (!sudahDiAlert(norm)) {
            getarNotifikasi();
            bunyiNotifikasi();
            if (norm.event_id > alertSince) {
                simpanAlertSince(norm.event_id);
            }
            if (typeof document.title === 'string' && document.hidden) {
                document.title = '(💰) ' + document.title.replace(/^\([^)]*\)\s*/, '');
            }
        }

        tambahKeDaftar(norm);

        if (!panelBuka && norm.id > readUntil) {
            muatPanel();
        }
    }

    function poll() {
        if (sedangPoll) {
            return;
        }
        sedangPoll = true;
        var url = pollUrl + (pollUrl.indexOf('?') >= 0 ? '&' : '?') + 'since=' + encodeURIComponent(String(alertSince));
        fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
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
                if (data.max_id) {
                    maxId = Math.max(maxId, parseInt(data.max_id, 10) || 0);
                }
                if (data.browser_aktif === false) {
                    if (!panelBuka) {
                        muatPanel();
                    }
                    return;
                }
                var events = Array.isArray(data.events) ? data.events : [];
                if (events.length === 0) {
                    if (data.max_id > alertSince) {
                        simpanAlertSince(data.max_id);
                    }
                    if (!panelBuka) {
                        muatPanel();
                    }
                    return;
                }
                events.forEach(function (ev) {
                    prosesEvent(ev, false);
                });
                if (data.max_id > alertSince) {
                    simpanAlertSince(data.max_id);
                }
            })
            .catch(function () {
                // Diam
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

    function dengarkanPushSw() {
        if (!('serviceWorker' in navigator)) {
            return;
        }
        navigator.serviceWorker.addEventListener('message', function (event) {
            var msg = event.data;
            if (!msg || msg.type !== 'easenikers-admin-push' || !msg.payload) {
                return;
            }
            var p = msg.payload;
            prosesEvent({
                event_id: p.event_id || 0,
                order_id: p.order_id || 0,
                total_price: p.total_price || 0,
                customer_name: p.customer_name || '',
                url: p.url || '',
                created_at: new Date().toISOString()
            }, true);
        });
    }

    function pasangPanel() {
        if (!btnToggle || !panel) {
            return;
        }
        tutupPanel();

        btnToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            togglePanel();
        });
        if (btnTutup) {
            btnTutup.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                tutupPanel();
            });
        }
        document.addEventListener('click', function (e) {
            if (!panelBuka || !bilah) {
                return;
            }
            if (!bilah.contains(e.target)) {
                tutupPanel();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panelBuka) {
                tutupPanel();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        pasangPanel();
        poll();
        jadwalkan();
        dengarkanPushSw();
        muatPanel();
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                poll();
                jadwalkan();
                muatPanel();
                document.title = document.title.replace(/^\(💰\)\s*/, '');
            } else if (timer) {
                clearInterval(timer);
                timer = null;
            }
        });
    });
})();