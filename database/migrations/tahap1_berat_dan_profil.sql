-- ============================================================================
-- TAHAP 1: Persiapan jualan
--   1. Tambah kolom berat_gram di produk (untuk hitung ongkir RajaOngkir nanti)
--   2. Tambah kolom profil pengiriman di users (no HP & alamat default pembeli)
--
-- Cara pakai: jalankan di Supabase SQL Editor. Aman dijalankan ulang
-- (IF NOT EXISTS) — tidak akan menggandakan kolom.
-- ============================================================================

-- 1) Berat per produk (gram). Default 1000g = 1kg.
ALTER TABLE produk
    ADD COLUMN IF NOT EXISTS berat_gram INTEGER NOT NULL DEFAULT 1000;

-- Guard the constraint so the migration is safe to re-run (idempotent)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'produk_berat_gram_positif' 
          AND conrelid = 'produk'::regclass
    ) THEN
        ALTER TABLE produk
            ADD CONSTRAINT produk_berat_gram_positif
            CHECK (berat_gram > 0);
    END IF;
END $$;

COMMENT ON COLUMN produk.berat_gram IS 'Berat produk dalam gram, dipakai untuk perhitungan ongkir.';

-- 2) Kolom profil pengiriman di users.
-- Field-field ini boleh kosong sampai pembeli mengisinya dari halaman akun.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS no_hp VARCHAR(20),
    ADD COLUMN IF NOT EXISTS nama_penerima VARCHAR(120),
    ADD COLUMN IF NOT EXISTS provinsi VARCHAR(120),
    ADD COLUMN IF NOT EXISTS kota VARCHAR(120),
    ADD COLUMN IF NOT EXISTS kecamatan VARCHAR(120),
    ADD COLUMN IF NOT EXISTS kode_pos VARCHAR(10),
    ADD COLUMN IF NOT EXISTS alamat_detail TEXT;

COMMENT ON COLUMN users.no_hp IS 'Nomor HP pembeli (format bebas, divalidasi PHP).';
COMMENT ON COLUMN users.nama_penerima IS 'Nama penerima paket (boleh beda dengan username).';
COMMENT ON COLUMN users.alamat_detail IS 'Detail alamat: nama jalan, nomor rumah, RT/RW, patokan.';
