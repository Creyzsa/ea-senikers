<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/keranjang_sesi.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

if (isset($_GET['hapus'])) {
    $hapus = trim((string) $_GET['hapus']);
    if ($hapus !== '') {
        keranjang_hapus_kunci($hapus);
        $_SESSION['flash_keranjang_info'] = 'Item dihapus dari keranjang.';
    }
    header('Location: ' . aplikasi_url('keranjang'));
    exit;
}

$flash_info = $_SESSION['flash_keranjang_info'] ?? null;
if ($flash_info !== null) {
    unset($_SESSION['flash_keranjang_info']);
}
$flash_checkout_error = $_SESSION['flash_checkout_error'] ?? null;
if ($flash_checkout_error !== null) {
    unset($_SESSION['flash_checkout_error']);
}
$flash_ok_tambah = !empty($_SESSION['flash_keranjang_ok']);
if ($flash_ok_tambah) {
    unset($_SESSION['flash_keranjang_ok']);
}

$baris = keranjang_ambil_baris();
$total = keranjang_total_rupiah();
$bilah_pembeli_aktif = 'keranjang';
$u_beranda = aplikasi_url(''); // clean root homepage
$u_produk = aplikasi_url('produk');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Keranjang — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
</head>
<body class="halaman-toko halaman-detail-produk">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<div class="detail-kontainer">
    <nav class="detail-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Katalog</a>
        <span aria-hidden="true"> / </span>
        <span>Keranjang</span>
    </nav>

    <main class="keranjang-utama">
        <h1 class="keranjang-judul">Keranjang belanja</h1>

        <?php if (is_string($flash_info) && $flash_info !== ''): ?>
            <p class="keranjang-flash keranjang-flash--info" role="status"><?php echo htmlspecialchars($flash_info, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (is_string($flash_checkout_error) && $flash_checkout_error !== ''): ?>
            <p class="keranjang-flash keranjang-flash--error" role="alert"><?php echo htmlspecialchars($flash_checkout_error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($flash_ok_tambah): ?>
            <p class="keranjang-flash keranjang-flash--ok" role="status">Produk ditambahkan ke keranjang.</p>
        <?php endif; ?>

        <?php if ($baris === []): ?>
            <div class="keranjang-kosong">
                <p>Keranjang masih kosong.</p>
                <p class="keranjang-kosong__aksi">
                    <a class="tautan-balik" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Lihat produk</a>
                    <a class="tautan-balik" href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">Beranda</a>
                </p>
            </div>
        <?php else: ?>
            <div class="keranjang-tabel-wrap">
                <table class="keranjang-tabel">
                    <thead>
                        <tr>
                            <th scope="col">Produk</th>
                            <th scope="col">Ukuran</th>
                            <th scope="col">Harga</th>
                            <th scope="col">Qty</th>
                            <th scope="col">Subtotal</th>
                            <th scope="col"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $r):
                            $nf = (string) ($r['nama_file'] ?? '');
                            $thumb = $nf !== '' ? katalog_url_gambar_produk($nf) : katalog_url_gambar_placeholder();
                            $kunci = (string) ($r['kunci'] ?? '');
                            $u_hapus = aplikasi_url('keranjang?hapus=' . rawurlencode($kunci));
                            $h = (int) ($r['harga'] ?? 0);
                            $q = (int) ($r['qty'] ?? 0);
                            $sub = $h * $q;
                            ?>
                        <tr>
                            <td>
                                <div class="keranjang-sel-produk">
                                    <img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="56" height="56" class="keranjang-thumb">
                                    <div>
                                        <p class="keranjang-nama"><?php echo htmlspecialchars((string) ($r['nama_produk'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p class="keranjang-meta"><?php echo htmlspecialchars((string) ($r['brand'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(kondisi_label_pembeli((string) ($r['kondisi'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($r['ukuran'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(katalog_format_rupiah($h), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $q; ?></td>
                            <td><strong><?php echo htmlspecialchars(katalog_format_rupiah($sub), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td>
                                <a class="keranjang-hapus" href="<?php echo htmlspecialchars($u_hapus, ENT_QUOTES, 'UTF-8'); ?>">Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="keranjang-ringkas">
                <p class="keranjang-total">Subtotal produk: <strong><?php echo htmlspecialchars(katalog_format_rupiah($total), ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <p class="keranjang-catatan">Ongkos kirim ditambahkan saat checkout, mengikuti pilihan kurir dan alamat tujuan Anda.</p>
                <?php if (sudah_masuk()): ?>
                <p class="keranjang-lanjut">
                    <form method="post" action="<?php echo htmlspecialchars(aplikasi_url('checkout'), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="aksi" value="dari_keranjang">
                        <button type="submit" class="tombol-oranye-besar">Lanjut ke Checkout →</button>
                    </form>
                </p>
                <?php else: ?>
                <p class="keranjang-catatan">Untuk melanjutkan ke checkout, silakan <a href="<?php echo htmlspecialchars(aplikasi_url('login/masuk.php'), ENT_QUOTES, 'UTF-8'); ?>">masuk</a> terlebih dahulu.</p>
                <?php endif; ?>
                <p class="keranjang-lanjut">
                    <a class="tautan-balik" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">← Lanjut belanja</a>
                </p>
            </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>
