<?php

declare(strict_types=1);

if (!function_exists('aplikasi_url')) {
    require_once __DIR__ . '/../url_bantu.php';
}

/**
 * Blok pengguna + lonceng notifikasi di bilah admin.
 * Variabel opsional: $nama, $namaAdmin, $admin_tampilkan_ikon (bool, default true).
 */
$nama_tampil = '';
if (isset($nama) && (string) $nama !== '') {
    $nama_tampil = (string) $nama;
} elseif (isset($namaAdmin) && (string) $namaAdmin !== '') {
    $nama_tampil = (string) $namaAdmin;
} else {
    $nama_tampil = htmlspecialchars((string) ($_SESSION['nama_pengguna'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
}

$tampilkan_ikon = !isset($admin_tampilkan_ikon) || (bool) $admin_tampilkan_ikon;

?>
<div class="admin-pengguna">
    <?php if ($tampilkan_ikon): ?>
        <span class="admin-pengguna__ikon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </span>
    <?php endif; ?>
    <span class="admin-pengguna__nama"><?php echo $nama_tampil; ?></span>
    <div class="admin-notif-bilah" id="admin-notif-bilah">
        <button
            type="button"
            class="admin-notif-bilah__tombol"
            id="admin-notif-toggle"
            aria-expanded="false"
            aria-controls="admin-notif-panel"
            aria-label="Notifikasi pembayaran"
            title="Notifikasi"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span class="admin-notif-bilah__badge" id="admin-notif-badge" hidden>0</span>
        </button>
        <div class="admin-notif-panel" id="admin-notif-panel" hidden>
            <div class="admin-notif-panel__header">
                <strong>Notifikasi</strong>
                <button type="button" class="admin-notif-panel__tutup" id="admin-notif-tutup" aria-label="Tutup notifikasi">&times;</button>
            </div>
            <ul class="admin-notif-panel__daftar" id="admin-notif-list"></ul>
            <div class="admin-notif-panel__footer">
                <a href="<?php echo htmlspecialchars(aplikasi_url('admin/notifikasi_admin.php'), ENT_QUOTES, 'UTF-8'); ?>">Pengaturan notifikasi</a>
            </div>
        </div>
    </div>
</div>