/**
 * Show/hide password untuk setiap .bungkus-kata-sandi (masuk, daftar, dll.)
 */
(function () {
    'use strict';

    document.querySelectorAll('.bungkus-kata-sandi').forEach(function (bungkus) {
        var input = bungkus.querySelector('input.isian-input');
        var tombol = bungkus.querySelector('.tombol-lihat-sandi');
        if (!input || !tombol) {
            return;
        }

        var ikonLihat = tombol.querySelector('.ikon-mata-lihat');
        var ikonSembunyikan = tombol.querySelector('.ikon-mata-sembunyikan');

        function sandiTersembunyi() {
            return input.type === 'password';
        }

        function perbaruiIkon() {
            var tersembunyi = sandiTersembunyi();
            tombol.setAttribute('aria-pressed', tersembunyi ? 'false' : 'true');
            var teksTampil = tombol.getAttribute('data-label-tampil') || 'Tampilkan kata sandi';
            var teksSembunyi = tombol.getAttribute('data-label-sembunyi') || 'Sembunyikan kata sandi';
            tombol.setAttribute('aria-label', tersembunyi ? teksTampil : teksSembunyi);
            if (ikonLihat && ikonSembunyikan) {
                ikonLihat.classList.toggle('ikon-mata--nonaktif', !tersembunyi);
                ikonSembunyikan.classList.toggle('ikon-mata--nonaktif', tersembunyi);
            }
        }

        tombol.addEventListener('click', function () {
            input.type = sandiTersembunyi() ? 'text' : 'password';
            perbaruiIkon();
            input.focus();
        });

        perbaruiIkon();
    });
})();
