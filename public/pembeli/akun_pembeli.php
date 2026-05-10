<?php
require_once __DIR__ . '/../../includes/sesi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$bilah_pembeli_aktif = 'akun';
$u_beranda = aplikasi_url('pembeli/beranda_pembeli.php');
$u_produk = aplikasi_url('pembeli/produk.php');
$u_pesanan = aplikasi_url('pembeli/pesanan_pembeli.php');
$u_keranjang = aplikasi_url('pembeli/keranjang_pembeli.php');
$u_keluar = aplikasi_url('login/keluar.php');
$kontak_toko = require __DIR__ . '/../../includes/kontak_toko.php';

$nama = trim((string) ($_SESSION['nama_pengguna'] ?? ''));
$email = trim((string) ($_SESSION['email_pengguna'] ?? ''));
$nama_tampil = $nama !== '' ? $nama : 'Pembeli EA SENIKERS';
$email_tampil = $email !== '' ? $email : 'Email belum tersedia';
$inisial = strtoupper(function_exists('mb_substr') ? mb_substr($nama_tampil, 0, 1, 'UTF-8') : substr($nama_tampil, 0, 1));
$wa_utama = '';
foreach ((array) ($kontak_toko['wa'] ?? []) as $wa) {
    $e164 = preg_replace('/\D+/', '', (string) ($wa['e164'] ?? ''));
    if ($e164 !== '') {
        $wa_utama = 'https://wa.me/' . $e164;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akun saya - EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <main class="kontainer-toko halaman-akun" id="utama">
        <section class="akun-hero" aria-labelledby="judul-akun">
            <div class="akun-avatar" aria-hidden="true"><?php echo htmlspecialchars($inisial, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="akun-hero__isi">
                <p class="section-eyebrow">Akun pembeli</p>
                <h1 id="judul-akun"><?php echo htmlspecialchars($nama_tampil, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php echo htmlspecialchars($email_tampil, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <a class="tombol-page-sekunder akun-hero__keluar" href="<?php echo htmlspecialchars($u_keluar, ENT_QUOTES, 'UTF-8'); ?>">Keluar</a>
        </section>

        <section class="akun-grid" aria-label="Informasi akun">
            <article class="akun-info-card">
                <span>Nama tampil</span>
                <strong><?php echo htmlspecialchars($nama_tampil, ENT_QUOTES, 'UTF-8'); ?></strong>
                <p>Digunakan untuk sapaan dan identitas pemesanan.</p>
            </article>
            <article class="akun-info-card">
                <span>Email</span>
                <strong><?php echo htmlspecialchars($email_tampil, ENT_QUOTES, 'UTF-8'); ?></strong>
                <p>Dipakai untuk akses akun dan komunikasi transaksi.</p>
            </article>
            <article class="akun-info-card">
                <span>Status</span>
                <strong>Pembeli aktif</strong>
                <p>Akun dapat mengakses katalog, keranjang, checkout, dan riwayat pesanan.</p>
            </article>
        </section>

        <section class="akun-section" aria-labelledby="judul-akses">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Akses cepat</p>
                    <h2 id="judul-akses">Lanjutkan aktivitas belanja</h2>
                </div>
            </div>
            <div class="akun-action-grid">
                <a href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Katalog produk</strong>
                    <span>Cari sepatu baru atau preloved.</span>
                </a>
                <a href="<?php echo htmlspecialchars($u_keranjang, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Keranjang</strong>
                    <span>Lihat produk yang sudah disimpan.</span>
                </a>
                <a href="<?php echo htmlspecialchars($u_pesanan, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Pesanan saya</strong>
                    <span>Pantau pembayaran dan pengiriman.</span>
                </a>
                <?php if ($wa_utama !== ''): ?>
                    <a href="<?php echo htmlspecialchars($wa_utama, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <strong>Layanan pelanggan</strong>
                        <span>Tanyakan ukuran, kondisi, atau ketersediaan.</span>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <p class="akun-kembali">
            <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">&larr; Kembali ke beranda</a>
        </p>
    </main>

</body>
</html>
