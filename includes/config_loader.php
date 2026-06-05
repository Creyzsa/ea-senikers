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
 *   SUPABASE_URL, SUPABASE_ANON_KEY,
 *   URL_APLIKASI,
 *   PAYMENT_CALLBACK_SECRET (optional),
 *   JNE_ORIGIN_CODE (optional, kode asal toko — contoh PDG21100),
 *   EMAIL_DRIVER, EMAIL_PENGIRIM (optional)
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
    }
}
