-- ============================================================================
-- TAHAP 4.1: Ulasan per pesanan (1 ulasan per order, 1x edit)
--
-- Aturan:
--   - Satu ulasan per kombinasi (user_id, order_id, id_produk)
--   - Beli produk yang sama lagi (order baru) → boleh ulasan baru
--   - Setelah kirim ulasan, boleh edit sekali (kolom edited_at)
--   - Setelah diedit, ulasan dikunci
--
-- Jalankan di Supabase SQL Editor setelah deploy kode.
-- ============================================================================

ALTER TABLE ulasan
    ADD COLUMN IF NOT EXISTS edited_at TIMESTAMPTZ;

COMMENT ON COLUMN ulasan.edited_at IS 'Diisi sekali saat pembeli mengedit ulasan; setelah itu ulasan tidak bisa diubah lagi.';

-- Hapus batasan lama: 1 ulasan per user per produk
ALTER TABLE ulasan DROP CONSTRAINT IF EXISTS ulasan_user_id_id_produk_key;

-- Satu ulasan per pesanan per produk (order_id wajib diisi oleh app untuk ulasan baru)
CREATE UNIQUE INDEX IF NOT EXISTS idx_ulasan_satu_per_pesanan
    ON ulasan (user_id, order_id, id_produk)
    WHERE order_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_ulasan_order_id ON ulasan (order_id);