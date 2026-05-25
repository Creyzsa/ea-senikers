<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/pesanan_repositori.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$bilah_pembeli_aktif = 'pesanan';
$id_pengguna = ambil_id_pengguna_efektif();
$tabel_ada = pesanan_cek_tabel_ada();
$daftar = $tabel_ada && $id_pengguna > 0 ? pesanan_ambil_oleh_user($id_pengguna) : [];

$labels = pesanan_status_label_id();
$badgeClass = pesanan_status_kelas_badge();
$filter_status = trim(is_string($_GET['status'] ?? null) ? (string) $_GET['status'] : '');
if ($filter_status !== '' && !array_key_exists($filter_status, $labels)) {
    $filter_status = '';
}

$hitung_status = array_fill_keys(array_keys($labels), 0);
foreach ($daftar as $order) {
    $st = (string) ($order['status'] ?? 'pending');
    if (isset($hitung_status[$st])) {
        $hitung_status[$st]++;
    }
}
$jumlah_pesanan = count($daftar);
$daftar_tampil = $filter_status === ''
    ? $daftar
    : array_values(array_filter($daftar, static fn (array $order): bool => (string) ($order['status'] ?? 'pending') === $filter_status));

function pesanan_format_tanggal(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '-';
    }
    try {
        $dt = new DateTimeImmutable($iso);

        return $dt->format('d M Y, H:i');
    } catch (Throwable $e) {
        return '-';
    }
}

function pesanan_url_filter_status(string $status): string
{
    $url = aplikasi_url('pembeli/pesanan_pembeli.php');
    return $status === '' ? $url : $url . '?status=' . rawurlencode($status);
}

$u_detail_base = aplikasi_url('pembeli/detail_pesanan_pembeli.php');
$u_produk = aplikasi_url('pembeli/produk.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pesanan saya - EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/pesanan-pembeli.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="pesanan-wrap" id="utama">
    <section class="pesanan-page-head" aria-labelledby="judul-pesanan">
        <div>
            <p class="section-eyebrow">Pesanan</p>
            <h1 id="judul-pesanan" class="pesanan-judul">Riwayat belanja Anda</h1>
            <p class="pesanan-sub">Pantau status pesanan dari pembayaran sampai barang selesai diterima.</p>
        </div>
        <a class="pesanan-tombol-belanja" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Belanja lagi</a>
    </section>

    <?php if (!$tabel_ada): ?>
        <div class="pesanan-setup-db" role="alert">
            Riwayat pesanan belum dapat ditampilkan. Hubungi admin toko atau coba lagi nanti.
        </div>
    <?php elseif ($id_pengguna <= 0): ?>
        <div class="pesanan-peringatan" role="alert">
            Akun Anda belum lengkap. Silakan <strong>Keluar</strong>, lalu <strong>Masuk</strong> lagi, atau hubungi layanan pelanggan.
        </div>
    <?php endif; ?>

    <?php if ($tabel_ada && $id_pengguna > 0): ?>
        <nav class="pesanan-filter" aria-label="Filter status pesanan">
            <a class="<?php echo $filter_status === '' ? 'pesanan-filter__item pesanan-filter__item--aktif' : 'pesanan-filter__item'; ?>" href="<?php echo htmlspecialchars(pesanan_url_filter_status(''), ENT_QUOTES, 'UTF-8'); ?>">
                <span>Semua</span>
                <strong><?php echo (string) $jumlah_pesanan; ?></strong>
            </a>
            <?php foreach ($labels as $status => $label): ?>
                <a class="<?php echo $filter_status === $status ? 'pesanan-filter__item pesanan-filter__item--aktif' : 'pesanan-filter__item'; ?>" href="<?php echo htmlspecialchars(pesanan_url_filter_status((string) $status), ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                    <strong><?php echo (string) ($hitung_status[$status] ?? 0); ?></strong>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($tabel_ada && $id_pengguna > 0 && $daftar_tampil === []): ?>
        <div class="pesanan-kosong">
            <div class="pesanan-kosong__ikon" aria-hidden="true"></div>
            <p class="pesanan-kosong__judul"><?php echo $filter_status === '' ? 'Belum ada pesanan' : 'Belum ada pesanan dengan status ini'; ?></p>
            <p style="margin:0;font-size:0.92rem;">Telusuri katalog dan pilih sneakers yang cocok untuk Anda.</p>
            <p style="margin:1rem 0 0;">
                <a class="pesanan-tombol-detail" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Lihat produk</a>
            </p>
        </div>
    <?php elseif ($tabel_ada && $id_pengguna > 0): ?>
        <div class="pesanan-grid">
            <?php foreach ($daftar_tampil as $order): ?>
                <?php
                $items = $order['items'] ?? [];
                $first = is_array($items) && $items !== [] ? $items[0] : null;
                $nItem = is_array($items) ? count($items) : 0;
                $st = (string) ($order['status'] ?? 'pending');
                $badge = $badgeClass[$st] ?? 'pesanan-badge pesanan-badge--kuning';
                $label = $labels[$st] ?? $st;
                $total = (int) ($order['total_price'] ?? 0);
                $oid = (int) ($order['id'] ?? 0);
                $namaTampil = $first
                    ? (string) ($first['product_name'] ?? 'Produk')
                    : 'Pesanan #' . $oid;
                if ($nItem > 1) {
                    $namaTampil .= ' +' . ($nItem - 1) . ' lainnya';
                }
                $imgUrl = $first ? pesanan_url_gambar_item($first) : katalog_url_gambar_placeholder();
                $uDetail = $u_detail_base . '?id=' . rawurlencode((string) $oid);
                ?>
                <article class="pesanan-kartu">
                    <img class="pesanan-kartu__gambar" src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="96" height="96" loading="lazy">
                    <div class="pesanan-kartu__isi">
                        <p class="pesanan-kartu__nomor">Pesanan #<?php echo (string) $oid; ?></p>
                        <h2 class="pesanan-kartu__nama"><?php echo htmlspecialchars($namaTampil, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="pesanan-kartu__meta"><?php echo htmlspecialchars(pesanan_format_tanggal(isset($order['created_at']) ? (string) $order['created_at'] : null), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="pesanan-kartu__harga"><?php echo htmlspecialchars(katalog_format_rupiah($total), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="pesanan-kartu__aksi">
                        <span class="<?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                        <a class="pesanan-tombol-detail" href="<?php echo htmlspecialchars($uDetail, ENT_QUOTES, 'UTF-8'); ?>">
                            Lihat detail
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
