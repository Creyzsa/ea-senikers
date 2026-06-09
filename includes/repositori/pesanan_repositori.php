<?php

declare(strict_types=1);

/**
 * Repositori pesanan — PostgreSQL (Supabase).
 */
require_once __DIR__ . '/../auth_db/database.php';
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

    $nama = produk_gambar_nama_aman($raw);
    if ($nama === '' || $nama === 'namafile.jpg') {
        return katalog_url_gambar_placeholder();
    }

    return katalog_url_gambar_produk($nama);
}

/**
 * Pastikan orders.destination_id bisa menyimpan kode JNE (VARCHAR), bukan INTEGER lama.
 */
function pesanan_pastikan_skema_destination_jne(PDO $pdo): void
{
    static $sudah = false;
    if ($sudah) {
        return;
    }
    $sudah = true;

    try {
        $stmt = $pdo->query(
            "SELECT data_type FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'orders' AND column_name = 'destination_id'"
        );
        $tipe = $stmt ? strtolower((string) $stmt->fetchColumn()) : '';
        if ($tipe === 'integer' || $tipe === 'bigint' || $tipe === 'smallint') {
            $pdo->exec(
                "ALTER TABLE orders
                 ALTER COLUMN destination_id TYPE VARCHAR(12)
                 USING NULLIF(TRIM(destination_id::text), '')"
            );
        }
    } catch (Throwable $e) {
        // Biarkan pesanan_buat menangkap error insert jika migrasi gagal (hak akses DB).
    }
}

/**
 * Buat pesanan baru beserta item-itemnya dalam satu transaksi.
 * Mengembalikan ID pesanan baru, atau null bila gagal.
 *
 * @param array{kurir:string, layanan:string, ongkir:int, destination_id:string} $shipping
 * @param list<array{product_name:string, price:int, size:string, quantity:int, product_image?:string}> $items
 */
function pesanan_buat(
    int $user_id,
    string $shipping_address,
    array $shipping,
    int $subtotal_produk,
    array $items
): ?int {
    if ($user_id <= 0 || trim($shipping_address) === '' || $items === []) {
        return null;
    }

    $kurir = trim((string) ($shipping['kurir'] ?? ''));
    $layanan = trim((string) ($shipping['layanan'] ?? ''));
    $ongkir = max(0, (int) ($shipping['ongkir'] ?? 0));
    $destination_id = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($shipping['destination_id'] ?? '')) ?? '');
    if (!preg_match('/^[A-Z]{3}\d{5}$/', $destination_id)) {
        $destination_id = '';
    }
    $total = max(0, $subtotal_produk) + $ongkir;

    try {
        $pdo = koneksi_database();
        pesanan_pastikan_skema_destination_jne($pdo);
        $pdo->beginTransaction();

        $stmt_order = $pdo->prepare(
            'INSERT INTO orders
                (user_id, total_price, status, shipping_address, payment_method,
                 kurir, layanan, ongkir, destination_id, created_at)
             VALUES
                (:uid, :total, :status, :alamat, :metode,
                 :kurir, :layanan, :ongkir, :dest, NOW())
             RETURNING id'
        );
        $stmt_order->execute([
            'uid' => $user_id,
            'total' => $total,
            'status' => 'pending',
            'alamat' => $shipping_address,
            'metode' => '',
            'kurir' => $kurir !== '' ? $kurir : null,
            'layanan' => $layanan !== '' ? $layanan : null,
            'ongkir' => $ongkir,
            'dest' => $destination_id !== '' ? $destination_id : null,
        ]);
        $row = $stmt_order->fetch();
        $order_id = $row ? (int) $row['id'] : 0;
        if ($order_id <= 0) {
            $pdo->rollBack();
            return null;
        }

        $stmt_item = $pdo->prepare(
            'INSERT INTO order_items
                (order_id, product_name, price, size, quantity, product_image, id_produk)
             VALUES
                (:oid, :nama, :harga, :ukuran, :qty, :gambar, :idp)'
        );
        foreach ($items as $it) {
            $stmt_item->execute([
                'oid' => $order_id,
                'nama' => trim((string) ($it['product_name'] ?? '')),
                'harga' => max(0, (int) ($it['price'] ?? 0)),
                'ukuran' => trim((string) ($it['size'] ?? '')),
                'qty' => max(1, (int) ($it['quantity'] ?? 1)),
                'gambar' => trim((string) ($it['product_image'] ?? '')),
                'idp' => !empty($it['id_produk']) ? (string)$it['id_produk'] : null,
            ]);
        }

        $pdo->commit();
        return $order_id;
    } catch (Throwable $e) {
        try {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $e2) {
            // abaikan
        }
        return null;
    }
}

/**
 * Simpan nomor resi pengiriman (dipakai admin saat status berubah ke shipped).
 */
function pesanan_simpan_nomor_resi(int $order_id, string $nomor_resi): bool
{
    $nomor_resi = trim($nomor_resi);
    if ($order_id <= 0) {
        return false;
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('UPDATE orders SET nomor_resi = :resi WHERE id = :id');
        return $stmt->execute([
            'resi' => $nomor_resi !== '' ? $nomor_resi : null,
            'id' => $order_id,
        ]);
    } catch (Throwable $e) {
        return false;
    }
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
            'SELECT id, user_id, total_price, status, shipping_address, payment_method,
                    kurir, layanan, ongkir, destination_id, nomor_resi, created_at
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
        "SELECT id, order_id, product_name, price, size, quantity, product_image, id_produk
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
            'SELECT id, user_id, total_price, status, shipping_address, payment_method,
                    kurir, layanan, ongkir, destination_id, nomor_resi, created_at
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
            'SELECT id, order_id, product_name, price, size, quantity, product_image, id_produk
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
function pesanan_perbarui_metode_bayar(int $order_id, string $metode): bool
{
    if ($order_id <= 0) {
        return false;
    }
    $metode = trim($metode);
    if ($metode === '') {
        return false;
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('UPDATE orders SET payment_method = :m WHERE id = :id');

        return $stmt->execute(['m' => $metode, 'id' => $order_id]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Status pesanan yang mengunci stok (sudah dibayar / sedang diproses).
 */
function pesanan_status_stok_terkunci(string $status): bool
{
    return in_array($status, ['paid', 'processed', 'shipped', 'completed'], true);
}

/**
 * Pastikan kolom orders.stok_dipotong ada (auto-migrasi ringan di runtime).
 */
function pesanan_pastikan_skema_stok_otomatis(PDO $pdo): void
{
    static $sudah = false;
    if ($sudah) {
        return;
    }
    $sudah = true;

    try {
        $stmt = $pdo->query(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'orders' AND column_name = 'stok_dipotong'
             LIMIT 1"
        );
        if ($stmt && $stmt->fetchColumn()) {
            return;
        }
        $pdo->exec('ALTER TABLE orders ADD COLUMN stok_dipotong BOOLEAN NOT NULL DEFAULT FALSE');
    } catch (Throwable $e) {
        error_log('[pesanan_pastikan_skema_stok_otomatis] ' . $e->getMessage());
    }
}

/**
 * @return list<array{id_produk: string, ukuran: string, qty: int}>
 */
function pesanan_stok_item_untuk_order(PDO $pdo, int $order_id): array
{
    $stmt = $pdo->prepare(
        'SELECT id_produk, product_name, size, quantity
         FROM order_items
         WHERE order_id = :oid
         ORDER BY id'
    );
    $stmt->execute(['oid' => $order_id]);
    $hasil = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $baris) {
        $id_produk = trim((string) ($baris['id_produk'] ?? ''));
        if ($id_produk === '') {
            $id_produk = pesanan_stok_cari_id_produk_dari_nama($pdo, (string) ($baris['product_name'] ?? ''));
        }
        if ($id_produk === '') {
            continue;
        }
        $ukuran = trim((string) ($baris['size'] ?? ''));
        if ($ukuran === '') {
            continue;
        }
        $hasil[] = [
            'id_produk' => $id_produk,
            'ukuran' => $ukuran,
            'qty' => max(1, (int) ($baris['quantity'] ?? 1)),
        ];
    }

    return $hasil;
}

function pesanan_stok_cari_id_produk_dari_nama(PDO $pdo, string $nama_produk): string
{
    $nama_produk = trim($nama_produk);
    if ($nama_produk === '') {
        return '';
    }
    $stmt = $pdo->prepare(
        'SELECT id_produk::text AS id_produk
         FROM produk
         WHERE LOWER(TRIM(nama_produk)) = LOWER(TRIM(:nama))
         LIMIT 1'
    );
    $stmt->execute(['nama' => $nama_produk]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? trim((string) ($row['id_produk'] ?? '')) : '';
}

/**
 * Kurangi atau kembalikan stok ukuran untuk semua item pesanan.
 *
 * @param 'kurangi'|'kembalikan' $mode
 */
function pesanan_stok_terapkan_untuk_order(PDO $pdo, int $order_id, string $mode): void
{
    if (!in_array($mode, ['kurangi', 'kembalikan'], true)) {
        return;
    }

    $items = pesanan_stok_item_untuk_order($pdo, $order_id);
    if ($items === []) {
        return;
    }

    $stmt_kurangi = $pdo->prepare(
        'UPDATE produk_ukuran
         SET stok = GREATEST(0, stok - :qty)
         WHERE id_produk = :id AND ukuran = :uk'
    );
    $stmt_kembalikan = $pdo->prepare(
        'UPDATE produk_ukuran
         SET stok = stok + :qty
         WHERE id_produk = :id AND ukuran = :uk'
    );
    $stmt_kembalikan_baru = $pdo->prepare(
        'INSERT INTO produk_ukuran (id_produk, ukuran, stok)
         VALUES (:id, :uk, :qty)'
    );

    foreach ($items as $it) {
        $params = [
            'id' => $it['id_produk'],
            'uk' => $it['ukuran'],
            'qty' => $it['qty'],
        ];
        if ($mode === 'kurangi') {
            $stmt_kurangi->execute($params);
            continue;
        }
        $stmt_kembalikan->execute($params);
        if ($stmt_kembalikan->rowCount() < 1) {
            $stmt_kembalikan_baru->execute($params);
        }
    }
}

/**
 * Sinkronkan flag stok_dipotong sesuai transisi status.
 */
function pesanan_stok_sinkron_status(
    PDO $pdo,
    int $order_id,
    string $status_lama,
    string $status_baru,
    bool $stok_dipotong
): bool {
    $masuk_terkunci = pesanan_status_stok_terkunci($status_baru);
    $batal = $status_baru === 'cancelled';

    if ($masuk_terkunci && !$stok_dipotong) {
        pesanan_stok_terapkan_untuk_order($pdo, $order_id, 'kurangi');

        return true;
    }
    if ($batal && $stok_dipotong) {
        pesanan_stok_terapkan_untuk_order($pdo, $order_id, 'kembalikan');

        return false;
    }

    return $stok_dipotong;
}

/**
 * Refresh counter terjual bila status masuk/keluar dari paid+ atau dibatalkan.
 */
function pesanan_refresh_terjual_untuk_order(PDO $pdo, int $order_id, string $status_lama, string $status_baru): void
{
    $perlu = pesanan_status_stok_terkunci($status_baru)
        || pesanan_status_stok_terkunci($status_lama)
        || $status_baru === 'cancelled';
    if (!$perlu) {
        return;
    }

    $sudah = [];
    foreach (pesanan_stok_item_untuk_order($pdo, $order_id) as $it) {
        $idp = trim((string) ($it['id_produk'] ?? ''));
        if ($idp === '' || isset($sudah[$idp])) {
            continue;
        }
        $sudah[$idp] = true;
        update_produk_terjual($idp);
    }
}

/**
 * @param null|callable(string,string):bool $validasi_transisi
 */
function pesanan_perbarui_status_dan_stok(int $order_id, string $status_baru, ?callable $validasi_transisi = null): bool
{
    $allowed = ['pending', 'paid', 'processed', 'shipped', 'completed', 'cancelled'];
    if ($order_id <= 0 || !in_array($status_baru, $allowed, true)) {
        return false;
    }

    try {
        $pdo = koneksi_database();
        pesanan_pastikan_skema_stok_otomatis($pdo);
        pesanan_pastikan_skema_destination_jne($pdo);
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT status, COALESCE(stok_dipotong, false) AS stok_dipotong
             FROM orders
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute(['id' => $order_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $pdo->rollBack();

            return false;
        }

        $status_lama = (string) ($row['status'] ?? '');
        $stok_dipotong = (bool) ($row['stok_dipotong'] ?? false);

        if ($status_lama === $status_baru) {
            $pdo->commit();

            return true;
        }

        if ($validasi_transisi !== null && !$validasi_transisi($status_lama, $status_baru)) {
            $pdo->rollBack();

            return false;
        }

        $stok_dipotong_baru = pesanan_stok_sinkron_status(
            $pdo,
            $order_id,
            $status_lama,
            $status_baru,
            $stok_dipotong
        );

        $stmt_u = $pdo->prepare(
            'UPDATE orders SET status = :s, stok_dipotong = :sd WHERE id = :id'
        );
        $ok = $stmt_u->execute([
            's' => $status_baru,
            'sd' => $stok_dipotong_baru,
            'id' => $order_id,
        ]);
        if (!$ok) {
            $pdo->rollBack();

            return false;
        }

        pesanan_refresh_terjual_untuk_order($pdo, $order_id, $status_lama, $status_baru);
        $pdo->commit();

        if ($status_baru === 'paid' && $status_lama !== 'paid') {
            try {
                require_once __DIR__ . '/admin_notifikasi_repositori.php';
                admin_notifikasi_pembayaran_masuk($order_id, pesanan_notifikasi_jalur_pembayaran());
            } catch (Throwable $e) {
                error_log('[admin_notifikasi_pembayaran_masuk] ' . $e->getMessage());
            }
        }

        return true;
    } catch (Throwable $e) {
        try {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $e2) {
            // abaikan
        }
        error_log('[pesanan_perbarui_status_dan_stok] ' . $e->getMessage());

        return false;
    }
}

/** @var 'pakasir'|'server' */
$GLOBALS['_pesanan_notifikasi_jalur'] = 'server';

/**
 * @param 'pakasir'|'server' $jalur
 */
function pesanan_notifikasi_jalur_pembayaran_set(string $jalur): void
{
    $GLOBALS['_pesanan_notifikasi_jalur'] = $jalur === 'pakasir' ? 'pakasir' : 'server';
}

/**
 * @return 'pakasir'|'server'
 */
function pesanan_notifikasi_jalur_pembayaran(): string
{
    $jalur = $GLOBALS['_pesanan_notifikasi_jalur'] ?? 'server';

    return $jalur === 'pakasir' ? 'pakasir' : 'server';
}

function pesanan_notifikasi_jalur_pembayaran_reset(): void
{
    $GLOBALS['_pesanan_notifikasi_jalur'] = 'server';
}

function pesanan_set_status_oleh_id(int $order_id, string $status_baru, string $notif_jalur = 'server'): bool
{
    pesanan_notifikasi_jalur_pembayaran_set($notif_jalur);
    try {
        return pesanan_perbarui_status_dan_stok($order_id, $status_baru);
    } finally {
        pesanan_notifikasi_jalur_pembayaran_reset();
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
                    u.username AS nama_pengguna, u.email
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
                    u.username AS nama_pengguna,
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
                    u.username AS nama_pengguna, u.email
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE o.id::text LIKE :q
                OR u.username ILIKE :q
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
                    o.kurir, o.layanan, o.ongkir, o.destination_id, o.nomor_resi,
                    u.username AS nama_pengguna, u.email, u.no_hp, u.nama_penerima
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
            'SELECT id, order_id, product_name, price, size, quantity, product_image, id_produk
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
    return pesanan_perbarui_status_dan_stok(
        $order_id,
        $status_baru,
        static fn (string $dari, string $ke): bool => pesanan_admin_status_transisi_diizinkan($dari, $ke)
    );
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
                            u.username AS nama_pengguna, u.email
                     FROM orders o
                     LEFT JOIN users u ON o.user_id = u.id
                     WHERE o.status = :st
                       AND (o.id::text LIKE :q OR u.username ILIKE :qi OR u.email ILIKE :qi)
                     ORDER BY o.created_at DESC'
                );
                $stmt->execute(['st' => $filter_status, 'q' => $like, 'qi' => $like]);

                return $stmt->fetchAll();
            }

            $stmt = $pdo->prepare(
                'SELECT o.id, o.user_id, o.total_price, o.status, o.shipping_address, o.payment_method, o.created_at,
                        u.username AS nama_pengguna, u.email
                 FROM orders o
                 LEFT JOIN users u ON o.user_id = u.id
                 WHERE o.id::text LIKE :q OR u.username ILIKE :qi OR u.email ILIKE :qi
                 ORDER BY o.created_at DESC'
            );

            $stmt->execute(['q' => $like, 'qi' => $like]);

            return $stmt->fetchAll();
        }

        if ($filter_status !== null && array_key_exists($filter_status, pesanan_status_label_id())) {
            $stmt = $pdo->prepare(
                'SELECT o.id, o.user_id, o.total_price, o.status, o.shipping_address, o.payment_method, o.created_at,
                        u.username AS nama_pengguna, u.email
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
