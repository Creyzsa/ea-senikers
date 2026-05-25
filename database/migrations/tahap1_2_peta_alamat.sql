-- ============================================================================
-- TAHAP 1.2: Tambah titik koordinat (lat/lng) untuk profil pengiriman
--   Dipakai untuk fitur peta di halaman akun pembeli. Nilai NULL artinya
--   pembeli belum menentukan lokasi di peta — alamat text tetap valid.
--
-- Aman dijalankan berulang: ADD COLUMN IF NOT EXISTS + cek constraint.
-- ============================================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS lat DOUBLE PRECISION;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS lng DOUBLE PRECISION;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'users_lat_valid') THEN
        ALTER TABLE users
            ADD CONSTRAINT users_lat_valid
            CHECK (lat IS NULL OR (lat >= -90 AND lat <= 90));
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'users_lng_valid') THEN
        ALTER TABLE users
            ADD CONSTRAINT users_lng_valid
            CHECK (lng IS NULL OR (lng >= -180 AND lng <= 180));
    END IF;
END $$;

COMMENT ON COLUMN users.lat IS 'Latitude titik alamat pembeli (boleh NULL bila tidak memakai peta).';
COMMENT ON COLUMN users.lng IS 'Longitude titik alamat pembeli (boleh NULL bila tidak memakai peta).';
