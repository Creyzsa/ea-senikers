<?php

declare(strict_types=1);

/**
 * Keranjang belanja sementara di sesi PHP (nanti bisa dipindah ke tabel DB).
 */
const EASENIKERS_KERANJANG_SESI = 'easenikers_keranjang';

/**
 * @return list<array{kunci: string, id_produk: string, nama_produk: string, brand: string, kondisi: string, ukuran: string, harga: int, qty: int, stok_max: int, nama_file?: string}>
 */
function keranjang_ambil_baris(): array
{
    if (empty($_SESSION[EASENIKERS_KERANJANG_SESI]) || !is_array($_SESSION[EASENIKERS_KERANJANG_SESI])) {
        return [];
    }
    $out = [];
    foreach ($_SESSION[EASENIKERS_KERANJANG_SESI] as $row) {
        if (is_array($row) && !empty($row['kunci'])) {
            $out[] = $row;
        }
    }
    return $out;
}

function keranjang_kunci_baris(string $id_produk, string $ukuran): string
{
    return $id_produk . '|' . $ukuran;
}

function keranjang_hitung_jumlah_item(): int
{
    $n = 0;
    foreach (keranjang_ambil_baris() as $r) {
        $n += (int) ($r['qty'] ?? 0);
    }
    return $n;
}

/**
 * @param array{id_produk: string, nama_produk: string, brand: string, kondisi: string, ukuran: string, harga: int, stok_max: int, nama_file?: string} $item
 */
function keranjang_tambah_atau_update(array $item): void
{
    if (!isset($_SESSION[EASENIKERS_KERANJANG_SESI]) || !is_array($_SESSION[EASENIKERS_KERANJANG_SESI])) {
        $_SESSION[EASENIKERS_KERANJANG_SESI] = [];
    }
    $kunci = keranjang_kunci_baris($item['id_produk'], $item['ukuran']);
    $qty_baru = (int) ($item['qty'] ?? 1);
    if ($qty_baru < 1) {
        $qty_baru = 1;
    }
    $max = (int) ($item['stok_max'] ?? 0);
    $found = false;
    foreach ($_SESSION[EASENIKERS_KERANJANG_SESI] as $i => $row) {
        if (is_array($row) && ($row['kunci'] ?? '') === $kunci) {
            $q = (int) ($row['qty'] ?? 0) + $qty_baru;
            if ($q > $max) {
                $q = $max;
            }
            $_SESSION[EASENIKERS_KERANJANG_SESI][$i]['qty'] = $q;
            $found = true;
            break;
        }
    }
    if (!$found) {
        if ($qty_baru > $max) {
            $qty_baru = $max;
        }
        $_SESSION[EASENIKERS_KERANJANG_SESI][] = [
            'kunci' => $kunci,
            'id_produk' => $item['id_produk'],
            'nama_produk' => $item['nama_produk'],
            'brand' => $item['brand'],
            'kondisi' => $item['kondisi'],
            'ukuran' => $item['ukuran'],
            'harga' => (int) $item['harga'],
            'qty' => $qty_baru,
            'stok_max' => $max,
            'nama_file' => (string) ($item['nama_file'] ?? ''),
        ];
    }
}

function keranjang_hapus_kunci(string $kunci): void
{
    if (empty($_SESSION[EASENIKERS_KERANJANG_SESI]) || !is_array($_SESSION[EASENIKERS_KERANJANG_SESI])) {
        return;
    }
    $_SESSION[EASENIKERS_KERANJANG_SESI] = array_values(array_filter(
        $_SESSION[EASENIKERS_KERANJANG_SESI],
        static function ($row) use ($kunci) {
            return !is_array($row) || ($row['kunci'] ?? '') !== $kunci;
        }
    ));
}

function keranjang_total_rupiah(): int
{
    $t = 0;
    foreach (keranjang_ambil_baris() as $r) {
        $t += (int) ($r['harga'] ?? 0) * (int) ($r['qty'] ?? 0);
    }
    return $t;
}

function keranjang_kosongkan(): void
{
    unset($_SESSION[EASENIKERS_KERANJANG_SESI]);
}
