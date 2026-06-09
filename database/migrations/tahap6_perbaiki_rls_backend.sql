-- ============================================================================
-- TAHAP 6: Perbaiki RLS untuk backend PHP (admin CRUD + storage gambar)
--
-- Error umum:
--   "new row violates row-level security policy"
--
-- Jalankan di Supabase SQL Editor. Aman dijalankan ulang.
-- ============================================================================

-- 1. Tabel produk — backend PHP sudah validasi sesi admin
ALTER TABLE IF EXISTS produk DISABLE ROW LEVEL SECURITY;
ALTER TABLE IF EXISTS produk_gambar DISABLE ROW LEVEL SECURITY;
ALTER TABLE IF EXISTS produk_ukuran DISABLE ROW LEVEL SECURITY;

-- 2. Tabel fitur marketplace (akses via PHP, bukan REST client)
ALTER TABLE IF EXISTS ulasan DISABLE ROW LEVEL SECURITY;
ALTER TABLE IF EXISTS wishlist DISABLE ROW LEVEL SECURITY;
ALTER TABLE IF EXISTS chat_messages DISABLE ROW LEVEL SECURITY;

-- 3. Bucket gambar produk (upload dari Vercel)
INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
VALUES (
    'produk-gambar',
    'produk-gambar',
    true,
    3145728,
    ARRAY['image/jpeg', 'image/png', 'image/webp']
)
ON CONFLICT (id) DO UPDATE SET
    public = EXCLUDED.public,
    file_size_limit = EXCLUDED.file_size_limit,
    allowed_mime_types = EXCLUDED.allowed_mime_types;

DROP POLICY IF EXISTS "Publik baca gambar produk" ON storage.objects;
CREATE POLICY "Publik baca gambar produk"
    ON storage.objects FOR SELECT
    TO public
    USING (bucket_id = 'produk-gambar');

DROP POLICY IF EXISTS "Backend upload gambar produk" ON storage.objects;
CREATE POLICY "Backend upload gambar produk"
    ON storage.objects FOR INSERT
    TO anon, authenticated, service_role
    WITH CHECK (bucket_id = 'produk-gambar');

DROP POLICY IF EXISTS "Backend update gambar produk" ON storage.objects;
CREATE POLICY "Backend update gambar produk"
    ON storage.objects FOR UPDATE
    TO anon, authenticated, service_role
    USING (bucket_id = 'produk-gambar')
    WITH CHECK (bucket_id = 'produk-gambar');

DROP POLICY IF EXISTS "Backend hapus gambar produk" ON storage.objects;
CREATE POLICY "Backend hapus gambar produk"
    ON storage.objects FOR DELETE
    TO anon, authenticated, service_role
    USING (bucket_id = 'produk-gambar');