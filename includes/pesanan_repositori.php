<?php

declare(strict_types=1);

/**
 * Repositori pesanan — PostgreSQL (Supabase).
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/katalog_produk.php';

/**
 * @return array<string, string>
 */
function pesanan_status_label_id(): array
{
    return [
        'pending' => 'Menunggu pembayaran',
        'paid' => 'Dibayar',
        'processed' => 'Diproses',
        'shipped' => 'Dikirim',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];
}

/**
 * @return array<string, string>
 */
function pesanan_status_kelas_badge(): array
{
    return [
        'pending' => 'pesanan-badge pesanan-badge--kuning',
        'paid' => 'pesanan-badge pesanan-badge--biru',
        'processed' => 'pesanan-badge pesanan-badge--ungu',
        'shipped' => 'pesanan-badge pesanan-badge--oranye',
        'completed' => 'pesanan-badge pesanan-badge--hijau',
        'cancelled' => 'pesanan-badge pesanan-badge--batal',
    ];
}

/**
 * Langkah progress UI (bukan 1:1 dengan status enum — cancelled khusus).
 *
 * @return list<array{key: string, label: string}>
 */
function pesanan_langkah_progress(): array
{
    return [
        ['key' => 'created', 'label' => 'Pesanan dibuat'],
        ['key' => 'paid', 'label' => 'Pembayaran berhasil'],
        ['key' => 'processed', 'label' => 'Diproses'],
        ['key' => 'shipped', 'label' => 'Dikirim'],
        ['key' => 'completed', 'label' => 'Selesai'],
    ];
}

/**
 * Indeks langkah aktif (0..4) berdasarkan status, atau -1 jika cancelled.
 */
function pesanan_indeks_langkah_aktif(string $status): int
{
    if ($status === 'cancelled') {
        return -1;
    }
    $map = [
        'pending' => 0,
        'paid' => 1,
        'processed' => 2,
        'shipped' => 3,
        'completed' => 4,
    ];

    return $map[$status] ?? 0;
}

/**
 * @param array<string, mixed> $item
 */
function pesanan_url_gambar_item(array $item): string
{
    $raw = trim((string) ($item['product_image'] ?? ''));
    if ($raw === '') {
        return katalog_url_gambar_placeholder();
    }
    if (preg_match('#^https?://#i', $raw)) {
        return $raw;
    }

    return katalog_url_gambar_produk($raw);
}

/**
 * @return list<array<string, mixed>>
 */
function pesanan_ambil_oleh_user(int $user_id): array
{
    if ($user_id <= 0) {
        return [];
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT id, user_id, total_price, status, shipping_address, payment_method, created_at
             FROM orders
             WHERE user_id = :uid
             ORDER BY created_at DESC'
        );
        $stmt->execute(['uid' => $user_id]);
        $orders = $stmt->fetchAll();
        if ($orders === []) {
            return [];
        }
        $ids = array_map(static fn ($r) => (int) $r['id'], $orders);
        $itemsMap = pesanan_ambil_items_untuk_order_ids($pdo, $ids);
        foreach ($orders as &$o) {
            $oid = (int) $o['id'];
            $o['items'] = $itemsMap[$oid] ?? [];
        }
        unset($o);

        return $orders;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @param list<int> $order_ids
 * @return array<int, list<array<string, mixed>>>
 */
function pesanan_ambil_items_untuk_order_ids(PDO $pdo, array $order_ids): array
{
    if ($order_ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, order_id, product_name, price, size, quantity, product_image
         FROM order_items
         WHERE order_id IN ($placeholders)
         ORDER BY order_id, id"
    );
    $stmt->execute($order_ids);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $oid = (int) $row['order_id'];
        if (!isset($out[$oid])) {
            $out[$oid] = [];
        }
        $out[$oid][] = $row;
    }

    return $out;
}

/**
 * @return array<string, mixed>|null
 */
function pesanan_ambil_detail_untuk_user(int $order_id, int $user_id): ?array
{
    if ($user_id <= 0 || $order_id <= 0) {
        return null;
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT id, user_id, total_price, status, shipping_address, payment_method, created_at
             FROM orders
             WHERE id = :id AND user_id = :uid
             LIMIT 1'
        );
        $stmt->execute(['id' => $order_id, 'uid' => $user_id]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }
        $stmtI = $pdo->prepare(
            'SELECT id, order_id, product_name, price, size, quantity, product_image
             FROM order_items
             WHERE order_id = :oid
             ORDER BY id'
        );
        $stmtI->execute(['oid' => $order_id]);
        $order['items'] = $stmtI->fetchAll();

        return $order;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Update status (dipakai webhook payment).
 */
function pesanan_set_status_oleh_id(int $order_id, string $status_baru): bool
{
    $allowed = ['pending', 'paid', 'processed', 'shipped', 'completed', 'cancelled'];
    if (!in_array($status_baru, $allowed, true)) {
        return false;
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('UPDATE orders SET status = :s WHERE id = :id');

        return $stmt->execute(['s' => $status_baru, 'id' => $order_id]);
    } catch (Throwable $e) {
        return false;
    }
}

function pesanan_cek_tabel_ada(): bool
{
    try {
        $pdo = koneksi_database();
        $pdo->query('SELECT 1 FROM orders LIMIT 1');

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Produk untuk blok "Produk Terlaris": diurutkan berdasarkan jumlah terjual (order_items + orders non-batal, status paid+).
 * Bila belum ada penjualan yang cocok dengan katalog, sisa slot diisi produk katalog (created_at) seperti tampilan awal.
 *
 * @return list<array<string, mixed>> Baris produk format sama dengan katalog_ambil_semua_produk (tanpa jumlah di array).
 */
function pesanan_produk_terlaris_gabung_katalog(int $batas = 4): array
{
    if ($batas <= 0) {
        return [];
    }

    $semua = katalog_ambil_semua_produk();
    if ($semua === []) {
        return [];
    }

    $byNama = [];
    foreach ($semua as $p) {
        $n = (string) ($p['nama_produk'] ?? '');
        if ($n !== '') {
            $byNama[$n] = $p;
        }
    }

    $dipakai = [];
    $hasil = [];

    if (pesanan_cek_tabel_ada()) {
        try {
            $pdo = koneksi_database();
            $stmt = $pdo->query(
                'SELECT oi.product_name, SUM(oi.quantity)::int AS jumlah
                 FROM order_items oi
                 INNER JOIN orders o ON o.id = oi.order_id
                 WHERE o.status IN (\'paid\', \'processed\', \'shipped\', \'completed\')
                 GROUP BY oi.product_name
                 ORDER BY jumlah DESC, oi.product_name ASC
                 LIMIT 50'
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
        } catch (Throwable $e) {
            $rows = [];
        }
        foreach ($rows as $r) {
            if (count($hasil) >= $batas) {
                break;
            }
            $nama = (string) ($r['product_name'] ?? '');
            if (!isset($byNama[$nama])) {
                continue;
            }
            $p = $byNama[$nama];
            $id = (string) ($p['id_produk'] ?? '');
            if ($id === '' || isset($dipakai[$id])) {
                continue;
            }
            $dipakai[$id] = true;
            $hasil[] = $p;
        }
    }

    foreach ($semua as $p) {
        if (count($hasil) >= $batas) {
            break;
        }
        $id = (string) ($p['id_produk'] ?? '');
        if ($id === '' || isset($dipakai[$id])) {
            continue;
        }
        $dipakai[$id] = true;
        $hasil[] = $p;
    }

    return $hasil;
}

/**
 * Admin: Ambil semua pesanan dengan info user
 * @return list<array<string, mixed>>
 */
function pesanan_admin_ambil_semua(): array
{
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT o.id, o.user_id, o.total_price, o.status, o.shipping_address, o.payment_method, o.created_at,
                    u.nama_pengguna, u.email
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             ORDER BY o.created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Admin: Cari pesanan berdasarkan query (ID pesanan, nama user, email)
 * @param string $query
 * @return list<array<string, mixed>>
 */
function pesanan_admin_cari(string $query): array
{
    if (trim($query) === '') {
        return pesanan_admin_ambil_semua();
    }

    try {
        $pdo = koneksi_database();
        $searchTerm = '%' . trim($query) . '%';
        $stmt = $pdo->prepare(
            'SELECT o.id, o.user_id, o.total_price, o.status, o.shipping_address, o.payment_method, o.created_at,
                    u.nama_pengguna, u.email
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE o.id::text LIKE :q
                OR u.nama_pengguna ILIKE :q
                OR u.email ILIKE :q
             ORDER BY o.created_at DESC'
        );
        $stmt->execute(['q' => $searchTerm]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Admin: Ambil detail pesanan lengkap
 * @param int $order_id
 * @return array<string, mixed>|null
 */
function pesanan_admin_detail(int $order_id): ?array
{
    if ($order_id <= 0) {
        return null;
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT o.id, o.user_id, o.total_price, o.status, o.shipping_address, o.payment_method, o.created_at,
                    u.nama_pengguna, u.email
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE o.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $order_id]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        // Ambil items pesanan
        $stmtI = $pdo->prepare(
            'SELECT id, order_id, product_name, price, size, quantity, product_image
             FROM order_items
             WHERE order_id = :oid
             ORDER BY id'
        );
        $stmtI->execute(['oid' => $order_id]);
        $order['items'] = $stmtI->fetchAll();

        return $order;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Admin: Batalkan pesanan (hanya jika belum dikirim)
 * @param int $order_id
 * @return bool
 */
function pesanan_admin_batalkan(int $order_id): bool
{
    if ($order_id <= 0) {
        return false;
    }

    try {
        $pdo = koneksi_database();

        // Cek status pesanan - hanya bisa batalkan jika belum dikirim
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $order_id]);
        $current = $stmt->fetch();

        if (!$current || in_array($current['status'], ['shipped', 'completed', 'cancelled'])) {
            return false; // Tidak bisa batalkan
        }

        // Update status ke cancelled
        $stmt = $pdo->prepare('UPDATE orders SET status = :s WHERE id = :id');
        return $stmt->execute(['s' => 'cancelled', 'id' => $order_id]);

    } catch (Throwable $e) {
        return false;
    }
}
