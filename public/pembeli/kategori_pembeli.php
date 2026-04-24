<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/sesi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$bilah_pembeli_aktif = 'kategori';
$u_produk = aplikasi_url('pembeli/produk.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kategori — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <main class="kontainer-toko" id="utama">
        <div class="panel-pembeli-teks">
            <h1>Kategori</h1>
            <p>Filter kategori produk (misalnya sneakers, apparel) bisa dihubungkan ke kolom kategori di katalog nanti.</p>
            <p style="margin-top:0.75rem;">
                <a class="tautan-balik" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">← Lihat semua produk</a>
            </p>
        </div>
    </main>

</body>
</html>
