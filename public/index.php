<?php
require_once __DIR__ . '/../includes/auth_db/sesi.php';

/**
 * Homepage di root URL (https://www.easenikers.shop/)
 * Menampilkan beranda pembeli secara langsung tanpa subpath.
 * Login hanya diperlukan untuk fitur beli (keranjang/checkout) atau area akun/pesanan.
 */
if (sudah_masuk() && ambil_peran() === 'admin') {
    header('Location: ' . aplikasi_url('admin/beranda_admin.php'));
    exit;
}

// Serve the buyer beranda_pembeli.php content directly at the root URL
// https://www.easenikers.shop/ now shows the homepage cleanly (no subpath)
// Token handling (for email confirm) is done client-side in the beranda <head>
include __DIR__ . '/pembeli/beranda_pembeli.php';

