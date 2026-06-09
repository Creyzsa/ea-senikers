<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/admin_notifikasi_repositori.php';

if (!sudah_masuk() || ambil_peran() !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Akses ditolak.'], JSON_UNESCAPED_UNICODE);

    exit;
}

$read_until = (int) ($_GET['read_until'] ?? 0);
$limit = (int) ($_GET['limit'] ?? 20);
$hasil = admin_notifikasi_panel($read_until, $limit);

echo json_encode([
    'ok' => true,
    'read_until' => $read_until,
    'max_id' => (int) $hasil['max_id'],
    'unread_count' => (int) $hasil['unread_count'],
    'recent' => $hasil['recent'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);