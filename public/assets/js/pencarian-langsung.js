/**
 * Pencarian langsung (live search) ala marketplace.
 *
 * Memakai kembali pencarian sisi server yang sudah ada: saat pengguna mengetik
 * (di-debounce), form di-fetch ke URL-nya sendiri lalu HANYA bagian hasil yang
 * ditukar di layar — tanpa reload, tanpa endpoint baru.
 *
 * Pakai pada <form>:
 *   data-live              -> penanda aktif
 *   data-target="#id"      -> kontainer hasil yang ditukar (harus di LUAR form)
 */
(function () {
    'use strict';

    function debounce(fn, jeda) {
        var timer;
        return function () {
            var args = arguments, konteks = this;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(konteks, args);
            }, jeda);
        };
    }

    function bangunUrl(form) {
        var params = new URLSearchParams(new FormData(form));
        Array.from(params.keys()).forEach(function (k) {
            if (params.get(k) === '') {
                params.delete(k);
            }
        });
        var action = form.getAttribute('action');
        var basis = action && action.trim() !== '' ? action : window.location.pathname;
        var qs = params.toString();
        return qs ? basis + '?' + qs : basis;
    }

    function pasang(form) {
        var targetSel = form.getAttribute('data-target');
        var target = targetSel ? document.querySelector(targetSel) : null;
        if (!target) {
            return;
        }

        var pengontrol = null;

        var jalankan = debounce(function () {
            var url = bangunUrl(form);
            if (pengontrol) {
                pengontrol.abort();
            }
            pengontrol = new AbortController();
            target.setAttribute('aria-busy', 'true');

            fetch(url, {
                headers: { 'X-Requested-With': 'fetch' },
                signal: pengontrol.signal
            })
                .then(function (res) { return res.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var baru = doc.querySelector(targetSel);
                    if (baru) {
                        target.innerHTML = baru.innerHTML;
                    }
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', url);
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                })
                .finally(function () {
                    target.removeAttribute('aria-busy');
                });
        }, 300);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            jalankan();
        });
        form.addEventListener('input', jalankan);
        form.addEventListener('change', jalankan);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-live]').forEach(pasang);
    });
})();
