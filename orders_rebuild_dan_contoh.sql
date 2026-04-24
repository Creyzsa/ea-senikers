-- ═══════════════════════════════════════════════════════════════════════════
-- LANGKAH 2 — Buat tabel lagi + contoh data (setelah orders_drop.sql di-run).
--
-- 1) Ganti HANYA string di baris: v_email := '...'  (satu tempat)
-- 2) Jalankan orders_cek.sql dulu supaya tahu email yang benar di tabel users
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    total_price BIGINT NOT NULL DEFAULT 0 CHECK (total_price >= 0),
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN (
            'pending', 'paid', 'processed', 'shipped', 'completed', 'cancelled'
        )),
    shipping_address TEXT,
    payment_method TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_orders_user_created ON orders (user_id, created_at DESC);

CREATE TABLE order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES orders (id) ON DELETE CASCADE,
    product_name TEXT NOT NULL,
    price BIGINT NOT NULL DEFAULT 0 CHECK (price >= 0),
    size TEXT,
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    product_image TEXT
);

CREATE INDEX idx_order_items_order ON order_items (order_id);

COMMENT ON TABLE orders IS 'Pesanan pembeli — status alur e-commerce';
COMMENT ON TABLE order_items IS 'Baris per produk dalam satu pesanan';

-- Insert: kalau email salah, skrip ERROR (bukan "sukses" diam-diam kosong)
DO $$
DECLARE
    v_email text := 'WAJIB_GANTI_EMAIL@example.com'; -- <<< GANTI EMAIL DI SINI
    v_uid bigint;
    v_oid bigint;
BEGIN
    SELECT u.id INTO v_uid
    FROM public.users AS u
    WHERE LOWER(trim(u.email)) = LOWER(trim(v_email))
    LIMIT 1;

    IF v_uid IS NULL THEN
        RAISE EXCEPTION
            'Email "%" tidak ada di public.users. Buka orders_cek.sql → jalankan SELECT users → copy email persis ke v_email.',
            v_email;
    END IF;

    INSERT INTO public.orders (user_id, total_price, status, shipping_address, payment_method)
    VALUES (v_uid, 1500000, 'paid', 'Jl. Contoh No. 1, Jakarta', 'Transfer')
    RETURNING id INTO v_oid;

    INSERT INTO public.order_items (order_id, product_name, price, size, quantity, product_image)
    VALUES (v_oid, 'Sneakers Runner', 1500000, '42', 1, 'namafile.jpg');
END $$;

-- Harus muncul 1 baris di masing-masing hasil:
SELECT id, user_id, status, total_price FROM public.orders;
SELECT id, order_id, product_name FROM public.order_items;
