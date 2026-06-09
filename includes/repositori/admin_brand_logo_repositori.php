<?php

declare(strict_types=1);

require_once __DIR__ . '/admin_produk_repositori.php';
require_once __DIR__ . '/brand_logo_repositori.php';
require_once __DIR__ . '/../integrasi/brand_logo_storage.php';

/**
 * @return array<string, string> nama brand => nama_file logo
 */
function admin_brand_logo_muat_map(): array
{
    brand_logo_migrasi_json_ke_db();

    return brand_logo_muat_map();
}

function admin_brand_logo_slug(string $brand): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '_', $brand) ?? '');
    $slug = trim((string) $slug, '_');

    return $slug !== '' ? $slug : 'brand';
}

/**
 * @return list<array{brand: string, jumlah_produk: int, nama_file: string, url_logo: string, punya_logo: bool}>
 */
function admin_brand_logo_daftar_kelola(): array
{
    $map = admin_brand_logo_muat_map();
    $hitung = [];
    foreach (admin_produk_ambil_semua('') as $produk) {
        $brand = trim((string) ($produk['brand'] ?? ''));
        if ($brand !== '') {
            $hitung[$brand] = ($hitung[$brand] ?? 0) + 1;
        }
    }

    $brands = array_values(array_unique(array_merge(admin_daftar_brand_produk(), array_keys($hitung))));
    usort($brands, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

    $hasil = [];
    foreach ($brands as $brand) {
        $nama_file = (string) ($map[$brand] ?? '');
        $url_logo = $nama_file !== '' ? brand_logo_url_untuk_tampil($nama_file) : '';
        $hasil[] = [
            'brand' => $brand,
            'jumlah_produk' => (int) ($hitung[$brand] ?? 0),
            'nama_file' => $nama_file,
            'url_logo' => $url_logo,
            'punya_logo' => $url_logo !== '',
        ];
    }

    return $hasil;
}

/**
 * @param array<string, mixed> $file_logo struktur $_FILES['logo']
 */
function admin_brand_logo_unggah(string $brand, array $file_logo): void
{
    $brand = trim($brand);
    if ($brand === '') {
        throw new RuntimeException('Nama brand wajib diisi.');
    }

    $err = (int) ($file_logo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Pilih file logo terlebih dahulu.');
    }
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload logo gagal (kode error ' . $err . ').');
    }
    if (!brand_logo_siap_unggah()) {
        throw new RuntimeException(
            'Upload logo belum dikonfigurasi. Set SUPABASE_SERVICE_ROLE_KEY di Vercel, '
            . 'jalankan database/migrations/tahap5_supabase_storage_produk.sql, '
            . 'dan database/migrations/tahap9_brand_logo.sql.'
        );
    }

    $tmp = (string) ($file_logo['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('File logo tidak valid.');
    }
    if ((int) ($file_logo['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Ukuran logo maksimal 2MB.');
    }

    $info = @getimagesize($tmp);
    if ($info === false) {
        throw new RuntimeException('File bukan gambar yang didukung.');
    }
    $ext_map = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    $ext = $ext_map[$info[2]] ?? '';
    if ($ext === '') {
        throw new RuntimeException('Format logo harus JPG, PNG, atau WEBP.');
    }

    if (brand_logo_pakai_cloud()) {
        $siap = supabase_storage_pastikan_bucket(produk_gambar_bucket(), true);
        if (!$siap['ok']) {
            throw new RuntimeException($siap['pesan'] !== '' ? $siap['pesan'] : 'Bucket Supabase Storage belum siap.');
        }
    }

    $nama_file = 'brand_' . admin_brand_logo_slug($brand) . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    brand_logo_simpan_tmp($tmp, $nama_file, produk_gambar_mime_dari_ekstensi($ext), true);

    $map = admin_brand_logo_muat_map();
    $lama = (string) ($map[$brand] ?? '');
    if ($lama !== '' && $lama !== $nama_file) {
        brand_logo_hapus($lama);
    }

    if (!brand_logo_simpan_untuk_brand($brand, $nama_file)) {
        brand_logo_hapus($nama_file);
        throw new RuntimeException('Gagal menyimpan data logo brand.');
    }
}

function admin_brand_logo_hapus_untuk_brand(string $brand): void
{
    $brand = trim($brand);
    if ($brand === '') {
        throw new RuntimeException('Nama brand tidak valid.');
    }

    $map = admin_brand_logo_muat_map();
    if (!isset($map[$brand])) {
        return;
    }

    $nama_file = (string) $map[$brand];
    if (!brand_logo_hapus_dari_penyimpanan($brand)) {
        throw new RuntimeException('Gagal menghapus data logo brand.');
    }

    if ($nama_file !== '') {
        brand_logo_hapus($nama_file);
    }
}