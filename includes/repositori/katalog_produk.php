<?php

declare(strict_types=1);

/**
 * Katalog produk — data dari Supabase PostgREST.
 */
require_once __DIR__ . '/../auth_db/supabase_rest.php';
require_once __DIR__ . '/../url_bantu.php';
require_once __DIR__ . '/../auth_db/database.php';
require_once __DIR__ . '/../integrasi/produk_gambar_storage.php';

function katalog_url_gambar_produk(string $nama_file): string
{
    $nama_file = produk_gambar_nama_aman($nama_file);
    if ($nama_file === '') {
        return katalog_url_gambar_placeholder();
    }

    $url = produk_gambar_url_untuk_tampil($nama_file);

    return $url !== '' ? $url : katalog_url_gambar_placeholder();
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
 * Pecah kata kunci pencarian menjadi token (spasi, aman UTF-8).
 *
 * @return list<string>
 */
function katalog_token_pencarian(string $q): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $norm = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $potong = preg_split('/\s+/u', $norm, -1, PREG_SPLIT_NO_EMPTY);

    return is_array($potong) ? array_values($potong) : [];
}

/**
 * Teks gabungan produk untuk pencarian (nama, merek, kategori, kondisi, deskripsi).
 */
function katalog_indeks_teks_produk(array $produk): string
{
    $kondisi = (string) ($produk['kondisi'] ?? '');

    return implode(' ', array_filter([
        (string) ($produk['nama_produk'] ?? ''),
        (string) ($produk['brand'] ?? ''),
        (string) ($produk['kategori'] ?? ''),
        $kondisi,
        kondisi_label_pembeli($kondisi),
        (string) ($produk['deskripsi'] ?? ''),
    ], static fn (string $s): bool => trim($s) !== ''));
}

/**
 * Satu token cocok di teks indeks atau sebagai awalan kata di nama/merek.
 */
function katalog_token_cocok_produk(string $token, string $indeks, string $nama, string $brand): bool
{
    if ($token === '') {
        return true;
    }
    if (katalog_teks_cocok($indeks, $token)) {
        return true;
    }
    foreach ([$nama, $brand] as $bidang) {
        $kata = preg_split('/\s+/u', trim($bidang), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($kata)) {
            continue;
        }
        foreach ($kata as $w) {
            if (function_exists('mb_stripos')) {
                if (mb_stripos($w, $token, 0, 'UTF-8') === 0) {
                    return true;
                }
            } elseif (stripos($w, $token) === 0) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Produk cocok dengan pencarian: setiap kata kunci harus cocok (AND).
 */
function katalog_produk_cocok_pencarian(array $produk, string $q): bool
{
    $tokens = katalog_token_pencarian($q);
    if ($tokens === []) {
        return true;
    }

    $indeks = katalog_indeks_teks_produk($produk);
    $nama = (string) ($produk['nama_produk'] ?? '');
    $brand = (string) ($produk['brand'] ?? '');

    foreach ($tokens as $token) {
        if (!katalog_token_cocok_produk($token, $indeks, $nama, $brand)) {
            return false;
        }
    }

    return true;
}

/**
 * Skor relevansi (lebih tinggi = lebih atas di saran).
 */
function katalog_skor_pencarian_produk(array $produk, string $q, array $tokens): int
{
    $nama = (string) ($produk['nama_produk'] ?? '');
    $brand = (string) ($produk['brand'] ?? '');
    $skor = 0;
    $q_trim = trim($q);

    if ($q_trim !== '' && katalog_teks_cocok($nama, $q_trim)) {
        $skor += 120;
    }
    if ($q_trim !== '' && katalog_teks_cocok($brand, $q_trim)) {
        $skor += 100;
    }
    if ($q_trim !== '' && function_exists('mb_stripos') && mb_stripos($nama, $q_trim, 0, 'UTF-8') === 0) {
        $skor += 80;
    } elseif ($q_trim !== '' && stripos($nama, $q_trim) === 0) {
        $skor += 80;
    }

    foreach ($tokens as $token) {
        if (katalog_teks_cocok($brand, $token)) {
            $skor += 45;
        }
        if (katalog_teks_cocok($nama, $token)) {
            $skor += 35;
        }
        if (function_exists('mb_stripos') && mb_stripos($nama, $token, 0, 'UTF-8') === 0) {
            $skor += 25;
        } elseif (stripos($nama, $token) === 0) {
            $skor += 25;
        }
    }

    return $skor;
}

/**
 * @return list<array<string, mixed>>
 */
function katalog_ambil_produk_untuk_cari(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $hasil = supabase_rest_request('GET', '/rest/v1/produk', [
        'select' => 'id_produk,nama_produk,brand,kategori,kondisi,harga,deskripsi,produk_gambar(nama_file,urutan)',
        'order' => 'created_at.desc',
    ]);
    if (!$hasil['ok'] || !is_array($hasil['data'])) {
        $cache = [];

        return $cache;
    }
    $rows = $hasil['data'];
    if (isset($rows['code']) || isset($rows['message'])) {
        $cache = [];

        return $cache;
    }
    if (isset($rows['id_produk'])) {
        $cache = [$rows];

        return $cache;
    }
    $cache = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $cache[] = $row;
        }
    }

    return $cache;
}

/**
 * Saran pencarian untuk autocomplete navbar.
 *
 * @return array{produk: list<array<string, mixed>>, kata_kunci: list<array<string, string>>}
 */
function katalog_saran_pencarian(string $q, int $batas_produk = 8, int $batas_kata = 4): array
{
    $q = trim($q);
    $tokens = katalog_token_pencarian($q);
    $min_len = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
    if ($min_len < 2) {
        return ['produk' => [], 'kata_kunci' => []];
    }

    $kandidat = [];
    foreach (katalog_ambil_produk_untuk_cari() as $produk) {
        if (!katalog_produk_cocok_pencarian($produk, $q)) {
            continue;
        }
        $kandidat[] = [
            'produk' => $produk,
            'skor' => katalog_skor_pencarian_produk($produk, $q, $tokens),
        ];
    }

    usort($kandidat, static fn (array $a, array $b): int => $b['skor'] <=> $a['skor']);

    $produk_out = [];
    foreach (array_slice($kandidat, 0, max(1, $batas_produk)) as $baris) {
        $p = $baris['produk'];
        $id = (string) ($p['id_produk'] ?? '');
        if ($id === '') {
            continue;
        }
        $produk_out[] = [
            'id' => $id,
            'nama' => (string) ($p['nama_produk'] ?? ''),
            'brand' => (string) ($p['brand'] ?? ''),
            'harga' => katalog_format_rupiah((int) ($p['harga'] ?? 0)),
            'gambar' => katalog_url_gambar_utama($p),
            'url' => aplikasi_url('detail-produk?id=' . rawurlencode($id)),
        ];
    }

    $kata_out = [];
    $merek_seen = [];
    foreach ($kandidat as $baris) {
        $brand = trim((string) ($baris['produk']['brand'] ?? ''));
        if ($brand === '' || isset($merek_seen[$brand])) {
            continue;
        }
        $cocok_merek = katalog_teks_cocok($brand, $q);
        foreach ($tokens as $token) {
            if (katalog_teks_cocok($brand, $token)) {
                $cocok_merek = true;
                break;
            }
        }
        if (!$cocok_merek) {
            continue;
        }
        $merek_seen[$brand] = true;
        $kata_out[] = [
            'label' => $brand,
            'url' => aplikasi_url('produk?' . http_build_query(['q' => $brand])),
        ];
        if (count($kata_out) >= $batas_kata) {
            break;
        }
    }

    $kata_out[] = [
        'label' => 'Lihat semua hasil untuk "' . $q . '"',
        'url' => aplikasi_url('produk?' . http_build_query(['q' => $q])),
    ];

    return ['produk' => $produk_out, 'kata_kunci' => $kata_out];
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

/** Cek apakah user pernah menyelesaikan pesanan produk ini (completed). */
function user_pernah_beli_produk(int $user_id, string $id_produk): bool
{
    if ($user_id <= 0 || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)) {
        return false;
    }
    return _db_call(function () use ($user_id, $id_produk) {
        $pdo = koneksi_database();
        $cocok = ulasan_sql_item_produk_cocok('oi');
        $stmt = $pdo->prepare(
            "SELECT 1 FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = :uid AND o.status = 'completed' AND {$cocok}
             LIMIT 1"
        );
        $stmt->execute(['uid' => $user_id, 'pid' => $id_produk]);
        return (bool) $stmt->fetch();
    }, false);
}

/** Cocokkan baris order_items dengan produk (id_produk atau nama produk lama). */
function ulasan_sql_item_produk_cocok(string $alias_item = 'oi'): string
{
    $a = preg_replace('/[^a-z_]/', '', $alias_item) ?: 'oi';

    return '(' . $a . '.id_produk = :pid OR (
        ' . $a . '.id_produk IS NULL
        AND EXISTS (
            SELECT 1 FROM produk px
            WHERE px.id_produk = :pid
              AND LOWER(TRIM(' . $a . '.product_name)) = LOWER(TRIM(px.nama_produk))
        )
    ))';
}

/** Pesanan milik user, selesai, dan berisi produk ini. */
function ulasan_cek_order_eligible(int $user_id, int $order_id, string $id_produk): bool
{
    if ($user_id <= 0 || $order_id <= 0
        || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)
    ) {
        return false;
    }
    return _db_call(function () use ($user_id, $order_id, $id_produk) {
        $pdo = koneksi_database();
        $cocok = ulasan_sql_item_produk_cocok('oi');
        $stmt = $pdo->prepare(
            "SELECT 1 FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.id = :oid AND o.user_id = :uid AND o.status = 'completed'
               AND {$cocok}
             LIMIT 1"
        );
        $stmt->execute(['oid' => $order_id, 'uid' => $user_id, 'pid' => $id_produk]);
        return (bool) $stmt->fetch();
    }, false);
}

/** Ambil ulasan user untuk satu pesanan + produk. */
function ulasan_ambil_untuk_order(int $user_id, int $order_id, string $id_produk): ?array
{
    if ($user_id <= 0 || $order_id <= 0
        || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)
    ) {
        return null;
    }
    return _db_call(function () use ($user_id, $order_id, $id_produk) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT id, rating, komentar, created_at, edited_at
             FROM ulasan
             WHERE user_id = :u AND order_id = :o AND id_produk = :p
             LIMIT 1'
        );
        $stmt->execute(['u' => $user_id, 'o' => $order_id, 'p' => $id_produk]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }, null);
}

/**
 * Status ulasan untuk satu pesanan: tidak_berhak | belum | bisa_edit | dikunci.
 */
function ulasan_status_untuk_order(int $user_id, int $order_id, string $id_produk): string
{
    if (!ulasan_cek_order_eligible($user_id, $order_id, $id_produk)) {
        return 'tidak_berhak';
    }
    $ulasan = ulasan_ambil_untuk_order($user_id, $order_id, $id_produk);
    if ($ulasan === null) {
        return 'belum';
    }
    if (empty($ulasan['edited_at'])) {
        return 'bisa_edit';
    }
    return 'dikunci';
}

/**
 * Tentukan pesanan & status form ulasan di halaman produk.
 * Prioritas: order_id dari URL → pesanan belum diulas → pesanan bisa diedit sekali.
 *
 * @return array{order_id: int, status: string, ulasan: ?array}
 */
function ulasan_konteks_form(int $user_id, string $id_produk, int $order_id_hint = 0): array
{
    $kosong = ['order_id' => 0, 'status' => 'tidak_berhak', 'ulasan' => null];
    if ($user_id <= 0
        || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)
    ) {
        return $kosong;
    }

    if ($order_id_hint > 0) {
        $status = ulasan_status_untuk_order($user_id, $order_id_hint, $id_produk);
        if ($status === 'tidak_berhak') {
            return $kosong;
        }
        return [
            'order_id' => $order_id_hint,
            'status' => $status,
            'ulasan' => ulasan_ambil_untuk_order($user_id, $order_id_hint, $id_produk),
        ];
    }

    return _db_call(function () use ($user_id, $id_produk, $kosong) {
        $pdo = koneksi_database();
        $cocok = ulasan_sql_item_produk_cocok('oi');
        $stmt = $pdo->prepare(
            "SELECT DISTINCT o.id AS order_id
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = :u AND o.status = 'completed' AND {$cocok}
             ORDER BY o.created_at DESC"
        );
        $stmt->execute(['u' => $user_id, 'pid' => $id_produk]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($orders) || $orders === []) {
            return $kosong;
        }

        $kandidat_edit = null;
        foreach ($orders as $row) {
            $oid = (int) ($row['order_id'] ?? 0);
            if ($oid <= 0) {
                continue;
            }
            $ulasan = ulasan_ambil_untuk_order($user_id, $oid, $id_produk);
            if ($ulasan === null) {
                return ['order_id' => $oid, 'status' => 'belum', 'ulasan' => null];
            }
            if ($kandidat_edit === null && empty($ulasan['edited_at'])) {
                $kandidat_edit = ['order_id' => $oid, 'status' => 'bisa_edit', 'ulasan' => $ulasan];
            }
        }

        return $kandidat_edit ?? ['order_id' => (int) ($orders[0]['order_id'] ?? 0), 'status' => 'dikunci', 'ulasan' => ulasan_ambil_untuk_order($user_id, (int) $orders[0]['order_id'], $id_produk)];
    }, $kosong) ?? $kosong;
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

/**
 * @return array{ok: bool, pesan: string}
 */
function ulasan_buat(int $user_id, int $order_id, string $id_produk, int $rating, string $komentar): array
{
    $gagal = static function (string $pesan): array {
        return ['ok' => false, 'pesan' => $pesan];
    };

    if ($user_id <= 0) {
        return $gagal('Akun belum dikenali. Silakan logout lalu login kembali.');
    }
    if ($order_id <= 0) {
        return $gagal('Pesanan tidak valid. Buka ulasan dari halaman detail pesanan yang sudah selesai.');
    }
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)) {
        return $gagal('Produk tidak valid.');
    }
    if ($rating < 1 || $rating > 5 || trim($komentar) === '') {
        return $gagal('Rating dan komentar wajib diisi.');
    }

    $status = ulasan_status_untuk_order($user_id, $order_id, $id_produk);
    if ($status === 'tidak_berhak') {
        return $gagal('Pesanan belum selesai atau bukan milik Anda.');
    }
    if ($status === 'bisa_edit' || $status === 'dikunci') {
        return $gagal('Ulasan untuk pesanan ini sudah ada. Edit lewat kartu ulasan Anda di atas.');
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'INSERT INTO ulasan (user_id, id_produk, order_id, rating, komentar)
             VALUES (:u, :p, :o, :r, :k)'
        );
        $stmt->execute([
            'u' => $user_id,
            'p' => $id_produk,
            'o' => $order_id,
            'r' => $rating,
            'k' => trim($komentar),
        ]);
        ulasan_refresh_produk_stats($id_produk);

        return ['ok' => true, 'pesan' => ''];
    } catch (PDOException $e) {
        error_log('[ulasan_buat] ' . $e->getMessage());
        $rls = database_pesan_error_rls($e);
        if ($rls !== null) {
            return $gagal($rls);
        }
        if (str_contains($e->getMessage(), 'duplicate key') || str_contains($e->getMessage(), 'unique')) {
            return $gagal('Ulasan untuk pesanan ini sudah tercatat.');
        }

        return $gagal('Gagal menyimpan ulasan ke database. Coba lagi.');
    } catch (Throwable $e) {
        error_log('[ulasan_buat] ' . $e->getMessage());

        return $gagal('Gagal menyimpan ulasan. Coba lagi.');
    }
}

/**
 * @return array{ok: bool, pesan: string}
 */
function ulasan_perbarui(int $user_id, int $order_id, string $id_produk, int $rating, string $komentar): array
{
    $gagal = static function (string $pesan): array {
        return ['ok' => false, 'pesan' => $pesan];
    };

    if ($user_id <= 0 || $order_id <= 0
        || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)
        || $rating < 1 || $rating > 5
        || trim($komentar) === ''
    ) {
        return $gagal('Data ulasan tidak valid.');
    }

    $status = ulasan_status_untuk_order($user_id, $order_id, $id_produk);
    if ($status !== 'bisa_edit') {
        return $gagal('Ulasan ini tidak bisa diedit lagi.');
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'UPDATE ulasan
             SET rating = :r, komentar = :k, edited_at = NOW()
             WHERE user_id = :u AND order_id = :o AND id_produk = :p AND edited_at IS NULL'
        );
        $stmt->execute([
            'u' => $user_id,
            'o' => $order_id,
            'p' => $id_produk,
            'r' => $rating,
            'k' => trim($komentar),
        ]);
        if ($stmt->rowCount() <= 0) {
            return $gagal('Ulasan tidak ditemukan atau sudah pernah diedit.');
        }
        ulasan_refresh_produk_stats($id_produk);

        return ['ok' => true, 'pesan' => ''];
    } catch (PDOException $e) {
        error_log('[ulasan_perbarui] ' . $e->getMessage());
        $rls = database_pesan_error_rls($e);

        return $gagal($rls ?? 'Gagal memperbarui ulasan.');
    } catch (Throwable $e) {
        error_log('[ulasan_perbarui] ' . $e->getMessage());

        return $gagal('Gagal memperbarui ulasan.');
    }
}

/** Jumlah ulasan untuk satu produk. */
/**
 * @return array{jumlah: int, rata: float}
 */
function ulasan_stats_untuk_produk(string $id_produk): array
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)) {
        return ['jumlah' => 0, 'rata' => 0.0];
    }

    $row = _db_call(function () use ($id_produk) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)::int AS jumlah, COALESCE(AVG(rating), 0)::float AS rata
             FROM ulasan WHERE id_produk = :p'
        );
        $stmt->execute(['p' => $id_produk]);
        $hasil = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($hasil) ? $hasil : ['jumlah' => 0, 'rata' => 0.0];
    }, ['jumlah' => 0, 'rata' => 0.0]) ?? ['jumlah' => 0, 'rata' => 0.0];

    return [
        'jumlah' => (int) ($row['jumlah'] ?? 0),
        'rata' => (float) ($row['rata'] ?? 0),
    ];
}

function ulasan_hitung_untuk_produk(string $id_produk): int
{
    return ulasan_stats_untuk_produk($id_produk)['jumlah'];
}

/**
 * Lengkapi nama pengguna untuk baris ulasan (cadangan REST tanpa join).
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function ulasan_lengkapi_nama_pengguna(array $rows): array
{
    if ($rows === []) {
        return [];
    }

    $perlu = [];
    foreach ($rows as $i => $row) {
        $nama = trim((string) ($row['nama_pengguna'] ?? ''));
        if ($nama !== '' && strcasecmp($nama, 'Pembeli') !== 0) {
            continue;
        }
        $uid = (int) ($row['user_id'] ?? 0);
        if ($uid > 0) {
            $perlu[$uid] = true;
        }
    }

    if ($perlu === []) {
        return $rows;
    }

    $peta = _db_call(function () use ($perlu) {
        $pdo = koneksi_database();
        $ids = array_keys($perlu);
        $placeholder = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, COALESCE(NULLIF(TRIM(username), ''), 'Pembeli') AS nama
             FROM users WHERE id IN ({$placeholder})"
        );
        $stmt->execute($ids);
        $hasil = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $baris) {
            $hasil[(int) ($baris['id'] ?? 0)] = (string) ($baris['nama'] ?? 'Pembeli');
        }

        return $hasil;
    }, []) ?? [];

    foreach ($rows as $i => $row) {
        $nama = trim((string) ($row['nama_pengguna'] ?? ''));
        if ($nama !== '' && strcasecmp($nama, 'Pembeli') !== 0) {
            continue;
        }
        $uid = (int) ($row['user_id'] ?? 0);
        $rows[$i]['nama_pengguna'] = $peta[$uid] ?? 'Pembeli';
    }

    return $rows;
}

/** Nama tampilan ulasan: "Anda" untuk pemilik sesi, username untuk pembeli lain. */
function ulasan_nama_tampilan(array $row, int $viewer_user_id): string
{
    $uid = (int) ($row['user_id'] ?? 0);
    if ($viewer_user_id > 0 && $uid === $viewer_user_id) {
        return 'Anda';
    }

    $nama = trim((string) ($row['nama_pengguna'] ?? ''));
    if ($nama === '' || strcasecmp($nama, 'Pembeli') === 0) {
        return $uid > 0 ? 'Pembeli #' . $uid : 'Pembeli';
    }

    return $nama;
}

/** Inisial avatar dari nama pengguna. */
function ulasan_inisial_nama(string $nama): string
{
    $nama = trim($nama);
    if ($nama === '' || strcasecmp($nama, 'Pembeli') === 0) {
        return 'P';
    }
    if (function_exists('mb_substr')) {
        return mb_strtoupper(mb_substr($nama, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return strtoupper(substr($nama, 0, 1));
}

/** Ambil semua ulasan publik untuk 1 produk (PDO utama — andal walau REST/RLS bermasalah). */
function ulasan_ambil_untuk_produk(string $id_produk, int $limit = 50): array
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)) {
        return [];
    }
    if ($limit < 1) {
        $limit = 1;
    }
    if ($limit > 100) {
        $limit = 100;
    }

    $pdo_hasil = _db_call(function () use ($id_produk, $limit) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.rating, u.komentar, u.created_at, u.user_id, u.order_id, u.edited_at,
                    COALESCE(NULLIF(TRIM(us.username), \'\'), \'Pembeli\') AS nama_pengguna
             FROM ulasan u
             LEFT JOIN users us ON us.id = u.user_id
             WHERE u.id_produk = :p
             ORDER BY u.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('p', $id_produk);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }, null);

    if (is_array($pdo_hasil) && $pdo_hasil !== []) {
        return ulasan_lengkapi_nama_pengguna($pdo_hasil);
    }

    $hasil = supabase_rest_request('GET', '/rest/v1/ulasan', [
        'select' => 'id,rating,komentar,created_at,user_id,order_id,edited_at',
        'id_produk' => 'eq.' . $id_produk,
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ]);
    if (!$hasil['ok'] || !is_array($hasil['data'])) {
        return is_array($pdo_hasil) ? ulasan_lengkapi_nama_pengguna($pdo_hasil) : [];
    }

    return ulasan_lengkapi_nama_pengguna($hasil['data']);
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

/**
 * Set id_produk yang ada di wishlist user (untuk tampilan kartu katalog).
 *
 * @return array<string, true>
 */
function wishlist_id_set(int $user_id): array
{
    return _db_call(function () use ($user_id) {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('SELECT id_produk FROM wishlist WHERE user_id = :u');
        $stmt->execute(['u' => $user_id]);
        $set = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (string) ($row['id_produk'] ?? '');
            if ($id !== '') {
                $set[$id] = true;
            }
        }

        return $set;
    }, []);
}

/**
 * Ukuran ringkas untuk kartu katalog (contoh: Size 42 atau Size 40–43).
 */
function katalog_ukuran_ringkas_kartu(array $produk): string
{
    $salin = $produk;
    katalog_lengkapi_ukuran_produk($salin);
    $tersedia = [];
    foreach ($salin['produk_ukuran'] ?? [] as $u) {
        if (!is_array($u)) {
            continue;
        }
        $st = (int) ($u['stok'] ?? 0);
        $uk = trim((string) ($u['ukuran'] ?? ''));
        if ($st > 0 && $uk !== '') {
            $tersedia[] = $uk;
        }
    }
    if ($tersedia === []) {
        return 'Stok habis';
    }
    usort($tersedia, static function (string $a, string $b): int {
        if (ctype_digit($a) && ctype_digit($b)) {
            return (int) $a <=> (int) $b;
        }

        return strnatcasecmp($a, $b);
    });
    if (count($tersedia) === 1) {
        return 'Size ' . $tersedia[0];
    }

    return 'Size ' . $tersedia[0] . '–' . $tersedia[array_key_last($tersedia)];
}

/** Persen kondisi untuk tampilan kartu (preloved: dari deskripsi atau default). */
function katalog_persen_kondisi_kartu(array $produk): string
{
    $kondisi = trim((string) ($produk['kondisi'] ?? ''));
    if (strcasecmp($kondisi, 'Baru') === 0) {
        return '100%';
    }
    $desk = (string) ($produk['deskripsi'] ?? '');
    if (preg_match('/\b(\d{1,3})\s*%/u', $desk, $m)) {
        return ((int) $m[1]) . '%';
    }

    return '90%';
}

/** Teks jumlah terjual untuk kartu katalog (format sama dengan halaman detail). */
function katalog_teks_terjual_kartu(array $produk): string
{
    $jml = max(0, (int) ($produk['terjual'] ?? 0));
    if ($jml <= 0) {
        return '0';
    }

    return $jml . '+';
}

/**
 * Baris rating bintang konsisten di kartu produk / rekomendasi.
 */
function katalog_render_rating_kartu(array $produk, string $kelas = 'kartu-produk__rating'): void
{
    $rata = (float) ($produk['rating_rata'] ?? 0);
    $jml_ulasan = (int) ($produk['jumlah_ulasan'] ?? 0);
    $ada_ulasan = $jml_ulasan > 0;
    $teks_rating = $ada_ulasan
        ? number_format($rata, 1) . ' (' . $jml_ulasan . ')'
        : 'Baru';
    ?>
    <p class="<?php echo htmlspecialchars($kelas, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $ada_ulasan ? ' title="' . htmlspecialchars(number_format($rata, 1) . ' dari 5 · ' . $jml_ulasan . ' ulasan', ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
        <span class="<?php echo htmlspecialchars($kelas, ENT_QUOTES, 'UTF-8'); ?>-bintang" aria-hidden="true">★</span>
        <span><?php echo htmlspecialchars($teks_rating, ENT_QUOTES, 'UTF-8'); ?></span>
    </p>
    <?php
}

/**
 * Render satu kartu produk premium (katalog).
 *
 * @param array<string, true> $wishlist_ids
 */
function katalog_render_kartu_produk(
    array $p,
    bool $sudah_login,
    string $url_masuk,
    array $wishlist_ids = []
): void {
    $id = (string) ($p['id_produk'] ?? '');
    $nama = (string) ($p['nama_produk'] ?? '');
    $brand = (string) ($p['brand'] ?? '');
    $kondisi = (string) ($p['kondisi'] ?? '');
    $harga = (int) ($p['harga'] ?? 0);
    $url_detail = aplikasi_url('detail-produk?id=' . rawurlencode($id));
    $url_gambar = katalog_url_gambar_utama($p);
    $label_kondisi = $kondisi !== '' ? strtoupper(kondisi_label_pembeli($kondisi)) : '';
    $kelas_badge = strcasecmp($kondisi, 'Baru') === 0 ? 'kartu-premium__badge--baru' : 'kartu-premium__badge--preloved';
    $di_wishlist = isset($wishlist_ids[$id]);
    $siap_jual = (bool) ($p['siap_jual'] ?? ((int) ($p['total_stok'] ?? 0) > 0));
    ?>
    <article class="kartu-premium" data-produk-id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="kartu-premium__media">
            <a class="kartu-premium__gambar-tautan" href="<?php echo htmlspecialchars($url_detail, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1" aria-hidden="true">
                <img class="kartu-premium__gambar" src="<?php echo htmlspecialchars($url_gambar, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" width="400" height="520">
            </a>
            <?php if ($label_kondisi !== ''): ?>
                <span class="kartu-premium__badge <?php echo htmlspecialchars($kelas_badge, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label_kondisi, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <button type="button"
                class="kartu-premium__wishlist<?php echo $di_wishlist ? ' kartu-premium__wishlist--aktif' : ''; ?>"
                data-wishlist-toggle
                data-id-produk="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>"
                data-login-url="<?php echo htmlspecialchars($url_masuk, ENT_QUOTES, 'UTF-8'); ?>"
                data-masuk="<?php echo $sudah_login ? '1' : '0'; ?>"
                aria-pressed="<?php echo $di_wishlist ? 'true' : 'false'; ?>"
                aria-label="<?php echo $di_wishlist ? 'Hapus dari wishlist' : 'Tambah ke wishlist'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="<?php echo $di_wishlist ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </button>
        </div>
        <div class="kartu-premium__isi">
            <h2 class="kartu-premium__nama">
                <a href="<?php echo htmlspecialchars($url_detail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></a>
            </h2>
            <ul class="kartu-premium__meta">
                <li><span class="kartu-premium__meta-label">Ukuran</span> <?php echo htmlspecialchars(katalog_ukuran_ringkas_kartu($p), ENT_QUOTES, 'UTF-8'); ?></li>
                <li><span class="kartu-premium__meta-label">Kondisi</span> <?php echo htmlspecialchars(katalog_persen_kondisi_kartu($p), ENT_QUOTES, 'UTF-8'); ?></li>
                <li><?php katalog_render_rating_kartu($p, 'kartu-premium__rating'); ?></li>
            </ul>
            <p class="kartu-premium__harga"><?php echo htmlspecialchars(katalog_format_rupiah($harga), ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="kartu-premium__aksi">
                <a class="kartu-premium__tombol kartu-premium__tombol--detail" href="<?php echo htmlspecialchars($url_detail, ENT_QUOTES, 'UTF-8'); ?>">Detail</a>
                <a class="kartu-premium__tombol kartu-premium__tombol--keranjang<?php echo $siap_jual ? '' : ' kartu-premium__tombol--nonaktif'; ?>"
                   href="<?php echo htmlspecialchars($url_detail, ENT_QUOTES, 'UTF-8'); ?>"
                   aria-label="Lihat produk untuk beli"
                   <?php echo $siap_jual ? '' : ' aria-disabled="true" tabindex="-1"'; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </a>
            </div>
        </div>
    </article>
    <?php
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
                       u1.username AS from_nama, 
                       u2.username AS to_nama
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
