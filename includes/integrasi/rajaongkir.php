<?php

declare(strict_types=1);

/**
 * Integrasi ongkir RajaOngkir (platform Komerce) — API resmi berbayar/gratis tier.
 *
 * Base URL: https://rajaongkir.komerce.id/api/v1
 *   - Cari lokasi: GET /destination/domestic-destination?search=...&limit=...&offset=...
 *   - Tarif:       POST /calculate/domestic-cost  (origin, destination, weight, courier)
 *
 * Autentikasi: header  key: <RAJAONGKIR_API_KEY>
 *
 * ID lokasi RajaOngkir Komerce = ID kelurahan/subdistrict (angka, mis. 48850), BUKAN
 * kode cabang JNE lama (PDG21100). Berat dikirim dalam gram.
 *
 * Nama fungsi rajaongkir_* dipertahankan agar checkout/admin tetap kompatibel.
 */

require_once __DIR__ . '/../config_loader.php';
require_once __DIR__ . '/../repositori/admin_pengaturan_repositori.php';
require_once __DIR__ . '/jne_destinasi_populer.php';

const RAJAONGKIR_BASE_URL = 'https://rajaongkir.komerce.id/api/v1';

/** Daftar kurir yang diminta ke RajaOngkir (dipisah titik dua). */
const RAJAONGKIR_KURIR_DEFAULT = 'jne:jnt:sicepat:pos:tiki:anteraja:ninja:wahana:lion';

/**
 * Ambil API key RajaOngkir dengan prioritas:
 * env/konstanta RAJAONGKIR_API_KEY  →  pengaturan admin (rajaongkir_api_key).
 */
function rajaongkir_api_key(): string
{
    if (defined('RAJAONGKIR_API_KEY')) {
        $k = trim((string) RAJAONGKIR_API_KEY);
        if ($k !== '') {
            return $k;
        }
    }
    $cfg = admin_pengaturan_muat_terapan();

    return trim((string) ($cfg['rajaongkir_api_key'] ?? ''));
}

/**
 * @return array<string, string>
 */
function rajaongkir_kurir_didukung(): array
{
    return [
        'jne' => 'JNE',
        'jnt' => 'J&T Express',
        'sicepat' => 'SiCepat',
        'pos' => 'POS Indonesia',
        'tiki' => 'TIKI',
        'anteraja' => 'AnterAja',
        'ninja' => 'Ninja Xpress',
        'wahana' => 'Wahana',
        'lion' => 'Lion Parcel',
    ];
}

/** Pesan error RajaOngkir yang lebih mudah dipahami pembeli. */
function rajaongkir_error_pesan_ramah(int $http, string $teknis = ''): string
{
    if ($http === 401 || $http === 403) {
        return 'API key RajaOngkir belum benar atau tidak punya akses. Hubungi admin.';
    }
    if ($http === 429) {
        return 'Terlalu banyak permintaan ongkir. Tunggu sebentar lalu coba lagi.';
    }
    if ($http >= 500) {
        return 'Layanan ongkir RajaOngkir sedang gangguan. Coba lagi beberapa menit.';
    }
    if ($teknis !== '') {
        return $teknis;
    }

    return 'Gagal menghubungi layanan ongkir RajaOngkir. Coba lagi.';
}

/**
 * Normalisasi ID lokasi RajaOngkir (hanya digit, mis. "48850").
 */
function rajaongkir_normalisasi_kode_desa(string|int $nilai): string
{
    $s = preg_replace('/\D+/', '', (string) $nilai) ?? '';

    return $s !== '' ? $s : '';
}

/** ID lokasi asal pengiriman toko (numerik RajaOngkir). */
function rajaongkir_asal_kode(): string
{
    $cfg = admin_pengaturan_muat_terapan();
    $kode = rajaongkir_normalisasi_kode_desa((string) ($cfg['rajaongkir_kota_asal_kode'] ?? ''));
    if ($kode !== '') {
        return $kode;
    }
    if (defined('RAJAONGKIR_ORIGIN_ID')) {
        return rajaongkir_normalisasi_kode_desa((string) RAJAONGKIR_ORIGIN_ID);
    }

    return '';
}

function rajaongkir_kota_asal_id(): int
{
    return (int) rajaongkir_asal_kode();
}
/**
 * Eksekusi request ke RajaOngkir Komerce.
 *
 * @param array<string, scalar> $query   Query string (GET) atau field body (POST).
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_request(string $path, string $method = 'GET', array $query = []): array
{
    $key = rajaongkir_api_key();
    if ($key === '') {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'API key RajaOngkir belum diatur. Isi di Pengaturan toko atau env RAJAONGKIR_API_KEY.',
            'data' => null,
            'raw' => '',
        ];
    }

    $method = strtoupper($method) === 'POST' ? 'POST' : 'GET';
    $url = RAJAONGKIR_BASE_URL . $path;

    $headers = [
        'key: ' . $key,
        'Accept: application/json',
    ];

    $ch = curl_init();
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_ENCODING => '',
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($query);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    } else {
        if ($query !== []) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($query);
        }
    }

    $opts[CURLOPT_URL] = $url;
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);

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
            'error' => rajaongkir_error_pesan_ramah($http, 'Respons RajaOngkir bukan JSON valid (HTTP ' . $http . ').'),
            'data' => null,
            'raw' => $raw_str,
        ];
    }

    $meta = is_array($json['meta'] ?? null) ? $json['meta'] : [];
    $kode_meta = (int) ($meta['code'] ?? $http);
    $status_meta = strtolower((string) ($meta['status'] ?? ''));
    $pesan_meta = trim((string) ($meta['message'] ?? ''));

    if ($http >= 400 || $kode_meta >= 400 || ($status_meta !== '' && $status_meta !== 'success')) {
        // 404 "data not found" pada pencarian bukan error fatal — kembalikan data kosong.
        $http_efektif = $http >= 400 ? $http : $kode_meta;

        return [
            'ok' => false,
            'http' => $http_efektif,
            'error' => rajaongkir_error_pesan_ramah($http_efektif, $pesan_meta !== '' ? $pesan_meta : 'Permintaan RajaOngkir ditolak.'),
            'data' => $json['data'] ?? null,
            'raw' => $raw_str,
        ];
    }

    return [
        'ok' => true,
        'http' => $http,
        'error' => '',
        'data' => $json['data'] ?? [],
        'raw' => $raw_str,
    ];
}

/**
 * Normalisasi satu baris lokasi dari RajaOngkir ke bentuk internal aplikasi.
 *
 * @return list<array<string, mixed>>
 */
function rajaongkir_normalisasi_baris_lokasi(mixed $data): array
{
    $rows = [];
    if (!is_array($data)) {
        return $rows;
    }
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = rajaongkir_normalisasi_kode_desa((string) ($row['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        $subdistrict = trim((string) ($row['subdistrict_name'] ?? ''));
        $district = trim((string) ($row['district_name'] ?? ''));
        $city = trim((string) ($row['city_name'] ?? ''));
        $province = trim((string) ($row['province_name'] ?? ''));
        $zip = trim((string) ($row['zip_code'] ?? ''));

        if ($label === '') {
            $label = implode(', ', array_filter([$subdistrict, $district, $city, $province, $zip]));
        }

        $rows[] = [
            'id' => $id,
            'label' => $label,
            'label_tampilan' => rajaongkir_label_tampilan($label),
            'zip_code' => $zip,
            'postal_code' => $zip,
            'subdistrict_name' => $subdistrict,
            'district_name' => $district,
            'city_name' => $city,
            'province_name' => $province,
            'is_courier_support' => true,
        ];
    }

    return $rows;
}

/**
 * Cari lokasi (kelurahan/kecamatan/kota) di RajaOngkir.
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_cari_lokasi(string $kata, int $limit = 20, int $offset = 0): array
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

    $res = rajaongkir_request('/destination/domestic-destination', 'GET', [
        'search' => $kata,
        'limit' => $limit,
        'offset' => $offset,
    ]);

    if (!$res['ok']) {
        return $res;
    }

    $rows = rajaongkir_normalisasi_baris_lokasi($res['data']);

    return [
        'ok' => true,
        'http' => $res['http'],
        'error' => '',
        'data' => $rows,
        'raw' => $res['raw'],
    ];
}

/**
 * Cari destinasi: API RajaOngkir dulu, lalu daftar populer jika gagal.
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string, fallback?:bool}
 */
function jne_cari_destinasi_dengan_fallback(string $kata, int $limit = 20): array
{
    $live = rajaongkir_cari_destinasi_tujuan($kata, $limit);
    if ($live['ok'] && is_array($live['data']) && $live['data'] !== []) {
        return $live;
    }

    $fb = jne_destinasi_cari_fallback($kata, $limit);
    if ($fb['ok']) {
        $fb['error'] = '';
        $fb['http'] = ($live['http'] ?? 0) > 0 ? $live['http'] : 200;

        return $fb;
    }

    if ($live['ok']) {
        return $live;
    }

    $live['error'] = rajaongkir_error_pesan_ramah((int) ($live['http'] ?? 0), (string) ($live['error'] ?? ''));

    return $live;
}

/**
 * Tes koneksi: pencarian lokasi contoh.
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_daftar_provinsi(): array
{
    return rajaongkir_cari_lokasi('padang', 10);
}

/**
 * Label RajaOngkir (CAPS) → tampilan ramah pembeli (Title Case, tetap ada konteks kota).
 */
function rajaongkir_label_tampilan(string $label): string
{
    $label = trim(preg_replace('/^\[Asal\]\s*/i', '', $label) ?? $label);
    if ($label === '') {
        return '';
    }

    return mb_convert_case(mb_strtolower($label, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

/**
 * Normalisasi teks untuk pencocokan kota/kecamatan (tanpa spasi & tanda baca).
 */
function rajaongkir_normalisasi_teks_cocok(string $teks): string
{
    $teks = mb_strtolower(trim($teks), 'UTF-8');
    $teks = preg_replace('/^(kota|kabupaten|kab\.?)\s+/u', '', $teks) ?? $teks;

    return preg_replace('/[^a-z0-9]+/u', '', $teks) ?? '';
}

/**
 * Skor kecocokan baris RajaOngkir dengan alamat profil (semakin tinggi = semakin cocok).
 * Memanfaatkan field terstruktur (subdistrict/district/city/province) bila ada,
 * jatuh ke label gabungan bila kosong.
 */
function rajaongkir_skor_cocok_lokasi(array $row, array $profil): int
{
    $kec = rajaongkir_normalisasi_teks_cocok((string) ($profil['kecamatan'] ?? ''));
    $kota = rajaongkir_normalisasi_teks_cocok((string) ($profil['kota'] ?? ''));
    $prov = rajaongkir_normalisasi_teks_cocok((string) ($profil['provinsi'] ?? ''));

    $row_kec = rajaongkir_normalisasi_teks_cocok((string) ($row['district_name'] ?? ''));
    $row_sub = rajaongkir_normalisasi_teks_cocok((string) ($row['subdistrict_name'] ?? ''));
    $row_kota = rajaongkir_normalisasi_teks_cocok((string) ($row['city_name'] ?? ''));
    $row_prov = rajaongkir_normalisasi_teks_cocok((string) ($row['province_name'] ?? ''));
    $label = rajaongkir_normalisasi_teks_cocok((string) ($row['label'] ?? ''));

    if ($row_kec === '' && $row_kota === '' && $label === '') {
        return 0;
    }

    $skor = 0;

    if ($kec !== '') {
        if ($row_kec === $kec || $row_sub === $kec) {
            $skor += 80;
        } elseif ($label !== '' && (str_contains($label, $kec) || str_contains($kec, $label))) {
            $skor += 50;
        } elseif ($row_kec !== '' && (str_contains($row_kec, $kec) || str_contains($kec, $row_kec))) {
            $skor += 45;
        }
    }
    if ($kota !== '') {
        if ($row_kota === $kota) {
            $skor += 70;
        } elseif ($row_kota !== '' && (str_contains($row_kota, $kota) || str_contains($kota, $row_kota))) {
            $skor += 45;
        } elseif ($label !== '' && str_contains($label, $kota)) {
            $skor += 30;
        }
    }
    if ($prov !== '') {
        if ($row_prov === $prov) {
            $skor += 12;
        } elseif ($row_prov !== '' && (str_contains($row_prov, $prov) || str_contains($prov, $row_prov))) {
            $skor += 8;
        } elseif ($label !== '' && str_contains($label, $prov)) {
            $skor += 6;
        }
    }

    return $skor;
}

/**
 * Pilih satu destinasi terbaik dari hasil pencarian + profil (null = pembeli pilih manual).
 *
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>|null
 */
function rajaongkir_pilih_destinasi_terbaik(array $rows, array $profil): ?array
{
    if ($rows === []) {
        return null;
    }

    $terbaik = null;
    $skor_terbaik = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $skor = rajaongkir_skor_cocok_lokasi($row, $profil);
        if ($skor > $skor_terbaik) {
            $skor_terbaik = $skor;
            $terbaik = $row;
        }
    }

    if ($skor_terbaik >= 120 && $terbaik !== null) {
        return $terbaik;
    }
    if (count($rows) === 1) {
        return $rows[0];
    }

    return null;
}

/**
 * Urutkan hasil: yang paling cocok dengan profil di atas.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function rajaongkir_urutkan_hasil_profil(array $rows, array $profil): array
{
    usort($rows, static function (array $a, array $b) use ($profil): int {
        return rajaongkir_skor_cocok_lokasi($b, $profil) <=> rajaongkir_skor_cocok_lokasi($a, $profil);
    });

    return $rows;
}

/**
 * Cari tujuan pengiriman saja — untuk checkout pembeli.
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_cari_destinasi_tujuan(string $kata, int $limit = 20): array
{
    return rajaongkir_cari_lokasi($kata, $limit);
}

/**
 * Cari beberapa kata kunci dari profil (kecamatan, kota) lalu gabungkan hasil unik.
 *
 * @param array{kecamatan?:string,kota?:string,provinsi?:string} $profil
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_cari_untuk_profil(array $profil, int $limit = 30): array
{
    $terms = [];
    foreach (['kecamatan', 'kota'] as $k) {
        $t = trim((string) ($profil[$k] ?? ''));
        if ($t === '') {
            continue;
        }
        if (!in_array($t, $terms, true)) {
            $terms[] = $t;
        }
        $tanpa_prefiks = trim((string) (preg_replace('/^(kota|kabupaten|kab\.?)\s+/iu', '', $t) ?? $t));
        if ($tanpa_prefiks !== '' && !in_array($tanpa_prefiks, $terms, true)) {
            $terms[] = $tanpa_prefiks;
        }
    }

    if ($terms === []) {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'Alamat profil belum memiliki kota/kecamatan.',
            'data' => null,
            'raw' => '',
        ];
    }

    $rows = [];
    $seen = [];
    $raw = '';
    $http = 200;
    $err = '';

    $kata_cari = [];
    foreach ($terms as $term) {
        $kata_cari[] = $term;
        $kata_utama = trim((string) (preg_split('/\s+/u', $term)[0] ?? $term));
        if ($kata_utama !== '' && $kata_utama !== $term && !in_array($kata_utama, $kata_cari, true)) {
            $kata_cari[] = $kata_utama;
        }
    }

    foreach ($kata_cari as $term) {
        $res = jne_cari_destinasi_dengan_fallback($term, $limit);
        $raw = ($res['raw'] ?? '') !== '' ? $res['raw'] : $raw;
        $http = $res['http'] ?? $http;
        if (!$res['ok']) {
            $err = (string) ($res['error'] ?? $err);
            continue;
        }
        foreach ((array) $res['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $rows[] = $row;
        }
    }

    if ($rows === []) {
        return [
            'ok' => false,
            'http' => $http,
            'error' => $err !== '' ? $err : 'Wilayah pengiriman tidak ditemukan. Coba ketik nama kota di kolom pencarian.',
            'data' => null,
            'raw' => $raw,
        ];
    }

    $rows = rajaongkir_urutkan_hasil_profil($rows, $profil);

    return [
        'ok' => true,
        'http' => $http,
        'error' => '',
        'data' => $rows,
        'raw' => $raw,
    ];
}

/**
 * Label tampilan untuk baris hasil (fallback bila field belum ada).
 */
function rajaongkir_baris_label_tampilan(array $row): string
{
    $tampilan = trim((string) ($row['label_tampilan'] ?? ''));
    if ($tampilan !== '') {
        return $tampilan;
    }

    return rajaongkir_label_tampilan((string) ($row['label'] ?? ''));
}

/**
 * Cari lokasi tujuan — untuk admin (tester). Alias pencarian biasa.
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_cari_destinasi(string $kata, int $limit = 20, int $offset = 0): array
{
    return rajaongkir_cari_lokasi($kata, $limit, $offset);
}

/**
 * Normalisasi data tarif dari RajaOngkir Komerce ke opsi internal.
 * Format respons: data[] = { name, code, service, description, cost, etd }.
 *
 * @return list<array{code:string, service:string, description:string, cost:int, etd:string}>
 */
function jne_normalisasi_opsi_tarif(mixed $data): array
{
    if (!is_array($data)) {
        return [];
    }

    $opsi = [];
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $biaya = (int) preg_replace('/\D+/', '', (string) ($row['cost'] ?? '0'));
        if ($biaya <= 0) {
            continue;
        }
        $kode = strtolower(trim((string) ($row['code'] ?? '')));
        if ($kode === '') {
            $kode = 'kurir';
        }
        $svc = trim((string) ($row['service'] ?? 'REG'));
        $deskripsi = trim((string) ($row['description'] ?? ''));
        $nama = trim((string) ($row['name'] ?? ''));
        $etd = trim((string) ($row['etd'] ?? ''));
        if ($etd === '-' ) {
            $etd = '';
        }

        $opsi[] = [
            'code' => $kode,
            'service' => $svc,
            'description' => $deskripsi !== '' ? $deskripsi : $nama,
            'cost' => $biaya,
            'etd' => $etd,
        ];
    }

    return $opsi;
}

/**
 * Hitung ongkos kirim via RajaOngkir Komerce (berat dalam gram).
 *
 * @param string $courier  Kosong = pakai daftar kurir default. Bisa "jne", "jne:tiki", dll.
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_cek_ongkir(string|int $origin, string|int $destination, int $weight_gram, string $courier = ''): array
{
    $origin_id = rajaongkir_normalisasi_kode_desa($origin);
    $dest_id = rajaongkir_normalisasi_kode_desa($destination);

    if ($origin_id === '' || $dest_id === '') {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'ID lokasi asal/tujuan RajaOngkir wajib berupa angka (mis. 48850).',
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

    // RajaOngkir minta berat gram, minimal 1.
    $berat = max(1, $weight_gram);

    // Tentukan daftar kurir yang diminta.
    $courier = strtolower(trim($courier));
    $courier_list = [];
    if ($courier !== '') {
        foreach (explode(':', $courier) as $c) {
            $c = trim($c);
            if ($c !== '' && isset(rajaongkir_kurir_didukung()[$c]) && !in_array($c, $courier_list, true)) {
                $courier_list[] = $c;
            }
        }
    }
    if ($courier_list === []) {
        $courier_param = RAJAONGKIR_KURIR_DEFAULT;
    } else {
        $courier_param = implode(':', $courier_list);
    }

    $res = rajaongkir_request('/calculate/domestic-cost', 'POST', [
        'origin' => $origin_id,
        'destination' => $dest_id,
        'weight' => $berat,
        'courier' => $courier_param,
    ]);

    if (!$res['ok']) {
        return $res;
    }

    $opsi = jne_normalisasi_opsi_tarif($res['data']);

    // Urutkan termurah dulu agar pembeli lebih mudah.
    usort($opsi, static fn (array $a, array $b): int => $a['cost'] <=> $b['cost']);

    if ($opsi === []) {
        return [
            'ok' => false,
            'http' => $res['http'],
            'error' => 'Tidak ada layanan kurir untuk rute ini.',
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
