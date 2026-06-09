<?php

declare(strict_types=1);

/**
 * Kirim email via SMTP (STARTTLS 587 / SSL 465).
 *
 * @return array{ok: bool, pesan: string}
 */
function notifikasi_email_smtp_kirim(
    string $host,
    int $port,
    string $user,
    string $pass,
    string $from,
    string $to,
    string $subject,
    string $body_teks
): array {
    $host = trim($host);
    $user = trim($user);
    $pass = (string) $pass;
    $from = trim($from);
    $to = trim($to);
    $subject = trim($subject);

    if ($host === '' || $to === '' || $from === '') {
        return ['ok' => false, 'pesan' => 'Host SMTP, pengirim (From), atau penerima (To) masih kosong.'];
    }
    if ($port <= 0 || $port > 65535) {
        $port = 587;
    }
    if ($user !== '' && $pass === '') {
        return ['ok' => false, 'pesan' => 'Password SMTP kosong. Isi ulang password lalu tes lagi.'];
    }

    $ssl_langsung = $port === 465;
    $transport = $ssl_langsung ? 'ssl://' . $host : $host;
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client(
        $transport . ':' . $port,
        $errno,
        $errstr,
        25,
        STREAM_CLIENT_CONNECT,
        stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ])
    );
    if (!is_resource($fp)) {
        return [
            'ok' => false,
            'pesan' => 'SMTP koneksi ke ' . $host . ':' . $port . ' gagal: '
                . ($errstr !== '' ? $errstr : 'errno ' . $errno)
                . '. Coba port 587 (TLS) atau 465 (SSL).',
        ];
    }

    stream_set_timeout($fp, 25);

    $baca = static function ($fp): string {
        $out = '';
        while (!feof($fp)) {
            $line = fgets($fp, 8192);
            if ($line === false) {
                break;
            }
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $out;
    };

    $kirim_cek = static function ($fp, string $cmd) use ($baca): array {
        fwrite($fp, $cmd . "\r\n");
        $resp = $baca($fp);
        $ok = isset($resp[0]) && $resp[0] === '2';

        return ['ok' => $ok, 'resp' => $resp];
    };

    $greet = $baca($fp);
    if (!notifikasi_email_smtp_resp_ok($greet, '220')) {
        fclose($fp);

        return ['ok' => false, 'pesan' => 'SMTP greeting gagal: ' . notifikasi_email_smtp_resp_ringkas($greet)];
    }

    $ehlo_host = 'easenikers.shop';
    fwrite($fp, 'EHLO ' . $ehlo_host . "\r\n");
    $ehlo = $baca($fp);
    if (!notifikasi_email_smtp_resp_ok($ehlo, '250')) {
        fclose($fp);

        return ['ok' => false, 'pesan' => 'SMTP EHLO gagal: ' . notifikasi_email_smtp_resp_ringkas($ehlo)];
    }

    if (!$ssl_langsung && stripos($ehlo, 'STARTTLS') !== false) {
        fwrite($fp, "STARTTLS\r\n");
        $tls = $baca($fp);
        if (!notifikasi_email_smtp_resp_ok($tls, '220')) {
            fclose($fp);

            return ['ok' => false, 'pesan' => 'SMTP STARTTLS ditolak: ' . notifikasi_email_smtp_resp_ringkas($tls)];
        }
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
            fclose($fp);

            return ['ok' => false, 'pesan' => 'SMTP TLS handshake gagal. Coba port 465 (SSL) jika 587 bermasalah.'];
        }
        fwrite($fp, 'EHLO ' . $ehlo_host . "\r\n");
        $ehlo2 = $baca($fp);
        if (!notifikasi_email_smtp_resp_ok($ehlo2, '250')) {
            fclose($fp);

            return ['ok' => false, 'pesan' => 'SMTP EHLO setelah TLS gagal: ' . notifikasi_email_smtp_resp_ringkas($ehlo2)];
        }
        $ehlo = $ehlo2;
    }

    if ($user !== '') {
        $login = notifikasi_email_smtp_auth($fp, $baca, $user, $pass, $ehlo, $host);
        if (!$login['ok']) {
            fclose($fp);

            return ['ok' => false, 'pesan' => $login['pesan']];
        }
    }

    $from_addr = notifikasi_email_smtp_ekstrak_alamat($from);
    $mail = $kirim_cek($fp, 'MAIL FROM:<' . $from_addr . '>');
    if (!$mail['ok']) {
        fclose($fp);

        return [
            'ok' => false,
            'pesan' => 'SMTP MAIL FROM ditolak untuk <' . $from_addr . '>: '
                . notifikasi_email_smtp_resp_ringkas((string) $mail['resp'])
                . ' — From harus sama/izin dengan akun SMTP login.',
        ];
    }

    $to_addr = notifikasi_email_smtp_ekstrak_alamat($to);
    $rcpt = $kirim_cek($fp, 'RCPT TO:<' . $to_addr . '>');
    if (!$rcpt['ok']) {
        fclose($fp);

        return [
            'ok' => false,
            'pesan' => 'SMTP RCPT TO ditolak untuk <' . $to_addr . '>: '
                . notifikasi_email_smtp_resp_ringkas((string) $rcpt['resp']),
        ];
    }

    fwrite($fp, "DATA\r\n");
    $data_resp = $baca($fp);
    if (!notifikasi_email_smtp_resp_ok($data_resp, '354')) {
        fclose($fp);

        return ['ok' => false, 'pesan' => 'SMTP DATA ditolak: ' . notifikasi_email_smtp_resp_ringkas($data_resp)];
    }

    $headers = [
        'From: ' . $from,
        'To: ' . $to,
        'Subject: ' . notifikasi_email_smtp_encode_subject($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
    ];
    $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $body_teks);
    $payload = str_replace("\n.", "\n..", $payload);
    fwrite($fp, $payload . "\r\n.\r\n");
    $send_resp = $baca($fp);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);

    if (!notifikasi_email_smtp_resp_ok($send_resp, '250')) {
        return ['ok' => false, 'pesan' => 'SMTP kirim ditolak: ' . notifikasi_email_smtp_resp_ringkas($send_resp)];
    }

    return ['ok' => true, 'pesan' => 'Email terkirim ke ' . $to . '.'];
}

/**
 * @param callable $baca fn($fp): string
 * @return array{ok: bool, pesan: string}
 */
function notifikasi_email_smtp_auth($fp, callable $baca, string $user, string $pass, string $ehlo, string $host): array
{
    $metode = [];
    if (stripos($ehlo, 'AUTH') !== false) {
        if (stripos($ehlo, 'PLAIN') !== false) {
            $metode[] = 'PLAIN';
        }
        if (stripos($ehlo, 'LOGIN') !== false) {
            $metode[] = 'LOGIN';
        }
    }
    if ($metode === []) {
        $metode = ['LOGIN', 'PLAIN'];
    }

    $error_terakhir = 'Autentikasi SMTP gagal.';
    foreach ($metode as $m) {
        if ($m === 'PLAIN') {
            $hasil = notifikasi_email_smtp_auth_plain($fp, $baca, $user, $pass);
        } else {
            $hasil = notifikasi_email_smtp_auth_login($fp, $baca, $user, $pass);
        }
        if ($hasil['ok']) {
            return $hasil;
        }
        $error_terakhir = $hasil['pesan'];
    }

    $saran = notifikasi_email_smtp_saran_gagal_login($host);
    if (stripos($host, 'gmail') !== false || stripos($user, '@gmail.') !== false) {
        $saran = 'Gmail wajib pakai App Password (bukan password login). Buat di Google Account → Security → App passwords.';
    } elseif (str_contains(strtolower($error_terakhir), '535') || str_contains(strtolower($error_terakhir), 'authentication')) {
        $saran = 'Username/password ditolak server. Untuk Gmail pakai App Password 16 digit. Pastikan From sama dengan akun SMTP.';
    }

    return ['ok' => false, 'pesan' => $error_terakhir . ' ' . $saran];
}

/**
 * @param callable $baca fn($fp): string
 * @return array{ok: bool, pesan: string}
 */
function notifikasi_email_smtp_auth_login($fp, callable $baca, string $user, string $pass): array
{
    fwrite($fp, "AUTH LOGIN\r\n");
    $auth = $baca($fp);
    if (!notifikasi_email_smtp_resp_ok($auth, '334')) {
        return ['ok' => false, 'pesan' => 'AUTH LOGIN tidak didukung: ' . notifikasi_email_smtp_resp_ringkas($auth)];
    }
    fwrite($fp, base64_encode($user) . "\r\n");
    $user_resp = $baca($fp);
    if (!notifikasi_email_smtp_resp_ok($user_resp, '334')) {
        return ['ok' => false, 'pesan' => 'Username SMTP ditolak: ' . notifikasi_email_smtp_resp_ringkas($user_resp)];
    }
    fwrite($fp, base64_encode($pass) . "\r\n");
    $pass_resp = $baca($fp);
    if (!notifikasi_email_smtp_resp_ok($pass_resp, '235')) {
        return [
            'ok' => false,
            'pesan' => 'Login SMTP gagal (AUTH LOGIN): ' . notifikasi_email_smtp_resp_ringkas($pass_resp),
        ];
    }

    return ['ok' => true, 'pesan' => 'OK'];
}

/**
 * @param callable $baca fn($fp): string
 * @return array{ok: bool, pesan: string}
 */
function notifikasi_email_smtp_auth_plain($fp, callable $baca, string $user, string $pass): array
{
    $plain = base64_encode("\0" . $user . "\0" . $pass);
    fwrite($fp, 'AUTH PLAIN ' . $plain . "\r\n");
    $resp = $baca($fp);
    if (!notifikasi_email_smtp_resp_ok($resp, '235')) {
        return [
            'ok' => false,
            'pesan' => 'Login SMTP gagal (AUTH PLAIN): ' . notifikasi_email_smtp_resp_ringkas($resp),
        ];
    }

    return ['ok' => true, 'pesan' => 'OK'];
}

function notifikasi_email_smtp_resp_ok(string $resp, string $prefix): bool
{
    return strncmp(trim($resp), $prefix, strlen($prefix)) === 0;
}

function notifikasi_email_smtp_resp_ringkas(string $resp): string
{
    $resp = trim(preg_replace('/\s+/', ' ', $resp) ?? '');
    if ($resp === '') {
        return '(tanpa respons server)';
    }
    if (strlen($resp) > 220) {
        return substr($resp, 0, 220) . '…';
    }

    return $resp;
}

function notifikasi_email_smtp_saran_gagal_login(string $host_hint): string
{
    return 'Periksa user/password, port (587/465), dan From yang cocok dengan akun SMTP.';
}

function notifikasi_email_smtp_ekstrak_alamat(string $masukan): string
{
    if (preg_match('/<([^>]+)>/', $masukan, $m)) {
        return trim($m[1]);
    }

    return trim($masukan);
}

function notifikasi_email_smtp_encode_subject(string $subject): string
{
    if (preg_match('/[^\x20-\x7E]/', $subject)) {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    return $subject;
}