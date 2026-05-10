<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/sesi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$bilah_pembeli_aktif = 'tentang';
$u_beranda = aplikasi_url('pembeli/beranda_pembeli.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tentang — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <main class="kontainer-toko" id="utama">
        <div class="panel-pembeli-teks">
            <h1>Tentang EA SENIKERS</h1>
            <p>Kami menyediakan sepatu berkualitas mulai dari sneakers baru hingga pilihan preloved terkurasi, dengan deskripsi jujur dan harga yang transparan.</p>
            <p style="margin-top:0.75rem;">Informasi kontak lengkap dapat dilihat di bagian footer beranda.</p>
            <p style="margin-top:0.75rem;">
                <a class="tautan-balik" href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">← Beranda</a>
            </p>
        </div>
    </main>

</body>
</html>
