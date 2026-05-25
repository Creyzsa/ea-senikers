<?php
/**
 * Panel merek EA SENIKERS — dipakai di halaman auth (masuk, daftar, lupa/atur sandi).
 * Badge & sub judul diambil dari includes/merek_ringkas.php agar konsisten dengan hero toko.
 */
$merek_ringkas = require __DIR__ . '/merek_ringkas.php';
?>
<aside class="panel-tentang" aria-labelledby="panel-tentang-judul">
    <div class="panel-tentang__inner">
        <header class="panel-tentang__header">
            <p class="panel-tentang__badge"><?php echo htmlspecialchars($merek_ringkas['badge_toko'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="panel-tentang__sub"><?php echo htmlspecialchars($merek_ringkas['tagline_satu_baris'], ENT_QUOTES, 'UTF-8'); ?></p>
        </header>

        <h2 id="panel-tentang-judul" class="panel-tentang__nama">
            <span class="panel-tentang__nama-merek">EA SENIKERS</span>
        </h2>

        <p class="panel-tentang__teks">
            Destinasi belanja sepatu dengan <strong>berbagai merek</strong> — sneakers baru hingga koleksi <strong>preloved</strong> pilihan. Kualitas terjaga dan harga transparan.
        </p>

        <ul class="panel-tentang__fitur" role="list">
            <li class="panel-tentang__fitur-item">
                <span class="panel-tentang__fitur-ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 17l2-7h12l2 7M8 17v3M16 17v3M6 10h12l-1-4H7L6 10z"/></svg>
                </span>
                <span class="panel-tentang__fitur-label">Multi-merek</span>
            </li>
            <li class="panel-tentang__fitur-item">
                <span class="panel-tentang__fitur-ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </span>
                <span class="panel-tentang__fitur-label">Sneakers baru &amp; terkurasi</span>
            </li>
            <li class="panel-tentang__fitur-item">
                <span class="panel-tentang__fitur-ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </span>
                <span class="panel-tentang__fitur-label">Preloved terjamin kualitas</span>
            </li>
        </ul>

        <div class="panel-tentang__chip-grup" role="list">
            <span class="panel-tentang__chip" role="listitem">Packing aman</span>
            <span class="panel-tentang__chip" role="listitem">Pengiriman cepat</span>
        </div>
    </div>
</aside>
