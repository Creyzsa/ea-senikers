-- ============================================================================
-- TAHAP 2: Tambah info pengiriman pada tabel orders
--   Menyimpan pilihan kurir, layanan, ongkir, destination_id RajaOngkir,
--   dan nomor resi yang akan diisi admin saat status berubah ke "shipped".
--
-- Aman dijalankan ulang (IF NOT EXISTS).
-- ============================================================================

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS kurir VARCHAR(20),
    ADD COLUMN IF NOT EXISTS layanan VARCHAR(40),
    ADD COLUMN IF NOT EXISTS ongkir INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS destination_id INTEGER,
    ADD COLUMN IF NOT EXISTS nomor_resi VARCHAR(60);

COMMENT ON COLUMN orders.kurir IS 'Kode kurir RajaOngkir (jne/pos/tiki/dst).';
COMMENT ON COLUMN orders.layanan IS 'Nama layanan kurir, mis. REG / YES / OKE.';
COMMENT ON COLUMN orders.ongkir IS 'Biaya kirim dalam rupiah (dari API RajaOngkir).';
COMMENT ON COLUMN orders.destination_id IS 'destination_id RajaOngkir untuk alamat pembeli.';
COMMENT ON COLUMN orders.nomor_resi IS 'Nomor resi pengiriman, diisi admin saat barang dikirim.';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'orders_ongkir_non_negatif') THEN
        ALTER TABLE orders
            ADD CONSTRAINT orders_ongkir_non_negatif
            CHECK (ongkir >= 0);
    END IF;
END $$;
