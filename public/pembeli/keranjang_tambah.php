<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/keranjang_sesi.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . aplikasi_url('pembeli/produk.php'));
    exit;
}

$id = trim((string) ($_POST['id_produk'] ?? ''));
$ukuran = trim((string) ($_POST['ukuran'] ?? ''));
$detail_url = aplikasi_url('pembeli/detail_produk.php?id=' . rawurlencode($id));

if ($id === '' || $ukuran === '') {
    $_SESSION['flash_keranjang_error'] = 'Pilih ukuran terlebih dahulu.';
    header('Location: ' . $detail_url);
    exit;
}

$produk = katalog_ambil_produk_ber_id($id);
if ($produk === null) {
    $_SESSION['flash_keranjang_error'] = 'Produk tidak ditemukan.';
    header('Location: ' . aplikasi_url('pembeli/produk.php'));
    exit;
}

$stok_max = 0;
$uk_list = $produk['produk_ukuran'] ?? [];
if (is_array($uk_list)) {
    foreach ($uk_list as $u) {
        if ((string) ($u['ukuran'] ?? '') === $ukuran) {
            $stok_max = (int) ($u['stok'] ?? 0);
            break;
        }
    }
}

if ($stok_max <= 0) {
    $_SESSION['flash_keranjang_error'] = 'Ukuran ini tidak tersedia atau stok habis.';
    header('Location: ' . $detail_url);
    exit;
}

$nama_file = '';
$g = $produk['produk_gambar'] ?? [];
if (is_array($g) && $g !== []) {
    $g = katalog_urutkan_gambar($g);
    $nama_file = (string) ($g[0]['nama_file'] ?? '');
}

keranjang_tambah_atau_update([
    'id_produk' => $id,
    'nama_produk' => (string) ($produk['nama_produk'] ?? ''),
    'brand' => (string) ($produk['brand'] ?? ''),
    'kondisi' => (string) ($produk['kondisi'] ?? ''),
    'ukuran' => $ukuran,
    'harga' => (int) ($produk['harga'] ?? 0),
    'stok_max' => $stok_max,
    'qty' => 1,
    'nama_file' => $nama_file,
]);

$_SESSION['flash_keranjang_ok'] = true;
header('Location: ' . aplikasi_url('pembeli/keranjang_pembeli.php'));
exit;
