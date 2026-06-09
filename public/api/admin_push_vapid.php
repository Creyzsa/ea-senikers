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

$hasil = admin_push_info_vapid();
echo json_encode([
    'ok' => (bool) ($hasil['ok'] ?? false),
    'pesan' => (string) ($hasil['pesan'] ?? ''),
    'public_key' => (string) ($hasil['public_key'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);