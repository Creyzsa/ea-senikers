-- ═══════════════════════════════════════════════════════════════════════════
-- Hanya TAMBAH 1 pesanan contoh (tabel orders & order_items sudah ada).
-- 1) Jalankan orders_cek.sql → lihat email di users
-- 2) Ganti v_email di bawah → Run
-- Kalau email salah: ERROR jelas (bukan tabel kosong tanpa pesan).
-- ═══════════════════════════════════════════════════════════════════════════

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
            'Email "%" tidak ada di public.users. Jalankan orders_cek.sql lalu copy email yang benar.',
            v_email;
    END IF;

    INSERT INTO public.orders (user_id, total_price, status, shipping_address, payment_method)
    VALUES (v_uid, 1500000, 'paid', 'Jl. Contoh No. 1, Jakarta', 'Transfer')
    RETURNING id INTO v_oid;

    INSERT INTO public.order_items (order_id, product_name, price, size, quantity, product_image)
    VALUES (v_oid, 'Sneakers Runner', 1500000, '42', 1, 'namafile.jpg');
END $$;

SELECT id, user_id, status FROM public.orders ORDER BY id DESC LIMIT 3;
SELECT id, order_id, product_name FROM public.order_items ORDER BY id DESC LIMIT 3;
