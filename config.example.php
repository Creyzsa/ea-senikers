<?php
/**
 * Koneksi database Supabase (PostgreSQL).
 * Session pooler: host & user dari Dashboard → Database → Connection string (Session pooler).
 * User pooler = postgres.NOMORPROYEK (bukan hanya "postgres").
 * File ini tidak di-commit (.gitignore).
 *
 * For Vercel / production deployment:
 * - Do NOT commit this file with real values.
 * - Instead, set the following as Environment Variables in your hosting platform (Vercel, etc.):
 *   DB_HOST
 *   DB_PORT
 *   DB_NAME
 *   DB_USER
 *   DB_PASS
 *   SUPABASE_URL
 *   SUPABASE_ANON_KEY
 *   URL_APLIKASI
 *   PAYMENT_CALLBACK_SECRET (optional)
 *   EMAIL_DRIVER (optional)
 *   EMAIL_PENGIRIM (optional)
 */

define('DB_HOST', 'aws-1-ap-northeast-1.pooler.supabase.com');
define('DB_PORT', '5432');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres.your-project-ref');
define('DB_PASS', 'your-db-password');

define('SUPABASE_URL', 'https://your-project-ref.supabase.co');
define('SUPABASE_ANON_KEY', 'your-anon-key-here');

define('URL_APLIKASI', 'https://www.your-domain.com');  // no trailing slash, use https for production

define('PAYMENT_CALLBACK_SECRET', '');

define('EMAIL_DRIVER', 'log');
define('EMAIL_PENGIRIM', 'EA SENIKERS <noreply@your-domain.com>');
