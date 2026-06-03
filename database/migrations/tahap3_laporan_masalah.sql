-- ============================================================================
-- TAHAP 3: Tabel laporan masalah (Report Bug) dari pembeli
--   Menyimpan laporan kendala pembeli: kategori, deskripsi, screenshot opsional,
--   dan status penanganan oleh admin.
--
--   user_id memakai BIGINT tanpa foreign key ketat (selaras dengan orders.user_id)
--   agar aman dari perbedaan tipe pada tabel users.
--
-- Aman dijalankan ulang (IF NOT EXISTS).
-- ============================================================================

CREATE TABLE IF NOT EXISTS laporan_masalah (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT,
    kategori    VARCHAR(20)  NOT NULL,
    deskripsi   TEXT         NOT NULL,
    screenshot  VARCHAR(255),
    status      VARCHAR(20)  NOT NULL DEFAULT 'baru',
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

COMMENT ON TABLE  laporan_masalah            IS 'Laporan masalah/bug dari pembeli untuk ditindaklanjuti admin.';
COMMENT ON COLUMN laporan_masalah.user_id    IS 'ID pembeli (users.id) yang melapor, boleh kosong.';
COMMENT ON COLUMN laporan_masalah.kategori   IS 'pesanan | pembayaran | pengiriman | akun | bug.';
COMMENT ON COLUMN laporan_masalah.screenshot IS 'Nama file gambar bukti di assets/images/laporan/ (opsional).';
COMMENT ON COLUMN laporan_masalah.status     IS 'baru | diproses | selesai.';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'laporan_kategori_valid') THEN
        ALTER TABLE laporan_masalah
            ADD CONSTRAINT laporan_kategori_valid
            CHECK (kategori IN ('pesanan', 'pembayaran', 'pengiriman', 'akun', 'bug'));
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'laporan_status_valid') THEN
        ALTER TABLE laporan_masalah
            ADD CONSTRAINT laporan_status_valid
            CHECK (status IN ('baru', 'diproses', 'selesai'));
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_laporan_status  ON laporan_masalah (status);
CREATE INDEX IF NOT EXISTS idx_laporan_created ON laporan_masalah (created_at DESC);
