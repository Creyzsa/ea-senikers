<?php

declare(strict_types=1);

require_once __DIR__ . '/repositori/katalog_produk.php';
require_once __DIR__ . '/keranjang_sesi.php';

/** Cadangan checkout di cookie (serverless/Vercel — sesi PHP sering kosong setelah redirect). */
const EASENIKERS_COOKIE_CHECKOUT = 'easenikers_co';

/** Masa berlaku cadangan checkout (1 jam). */
const EASENIKERS_CHECKOUT_COOKIE_DETIK = 3600;

/**
 * @return list<array{id_produk: string, ukuran: string, qty: int}>
 */
function checkout_baris_dari_sesi(?array $sesi): array
{
    if (!is_array($sesi)) {
        return [];
    }
    if (!empty($sesi['items']) && is_array($sesi['items'])) {
        $out = [];
        foreach ($sesi['items'] as $it) {
            if (!is_array($it) || empty($it['id_produk']) || empty($it['ukuran'])) {
                continue;
            }
            $out[] = [
                'id_produk' => (string) $it['id_produk'],
                'ukuran' => (string) $it['ukuran'],
                'qty' => max(1, (int) ($it['qty'] ?? 1)),
            ];
        }
        return $out;
    }
    if (!empty($sesi['id_produk']) && !empty($sesi['ukuran'])) {
        return [
            [
                'id_produk' => (string) $sesi['id_produk'],
                'ukuran' => (string) $sesi['ukuran'],
                'qty' => max(1, (int) ($sesi['qty'] ?? 1)),
            ],
        ];
    }

    return [];
}

/**
 * @return list<array{id_produk: string, ukuran: string, qty: int}>
 */
function checkout_normalisasi_baris_input(array $baris): array
{
    $out = [];
    foreach ($baris as $b) {
        if (!is_array($b)) {
            continue;
        }
        $id = trim((string) ($b['id_produk'] ?? ''));
        $uk = trim((string) ($b['ukuran'] ?? ''));
        if ($id === '' || $uk === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            continue;
        }
        $out[] = [
            'id_produk' => $id,
            'ukuran' => $uk,
            'qty' => max(1, (int) ($b['qty'] ?? 1)),
        ];
    }

    return $out;
}

/**
 * Simpan baris checkout ke cookie (httponly).
 *
 * @param list<array{id_produk: string, ukuran: string, qty: int}> $baris
 */
/**
 * Encode payload checkout untuk cookie (base64url — JSON mentah sering ditolak browser).
 */
function checkout_encode_cookie_nilai(array $baris): string
{
    $json = json_encode(['items' => $baris, 'ts' => time()], JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || $json === '') {
        return '';
    }

    return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
}

/**
 * @return array{items?: mixed, ts?: mixed}|null
 */
function checkout_decode_cookie_nilai(string $raw): ?array
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if ($raw[0] === '{' || $raw[0] === '[') {
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
    $b64 = strtr($raw, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad > 0) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $json = base64_decode($b64, true);
    if ($json === false || $json === '') {
        return null;
    }
    $data = json_decode($json, true);

    return is_array($data) ? $data : null;
}

function checkout_simpan_cookie_baris(array $baris): void
{
    $baris = checkout_normalisasi_baris_input($baris);
    if ($baris === []) {
        checkout_hapus_cookie_baris();
        return;
    }

    require_once __DIR__ . '/auth_db/sesi.php';
    $opsi = sesi_opsi_cookie();
    $payload = checkout_encode_cookie_nilai($baris);
    if ($payload === '') {
        return;
    }

    setcookie(EASENIKERS_COOKIE_CHECKOUT, $payload, [
        'expires' => time() + EASENIKERS_CHECKOUT_COOKIE_DETIK,
        'path' => $opsi['path'],
        'domain' => $opsi['domain'],
        'secure' => $opsi['secure'],
        'httponly' => $opsi['httponly'],
        'samesite' => $opsi['samesite'],
    ]);
    $_COOKIE[EASENIKERS_COOKIE_CHECKOUT] = $payload;
}

function checkout_hapus_cookie_baris(): void
{
    if (!isset($_COOKIE[EASENIKERS_COOKIE_CHECKOUT])) {
        return;
    }
    require_once __DIR__ . '/auth_db/sesi.php';
    $opsi = sesi_opsi_cookie();
    setcookie(EASENIKERS_COOKIE_CHECKOUT, '', [
        'expires' => time() - 3600,
        'path' => $opsi['path'],
        'domain' => $opsi['domain'],
        'secure' => $opsi['secure'],
        'httponly' => $opsi['httponly'],
        'samesite' => $opsi['samesite'],
    ]);
    unset($_COOKIE[EASENIKERS_COOKIE_CHECKOUT]);
}

/**
 * @return list<array{id_produk: string, ukuran: string, qty: int}>
 */
function checkout_muat_cookie_baris(): array
{
    $raw = (string) ($_COOKIE[EASENIKERS_COOKIE_CHECKOUT] ?? '');
    if ($raw === '') {
        return [];
    }
    $data = checkout_decode_cookie_nilai($raw);
    if ($data === null || empty($data['items']) || !is_array($data['items'])) {
        return [];
    }
    $ts = (int) ($data['ts'] ?? 0);
    if ($ts > 0 && (time() - $ts) > EASENIKERS_CHECKOUT_COOKIE_DETIK) {
        checkout_hapus_cookie_baris();

        return [];
    }

    return checkout_normalisasi_baris_input($data['items']);
}

/**
 * Pulihkan $_SESSION['checkout_pesanan'] dari cookie bila sesi PHP kosong.
 */
function checkout_pulihkan_dari_cookie(): void
{
    $ada = checkout_baris_dari_sesi($_SESSION['checkout_pesanan'] ?? null);
    if ($ada !== []) {
        return;
    }
    $dari_cookie = checkout_muat_cookie_baris();
    if ($dari_cookie !== []) {
        $_SESSION['checkout_pesanan'] = ['items' => $dari_cookie];
    }
}

/**
 * Ambil baris checkout aktif (sesi dulu, lalu cookie cadangan).
 *
 * @return list<array{id_produk: string, ukuran: string, qty: int}>
 */
function checkout_ambil_baris_aktif(): array
{
    $dari_sesi = checkout_baris_dari_sesi($_SESSION['checkout_pesanan'] ?? null);
    if ($dari_sesi !== []) {
        return $dari_sesi;
    }

    return checkout_muat_cookie_baris();
}

/**
 * @param list<array{id_produk: string, ukuran: string, qty: int}> $baris
 */
function checkout_set_sesi_baris(array $baris): void
{
    $baris = checkout_normalisasi_baris_input($baris);
    $_SESSION['checkout_pesanan'] = ['items' => $baris];
    checkout_simpan_cookie_baris($baris);
}

/**
 * @param list<array{id_produk: string, ukuran: string, qty: int}> $baris
 */
function checkout_validasi_stok_baris(array $baris): ?string
{
    foreach ($baris as $b) {
        $produk = katalog_ambil_produk_ber_id($b['id_produk']);
        if ($produk === null) {
            return 'Produk sudah tidak tersedia.';
        }
        $stok = 0;
        foreach ($produk['produk_ukuran'] ?? [] as $u) {
            if ((string) ($u['ukuran'] ?? '') === $b['ukuran']) {
                $stok = (int) ($u['stok'] ?? 0);
                break;
            }
        }
        if ($stok < $b['qty']) {
            $nama = (string) ($produk['nama_produk'] ?? 'Produk');
            return 'Stok ukuran ' . $b['ukuran'] . ' untuk "' . $nama . '" tidak mencukupi.';
        }
    }

    return null;
}

function checkout_siapkan_dari_keranjang(): ?string
{
    $keranjang = keranjang_ambil_baris();
    if ($keranjang === []) {
        return 'Keranjang kosong.';
    }
    $baris = [];
    foreach ($keranjang as $r) {
        $id = trim((string) ($r['id_produk'] ?? ''));
        $uk = trim((string) ($r['ukuran'] ?? ''));
        if ($id === '' || $uk === '') {
            continue;
        }
        $baris[] = [
            'id_produk' => $id,
            'ukuran' => $uk,
            'qty' => max(1, (int) ($r['qty'] ?? 1)),
        ];
    }
    if ($baris === []) {
        return 'Keranjang kosong.';
    }
    $err = checkout_validasi_stok_baris($baris);
    if ($err !== null) {
        return $err;
    }
    checkout_set_sesi_baris($baris);

    return null;
}

/**
 * @param list<array{id_produk: string, ukuran: string, qty: int}> $baris
 * @return list<array{
 *   id_produk: string, ukuran: string, qty: int, nama_produk: string, brand: string,
 *   kondisi: string, harga: int, subtotal: int, berat_gram: int, gambar: string, nama_file: string
 * }>
 */
function checkout_muat_detail_baris(array $baris): array
{
    $out = [];
    foreach ($baris as $b) {
        $produk = katalog_ambil_produk_ber_id($b['id_produk']);
        if ($produk === null) {
            continue;
        }
        $harga = (int) ($produk['harga'] ?? 0);
        $qty = $b['qty'];
        $out[] = [
            'id_produk' => $b['id_produk'],
            'ukuran' => $b['ukuran'],
            'qty' => $qty,
            'nama_produk' => (string) ($produk['nama_produk'] ?? ''),
            'brand' => (string) ($produk['brand'] ?? ''),
            'kondisi' => (string) ($produk['kondisi'] ?? ''),
            'harga' => $harga,
            'subtotal' => $harga * $qty,
            'berat_gram' => max(100, (int) ($produk['berat_gram'] ?? 1000)),
            'gambar' => katalog_url_gambar_utama($produk),
            'nama_file' => (string) ($produk['produk_gambar'][0]['nama_file'] ?? ''),
        ];
    }

    return $out;
}