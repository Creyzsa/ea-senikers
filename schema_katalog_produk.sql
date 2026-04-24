-- =============================================================================
-- EA SENIKERS — Katalog produk (Supabase / PostgreSQL)
-- Jalankan seluruh skrip ini di: Supabase Dashboard → SQL Editor → Run
--
-- Struktur folder (gambar lokal, hanya nama file di DB):
--   public/assets/images/produk/
-- Halaman PHP:
--   public/pembeli/produk.php          — grid katalog
--   public/pembeli/detail_produk.php — detail ?id=<uuid>
-- Helper: includes/katalog_produk.php + includes/supabase_rest.php
-- =============================================================================

-- --- Tabel utama ---
CREATE TABLE IF NOT EXISTS public.produk (
    id_produk UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nama_produk TEXT NOT NULL,
    brand TEXT NOT NULL,
    kategori TEXT NOT NULL,
    kondisi TEXT NOT NULL CHECK (kondisi IN ('Baru', 'Second')),
    harga INTEGER NOT NULL CHECK (harga >= 0),
    deskripsi TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.produk_gambar (
    id_gambar UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    id_produk UUID NOT NULL REFERENCES public.produk (id_produk) ON DELETE CASCADE,
    nama_file TEXT NOT NULL,
    urutan SMALLINT NOT NULL DEFAULT 0,
    UNIQUE (id_produk, nama_file)
);

CREATE TABLE IF NOT EXISTS public.produk_ukuran (
    id_ukuran UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    id_produk UUID NOT NULL REFERENCES public.produk (id_produk) ON DELETE CASCADE,
    ukuran TEXT NOT NULL,
    stok INTEGER NOT NULL DEFAULT 0 CHECK (stok >= 0),
    UNIQUE (id_produk, ukuran)
);

CREATE INDEX IF NOT EXISTS idx_produk_gambar_produk ON public.produk_gambar (id_produk);
CREATE INDEX IF NOT EXISTS idx_produk_ukuran_produk ON public.produk_ukuran (id_produk);
CREATE INDEX IF NOT EXISTS idx_produk_created ON public.produk (created_at DESC);

-- --- Row Level Security (baca publik lewat anon key) ---
ALTER TABLE public.produk ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.produk_gambar ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.produk_ukuran ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "produk_baca_publik" ON public.produk;
CREATE POLICY "produk_baca_publik"
    ON public.produk FOR SELECT
    TO anon, authenticated
    USING (true);

DROP POLICY IF EXISTS "produk_gambar_baca_publik" ON public.produk_gambar;
CREATE POLICY "produk_gambar_baca_publik"
    ON public.produk_gambar FOR SELECT
    TO anon, authenticated
    USING (true);

DROP POLICY IF EXISTS "produk_ukuran_baca_publik" ON public.produk_ukuran;
CREATE POLICY "produk_ukuran_baca_publik"
    ON public.produk_ukuran FOR SELECT
    TO anon, authenticated
    USING (true);

-- (Tambah policy INSERT/UPDATE untuk admin lewat service_role atau autentikasi terpisah bila perlu.)

-- =============================================================================
-- Data contoh: Nike (Baru), Adidas (Second), Vans (Baru)
-- Gambar = nama file di server: public/assets/images/produk/ (lihat repo)
-- =============================================================================

INSERT INTO public.produk (id_produk, nama_produk, brand, kategori, kondisi, harga, deskripsi)
VALUES
    (
        'a1111111-1111-4111-8111-111111111111',
        'Nike Air Max 90 Essential',
        'Nike',
        'Sneakers',
        'Baru',
        1899000,
        'Sneakers Nike Air Max 90 dengan unit Air di tumit untuk kenyamanan harian. Upper kombinasi kulit dan mesh, sol karet tahan lama. Cocok untuk jalan santai maupun aktivitas ringan.'
    ),
    (
        'b2222222-2222-4222-8222-222222222222',
        'Adidas Gazelle Vintage',
        'Adidas',
        'Sneakers',
        'Second',
        920000,
        'Preloved terkurasi: suede upper dengan nuansa vintage. Sol dan lem dicek, insole diganti bila perlu. Kondisi di foto = kondisi aktual; silakan baca ukuran dan catatan di deskripsi ukuran.'
    ),
    (
        'c3333333-3333-4333-8333-333333333333',
        'Vans Old Skool Classic',
        'Vans',
        'Sneakers',
        'Baru',
        899000,
        'Vans Old Skool stripe samping ikonik, upper canvas & suede, sol waffle khas Vans. Baru original box (jika stok tersedia di gudang).'
    )
ON CONFLICT (id_produk) DO NOTHING;

INSERT INTO public.produk_gambar (id_produk, nama_file, urutan)
VALUES
    ('a1111111-1111-4111-8111-111111111111', 'nike-air-max-utama.svg', 0),
    ('a1111111-1111-4111-8111-111111111111', 'nike-air-max-samping.svg', 1),
    ('b2222222-2222-4222-8222-222222222222', 'adidas-gazelle-utama.svg', 0),
    ('c3333333-3333-4333-8333-333333333333', 'vans-old-skool-utama.svg', 0)
ON CONFLICT (id_produk, nama_file) DO NOTHING;

INSERT INTO public.produk_ukuran (id_produk, ukuran, stok)
VALUES
    ('a1111111-1111-4111-8111-111111111111', '40', 3),
    ('a1111111-1111-4111-8111-111111111111', '41', 2),
    ('a1111111-1111-4111-8111-111111111111', '42', 4),
    ('b2222222-2222-4222-8222-222222222222', '40', 1),
    ('b2222222-2222-4222-8222-222222222222', '42', 1),
    ('c3333333-3333-4333-8333-333333333333', '38', 2),
    ('c3333333-3333-4333-8333-333333333333', '39', 3),
    ('c3333333-3333-4333-8333-333333333333', '40', 5),
    ('c3333333-3333-4333-8333-333333333333', '44', 1)
ON CONFLICT (id_produk, ukuran) DO NOTHING;
