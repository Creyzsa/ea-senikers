-- ============================================================================
-- TAHAP 10: Notifikasi admin (Telegram, email SMTP, polling browser)
--
-- Pengaturan disimpan di Supabase (Vercel filesystem read-only).
-- Jalankan di Supabase SQL Editor. Aman dijalankan ulang.
-- ============================================================================

CREATE TABLE IF NOT EXISTS admin_notifikasi_pengaturan (
    id SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    telegram_bot_token TEXT NOT NULL DEFAULT '',
    telegram_chat_id TEXT NOT NULL DEFAULT '',
    telegram_aktif BOOLEAN NOT NULL DEFAULT FALSE,
    smtp_host TEXT NOT NULL DEFAULT '',
    smtp_port INTEGER NOT NULL DEFAULT 587,
    smtp_user TEXT NOT NULL DEFAULT '',
    smtp_pass TEXT NOT NULL DEFAULT '',
    smtp_from TEXT NOT NULL DEFAULT '',
    smtp_to TEXT NOT NULL DEFAULT '',
    email_aktif BOOLEAN NOT NULL DEFAULT FALSE,
    notif_browser_aktif BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE admin_notifikasi_pengaturan IS 'Pengaturan notifikasi admin (satu baris id=1).';

INSERT INTO admin_notifikasi_pengaturan (id)
VALUES (1)
ON CONFLICT (id) DO NOTHING;

CREATE TABLE IF NOT EXISTS admin_pembayaran_notifikasi (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL,
    total_price INTEGER NOT NULL DEFAULT 0,
    payment_method TEXT NOT NULL DEFAULT '',
    customer_name TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE admin_pembayaran_notifikasi IS 'Log event pembayaran masuk untuk polling browser admin.';

CREATE UNIQUE INDEX IF NOT EXISTS idx_admin_pembayaran_notifikasi_order
    ON admin_pembayaran_notifikasi (order_id);

CREATE INDEX IF NOT EXISTS idx_admin_pembayaran_notifikasi_created
    ON admin_pembayaran_notifikasi (created_at DESC);

ALTER TABLE admin_notifikasi_pengaturan DISABLE ROW LEVEL SECURITY;
ALTER TABLE admin_pembayaran_notifikasi DISABLE ROW LEVEL SECURITY;