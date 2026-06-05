<?php
require_once __DIR__ . '/../includes/auth_db/sesi.php';
require_once __DIR__ . '/../includes/url_bantu.php';

/**
 * Tautan email Supabase kadang mengarah ke Site URL (root) — teruskan ke halaman konfirmasi.
 */
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$qstr = (string) ($_SERVER['QUERY_STRING'] ?? '');
if (preg_match('/(?:^|[?&])(?:access_token|token_hash|code|error)=/i', $uri . '&' . $qstr)) {
    // Email salah format (…/public?token_hash=) → tetap ke halaman konfirmasi (pakai host yang dibuka)
    header('Location: ' . aplikasi_url_auth('login/konfirmasi_email.php') . ($qstr !== '' ? '?' . $qstr : ''));
    exit;
}

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
// Note: wishlist and chat were added for the new features (ulasan/wishlist/chat/rekomendasi)
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = preg_replace('#/+#', '/', $requestPath) ?: '/';
$requestPath = ltrim($requestPath, '/');

// Support both clean (/wishlist) and with .php (/wishlist.php) for robustness
$lookupPath = $requestPath;
if (substr($lookupPath, -4) === '.php') {
    $lookupPath = substr($lookupPath, 0, -4);
}

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
    'wishlist' => 'pembeli/wishlist.php',
    'checkout' => 'pembeli/checkout_pembeli.php',
    'lapor-masalah' => 'pembeli/lapor_masalah.php',
    'detail-pesanan' => 'pembeli/detail_pesanan_pembeli.php',
    'keranjang-tambah' => 'pembeli/keranjang_tambah.php',
    'chat' => 'pembeli/chat.php',
    'api/cari-saran' => 'api/cari-saran.php',
    'api/wishlist-toggle' => 'api/wishlist-toggle.php',
];

if (array_key_exists($lookupPath, $cleanRoutes)) {
    $includePath = __DIR__ . '/' . $cleanRoutes[$lookupPath];
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

// Note: beranda_pembeli.php has JS that auto-redirects Supabase auth tokens/errors (#access_token or #error) to /login/konfirmasi_email.php
// This catches cases where Supabase redirects to Site URL root with error hash (e.g. otp_expired from pre-fetched links).

