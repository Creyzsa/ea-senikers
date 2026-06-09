<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/admin_notifikasi_repositori.php';

if (!sudah_masuk() || ambil_peran() !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Akses ditolak.']);

    exit;
}

$since = (int) ($_GET['since'] ?? 0);
$hasil = admin_notifikasi_poll($since);

echo json_encode([
    'ok' => true,
    'since' => $since,
    'max_id' => (int) $hasil['max_id'],
    'browser_aktif' => (bool) $hasil['browser_aktif'],
    'events' => $hasil['events'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);