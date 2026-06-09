-- ============================================================================
-- TAHAP 9: Mapping logo brand di database (bukan file JSON)
--
-- Vercel filesystem read-only — metadata logo brand harus di Supabase,
-- bukan includes/brand_logo_admin.json.
--
-- Jalankan di Supabase SQL Editor. Aman dijalankan ulang.
-- ============================================================================

CREATE TABLE IF NOT EXISTS brand_logo (
    nama_brand TEXT PRIMARY KEY,
    nama_file TEXT NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE brand_logo IS 'Mapping nama brand ke file logo di bucket produk-gambar (prefix brand_).';
COMMENT ON COLUMN brand_logo.nama_brand IS 'Nama brand persis seperti di kolom produk.brand.';
COMMENT ON COLUMN brand_logo.nama_file IS 'Nama file di bucket produk-gambar, mis. brand_nike_a1b2c3d4.jpg.';

CREATE INDEX IF NOT EXISTS idx_brand_logo_updated_at ON brand_logo (updated_at DESC);

-- Backend PHP (admin) menulis via PDO; katalog membaca via REST/PDO
ALTER TABLE brand_logo DISABLE ROW LEVEL SECURITY;