<?php

declare(strict_types=1);

/**
 * Persistensi mapping brand → nama_file logo.
 * Produksi (Vercel): Supabase. Lokal: database bila tersedia, cadangan JSON.
 */
require_once __DIR__ . '/../auth_db/database.php';
require_once __DIR__ . '/../auth_db/supabase_rest.php';
require_once __DIR__ . '/../integrasi/produk_gambar_storage.php';

function brand_logo_json_path(): string
{
    return __DIR__ . '/../brand_logo_admin.json';
}

/** @return array<string, string>|null */
function &brand_logo_cache_var()
{
    static $cache = null;

    return $cache;
}

function brand_logo_reset_cache(): void
{
    $cache = &brand_logo_cache_var();
    $cache = null;
}

/**
 * @return array<string, string> nama brand => nama_file logo
 */
function brand_logo_muat_dari_json(): array
{
    $path = brand_logo_json_path();
    if (!is_file($path)) {
        return [];
    }

    try {
        $raw = json_decode(file_get_contents($path) ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return [];
    }

    if (!is_array($raw)) {
        return [];
    }

    $hasil = [];
    foreach ($raw as $brand => $nama_file) {
        $brand = trim((string) $brand);
        $nama_file = produk_gambar_nama_aman((string) $nama_file);
        if ($brand !== '' && $nama_file !== '') {
            $hasil[$brand] = $nama_file;
        }
    }

    return $hasil;
}

/**
 * @return array<string, string>|null null bila koneksi/query gagal
 */
function brand_logo_muat_dari_db(): ?array
{
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->query('SELECT nama_brand, nama_file FROM brand_logo ORDER BY nama_brand');
        $rows = $stmt->fetchAll();
        $hasil = [];
        foreach ($rows as $row) {
            $brand = trim((string) ($row['nama_brand'] ?? ''));
            $nama_file = produk_gambar_nama_aman((string) ($row['nama_file'] ?? ''));
            if ($brand !== '' && $nama_file !== '') {
                $hasil[$brand] = $nama_file;
            }
        }

        return $hasil;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * @return array<string, string>|null null bila REST gagal
 */
function brand_logo_muat_dari_rest(): ?array
{
    $hasil = supabase_rest_request('GET', '/rest/v1/brand_logo', [
        'select' => 'nama_brand,nama_file',
        'order' => 'nama_brand.asc',
    ]);
    if (!$hasil['ok'] || !is_array($hasil['data'])) {
        return null;
    }

    $map = [];
    foreach ($hasil['data'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $brand = trim((string) ($row['nama_brand'] ?? ''));
        $nama_file = produk_gambar_nama_aman((string) ($row['nama_file'] ?? ''));
        if ($brand !== '' && $nama_file !== '') {
            $map[$brand] = $nama_file;
        }
    }

    return $map;
}

/**
 * @return array<string, string> nama brand => nama_file logo
 */
function brand_logo_muat_map(): array
{
    $cache = &brand_logo_cache_var();
    if (is_array($cache)) {
        return $cache;
    }

    $map = brand_logo_muat_dari_db();
    if ($map === null) {
        $map = brand_logo_muat_dari_rest() ?? [];
    }
    if ($map === []) {
        $map = brand_logo_muat_dari_json();
    }

    $cache = $map;

    return $map;
}

/**
 * @param array<string, string> $map
 */
function brand_logo_tulis_json(array $map): bool
{
    if (brand_logo_pakai_cloud()) {
        return false;
    }

    $path = brand_logo_json_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    ksort($map, SORT_NATURAL | SORT_FLAG_CASE);
    $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return false;
    }

    return file_put_contents($path, $json . "\n", LOCK_EX) !== false;
}

function brand_logo_simpan_untuk_brand(string $brand, string $nama_file): bool
{
    $brand = trim($brand);
    $nama_file = produk_gambar_nama_aman($nama_file);
    if ($brand === '' || $nama_file === '') {
        return false;
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'INSERT INTO brand_logo (nama_brand, nama_file, updated_at)
             VALUES (:brand, :file, NOW())
             ON CONFLICT (nama_brand) DO UPDATE SET
                nama_file = EXCLUDED.nama_file,
                updated_at = NOW()'
        );
        $stmt->execute([
            'brand' => $brand,
            'file' => $nama_file,
        ]);
        brand_logo_reset_cache();

        return true;
    } catch (Throwable $e) {
        if (brand_logo_pakai_cloud()) {
            $rls = database_pesan_error_rls($e);
            throw new RuntimeException(
                $rls ?? 'Gagal menyimpan logo brand ke database. Jalankan database/migrations/tahap9_brand_logo.sql di Supabase.',
                0,
                $e
            );
        }

        $map = brand_logo_muat_map();
        $map[$brand] = $nama_file;
        if (!brand_logo_tulis_json($map)) {
            return false;
        }
        brand_logo_reset_cache();

        return true;
    }
}

function brand_logo_hapus_dari_penyimpanan(string $brand): bool
{
    $brand = trim($brand);
    if ($brand === '') {
        return false;
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('DELETE FROM brand_logo WHERE nama_brand = :brand');
        $stmt->execute(['brand' => $brand]);
        brand_logo_reset_cache();

        return true;
    } catch (Throwable $e) {
        if (brand_logo_pakai_cloud()) {
            $rls = database_pesan_error_rls($e);
            throw new RuntimeException(
                $rls ?? 'Gagal menghapus logo brand dari database.',
                0,
                $e
            );
        }

        $map = brand_logo_muat_map();
        if (!isset($map[$brand])) {
            return true;
        }
        unset($map[$brand]);
        if (!brand_logo_tulis_json($map)) {
            return false;
        }
        brand_logo_reset_cache();

        return true;
    }
}

/** Migrasi sekali dari brand_logo_admin.json bila tabel masih kosong. */
function brand_logo_migrasi_json_ke_db(): void
{
    $json = brand_logo_muat_dari_json();
    if ($json === []) {
        return;
    }

    try {
        $pdo = koneksi_database();
        $cek = $pdo->query('SELECT COUNT(*)::int AS c FROM brand_logo')->fetch();
        if ((int) ($cek['c'] ?? 0) > 0) {
            return;
        }

        foreach ($json as $brand => $nama_file) {
            $stmt = $pdo->prepare(
                'INSERT INTO brand_logo (nama_brand, nama_file, updated_at)
                 VALUES (:brand, :file, NOW())
                 ON CONFLICT (nama_brand) DO NOTHING'
            );
            $stmt->execute([
                'brand' => trim((string) $brand),
                'file' => produk_gambar_nama_aman((string) $nama_file),
            ]);
        }
        brand_logo_reset_cache();
    } catch (Throwable $e) {
        // Abaikan — admin tetap bisa unggah ulang
    }
}