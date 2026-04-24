-- Jalankan di Supabase: SQL Editor -> New query -> Run
-- Nama tabel/kolom pakai bahasa Inggris (biasa di SQL); isi peran pakai: admin / pembeli
-- Reset sandi: lewat Supabase Auth (bukan tabel token lokal). Hapus sisa: migrasi_hapus_password_reset_tokens.sql

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) UNIQUE,
    password_hash TEXT NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'pembeli')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
