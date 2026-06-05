-- ============================================================================
-- TAHAP 4: Fitur tambahan marketplace
--  - Ulasan & rating produk (verified purchase via order_id optional)
--  - Wishlist
--  - Chat antara pembeli & penjual (admin)
--  - Denormalized sold count + rating di produk
--  - Tambah id_produk ke order_items untuk linking akurat
--
-- Jalankan di Supabase SQL Editor. Aman re-run (IF NOT EXISTS).
-- ============================================================================

-- 1. Tambah kolom ke produk untuk sold & rating (denormalized, update via triggers atau app logic)
ALTER TABLE produk
    ADD COLUMN IF NOT EXISTS terjual INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rating_rata NUMERIC(3,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS jumlah_ulasan INTEGER NOT NULL DEFAULT 0;

COMMENT ON COLUMN produk.terjual IS 'Jumlah terjual (dari order_items dengan status paid+).';
COMMENT ON COLUMN produk.rating_rata IS 'Rata-rata rating 1-5 dari ulasan.';
COMMENT ON COLUMN produk.jumlah_ulasan IS 'Jumlah ulasan untuk produk ini.';

-- 2. Perbaiki order_items: tambah id_produk (UUID) agar bisa link akurat ke produk
ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS id_produk UUID;

COMMENT ON COLUMN order_items.id_produk IS 'FK ke produk.id_produk (UUID) untuk sold count & review verification.';

-- 3. Tabel ulasan produk
CREATE TABLE IF NOT EXISTS ulasan (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    id_produk UUID NOT NULL,
    order_id BIGINT,
    rating SMALLINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    komentar TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, id_produk)  -- 1 review per user per product (bisa diubah jika mau multiple)
);

COMMENT ON TABLE ulasan IS 'Ulasan & rating produk oleh pembeli. Bisa di-link ke order untuk verified purchase.';
COMMENT ON COLUMN ulasan.user_id IS 'id dari tabel users (bigint).';
COMMENT ON COLUMN ulasan.id_produk IS 'UUID dari produk.id_produk.';
COMMENT ON COLUMN ulasan.order_id IS 'Opsional: order yang membeli produk ini (untuk verified review).';

-- Index untuk query cepat
CREATE INDEX IF NOT EXISTS idx_ulasan_id_produk ON ulasan (id_produk);
CREATE INDEX IF NOT EXISTS idx_ulasan_user_id ON ulasan (user_id);

-- 4. Tabel wishlist
CREATE TABLE IF NOT EXISTS wishlist (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    id_produk UUID NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, id_produk)
);

COMMENT ON TABLE wishlist IS 'Wishlist / favorit produk per user.';
CREATE INDEX IF NOT EXISTS idx_wishlist_user_id ON wishlist (user_id);
CREATE INDEX IF NOT EXISTS idx_wishlist_id_produk ON wishlist (id_produk);

-- 5. Tabel chat_messages (pembeli <-> penjual/admin)
CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGSERIAL PRIMARY KEY,
    from_user_id BIGINT NOT NULL,
    to_user_id BIGINT NOT NULL,
    id_produk UUID,
    order_id BIGINT,
    message TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE chat_messages IS 'Chat antara pembeli dan penjual (admin). Bisa terkait produk atau order.';
COMMENT ON COLUMN chat_messages.from_user_id IS 'Pengirim (id users bigint).';
COMMENT ON COLUMN chat_messages.to_user_id IS 'Penerima (biasanya admin atau pembeli).';
COMMENT ON COLUMN chat_messages.id_produk IS 'Konteks produk (opsional).';
COMMENT ON COLUMN chat_messages.order_id IS 'Konteks pesanan (opsional).';

CREATE INDEX IF NOT EXISTS idx_chat_from ON chat_messages (from_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_chat_to ON chat_messages (to_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_chat_produk ON chat_messages (id_produk);
CREATE INDEX IF NOT EXISTS idx_chat_order ON chat_messages (order_id);

-- Optional: view sederhana untuk unread count (bisa pakai di app nanti)
-- (tidak wajib)

-- 6. Trigger/helper untuk update rating & terjual di produk (opsional, untuk mantap)
-- Untuk sekarang, update manual di app logic saat insert ulasan / update order status.
-- Bisa tambah trigger nanti jika mau.

-- Contoh function (bisa di-extend):
-- CREATE OR REPLACE FUNCTION update_produk_stats() RETURNS trigger AS $$
-- BEGIN
--   -- update rating_rata, jumlah_ulasan, terjual
--   RETURN NEW;
-- END;
-- $$ LANGUAGE plpgsql;

-- Catatan RLS (penting untuk arsitektur project ini):
-- Karena backend PHP menggunakan SUPABASE_ANON_KEY untuk REST (bukan user JWT),
-- dan validasi dilakukan di PHP (setelah wajib_sudah_masuk() dll),
-- lebih aman & konsisten dengan produk untuk **DISABLE RLS** pada tabel baru.
-- (Sama seperti tahap1_3 yang disable RLS di produk_* )
--
-- Jalankan ini setelah migration:
-- ALTER TABLE ulasan DISABLE ROW LEVEL SECURITY;
-- ALTER TABLE wishlist DISABLE ROW LEVEL SECURITY;
-- ALTER TABLE chat_messages DISABLE ROW LEVEL SECURITY;
--
-- Kalau suatu saat mau expose REST ke client + pakai RLS ketat, baru enable + buat policy.

-- Selesai tahap 4.
