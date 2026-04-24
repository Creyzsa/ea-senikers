<?php

declare(strict_types=1);

/**
 * Alias nama file singkat → daftar pesanan.
 */
require_once __DIR__ . '/../../includes/url_bantu.php';

header('Location: ' . aplikasi_url('pembeli/pesanan_pembeli.php'), true, 302);
exit;
