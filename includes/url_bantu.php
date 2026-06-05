<?php
/**
 * URL absolut ke halaman aplikasi (penting jika situs di subfolder, mis. /EASENIKERS/public/).
 *
 * - aplikasi_url(): untuk redirect/form di browser — memakai URL permintaan saat ini
 *   (host, port, path) supaya tidak salah ke localhost:8080 saat dibuka lewat Laragon/IP.
 * - aplikasi_url_konfigurasi(): nilai URL_APLIKASI dari config (untuk dokumentasi).
 * - aplikasi_url_redirect_email(): untuk redirect_to Supabase — SELALU dari URL_APLIKASI (config),
 *   supaya email tidak pakai IP LAN (192.168.x.x) walau halaman dibuka lewat IP.
 */
require_once __DIR__ . '/config_loader.php';

/**
 * Scheme + host dari permintaan HTTP saat ini.
 */
/** Host produksi easenikers.shop (www atau tanpa www). */
function easenikers_apakah_domain_produksi(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return false;
    }

    return $host === 'easenikers.shop' || str_ends_with($host, '.easenikers.shop');
}

/**
 * Domain cookie sesi di produksi supaya www dan non-www berbagi login.
 */
function easenikers_cookie_domain(): string
{
    return easenikers_apakah_domain_produksi() ? '.easenikers.shop' : '';
}

/**
 * Alihkan ke host di URL_APLIKASI bila pengunjung pakai host lain (mis. easenikers.shop vs www).
 */
function easenikers_redirect_kanonikal_jika_perlu(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    if (!easenikers_apakah_domain_produksi()) {
        return;
    }
    $cfg = aplikasi_url_konfigurasi();
    if ($cfg === '') {
        return;
    }
    $host_cfg = strtolower((string) (parse_url($cfg, PHP_URL_HOST) ?? ''));
    $host_now = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host_cfg === '' || $host_now === '' || $host_now === $host_cfg) {
        return;
    }

    $scheme = (string) (parse_url($cfg, PHP_URL_SCHEME) ?: 'https');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $scheme . '://' . $host_cfg . $uri, true, 301);
    exit;
}

/**
 * URL dasar untuk link/form di produksi — selalu dari URL_APLIKASI agar host konsisten.
 */
function easenikers_url_dasar_untuk_link(): string
{
    if (easenikers_apakah_domain_produksi()) {
        $cfg = aplikasi_url_konfigurasi();
        if ($cfg !== '') {
            return $cfg;
        }
    }

    return easenikers_url_dasar_runtime() !== '' ? easenikers_url_dasar_runtime() : aplikasi_url_konfigurasi();
}

function easenikers_skema_permintaan(): string
{
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https');

    return $is_https ? 'https' : 'http';
}

/**
 * Path folder public aplikasi dari SCRIPT_NAME (mis. /EASENIKERS/public).
 */
function easenikers_path_dasar_aplikasi(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '' || $script === '/') {
        return '';
    }

    if (preg_match('#^(.*)/login/[^/]+\.php$#', $script, $m)) {
        return rtrim($m[1], '/');
    }
    if (preg_match('#^(.*)/pembeli/[^/]+\.php$#', $script, $m)) {
        return rtrim($m[1], '/');
    }
    if (preg_match('#^(.*)/admin/[^/]+\.php$#', $script, $m)) {
        return rtrim($m[1], '/');
    }
    if (preg_match('#^(.*)/api/[^/]+\.php$#', $script, $m)) {
        return rtrim($m[1], '/');
    }

    $dir = rtrim(dirname($script), '/\\');
    if ($dir === '' || $dir === '/' || $dir === '.') {
        return '';
    }

    return $dir;
}

/**
 * Gabung base URL + path tanpa double slash (mis. http://host//pesanan).
 */
function easenikers_gabung_url(string $dasar, string $jalur = ''): string
{
    $dasar = rtrim(trim($dasar), '/');
    $j = ltrim($jalur, '/');
    if ($dasar === '') {
        return $j === '' ? '/' : '/' . $j;
    }
    if ($j === '') {
        return $dasar;
    }

    return $dasar . '/' . $j;
}

/**
 * URL dasar aplikasi dari permintaan browser (tanpa slash akhir).
 */
function easenikers_url_dasar_runtime(): string
{
    if (empty($_SERVER['HTTP_HOST'])) {
        return '';
    }

    $skema = easenikers_skema_permintaan();
    $host = rtrim((string) $_SERVER['HTTP_HOST'], '/');
    $path = easenikers_path_dasar_aplikasi();

    return $skema . '://' . $host . $path;
}

/**
 * URL_APLIKASI dari config.php (tanpa slash akhir).
 */
function aplikasi_url_konfigurasi(): string
{
    return rtrim(defined('URL_APLIKASI') ? (string) URL_APLIKASI : '', '/');
}

/**
 * URL config yang dipakai di email & auth — pada php -S (folder public) tanpa subpath /EASENIKERS/public.
 */
function aplikasi_url_konfigurasi_efektif(): string
{
    $cfg = aplikasi_url_konfigurasi();
    if ($cfg === '' || PHP_SAPI !== 'cli-server') {
        return $cfg;
    }

    $cfg_path = easenikers_path_dari_config();
    if ($cfg_path === '') {
        return $cfg;
    }

    $scheme = parse_url($cfg, PHP_URL_SCHEME) ?: easenikers_skema_permintaan();
    $host = parse_url($cfg, PHP_URL_HOST) ?: '';
    if ($host === '' && !empty($_SERVER['HTTP_HOST'])) {
        $host = (string) $_SERVER['HTTP_HOST'];
    }
    if ($host === '') {
        $host = 'localhost:8080';
    }
    $port = parse_url($cfg, PHP_URL_PORT);
    if ($port && strpos($host, ':') === false) {
        $host .= ':' . $port;
    }

    return $scheme . '://' . rtrim($host, '/');
}

/**
 * URL dasar untuk redirect_to di email Supabase (hanya config, bukan IP browser).
 */
function aplikasi_url_redirect_email(): string
{
    $cfg = aplikasi_url_konfigurasi_efektif();
    if ($cfg !== '') {
        return $cfg;
    }

    return easenikers_url_dasar_runtime();
}

/**
 * Halaman callback setelah klik tautan di email (localhost dari config bila ada).
 */
function aplikasi_url_konfirmasi_email(): string
{
    $dasar = aplikasi_url_redirect_email();
    if ($dasar === '') {
        return '';
    }

    return easenikers_gabung_url($dasar, 'login/konfirmasi_email.php');
}

function easenikers_path_dari_config(): string
{
    $cfg = aplikasi_url_konfigurasi();
    if ($cfg === '') {
        return '';
    }
    $p = (string) parse_url($cfg, PHP_URL_PATH);
    $p = rtrim($p, '/');

    return ($p === '' || $p === '/') ? '' : $p;
}

/**
 * Apakah permintaan saat ini dilayani di bawah path URL_APLIKASI (subfolder Laragon, dll.).
 */
function easenikers_permintaan_di_bawah_path_config(): bool
{
    $cfg_path = easenikers_path_dari_config();
    if ($cfg_path === '' || empty($_SERVER['REQUEST_URI'])) {
        return false;
    }

    return strpos((string) $_SERVER['REQUEST_URI'], $cfg_path) === 0;
}

/**
 * Folder public di disk (untuk cek file gambar, upload, dll.).
 */
function easenikers_folder_public(): string
{
    return dirname(__DIR__) . '/public';
}

/**
 * URL dasar untuk CSS, JS, gambar — selalu selaras dengan php -S / router (tanpa /EASENIKERS/public salah).
 */
function aplikasi_url_aset(string $jalur = ''): string
{
    $dasar = aplikasi_url_konfigurasi_efektif();
    if ($dasar === '') {
        return aplikasi_url($jalur);
    }

    return easenikers_gabung_url($dasar, $jalur);
}

function aplikasi_url(string $jalur = ''): string
{
    if (easenikers_apakah_domain_produksi()) {
        $dasar_prod = easenikers_url_dasar_untuk_link();
        if ($dasar_prod !== '') {
            return easenikers_gabung_url($dasar_prod, $jalur);
        }
    }

    if (PHP_SAPI === 'cli-server') {
        $efektif = aplikasi_url_konfigurasi_efektif();
        if ($efektif !== '') {
            return easenikers_gabung_url($efektif, $jalur);
        }
    }

    $dasar_cfg = aplikasi_url_konfigurasi();
    $dasar_runtime = easenikers_url_dasar_runtime();
    $dasar = $dasar_runtime !== '' ? $dasar_runtime : $dasar_cfg;

    if ($dasar_cfg !== '' && easenikers_permintaan_di_bawah_path_config()) {
        $cfg_path = easenikers_path_dari_config();
        if ($cfg_path !== '' && easenikers_path_dasar_aplikasi() === $cfg_path) {
            $dasar = $dasar_cfg;
        }
    }

    if ($dasar === '' && !empty($_SERVER['HTTP_HOST'])) {
        $dasar = easenikers_skema_permintaan() . '://' . rtrim((string) $_SERVER['HTTP_HOST'], '/');
    } elseif ($dasar_cfg !== '' && strpos($dasar_cfg, 'https://') === 0 && easenikers_skema_permintaan() === 'https' && $dasar_runtime === '') {
        $dasar = $dasar_cfg;
    }

    return easenikers_gabung_url($dasar, $jalur);
}

/**
 * URL untuk halaman login/konfirmasi/reset — selaras dengan link di email (URL_APLIKASI).
 */
function aplikasi_url_auth(string $jalur = ''): string
{
    $dasar = aplikasi_url_konfigurasi_efektif();
    if ($dasar === '') {
        return aplikasi_url($jalur);
    }

    return easenikers_gabung_url($dasar, $jalur);
}

/**
 * Path cookie sesi — harus sama dengan path URL yang dipakai browser.
 */
function easenikers_path_cookie_sesi(): string
{
    if (easenikers_apakah_domain_produksi()) {
        return '/';
    }

    $path = easenikers_path_dasar_aplikasi();
    if ($path !== '') {
        return $path . '/';
    }

    if (easenikers_permintaan_di_bawah_path_config()) {
        return easenikers_path_dari_config() . '/';
    }

    return '/';
}

/**
 * Deteksi apakah URL terlihat seperti local/dev (localhost, private IP, dll).
 */
function is_local_dev_url(string $url): bool
{
    if ($url === '') {
        return false;
    }
    $u = strtolower($url);
    if (strpos($u, 'localhost') !== false || strpos($u, '127.0.0.1') !== false) {
        return true;
    }
    if (preg_match('#https?://(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)#', $u)) {
        return true;
    }

    return false;
}