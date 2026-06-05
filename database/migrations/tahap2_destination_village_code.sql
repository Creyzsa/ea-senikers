-- Kode cabang JNE (8 karakter, mis. PDG21100) untuk tujuan pengiriman.
-- Ubah destination_id menjadi VARCHAR(12).

ALTER TABLE orders
    ALTER COLUMN destination_id TYPE VARCHAR(12)
    USING NULLIF(TRIM(destination_id::text), '');

COMMENT ON COLUMN orders.destination_id IS 'Kode lokasi tujuan JNE (format XXX#####, contoh CGK10400).';