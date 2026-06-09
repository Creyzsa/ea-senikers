<?php
declare(strict_types=1);

/**
 * Cek status gambar semua produk di database.
 * php scripts/cek_gambar_produk.php
 */
require_once __DIR__ . '/../includes/auth_db/database.php';
require_once __DIR__ . '/../includes/repositori/katalog_produk.php';

putenv('VERCEL=1');

$pdo = koneksi_database();
$stmt = $pdo->query(
    'SELECT p.nama_produk,
            COALESCE(
                (SELECT string_agg(pg.nama_file, \', \' ORDER BY pg.urutan)
                 FROM produk_gambar pg WHERE pg.id_produk = p.id_produk),
                \'(tanpa gambar di DB)\'
            ) AS gambar
     FROM produk p ORDER BY p.nama_produk'
);

echo "Mode cloud: " . (produk_gambar_pakai_cloud() ? 'ya' : 'tidak') . "\n\n";
foreach ($stmt->fetchAll() as $r) {
    $gambar = (string) $r['gambar'];
    echo $r['nama_produk'] . " → " . $gambar;
    if ($gambar !== '(tanpa gambar di DB)') {
        foreach (array_map('trim', explode(',', $gambar)) as $f) {
            $u = katalog_url_gambar_produk($f);
            echo str_starts_with($u, 'data:') ? ' [PLACEHOLDER]' : ' [OK]';
        }
    } else {
        echo ' ← upload ulang lewat admin';
    }
    echo "\n";
}