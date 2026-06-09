-- ============================================================================
-- TAHAP 8: Stok otomatis saat pembayaran / pengembalian saat batal
-- Kolom stok_dipotong mencegah pengurangan ganda per pesanan.
-- Jalankan di Supabase SQL Editor. Aman dijalankan ulang.
-- ============================================================================

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS stok_dipotong BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN orders.stok_dipotong IS
    'True jika stok produk_ukuran sudah dikurangi untuk pesanan ini (status paid+).';

CREATE UNIQUE INDEX IF NOT EXISTS idx_produk_ukuran_id_ukuran
    ON produk_ukuran (id_produk, ukuran);