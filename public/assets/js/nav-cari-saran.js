/**
 * Autocomplete pencarian navbar — saran produk & kata kunci saat mengetik.
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 180;
    var MIN_CHARS = 2;

    function debounce(fn, ms) {
        var t;
        return function () {
            var self = this;
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(self, args);
            }, ms);
        };
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function init(form) {
        var apiUrl = form.getAttribute('data-cari-saran');
        var input = form.querySelector('input[type="search"][name="q"]');
        if (!apiUrl || !input) {
            return;
        }

        var panel = form.querySelector('.nav-toko__cari-saran');
        if (!panel) {
            panel = document.createElement('div');
            panel.className = 'nav-toko__cari-saran';
            panel.id = input.id ? input.id + '-saran' : 'nav-cari-saran';
            panel.setAttribute('role', 'listbox');
            panel.setAttribute('aria-label', 'Saran pencarian');
            panel.hidden = true;
            form.appendChild(panel);
        }

        var abort = null;
        var indeksAktif = -1;
        var itemEls = [];

        function tutup() {
            panel.hidden = true;
            panel.innerHTML = '';
            itemEls = [];
            indeksAktif = -1;
            input.setAttribute('aria-expanded', 'false');
        }

        function buka() {
            panel.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function pilihItem(el) {
            if (!el) {
                return;
            }
            var url = el.getAttribute('data-url');
            var q = el.getAttribute('data-q');
            if (url) {
                window.location.href = url;
                return;
            }
            if (q) {
                input.value = q;
                form.requestSubmit();
            }
        }

        function render(data) {
            var produk = Array.isArray(data.produk) ? data.produk : [];
            var kata = Array.isArray(data.kata_kunci) ? data.kata_kunci : [];
            if (produk.length === 0 && kata.length === 0) {
                panel.innerHTML = '<p class="nav-toko__cari-kosong">Tidak ada saran. Coba kata kunci lain.</p>';
                buka();
                return;
            }

            var html = '';
            if (produk.length > 0) {
                html += '<p class="nav-toko__cari-judul">Produk</p><ul class="nav-toko__cari-daftar">';
                produk.forEach(function (p) {
                    html +=
                        '<li><button type="button" class="nav-toko__cari-item nav-toko__cari-item--produk" role="option" data-url="' +
                        escapeHtml(p.url || '') +
                        '">' +
                        '<img src="' +
                        escapeHtml(p.gambar || '') +
                        '" alt="" width="40" height="40" loading="lazy">' +
                        '<span class="nav-toko__cari-item-isi">' +
                        '<span class="nav-toko__cari-item-nama">' +
                        escapeHtml(p.nama || '') +
                        '</span>' +
                        '<span class="nav-toko__cari-item-meta">' +
                        escapeHtml(p.brand || '') +
                        ' · ' +
                        escapeHtml(p.harga || '') +
                        '</span></span></button></li>';
                });
                html += '</ul>';
            }
            if (kata.length > 0) {
                html += '<p class="nav-toko__cari-judul">Kata kunci</p><ul class="nav-toko__cari-daftar nav-toko__cari-daftar--kata">';
                kata.forEach(function (k, i) {
                    var isSemua = i === kata.length - 1;
                    html +=
                        '<li><button type="button" class="nav-toko__cari-item' +
                        (isSemua ? ' nav-toko__cari-item--semua' : '') +
                        '" role="option" data-url="' +
                        escapeHtml(k.url || '') +
                        '">' +
                        escapeHtml(k.label || '') +
                        '</button></li>';
                });
                html += '</ul>';
            }

            panel.innerHTML = html;
            itemEls = Array.prototype.slice.call(panel.querySelectorAll('.nav-toko__cari-item'));
            buka();
        }

        var muat = debounce(function () {
            var q = input.value.trim();
            if (q.length < MIN_CHARS) {
                tutup();
                return;
            }
            if (abort) {
                abort.abort();
            }
            abort = new AbortController();
            panel.innerHTML = '<p class="nav-toko__cari-memuat">Mencari…</p>';
            buka();

            fetch(apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
                signal: abort.signal,
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('gagal');
                    }
                    return res.json();
                })
                .then(render)
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    panel.innerHTML = '<p class="nav-toko__cari-kosong">Saran tidak dapat dimuat.</p>';
                });
        }, DEBOUNCE_MS);

        input.addEventListener('input', function () {
            indeksAktif = -1;
            muat();
        });
        input.addEventListener('focus', function () {
            if (input.value.trim().length >= MIN_CHARS && itemEls.length === 0) {
                muat();
            } else if (itemEls.length > 0) {
                buka();
            }
        });

        panel.addEventListener('click', function (e) {
            var btn = e.target.closest('.nav-toko__cari-item');
            if (btn) {
                pilihItem(btn);
            }
        });

        input.addEventListener('keydown', function (e) {
            if (panel.hidden || itemEls.length === 0) {
                if (e.key === 'Escape') {
                    tutup();
                }
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                indeksAktif = Math.min(indeksAktif + 1, itemEls.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                indeksAktif = Math.max(indeksAktif - 1, 0);
            } else if (e.key === 'Enter' && indeksAktif >= 0) {
                e.preventDefault();
                pilihItem(itemEls[indeksAktif]);
                return;
            } else if (e.key === 'Escape') {
                tutup();
                return;
            } else {
                return;
            }
            itemEls.forEach(function (el, i) {
                el.classList.toggle('nav-toko__cari-item--aktif', i === indeksAktif);
            });
            if (indeksAktif >= 0 && itemEls[indeksAktif]) {
                itemEls[indeksAktif].scrollIntoView({ block: 'nearest' });
            }
        });

        document.addEventListener('click', function (e) {
            if (!form.contains(e.target)) {
                tutup();
            }
        });

        form.addEventListener('submit', function () {
            tutup();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-cari-saran]').forEach(init);
    });
})();