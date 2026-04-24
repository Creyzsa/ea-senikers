-- EA SENIKERS — tabel pesanan (jalankan di Supabase SQL Editor atau psql)
-- Pastikan tabel `users` sudah ada (foreign key ke users.id).

CREATE TABLE IF NOT EXISTS orders (
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

CREATE INDEX IF NOT EXISTS idx_orders_user_created ON orders (user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES orders (id) ON DELETE CASCADE,
    product_name TEXT NOT NULL,
    price BIGINT NOT NULL DEFAULT 0 CHECK (price >= 0),
    size TEXT,
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    product_image TEXT
);

CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items (order_id);

COMMENT ON TABLE orders IS 'Pesanan pembeli — status alur e-commerce';
COMMENT ON TABLE order_items IS 'Baris per produk dalam satu pesanan';

-- Troubleshooting: data ada di Table Editor tapi PHP tidak melihat apa-apa —
-- 1) Pastikan orders.user_id = users.id untuk email yang sama dengan akun login.
-- 2) Koneksi PHP memakai role database (bukan anon API). Jika RLS aktif dan memblokir,
--    tambahkan policy yang sesuai atau uji: ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
--    lalu policy untuk role yang dipakai koneksi (biasanya service role / postgres punya akses penuh).
