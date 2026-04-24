<?php
require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/katalog_produk.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$bilah_pembeli_aktif = 'produk';
$u_beranda = aplikasi_url('pembeli/beranda_pembeli.php');
$daftar_produk = katalog_ambil_semua_produk();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Katalog produk — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
</head>
<body class="halaman-toko halaman-katalog">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<div class="katalog-latar">
    <div class="katalog-kontainer">
        <div class="katalog-judul-bar">
            <h1>Katalog sepatu</h1>
            <p><?php echo count($daftar_produk); ?> produk</p>
        </div>

        <?php if ($daftar_produk === []): ?>
            <div class="katalog-kosong">
                <strong>Belum ada produk atau koneksi gagal</strong>
                Pastikan tabel Supabase sudah dibuat (jalankan <code>schema_katalog_produk.sql</code>) dan URL/key di <code>config.php</code> benar.
            </div>
        <?php else: ?>
            <div class="katalog-grid">
                <?php foreach ($daftar_produk as $p):
                    $id = (string) ($p['id_produk'] ?? '');
                    $nama = (string) ($p['nama_produk'] ?? '');
                    $brand = (string) ($p['brand'] ?? '');
                    $kondisi = (string) ($p['kondisi'] ?? '');
                    $harga = (int) ($p['harga'] ?? 0);
                    $url_detail = aplikasi_url('pembeli/detail_produk.php?id=' . rawurlencode($id));
                    $url_gambar = katalog_url_gambar_utama($p);
                    $kelas_kondisi = strcasecmp($kondisi, 'Baru') === 0 ? 'kartu-katalog__badge-kondisi--baru' : 'kartu-katalog__badge-kondisi--second';
                    ?>
                <a class="kartu-katalog" href="<?php echo htmlspecialchars($url_detail, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="kartu-katalog__gambar-wrap">
                        <img class="kartu-katalog__gambar" src="<?php echo htmlspecialchars($url_gambar, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" width="400" height="400">
                        <span class="kartu-katalog__badge-kondisi <?php echo htmlspecialchars($kelas_kondisi, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($kondisi, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="kartu-katalog__isi">
                        <span class="kartu-katalog__brand"><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></span>
                        <p class="kartu-katalog__nama"><?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="kartu-katalog__harga"><?php echo htmlspecialchars(katalog_format_rupiah($harga), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p style="margin-top:1.25rem;text-align:center;">
            <a class="tautan-keluar-kecil" href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--oranye-cta);text-decoration:none;font-weight:700;">← Beranda</a>
        </p>
    </div>
</div>

</body>
</html>
