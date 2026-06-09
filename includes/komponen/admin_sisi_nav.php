<?php

declare(strict_types=1);

/**
 * Sidebar navigasi panel admin.
 *
 * @var string $admin_nav_aktif dashboard|produk|brand|pesanan|pengguna|laporan|pengaturan
 */
$admin_nav_aktif = isset($admin_nav_aktif) ? (string) $admin_nav_aktif : '';

$admin_nav_item = static function (string $id, string $href, string $label, string $svg_path) use ($admin_nav_aktif): void {
    $aktif = $admin_nav_aktif === $id;
    $kelas = 'admin-nav__tautan' . ($aktif ? ' admin-nav__tautan--aktif' : '');
    $aria = $aktif ? ' aria-current="page"' : '';
    echo '<a class="' . htmlspecialchars($kelas, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $aria . '>';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">';
    echo $svg_path;
    echo '</svg>';
    echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    echo '</a>';
};

?>
<aside class="admin-sisi" aria-label="Navigasi admin">
    <a class="admin-sisi__merek" href="beranda_admin.php">
        <p class="admin-sisi__nama"><?php $ukuran_logo = 'admin'; include __DIR__ . '/logo_teks_merek.php'; ?></p>
        <p class="admin-sisi__sub">Panel Admin</p>
    </a>
    <nav class="admin-nav">
        <?php
        $admin_nav_item(
            'dashboard',
            'beranda_admin.php',
            'Dashboard',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>'
        );
        $admin_nav_item(
            'produk',
            'produk_admin.php',
            'Produk',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>'
        );
        $admin_nav_item(
            'brand',
            'brand_admin.php',
            'Brand',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>'
        );
        $admin_nav_item(
            'pesanan',
            'pesanan_admin.php',
            'Pesanan',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>'
        );
        $admin_nav_item(
            'pengguna',
            'pengguna_admin.php',
            'Pengguna',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>'
        );
        $admin_nav_item(
            'laporan',
            'laporan_admin.php',
            'Laporan',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>'
        );
        $admin_nav_item(
            'pengaturan',
            'pengaturan_admin.php',
            'Pengaturan',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'
        );
        ?>
    </nav>
    <p class="admin-sisi__kaki">© EA SENIKERS</p>
</aside>