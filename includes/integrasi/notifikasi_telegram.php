<?php

declare(strict_types=1);

require_once __DIR__ . '/../url_bantu.php';

function notifikasi_telegram_url_webhook(): string
{
    return aplikasi_url('api/telegram_webhook.php');
}

function notifikasi_telegram_secret_webhook(string $bot_token): string
{
    return substr(hash('sha256', 'easenikers-tg-wh-' . trim($bot_token)), 0, 32);
}

/**
 * @return array<int, mixed>
 */
function notifikasi_telegram_curl_ssl_opts(): array
{
    $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if (is_string($ca) && $ca !== '' && is_readable($ca)) {
        return [CURLOPT_CAINFO => $ca];
    }

    return [
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];
}

/**
 * @param array<string, mixed> $params
 * @return array{ok: bool, pesan: string, data: mixed}
 */
function notifikasi_telegram_api(string $bot_token, string $method, array $params = []): array
{
    $bot_token = trim($bot_token);
    if ($bot_token === '') {
        return ['ok' => false, 'pesan' => 'Token bot Telegram kosong.', 'data' => null];
    }

    $url = 'https://api.telegram.org/bot' . $bot_token . '/' . trim($method);
    $json = json_encode($params);
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
    ] + notifikasi_telegram_curl_ssl_opts());
    $raw = curl_exec($ch);
    $errno = (int) curl_errno($ch);
    $err = (string) curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($errno !== 0 || !is_string($raw)) {
        return ['ok' => false, 'pesan' => 'Telegram: ' . ($err !== '' ? $err : 'koneksi gagal'), 'data' => null];
    }

    $data = json_decode($raw, true);
    if ($http >= 200 && $http < 300 && is_array($data) && !empty($data['ok'])) {
        return ['ok' => true, 'pesan' => 'OK', 'data' => $data];
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
 * @return array{ok: bool, pesan: string, data: mixed}
 */
function notifikasi_telegram_daftarkan_webhook(string $bot_token): array
{
    $hasil = notifikasi_telegram_api($bot_token, 'setWebhook', [
        'url' => notifikasi_telegram_url_webhook(),
        'secret_token' => notifikasi_telegram_secret_webhook($bot_token),
        'allowed_updates' => ['message', 'edited_message'],
        'drop_pending_updates' => true,
    ]);
    if (!$hasil['ok']) {
        return $hasil;
    }

    return [
        'ok' => true,
        'pesan' => 'Webhook Telegram terdaftar ke ' . notifikasi_telegram_url_webhook(),
        'data' => $hasil['data'],
    ];
}

/**
 * @return array{ok: bool, pesan: string, data: mixed}
 */
function notifikasi_telegram_hapus_webhook(string $bot_token): array
{
    $hasil = notifikasi_telegram_api($bot_token, 'deleteWebhook', ['drop_pending_updates' => true]);
    if (!$hasil['ok']) {
        return $hasil;
    }

    return ['ok' => true, 'pesan' => 'Webhook Telegram dihapus. Bot kembali ke mode polling.', 'data' => $hasil['data']];
}

/**
 * @return array{ok: bool, pesan: string, url: string, pending: int}
 */
function notifikasi_telegram_info_webhook(string $bot_token): array
{
    $hasil = notifikasi_telegram_api($bot_token, 'getWebhookInfo', []);
    if (!$hasil['ok'] || !is_array($hasil['data'])) {
        return ['ok' => false, 'pesan' => (string) ($hasil['pesan'] ?? 'Gagal memuat info webhook.'), 'url' => '', 'pending' => 0];
    }

    $result = $hasil['data']['result'] ?? null;
    if (!is_array($result)) {
        return ['ok' => false, 'pesan' => 'Respons getWebhookInfo tidak valid.', 'url' => '', 'pending' => 0];
    }

    return [
        'ok' => true,
        'pesan' => 'OK',
        'url' => trim((string) ($result['url'] ?? '')),
        'pending' => (int) ($result['pending_update_count'] ?? 0),
    ];
}

/**
 * @param array<string, mixed> $update
 * @return array{chat_id: string, text: string, label: string}|null
 */
function notifikasi_telegram_ekstrak_pesan_update(array $update): ?array
{
    $msg = $update['message'] ?? $update['edited_message'] ?? null;
    if (!is_array($msg)) {
        return null;
    }
    $chat = $msg['chat'] ?? null;
    if (!is_array($chat)) {
        return null;
    }

    $chat_id = trim((string) ($chat['id'] ?? ''));
    if ($chat_id === '') {
        return null;
    }

    $label = trim((string) ($chat['username'] ?? $chat['first_name'] ?? ''));
    $text = trim((string) ($msg['text'] ?? ''));

    return ['chat_id' => $chat_id, 'text' => $text, 'label' => $label];
}

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

    $body = [
        'chat_id' => $chat_id,
        'text' => $teks,
        'disable_web_page_preview' => true,
    ];
    if ($html) {
        $body['parse_mode'] = 'HTML';
    }

    $hasil = notifikasi_telegram_api($bot_token, 'sendMessage', $body);
    if ($hasil['ok']) {
        return ['ok' => true, 'pesan' => 'Pesan Telegram terkirim.', 'data' => $hasil['data']];
    }

    return $hasil;
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
    ] + notifikasi_telegram_curl_ssl_opts());
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