-- ============================================================================
-- TAHAP 1.3: Perbaiki Row Level Security (RLS) untuk tabel produk
--
-- Masalah:
--   Admin tidak bisa INSERT/UPDATE/DELETE produk dari panel admin —
--   muncul error 401 "new row violates row-level security policy".
--
-- Akar penyebab:
--   - Repositori admin produk (includes/admin_produk_repositori.php)
--     mengakses Supabase lewat REST API memakai SUPABASE_ANON_KEY,
--     sehingga diperlakukan sebagai role `anon`.
--   - RLS aktif di tabel produk dan tidak ada policy yang mengizinkan
--     anon untuk INSERT/UPDATE/DELETE.
--   - Repositori lain (orders, users) memakai koneksi PDO langsung
--     sehingga melewati RLS — itu sebabnya hanya CRUD produk yang
--     bermasalah.
--
-- Solusi:
--   Matikan RLS pada tiga tabel produk. Aman pada arsitektur saat
--   ini karena seluruh akses Supabase berjalan lewat backend PHP
--   yang sudah memvalidasi sesi admin (ambil_peran() === 'admin').
--
--   Bila kelak `SUPABASE_ANON_KEY` ingin di-expose ke client, RLS
--   wajib diaktifkan kembali dan akses admin dialihkan ke
--   service_role key.
--
-- Cara pakai: jalankan di Supabase SQL Editor. Aman dijalankan ulang.
-- ============================================================================

ALTER TABLE produk DISABLE ROW LEVEL SECURITY;
ALTER TABLE produk_gambar DISABLE ROW LEVEL SECURITY;
ALTER TABLE produk_ukuran DISABLE ROW LEVEL SECURITY;
