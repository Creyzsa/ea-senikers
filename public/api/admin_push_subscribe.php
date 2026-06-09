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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Metode tidak diizinkan.'], JSON_UNESCAPED_UNICODE);

    exit;
}

$raw = file_get_contents('php://input');
$data = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'JSON tidak valid.'], JSON_UNESCAPED_UNICODE);

    exit;
}

$aksi = (string) ($data['aksi'] ?? 'subscribe');
$user_id = (int) ($_SESSION['id_pengguna'] ?? 0);

if ($aksi === 'unsubscribe') {
    $endpoint = (string) ($data['endpoint'] ?? '');
    $hasil = admin_push_hapus_langganan($endpoint);
} else {
    $hasil = admin_push_simpan_langganan($user_id, $data);
}

echo json_encode([
    'ok' => (bool) ($hasil['ok'] ?? false),
    'pesan' => (string) ($hasil['pesan'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);