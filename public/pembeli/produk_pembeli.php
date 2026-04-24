<?php
/**
 * URL lama — dialihkan ke katalog produk (Supabase).
 */
require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

header('Location: ' . aplikasi_url('pembeli/produk.php'), true, 302);
exit;
