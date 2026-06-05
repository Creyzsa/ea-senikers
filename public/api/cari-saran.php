<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$q = trim((string) ($_GET['q'] ?? ''));
if (function_exists('mb_strlen')) {
    if (mb_strlen($q, 'UTF-8') > 80) {
        $q = mb_substr($q, 0, 80, 'UTF-8');
    }
} elseif (strlen($q) > 80) {
    $q = substr($q, 0, 80);
}
$hasil = katalog_saran_pencarian($q);

echo json_encode(
    [
        'q' => $q,
        'produk' => $hasil['produk'],
        'kata_kunci' => $hasil['kata_kunci'],
    ],
    JSON_UNESCAPED_UNICODE
);