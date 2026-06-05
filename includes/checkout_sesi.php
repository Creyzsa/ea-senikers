<?php

declare(strict_types=1);

require_once __DIR__ . '/repositori/katalog_produk.php';
require_once __DIR__ . '/keranjang_sesi.php';

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
 * @param list<array{id_produk: string, ukuran: string, qty: int}> $baris
 */
function checkout_set_sesi_baris(array $baris): void
{
    $_SESSION['checkout_pesanan'] = ['items' => $baris];
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