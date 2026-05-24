/**
 * Toggle dropdown brand pada sub-nav kategori bilah pembeli.
 */
(function () {
    'use strict';

    var bukaSekarang = null;

    function tutup(wrap) {
        if (!wrap) return;
        wrap.classList.remove('terbuka');
        var tombol = wrap.querySelector('[data-bilah-tombol]');
        if (tombol) tombol.setAttribute('aria-expanded', 'false');
        if (bukaSekarang === wrap) bukaSekarang = null;
    }

    function buka(wrap) {
        if (bukaSekarang && bukaSekarang !== wrap) tutup(bukaSekarang);
        wrap.classList.add('terbuka');
        var tombol = wrap.querySelector('[data-bilah-tombol]');
        if (tombol) tombol.setAttribute('aria-expanded', 'true');
        bukaSekarang = wrap;
    }

    document.querySelectorAll('[data-bilah-dropdown]').forEach(function (wrap) {
        var tombol = wrap.querySelector('[data-bilah-tombol]');
        if (!tombol) return;

        tombol.addEventListener('click', function (e) {
            e.stopPropagation();
            if (wrap.classList.contains('terbuka')) {
                tutup(wrap);
            } else {
                buka(wrap);
            }
        });
    });

    document.addEventListener('click', function (e) {
        if (!bukaSekarang) return;
        if (!bukaSekarang.contains(e.target)) tutup(bukaSekarang);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && bukaSekarang) {
            var tombol = bukaSekarang.querySelector('[data-bilah-tombol]');
            tutup(bukaSekarang);
            if (tombol) tombol.focus();
        }
    });
})();
