-- ============================================================================
-- TAHAP 7: Isi order_items.id_produk yang masih NULL (pesanan lama)
-- Membantu verifikasi ulasan & statistik produk.
-- Jalankan di Supabase SQL Editor. Aman dijalankan ulang.
-- ============================================================================

UPDATE order_items oi
SET id_produk = p.id_produk
FROM produk p
WHERE oi.id_produk IS NULL
  AND LOWER(TRIM(oi.product_name)) = LOWER(TRIM(p.nama_produk));