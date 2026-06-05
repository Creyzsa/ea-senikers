<?php
/**
 * URL absolut ke halaman aplikasi (penting jika situs di subfolder, mis. /EASENIKERS/public/).
 *
 * Bila pakai `php -S localhost:8080` dari folder public (SAPI cli-server), dasar URL diambil dari
 * permintaan (scheme + HTTP_HOST) supaya redirect tidak memaksa pindah ke URL_APLIKASI (mis. IP Laragon).
 * Alur email Supabase tetap memakai URL_APLIKASI di config lewat includes lain yang tidak lewat sini.
 */
require_once __DIR__ . '/config_loader.php';

function aplikasi_url(string $jalur = ''): string
{
    $dasar_cfg = rtrim(defined('URL_APLIKASI') ? (string) URL_APLIKASI : '', '/');
    $dasar = $dasar_cfg;

    // Determine current request scheme (works behind proxies like Vercel)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https');

    if (PHP_SAPI === 'cli-server' && !empty($_SERVER['HTTP_HOST'])) {
        $skema = $is_https ? 'https' : 'http';
        $dasar = $skema . '://' . rtrim((string) $_SERVER['HTTP_HOST'], '/');
    } elseif (empty($dasar) || (strpos($dasar, 'http://') === 0 && $is_https)) {
        // Fallback or auto-upgrade to https if current request is secure (fixes mixed content on custom domains)
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $dasar = 'https://' . rtrim($host, '/');
    }

    $j = ltrim($jalur, '/');
    if ($j === '') {
        return $dasar;
    }
    return $dasar . '/' . $j;
}

/**
 * Deteksi apakah URL terlihat seperti local/dev (localhost, private IP, dll).
 * Dipakai untuk memberi peringatan di UI saat generate link email Supabase (daftar/reset).
 * Link email harus bisa diakses dari perangkat penerima email (bukan hanya LAN).
 */
function is_local_dev_url(string $url): bool
{
    if ($url === '') return false;
    $u = strtolower($url);
    if (strpos($u, 'localhost') !== false || strpos($u, '127.0.0.1') !== false) {
        return true;
    }
    // Private IP ranges
    if (preg_match('#https?://(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)#', $u)) {
        return true;
    }
    return false;
}
