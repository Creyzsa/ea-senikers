<?php

declare(strict_types=1);

/**
 * Katalog produk — data dari Supabase PostgREST.
 */
require_once __DIR__ . '/../auth_db/supabase_rest.php';
require_once __DIR__ . '/../url_bantu.php';
require_once __DIR__ . '/../auth_db/database.php';

/** Path relatif folder gambar produk di bawah URL_APLIKASI (public). */
const KATALOG_FOLDER_GAMBAR = 'assets/images/produk';

function katalog_url_gambar_produk(string $nama_file): string
{
    $nama_file = str_replace(['/', '\\'], '', $nama_file);
    if ($nama_file === '') {
        return katalog_url_gambar_placeholder();
    }
    $lokal = easenikers_folder_public() . '/' . KATALOG_FOLDER_GAMBAR . '/' . $nama_file;
    if (!is_file($lokal)) {
        return katalog_url_gambar_placeholder();
    }

    return aplikasi_url_aset(KATALOG_FOLDER_GAMBAR . '/' . rawurlencode($nama_file));
}

/** Gambar pengganti bila belum ada upload (tanpa file statis). */
function katalog_url_gambar_placeholder(): string
{
    $svg = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400" role="img" aria-label="Tidak ada gambar">'
        . '<rect width="400" height="400" fill="#f3f4f6"/>'
        . '<rect x="120" y="150" width="160" height="100" rx="8" fill="none" stroke="#d1d5db" stroke-width="2"/>'
        . '<circle cx="200" cy="200" r="20" fill="#e5e7eb"/>'
        . '<path d="M160 210 L180 190 L200 200 L220 180 L240 200 L240 230 L160 230 Z" fill="#d1d5db"/>'
        . '</svg>';

    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

function katalog_format_rupiah(int $harga): string
{
    return 'Rp ' . number_format($harga, 0, ',', '.');
}

/**
 * Pencocokan teks untuk pencarian: TIDAK peduli huruf besar/kecil dan aman
 * untuk karakter multibyte (UTF-8). Mengembalikan true bila kata kunci
 * $needle terdapat di dalam $haystack.
 */
function katalog_teks_cocok(string $haystack, string $needle): bool
{
    $needle = trim($needle);
    if ($needle === '') {
        return true;
    }
    if (function_exists('mb_stripos')) {
        return mb_stripos($haystack, $needle) !== false;
    }

    return stripos($haystack, $needle) !== false;
}

/**
 * Label kondisi untuk ditampilkan ke pembeli.
 * Data internal memakai 'Baru' / 'Second' (untuk admin form),
 * tetapi pembeli melihatnya sebagai 'Baru' / 'Preloved' agar selaras
 * dengan terminologi marketing di seluruh situs.
 */
function kondisi_label_pembeli(string $kondisi): string
{
    $kondisi_bersih = trim($kondisi);
    if (strcasecmp($kondisi_bersih, 'Second') === 0) {
        return 'Preloved';
    }
    return $kondisi_bersih;
}

/**
 * @return list<array<string, mixed>>
 */
function katalog_ambil_semua_produk(): array
{
    $hasil = supabase_rest_request('GET', '/rest/v1/produk', [
        'select' => '*,produk_gambar(*),produk_ukuran(*)',
        'order' => 'created_at.desc',
    ]);
    if (!$hasil['ok'] || !is_array($hasil['data'])) {
        return [];
    }
    $rows = $hasil['data'];
    if (isset($rows['code']) || isset($rows['message'])) {
        return [];
    }
    if (isset($rows['id_produk'])) {
        katalog_isi_ringkasan_stok($rows);
        return [$rows];
    }
    foreach ($rows as &$__row) {
        if (is_array($__row)) {
            katalog_isi_ringkasan_stok($__row);
        }
    }
    unset($__row);
    return $rows;
}

/** Ukuran standar EU yang ditampilkan di katalog & detail produk. */
function katalog_daftar_ukuran_default(): array
{
    return ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
}

/**
 * Lengkapi produk_ukuran dengan semua ukuran default; yang tidak ada di DB = stok 0.
 *
 * @param array<string, mixed> $produk
 */
function katalog_lengkapi_ukuran_produk(array &$produk): void
{
    $map = [];
    $list = $produk['produk_ukuran'] ?? [];
    if (is_array($list)) {
        foreach ($list as $u) {
            if (!is_array($u)) {
                continue;
            }
            $uk = trim((string) ($u['ukuran'] ?? ''));
            if ($uk !== '') {
                $map[$uk] = max(0, (int) ($u['stok'] ?? 0));
            }
        }
    }
    $lengkap = [];
    foreach (katalog_daftar_ukuran_default() as $uk) {
        $lengkap[] = ['ukuran' => $uk, 'stok' => $map[$uk] ?? 0];
    }
    $produk['produk_ukuran'] = katalog_urutkan_ukuran($lengkap);
}

/**
 * Isi field turunan `total_stok` dan `siap_jual` pada array produk
 * berdasarkan daftar `produk_ukuran`. Field ini bukan kolom database,
 * dihitung di PHP setiap kali baca produk.
 *
 * @param array<string, mixed> $produk
 */
function katalog_isi_ringkasan_stok(array &$produk): void
{
    katalog_lengkapi_ukuran_produk($produk);
    $ukuran_list = $produk['produk_ukuran'] ?? [];
    $total = 0;
    if (is_array($ukuran_list)) {
        foreach ($ukuran_list as $u) {
            if (is_array($u)) {
                $total += max(0, (int) ($u['stok'] ?? 0));
            }
        }
    }
    $produk['total_stok'] = $total;
    $produk['siap_jual'] = $total > 0;
}

/**
 * @return array<string, mixed>|null
 */
function katalog_ambil_produk_ber_id(string $id_produk): ?array
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)) {
        return null;
    }
    $hasil = supabase_rest_request('GET', '/rest/v1/produk', [
        'select' => '*,produk_gambar(*),produk_ukuran(*)',
        'id_produk' => 'eq.' . $id_produk,
        'limit' => 1,
    ]);
    if (!$hasil['ok'] || !is_array($hasil['data'])) {
        return null;
    }
    $rows = $hasil['data'];
    if ($rows === []) {
        return null;
    }
    if (isset($rows['code'])) {
        return null;
    }
    if (isset($rows['id_produk'])) {
        katalog_isi_ringkasan_stok($rows);
        return $rows;
    }
    $satu = is_array($rows[0] ?? null) ? $rows[0] : null;
    if ($satu !== null) {
        katalog_isi_ringkasan_stok($satu);
    }
    return $satu;
}

/** URL gambar utama (pertama menurut urutan) atau placeholder. */
function katalog_url_gambar_utama(array $produk): string
{
    $g = $produk['produk_gambar'] ?? [];
    if (!is_array($g) || $g === []) {
        return katalog_url_gambar_placeholder();
    }
    $g = katalog_urutkan_gambar($g);
    $nama = (string) ($g[0]['nama_file'] ?? '');
    if ($nama === '') {
        return katalog_url_gambar_placeholder();
    }
    return katalog_url_gambar_produk($nama);
}

/**
 * Urutkan gambar berdasarkan kolom urutan.
 *
 * @param array<int, array<string, mixed>> $gambar
 * @return array<int, array<string, mixed>>
 */
function katalog_urutkan_gambar(array $gambar): array
{
    usort($gambar, static function (array $a, array $b): int {
        return ((int) ($a['urutan'] ?? 0)) <=> ((int) ($b['urutan'] ?? 0));
    });
    return $gambar;
}

/**
 * @param array<int, array<string, mixed>> $ukuran
 * @return array<int, array<string, mixed>>
 */
function katalog_urutkan_ukuran(array $ukuran): array
{
    usort($ukuran, static function (array $a, array $b): int {
        $sa = trim((string) ($a['ukuran'] ?? ''));
        $sb = trim((string) ($b['ukuran'] ?? ''));
        if ($sa !== '' && $sb !== '' && ctype_digit($sa) && ctype_digit($sb)) {
            return (int) $sa <=> (int) $sb;
        }

        return strnatcasecmp($sa, $sb);
    });
    return $ukuran;
}

// ============================================================================
// FITUR BARU: ulasan, rating, wishlist, rekomendasi, sold (menggunakan kolom di produk)
// ============================================================================

/**
 * Internal helper: jalankan callback DB dengan fallback aman jika koneksi gagal.
 * Mencegah fatal "could not translate host" (pooler DNS) di local Laragon/Windows.
 * Untuk dev: page tetap render (wishlist=false, boleh review=false, list=[]).
 */
function _db_call(callable $fn, $default = null)
{
    try {
        return $fn();
    } catch (PDOException $e) {
        error_log('[DB graceful fallback] ' . $e->getMessage());
        return $default;
    } catch (Throwable $e) {
        error_log('[DB unexpected] ' . $e->getMessage());
        return $default;
    }
}

/** Cek apakah user pernah beli produk (status paid+). */
function user_pernah_beli_produk(int $user_id, string $id_produk): bool
{
    if ($user_id <= 0 || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)) {
        return false;
    }
    return _db_call(function () use ($user_id, $id_produk) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = :uid AND oi.id_produk = :pid
               AND o.status IN (\'paid\', \'processed\', \'shipped\', \'completed\')
             LIMIT 1'
        );
        $stmt->execute(['uid' => $user_id, 'pid' => $id_produk]);
        return (bool) $stmt->fetch();
    }, false);
}

/** Refresh denormalized stats di produk (panggil setelah tambah ulasan atau update order). */
function ulasan_refresh_produk_stats(string $id_produk): void
{
    _db_call(function () use ($id_produk) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'UPDATE produk SET
                jumlah_ulasan = COALESCE((SELECT COUNT(*)::int FROM ulasan WHERE id_produk = :p), 0),
                rating_rata   = COALESCE((SELECT AVG(rating)::numeric(3,2) FROM ulasan WHERE id_produk = :p), 0)
             WHERE id_produk = :p'
        );
        $stmt->execute(['p' => $id_produk]);
    }, null);
}

/** Tambah / update ulasan (1 per user per produk). */
function ulasan_tambah(int $user_id, string $id_produk, int $rating, string $komentar): bool
{
    if ($user_id <= 0
        || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)
        || $rating < 1 || $rating > 5
        || trim($komentar) === ''
    ) {
        return false;
    }

    return _db_call(function () use ($user_id, $id_produk, $rating, $komentar) {
        $pdo = koneksi_database();

        // cari order terbaru untuk verified (opsional)
        $stmtO = $pdo->prepare(
            'SELECT o.id FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = :u AND oi.id_produk = :p
               AND o.status IN (\'paid\', \'processed\', \'shipped\', \'completed\')
             ORDER BY o.created_at DESC LIMIT 1'
        );
        $stmtO->execute(['u' => $user_id, 'p' => $id_produk]);
        $order_id = $stmtO->fetchColumn() ?: null;

        $stmt = $pdo->prepare(
            'INSERT INTO ulasan (user_id, id_produk, order_id, rating, komentar)
             VALUES (:u, :p, :o, :r, :k)
             ON CONFLICT (user_id, id_produk) DO UPDATE
             SET rating = EXCLUDED.rating, komentar = EXCLUDED.komentar, created_at = NOW(), order_id = EXCLUDED.order_id'
        );
        $ok = $stmt->execute([
            'u' => $user_id,
            'p' => $id_produk,
            'o' => $order_id,
            'r' => $rating,
            'k' => trim($komentar),
        ]);

        if ($ok) {
            ulasan_refresh_produk_stats($id_produk);
        }
        return $ok;
    }, false);
}

/** Ambil ulasan untuk 1 produk (dengan nama user jika ada). */
function ulasan_ambil_untuk_produk(string $id_produk, int $limit = 20): array
{
    $hasil = supabase_rest_request('GET', '/rest/v1/ulasan', [
        'select' => 'id,rating,komentar,created_at,user_id',
        'id_produk' => 'eq.' . $id_produk,
        'order' => 'created_at.desc',
        'limit' => (string)$limit,
    ]);
    if (!$hasil['ok'] || !is_array($hasil['data'])) {
        return [];
    }
    return $hasil['data'];
}

/** Wishlist */
function wishlist_tambah(int $user_id, string $id_produk): bool
{
    if ($user_id <= 0 || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)) {
        return false;
    }
    return _db_call(function () use ($user_id, $id_produk) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('INSERT INTO wishlist (user_id, id_produk) VALUES (:u, :p) ON CONFLICT DO NOTHING');
        return $stmt->execute(['u' => $user_id, 'p' => $id_produk]);
    }, false);
}

function wishlist_hapus(int $user_id, string $id_produk): bool
{
    return _db_call(function () use ($user_id, $id_produk) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('DELETE FROM wishlist WHERE user_id = :u AND id_produk = :p');
        return $stmt->execute(['u' => $user_id, 'p' => $id_produk]);
    }, false);
}

function wishlist_ada(int $user_id, string $id_produk): bool
{
    return _db_call(function () use ($user_id, $id_produk) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('SELECT 1 FROM wishlist WHERE user_id = :u AND id_produk = :p LIMIT 1');
        $stmt->execute(['u' => $user_id, 'p' => $id_produk]);
        return (bool)$stmt->fetch();
    }, false);
}

/** Ambil wishlist user + data produk lengkap. */
function wishlist_ambil_user(int $user_id): array
{
    return _db_call(function () use ($user_id) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('SELECT id_produk FROM wishlist WHERE user_id = :u ORDER BY created_at DESC');
        $stmt->execute(['u' => $user_id]);
        $ids = array_map(static fn($r) => (string)$r['id_produk'], $stmt->fetchAll());
        if ($ids === []) {
            return [];
        }
        // gunakan REST supaya dapat relasi gambar/ukuran + filter
        // PostgREST IN syntax: column=in.(val1,val2) — tanpa quote di dalam untuk uuid
        $in = 'in.(' . implode(',', $ids) . ')';
        $hasil = supabase_rest_request('GET', '/rest/v1/produk', [
            'select' => '*,produk_gambar(*),produk_ukuran(*)',
            'id_produk' => $in,
            'order' => 'created_at.desc',
        ]);
        if (!$hasil['ok'] || !is_array($hasil['data'])) {
            return [];
        }
        $rows = $hasil['data'];
        foreach ($rows as &$r) {
            if (is_array($r)) {
                katalog_isi_ringkasan_stok($r);
            }
        }
        return $rows;
    }, []);
}

/** Rekomendasi produk (prefer brand sama, fallback populer). */
function katalog_rekomendasi_untuk_produk(string $id_produk, int $limit = 4): array
{
    $p = katalog_ambil_produk_ber_id($id_produk);
    if (!$p) {
        return [];
    }
    $brand = trim((string)($p['brand'] ?? ''));
    $exclude = 'neq.' . $id_produk;

    // coba brand sama dulu
    $q = [
        'select' => '*,produk_gambar(*),produk_ukuran(*)',
        'id_produk' => $exclude,
        'order' => 'terjual.desc,created_at.desc',
        'limit' => (string)($limit + 3),
    ];
    if ($brand !== '') {
        $q['brand'] = 'eq.' . $brand;
    }
    $hasil = supabase_rest_request('GET', '/rest/v1/produk', $q);
    $rows = ($hasil['ok'] && is_array($hasil['data'])) ? $hasil['data'] : [];

    if (count($rows) < $limit && $brand !== '') {
        // fallback tanpa brand
        $q2 = [
            'select' => '*,produk_gambar(*),produk_ukuran(*)',
            'id_produk' => $exclude,
            'order' => 'terjual.desc,created_at.desc',
            'limit' => (string)$limit,
        ];
        $h2 = supabase_rest_request('GET', '/rest/v1/produk', $q2);
        if ($h2['ok'] && is_array($h2['data'])) {
            $rows = $h2['data'];
        }
    }

    foreach ($rows as &$r) {
        if (is_array($r)) {
            katalog_isi_ringkasan_stok($r);
        }
    }
    // acak sedikit biar variatif
    shuffle($rows);
    return array_slice($rows, 0, $limit);
}

/** Update terjual (panggil saat order jadi paid+). */
function update_produk_terjual(string $id_produk): void
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)) {
        return;
    }
    _db_call(function () use ($id_produk) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'UPDATE produk SET terjual = COALESCE((
                SELECT SUM(oi.quantity)::int
                FROM order_items oi
                INNER JOIN orders o ON o.id = oi.order_id
                WHERE oi.id_produk = :p
                  AND o.status IN (\'paid\', \'processed\', \'shipped\', \'completed\')
            ), 0)
             WHERE id_produk = :p'
        );
        $stmt->execute(['p' => $id_produk]);
    }, null);
}

// ============================================================================
// Chat pembeli <-> penjual (admin)
// ============================================================================

/** Kirim pesan chat. */
function chat_kirim(int $from_id, int $to_id, string $message, ?string $id_produk = null, ?int $order_id = null): bool
{
    if ($from_id <= 0 || $to_id <= 0 || trim($message) === '') {
        return false;
    }
    return _db_call(function () use ($from_id, $to_id, $message, $id_produk, $order_id) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'INSERT INTO chat_messages (from_user_id, to_user_id, id_produk, order_id, message)
             VALUES (:f, :t, :p, :o, :m)'
        );
        return $stmt->execute([
            'f' => $from_id,
            't' => $to_id,
            'p' => $id_produk,
            'o' => $order_id,
            'm' => trim($message),
        ]);
    }, false);
}

/** Ambil riwayat chat untuk user (bisa filter per produk). */
function chat_ambil_untuk_user(int $user_id, ?string $id_produk = null, int $limit = 100): array
{
    return _db_call(function () use ($user_id, $id_produk, $limit) {
        $pdo = koneksi_database();
        $sql = 'SELECT cm.*, 
                       u1.nama_pengguna AS from_nama, 
                       u2.nama_pengguna AS to_nama
                FROM chat_messages cm
                LEFT JOIN users u1 ON u1.id = cm.from_user_id
                LEFT JOIN users u2 ON u2.id = cm.to_user_id
                WHERE cm.from_user_id = :u OR cm.to_user_id = :u';
        $params = ['u' => $user_id, 'lim' => $limit];
        if ($id_produk && preg_match('/^[0-9a-f-]{36}$/i', $id_produk)) {
            $sql .= ' AND cm.id_produk = :p';
            $params['p'] = $id_produk;
        }
        $sql .= ' ORDER BY cm.created_at ASC LIMIT :lim';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }, []);
}

/** Tandai semua pesan ke user sebagai dibaca (untuk produk tertentu jika disediakan). */
function chat_tandai_dibaca(int $user_id, ?string $id_produk = null): int
{
    return _db_call(function () use ($user_id, $id_produk) {
        $pdo = koneksi_database();
        $sql = 'UPDATE chat_messages SET is_read = true 
                WHERE to_user_id = :u AND is_read = false';
        $params = ['u' => $user_id];
        if ($id_produk && preg_match('/^[0-9a-f-]{36}$/i', $id_produk)) {
            $sql .= ' AND id_produk = :p';
            $params['p'] = $id_produk;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }, 0);
}

/** Hitung unread untuk user (total atau per produk). */
function chat_hitung_unread(int $user_id, ?string $id_produk = null): int
{
    return _db_call(function () use ($user_id, $id_produk) {
        $pdo = koneksi_database();
        $sql = 'SELECT COUNT(*)::int FROM chat_messages 
                WHERE to_user_id = :u AND is_read = false';
        $params = ['u' => $user_id];
        if ($id_produk && preg_match('/^[0-9a-f-]{36}$/i', $id_produk)) {
            $sql .= ' AND id_produk = :p';
            $params['p'] = $id_produk;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }, 0);
}

/** Ambil ID penjual (admin pertama) untuk chat. */
function ambil_penjual_id(): ?int
{
    return _db_call(function () {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE peran = 'admin' ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }, null);
}
