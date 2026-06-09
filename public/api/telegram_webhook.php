<?php

declare(strict_types=1);

/**
 * Webhook Telegram Bot API: Telegram → server EA SENIKERS.
 * Dipasang lewat setWebhook (tombol di panel Notifikasi admin).
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/repositori/admin_notifikasi_repositori.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Gunakan POST.']);

    exit;
}

$cfg = admin_notifikasi_muat_pengaturan();
$bot_token = trim((string) ($cfg['telegram_bot_token'] ?? ''));
if ($bot_token === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'pesan' => 'Bot Telegram belum dikonfigurasi.']);

    exit;
}

$secret_diharapkan = notifikasi_telegram_secret_webhook($bot_token);
$secret_header = trim((string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''));
if ($secret_header === '' || !hash_equals($secret_diharapkan, $secret_header)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Secret token webhook tidak valid.']);

    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Payload kosong.']);

    exit;
}

$update = json_decode($raw, true);
if (!is_array($update)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'JSON tidak valid.']);

    exit;
}

try {
    admin_notifikasi_telegram_proses_webhook_update($update, $cfg);
} catch (Throwable $e) {
    error_log('[telegram_webhook] ' . $e->getMessage());
}

echo json_encode(['ok' => true]);