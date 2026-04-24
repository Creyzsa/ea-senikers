-- ═══════════════════════════════════════════════════════════════════════════
-- LANGKAH 1 — Hanya buang tabel pesanan (Supabase → SQL Editor → Run).
-- Setelah sukses, lanjut LANGKAH 2: buka orders_rebuild_dan_contoh.sql
--   → ganti email → Run (buat tabel lagi + contoh data).
-- ═══════════════════════════════════════════════════════════════════════════

DROP TABLE IF EXISTS order_items CASCADE;
DROP TABLE IF EXISTS orders CASCADE;
