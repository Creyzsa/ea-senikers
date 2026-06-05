-- Kode desa/kelurahan API Co.id (10 digit) tidak muat di INTEGER 32-bit.
-- Ubah destination_id menjadi VARCHAR(12).

ALTER TABLE orders
    ALTER COLUMN destination_id TYPE VARCHAR(12)
    USING NULLIF(TRIM(destination_id::text), '');

COMMENT ON COLUMN orders.destination_id IS 'Kode desa/kelurahan tujuan (10 digit) dari API Co.id Regional.';