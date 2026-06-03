<?php
/**
 * Koneksi database Supabase (PostgreSQL).
 * Session pooler: host & user dari Dashboard → Database → Connection string (Session pooler).
 * User pooler = postgres.NOMORPROYEK (bukan hanya "postgres").
 * 
 * CARA PAKAI:
 * 1. Copy file ini menjadi config.php
 * 2. Isi dengan kredensial asli dari Supabase kamu
 * 3. JANGAN commit config.php ke Git (sudah ada di .gitignore)
 * 4. Upload config.php langsung ke server via File Manager / FTP
 */

/**
 * Database PostgreSQL Supabase
 */
define('DB_HOST', 'ganti-dengan-pooler.supabase.com');   // Contoh: aws-1-ap-northeast-1.pooler.supabase.com
define('DB_PORT', '5432');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres.PROJECT_REF');              // Contoh: postgres.higdzjptfukowzkbmzvg
define('DB_PASS', 'PASSWORD_SUPABASE_KAMU');

/**
 * Supabase — Authentication (REST) & PostgREST
 * Ambil dari: Supabase Dashboard → Settings → API
 */
define('SUPABASE_URL', 'https://xxxxxxxx.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...');

/**
 * URL dasar situs (tanpa slash di akhir).
 * 
 * PENTING untuk production:
 * - Samakan persis dengan Supabase → Authentication → URL Configuration → Site URL
 * - Tambahkan juga di Redirect URLs: https://easenikers.shop/**
 * 
 * Contoh production:
 * define('URL_APLIKASI', 'https://easenikers.shop');
 */
define('URL_APLIKASI', 'https://easenikers.shop');

/**
 * Secret untuk webhook payment gateway (Tripay dll).
 * Kosongkan dulu jika belum pakai payment gateway.
 */
define('PAYMENT_CALLBACK_SECRET', '');

/** Email driver (log = hanya catat di log, mail = kirim email asli) */
define('EMAIL_DRIVER', 'log');
define('EMAIL_PENGIRIM', 'EA SENIKERS <noreply@easenikers.shop>');
