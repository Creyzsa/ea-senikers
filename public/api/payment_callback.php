<?php

declare(strict_types=1);

/**
 * Webhook Pakasir: pembayaran sukses → status pesanan `paid`.
 *
 * Payload (JSON): project, order_id, amount, payment_method, status, completed_at
 * Status `completed` diverifikasi ulang ke API Pakasir sebelum update DB.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/auth_db/database.php';
require_once __DIR__ . '/../../includes/repositori/pesanan_repositori.php';
require_once __DIR__ . '/../../includes/integrasi/pakasir.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Gunakan POST.']);

    exit;
}

if (!pakasir_siap()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'pesan' => 'Pakasir belum dikonfigurasi di Pengaturan admin.']);

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

if ($data === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Payload kosong.']);

    exit;
}

$order_id_db_awal = pakasir_parse_order_id_db((string) ($data['order_id'] ?? ''));
$amount_webhook = (int) ($data['amount'] ?? 0);

if ($order_id_db_awal <= 0 || $amount_webhook < 500) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'order_id atau amount tidak valid.']);

    exit;
}

if (!pesanan_cek_tabel_ada()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Tabel orders belum tersedia.']);

    exit;
}

try {
    $pdo = koneksi_database();
    $stmt = $pdo->prepare('SELECT id, status, total_price FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $order_id_db_awal]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'pesan' => 'Pesanan tidak ditemukan.']);

        exit;
    }

    $expected_amount = (int) ($row['total_price'] ?? 0);
    if ($expected_amount < 500) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'pesan' => 'Total pesanan tidak valid.']);

        exit;
    }

    $st = (string) ($row['status'] ?? '');
    if ($st === 'cancelled' || $st === 'completed') {
        http_response_code(409);
        echo json_encode(['ok' => false, 'pesan' => 'Status pesanan tidak bisa diubah ke paid.']);

        exit;
    }
    if ($st === 'paid') {
        echo json_encode(['ok' => true, 'pesan' => 'Sudah paid.', 'order_id' => $order_id_db_awal]);

        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Database error.']);

    exit;
}

$verif = pakasir_verifikasi_webhook($data, $expected_amount);
if (!$verif['ok']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => (string) ($verif['error'] ?? 'Webhook tidak valid.')]);

    exit;
}

$order_id = (int) ($verif['order_id_db'] ?? 0);
$metode_kode = (string) ($verif['payment_method'] ?? '');
$label_bayar = 'Pakasir · ' . pakasir_label_metode($metode_kode);
pesanan_perbarui_metode_bayar($order_id, $label_bayar);

$ok = pesanan_set_status_oleh_id($order_id, 'paid', 'pakasir');
if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Gagal memperbarui status.']);

    exit;
}

echo json_encode([
    'ok' => true,
    'pesan' => 'Status diperbarui menjadi paid.',
    'order_id' => $order_id,
]);