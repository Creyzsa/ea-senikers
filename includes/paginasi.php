<?php

declare(strict_types=1);

/**
 * Paginasi sederhana berbasis array — potong daftar per halaman dan render
 * navigasi halaman. Dipakai daftar admin (produk/pengguna/pesanan) maupun
 * daftar pembeli (katalog/riwayat pesanan) agar konsisten.
 */
require_once __DIR__ . '/url_bantu.php';

/** Baca nomor halaman aktif dari query string (minimal 1). */
function paginasi_halaman_dari_query(string $param = 'hal'): int
{
    $nilai = $_GET[$param] ?? 1;
    $hal = is_numeric($nilai) ? (int) $nilai : 1;

    return $hal < 1 ? 1 : $hal;
}

/**
 * Hitung metadata paginasi. Nomor halaman dijepit ke rentang valid.
 *
 * @return array{halaman:int,total_halaman:int,per_halaman:int,offset:int,total_item:int,dari:int,sampai:int}
 */
function paginasi_hitung(int $total_item, int $halaman, int $per_halaman = 10): array
{
    $total_item = max(0, $total_item);
    $per_halaman = max(1, $per_halaman);
    $total_halaman = max(1, (int) ceil($total_item / $per_halaman));
    $halaman = min(max(1, $halaman), $total_halaman);
    $offset = ($halaman - 1) * $per_halaman;

    return [
        'halaman' => $halaman,
        'total_halaman' => $total_halaman,
        'per_halaman' => $per_halaman,
        'offset' => $offset,
        'total_item' => $total_item,
        'dari' => $total_item === 0 ? 0 : $offset + 1,
        'sampai' => min($offset + $per_halaman, $total_item),
    ];
}

/**
 * Potong array item untuk halaman aktif.
 *
 * @param array<int, mixed> $items
 * @param array{offset:int,per_halaman:int} $info
 * @return array<int, mixed>
 */
function paginasi_potong(array $items, array $info): array
{
    return array_slice($items, (int) $info['offset'], (int) $info['per_halaman']);
}

/**
 * Bangun closure pembentuk URL halaman, mempertahankan parameter query lain.
 *
 * @param array<string, scalar> $params_lain Parameter query selain nomor halaman
 */
function paginasi_pembuat_url(string $base_url, array $params_lain = [], string $param = 'hal'): callable
{
    return static function (int $hal) use ($base_url, $params_lain, $param): string {
        $query = $params_lain;
        $query[$param] = $hal;

        return $base_url . (str_contains($base_url, '?') ? '&' : '?') . http_build_query($query);
    };
}

/**
 * Render navigasi paginasi (Sebelumnya / nomor halaman / Berikutnya).
 * Mengembalikan string kosong bila hanya ada satu halaman.
 *
 * @param array{halaman:int,total_halaman:int} $info  Hasil paginasi_hitung()
 * @param callable(int):string $url_halaman           Pembentuk URL per nomor halaman
 */
function paginasi_render(array $info, callable $url_halaman): string
{
    $total = (int) $info['total_halaman'];
    $aktif = (int) $info['halaman'];
    if ($total <= 1) {
        return '';
    }

    $jangkauan = 2;
    $awal = max(1, $aktif - $jangkauan);
    $akhir = min($total, $aktif + $jangkauan);

    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $html = '<nav class="paginasi" aria-label="Navigasi halaman">';

    if ($aktif > 1) {
        $html .= '<a class="paginasi__item paginasi__item--nav" rel="prev" href="' . $esc($url_halaman($aktif - 1)) . '" aria-label="Halaman sebelumnya">&larr;</a>';
    } else {
        $html .= '<span class="paginasi__item paginasi__item--nav paginasi__item--mati" aria-hidden="true">&larr;</span>';
    }

    if ($awal > 1) {
        $html .= '<a class="paginasi__item" href="' . $esc($url_halaman(1)) . '">1</a>';
        if ($awal > 2) {
            $html .= '<span class="paginasi__elipsis" aria-hidden="true">&hellip;</span>';
        }
    }

    for ($i = $awal; $i <= $akhir; $i++) {
        if ($i === $aktif) {
            $html .= '<span class="paginasi__item paginasi__item--aktif" aria-current="page">' . $i . '</span>';
        } else {
            $html .= '<a class="paginasi__item" href="' . $esc($url_halaman($i)) . '">' . $i . '</a>';
        }
    }

    if ($akhir < $total) {
        if ($akhir < $total - 1) {
            $html .= '<span class="paginasi__elipsis" aria-hidden="true">&hellip;</span>';
        }
        $html .= '<a class="paginasi__item" href="' . $esc($url_halaman($total)) . '">' . $total . '</a>';
    }

    if ($aktif < $total) {
        $html .= '<a class="paginasi__item paginasi__item--nav" rel="next" href="' . $esc($url_halaman($aktif + 1)) . '" aria-label="Halaman berikutnya">&rarr;</a>';
    } else {
        $html .= '<span class="paginasi__item paginasi__item--nav paginasi__item--mati" aria-hidden="true">&rarr;</span>';
    }

    $html .= '</nav>';

    return $html;
}
