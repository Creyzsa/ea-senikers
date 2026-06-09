<?php

declare(strict_types=1);

/**
 * Kirim email sederhana via SMTP (STARTTLS port 587).
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
    $from = trim($from);
    $to = trim($to);
    $subject = trim($subject);

    if ($host === '' || $to === '' || $from === '') {
        return ['ok' => false, 'pesan' => 'Host SMTP, pengirim, atau penerima kosong.'];
    }
    if ($port <= 0 || $port > 65535) {
        $port = 587;
    }

    $transport = $port === 465 ? 'ssl://' . $host : $host;
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client(
        $transport . ':' . $port,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );
    if (!is_resource($fp)) {
        return ['ok' => false, 'pesan' => 'SMTP koneksi gagal: ' . ($errstr !== '' ? $errstr : (string) $errno)];
    }

    stream_set_timeout($fp, 20);

    $baca = static function ($fp): string {
        $out = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
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

    $kirim = static function ($fp, string $cmd) use ($baca): bool {
        fwrite($fp, $cmd . "\r\n");
        $resp = $baca($fp);

        return isset($resp[0]) && $resp[0] === '2';
    };

    $expect = static function (string $resp, string $prefix): bool {
        return strncmp($resp, $prefix, strlen($prefix)) === 0;
    };

    $greet = $baca($fp);
    if (!$expect($greet, '220')) {
        fclose($fp);

        return ['ok' => false, 'pesan' => 'SMTP greeting gagal.'];
    }

    $ehlo_host = 'easenikers.shop';
    fwrite($fp, 'EHLO ' . $ehlo_host . "\r\n");
    $ehlo = $baca($fp);
    if (!$expect($ehlo, '250')) {
        fclose($fp);

        return ['ok' => false, 'pesan' => 'SMTP EHLO gagal.'];
    }

    if ($port !== 465 && stripos($ehlo, 'STARTTLS') !== false) {
        fwrite($fp, "STARTTLS\r\n");
        $tls = $baca($fp);
        if (!$expect($tls, '220')) {
            fclose($fp);

            return ['ok' => false, 'pesan' => 'SMTP STARTTLS ditolak.'];
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);

            return ['ok' => false, 'pesan' => 'SMTP TLS handshake gagal.'];
        }
        fwrite($fp, 'EHLO ' . $ehlo_host . "\r\n");
        $ehlo2 = $baca($fp);
        if (!$expect($ehlo2, '250')) {
            fclose($fp);

            return ['ok' => false, 'pesan' => 'SMTP EHLO setelah TLS gagal.'];
        }
    }

    if ($user !== '') {
        fwrite($fp, "AUTH LOGIN\r\n");
        $auth = $baca($fp);
        if (!$expect($auth, '334')) {
            fclose($fp);

            return ['ok' => false, 'pesan' => 'SMTP AUTH tidak didukung.'];
        }
        fwrite($fp, base64_encode($user) . "\r\n");
        $user_resp = $baca($fp);
        if (!$expect($user_resp, '334')) {
            fclose($fp);

            return ['ok' => false, 'pesan' => 'SMTP username ditolak.'];
        }
        fwrite($fp, base64_encode($pass) . "\r\n");
        $pass_resp = $baca($fp);
        if (!$expect($pass_resp, '235')) {
            fclose($fp);

            return ['ok' => false, 'pesan' => 'SMTP login gagal — periksa user/password.'];
        }
    }

    $from_addr = notifikasi_email_smtp_ekstrak_alamat($from);
    if (!$kirim($fp, 'MAIL FROM:<' . $from_addr . '>')) {
        fclose($fp);

        return ['ok' => false, 'pesan' => 'SMTP MAIL FROM gagal.'];
    }
    if (!$kirim($fp, 'RCPT TO:<' . notifikasi_email_smtp_ekstrak_alamat($to) . '>')) {
        fclose($fp);

        return ['ok' => false, 'pesan' => 'SMTP RCPT TO gagal.'];
    }
    fwrite($fp, "DATA\r\n");
    $data_resp = $baca($fp);
    if (!$expect($data_resp, '354')) {
        fclose($fp);

        return ['ok' => false, 'pesan' => 'SMTP DATA ditolak.'];
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

    if (!$expect($send_resp, '250')) {
        return ['ok' => false, 'pesan' => 'SMTP pengiriman ditolak server.'];
    }

    return ['ok' => true, 'pesan' => 'Email terkirim ke ' . $to . '.'];
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