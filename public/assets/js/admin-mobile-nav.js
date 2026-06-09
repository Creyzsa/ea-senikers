(function () {
    'use strict';

    if (!document.body.classList.contains('halaman-admin')) {
        return;
    }

    var sisi = document.querySelector('.admin-sisi');
    var bilah = document.querySelector('.admin-bilah');
    if (!sisi || !bilah) {
        return;
    }

    var mq = window.matchMedia('(max-width: 880px)');
    var nav = sisi.querySelector('.admin-nav');
    if (nav) {
        nav.id = 'admin-nav-menu';
    }

    var overlay = document.createElement('div');
    overlay.className = 'admin-nav-overlay';
    overlay.hidden = true;
    document.body.appendChild(overlay);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'admin-nav-toggle';
    btn.setAttribute('aria-label', 'Buka menu navigasi');
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-controls', 'admin-nav-menu');
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>';
    bilah.insertBefore(btn, bilah.firstChild);

    function tutupMenu() {
        sisi.classList.remove('admin-sisi--terbuka');
        overlay.classList.remove('admin-nav-overlay--aktif');
        overlay.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-label', 'Buka menu navigasi');
        document.body.classList.remove('admin-menu-terbuka');
    }

    function bukaMenu() {
        sisi.classList.add('admin-sisi--terbuka');
        overlay.classList.add('admin-nav-overlay--aktif');
        overlay.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        btn.setAttribute('aria-label', 'Tutup menu navigasi');
        document.body.classList.add('admin-menu-terbuka');
    }

    btn.addEventListener('click', function () {
        if (sisi.classList.contains('admin-sisi--terbuka')) {
            tutupMenu();
        } else {
            bukaMenu();
        }
    });

    overlay.addEventListener('click', tutupMenu);

    if (nav) {
        nav.querySelectorAll('.admin-nav__tautan').forEach(function (link) {
            link.addEventListener('click', function () {
                if (mq.matches) {
                    tutupMenu();
                }
            });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            tutupMenu();
        }
    });

    if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', function () {
            if (!mq.matches) {
                tutupMenu();
            }
        });
    } else if (typeof mq.addListener === 'function') {
        mq.addListener(function () {
            if (!mq.matches) {
                tutupMenu();
            }
        });
    }
})();