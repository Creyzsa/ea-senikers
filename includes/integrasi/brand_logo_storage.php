<?php

declare(strict_types=1);

/**
 * Penyimpanan logo brand — folder lokal atau bucket Supabase yang sama dengan gambar produk.
 */
require_once __DIR__ . '/produk_gambar_storage.php';

if (!defined('BRAND_LOGO_FOLDER')) {
    define('BRAND_LOGO_FOLDER', 'assets/images/brand');
}

function brand_logo_siap_unggah(): bool
{
    return produk_gambar_siap_unggah();
}

function brand_logo_pakai_cloud(): bool
{
    return produk_gambar_pakai_cloud();
}

function brand_logo_folder_lokal(): string
{
    $folder = easenikers_folder_public() . '/' . BRAND_LOGO_FOLDER;
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Folder upload logo brand tidak dapat dibuat.');
    }

    return $folder;
}

function brand_logo_path_lokal(string $nama_file): string
{
    return brand_logo_folder_lokal() . '/' . produk_gambar_nama_aman($nama_file);
}

function brand_logo_ada_lokal(string $nama_file): bool
{
    $nama_file = produk_gambar_nama_aman($nama_file);

    return $nama_file !== '' && is_file(brand_logo_path_lokal($nama_file));
}

function brand_logo_url_publik(string $nama_file): string
{
    return produk_gambar_url_publik(produk_gambar_nama_aman($nama_file));
}

function brand_logo_url_untuk_tampil(string $nama_file): string
{
    $nama_file = produk_gambar_nama_aman($nama_file);
    if ($nama_file === '') {
        return '';
    }

    if (brand_logo_ada_lokal($nama_file)) {
        return aplikasi_url_aset(BRAND_LOGO_FOLDER . '/' . rawurlencode($nama_file));
    }

    return brand_logo_url_publik($nama_file);
}

function brand_logo_simpan_tmp(
    string $tmp_path,
    string $nama_file,
    string $mime,
    bool $dari_upload = true
): void {
    produk_gambar_simpan_tmp($tmp_path, $nama_file, $mime, $dari_upload, !brand_logo_pakai_cloud());
}

function brand_logo_hapus(string $nama_file): void
{
    produk_gambar_hapus(produk_gambar_nama_aman($nama_file));
}