<?php

declare(strict_types=1);

/**
 * Webhook payment gateway: setelah pembayaran sukses → status pesanan jadi `paid`.
 *
 * POST JSON: { "order_id": 123, "secret": "sama dengan PAYMENT_CALLBACK_SECRET di config.php" }
 * Atau form: order_id + secret
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/config_loader.php';
require_once __DIR__ . '/../../includes/auth_db/database.php';
require_once __DIR__ . '/../../includes/repositori/pesanan_repositori.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Gunakan POST.']);

    exit;
}

$secretCfg = defined('PAYMENT_CALLBACK_SECRET') ? (string) PAYMENT_CALLBACK_SECRET : '';
if ($secretCfg === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'pesan' => 'Callback belum dikonfigurasi (PAYMENT_CALLBACK_SECRET kosong).']);

    exit;
}

$raw = file_get_contents('php://input');
$data = [];
if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
if ($data === []) {
    $data = $_POST;
}

$secretIn = (string) ($data['secret'] ?? '');
$orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;

if (!hash_equals($secretCfg, $secretIn)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Secret tidak valid.']);

    exit;
}

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'order_id wajib.']);

    exit;
}

if (!pesanan_cek_tabel_ada()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Tabel orders belum tersedia.']);

    exit;
}

try {
    $pdo = koneksi_database();
    $stmt = $pdo->prepare('SELECT id, status FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $orderId]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'pesan' => 'Pesanan tidak ditemukan.']);

        exit;
    }
    $st = (string) ($row['status'] ?? '');
    if ($st === 'cancelled' || $st === 'completed') {
        http_response_code(409);
        echo json_encode(['ok' => false, 'pesan' => 'Status pesanan tidak bisa diubah ke paid.']);

        exit;
    }
    if ($st === 'paid') {
        echo json_encode(['ok' => true, 'pesan' => 'Sudah paid.', 'order_id' => $orderId]);

        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Database error.']);

    exit;
}

$ok = pesanan_set_status_oleh_id($orderId, 'paid');
if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Gagal memperbarui status.']);

    exit;
}

echo json_encode([
    'ok' => true,
    'pesan' => 'Status diperbarui menjadi paid.',
    'order_id' => $orderId,
]);
