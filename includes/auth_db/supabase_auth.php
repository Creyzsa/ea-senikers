<?php
/**
 * Panggilan Supabase Auth lewat REST API + cURL (tanpa SDK JavaScript).
 * Dokumentasi: https://supabase.com/docs/reference/api
 */

require_once __DIR__ . '/../config_loader.php';
require_once __DIR__ . '/../url_bantu.php';

/**
 * Ambil URL dasar project (tanpa slash di akhir).
 */
function supabase_url_dasar(): string
{
    if (!defined('SUPABASE_URL')) {
        return '';
    }
    return rtrim((string) SUPABASE_URL, '/');
}

/**
 * Header standar Supabase: anon key dipakai sebagai apikey dan Bearer.
 */
function supabase_header_curl(): array
{
    $kunci = defined('SUPABASE_ANON_KEY') ? (string) SUPABASE_ANON_KEY : '';
    return [
        'apikey: ' . $kunci,
        'Authorization: Bearer ' . $kunci,
    ];
}

/**
 * Eksekusi permintaan cURL ke Supabase Auth.
 *
 * @param string $metode       GET atau POST
 * @param string $path_relatif contoh: '/auth/v1/signup'
 * @param string|null $body_json JSON string atau null
 * @param string|null $body_form-urlencoded untuk grant_type=password
 * @return array{ok: bool, http: int, data: ?array, raw: string, curl_errno: int, curl_error: string}
 */
function supabase_auth_request(
    string $metode,
    string $path_relatif,
    ?string $body_json = null,
    ?string $body_form = null
): array {
    $url = supabase_url_dasar() . $path_relatif;
    $headers = supabase_header_curl();

    if ($body_json !== null) {
        $headers[] = 'Content-Type: application/json';
    } elseif ($body_form !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    $ch = curl_init($url);
    $opsi = [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ];
    if (strtoupper($metode) === 'POST') {
        $opsi[CURLOPT_POST] = true;
        if ($body_json !== null) {
            $opsi[CURLOPT_POSTFIELDS] = $body_json;
        } elseif ($body_form !== null) {
            $opsi[CURLOPT_POSTFIELDS] = $body_form;
        }
    }

    // Paket CA (cacert.pem) — kalau tidak ada, HTTPS dari PHP di Windows/Laragon sering gagal.
    $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($ca && is_readable($ca)) {
        $opsi[CURLOPT_CAINFO] = $ca;
    } else {
        // Hanya untuk pengembangan lokal: tanpa file CA, cURL tidak bisa verifikasi sertifikat Supabase.
        // Di production, set curl.cainfo / openssl.cafile di php.ini ke cacert.pem resmi.
        $opsi[CURLOPT_SSL_VERIFYPEER] = false;
        $opsi[CURLOPT_SSL_VERIFYHOST] = 0;
    }

    curl_setopt_array($ch, $opsi);
    $eksekusi = curl_exec($ch);
    $curl_errno = (int) curl_errno($ch);
    $curl_error = (string) curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() removed: deprecated and no-op since PHP 8.0 (handles are auto-closed)

    $raw = is_string($eksekusi) ? $eksekusi : '';
    $data = null;
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : null;
    }

    return [
        'ok' => $curl_errno === 0 && $http >= 200 && $http < 300,
        'http' => $http,
        'data' => $data,
        'raw' => $raw,
        'curl_errno' => $curl_errno,
        'curl_error' => $curl_error,
    ];
}

/**
 * Ubah respons error Supabase menjadi teks untuk pengguna.
 */
function supabase_pesan_error_dari_respons(?array $data): string
{
    if ($data === null) {
        return 'Layanan tidak merespons. Periksa koneksi lalu coba lagi.';
    }
    if (!empty($data['error_description']) && is_string($data['error_description'])) {
        return $data['error_description'];
    }
    if (!empty($data['message']) && is_string($data['message'])) {
        return $data['message'];
    }
    if (!empty($data['msg']) && is_string($data['msg'])) {
        return $data['msg'];
    }
    if (!empty($data['error']) && is_string($data['error'])) {
        return $data['error'];
    }
    return 'Proses tidak dapat diselesaikan. Coba lagi.';
}

/**
 * Samakan pesan API (sering bahasa Inggris) ke teks singkat untuk pengguna akhir.
 */
function supabase_pesan_di_pahamkan(string $teks): string
{
    $l = strtolower($teks);

    if (strpos($l, 'rate limit') !== false || strpos($l, 'over_email_send') !== false || strpos($l, 'email rate') !== false) {
        return 'Terlalu banyak permintaan email. Tunggu beberapa menit, lalu coba lagi.';
    }
    if (strpos($l, 'error sending recovery email') !== false
        || strpos($l, 'sending recovery email') !== false
        || (strpos($l, 'failed to send') !== false && strpos($l, 'email') !== false)) {
        return 'Email reset tidak bisa dikirim dari server autentikasi. Di Supabase: buka Authentication → SMTP (pastikan custom SMTP benar atau gunakan bawaan untuk uji), periksa template email reset, lalu lihat log Auth untuk detail. Pastikan URL_APLIKASI (redirect) sudah ada di daftar Redirect URLs.';
    }
    if (strpos($l, 'email link is invalid') !== false || strpos($l, 'invalid or has expired') !== false
        || strpos($l, 'otp_expired') !== false) {
        return 'Tautan email sudah tidak berlaku (mungkin dipakai otomatis oleh aplikasi email). Minta email baru atau gunakan tautan token_hash di template email.';
    }
    if (strpos($l, 'invalid login') !== false || strpos($l, 'invalid_grant') !== false
        || (strpos($l, 'invalid') !== false && strpos($l, 'credential') !== false)) {
        return 'Email atau kata sandi tidak sesuai.';
    }
    if (strpos($l, 'email not confirmed') !== false) {
        return 'Silakan konfirmasi email Anda terlebih dahulu.';
    }
    if (strpos($l, 'too many requests') !== false || strpos($l, '429') !== false) {
        return 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';
    }
    if (strpos($l, 'password') !== false && (strpos($l, 'weak') !== false || strpos($l, 'at least') !== false || strpos($l, 'too short') !== false)) {
        return 'Kata sandi tidak memenuhi persyaratan.';
    }
    if (strpos($l, 'already registered') !== false || strpos($l, 'user already') !== false
        || strpos($l, 'already exists') !== false || strpos($l, 'duplicate') !== false) {
        return 'Email ini sudah terdaftar.';
    }
    if (strpos($l, 'could not parse') !== false || strpos($l, 'unexpected end') !== false) {
        return 'Permintaan gagal diproses. Muat ulang halaman lalu coba lagi.';
    }
    if (strpos($l, 'respons server') !== false && strpos($l, 'http') !== false) {
        return 'Layanan sementara bermasalah. Coba lagi nanti.';
    }

    if (strlen($teks) > 140) {
        return 'Terjadi gangguan. Silakan coba lagi.';
    }

    return $teks;
}

/**
 * Gabungkan error cURL + HTTP + body Supabase jadi satu pesan (untuk ditampilkan / dicatat).
 *
 * @param array{http: int, data: ?array, raw: string, curl_errno: int, curl_error: string} $hasil
 */
function supabase_pesan_gagal_dari_hasil(array $hasil): string
{
    if ($hasil['curl_errno'] !== 0) {
        return 'Tidak dapat terhubung. Periksa koneksi internet lalu coba lagi.';
    }
    if ($hasil['http'] === 0) {
        return 'Layanan tidak merespons. Coba lagi.';
    }
    if (is_array($hasil['data'])) {
        return supabase_pesan_di_pahamkan(supabase_pesan_error_dari_respons($hasil['data']));
    }
    // Body ada tapi bukan JSON (misalnya halaman error HTML)
    $potong = function_exists('mb_substr')
        ? mb_substr($hasil['raw'], 0, 200)
        : substr($hasil['raw'], 0, 200);
    $cuplikan = trim($potong);
    if ($cuplikan !== '') {
        return supabase_pesan_di_pahamkan('Respons server (HTTP ' . $hasil['http'] . '): ' . $cuplikan);
    }
    if ($hasil['http'] >= 400) {
        return 'Layanan autentikasi menolak permintaan (HTTP ' . $hasil['http'] . '). Coba minta tautan reset baru atau masuk lagi.';
    }
    return 'Permintaan gagal. Coba lagi.';
}

/**
 * a) Register — POST /auth/v1/signup
 *
 * @return array{ok: bool, pesan?: string, data?: array}
 */
function supabase_auth_daftar(string $email, string $password, array $metadata_tambahan = []): array
{
    if (supabase_url_dasar() === '' || !defined('SUPABASE_ANON_KEY') || SUPABASE_ANON_KEY === '') {
        return ['ok' => false, 'pesan' => 'Layanan belum siap. Hubungi pengelola situs.'];
    }

    // redirect_to: URL yang sama dengan browser saat submit (Laragon/IP), bukan hanya config statis
    $tujuan_setelah_klik_email = aplikasi_url_konfirmasi_email();

    $payload = [
        'email' => $email,
        'password' => $password,
        'data' => $metadata_tambahan,
    ];
    if ($tujuan_setelah_klik_email !== '') {
        $payload['redirect_to'] = $tujuan_setelah_klik_email;
        // Beberapa versi API pakai camelCase, lainnya snake_case
        $payload['options'] = [
            'emailRedirectTo' => $tujuan_setelah_klik_email,
            'email_redirect_to' => $tujuan_setelah_klik_email,
        ];
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $hasil = supabase_auth_request('POST', '/auth/v1/signup', $json, null);

    if ($hasil['ok']) {
        return ['ok' => true, 'data' => $hasil['data']];
    }

    return [
        'ok' => false,
        'pesan' => supabase_pesan_gagal_dari_hasil($hasil),
        'data' => $hasil['data'],
    ];
}

/**
 * b) Login — POST /auth/v1/token?grant_type=password
 *
 * @return array{ok: bool, pesan?: string, data?: array}
 */
function supabase_auth_masuk(string $email, string $password): array
{
    if (supabase_url_dasar() === '' || !defined('SUPABASE_ANON_KEY') || SUPABASE_ANON_KEY === '') {
        return ['ok' => false, 'pesan' => 'Layanan belum siap. Hubungi pengelola situs.'];
    }

    // API mengharapkan body JSON (bukan form-urlencoded) — kalau tidak, error "Could not parse request body as JSON"
    $json = json_encode([
        'email' => $email,
        'password' => $password,
    ], JSON_UNESCAPED_UNICODE);

    $hasil = supabase_auth_request('POST', '/auth/v1/token?grant_type=password', $json, null);

    if ($hasil['ok'] && is_array($hasil['data']) && !empty($hasil['data']['access_token'])) {
        return ['ok' => true, 'data' => $hasil['data']];
    }

    return [
        'ok' => false,
        'pesan' => supabase_pesan_gagal_dari_hasil($hasil),
        'data' => $hasil['data'],
    ];
}

/**
 * c) Lupa password — POST /auth/v1/recover
 * Supabase mengirim email berisi tautan reset (atur redirect_to di Dashboard jika perlu).
 *
 * @return array{ok: bool, pesan?: string}
 */
function supabase_auth_lupa_password(string $email): array
{
    if (supabase_url_dasar() === '' || !defined('SUPABASE_ANON_KEY') || SUPABASE_ANON_KEY === '') {
        return ['ok' => false, 'pesan' => 'Layanan belum siap. Hubungi pengelola situs.'];
    }

    // redirect_to + options.redirectTo: setelah klik email, buka halaman callback (bukan root IP kosong).
    $payload = [
        'email' => $email,
    ];
    $balik = aplikasi_url_konfirmasi_email();
    if ($balik !== '') {
        $payload['redirect_to'] = $balik;
        // Sama seperti signup: beberapa versi GoTrue mengharapkan redirect di beberapa kunci.
        $payload['options'] = [
            'redirectTo' => $balik,
            'emailRedirectTo' => $balik,
            'email_redirect_to' => $balik,
        ];
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $hasil = supabase_auth_request('POST', '/auth/v1/recover', $json, null);

    if ($hasil['ok']) {
        return ['ok' => true];
    }

    return [
        'ok' => false,
        'pesan' => supabase_pesan_gagal_dari_hasil($hasil),
    ];
}

/**
 * Verifikasi tautan email (token_hash) lewat POST — token tidak terbakar oleh pemindai email
 * yang memanggil GET ke {{ .ConfirmationURL }} Supabase (sering memicu otp_expired).
 *
 * Di Dashboard Supabase → Authentication → Email Templates, ganti tautan menjadi:
 *
 * Reset password:
 *   <a href="{{ .RedirectTo }}?token_hash={{ .TokenHash }}&type=recovery">Reset kata sandi</a>
 *
 * Confirm sign up:
 *   <a href="{{ .RedirectTo }}?token_hash={{ .TokenHash }}&type=signup">Konfirmasi email</a>
 *
 * {{ .RedirectTo }} mengikuti redirect_to dari aplikasi (harus sudah di Redirect URLs).
 *
 * PENTING: Jangan pakai {{ .ConfirmationURL }} atau {{ .SiteURL }} langsung di template,
 * karena email client (Gmail dll) sering pre-load link tersebut dan membuat token langsung expired (otp_expired).
 *
 * Contoh template HTML yang aman (copy-paste ke Supabase Email Templates):
 *
 * Untuk Confirm signup (ganti template "Confirm signup"):
 * <div style="background:#f5f5f5; padding:40px 20px; font-family:Arial, sans-serif;">
 *   <div style="max-width:420px; margin:auto; background:#ffffff; padding:32px; border-radius:10px;">
 *     <h2 style="margin:0 0 10px; font-size:20px; color:#111;">Konfirmasi Email</h2>
 *     <p style="font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;">
 *       Terima kasih sudah mendaftar di <b>EA SENIKERS</b>. Silakan konfirmasi email Anda.
 *     </p>
 *     <div style="margin:25px 0;">
 *       <a href="{{ .RedirectTo }}?token_hash={{ .TokenHash }}&amp;type=signup"
 *          style="display:block; text-align:center; background:#111; color:#fff; padding:12px; border-radius:6px; text-decoration:none; font-size:14px;">
 *         Konfirmasi Email
 *       </a>
 *     </div>
 *     <p style="font-size:12px; color:#777;">Jika tombol tidak bisa diklik, buka link ini:</p>
 *     <p style="font-size:12px; color:#333; word-break:break-all;">
 *       {{ .RedirectTo }}?token_hash={{ .TokenHash }}&amp;type=signup
 *     </p>
 *     <p style="font-size:12px; color:#999; margin-top:20px;">
 *       Jika Anda tidak merasa mendaftar, abaikan email ini.
 *     </p>
 *     <hr style="border:none; border-top:1px solid #eee; margin:25px 0;">
 *     <p style="font-size:12px; color:#aaa; text-align:center;">
 *       EA SENIKERS
 *     </p>
 *   </div>
 * </div>
 *
 * Untuk Reset password (ganti template "Reset password"):
 * <div style="background:#f5f5f5; padding:40px 20px; font-family:Arial, sans-serif;">
 *   <div style="max-width:420px; margin:auto; background:#ffffff; padding:32px; border-radius:10px;">
 *     <h2 style="margin:0 0 10px; font-size:20px; color:#111;">Reset Kata Sandi</h2>
 *     <p style="font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;">
 *       Kami menerima permintaan reset kata sandi untuk akun EA SENIKERS Anda.
 *     </p>
 *     <div style="margin:25px 0;">
 *       <a href="{{ .RedirectTo }}?token_hash={{ .TokenHash }}&amp;type=recovery"
 *          style="display:block; text-align:center; background:#111; color:#fff; padding:12px; border-radius:6px; text-decoration:none; font-size:14px;">
 *         Reset Kata Sandi
 *       </a>
 *     </div>
 *     <p style="font-size:12px; color:#777;">Jika tombol tidak bisa diklik, buka link ini:</p>
 *     <p style="font-size:12px; color:#333; word-break:break-all;">
 *       {{ .RedirectTo }}?token_hash={{ .TokenHash }}&amp;type=recovery
 *     </p>
 *     <p style="font-size:12px; color:#999; margin-top:20px;">
 *       Jika Anda tidak meminta reset, abaikan email ini.
 *     </p>
 *     <hr style="border:none; border-top:1px solid #eee; margin:25px 0;">
 *     <p style="font-size:12px; color:#aaa; text-align:center;">
 *       EA SENIKERS
 *     </p>
 *   </div>
 * </div>
 *
 * @param string $tipe recovery | signup | email | invite | magiclink
 * @return array{ok: bool, pesan?: string, access_token?: string, refresh_token?: string}
 */
function supabase_auth_verifikasi_token_hash(string $tipe, string $token_hash): array
{
    if (supabase_url_dasar() === '' || !defined('SUPABASE_ANON_KEY') || SUPABASE_ANON_KEY === '') {
        return ['ok' => false, 'pesan' => 'Layanan belum siap. Hubungi pengelola situs.'];
    }
    $tipe = strtolower(trim($tipe));
    $token_hash = trim($token_hash);
    $izin = ['recovery', 'signup', 'email', 'invite', 'magiclink'];
    if ($token_hash === '' || !in_array($tipe, $izin, true)) {
        return ['ok' => false, 'pesan' => 'Tautan tidak lengkap atau tidak didukung.'];
    }

    $payload = [
        'type' => $tipe,
        'token_hash' => $token_hash,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $hasil = supabase_auth_request('POST', '/auth/v1/verify', $json, null);

    if ($hasil['ok'] && is_array($hasil['data'])) {
        $d = $hasil['data'];
        $at = '';
        $rt = '';
        if (!empty($d['access_token']) && is_string($d['access_token'])) {
            $at = $d['access_token'];
            $rt = is_string($d['refresh_token'] ?? null) ? (string) $d['refresh_token'] : '';
        } elseif (!empty($d['session']) && is_array($d['session'])) {
            $at = (string) ($d['session']['access_token'] ?? '');
            $rt = (string) ($d['session']['refresh_token'] ?? '');
        }
        if ($at !== '') {
            return [
                'ok' => true,
                'access_token' => $at,
                'refresh_token' => $rt,
            ];
        }
    }

    return [
        'ok' => false,
        'pesan' => supabase_pesan_gagal_dari_hasil($hasil),
    ];
}

/**
 * Ambil profil pengguna dari Supabase memakai access_token (setelah klik tautan di email).
 * GET /auth/v1/user — Authorization: Bearer <access_token pengguna>
 *
 * @return array<string, mixed>|null
 */
function supabase_auth_ambil_user_dengan_token(string $access_token_pengguna): ?array
{
    if ($access_token_pengguna === '' || supabase_url_dasar() === '') {
        return null;
    }

    $url = supabase_url_dasar() . '/auth/v1/user';
    $kunci = defined('SUPABASE_ANON_KEY') ? (string) SUPABASE_ANON_KEY : '';
    $ch = curl_init($url);
    $opsi = [
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $kunci,
            'Authorization: Bearer ' . $access_token_pengguna,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ];
    $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($ca && is_readable($ca)) {
        $opsi[CURLOPT_CAINFO] = $ca;
    } else {
        $opsi[CURLOPT_SSL_VERIFYPEER] = false;
        $opsi[CURLOPT_SSL_VERIFYHOST] = 0;
    }
    curl_setopt_array($ch, $opsi);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() removed: deprecated and no-op since PHP 8.0 (handles are auto-closed)

    if ($http !== 200 || !is_string($raw) || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Ubah kata sandi memakai access_token sesi (termasuk token dari tautan reset email).
 * PUT /auth/v1/user — sama dengan supabase-js updateUser() (bukan PATCH).
 *
 * @return array{ok: bool, pesan?: string}
 */
function supabase_auth_perbarui_kata_sandi(string $access_token_pengguna, string $password_baru): array
{
    if (supabase_url_dasar() === '' || !defined('SUPABASE_ANON_KEY') || SUPABASE_ANON_KEY === '') {
        return ['ok' => false, 'pesan' => 'Layanan belum siap. Hubungi pengelola situs.'];
    }
    if (strlen($password_baru) < 8) {
        return ['ok' => false, 'pesan' => 'Kata sandi minimal 8 karakter.'];
    }

    $url = supabase_url_dasar() . '/auth/v1/user';
    $kunci = (string) SUPABASE_ANON_KEY;
    $json = json_encode(['password' => $password_baru], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    $opsi = [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $kunci,
            'Authorization: Bearer ' . $access_token_pengguna,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ];
    $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($ca && is_readable($ca)) {
        $opsi[CURLOPT_CAINFO] = $ca;
    } else {
        $opsi[CURLOPT_SSL_VERIFYPEER] = false;
        $opsi[CURLOPT_SSL_VERIFYHOST] = 0;
    }
    curl_setopt_array($ch, $opsi);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() removed: deprecated and no-op since PHP 8.0 (handles are auto-closed)

    $data = null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : null;
    }

    if ($http >= 200 && $http < 300) {
        return ['ok' => true];
    }

    return [
        'ok' => false,
        'pesan' => supabase_pesan_gagal_dari_hasil([
            'ok' => false,
            'http' => $http,
            'data' => $data,
            'raw' => is_string($raw) ? $raw : '',
            'curl_errno' => 0,
            'curl_error' => '',
        ]),
    ];
}
