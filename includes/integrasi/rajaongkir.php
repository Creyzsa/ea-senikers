<?php

declare(strict_types=1);

/**
 * Integrasi ongkir JNE — endpoint publik situs jne.co.id (sama dengan halaman Cek Ongkir).
 *
 * - Cari asal:     GET https://jne.co.id/api-origin?search=...
 * - Cari tujuan:   GET https://jne.co.id/api-destination?search=...
 * - Tarif:         GET https://jne.co.id/api-price?origin=PDG21100&destination=CGK10400&weight=1
 *
 * Kode lokasi format JNE: 3 huruf + 5 angka (contoh PDG21100 = Padang Panjang, BOO10000 = Bogor).
 * Berat di api-price dalam kilogram (integer minimal 1).
 *
 * Nama fungsi rajaongkir_* dipertahankan agar checkout/admin tetap kompatibel.
 */

require_once __DIR__ . '/../config_loader.php';
require_once __DIR__ . '/../repositori/admin_pengaturan_repositori.php';

const JNE_WEB_BASE_URL = 'https://jne.co.id';

/**
 * @return array<string, string>
 */
function rajaongkir_kurir_didukung(): array
{
    return [
        'jne' => 'JNE',
        'reg' => 'REG (Regular)',
        'yes' => 'YES (Yakin Esok Sampai)',
        'oke' => 'OKE',
        'jtr' => 'JTR (Trucking)',
        'sps' => 'SPS',
    ];
}

/** Tidak dipakai untuk API web JNE; tetap ada agar pengaturan lama tidak error. */
function rajaongkir_api_key(): string
{
    if (defined('JNE_API_USERNAME') && defined('JNE_API_KEY')) {
        return trim((string) JNE_API_USERNAME) !== '' && trim((string) JNE_API_KEY) !== '' ? 'configured' : '';
    }
    $cfg = admin_pengaturan_muat_terapan();
    $legacy = trim((string) ($cfg['rajaongkir_api_key'] ?? ''));
    return $legacy !== '' ? $legacy : 'jne-web';
}

/**
 * Normalisasi kode cabang JNE (mis. pdg21100 → PDG21100).
 */
function rajaongkir_normalisasi_kode_desa(string|int $nilai): string
{
    $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $nilai) ?? '');
    return preg_match('/^[A-Z]{3}\d{5}$/', $s) === 1 ? $s : '';
}

/** Kode asal pengiriman toko (format JNE). */
function rajaongkir_asal_kode(): string
{
    $cfg = admin_pengaturan_muat_terapan();
    $kode = rajaongkir_normalisasi_kode_desa((string) ($cfg['rajaongkir_kota_asal_kode'] ?? ''));
    if ($kode !== '') {
        return $kode;
    }
    if (defined('JNE_ORIGIN_CODE')) {
        return rajaongkir_normalisasi_kode_desa((string) JNE_ORIGIN_CODE);
    }

    return '';
}

function rajaongkir_kota_asal_id(): int
{
    return rajaongkir_asal_kode() !== '' ? 1 : 0;
}

/**
 * @param array<string, scalar> $query
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function jne_web_request(string $path, array $query = []): array
{
    $url = JNE_WEB_BASE_URL . $path;
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
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest',
            'User-Agent: EA-SENIKERS-Checkout/1.0',
        ],
    ]);

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
            'error' => $err_curl !== '' ? $err_curl : 'Tidak ada respons dari jne.co.id.',
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

    if ($http >= 400) {
        return [
            'ok' => false,
            'http' => $http,
            'error' => trim((string) ($json['message'] ?? 'Permintaan ditolak jne.co.id.')),
            'data' => null,
            'raw' => $raw_str,
        ];
    }

    if (isset($json['status']) && $json['status'] === false) {
        return [
            'ok' => false,
            'http' => $http,
            'error' => trim((string) ($json['message'] ?? 'Data ongkir tidak ditemukan.')),
            'data' => null,
            'raw' => $raw_str,
        ];
    }

    return [
        'ok' => true,
        'http' => $http,
        'error' => '',
        'data' => $json['data'] ?? $json,
        'raw' => $raw_str,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function jne_normalisasi_baris_lokasi(mixed $data): array
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
        $label = trim((string) ($row['label'] ?? ''));
        $rows[] = [
            'id' => $code,
            'label' => $label,
            'zip_code' => '',
            'postal_code' => '',
            'subdistrict_name' => $label,
            'district_name' => '',
            'city_name' => '',
            'province_name' => '',
            'is_courier_support' => true,
        ];
    }

    return $rows;
}

/**
 * Tes koneksi: pencarian asal sample.
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_daftar_provinsi(): array
{
    $res = jne_web_request('/api-origin', ['search' => 'padang']);
    if (!$res['ok']) {
        return $res;
    }
    $rows = jne_normalisasi_baris_lokasi($res['data']);
    return [
        'ok' => true,
        'http' => $res['http'],
        'error' => '',
        'data' => $rows,
        'raw' => $res['raw'],
    ];
}

/**
 * Cari lokasi tujuan (dan gabung asal bila kata kunci cocok).
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
    if ($limit < 1 || $limit > 50) {
        $limit = 20;
    }

    $dest = jne_web_request('/api-destination', ['search' => $kata]);
    $rows = [];
    if ($dest['ok']) {
        $rows = jne_normalisasi_baris_lokasi($dest['data']);
    }

    if (count($rows) < $limit) {
        $orig = jne_web_request('/api-origin', ['search' => $kata]);
        if ($orig['ok']) {
            $seen = [];
            foreach ($rows as $r) {
                $seen[$r['id']] = true;
            }
            foreach (jne_normalisasi_baris_lokasi($orig['data']) as $r) {
                if (isset($seen[$r['id']])) {
                    continue;
                }
                $r['label'] = '[Asal] ' . $r['label'];
                $rows[] = $r;
                if (count($rows) >= $limit) {
                    break;
                }
            }
        }
    }

    if ($rows === [] && !$dest['ok']) {
        return $dest;
    }

    if ($limit < count($rows)) {
        $rows = array_slice($rows, 0, $limit);
    }

    return [
        'ok' => true,
        'http' => $dest['http'] ?? 200,
        'error' => '',
        'data' => $rows,
        'raw' => $dest['raw'] ?? '',
    ];
}

/**
 * @return list<array{code:string, service:string, description:string, cost:int, etd:string}>
 */
function jne_normalisasi_opsi_tarif(mixed $data): array
{
    if (!is_array($data)) {
        return [];
    }
    $price = $data['price'] ?? $data;
    if (!is_array($price)) {
        return [];
    }

    $opsi = [];
    foreach ($price as $row) {
        if (!is_array($row)) {
            continue;
        }
        $biaya = (int) preg_replace('/\D+/', '', (string) ($row['price'] ?? '0'));
        if ($biaya <= 0) {
            continue;
        }
        $svc = trim((string) ($row['service_display'] ?? $row['service_code'] ?? 'REG'));
        $svc_code = trim((string) ($row['service_code'] ?? $svc));
        $etd_from = trim((string) ($row['etd_from'] ?? ''));
        $etd_thru = trim((string) ($row['etd_thru'] ?? ''));
        $times = trim((string) ($row['times'] ?? 'D'));
        $etd = '';
        if ($etd_from !== '' && $etd_thru !== '') {
            $etd = $etd_from . '-' . $etd_thru . ' ' . $times;
        }
        $opsi[] = [
            'code' => 'jne',
            'service' => $svc,
            'description' => trim((string) ($row['goods_type'] ?? 'Paket')) . ' · ' . $svc_code,
            'cost' => $biaya,
            'etd' => $etd,
        ];
    }

    return $opsi;
}

/**
 * Hitung ongkos kirim JNE (berat gram → dikonversi ke kg untuk API).
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
            'error' => 'Kode asal/tujuan JNE wajib format 3 huruf + 5 angka (contoh PDG21100).',
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

    $berat_kg = max(1, (int) ceil($weight_gram / 1000));

    $res = jne_web_request('/api-price', [
        'origin' => $origin_kode,
        'destination' => $dest_kode,
        'weight' => $berat_kg,
    ]);
    if (!$res['ok']) {
        return $res;
    }

    $opsi = jne_normalisasi_opsi_tarif($res['data']);

    $filter = [];
    if ($courier !== '') {
        foreach (explode(':', strtolower($courier)) as $k) {
            $k = trim($k);
            if ($k !== '') {
                $filter[] = $k;
            }
        }
    }
    if ($filter !== []) {
        $filtered = [];
        foreach ($opsi as $o) {
            $svc = strtolower((string) $o['service']);
            foreach ($filter as $f) {
                if ($f === 'jne' || str_contains($svc, $f)) {
                    $filtered[] = $o;
                    break;
                }
            }
        }
        $opsi = $filtered;
    }

    if ($opsi === []) {
        return [
            'ok' => false,
            'http' => $res['http'],
            'error' => 'Tidak ada layanan JNE untuk rute ini.',
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