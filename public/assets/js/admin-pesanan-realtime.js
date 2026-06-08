/**
 * Pembaruan otomatis daftar pesanan admin (polling ringan).
 * Mengambil ulang halaman yang sama lalu menukar chip status + tabel hasil.
 */
(function () {
    'use strict';

    var INTERVAL_MS = 10000;
    var chipSel = '#admin-chip-pesanan';
    var hasilSel = '#hasil-pesanan-admin';
    var timer = null;
    var sedangMuat = false;

    function ringkasId(html) {
        var tbody = html.querySelector(hasilSel + ' tbody');
        if (!tbody) {
            return '';
        }
        var ids = [];
        tbody.querySelectorAll('tr td:first-child strong').forEach(function (el) {
            ids.push(el.textContent.replace(/\D/g, ''));
        });
        return ids.join(',');
    }

    function tukarKonten(doc) {
        var chipBaru = doc.querySelector(chipSel);
        var hasilBaru = doc.querySelector(hasilSel);
        var chipLama = document.querySelector(chipSel);
        var hasilLama = document.querySelector(hasilSel);
        if (!hasilLama || !hasilBaru) {
            return false;
        }

        var idLama = ringkasId(document);
        var idBaru = ringkasId(doc);

        if (chipBaru && chipLama) {
            chipLama.innerHTML = chipBaru.innerHTML;
        }
        hasilLama.innerHTML = hasilBaru.innerHTML;

        return idLama !== idBaru;
    }

    function setStatus(teks, tampil) {
        var el = document.getElementById('admin-pesanan-live-status');
        if (!el) {
            return;
        }
        el.textContent = teks;
        el.hidden = !tampil;
    }

    function muatUlang() {
        if (sedangMuat || document.hidden) {
            return;
        }

        var target = document.querySelector(hasilSel);
        if (!target) {
            return;
        }

        sedangMuat = true;
        setStatus('Memperbarui…', true);
        target.setAttribute('aria-busy', 'true');

        fetch(window.location.href, {
            headers: { 'X-Requested-With': 'fetch' },
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var berubah = tukarKonten(doc);
                setStatus(berubah ? 'Pesanan baru' : 'Terbaru', true);
                window.setTimeout(function () {
                    setStatus('', false);
                }, berubah ? 2800 : 1200);
            })
            .catch(function () {
                setStatus('Gagal memuat', true);
                window.setTimeout(function () {
                    setStatus('', false);
                }, 2000);
            })
            .finally(function () {
                sedangMuat = false;
                target.removeAttribute('aria-busy');
            });
    }

    function jadwalkan() {
        if (timer) {
            clearInterval(timer);
        }
        timer = window.setInterval(muatUlang, INTERVAL_MS);
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.querySelector(hasilSel)) {
            return;
        }
        jadwalkan();
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                muatUlang();
                jadwalkan();
            } else if (timer) {
                clearInterval(timer);
                timer = null;
            }
        });
    });
})();