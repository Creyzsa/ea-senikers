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
        return aplikasi_url(KATALOG_FOLDER_GAMBAR . '/placeholder.svg');
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
