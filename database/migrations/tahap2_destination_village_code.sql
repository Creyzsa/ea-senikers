-- Kode cabang JNE (8 karakter, mis. CGK10400) untuk tujuan pengiriman.
-- WAJIB dijalankan setelah integrasi JNE (kolom lama INTEGER tidak bisa menyimpan CGK10400).
-- Aplikasi juga mencoba migrasi otomatis saat pesanan pertama (pesanan_pastikan_skema_destination_jne).

ALTER TABLE orders
    ALTER COLUMN destination_id TYPE VARCHAR(12)
    USING NULLIF(TRIM(destination_id::text), '');

COMMENT ON COLUMN orders.destination_id IS 'Kode lokasi tujuan JNE (format XXX#####, contoh CGK10400).';