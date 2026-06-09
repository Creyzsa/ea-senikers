<?php

declare(strict_types=1);

/**
 * Blok nama pengguna di bilah admin (tanpa lonceng notifikasi).
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
</div>