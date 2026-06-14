<?php

declare(strict_types=1);

/**
 * Daftar destinasi populer (ID RajaOngkir Komerce) — fallback bila API pencarian
 * RajaOngkir gagal/limit. ID = ID kelurahan/subdistrict RajaOngkir.
 *
 * @return list<array<string,mixed>>
 */
function jne_destinasi_populer_daftar(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // [id, subdistrict, district, city, province, zip]
    $baris = [
        ['48850', 'BALAI-BALAI', 'PADANG PANJANG BARAT', 'PADANG PANJANG', 'SUMATERA BARAT', '27114'],
        ['48322', 'LIMA KAUM', 'LIMA KAUM', 'TANAH DATAR', 'SUMATERA BARAT', '27211'],
        ['48360', 'AUR TAJUNGKANG TENGAH SAWAH', 'GUGUK PANJANG', 'BUKITTINGGI', 'SUMATERA BARAT', '26111'],
        ['48721', 'BALAI NAN DUO', 'PAYAKUMBUH BARAT', 'PAYAKUMBUH', 'SUMATERA BARAT', '26224'],
        ['48626', 'BALAI KURAI TAJI', 'PARIAMAN SELATAN', 'PARIAMAN', 'SUMATERA BARAT', '25531'],
        ['49239', 'LUBUK BASUNG', 'LUBUK BASUNG', 'AGAM', 'SUMATERA BARAT', '26451'],
        ['49233', 'SIJUNJUNG', 'SIJUNJUNG', 'SIJUNJUNG', 'SUMATERA BARAT', '27553'],
        ['17596', 'CEMPAKA PUTIH BARAT', 'CEMPAKA PUTIH', 'JAKARTA PUSAT', 'DKI JAKARTA', '10520'],
        ['17547', 'GROGOL SELATAN', 'KEBAYORAN LAMA', 'JAKARTA SELATAN', 'DKI JAKARTA', '12220'],
        ['17644', 'KOJA', 'KOJA', 'JAKARTA UTARA', 'DKI JAKARTA', '14210'],
        ['17523', 'CENGKARENG BARAT', 'CENGKARENG', 'JAKARTA BARAT', 'DKI JAKARTA', '11730'],
        ['17674', 'CAKUNG TIMUR', 'CAKUNG', 'JAKARTA TIMUR', 'DKI JAKARTA', '13910'],
        ['6532', 'BEKASI JAYA', 'BEKASI TIMUR', 'BEKASI', 'JAWA BARAT', '17112'],
        ['8118', 'BALUNGBANG JAYA', 'BOGOR BARAT - KOTA', 'BOGOR', 'JAWA BARAT', '16116'],
        ['73239', 'BABAKAN', 'TANGERANG', 'TANGERANG', 'BANTEN', '15118'],
        ['4816', '-', 'BANDUNG', 'BANDUNG', 'JAWA BARAT', '40614'],
        ['69212', 'ASEM ROWO', 'ASEMROWO', 'SURABAYA', 'JAWA TIMUR', '60182'],
        ['65025', 'LAMPER TENGAH', 'SEMARANG SELATAN', 'SEMARANG', 'JAWA TENGAH', '50248'],
        ['31397', 'BENER', 'TEGALREJO', 'YOGYAKARTA', 'DI YOGYAKARTA', '55243'],
        ['41068', 'MEDAN TENGGARA', 'MEDAN DENAI', 'MEDAN', 'SUMATERA UTARA', '20228'],
        ['79234', 'BARA-BARAYA', 'MAKASSAR', 'MAKASSAR', 'SULAWESI SELATAN', '90143'],
        ['26027', 'DAUH PURI', 'DENPASAR BARAT', 'DENPASAR', 'BALI', '80113'],
        ['49618', 'KOTA BARU', 'PEKANBARU KOTA', 'PEKANBARU', 'RIAU', '28114'],
        ['52643', '26 ILIR D. I', 'ILIR BARAT I', 'PALEMBANG', 'SUMATERA SELATAN', '30136'],
        ['9147', 'BALOI PERMAI', 'BATAM KOTA', 'BATAM', 'KEPULAUAN RIAU', '29431'],
    ];

    $out = [];
    foreach ($baris as [$id, $sub, $dist, $city, $prov, $zip]) {
        $id_norm = rajaongkir_normalisasi_kode_desa($id);
        if ($id_norm === '') {
            continue;
        }
        $label = implode(', ', array_filter([$sub, $dist, $city, $prov, $zip]));
        $out[] = [
            'id' => $id_norm,
            'label' => $label,
            'label_tampilan' => rajaongkir_label_tampilan($label),
            'zip_code' => $zip,
            'postal_code' => $zip,
            'subdistrict_name' => $sub,
            'district_name' => $dist,
            'city_name' => $city,
            'province_name' => $prov,
            'is_courier_support' => true,
        ];
    }

    $cache = $out;

    return $cache;
}

/**
 * @return array{ok:bool, http:int, error:string, data:list<array<string,mixed>>, raw:string, fallback:bool}
 */
function jne_destinasi_cari_fallback(string $kata, int $limit = 20): array
{
    $kata_norm = rajaongkir_normalisasi_teks_cocok($kata);
    if ($kata_norm === '') {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'Kata kunci pencarian kosong.',
            'data' => [],
            'raw' => '',
            'fallback' => true,
        ];
    }

    $hasil = [];
    foreach (jne_destinasi_populer_daftar() as $row) {
        $kandidat = [
            rajaongkir_normalisasi_teks_cocok((string) ($row['city_name'] ?? '')),
            rajaongkir_normalisasi_teks_cocok((string) ($row['district_name'] ?? '')),
            rajaongkir_normalisasi_teks_cocok((string) ($row['subdistrict_name'] ?? '')),
            rajaongkir_normalisasi_teks_cocok((string) ($row['label'] ?? '')),
        ];
        foreach ($kandidat as $teks) {
            if ($teks === '') {
                continue;
            }
            if (str_contains($teks, $kata_norm) || str_contains($kata_norm, $teks)) {
                $hasil[] = $row;
                break;
            }
        }
    }

    if ($limit > 0 && count($hasil) > $limit) {
        $hasil = array_slice($hasil, 0, $limit);
    }

    if ($hasil === []) {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'Wilayah tidak ditemukan di daftar cadangan. Coba kata kunci lain (mis. Padang, Jakarta).',
            'data' => [],
            'raw' => '',
            'fallback' => true,
        ];
    }

    return [
        'ok' => true,
        'http' => 200,
        'error' => '',
        'data' => $hasil,
        'raw' => '',
        'fallback' => true,
    ];
}