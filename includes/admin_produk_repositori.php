<?php

declare(strict_types=1);

/**
 * Repositori admin produk — operasi CRUD untuk panel admin.
 */
require_once __DIR__ . '/supabase_rest.php';
require_once __DIR__ . '/url_bantu.php';
require_once __DIR__ . '/katalog_produk.php';

/** Ukuran default untuk produk. */
function admin_daftar_ukuran_default(): array
{
    return ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
}

/**
 * Normalisasi array stok ukuran menjadi array dengan semua ukuran default.
 * @param array<string, mixed> $stok_input
 * @return array<string, int>
 */
function admin_normalisasi_stok_ukuran(array $stok_input): array
{
    $hasil = [];
    foreach (admin_daftar_ukuran_default() as $uk) {
        $nilai = $stok_input[$uk] ?? 0;
        $hasil[$uk] = is_numeric($nilai) ? (int) $nilai : 0;
    }
    return $hasil;
}

/**
 * @return array<string, mixed>|null
 */
function admin_produk_ambil_detail(string $id_produk): ?array
{
    return katalog_ambil_produk_ber_id($id_produk);
}

/**
 * @return list<array<string, mixed>>
 */
function admin_produk_ambil_semua(string $query = ''): array
{
    $produk = katalog_ambil_semua_produk();
    if ($query === '') {
        return $produk;
    }
    $hasil = [];
    $query_lower = strtolower($query);
    foreach ($produk as $p) {
        if (str_contains(strtolower((string) ($p['nama_produk'] ?? '')), $query_lower) ||
            str_contains(strtolower((string) ($p['brand'] ?? '')), $query_lower)) {
            $hasil[] = $p;
        }
    }
    return $hasil;
}

/**
 * @param array<string, mixed> $payload
 * @param array<string, int> $stok_ukuran
 * @param array<string, mixed> $files
 * @return string ID produk baru
 * @throws Exception
 */
function admin_produk_tambah(array $payload, array $stok_ukuran, array $files): string
{
    // Validasi payload
    $nama = trim((string) ($payload['nama_produk'] ?? ''));
    $brand = trim((string) ($payload['brand'] ?? ''));
    $kategori = trim((string) ($payload['kategori'] ?? ''));
    $kondisi = trim((string) ($payload['kondisi'] ?? ''));
    $harga = (int) ($payload['harga'] ?? 0);
    $deskripsi = trim((string) ($payload['deskripsi'] ?? ''));

    if ($nama === '' || $harga <= 0) {
        throw new Exception('Nama produk dan harga wajib diisi.');
    }

    // Insert produk
    $produk_data = [
        'nama_produk' => $nama,
        'brand' => $brand,
        'kategori' => $kategori,
        'kondisi' => $kondisi,
        'harga' => $harga,
        'deskripsi' => $deskripsi,
    ];

    $hasil = supabase_rest_request('POST', '/rest/v1/produk', [], $produk_data);
    if (!$hasil['ok']) {
        throw new Exception('Gagal menambah produk: ' . json_encode($hasil));
    }

    $id_produk = $hasil['data']['id_produk'] ?? '';
    if ($id_produk === '') {
        throw new Exception('ID produk tidak diterima dari database.');
    }

    // Insert stok ukuran
    foreach ($stok_ukuran as $ukuran => $stok) {
        if ($stok > 0) {
            supabase_rest_request('POST', '/rest/v1/produk_ukuran', [], [
                'id_produk' => $id_produk,
                'ukuran' => $ukuran,
                'stok' => $stok,
            ]);
        }
    }

    // Upload gambar jika ada
    admin_produk_upload_gambar($id_produk, $files);

    return $id_produk;
}

/**
 * @param string $id_produk
 * @param array<string, mixed> $payload
 * @param array<string, int> $stok_ukuran
 * @param array<string, mixed> $files
 * @throws Exception
 */
function admin_produk_update(string $id_produk, array $payload, array $stok_ukuran, array $files): void
{
    // Validasi
    $nama = trim((string) ($payload['nama_produk'] ?? ''));
    $brand = trim((string) ($payload['brand'] ?? ''));
    $kategori = trim((string) ($payload['kategori'] ?? ''));
    $kondisi = trim((string) ($payload['kondisi'] ?? ''));
    $harga = (int) ($payload['harga'] ?? 0);
    $deskripsi = trim((string) ($payload['deskripsi'] ?? ''));

    if ($nama === '' || $harga <= 0) {
        throw new Exception('Nama produk dan harga wajib diisi.');
    }

    // Update produk
    $produk_data = [
        'nama_produk' => $nama,
        'brand' => $brand,
        'kategori' => $kategori,
        'kondisi' => $kondisi,
        'harga' => $harga,
        'deskripsi' => $deskripsi,
    ];

    $hasil = supabase_rest_request('PATCH', '/rest/v1/produk', ['id_produk' => 'eq.' . $id_produk], $produk_data);
    if (!$hasil['ok']) {
        throw new Exception('Gagal update produk: ' . json_encode($hasil));
    }

    // Update stok ukuran - hapus yang lama, insert yang baru
    supabase_rest_request('DELETE', '/rest/v1/produk_ukuran', ['id_produk' => 'eq.' . $id_produk]);

    foreach ($stok_ukuran as $ukuran => $stok) {
        if ($stok > 0) {
            supabase_rest_request('POST', '/rest/v1/produk_ukuran', [], [
                'id_produk' => $id_produk,
                'ukuran' => $ukuran,
                'stok' => $stok,
            ]);
        }
    }

    // Upload gambar baru jika ada
    admin_produk_upload_gambar($id_produk, $files);
}

/**
 * @param string $id_produk
 * @throws Exception
 */
function admin_produk_hapus(string $id_produk): void
{
    // Hapus gambar dulu
    $gambar = supabase_rest_request('GET', '/rest/v1/produk_gambar', ['id_produk' => 'eq.' . $id_produk]);
    if ($gambar['ok'] && is_array($gambar['data'])) {
        foreach ($gambar['data'] as $g) {
            admin_produk_hapus_gambar_file((string) ($g['nama_file'] ?? ''));
        }
    }

    // Hapus dari database
    supabase_rest_request('DELETE', '/rest/v1/produk_gambar', ['id_produk' => 'eq.' . $id_produk]);
    supabase_rest_request('DELETE', '/rest/v1/produk_ukuran', ['id_produk' => 'eq.' . $id_produk]);
    $hasil = supabase_rest_request('DELETE', '/rest/v1/produk', ['id_produk' => 'eq.' . $id_produk]);

    if (!$hasil['ok']) {
        throw new Exception('Gagal hapus produk.');
    }
}

/**
 * @param string $id_produk
 * @param string $id_gambar
 * @throws Exception
 */
function admin_produk_hapus_gambar(string $id_produk, string $id_gambar): void
{
    // Ambil nama file
    $hasil = supabase_rest_request('GET', '/rest/v1/produk_gambar', [
        'id_gambar' => 'eq.' . $id_gambar,
        'id_produk' => 'eq.' . $id_produk,
    ]);

    if ($hasil['ok'] && is_array($hasil['data']) && isset($hasil['data'][0])) {
        $nama_file = (string) ($hasil['data'][0]['nama_file'] ?? '');
        admin_produk_hapus_gambar_file($nama_file);
    }

    // Hapus dari database
    supabase_rest_request('DELETE', '/rest/v1/produk_gambar', ['id_gambar' => 'eq.' . $id_gambar]);
}

/**
 * Upload gambar untuk produk.
 * @param string $id_produk
 * @param array<string, mixed> $files
 */
function admin_produk_upload_gambar(string $id_produk, array $files): void
{
    $gambar_files = $files['gambar'] ?? [];
    if (!is_array($gambar_files) || !isset($gambar_files['name'])) {
        return;
    }

    $names = (array) ($gambar_files['name'] ?? []);
    $tmp_names = (array) ($gambar_files['tmp_name'] ?? []);
    $errors = (array) ($gambar_files['error'] ?? []);

    foreach ($names as $i => $name) {
        if ($errors[$i] !== UPLOAD_ERR_OK || !is_uploaded_file($tmp_names[$i])) {
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            continue;
        }

        $nama_file = uniqid('produk_', true) . '.' . $ext;
        $path = __DIR__ . '/../public/assets/images/produk/' . $nama_file;

        if (move_uploaded_file($tmp_names[$i], $path)) {
            supabase_rest_request('POST', '/rest/v1/produk_gambar', [], [
                'id_produk' => $id_produk,
                'nama_file' => $nama_file,
                'urutan' => 0, // Sementara
            ]);
        }
    }
}

/**
 * Hapus file gambar dari filesystem.
 * @param string $nama_file
 */
function admin_produk_hapus_gambar_file(string $nama_file): void
{
    if ($nama_file === '') {
        return;
    }
    $path = __DIR__ . '/../public/assets/images/produk/' . $nama_file;
    if (file_exists($path)) {
        unlink($path);
    }
}