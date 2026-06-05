/**
 * Katalog premium: muat lebih banyak & toggle wishlist pada kartu.
 */
(function () {
    'use strict';

    function muatLebih() {
        var wrap = document.querySelector('[data-katalog-muat]');
        var grid = document.querySelector('[data-katalog-grid]');
        if (!wrap || !grid) {
            return;
        }

        var hal = parseInt(wrap.getAttribute('data-halaman') || '1', 10);
        var totalHal = parseInt(wrap.getAttribute('data-total-hal') || '1', 10);
        var base = wrap.getAttribute('data-base-url') || '';
        var params = {};
        try {
            params = JSON.parse(wrap.getAttribute('data-params') || '{}');
        } catch (e) {
            params = {};
        }

        if (hal >= totalHal) {
            return;
        }

        var btn = wrap.querySelector('.katalog-muat-lagi');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Memuat…';
        }

        var berikut = hal + 1;
        params.hal = String(berikut);
        var qs = Object.keys(params)
            .map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            })
            .join('&');
        var url = base + (qs ? (base.indexOf('?') >= 0 ? '&' : '?') + qs : '');

        fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (res) {
                return res.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var kartu = doc.querySelectorAll('[data-katalog-grid] .kartu-premium');
                kartu.forEach(function (node) {
                    grid.appendChild(document.importNode(node, true));
                });

                var wrapBaru = doc.querySelector('[data-katalog-muat]');
                var totalBaru = wrapBaru
                    ? parseInt(wrapBaru.getAttribute('data-total-hal') || String(totalHal), 10)
                    : totalHal;
                wrap.setAttribute('data-halaman', String(berikut));
                wrap.setAttribute('data-total-hal', String(totalBaru));

                if (berikut >= totalBaru) {
                    if (btn) {
                        btn.remove();
                    }
                } else if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Muat Lebih Banyak';
                }
            })
            .catch(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Muat Lebih Banyak';
                }
            });
    }

    function toggleWishlist(btn) {
        var masuk = btn.getAttribute('data-masuk') === '1';
        var loginUrl = btn.getAttribute('data-login-url') || '';
        if (!masuk) {
            if (loginUrl) {
                window.location.href = loginUrl;
            }
            return;
        }

        var api = document.body.getAttribute('data-wishlist-api');
        var csrf = document.body.getAttribute('data-wishlist-csrf') || '';
        var idProduk = btn.getAttribute('data-id-produk') || '';
        if (!api || !idProduk || !csrf) {
            return;
        }

        var aktif = btn.classList.contains('kartu-premium__wishlist--aktif');
        var fd = new FormData();
        fd.append('id_produk', idProduk);
        fd.append('aksi', aktif ? 'hapus' : 'tambah');
        fd.append('csrf', csrf);

        btn.disabled = true;
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (data.perlu_login) {
                    window.location.href = loginUrl;
                    return;
                }
                if (!data.ok) {
                    return;
                }
                var sekarang = !!data.aktif;
                btn.classList.toggle('kartu-premium__wishlist--aktif', sekarang);
                btn.setAttribute('aria-pressed', sekarang ? 'true' : 'false');
                btn.setAttribute('aria-label', sekarang ? 'Hapus dari wishlist' : 'Tambah ke wishlist');
                var svg = btn.querySelector('svg');
                if (svg) {
                    svg.setAttribute('fill', sekarang ? 'currentColor' : 'none');
                }
            })
            .finally(function () {
                btn.disabled = false;
            });
    }

    document.addEventListener('click', function (e) {
        var muat = e.target.closest('.katalog-muat-lagi');
        if (muat) {
            e.preventDefault();
            muatLebih();
            return;
        }

        var wish = e.target.closest('[data-wishlist-toggle]');
        if (wish) {
            e.preventDefault();
            e.stopPropagation();
            toggleWishlist(wish);
        }
    });
})();