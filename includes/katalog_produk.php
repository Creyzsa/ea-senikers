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

/** Gambar pengganti bila belum ada upload (tanpa file statis). */
function katalog_url_gambar_placeholder(): string
{
    $svg = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400" role="img" aria-label="Tidak ada gambar">'
        . '<rect width="400" height="400" fill="#f3f4f6"/>'
        . '<rect x="120" y="150" width="160" height="100" rx="8" fill="none" stroke="#d1d5db" stroke-width="2"/>'
        . '<circle cx="200" cy="200" r="20" fill="#e5e7eb"/>'
        . '<path d="M160 210 L180 190 L200 200 L220 180 L240 200 L240 230 L160 230 Z" fill="#d1d5db"/>'
        . '</svg>';

    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

function katalog_format_rupiah(int $harga): string
{
    return 'Rp ' . number_format($harga, 0, ',', '.');
}

/**
 * Label kondisi untuk ditampilkan ke pembeli.
 * Data internal memakai 'Baru' / 'Second' (untuk admin form),
 * tetapi pembeli melihatnya sebagai 'Baru' / 'Preloved' agar selaras
 * dengan terminologi marketing di seluruh situs.
 */
function kondisi_label_pembeli(string $kondisi): string
{
    $kondisi_bersih = trim($kondisi);
    if (strcasecmp($kondisi_bersih, 'Second') === 0) {
        return 'Preloved';
    }
    return $kondisi_bersih;
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
        katalog_isi_ringkasan_stok($rows);
        return [$rows];
    }
    foreach ($rows as &$__row) {
        if (is_array($__row)) {
            katalog_isi_ringkasan_stok($__row);
        }
    }
    unset($__row);
    return $rows;
}

/**
 * Isi field turunan `total_stok` dan `siap_jual` pada array produk
 * berdasarkan daftar `produk_ukuran`. Field ini bukan kolom database,
 * dihitung di PHP setiap kali baca produk.
 *
 * @param array<string, mixed> $produk
 */
function katalog_isi_ringkasan_stok(array &$produk): void
{
    $ukuran_list = $produk['produk_ukuran'] ?? [];
    $total = 0;
    if (is_array($ukuran_list)) {
        foreach ($ukuran_list as $u) {
            if (is_array($u)) {
                $total += max(0, (int) ($u['stok'] ?? 0));
            }
        }
    }
    $produk['total_stok'] = $total;
    $produk['siap_jual'] = $total > 0;
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
        katalog_isi_ringkasan_stok($rows);
        return $rows;
    }
    $satu = is_array($rows[0] ?? null) ? $rows[0] : null;
    if ($satu !== null) {
        katalog_isi_ringkasan_stok($satu);
    }
    return $satu;
}

/** URL gambar utama (pertama menurut urutan) atau placeholder. */
function katalog_url_gambar_utama(array $produk): string
{
    $g = $produk['produk_gambar'] ?? [];
    if (!is_array($g) || $g === []) {
        return katalog_url_gambar_placeholder();
    }
    $g = katalog_urutkan_gambar($g);
    $nama = (string) ($g[0]['nama_file'] ?? '');
    if ($nama === '') {
        return katalog_url_gambar_placeholder();
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
        $sa = trim((string) ($a['ukuran'] ?? ''));
        $sb = trim((string) ($b['ukuran'] ?? ''));
        if ($sa !== '' && $sb !== '' && ctype_digit($sa) && ctype_digit($sb)) {
            return (int) $sa <=> (int) $sb;
        }

        return strnatcasecmp($sa, $sb);
    });
    return $ukuran;
}
