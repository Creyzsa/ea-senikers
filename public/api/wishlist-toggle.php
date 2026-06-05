<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Metode tidak diizinkan.']);

    exit;
}

if (!sudah_masuk()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'pesan' => 'Silakan masuk terlebih dahulu.', 'perlu_login' => true]);

    exit;
}

$id_pengguna = (int) ($_SESSION['id_pengguna'] ?? 0);
$id_produk = trim((string) ($_POST['id_produk'] ?? ''));
$aksi = (string) ($_POST['aksi'] ?? '');
$csrf = (string) ($_POST['csrf'] ?? '');
$csrf_sesi = csrf_wishlist_token();

if ($csrf === '' || !hash_equals($csrf_sesi, $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Sesi tidak valid. Muat ulang halaman.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($id_pengguna <= 0 || $id_produk === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id_produk)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Data tidak valid.']);

    exit;
}

$ok = false;
$aktif = false;
if ($aksi === 'hapus' || $aksi === 'hapus_wishlist') {
    $ok = wishlist_hapus($id_pengguna, $id_produk);
    $aktif = false;
} else {
    $ok = wishlist_tambah($id_pengguna, $id_produk);
    $aktif = $ok ? true : wishlist_ada($id_pengguna, $id_produk);
}

echo json_encode([
    'ok' => $ok,
    'aktif' => $aktif,
    'pesan' => $aktif ? 'Ditambahkan ke wishlist.' : 'Dihapus dari wishlist.',
], JSON_UNESCAPED_UNICODE);