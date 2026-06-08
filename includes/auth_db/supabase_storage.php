<?php

declare(strict_types=1);

/**
 * Supabase Storage — upload/hapus file lewat REST API (untuk Vercel / serverless).
 */
require_once __DIR__ . '/supabase_auth.php';

/**
 * Kunci API untuk operasi storage server-side (service_role disarankan).
 */
function supabase_storage_api_key(): string
{
    if (defined('SUPABASE_SERVICE_ROLE_KEY') && (string) SUPABASE_SERVICE_ROLE_KEY !== '') {
        return (string) SUPABASE_SERVICE_ROLE_KEY;
    }

    return defined('SUPABASE_ANON_KEY') ? (string) SUPABASE_ANON_KEY : '';
}

/**
 * @return array{ok: bool, http: int, pesan: string, raw: string}
 */
function supabase_storage_upload_file(
    string $bucket,
    string $object_path,
    string $local_path,
    string $content_type
): array {
    $base = supabase_url_dasar();
    $key = supabase_storage_api_key();
    if ($base === '' || $key === '') {
        return [
            'ok' => false,
            'http' => 0,
            'pesan' => 'Supabase Storage belum dikonfigurasi (SUPABASE_URL dan kunci API).',
            'raw' => '',
        ];
    }

    $object_path = ltrim(str_replace('\\', '/', $object_path), '/');
    if ($object_path === '' || !is_file($local_path)) {
        return ['ok' => false, 'http' => 0, 'pesan' => 'File upload tidak ditemukan.', 'raw' => ''];
    }

    $body = file_get_contents($local_path);
    if ($body === false) {
        return ['ok' => false, 'http' => 0, 'pesan' => 'Tidak dapat membaca file upload.', 'raw' => ''];
    }

    $url = $base . '/storage/v1/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($object_path));

    $ch = curl_init($url);
    $opsi = [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: ' . $content_type,
            'x-upsert: true',
        ],
    ];
    $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($ca && is_readable($ca)) {
        $opsi[CURLOPT_CAINFO] = $ca;
    }

    curl_setopt_array($ch, $opsi);
    $raw = (string) curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = (string) curl_error($ch);

    if ($http >= 200 && $http < 300) {
        return ['ok' => true, 'http' => $http, 'pesan' => '', 'raw' => $raw];
    }

    $pesan = 'Upload ke Supabase Storage gagal (HTTP ' . $http . ').';
    if ($err !== '') {
        $pesan .= ' ' . $err;
    } elseif ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['message'])) {
            $pesan = (string) $decoded['message'];
        }
    }
    if ($http === 401 || $http === 403) {
        $pesan .= ' Pastikan SUPABASE_SERVICE_ROLE_KEY sudah di-set di Vercel dan bucket storage sudah dibuat.';
    }

    return ['ok' => false, 'http' => $http, 'pesan' => $pesan, 'raw' => $raw];
}

/**
 * @return array{ok: bool, http: int, pesan: string}
 */
function supabase_storage_hapus_file(string $bucket, string $object_path): array
{
    $base = supabase_url_dasar();
    $key = supabase_storage_api_key();
    if ($base === '' || $key === '') {
        return ['ok' => false, 'http' => 0, 'pesan' => 'Supabase Storage belum dikonfigurasi.'];
    }

    $object_path = ltrim(str_replace('\\', '/', $object_path), '/');
    if ($object_path === '') {
        return ['ok' => true, 'http' => 0, 'pesan' => ''];
    }

    $url = $base . '/storage/v1/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($object_path));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
        ],
    ]);

    $raw = (string) curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($http === 200 || $http === 204 || $http === 404) {
        return ['ok' => true, 'http' => $http, 'pesan' => ''];
    }

    return ['ok' => false, 'http' => $http, 'pesan' => 'Gagal menghapus file di Supabase Storage (HTTP ' . $http . ').'];
}

function supabase_storage_url_publik(string $bucket, string $object_path): string
{
    $base = supabase_url_dasar();
    $object_path = ltrim(str_replace('\\', '/', $object_path), '/');
    if ($base === '' || $object_path === '') {
        return '';
    }

    return $base . '/storage/v1/object/public/'
        . rawurlencode($bucket) . '/'
        . str_replace('%2F', '/', rawurlencode($object_path));
}