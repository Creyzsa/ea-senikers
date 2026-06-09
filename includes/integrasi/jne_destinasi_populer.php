<?php

declare(strict_types=1);

/**
 * Daftar destinasi JNE populer — fallback bila API jne.co.id menolak (HTTP 403 dari server cloud).
 *
 * @return list<array{id:string,label:string,label_tampilan:string}>
 */
function jne_destinasi_populer_daftar(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $baris = [
        ['PDG10000', 'PADANG'],
        ['PDG21100', 'PADANGPANJANG'],
        ['PDG10014', 'PADANG BARAT, PADANG'],
        ['PDG10015', 'PADANG SELATAN, PADANG'],
        ['PDG10016', 'PADANG TIMUR, PADANG'],
        ['PDG10017', 'PADANG UTARA, PADANG'],
        ['PDG20117', 'PADANG GANTING, BATU SANGKAR'],
        ['PDG20619', 'PADANG SAGO, PARIAMAN'],
        ['PDG21800', 'PADANG ARO'],
        ['CGK10000', 'JAKARTA'],
        ['CGK10100', 'JAKARTA PUSAT'],
        ['CGK10200', 'JAKARTA UTARA'],
        ['CGK10300', 'JAKARTA BARAT'],
        ['CGK10400', 'JAKARTA SELATAN'],
        ['CGK10500', 'JAKARTA TIMUR'],
        ['SUB10000', 'SURABAYA'],
        ['BDO10000', 'BANDUNG'],
        ['YIA10000', 'YOGYAKARTA'],
        ['SRG10000', 'SEMARANG'],
        ['MLG10000', 'MALANG'],
        ['DPS10000', 'DENPASAR'],
        ['UPG10000', 'MAKASSAR'],
        ['MDN10000', 'MEDAN'],
        ['PLM10000', 'PALEMBANG'],
        ['BTH10000', 'BATAM'],
        ['PKU10000', 'PEKANBARU'],
        ['BPN10000', 'BALIKPAPAN'],
        ['PNK10000', 'PONTIANAK'],
        ['DJJ10000', 'JAYAPURA'],
        ['AMQ10000', 'AMBON'],
        ['KOE10000', 'KUPANG'],
        ['SOC10000', 'SOLO'],
        ['BTG10000', 'BATANG'],
        ['TGR10000', 'TANGERANG'],
        ['BOO10000', 'BOGOR'],
        ['BKI10000', 'BEKASI'],
        ['DEP10000', 'DEPOK'],
        ['CBN10000', 'CIREBON'],
        ['PWK10000', 'PURWOKERTO'],
        ['MES10000', 'PEMATANG SIANTAR'],
        ['DTB20500', 'PADANGSIDEMPUAN'],
        ['BKS10000', 'BENGKULU'],
        ['TKG10000', 'BANDAR LAMPUNG'],
    ];

    $out = [];
    foreach ($baris as [$kode, $label]) {
        $id = rajaongkir_normalisasi_kode_desa($kode);
        if ($id === '') {
            continue;
        }
        $out[] = [
            'id' => $id,
            'label' => $label,
            'label_tampilan' => rajaongkir_label_tampilan($label),
            'zip_code' => '',
            'postal_code' => '',
            'subdistrict_name' => $label,
            'district_name' => '',
            'city_name' => '',
            'province_name' => '',
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
        $label_norm = rajaongkir_normalisasi_teks_cocok((string) ($row['label'] ?? ''));
        $tampil_norm = rajaongkir_normalisasi_teks_cocok((string) ($row['label_tampilan'] ?? ''));
        if (
            str_contains($label_norm, $kata_norm)
            || str_contains($kata_norm, $label_norm)
            || str_contains($tampil_norm, $kata_norm)
            || str_contains($kata_norm, $tampil_norm)
        ) {
            $hasil[] = $row;
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