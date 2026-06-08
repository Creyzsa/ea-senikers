-- ============================================================================
-- TAHAP 5: Supabase Storage untuk gambar produk (wajib di Vercel/serverless)
--
-- Vercel filesystem read-only — upload harus ke bucket cloud, bukan folder lokal.
-- Jalankan di Supabase SQL Editor. Aman dijalankan ulang.
--
-- Setelah ini, tambahkan env di Vercel:
--   SUPABASE_SERVICE_ROLE_KEY = (Settings → API → service_role secret)
--   SUPABASE_BUCKET_PRODUK    = produk-gambar  (opsional, default sama)
-- ============================================================================

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

-- Baca publik (katalog / detail produk)
DROP POLICY IF EXISTS "Publik baca gambar produk" ON storage.objects;
CREATE POLICY "Publik baca gambar produk"
    ON storage.objects FOR SELECT
    TO public
    USING (bucket_id = 'produk-gambar');

-- Upload/update/hapus dari backend PHP (service_role melewati RLS;
-- policy anon cadangan bila service_role belum di-set)
DROP POLICY IF EXISTS "Backend upload gambar produk" ON storage.objects;
CREATE POLICY "Backend upload gambar produk"
    ON storage.objects FOR INSERT
    TO anon, authenticated, service_role
    WITH CHECK (bucket_id = 'produk-gambar');

DROP POLICY IF EXISTS "Backend update gambar produk" ON storage.objects;
CREATE POLICY "Backend update gambar produk"
    ON storage.objects FOR UPDATE
    TO anon, authenticated, service_role
    USING (bucket_id = 'produk-gambar');

DROP POLICY IF EXISTS "Backend hapus gambar produk" ON storage.objects;
CREATE POLICY "Backend hapus gambar produk"
    ON storage.objects FOR DELETE
    TO anon, authenticated, service_role
    USING (bucket_id = 'produk-gambar');