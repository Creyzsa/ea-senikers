<?php

declare(strict_types=1);

/**
 * Penyimpanan gambar produk — disk lokal (Laragon) atau Supabase Storage (Vercel).
 */
require_once __DIR__ . '/../auth_db/supabase_storage.php';
require_once __DIR__ . '/../url_bantu.php';

if (!defined('KATALOG_FOLDER_GAMBAR')) {
    define('KATALOG_FOLDER_GAMBAR', 'assets/images/produk');
}

function produk_gambar_bucket(): string
{
    if (defined('SUPABASE_BUCKET_PRODUK') && (string) SUPABASE_BUCKET_PRODUK !== '') {
        return (string) SUPABASE_BUCKET_PRODUK;
    }

    return 'produk-gambar';
}

/**
 * Pakai cloud storage bila folder lokal tidak bisa ditulis (Vercel/serverless).
 */
function produk_gambar_pakai_cloud(): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $paksa = strtolower(trim((string) (getenv('EASENIKERS_STORAGE_PRODUK') ?: '')));
    if ($paksa === 'supabase' || $paksa === 'cloud') {
        $cache = true;

        return true;
    }
    if ($paksa === 'local' || $paksa === 'disk') {
        $cache = false;

        return false;
    }

    if ((getenv('VERCEL') ?: '') !== '' || (getenv('AWS_LAMBDA_FUNCTION_NAME') ?: '') !== '') {
        $cache = true;

        return true;
    }

    $folder = easenikers_folder_public() . '/' . KATALOG_FOLDER_GAMBAR;
    if (!is_dir($folder)) {
        @mkdir($folder, 0755, true);
    }

    $cache = !is_dir($folder) || !is_writable($folder);

    return $cache;
}

function produk_gambar_folder_lokal(): string
{
    $folder = easenikers_folder_public() . '/' . KATALOG_FOLDER_GAMBAR;
    if (!is_dir($folder) && produk_gambar_pakai_cloud()) {
        return $folder;
    }
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Folder upload gambar produk tidak dapat dibuat.');
    }

    return $folder;
}

function produk_gambar_mime_dari_ekstensi(string $ext): string
{
    return match (strtolower($ext)) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };
}

function produk_gambar_nama_aman(string $nama_file): string
{
    $nama_file = trim(str_replace('\\', '/', $nama_file));

    return $nama_file === '' ? '' : basename($nama_file);
}

/**
 * Apakah backend siap menerima upload (Supabase di Vercel / folder lokal di Laragon).
 */
function produk_gambar_siap_unggah(): bool
{
    if (!produk_gambar_pakai_cloud()) {
        return true;
    }

    return supabase_url_dasar() !== '' && supabase_storage_api_key() !== '';
}

function produk_gambar_path_lokal(string $nama_file): string
{
    return easenikers_folder_public() . '/' . KATALOG_FOLDER_GAMBAR . '/' . produk_gambar_nama_aman($nama_file);
}

function produk_gambar_ada_lokal(string $nama_file): bool
{
    $nama_file = produk_gambar_nama_aman($nama_file);

    return $nama_file !== '' && is_file(produk_gambar_path_lokal($nama_file));
}

function produk_gambar_url_publik(string $nama_file): string
{
    $nama_file = produk_gambar_nama_aman($nama_file);
    if ($nama_file === '') {
        return '';
    }

    return supabase_storage_url_publik(produk_gambar_bucket(), $nama_file);
}

/**
 * URL tampilan: file statis di deploy → Supabase Storage → kosong (placeholder di katalog).
 */
function produk_gambar_url_untuk_tampil(string $nama_file): string
{
    $nama_file = produk_gambar_nama_aman($nama_file);
    if ($nama_file === '') {
        return '';
    }

    if (produk_gambar_ada_lokal($nama_file)) {
        return aplikasi_url_aset(KATALOG_FOLDER_GAMBAR . '/' . rawurlencode($nama_file));
    }

    return produk_gambar_url_publik($nama_file);
}

/**
 * Simpan file upload (tmp) ke storage aktif.
 */
function produk_gambar_simpan_tmp(string $tmp_path, string $nama_file, string $mime, bool $dari_upload = true): void
{
    $nama_file = basename(str_replace(['/', '\\'], '', $nama_file));
    if ($nama_file === '' || !is_file($tmp_path)) {
        throw new RuntimeException('File gambar tidak valid.');
    }

    if (produk_gambar_pakai_cloud()) {
        $bucket = produk_gambar_bucket();
        $siap = supabase_storage_pastikan_bucket($bucket, true);
        if (!$siap['ok']) {
            throw new RuntimeException($siap['pesan'] !== '' ? $siap['pesan'] : 'Bucket Supabase Storage belum siap.');
        }
        $hasil = supabase_storage_upload_file($bucket, $nama_file, $tmp_path, $mime);
        if (!$hasil['ok']) {
            throw new RuntimeException($hasil['pesan'] !== '' ? $hasil['pesan'] : 'Gagal mengunggah gambar ke Supabase Storage.');
        }

        return;
    }

    $tujuan = produk_gambar_folder_lokal() . '/' . $nama_file;
    $ok = $dari_upload && is_uploaded_file($tmp_path)
        ? move_uploaded_file($tmp_path, $tujuan)
        : rename($tmp_path, $tujuan);
    if (!$ok) {
        $ok = copy($tmp_path, $tujuan);
    }
    if (!$ok) {
        throw new RuntimeException('Gagal menyimpan file gambar ke server.');
    }
}

function produk_gambar_hapus(string $nama_file): void
{
    $nama_file = basename(str_replace(['/', '\\'], '', $nama_file));
    if ($nama_file === '' || preg_match('/^[a-zA-Z0-9._-]+$/', $nama_file) !== 1) {
        return;
    }

    if (produk_gambar_pakai_cloud()) {
        supabase_storage_hapus_file(produk_gambar_bucket(), $nama_file);

        return;
    }

    $path = produk_gambar_folder_lokal() . '/' . $nama_file;
    if (is_file($path)) {
        unlink($path);
    }
}