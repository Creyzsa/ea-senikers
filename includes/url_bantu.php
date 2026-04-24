<?php
/**
 * URL absolut ke halaman aplikasi (penting jika situs di subfolder, mis. /EASENIKERS/public/).
 *
 * Bila pakai `php -S localhost:8080` dari folder public (SAPI cli-server), dasar URL diambil dari
 * permintaan (scheme + HTTP_HOST) supaya redirect tidak memaksa pindah ke URL_APLIKASI (mis. IP Laragon).
 * Alur email Supabase tetap memakai URL_APLIKASI di config lewat includes lain yang tidak lewat sini.
 */
require_once __DIR__ . '/../config.php';

function aplikasi_url(string $jalur = ''): string
{
    $dasar_cfg = rtrim(defined('URL_APLIKASI') ? (string) URL_APLIKASI : '', '/');
    $dasar = $dasar_cfg;

    if (PHP_SAPI === 'cli-server' && !empty($_SERVER['HTTP_HOST'])) {
        $skema = 'http';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $skema = 'https';
        } elseif (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https') {
            $skema = 'https';
        }
        $dasar = $skema . '://' . rtrim((string) $_SERVER['HTTP_HOST'], '/');
    }

    $j = ltrim($jalur, '/');
    if ($j === '') {
        return $dasar;
    }
    return $dasar . '/' . $j;
}
