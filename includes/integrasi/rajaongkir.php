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
require_once __DIR__ . '/jne_destinasi_populer.php';

const JNE_WEB_BASE_URL = 'https://jne.co.id';

/** @return string path file cookie sementara untuk sesi JNE */
function jne_web_cookie_file(): string
{
    static $path = null;
    if ($path === null) {
        $tmp = tempnam(sys_get_temp_dir(), 'jne_ck_');
        $path = is_string($tmp) ? $tmp : '';
    }

    return $path;
}

/** Ambil cookie sesi dari halaman ongkir JNE agar API tidak menolak (403). */
function jne_web_warmup_session(bool $paksa = false): void
{
    static $sudah = false;
    if ($sudah && !$paksa) {
        return;
    }
    $sudah = true;

    $cookie = jne_web_cookie_file();
    if ($cookie === '') {
        return;
    }

    $ch = curl_init(JNE_WEB_BASE_URL . '/ongkir');
    if ($ch === false) {
        return;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ],
    ]);

    $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($ca && is_readable($ca)) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    curl_exec($ch);
    curl_close($ch);
}

/** Pesan error JNE yang lebih mudah dipahami pembeli. */
function jne_error_pesan_ramah(int $http, string $teknis = ''): string
{
    if ($http === 403) {
        return 'Server pengiriman menolak koneksi sementara. Gunakan daftar cadangan di bawah atau coba lagi.';
    }
    if ($http === 429) {
        return 'Terlalu banyak pencarian. Tunggu sebentar lalu coba lagi.';
    }
    if ($http >= 500) {
        return 'Layanan ongkir sedang gangguan. Coba lagi beberapa menit.';
    }
    if ($teknis !== '') {
        return $teknis;
    }

    return 'Gagal menghubungi layanan ongkir. Coba lagi.';
}

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
function jne_web_request(string $path, array $query = [], bool $ulang_setelah_warmup = true): array
{
    jne_web_warmup_session();

    $url = JNE_WEB_BASE_URL . $path;
    if ($query !== []) {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($query);
    }

    $cookie = jne_web_cookie_file();
    $headers = [
        'Accept: application/json, text/plain, */*',
        'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
        'Referer: ' . JNE_WEB_BASE_URL . '/ongkir',
        'Origin: ' . JNE_WEB_BASE_URL,
        'X-Requested-With: XMLHttpRequest',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ];

    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($cookie !== '') {
        $opts[CURLOPT_COOKIEJAR] = $cookie;
        $opts[CURLOPT_COOKIEFILE] = $cookie;
    }
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
    curl_close($ch);
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
        if ($ulang_setelah_warmup && ($http === 403 || $http === 401)) {
            $ck = jne_web_cookie_file();
            if ($ck !== '' && is_file($ck)) {
                @unlink($ck);
            }
            jne_web_warmup_session(true);

            return jne_web_request($path, $query, false);
        }

        return [
            'ok' => false,
            'http' => $http,
            'error' => jne_error_pesan_ramah($http, 'Respons bukan JSON valid (HTTP ' . $http . ').'),
            'data' => null,
            'raw' => $raw_str,
        ];
    }

    if ($http >= 400) {
        if ($ulang_setelah_warmup && ($http === 403 || $http === 401)) {
            return jne_web_request($path, $query, false);
        }

        return [
            'ok' => false,
            'http' => $http,
            'error' => jne_error_pesan_ramah($http, trim((string) ($json['message'] ?? 'Permintaan ditolak jne.co.id.'))),
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
 * Cari destinasi: API JNE dulu, lalu daftar populer jika gagal.
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string, fallback?:bool}
 */
function jne_cari_destinasi_dengan_fallback(string $kata, int $limit = 20): array
{
    $live = rajaongkir_cari_destinasi_tujuan($kata, $limit);
    if ($live['ok']) {
        return $live;
    }

    $fb = jne_destinasi_cari_fallback($kata, $limit);
    if ($fb['ok']) {
        $fb['error'] = '';
        $fb['http'] = $live['http'] > 0 ? $live['http'] : 200;

        return $fb;
    }

    $live['error'] = jne_error_pesan_ramah((int) ($live['http'] ?? 0), (string) ($live['error'] ?? ''));

    return $live;
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
 * Label API JNE (CAPS) → tampilan ramah pembeli.
 */
function rajaongkir_label_tampilan(string $label): string
{
    $label = trim(preg_replace('/^\[Asal\]\s*/i', '', $label));
    if ($label === '') {
        return '';
    }

    $tanpa_spasi = strtoupper(preg_replace('/\s+/', '', $label) ?? '');
    $khusus = [
        'PADANGPANJANG' => 'Padang Panjang',
        'JAKARTAPUSAT' => 'Jakarta Pusat',
        'JAKARTAUTARA' => 'Jakarta Utara',
        'JAKARTASELATAN' => 'Jakarta Selatan',
        'JAKARTATIMUR' => 'Jakarta Timur',
        'JAKARTABARAT' => 'Jakarta Barat',
    ];
    if (isset($khusus[$tanpa_spasi])) {
        return $khusus[$tanpa_spasi];
    }

    if (str_contains($label, ' ')) {
        return mb_convert_case(mb_strtolower($label, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
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
 * Skor kecocokan baris JNE dengan alamat profil (semakin tinggi = semakin cocok).
 */
function rajaongkir_skor_cocok_lokasi(array $row, array $profil): int
{
    $label = rajaongkir_normalisasi_teks_cocok((string) ($row['label'] ?? ''));
    if ($label === '') {
        return 0;
    }

    $kec = rajaongkir_normalisasi_teks_cocok((string) ($profil['kecamatan'] ?? ''));
    $kota = rajaongkir_normalisasi_teks_cocok((string) ($profil['kota'] ?? ''));
    $prov = rajaongkir_normalisasi_teks_cocok((string) ($profil['provinsi'] ?? ''));
    $skor = 0;

    if ($kec !== '') {
        if ($label === $kec) {
            $skor += 80;
        } elseif (str_contains($label, $kec) || str_contains($kec, $label)) {
            $skor += 50;
        }
    }
    if ($kota !== '') {
        if ($label === $kota) {
            $skor += 70;
        } elseif (str_contains($label, $kota) || str_contains($kota, $label)) {
            $skor += 45;
        }
    }
    if ($prov !== '' && str_contains($label, $prov)) {
        $skor += 10;
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

    if ($skor_terbaik >= 45 && $terbaik !== null) {
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
 * Cari tujuan pengiriman saja (tanpa lokasi asal) — untuk checkout pembeli.
 *
 * @return array{ok:bool, http:int, error:string, data:mixed, raw:string}
 */
function rajaongkir_cari_destinasi_tujuan(string $kata, int $limit = 20): array
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
    if (!$dest['ok']) {
        return $dest;
    }
    $rows = jne_normalisasi_baris_lokasi($dest['data']);
    if ($limit < count($rows)) {
        $rows = array_slice($rows, 0, $limit);
    }

    return [
        'ok' => true,
        'http' => $dest['http'],
        'error' => '',
        'data' => $rows,
        'raw' => $dest['raw'],
    ];
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
        $raw = $res['raw'] !== '' ? $res['raw'] : $raw;
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
 * Cari lokasi tujuan (dan gabung asal bila kata kunci cocok) — untuk admin.
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