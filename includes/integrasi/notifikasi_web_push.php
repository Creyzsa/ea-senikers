<?php

declare(strict_types=1);

/**
 * Web Push (RFC 8030 / RFC 8291) tanpa Composer — memakai OpenSSL bawaan PHP.
 */

function web_push_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function web_push_base64url_decode(string $data): string
{
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }

    return (string) base64_decode(strtr($data, '-_', '+/'), true);
}

function web_push_decode_langganan_key(string $b64): string
{
    $b64 = trim($b64);
    if ($b64 === '') {
        return '';
    }

    $decoded = web_push_base64url_decode($b64);
    if ($decoded !== '') {
        return $decoded;
    }

    $standar = $b64;
    $pad = strlen($standar) % 4;
    if ($pad > 0) {
        $standar .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($standar, true);

    return is_string($decoded) ? $decoded : '';
}

function web_push_normalisasi_p256dh(string $raw): string
{
    if ($raw === '') {
        return '';
    }
    if (strlen($raw) === 64) {
        return "\x04" . $raw;
    }
    if (strlen($raw) === 65 && $raw[0] === "\x04") {
        return $raw;
    }

    return '';
}

/**
 * @return array{public_key: string, private_key_pem: string}
 */
function web_push_generate_vapid_keys(): array
{
    $key = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if ($key === false) {
        throw new RuntimeException('Gagal membuat kunci VAPID (OpenSSL EC).');
    }

    $details = openssl_pkey_get_details($key);
    if (!is_array($details) || empty($details['ec']['x']) || empty($details['ec']['y'])) {
        throw new RuntimeException('Detail kunci VAPID tidak valid.');
    }

    $public_raw = "\x04" . $details['ec']['x'] . $details['ec']['y'];
    $private_pem = '';
    if (!openssl_pkey_export($key, $private_pem)) {
        throw new RuntimeException('Gagal mengekspor kunci privat VAPID.');
    }

    return [
        'public_key' => web_push_base64url_encode($public_raw),
        'private_key_pem' => $private_pem,
    ];
}

function web_push_hkdf(string $salt, string $ikm, string $info, int $length): string
{
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $t = '';
    $last = '';
    $blocks = (int) ceil($length / 32);
    for ($i = 1; $i <= $blocks; $i++) {
        $last = hash_hmac('sha256', $last . $info . chr($i), $prk, true);
        $t .= $last;
    }

    return substr($t, 0, $length);
}

/**
 * @return array{ciphertext: string, salt: string, local_public: string}
 */
function web_push_encrypt_payload(string $payload, string $p256dh_b64, string $auth_b64): array
{
    $user_public = web_push_normalisasi_p256dh(web_push_decode_langganan_key($p256dh_b64));
    $auth_secret = web_push_decode_langganan_key($auth_b64);
    if ($user_public === '') {
        throw new RuntimeException('Kunci p256dh langganan tidak valid. Nonaktifkan lalu aktifkan push lagi.');
    }
    if (strlen($auth_secret) < 16) {
        throw new RuntimeException('Auth secret langganan tidak valid. Nonaktifkan lalu aktifkan push lagi.');
    }

    $local_key = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if ($local_key === false) {
        throw new RuntimeException('Gagal membuat kunci sementara push.');
    }

    $local_details = openssl_pkey_get_details($local_key);
    if (!is_array($local_details) || empty($local_details['ec']['x']) || empty($local_details['ec']['y'])) {
        throw new RuntimeException('Kunci sementara push tidak valid.');
    }
    $local_x = str_pad((string) $local_details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $local_y = str_pad((string) $local_details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $local_public = "\x04" . $local_x . $local_y;

    $user_pem = web_push_ec_public_to_pem($user_public);
    $user_key = openssl_pkey_get_public($user_pem);
    if ($user_key === false) {
        throw new RuntimeException('Kunci publik langganan tidak dapat dibaca.');
    }
    $shared = openssl_pkey_derive($user_key, $local_key);
    if (!is_string($shared) || $shared === '') {
        throw new RuntimeException('Gagal ECDH untuk enkripsi push.');
    }
    $shared = str_pad($shared, 32, "\x00", STR_PAD_LEFT);

    $salt = random_bytes(16);
    // aes128gcm (RFC 8188): IKM memakai WebPush: info + kunci publik UA & lokal
    $ikm = web_push_hkdf(
        $auth_secret,
        $shared,
        "WebPush: info\x00" . $user_public . $local_public,
        32
    );
    $cek = web_push_hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
    $nonce = web_push_hkdf($salt, $ikm, "Content-Encoding: nonce\x00", 12);

    // Plaintext aes128gcm: payload + delimiter 0x02
    $record = $payload . "\x02";
    $tag = '';
    $ciphertext = openssl_encrypt($record, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ciphertext === false) {
        throw new RuntimeException('Gagal mengenkripsi payload push.');
    }

    $body = $salt
        . pack('N', 4096)
        . chr(strlen($local_public))
        . $local_public
        . $ciphertext
        . $tag;

    return [
        'ciphertext' => $body,
        'salt' => $salt,
        'local_public' => $local_public,
    ];
}

function web_push_ec_public_to_pem(string $public_raw): string
{
    $public_raw = web_push_normalisasi_p256dh($public_raw);
    if ($public_raw === '') {
        throw new RuntimeException('Kunci p256dh langganan tidak valid.');
    }

    // SPKI EC P-256 (id-ecPublicKey + prime256v1) — format standar Web Push
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $der = $prefix . $public_raw;
    $pem = "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";

    $key = openssl_pkey_get_public($pem);
    if ($key === false) {
        throw new RuntimeException('Gagal membaca kunci publik langganan. Nonaktifkan lalu aktifkan push lagi.');
    }

    return $pem;
}

function web_push_normalisasi_pem_privat(string $pem): string
{
    $pem = trim(str_replace(['\\n', '\r\n'], "\n", $pem));
    if ($pem === '') {
        return $pem;
    }
    if (!str_contains($pem, 'BEGIN')) {
        return $pem;
    }

    return $pem . "\n";
}

function web_push_jwt_vapid(string $endpoint, string $subject, string $private_pem): string
{
    $parts = parse_url($endpoint);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        throw new RuntimeException('Endpoint push tidak valid.');
    }
    $audience = $parts['scheme'] . '://' . $parts['host'];

    $header = web_push_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
    $claims = web_push_base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200,
        'sub' => $subject,
    ], JSON_UNESCAPED_SLASHES));

    $unsigned = $header . '.' . $claims;
    $private_pem = web_push_normalisasi_pem_privat($private_pem);
    $key = openssl_pkey_get_private($private_pem);
    if ($key === false) {
        throw new RuntimeException('Kunci privat VAPID tidak valid.');
    }

    $signature = '';
    if (!openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Gagal menandatangani JWT VAPID.');
    }

    $signature = web_push_der_to_raw_ecdsa($signature);

    return $unsigned . '.' . web_push_base64url_encode($signature);
}

function web_push_der_to_raw_ecdsa(string $der): string
{
    $offset = 0;
    if (ord($der[$offset++]) !== 0x30) {
        throw new RuntimeException('Format tanda tangan ECDSA tidak dikenali.');
    }
    $seq_len = ord($der[$offset++]);
    if ($seq_len & 0x80) {
        $nb = $seq_len & 0x7f;
        $seq_len = 0;
        for ($i = 0; $i < $nb; $i++) {
            $seq_len = ($seq_len << 8) | ord($der[$offset++]);
        }
    }

    if (ord($der[$offset++]) !== 0x02) {
        throw new RuntimeException('Komponen R ECDSA tidak valid.');
    }
    $r_len = ord($der[$offset++]);
    $r = substr($der, $offset, $r_len);
    $offset += $r_len;

    if (ord($der[$offset++]) !== 0x02) {
        throw new RuntimeException('Komponen S ECDSA tidak valid.');
    }
    $s_len = ord($der[$offset++]);
    $s = substr($der, $offset, $s_len);

    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

/**
 * @param array{endpoint: string, p256dh: string, auth: string} $subscription
 * @param array{public_key: string, private_key_pem: string, subject: string} $vapid
 * @return array{ok: bool, pesan: string, status: int}
 */
function web_push_kirim_satu(array $subscription, string $payload_json, array $vapid): array
{
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    $p256dh = trim((string) ($subscription['p256dh'] ?? ''));
    $auth = trim((string) ($subscription['auth'] ?? ''));
    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        return ['ok' => false, 'pesan' => 'Langganan push tidak lengkap.', 'status' => 0];
    }

    try {
        $encrypted = web_push_encrypt_payload($payload_json, $p256dh, $auth);
        $jwt = web_push_jwt_vapid(
            $endpoint,
            (string) ($vapid['subject'] ?? 'mailto:admin@easenikers.shop'),
            (string) $vapid['private_key_pem']
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'pesan' => $e->getMessage(), 'status' => 0];
    }

    $public_key = (string) ($vapid['public_key'] ?? '');
    $headers = [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Content-Length: ' . strlen($encrypted['ciphertext']),
        'TTL: 86400',
        'Urgency: high',
        'Authorization: vapid t=' . $jwt . ', k=' . $public_key,
    ];

    $ch = curl_init($endpoint);
    if ($ch === false) {
        return ['ok' => false, 'pesan' => 'cURL tidak tersedia.', 'status' => 0];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $encrypted['ciphertext'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    // curl_close() dihapus: deprecated PHP 8.5, handle ditutup otomatis sejak PHP 8.0

    if ($body === false) {
        return ['ok' => false, 'pesan' => 'Push gagal: ' . $err, 'status' => $status];
    }

    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'pesan' => 'Push terkirim.', 'status' => $status];
    }

    if ($status === 404 || $status === 410) {
        return ['ok' => false, 'pesan' => 'Langganan kedaluwarsa.', 'status' => $status, 'hapus' => true];
    }

    $snippet = trim((string) $body);
    if (strlen($snippet) > 180) {
        $snippet = substr($snippet, 0, 180) . '…';
    }

    return [
        'ok' => false,
        'pesan' => 'Push HTTP ' . $status . ($snippet !== '' ? ': ' . $snippet : ''),
        'status' => $status,
    ];
}