<?php

declare(strict_types=1);

/**
 * Kirim pesan Telegram Bot API.
 *
 * @return array{ok: bool, pesan: string, data: mixed}
 */
function notifikasi_telegram_kirim(string $bot_token, string $chat_id, string $teks, bool $html = true): array
{
    $bot_token = trim($bot_token);
    $chat_id = trim($chat_id);
    if ($bot_token === '' || $chat_id === '') {
        return ['ok' => false, 'pesan' => 'Token bot atau Chat ID Telegram kosong.', 'data' => null];
    }

    $url = 'https://api.telegram.org/bot' . $bot_token . '/sendMessage';
    $body = [
        'chat_id' => $chat_id,
        'text' => $teks,
        'disable_web_page_preview' => true,
    ];
    if ($html) {
        $body['parse_mode'] = 'HTML';
    }

    $json = json_encode($body);
    if (!is_string($json)) {
        return ['ok' => false, 'pesan' => 'Gagal menyiapkan payload Telegram.', 'data' => null];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $errno = (int) curl_errno($ch);
    $err = (string) curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($errno !== 0 || !is_string($raw)) {
        return ['ok' => false, 'pesan' => 'Telegram: ' . ($err !== '' ? $err : 'koneksi gagal'), 'data' => null];
    }

    $data = json_decode($raw, true);
    if ($http >= 200 && $http < 300 && is_array($data) && !empty($data['ok'])) {
        return ['ok' => true, 'pesan' => 'Pesan Telegram terkirim.', 'data' => $data];
    }

    $pesan_api = '';
    if (is_array($data)) {
        $pesan_api = trim((string) ($data['description'] ?? ''));
    }

    return [
        'ok' => false,
        'pesan' => $pesan_api !== '' ? $pesan_api : 'Telegram HTTP ' . $http,
        'data' => $data,
    ];
}

/**
 * Ambil chat_id terbaru dari pesan masuk ke bot (getUpdates).
 *
 * @return array{ok: bool, chat_id: string, pesan: string}
 */
function notifikasi_telegram_ambil_chat_id(string $bot_token): array
{
    $bot_token = trim($bot_token);
    if ($bot_token === '') {
        return ['ok' => false, 'chat_id' => '', 'pesan' => 'Token bot kosong.'];
    }

    $url = 'https://api.telegram.org/bot' . $bot_token . '/getUpdates?limit=20';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (!is_string($raw) || $http < 200 || $http >= 300) {
        return ['ok' => false, 'chat_id' => '', 'pesan' => 'Gagal memanggil getUpdates Telegram.'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['ok']) || !is_array($data['result'] ?? null)) {
        return ['ok' => false, 'chat_id' => '', 'pesan' => 'Respons getUpdates tidak valid.'];
    }

    $chat_id = '';
    $nama = '';
    foreach (array_reverse($data['result']) as $update) {
        if (!is_array($update)) {
            continue;
        }
        $msg = $update['message'] ?? $update['edited_message'] ?? null;
        if (!is_array($msg)) {
            continue;
        }
        $chat = $msg['chat'] ?? null;
        if (!is_array($chat)) {
            continue;
        }
        $id = (string) ($chat['id'] ?? '');
        if ($id !== '') {
            $chat_id = $id;
            $nama = trim((string) ($chat['username'] ?? $chat['first_name'] ?? ''));
            break;
        }
    }

    if ($chat_id === '') {
        return [
            'ok' => false,
            'chat_id' => '',
            'pesan' => 'Belum ada pesan ke bot. Buka bot di Telegram, kirim /start, lalu coba lagi.',
        ];
    }

    $pesan = 'Chat ID ditemukan: ' . $chat_id;
    if ($nama !== '') {
        $pesan .= ' (@' . $nama . ')';
    }

    return ['ok' => true, 'chat_id' => $chat_id, 'pesan' => $pesan];
}