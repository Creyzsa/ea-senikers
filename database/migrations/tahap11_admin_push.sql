-- ============================================================================
-- TAHAP 11: Web Push notifikasi admin (Telegram + email + push browser)
--
-- Jalankan di Supabase SQL Editor setelah tahap10. Aman dijalankan ulang.
-- ============================================================================

ALTER TABLE admin_notifikasi_pengaturan
    ADD COLUMN IF NOT EXISTS push_aktif BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE admin_notifikasi_pengaturan
    ADD COLUMN IF NOT EXISTS vapid_public_key TEXT NOT NULL DEFAULT '';

ALTER TABLE admin_notifikasi_pengaturan
    ADD COLUMN IF NOT EXISTS vapid_private_key TEXT NOT NULL DEFAULT '';

COMMENT ON COLUMN admin_notifikasi_pengaturan.push_aktif IS 'Kirim Web Push ke perangkat admin saat pembayaran masuk.';
COMMENT ON COLUMN admin_notifikasi_pengaturan.vapid_public_key IS 'VAPID public key (base64url) untuk subscribe browser.';
COMMENT ON COLUMN admin_notifikasi_pengaturan.vapid_private_key IS 'VAPID private key PEM untuk tanda tangan push.';

CREATE TABLE IF NOT EXISTS admin_push_subscription (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL DEFAULT 0,
    endpoint TEXT NOT NULL,
    p256dh TEXT NOT NULL DEFAULT '',
    auth TEXT NOT NULL DEFAULT '',
    user_agent TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE admin_push_subscription IS 'Langganan Web Push admin (satu endpoint per perangkat/browser).';

CREATE UNIQUE INDEX IF NOT EXISTS idx_admin_push_subscription_endpoint
    ON admin_push_subscription (endpoint);

CREATE INDEX IF NOT EXISTS idx_admin_push_subscription_user
    ON admin_push_subscription (user_id);

ALTER TABLE admin_push_subscription DISABLE ROW LEVEL SECURITY;