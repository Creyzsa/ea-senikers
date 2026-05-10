<?php
declare(strict_types=1);

/** Langsung bayar satu item dari detail produk (tanpa menyimpan ke keranjang). */
require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/katalog_produk.php';
require_once __DIR__ . '/../../includes/keranjang_sesi.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . aplikasi_url('pembeli/produk.php'));
    exit;
}

$id = trim((string) ($_POST['id_produk'] ?? ''));
$ukuran = trim((string) ($_POST['ukuran'] ?? ''));
$u_kembali = aplikasi_url('pembeli/detail_produk.php?id=' . rawurlencode($id));

if ($id === '' || $ukuran === '') {
    header('Location: ' . aplikasi_url('pembeli/produk.php'));
    exit;
}

$produk = katalog_ambil_produk_ber_id($id);
if ($produk === null) {
    header('Location: ' . aplikasi_url('pembeli/produk.php'));
    exit;
}

$stok_ok = false;
foreach ($produk['produk_ukuran'] ?? [] as $u) {
    if ((string) ($u['ukuran'] ?? '') === $ukuran && (int) ($u['stok'] ?? 0) > 0) {
        $stok_ok = true;
        break;
    }
}
if (!$stok_ok) {
    header('Location: ' . $u_kembali);
    exit;
}

$bilah_keranjang_jumlah = keranjang_hitung_jumlah_item();
$bilah_pembeli_aktif = 'produk';

$nama = (string) ($produk['nama_produk'] ?? '');
$harga = (int) ($produk['harga'] ?? 0);
$brand = (string) ($produk['brand'] ?? '');
$kondisi = (string) ($produk['kondisi'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
</head>
<body class="halaman-toko halaman-detail-produk">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<div class="detail-kontainer">
    <nav class="detail-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo htmlspecialchars(aplikasi_url('pembeli/produk.php'), ENT_QUOTES, 'UTF-8'); ?>">Katalog</a>
        <span aria-hidden="true"> / </span>
        <span>Checkout</span>
    </nav>

    <article class="detail-kartu" style="max-width:36rem;margin:0 auto;">
        <div class="detail-panel">
            <h1 style="margin:0 0 0.5rem;font-size:1.2rem;">Ringkasan pesanan</h1>
            <p style="margin:0 0 1rem;color:#6b7280;font-size:0.9rem;"><strong>Pembelian langsung</strong> — Anda tidak menggunakan keranjang untuk item ini.</p>

            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:1rem;margin-bottom:1.25rem;">
                <p style="margin:0 0 0.25rem;font-size:0.75rem;font-weight:700;text-transform:uppercase;color:var(--oranye-cta-hover);"><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></p>
                <p style="margin:0 0 0.5rem;font-weight:800;"><?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></p>
                <p style="margin:0;font-size:0.88rem;color:#374151;">
                    Ukuran: <strong><?php echo htmlspecialchars($ukuran, ENT_QUOTES, 'UTF-8'); ?></strong>
                    · Kondisi: <?php echo htmlspecialchars($kondisi, ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <p style="margin:0.65rem 0 0;font-size:1.25rem;font-weight:800;color:var(--oranye-cta-hover);"><?php echo htmlspecialchars(katalog_format_rupiah($harga), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div style="border:1px dashed #d1d5db;border-radius:10px;padding:1rem;background:#fffbeb;">
                <p style="margin:0 0 0.35rem;font-weight:700;color:#92400e;">Langkah lanjutan</p>
                <p style="margin:0;font-size:0.9rem;line-height:1.5;color:#78350f;">
                    Silakan lakukan pembayaran mengikuti petunjuk yang Anda terima atau hubungi kami untuk konfirmasi alamat kirim dan pembayaran.
                </p>
            </div>

            <p style="margin-top:1.25rem;">
                <a class="tautan-balik" href="<?php echo htmlspecialchars($u_kembali, ENT_QUOTES, 'UTF-8'); ?>">← Kembali ke produk</a>
                <a class="tautan-balik" href="<?php echo htmlspecialchars(aplikasi_url('pembeli/keranjang_pembeli.php'), ENT_QUOTES, 'UTF-8'); ?>" style="margin-left:1rem;">Lihat keranjang</a>
            </p>
        </div>
    </article>
</div>

</body>
</html>
