<?php

declare(strict_types=1);

/**
 * Alias nama file singkat → detail pesanan (?id=).
 */
require_once __DIR__ . '/../../includes/url_bantu.php';

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
$tujuan = aplikasi_url('pembeli/detail_pesanan_pembeli.php');
if ($id !== '') {
    $tujuan .= '?id=' . rawurlencode($id);
}

header('Location: ' . $tujuan, true, 302);
exit;
