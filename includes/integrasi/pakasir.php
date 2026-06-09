<?php

declare(strict_types=1);

/**
 * Integrasi payment gateway Pakasir (app.pakasir.com).
 * QRIS, Virtual Account multi-bank, PayPal.
 */

require_once __DIR__ . '/../repositori/admin_pengaturan_repositori.php';
require_once __DIR__ . '/../url_bantu.php';

const PAKASIR_API_BASE = 'https://app.pakasir.com/api';
const PAKASIR_WEB_BASE = 'https://app.pakasir.com';

function pakasir_env_nilai(string ...$nama): string
{
    foreach ($nama as $n) {
        $v = getenv($n);
        if (is_string($v) && trim($v) !== '') {
            return trim($v);
        }
        if (isset($_ENV[$n]) && is_string($_ENV[$n]) && trim($_ENV[$n]) !== '') {
            return trim($_ENV[$n]);
        }
        if (isset($_SERVER[$n]) && is_string($_SERVER[$n]) && trim($_SERVER[$n]) !== '') {
            return trim($_SERVER[$n]);
        }
        if (defined($n)) {
            $konst = constant($n);
            if (is_string($konst) && trim($konst) !== '') {
                return trim($konst);
            }
        }
    }

    return '';
}

/**
 * @return array<string, string>
 */
function pakasir_daftar_metode(): array
{
    return [
        'all' => 'Semua metode (pilih di halaman Pakasir)',
        'qris' => 'QRIS',
        'bni_va' => 'BNI Virtual Account',
        'bri_va' => 'BRI Virtual Account',
        'cimb_niaga_va' => 'CIMB Niaga VA',
        'maybank_va' => 'Maybank VA',
        'permata_va' => 'Permata VA',
        'bnc_va' => 'BNC VA',
        'atm_bersama_va' => 'ATM Bersama VA',
        'sampoerna_va' => 'Sampoerna VA',
        'artha_graha_va' => 'Artha Graha VA',
        'paypal' => 'PayPal',
    ];
}

/**
 * @return array{mode:string, project_slug:string, api_key:string, metode_default:string}
 */
function pakasir_konfigurasi(): array
{
    $cfg = admin_pengaturan_muat_terapan();

    $env_slug = pakasir_env_nilai('PAKASIR_PROJECT_SLUG', 'PAKASIR_PROJECT');
    $env_key = pakasir_env_nilai('PAKASIR_API_KEY');
    $env_mode = strtolower(pakasir_env_nilai('PAKASIR_MODE'));
    $env_metode = strtolower(pakasir_env_nilai('PAKASIR_METODE_DEFAULT'));

    $mode = $env_mode !== '' ? $env_mode : strtolower(trim((string) ($cfg['pakasir_mode'] ?? 'sandbox')));
    if (!in_array($mode, ['sandbox', 'production'], true)) {
        $mode = 'sandbox';
    }
    $metode = $env_metode !== '' ? $env_metode : strtolower(trim((string) ($cfg['pakasir_metode_default'] ?? 'qris')));
    if (!array_key_exists($metode, pakasir_daftar_metode())) {
        $metode = 'qris';
    }

    $project_slug = $env_slug !== '' ? $env_slug : trim((string) ($cfg['pakasir_project_slug'] ?? ''));
    $api_key = $env_key !== '' ? $env_key : trim((string) ($cfg['pakasir_api_key'] ?? ''));

    return [
        'mode' => $mode,
        'project_slug' => $project_slug,
        'api_key' => $api_key,
        'metode_default' => $metode,
    ];
}

function pakasir_siap(): bool
{
    $k = pakasir_konfigurasi();

    return $k['project_slug'] !== '' && $k['api_key'] !== '';
}

function pakasir_sanitize_order_id(string $order_id): string
{
    return preg_replace('/[^\w\-_.~0-9]/', '', $order_id) ?? '';
}

function pakasir_order_id_dari_db(int $order_id): string
{
    if ($order_id <= 0) {
        return '';
    }

    return pakasir_sanitize_order_id('EAS' . str_pad((string) $order_id, 7, '0', STR_PAD_LEFT));
}

function pakasir_parse_order_id_db(string $external): int
{
    $external = pakasir_sanitize_order_id($external);
    if (preg_match('/^EAS(\d{1,10})$/i', $external, $m)) {
        return (int) $m[1];
    }

    return 0;
}

function pakasir_label_metode(string $kode): string
{
    $kode = strtolower(trim($kode));
    $daftar = pakasir_daftar_metode();

    return $daftar[$kode] ?? strtoupper($kode);
}

function pakasir_url_webhook(): string
{
    return aplikasi_url('api/payment_callback.php');
}

function pakasir_url_redirect_detail(int $order_id): string
{
    return aplikasi_url('detail-pesanan?id=' . $order_id . '&bayar=kembali');
}

/**
 * @return array{fee:int, total_payment:int, payment_url:string}
 */
function pakasir_hitung_url_pembayaran(string $metode, string $project_slug, string $order_id, int $amount, ?string $redirect_url): array
{
    $metode = strtolower(trim($metode));
    if (!array_key_exists($metode, pakasir_daftar_metode())) {
        $metode = 'all';
    }
    $order_id = pakasir_sanitize_order_id($order_id);
    if (strlen($order_id) < 5) {
        throw new InvalidArgumentException('Order ID Pakasir minimal 5 karakter.');
    }
    if ($amount < 500) {
        throw new InvalidArgumentException('Nominal minimal Rp500.');
    }

    $fee = 0;
    $redirect_q = $redirect_url !== null && $redirect_url !== '' ? '&redirect=' . rawurlencode($redirect_url) : '';

    if ($metode === 'qris') {
        $fee = $amount > 105000 ? (int) round(0.01 * $amount) : (int) round(0.007 * $amount + 310);
        $payment_url = PAKASIR_WEB_BASE . '/pay/' . rawurlencode($project_slug) . '/' . $amount
            . '?order_id=' . rawurlencode($order_id) . $redirect_q . '&qris_only=1';
    } elseif ($metode === 'paypal') {
        if ($amount < 10000) {
            throw new InvalidArgumentException('PayPal minimal Rp10.000.');
        }
        $fee = max((int) round(0.01 * $amount), 3000);
        $payment_url = PAKASIR_WEB_BASE . '/paypal/' . rawurlencode($project_slug) . '/' . $amount
            . '?order_id=' . rawurlencode($order_id) . $redirect_q;
    } elseif ($metode === 'sampoerna_va' || $metode === 'artha_graha_va') {
        $fee = 2000;
        $payment_url = PAKASIR_WEB_BASE . '/pay/' . rawurlencode($project_slug) . '/' . $amount
            . '?order_id=' . rawurlencode($order_id) . $redirect_q . '&payment_method=' . rawurlencode($metode);
    } elseif ($metode !== 'all') {
        $fee = 3500;
        $payment_url = PAKASIR_WEB_BASE . '/pay/' . rawurlencode($project_slug) . '/' . $amount
            . '?order_id=' . rawurlencode($order_id) . $redirect_q . '&payment_method=' . rawurlencode($metode);
    } else {
        $payment_url = PAKASIR_WEB_BASE . '/pay/' . rawurlencode($project_slug) . '/' . $amount
            . '?order_id=' . rawurlencode($order_id) . $redirect_q;
    }

    return [
        'fee' => $fee,
        'total_payment' => $amount + $fee,
        'payment_url' => $payment_url,
    ];
}

/**
 * @return array{ok:bool, error?:string, data?:array<string, mixed>}
 */
function pakasir_http_json(string $method, string $url, ?array $body = null, ?array $query = null): array
{
    if ($query !== null && $query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'Gagal inisialisasi koneksi.'];
    }

    $headers = ['Accept: application/json'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];

    if ($body !== null) {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return ['ok' => false, 'error' => 'Payload tidak valid.'];
        }
        $opts[CURLOPT_POSTFIELDS] = $json;
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }

    $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($ca && is_readable($ca)) {
        $opts[CURLOPT_CAINFO] = $ca;
    } else {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    }

    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($errno !== 0 || !is_string($raw)) {
        return ['ok' => false, 'error' => $err !== '' ? $err : 'Permintaan ke Pakasir gagal.'];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Respons Pakasir tidak valid (HTTP ' . $code . ').'];
    }

    if ($code >= 400) {
        $pesan = trim((string) ($decoded['message'] ?? $decoded['error'] ?? 'HTTP ' . $code));

        return ['ok' => false, 'error' => $pesan !== '' ? $pesan : 'Permintaan ditolak Pakasir.'];
    }

    return ['ok' => true, 'data' => $decoded];
}

/**
 * Buat transaksi di Pakasir lalu kembalikan URL/ nomor pembayaran.
 *
 * @return array{ok:bool, error?:string, payment_url?:string, payment_number?:string, fee?:int, total_payment?:int, payment_method?:string}
 */
function pakasir_buat_pembayaran(string $metode, int $order_id_db, int $amount, ?string $redirect_url = null): array
{
    if (!pakasir_siap()) {
        return ['ok' => false, 'error' => 'Pakasir belum dikonfigurasi di Pengaturan admin.'];
    }
    if ($order_id_db <= 0 || $amount < 500) {
        return ['ok' => false, 'error' => 'Data pesanan tidak valid untuk pembayaran.'];
    }

    $cfg = pakasir_konfigurasi();
    $metode = strtolower(trim($metode));
    if (!array_key_exists($metode, pakasir_daftar_metode())) {
        $metode = $cfg['metode_default'];
    }

    $order_id = pakasir_order_id_dari_db($order_id_db);
    if ($order_id === '') {
        return ['ok' => false, 'error' => 'ID pesanan tidak valid.'];
    }

    try {
        $url_data = pakasir_hitung_url_pembayaran(
            $metode,
            $cfg['project_slug'],
            $order_id,
            $amount,
            $redirect_url ?? pakasir_url_redirect_detail($order_id_db)
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    $payload = [
        'project' => $cfg['project_slug'],
        'order_id' => $order_id,
        'amount' => $amount,
        'api_key' => $cfg['api_key'],
    ];
    $redirect = $redirect_url ?? pakasir_url_redirect_detail($order_id_db);
    if ($redirect !== '') {
        $payload['redirect_url'] = $redirect;
    }

    $resp = pakasir_http_json(
        'POST',
        PAKASIR_API_BASE . '/transactioncreate/' . rawurlencode($metode),
        $payload
    );
    if (!$resp['ok']) {
        return ['ok' => false, 'error' => (string) ($resp['error'] ?? 'Gagal membuat pembayaran.')];
    }

    $data = $resp['data'] ?? [];
    $payment = is_array($data['payment'] ?? null) ? $data['payment'] : [];

    return [
        'ok' => true,
        'payment_url' => (string) ($url_data['payment_url'] ?? ''),
        'payment_number' => isset($payment['payment_number']) ? (string) $payment['payment_number'] : null,
        'fee' => (int) ($url_data['fee'] ?? 0),
        'total_payment' => (int) ($url_data['total_payment'] ?? $amount),
        'payment_method' => $metode,
        'expired_at' => isset($payment['expired_at']) ? (string) $payment['expired_at'] : null,
    ];
}

/**
 * @return array{ok:bool, error?:string, status?:string, payment_method?:string, completed_at?:string|null}
 */
function pakasir_detail_pembayaran(string $order_id, int $amount): array
{
    if (!pakasir_siap()) {
        return ['ok' => false, 'error' => 'Pakasir belum dikonfigurasi.'];
    }
    $cfg = pakasir_konfigurasi();
    $order_id = pakasir_sanitize_order_id($order_id);
    if ($order_id === '' || $amount < 500) {
        return ['ok' => false, 'error' => 'Parameter tidak valid.'];
    }

    $resp = pakasir_http_json('GET', PAKASIR_API_BASE . '/transactiondetail', null, [
        'project' => $cfg['project_slug'],
        'amount' => $amount,
        'order_id' => $order_id,
        'api_key' => $cfg['api_key'],
    ]);
    if (!$resp['ok']) {
        return ['ok' => false, 'error' => (string) ($resp['error'] ?? 'Gagal cek status pembayaran.')];
    }

    $data = $resp['data'] ?? [];
    $tx = is_array($data['transaction'] ?? null) ? $data['transaction'] : (is_array($data['data'] ?? null) ? $data['data'] : $data);

    return [
        'ok' => true,
        'status' => strtolower(trim((string) ($tx['status'] ?? 'pending'))),
        'payment_method' => strtolower(trim((string) ($tx['payment_method'] ?? ''))),
        'completed_at' => isset($tx['completed_at']) ? (string) $tx['completed_at'] : null,
    ];
}

/**
 * Verifikasi webhook Pakasir + double-check ke API.
 *
 * @param array<string, mixed> $payload
 * @return array{ok:bool, error?:string, order_id_db?:int, amount?:int, status?:string, payment_method?:string}
 */
function pakasir_verifikasi_webhook(array $payload, int $expected_amount): array
{
    if (!pakasir_siap()) {
        return ['ok' => false, 'error' => 'Pakasir belum dikonfigurasi.'];
    }

    $cfg = pakasir_konfigurasi();
    $project = trim((string) ($payload['project'] ?? ''));
    $order_id = pakasir_sanitize_order_id((string) ($payload['order_id'] ?? ''));
    $amount = (int) ($payload['amount'] ?? 0);
    $status = strtolower(trim((string) ($payload['status'] ?? '')));
    $metode = strtolower(trim((string) ($payload['payment_method'] ?? '')));

    if ($order_id === '') {
        return ['ok' => false, 'error' => 'order_id kosong.'];
    }
    if ($project !== $cfg['project_slug']) {
        return ['ok' => false, 'error' => 'Project tidak cocok.'];
    }
    if ($amount !== $expected_amount) {
        return ['ok' => false, 'error' => 'Nominal tidak cocok dengan pesanan.'];
    }
    if ($status !== 'completed') {
        return ['ok' => false, 'error' => 'Status webhook bukan completed.'];
    }

    $detail = pakasir_detail_pembayaran($order_id, $amount);
    if (!$detail['ok']) {
        return ['ok' => false, 'error' => (string) ($detail['error'] ?? 'Gagal verifikasi ke server Pakasir.')];
    }
    if (($detail['status'] ?? '') !== 'completed') {
        return ['ok' => false, 'error' => 'Status di server Pakasir belum completed.'];
    }

    $order_id_db = pakasir_parse_order_id_db($order_id);
    if ($order_id_db <= 0) {
        return ['ok' => false, 'error' => 'order_id tidak dikenali.'];
    }

    return [
        'ok' => true,
        'order_id_db' => $order_id_db,
        'amount' => $amount,
        'status' => 'completed',
        'payment_method' => $metode !== '' ? $metode : (string) ($detail['payment_method'] ?? ''),
    ];
}

/**
 * Sinkronkan status pesanan dari Pakasir (redirect / polling manual).
 *
 * @return 'paid'|'pending'|'cancelled'|'error'
 */
function pakasir_sinkronkan_pesanan_db(int $order_id_db, int $amount, string $status_saat_ini): string
{
    if (!pakasir_siap() || $order_id_db <= 0 || $amount < 500) {
        return 'error';
    }
    if (in_array($status_saat_ini, ['paid', 'processed', 'shipped', 'completed', 'cancelled'], true)) {
        return $status_saat_ini === 'cancelled' ? 'cancelled' : 'paid';
    }

    $external = pakasir_order_id_dari_db($order_id_db);
    $detail = pakasir_detail_pembayaran($external, $amount);
    if (!$detail['ok']) {
        return 'error';
    }

    $st = (string) ($detail['status'] ?? 'pending');
    if ($st === 'completed') {
        require_once __DIR__ . '/../repositori/pesanan_repositori.php';
        $label = 'Pakasir · ' . pakasir_label_metode((string) ($detail['payment_method'] ?? ''));
        pesanan_perbarui_metode_bayar($order_id_db, $label);
        pesanan_set_status_oleh_id($order_id_db, 'paid', 'server');

        return 'paid';
    }
    if ($st === 'canceled' || $st === 'cancelled') {
        return 'cancelled';
    }

    return 'pending';
}