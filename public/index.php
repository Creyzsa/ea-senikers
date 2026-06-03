<?php
require_once __DIR__ . '/../includes/auth_db/sesi.php';

/**
 * Homepage di root URL (https://www.easenikers.shop/)
 * Menampilkan beranda pembeli secara langsung tanpa subpath.
 * Login hanya diperlukan untuk fitur beli (keranjang/checkout) atau area akun/pesanan.
 *
 * Supports clean URLs like /produk , /detail-produk?id=... etc.
 * For Apache/Laragon etc, pair with .htaccess rewrite to this index.php
 */
if (sudah_masuk() && ambil_peran() === 'admin') {
    header('Location: ' . aplikasi_url('admin/beranda_admin.php'));
    exit;
}

// Clean simple URLs mapping (same as Vercel api/index.php)
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = ltrim($requestPath, '/');

$cleanRoutes = [
    '' => 'pembeli/beranda_pembeli.php',
    'index.php' => 'pembeli/beranda_pembeli.php',
    'produk' => 'pembeli/produk.php',
    'kategori' => 'pembeli/kategori_pembeli.php',
    'tentang' => 'pembeli/tentang_pembeli.php',
    'bantuan' => 'pembeli/bantuan_pembeli.php',
    'cara-membersihkan' => 'pembeli/cara_membersihkan_sepatu.php',
    'detail-produk' => 'pembeli/detail_produk.php',
    'keranjang' => 'pembeli/keranjang_pembeli.php',
    'akun' => 'pembeli/akun_pembeli.php',
    'pesanan' => 'pembeli/pesanan_pembeli.php',
    'checkout' => 'pembeli/checkout_pembeli.php',
    'lapor-masalah' => 'pembeli/lapor_masalah.php',
    'detail-pesanan' => 'pembeli/detail_pesanan_pembeli.php',
    'keranjang-tambah' => 'pembeli/keranjang_tambah.php',
];

if (array_key_exists($requestPath, $cleanRoutes)) {
    $includePath = __DIR__ . '/' . $cleanRoutes[$requestPath];
    if (file_exists($includePath)) {
        include $includePath;
        exit;
    }
} elseif (strpos($requestPath, 'admin/') === 0) {
    // Admin subpaths: try direct include if file exists
    $adminPath = __DIR__ . '/' . $requestPath;
    if (file_exists($adminPath)) {
        include $adminPath;
        exit;
    }
}

// Fallback: serve beranda at root (for / and unknown)
include __DIR__ . '/pembeli/beranda_pembeli.php';

