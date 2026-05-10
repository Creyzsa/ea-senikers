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
 * Admin beranda: pesanan terbaru dengan nama produk pertama (satu query).
 *
 * @return list<array<string, mixed>>
 */
function pesanan_admin_ambil_terbaru_ringkas(int $batas = 5): array
{
    if ($batas < 1) {
        $batas = 5;
    }
    if ($batas > 50) {
        $batas = 50;
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT o.id, o.total_price, o.status, o.created_at,
                    u.nama_pengguna,
                    (SELECT oi.product_name FROM order_items oi WHERE oi.order_id = o.id ORDER BY oi.id ASC LIMIT 1) AS produk_ringkas
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             ORDER BY o.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $batas, PDO::PARAM_INT);
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
/**
 * Langkah lanjutan status yang boleh dipilih admin (satu tingkat maju atau batal).
 *
 * @return list<string>
 */
function pesanan_admin_opsi_status_selanjutnya(string $status_sekarang): array
{
    $opsi = [];

    switch ($status_sekarang) {
        case 'pending':
            $opsi = ['paid', 'cancelled'];
            break;

        case 'paid':
            $opsi = ['processed', 'cancelled'];
            break;

        case 'processed':
            $opsi = ['shipped', 'cancelled'];
            break;

        case 'shipped':
            $opsi = ['completed'];
            break;
    }

    return $opsi;
}

function pesanan_admin_status_transisi_diizinkan(string $dari, string $ke): bool
{
    if ($dari === $ke) {
        return false;
    }
    $allowed = pesanan_admin_opsi_status_selanjutnya($dari);

    return in_array($ke, $allowed, true);
}

/**
 * Perbarui status pesanan oleh admin dengan aturan rantai maju / batal.
 */
function pesanan_admin_ubah_status(int $order_id, string $status_baru): bool
{
    $allowed_enum = ['pending', 'paid', 'processed', 'shipped', 'completed', 'cancelled'];
    if ($order_id <= 0 || !in_array($status_baru, $allowed_enum, true)) {
        return false;
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $order_id]);
        $current = $stmt->fetch();
        if (!$current) {
            return false;
        }
        $dari = (string) ($current['status'] ?? '');

        if (!pesanan_admin_status_transisi_diizinkan($dari, $status_baru)) {
            return false;
        }

        $stmt_u = $pdo->prepare('UPDATE orders SET status = :s WHERE id = :id');

        return $stmt_u->execute(['s' => $status_baru, 'id' => $order_id]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Hitung pesanan tiap status (dashboard / chip filter).
 *
 * @return array<string, int>
 */
function pesanan_admin_hitung_per_status(): array
{
    $basis = [];
    foreach (array_keys(pesanan_status_label_id()) as $k) {
        $basis[(string) $k] = 0;
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->query('SELECT status, COUNT(*)::int AS jumlah FROM orders GROUP BY status');
        if (!$stmt) {
            return $basis;
        }

        foreach ($stmt->fetchAll() as $r) {
            $s = (string) ($r['status'] ?? '');
            if ($s !== '') {
                $basis[$s] = (int) ($r['jumlah'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        return $basis;
    }

    return $basis;
}

/**
 * Admin daftar pesanan dengan filter status opsional dan pencarian.
 *
 * @return list<array<string, mixed>>
 */
function pesanan_admin_daftar_berfilter(?string $filter_status = null, string $q = ''): array
{
    $filter_status = $filter_status !== null ? trim(strtolower($filter_status)) : null;
    if ($filter_status === '') {
        $filter_status = null;
    }
    $q = trim($q);

    try {
        $pdo = koneksi_database();

        if ($q !== '') {
            $like = '%' . $q . '%';
            if ($filter_status !== null && array_key_exists($filter_status, pesanan_status_label_id())) {
                $stmt = $pdo->prepare(
                    'SELECT o.id, o.user_id, o.total_price, o.status, o.shipping_address, o.payment_method, o.created_at,
                            u.nama_pengguna, u.email
                     FROM orders o
                     LEFT JOIN users u ON o.user_id = u.id
                     WHERE o.status = :st
                       AND (o.id::text LIKE :q OR u.nama_pengguna ILIKE :qi OR u.email ILIKE :qi)
                     ORDER BY o.created_at DESC'
                );
                $stmt->execute(['st' => $filter_status, 'q' => $like, 'qi' => $like]);

                return $stmt->fetchAll();
            }

            $stmt = $pdo->prepare(
                'SELECT o.id, o.user_id, o.total_price, o.status, o.shipping_address, o.payment_method, o.created_at,
                        u.nama_pengguna, u.email
                 FROM orders o
                 LEFT JOIN users u ON o.user_id = u.id
                 WHERE o.id::text LIKE :q OR u.nama_pengguna ILIKE :qi OR u.email ILIKE :qi
                 ORDER BY o.created_at DESC'
            );

            $stmt->execute(['q' => $like, 'qi' => $like]);

            return $stmt->fetchAll();
        }

        if ($filter_status !== null && array_key_exists($filter_status, pesanan_status_label_id())) {
            $stmt = $pdo->prepare(
                'SELECT o.id, o.user_id, o.total_price, o.status, o.shipping_address, o.payment_method, o.created_at,
                        u.nama_pengguna, u.email
                 FROM orders o
                 LEFT JOIN users u ON o.user_id = u.id
                 WHERE o.status = :st
                 ORDER BY o.created_at DESC'
            );
            $stmt->execute(['st' => $filter_status]);

            return $stmt->fetchAll();
        }

        return pesanan_admin_ambil_semua();
    } catch (Throwable $e) {
        return [];
    }
}

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
