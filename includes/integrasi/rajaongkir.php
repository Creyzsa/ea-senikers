<?php

declare(strict_types=1);

/**
 * Integrasi ongkir & wilayah — API Co.id (https://docs.api.co.id/).
 *
 * - Regional: GET https://use.api.co.id/regional/indonesia/...
 * - Ongkir: GET https://use.api.co.id/expedition/shipping-cost
 * - Header: x-api-co-id: <API_KEY>
 *
 * Nama fungsi rajaongkir_* dipertahankan agar checkout/admin tidak perlu refactor besar.
 */

require_once __DIR__ . '/../config_loader.php';
require_once __DIR__ . '/../repositori/admin_pengaturan_repositori.php';

const APICOID_BASE_URL = 'https://use.api.co.id';

/**
 * @return array<string, string>
 */
function rajaongkir_kurir_didukung(): array
{
    return [
        'jne' => 'JNE',
        'jnt' => 'J&T Express',
        'jt' => 'J&T Express',
        'sicepat' => 'SiCepat',
        'sap' => 'SAP Express',
        'anteraja' => 'AnterAja',
        'lion' => 'Lion Parcel',
        'ninja' => 'Ninja Xpress',
        'idexpress' => 'iDexpress',
        'pos' => 'POS Indonesia',
        'tiki' => 'TIKI',
    ];
}

function rajaongkir_api_key(): string
{
    if (defined('APICOID_API_KEY') && is_string(APICOID_API_KEY) && APICOID_API_KEY !== '') {
        return trim(APICOID_API_KEY);
    }
    $env = getenv('APICOID_API_KEY');
    if (is_string($env) && trim($env) !== '') {
        return trim($env);
    }
    $cfg = admin_pengaturan_muat_terapan();
    return trim((string) ($cfg['rajaongkir_api_key'] ?? ''));
}

function rajaongkir_normalisasi_kode_desa(string|int $nilai): string
{
    $s = preg_replace('/\D+/', '', (string) $nilai) ?? '';
    return strlen($s) === 10 ? $s : '';
}

/** Kode desa/kelurahan asal pengiriman (10 digit). */
function rajaongkir_asal_kode(): string
{
    $cfg = admin_pengaturan_muat_terapan();
    $kode = rajaongkir_normalisasi_kode_desa((string) ($cfg['rajaongkir_kota_asal_kode'] ?? ''));
    if ($kode !== '') {
        return $kode;
    }
    $legacy = (string) ($cfg['rajaongkir_kota_asal_id'] ?? '');
    return rajaongkir_normalisasi_kode_desa($legacy);
}

/**
 * Kompatibilitas lama: > 0 bila kode asal sudah diatur.
 */
function rajaongkir_kota_asal_id(): int
{
    return rajaongkir_asal_kode() !== '' ? 1 : 0;
}

/**
 * @param 'GET'|'POST' $metode
 * @param array<string, scalar> $query
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function apicoid_request(string $metode, string $path, array $query = []): array
{
    $key = rajaongkir_api_key();
    if ($key === '') {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'API Key belum diisi. Buka Pengaturan admin → Integrasi API Co.id.',
            'data' => null,
            'raw' => '',
        ];
    }

    $url = APICOID_BASE_URL . $path;
    if ($query !== []) {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($query);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER => [
            'x-api-co-id: ' . $key,
            'Accept: application/json',
        ],
    ]);

    if (strtoupper($metode) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    }

    $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($ca && is_readable($ca)) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err_curl = (string) curl_error($ch);
    $raw_str = is_string($raw) ? $raw : '';

    if ($raw === false) {
        return [
            'ok' => false,
            'http' => $http,
            'error' => $err_curl !== '' ? $err_curl : 'Tidak ada respons dari API Co.id.',
            'data' => null,
            'raw' => $raw_str,
        ];
    }

    $json = json_decode($raw_str, true);
    if (!is_array($json)) {
        return [
            'ok' => false,
            'http' => $http,
            'error' => 'Respons bukan JSON valid (HTTP ' . $http . ').',
            'data' => null,
            'raw' => $raw_str,
        ];
    }

    if (isset($json['is_success']) && $json['is_success'] === false) {
        return [
            'ok' => false,
            'http' => $http,
            'error' => trim((string) ($json['message'] ?? 'Permintaan ditolak API Co.id.')),
            'data' => null,
            'raw' => $raw_str,
        ];
    }

    if ($http >= 400) {
        return [
            'ok' => false,
            'http' => $http,
            'error' => trim((string) ($json['message'] ?? 'HTTP ' . $http)),
            'data' => null,
            'raw' => $raw_str,
        ];
    }

    $data = $json['data'] ?? $json['result'] ?? $json;
    if (($json['status'] ?? '') === 'success' && isset($json['result'])) {
        $data = $json['result'];
    }

    return [
        'ok' => true,
        'http' => $http,
        'error' => '',
        'data' => $data,
        'raw' => $raw_str,
    ];
}

/**
 * Tes koneksi: ambil daftar provinsi.
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_daftar_provinsi(): array
{
    return apicoid_request('GET', '/regional/indonesia/provinces', ['page' => 1]);
}

/**
 * @return list<array<string, mixed>>
 */
function apicoid_normalisasi_baris_desa(mixed $data): array
{
    $rows = [];
    if (!is_array($data)) {
        return $rows;
    }
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $code = rajaongkir_normalisasi_kode_desa((string) ($row['code'] ?? ''));
        if ($code === '') {
            continue;
        }
        $nama = trim((string) ($row['name'] ?? ''));
        $kec = trim((string) ($row['district'] ?? ''));
        $kab = trim((string) ($row['regency'] ?? ''));
        $prov = trim((string) ($row['province'] ?? ''));
        $label = implode(', ', array_filter([$nama, $kec, $kab, $prov]));
        $pos = '';
        if (isset($row['postal_codes']) && is_array($row['postal_codes']) && $row['postal_codes'] !== []) {
            $pos = trim((string) $row['postal_codes'][0]);
        }
        $rows[] = [
            'id' => $code,
            'label' => $label,
            'zip_code' => $pos,
            'postal_code' => $pos,
            'subdistrict_name' => $nama,
            'district_name' => $kec,
            'city_name' => $kab,
            'province_name' => $prov,
            'is_courier_support' => !empty($row['is_courier_support']),
        ];
    }

    return $rows;
}

/**
 * Cari desa/kelurahan (kode 10 digit) untuk destinasi ongkir.
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_cari_destinasi(string $kata, int $limit = 20, int $offset = 0): array
{
    $kata = trim($kata);
    if ($kata === '') {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'Kata kunci pencarian kosong.',
            'data' => null,
            'raw' => '',
        ];
    }
    if ($limit < 1 || $limit > 100) {
        $limit = 20;
    }
    $page = (int) floor(max(0, $offset) / 100) + 1;

    $res = apicoid_request('GET', '/regional/indonesia/villages', [
        'name' => $kata,
        'page' => $page,
    ]);
    if (!$res['ok']) {
        return $res;
    }

    $rows = apicoid_normalisasi_baris_desa($res['data']);
    if ($limit < count($rows)) {
        $rows = array_slice($rows, 0, $limit);
    }

    return [
        'ok' => true,
        'http' => $res['http'],
        'error' => '',
        'data' => $rows,
        'raw' => $res['raw'],
    ];
}

/**
 * Hitung ongkos kirim antar kode desa (berat dalam gram).
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_cek_ongkir(string|int $origin, string|int $destination, int $weight_gram, string $courier = ''): array
{
    $origin_kode = rajaongkir_normalisasi_kode_desa($origin);
    $dest_kode = rajaongkir_normalisasi_kode_desa($destination);

    if ($origin_kode === '' || $dest_kode === '') {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'Kode desa asal dan tujuan wajib 10 digit (dari API Regional).',
            'data' => null,
            'raw' => '',
        ];
    }
    if ($weight_gram <= 0) {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'Berat harus > 0 gram.',
            'data' => null,
            'raw' => '',
        ];
    }

    $berat_kg = max(0.01, round($weight_gram / 1000, 2));

    $res = apicoid_request('GET', '/expedition/shipping-cost', [
        'origin_village_code' => $origin_kode,
        'destination_village_code' => $dest_kode,
        'weight' => $berat_kg,
    ]);
    if (!$res['ok']) {
        return $res;
    }

    $couriers = [];
    $payload = $res['data'];
    if (is_array($payload)) {
        if (isset($payload['couriers']) && is_array($payload['couriers'])) {
            $couriers = $payload['couriers'];
        } elseif (isset($payload[0]['courier_code'])) {
            $couriers = $payload;
        }
    }

    $filter = [];
    if ($courier !== '') {
        foreach (explode(':', strtolower($courier)) as $k) {
            $k = trim($k);
            if ($k !== '') {
                $filter[] = $k;
            }
        }
    }

    $opsi = [];
    foreach ($couriers as $row) {
        if (!is_array($row)) {
            continue;
        }
        $harga = (int) ($row['price'] ?? 0);
        if ($harga <= 0) {
            continue;
        }
        $kode = (string) ($row['courier_code'] ?? '');
        $nama = (string) ($row['courier_name'] ?? $kode);
        $kode_lc = strtolower($kode);
        if ($filter !== []) {
            $cocok = false;
            foreach ($filter as $f) {
                if ($kode_lc === $f || str_contains($kode_lc, $f)) {
                    $cocok = true;
                    break;
                }
            }
            if (!$cocok) {
                continue;
            }
        }
        $opsi[] = [
            'code' => $kode_lc,
            'service' => $nama,
            'description' => $nama,
            'cost' => $harga,
            'etd' => (string) ($row['estimation'] ?? ''),
        ];
    }

    if ($opsi === [] && $couriers !== []) {
        return [
            'ok' => false,
            'http' => $res['http'],
            'error' => 'Tidak ada kurir dengan harga untuk filter ini.',
            'data' => null,
            'raw' => $res['raw'],
        ];
    }

    return [
        'ok' => true,
        'http' => $res['http'],
        'error' => '',
        'data' => $opsi,
        'raw' => $res['raw'],
    ];
}