<?php

declare(strict_types=1);

/**
 * Wrapper RajaOngkir API (versi Komerce/Collaborator).
 *
 * Endpoint dasar: https://rajaongkir.komerce.id/api/v1
 * Header autentikasi: key: <API_KEY>
 *
 * Catatan:
 *   - RajaOngkir kini di-host di Komerce dengan API baru level district
 *     (bukan city seperti API lama).
 *   - Pencarian destinasi dilakukan via search keyword, bukan listing per
 *     province seperti sebelumnya.
 *   - Hitung ongkir mendukung multi-kurir dalam satu panggilan (dipisah
 *     tanda titik dua, mis. "jne:pos:tiki").
 *   - API key dibaca dari pengaturan admin (file JSON gitignored).
 */

require_once __DIR__ . '/../repositori/admin_pengaturan_repositori.php';

const RAJAONGKIR_BASE_URL = 'https://rajaongkir.komerce.id/api/v1';

/**
 * Daftar kurir yang didukung API Komerce RajaOngkir.
 * @return array<string,string>
 */
function rajaongkir_kurir_didukung(): array
{
    return [
        'jne' => 'JNE',
        'pos' => 'POS Indonesia',
        'tiki' => 'TIKI',
        'jnt' => 'J&T Express',
        'sicepat' => 'SiCepat',
        'ide' => 'ID Express',
        'sap' => 'SAP Express',
        'ninja' => 'Ninja Xpress',
        'anteraja' => 'AnterAja',
        'lion' => 'Lion Parcel',
        'ncs' => 'NCS',
        'rex' => 'REX',
        'rpx' => 'RPX',
        'wahana' => 'Wahana',
        'sentral' => 'Sentral Cargo',
        'star' => 'Star Cargo',
        'dse' => 'DSE Logistic',
    ];
}

function rajaongkir_api_key(): string
{
    $cfg = admin_pengaturan_muat_terapan();
    return trim((string) ($cfg['rajaongkir_api_key'] ?? ''));
}

function rajaongkir_kota_asal_id(): int
{
    $cfg = admin_pengaturan_muat_terapan();
    return (int) ($cfg['rajaongkir_kota_asal_id'] ?? 0);
}

/**
 * Panggil endpoint RajaOngkir (Komerce).
 *
 * Parsing respons mendukung dua format:
 *   - Baru (Komerce): { "meta": { "code": 200, "status": "success", "message": "..." }, "data": [...] }
 *   - Lama (legacy fallback): { "rajaongkir": { "status": {...}, "results": [...] } }
 *
 * @param 'GET'|'POST' $metode
 * @param string $path Path relatif (mis. '/destination/province')
 * @param array<string,scalar> $body Query string untuk GET, form body untuk POST
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_request(string $metode, string $path, array $body = []): array
{
    $key = rajaongkir_api_key();
    if ($key === '') {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'API Key RajaOngkir belum diisi. Buka Pengaturan admin → Integrasi RajaOngkir.',
            'data' => null,
            'raw' => '',
        ];
    }

    $url = RAJAONGKIR_BASE_URL . $path;
    $headers = ['key: ' . $key, 'Accept: application/json'];

    $metode = strtoupper($metode);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    if ($metode === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
    } else {
        if ($body !== []) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($body);
        }
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
    curl_close($ch);

    $raw_str = is_string($raw) ? $raw : '';

    if ($raw === false) {
        return [
            'ok' => false,
            'http' => $http,
            'error' => $err_curl !== '' ? $err_curl : 'Tidak ada respons dari RajaOngkir.',
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

    // Format baru (Komerce): {"meta":{"code":200,...},"data":[...]}
    if (isset($json['meta']) && is_array($json['meta'])) {
        $meta = $json['meta'];
        $code = (int) ($meta['code'] ?? 0);
        $msg = (string) ($meta['message'] ?? $meta['status'] ?? '');
        if ($code !== 200) {
            return [
                'ok' => false,
                'http' => $http,
                'error' => $msg !== '' ? $msg : ('Kode RajaOngkir: ' . $code),
                'data' => null,
                'raw' => $raw_str,
            ];
        }
        return [
            'ok' => true,
            'http' => $http,
            'error' => '',
            'data' => $json['data'] ?? null,
            'raw' => $raw_str,
        ];
    }

    // Format lama (fallback): {"rajaongkir":{"status":{"code":200},"results":...}}
    if (isset($json['rajaongkir']) && is_array($json['rajaongkir'])) {
        $ro = $json['rajaongkir'];
        $st = is_array($ro['status'] ?? null) ? $ro['status'] : [];
        $code = (int) ($st['code'] ?? 0);
        $desk = (string) ($st['description'] ?? '');
        if ($code !== 200) {
            return [
                'ok' => false,
                'http' => $http,
                'error' => $desk !== '' ? $desk : ('Kode RajaOngkir: ' . $code),
                'data' => null,
                'raw' => $raw_str,
            ];
        }
        return [
            'ok' => true,
            'http' => $http,
            'error' => '',
            'data' => $ro['results'] ?? null,
            'raw' => $raw_str,
        ];
    }

    return [
        'ok' => false,
        'http' => $http,
        'error' => 'Format respons tidak dikenali. HTTP ' . $http . '. Cek raw response untuk detail.',
        'data' => null,
        'raw' => $raw_str,
    ];
}

/**
 * Ambil daftar semua provinsi (untuk validasi koneksi & info umum).
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_daftar_provinsi(): array
{
    return rajaongkir_request('GET', '/destination/province');
}

/**
 * Cari destinasi domestik berdasar kata kunci. Hasil sampai level subdistrict/district.
 * Endpoint baru menggantikan listing per province di API lama.
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
    if ($offset < 0) {
        $offset = 0;
    }
    return rajaongkir_request('GET', '/destination/domestic-destination', [
        'search' => $kata,
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

/**
 * Hitung ongkos kirim domestik level district.
 *
 * $courier dapat berisi satu kurir ("jne") atau beberapa dipisah titik dua ("jne:pos:tiki").
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_cek_ongkir(int $origin_id, int $destination_id, int $weight_gram, string $courier = 'jne'): array
{
    $courier = strtolower(trim($courier));
    if ($courier === '') {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'Kurir wajib diisi (mis. "jne" atau "jne:pos:tiki").',
            'data' => null,
            'raw' => '',
        ];
    }

    $kurir_valid = rajaongkir_kurir_didukung();
    foreach (explode(':', $courier) as $k) {
        $k = trim($k);
        if ($k !== '' && !array_key_exists($k, $kurir_valid)) {
            return [
                'ok' => false,
                'http' => 0,
                'error' => 'Kurir "' . $k . '" tidak ada di daftar yang didukung.',
                'data' => null,
                'raw' => '',
            ];
        }
    }

    if ($origin_id <= 0 || $destination_id <= 0) {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'origin_id dan destination_id wajib > 0.',
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

    // Endpoint search-based: terima ID dari /destination/domestic-destination apa adanya
    // (level kecamatan/kelurahan, tanpa konversi). Berbeda dari /calculate/district/...
    // yang dipakai oleh alur lama province → city → district.
    return rajaongkir_request('POST', '/calculate/domestic-cost', [
        'origin' => (string) $origin_id,
        'destination' => (string) $destination_id,
        'weight' => $weight_gram,
        'courier' => $courier,
        'price' => 'lowest',
    ]);
}
