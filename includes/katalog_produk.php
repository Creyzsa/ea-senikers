<?php

declare(strict_types=1);

/**
 * Katalog produk — data dari Supabase PostgREST.
 */
require_once __DIR__ . '/supabase_rest.php';
require_once __DIR__ . '/url_bantu.php';

/** Path relatif folder gambar produk di bawah URL_APLIKASI (public). */
const KATALOG_FOLDER_GAMBAR = 'assets/images/produk';

function katalog_url_gambar_produk(string $nama_file): string
{
    $nama_file = str_replace(['/', '\\'], '', $nama_file);
    return aplikasi_url(KATALOG_FOLDER_GAMBAR . '/' . rawurlencode($nama_file));
}

function katalog_format_rupiah(int $harga): string
{
    return 'Rp ' . number_format($harga, 0, ',', '.');
}

/**
 * @return list<array<string, mixed>>
 */
function katalog_ambil_semua_produk(): array
{
    $hasil = supabase_rest_request('GET', '/rest/v1/produk', [
        'select' => '*,produk_gambar(*),produk_ukuran(*)',
        'order' => 'created_at.desc',
    ]);
    if (!$hasil['ok'] || !is_array($hasil['data'])) {
        return [];
    }
    $rows = $hasil['data'];
    if (isset($rows['code']) || isset($rows['message'])) {
        return [];
    }
    if (isset($rows['id_produk'])) {
        return [$rows];
    }
    return $rows;
}

/**
 * @return array<string, mixed>|null
 */
function katalog_ambil_produk_ber_id(string $id_produk): ?array
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)) {
        return null;
    }
    $hasil = supabase_rest_request('GET', '/rest/v1/produk', [
        'select' => '*,produk_gambar(*),produk_ukuran(*)',
        'id_produk' => 'eq.' . $id_produk,
        'limit' => 1,
    ]);
    if (!$hasil['ok'] || !is_array($hasil['data'])) {
        return null;
    }
    $rows = $hasil['data'];
    if ($rows === []) {
        return null;
    }
    if (isset($rows['code'])) {
        return null;
    }
    if (isset($rows['id_produk'])) {
        return $rows;
    }
    return is_array($rows[0] ?? null) ? $rows[0] : null;
}

/** URL gambar utama (pertama menurut urutan) atau placeholder. */
function katalog_url_gambar_utama(array $produk): string
{
    $g = $produk['produk_gambar'] ?? [];
    if (!is_array($g) || $g === []) {
        return aplikasi_url(KATALOG_FOLDER_GAMBAR . '/placeholder.svg');
    }
    $g = katalog_urutkan_gambar($g);
    $nama = (string) ($g[0]['nama_file'] ?? '');
    if ($nama === '') {
        return aplikasi_url(KATALOG_FOLDER_GAMBAR . '/placeholder.svg');
    }
    return katalog_url_gambar_produk($nama);
}

/**
 * Urutkan gambar berdasarkan kolom urutan.
 *
 * @param array<int, array<string, mixed>> $gambar
 * @return array<int, array<string, mixed>>
 */
function katalog_urutkan_gambar(array $gambar): array
{
    usort($gambar, static function (array $a, array $b): int {
        return ((int) ($a['urutan'] ?? 0)) <=> ((int) ($b['urutan'] ?? 0));
    });
    return $gambar;
}

/**
 * @param array<int, array<string, mixed>> $ukuran
 * @return array<int, array<string, mixed>>
 */
function katalog_urutkan_ukuran(array $ukuran): array
{
    usort($ukuran, static function (array $a, array $b): int {
        return strnatcasecmp((string) ($a['ukuran'] ?? ''), (string) ($b['ukuran'] ?? ''));
    });
    return $ukuran;
}
