-- Jalankan sekali di Supabase SQL Editor jika tabel users sudah ada tanpa kolom email.

ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255);

CREATE UNIQUE INDEX IF NOT EXISTS users_email_unique ON users (email);
