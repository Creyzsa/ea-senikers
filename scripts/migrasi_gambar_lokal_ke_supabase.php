<?php

declare(strict_types=1);

/**
 * Satu kali: unggah semua gambar di public/assets/images/produk ke Supabase Storage.
 *
 * Jalankan dari root project:
 *   php scripts/migrasi_gambar_lokal_ke_supabase.php
 *
 * Wajib: SUPABASE_SERVICE_ROLE_KEY di config.php atau env.
 */
putenv('EASENIKERS_STORAGE_PRODUK=supabase');

require_once __DIR__ . '/../includes/config_loader.php';
require_once __DIR__ . '/../includes/integrasi/produk_gambar_storage.php';
require_once __DIR__ . '/../includes/auth_db/supabase_storage.php';

if (!defined('SUPABASE_SERVICE_ROLE_KEY') || (string) SUPABASE_SERVICE_ROLE_KEY === '') {
    fwrite(STDERR, "SUPABASE_SERVICE_ROLE_KEY belum di-set.\n");
    fwrite(STDERR, "Tambahkan di config.php (Supabase → Settings → API → service_role):\n");
    fwrite(STDERR, "  define('SUPABASE_SERVICE_ROLE_KEY', 'eyJ...');\n");
    fwrite(STDERR, "Atau jalankan SQL: database/migrations/tahap5_supabase_storage_produk.sql\n");
    exit(1);
}

$bucket = produk_gambar_bucket();
$buat = supabase_storage_pastikan_bucket($bucket, true);
if (!$buat['ok']) {
    fwrite(STDERR, 'Bucket: ' . $buat['pesan'] . "\n");
    exit(1);
}
echo "Bucket \"{$bucket}\" siap.\n";

$folder = easenikers_folder_public() . '/' . KATALOG_FOLDER_GAMBAR;
if (!is_dir($folder)) {
    fwrite(STDERR, "Folder tidak ada: {$folder}\n");
    exit(1);
}

/** @var list<string> $files */
$files = [];
foreach (scandir($folder) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    $path = $folder . '/' . $entry;
    if (!is_file($path)) {
        continue;
    }
    $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        continue;
    }
    $files[] = $path;
}

if ($files === []) {
    echo "Tidak ada gambar di {$folder}\n";
    exit(0);
}

$ok = 0;
$skip = 0;
foreach ($files as $path) {
    $nama = basename($path);
    $ext = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
    $mime = produk_gambar_mime_dari_ekstensi($ext);
    try {
        produk_gambar_simpan_tmp($path, $nama, $mime, false);
        echo "OK  {$nama}\n";
        ++$ok;
    } catch (Throwable $e) {
        echo "SKIP {$nama}: " . $e->getMessage() . "\n";
        ++$skip;
    }
}

echo "\nSelesai: {$ok} diunggah, {$skip} dilewati.\n";
exit($skip > 0 && $ok === 0 ? 1 : 0);