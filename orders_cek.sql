-- Satu query = satu baris hasil (Supabase sering hanya menampilkan SELECT terakhir kalau ada banyak perintah).

SELECT
    (SELECT COUNT(*)::int FROM public.users) AS jumlah_users,
    (SELECT COUNT(*)::int FROM public.orders) AS jumlah_orders,
    (SELECT COUNT(*)::int FROM public.order_items) AS jumlah_order_items,
    COALESCE(
        (
            SELECT json_agg(
                json_build_object('id', u.id, 'email', u.email, 'username', u.username)
                ORDER BY u.id
            )
            FROM public.users AS u
        ),
        '[]'::json
    ) AS daftar_users_json;

-- Lihat kolom daftar_users_json (array user): copy email ke v_email di orders_insert_saja.sql / orders_rebuild_dan_contoh.sql
