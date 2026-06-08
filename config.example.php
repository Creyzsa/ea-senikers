<?php
/**
 * Koneksi database Supabase (PostgreSQL) via PDO.
 * File ini tidak di-commit (.gitignore) — copy ke config.php dan isi.
 *
 * PENTING UNTUK LOCAL LARAGON / WINDOWS:
 *   Error "could not translate host name '....pooler.supabase.com' to address: Unknown host"
 *   biasanya karena DNS local tidak resolve pooler subdomain, atau offline, firewall, ISP.
 *
 *   SOLUSI CEPAT:
 *   1. Di Supabase Dashboard → Database → Connect → pilih "Direct connection" (bukan Pooler).
 *   2. Copy host (biasanya db.<ref>.supabase.co atau aws-1-ap-northeast-1.supabase.co TANPA .pooler)
 *   3. Port 5432
 *   4. User: postgres   <--- BUKAN postgres.ref
 *   5. Password: database password (bukan pooler password)
 *   6. Isi di config.php kamu (local).
 *
 *   Pooler (yang sekarang error) lebih cocok untuk production/serverless (Vercel) karena connection limit.
 *
 * Untuk production / Vercel:
 * - JANGAN commit config.php
 * - Set env vars di Vercel: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, SUPABASE_URL, SUPABASE_ANON_KEY, URL_APLIKASI, ...
 * - Pakai Session/Transaction Pooler dari Connect dialog (user = postgres.<ref>, host = *.pooler...)
 */

define('DB_HOST', 'db.your-project-ref.supabase.co');   // LOCAL: pakai Direct connection host (tanpa .pooler). PROD: pooler host
define('DB_PORT', '5432');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres');                           // LOCAL/Direct: "postgres".  POOLER: "postgres.your-ref"
define('DB_PASS', 'your-database-password-here');

define('SUPABASE_URL', 'https://your-project-ref.supabase.co');
define('SUPABASE_ANON_KEY', 'your-anon-key-here');

define('URL_APLIKASI', 'https://www.easenikers.shop');   // PROD. Untuk local dev lihat komentar di bawah + config.php

/*
 * PENTING untuk fitur daftar & reset password (email konfirmasi):
 * - URL_APLIKASI dipakai untuk membangun redirect_to yang dikirim ke Supabase saat signup/recover.
 * - Link di email konfirmasi akan mengarah ke URL_APLIKASI + /login/konfirmasi_email.php?token_hash=...
 * - Jika pakai localhost / 192.168.x.x , user yang klik dari email (HP di 4G, Gmail, luar WiFi) akan dapat error "This site can’t be reached".
 *
 * - TIDAK HARUS PAKAI NGROK untuk test murni di satu mesin:
 *   Set URL_APLIKASI = 'http://localhost:8080/EASENIKERS/public'
 *   Tambahkan ke Supabase Redirect URLs:
 *     http://localhost:8080/EASENIKERS/public/login/konfirmasi_email.php
 *     http://localhost:8080/EASENIKERS/public/**
 *   Buka daftar/lupa via localhost URL.
 *   Buka email di browser Gmail/Outlook **di komputer yang sama**.
 *   Klik link → localhost biasanya resolve ke local server kamu.
 *
 * - Kalau butuh akses dari HP / jaringan lain:
 *   Pakai ngrok (https://ngrok.com), set URL_APLIKASI ke ngrok URL + subpath, tambah ke Redirect URLs, buka via ngrok URL.
 *
 * - Di Supabase Dashboard WAJIB set (Authentication → URL Configuration):
 *     Site URL: https://www.easenikers.shop (atau localhost untuk dev)
 *     Redirect URLs: sesuaikan dengan yang di atas (prod + localhost + ngrok)
 *
 *   Contoh lengkap untuk local IP (192.168.0.120) seperti testing kamu:
 *   http://192.168.0.120/EASENIKERS/public/*
 *   http://192.168.0.120/EASENIKERS/public
 *   http://192.168.0.120/EASENIKERS/public/index.php
 *   http://192.168.0.120/EASENIKERS/public/login/masuk.php
 *   http://192.168.0.120/EASENIKERS/public/login/daftar.php
 *   http://192.168.0.120/EASENIKERS/public/login/konfirmasi_email.php
 *   http://192.168.0.120/EASENIKERS/public/login/lupa_sandi.php
 *   http://192.168.0.120/EASENIKERS/public/login/setel_sandi_baru.php
 *
 * - Saran: update juga Email Templates di Supabase supaya pakai token_hash (lihat komentar di includes/auth_db/supabase_auth.php).
 */

/** Legacy — webhook Pakasir tidak memakai secret ini (verifikasi via API Pakasir). */
define('PAYMENT_CALLBACK_SECRET', '');

/** Kode asal JNE toko (3 huruf + 5 angka). EA Senikers Padang Panjang = PDG21100 */
define('JNE_ORIGIN_CODE', 'PDG21100');

/** Pakasir (opsional di config.php — alternatif: Admin Pengaturan atau env Vercel) */
// define('PAKASIR_PROJECT_SLUG', 'easenikers');
// define('PAKASIR_API_KEY', 'your-pakasir-api-key');
// define('PAKASIR_MODE', 'sandbox');
// define('PAKASIR_METODE_DEFAULT', 'qris');

define('EMAIL_DRIVER', 'log');
define('EMAIL_PENGIRIM', 'EA SENIKERS <noreply@example.com>');
