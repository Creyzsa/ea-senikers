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

/**
 * Ringkasan ringan untuk sub-navigasi pembeli (daftar merek + kondisi yang tersedia).
 * Hanya mengambil kolom brand & kondisi agar permintaan kecil; dicache per-request.
 *
 * @return array{brands: array<string, int>, kondisi_baru_ada: bool, kondisi_preloved: string|null}
 */
function katalog_ambil_meta_navigasi(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $hasil = supabase_rest_request('GET', '/rest/v1/produk', [
        'select' => 'brand,kondisi',
    ]);

    $brands = [];
    $kondisi_baru_ada = false;
    $kondisi_preloved = null;

    if ($hasil['ok'] && is_array($hasil['data'])) {
        $rows = $hasil['data'];
        if (isset($rows['brand']) || isset($rows['kondisi'])) {
            $rows = [$rows];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $brand = trim((string) ($row['brand'] ?? ''));
            $kondisi = trim((string) ($row['kondisi'] ?? ''));
            if ($brand !== '') {
                $brands[$brand] = ($brands[$brand] ?? 0) + 1;
            }
            if ($kondisi !== '') {
                if (strcasecmp($kondisi, 'Baru') === 0) {
                    $kondisi_baru_ada = true;
                } elseif ($kondisi_preloved === null) {
                    $kondisi_preloved = $kondisi;
                }
            }
        }
    }

    ksort($brands, SORT_NATURAL | SORT_FLAG_CASE);

    $cache = [
        'brands' => $brands,
        'kondisi_baru_ada' => $kondisi_baru_ada,
        'kondisi_preloved' => $kondisi_preloved,
    ];
    return $cache;
}
