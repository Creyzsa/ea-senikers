<?php
/**
 * Safe config loader for local (config.php) and serverless like Vercel (env vars).
 * 
 * LOCAL LARAGON (Windows):
 *   Copy config.example.php → config.php (root)
 *   Gunakan "Direct connection" dari Supabase (bukan pooler) agar tidak kena "Unknown host pooler".
 *   Lihat komentar di config.example.php untuk detail host/user.
 *
 * On Vercel / production:
 * - Do NOT commit config.php
 * - Set these Environment Variables in Vercel Dashboard (Project > Settings > Environment Variables):
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS,
 *   SUPABASE_URL, SUPABASE_ANON_KEY, SUPABASE_SERVICE_ROLE_KEY (upload gambar di Vercel),
 *   SUPABASE_BUCKET_PRODUK (opsional, default produk-gambar),
 *   URL_APLIKASI,
 *   PAYMENT_CALLBACK_SECRET (optional),
 *   PAKASIR_PROJECT_SLUG, PAKASIR_API_KEY, PAKASIR_MODE (sandbox|production),
 *   PAKASIR_METODE_DEFAULT (optional, default qris),
 *   JNE_ORIGIN_CODE (optional, kode asal toko — contoh PDG21100),
 *   RAJAONGKIR_API_KEY (RajaOngkir Komerce API key), RAJAONGKIR_ORIGIN_ID (optional, ID asal toko — contoh 48850),
 *   EMAIL_DRIVER, EMAIL_PENGIRIM (optional),
 *   TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM, SMTP_TO (optional)
 */

if (!defined('DB_HOST')) {
    $configFile = __DIR__ . '/../config.php';

    if (file_exists($configFile)) {
        // Local development
        require_once $configFile;
    } else {
        // Vercel / CI / production without committed config.php
        define('DB_HOST', getenv('DB_HOST') ?: '');
        define('DB_PORT', getenv('DB_PORT') ?: '5432');
        define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
        define('DB_USER', getenv('DB_USER') ?: '');
        define('DB_PASS', getenv('DB_PASS') ?: '');

        define('SUPABASE_URL', getenv('SUPABASE_URL') ?: '');
        define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: '');
        $service_role = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
        if ($service_role !== '') {
            define('SUPABASE_SERVICE_ROLE_KEY', $service_role);
        }
        define('SUPABASE_BUCKET_PRODUK', getenv('SUPABASE_BUCKET_PRODUK') ?: 'produk-gambar');

        $url = getenv('URL_APLIKASI') ?: '';
        if (empty($url) || strpos($url, 'http://') === 0) {
            // Always prefer https for this production domain
            $url = 'https://www.easenikers.shop';
        }
        define('URL_APLIKASI', $url);

        define('PAYMENT_CALLBACK_SECRET', getenv('PAYMENT_CALLBACK_SECRET') ?: '');

        define('EMAIL_DRIVER', getenv('EMAIL_DRIVER') ?: 'log');
        define('EMAIL_PENGIRIM', getenv('EMAIL_PENGIRIM') ?: 'EA SENIKERS <noreply@example.com>');

        if (!defined('JNE_ORIGIN_CODE')) {
            define('JNE_ORIGIN_CODE', getenv('JNE_ORIGIN_CODE') ?: 'PDG21100');
        }

        if (!defined('RAJAONGKIR_API_KEY')) {
            define('RAJAONGKIR_API_KEY', getenv('RAJAONGKIR_API_KEY') ?: '');
        }
        if (!defined('RAJAONGKIR_ORIGIN_ID')) {
            define('RAJAONGKIR_ORIGIN_ID', getenv('RAJAONGKIR_ORIGIN_ID') ?: '48850');
        }

        if (!defined('PAKASIR_PROJECT_SLUG')) {
            define('PAKASIR_PROJECT_SLUG', getenv('PAKASIR_PROJECT_SLUG') ?: getenv('PAKASIR_PROJECT') ?: '');
        }
        if (!defined('PAKASIR_API_KEY')) {
            define('PAKASIR_API_KEY', getenv('PAKASIR_API_KEY') ?: '');
        }
        if (!defined('PAKASIR_MODE')) {
            define('PAKASIR_MODE', getenv('PAKASIR_MODE') ?: 'sandbox');
        }
        if (!defined('PAKASIR_METODE_DEFAULT')) {
            define('PAKASIR_METODE_DEFAULT', getenv('PAKASIR_METODE_DEFAULT') ?: 'qris');
        }
    }
}

/**
 * Pakasir: env / vercel.json boleh mengisi konstanta meski config.php sudah dimuat.
 * Prioritas tetap: env → konstanta → pengaturan_toko_admin.json (di pakasir_konfigurasi).
 */
if (!function_exists('easenikers_definisikan_pakasir_dari_env')) {
    function easenikers_definisikan_pakasir_dari_env(): void
    {
        if (!defined('PAKASIR_PROJECT_SLUG')) {
            $slug = getenv('PAKASIR_PROJECT_SLUG') ?: getenv('PAKASIR_PROJECT') ?: '';
            if (is_string($slug) && trim($slug) !== '') {
                define('PAKASIR_PROJECT_SLUG', trim($slug));
            }
        }
        if (!defined('PAKASIR_API_KEY')) {
            $key = getenv('PAKASIR_API_KEY') ?: '';
            if (is_string($key) && trim($key) !== '') {
                define('PAKASIR_API_KEY', trim($key));
            }
        }
        if (!defined('PAKASIR_MODE')) {
            $mode = strtolower(trim((string) (getenv('PAKASIR_MODE') ?: 'sandbox')));
            define('PAKASIR_MODE', in_array($mode, ['sandbox', 'production'], true) ? $mode : 'sandbox');
        }
        if (!defined('PAKASIR_METODE_DEFAULT')) {
            $metode = strtolower(trim((string) (getenv('PAKASIR_METODE_DEFAULT') ?: 'qris')));
            define('PAKASIR_METODE_DEFAULT', $metode !== '' ? $metode : 'qris');
        }
    }
}
easenikers_definisikan_pakasir_dari_env();

/**
 * RajaOngkir: env / vercel.json boleh mengisi konstanta meski config.php sudah dimuat.
 * Prioritas: env/konstanta RAJAONGKIR_API_KEY → pengaturan_toko_admin.json (rajaongkir_api_key).
 */
if (!function_exists('easenikers_definisikan_rajaongkir_dari_env')) {
    function easenikers_definisikan_rajaongkir_dari_env(): void
    {
        if (!defined('RAJAONGKIR_API_KEY')) {
            $key = getenv('RAJAONGKIR_API_KEY') ?: '';
            if (is_string($key) && trim($key) !== '') {
                define('RAJAONGKIR_API_KEY', trim($key));
            }
        }
        if (!defined('RAJAONGKIR_ORIGIN_ID')) {
            $origin = getenv('RAJAONGKIR_ORIGIN_ID') ?: '';
            if (is_string($origin) && trim($origin) !== '') {
                define('RAJAONGKIR_ORIGIN_ID', trim($origin));
            }
        }
    }
}
easenikers_definisikan_rajaongkir_dari_env();

if (!function_exists('easenikers_definisikan_supabase_storage_dari_env')) {
    function easenikers_definisikan_supabase_storage_dari_env(): void
    {
        if (!defined('SUPABASE_SERVICE_ROLE_KEY')) {
            $service_role = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
            if (is_string($service_role) && trim($service_role) !== '') {
                define('SUPABASE_SERVICE_ROLE_KEY', trim($service_role));
            }
        }
        if (!defined('SUPABASE_BUCKET_PRODUK')) {
            $bucket = getenv('SUPABASE_BUCKET_PRODUK') ?: 'produk-gambar';
            define('SUPABASE_BUCKET_PRODUK', is_string($bucket) && trim($bucket) !== '' ? trim($bucket) : 'produk-gambar');
        }
    }
}
easenikers_definisikan_supabase_storage_dari_env();
