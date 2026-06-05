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
$jumlah_item = keranjang_hitung_jumlah_item();
$jumlah_produk = count($baris);
$bilah_pembeli_aktif = 'keranjang';
$u_beranda = aplikasi_url('');
$u_produk = aplikasi_url('produk');
$u_checkout = aplikasi_url('checkout');
$u_masuk = aplikasi_url('login/masuk.php');
$sudah_login = sudah_masuk();
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
<body class="halaman-toko halaman-keranjang">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="keranjang-wrap">
    <nav class="detail-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Katalog</a>
        <span aria-hidden="true"> / </span>
        <span>Keranjang</span>
    </nav>

    <header class="keranjang-hero">
        <div class="keranjang-hero__teks">
            <p class="keranjang-hero__eyebrow">EA SENIKERS</p>
            <h1 class="keranjang-hero__judul">Keranjang belanja</h1>
            <p class="keranjang-hero__deskripsi">
                <?php if ($baris === []): ?>
                    Simpan sneaker favoritmu di sini sebelum checkout.
                <?php else: ?>
                    <?php echo (int) $jumlah_produk; ?> produk · <?php echo (int) $jumlah_item; ?> item siap dibayar
                <?php endif; ?>
            </p>
        </div>
        <?php if ($baris !== []): ?>
        <div class="keranjang-hero__badge" aria-hidden="true">
            <span class="keranjang-hero__badge-angka"><?php echo (int) $jumlah_item; ?></span>
            <span class="keranjang-hero__badge-label">Item</span>
        </div>
        <?php endif; ?>
    </header>

    <?php if (is_string($flash_info) && $flash_info !== ''): ?>
        <div class="keranjang-alert keranjang-alert--info" role="status"><?php echo htmlspecialchars($flash_info, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (is_string($flash_checkout_error) && $flash_checkout_error !== ''): ?>
        <div class="keranjang-alert keranjang-alert--error" role="alert"><?php echo htmlspecialchars($flash_checkout_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($flash_ok_tambah): ?>
        <div class="keranjang-alert keranjang-alert--ok" role="status">Produk berhasil ditambahkan ke keranjang.</div>
    <?php endif; ?>

    <?php if ($baris === []): ?>
        <section class="keranjang-kosong" aria-labelledby="keranjang-kosong-judul">
            <div class="keranjang-kosong__ikon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c.432 0 .82.268.982.656l1.2 3c.149.373.456.624.8.624H18.75M7.5 14.25L5.106 5.272M15.75 14.25l2.106-8.978M9.75 18.75a1.125 1.125 0 100-2.25 1.125 1.125 0 000 2.25zm7.5 0a1.125 1.125 0 100-2.25 1.125 1.125 0 000 2.25z"/>
                </svg>
            </div>
            <h2 id="keranjang-kosong-judul" class="keranjang-kosong__judul">Keranjang masih kosong</h2>
            <p class="keranjang-kosong__teks">Jelajahi katalog, pilih ukuran, lalu tambahkan ke keranjang atau langsung beli.</p>
            <div class="keranjang-kosong__aksi">
                <a class="keranjang-tombol keranjang-tombol--utama" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Lihat katalog</a>
                <a class="keranjang-tombol keranjang-tombol--sekunder" href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">Beranda</a>
            </div>
        </section>
    <?php else: ?>
        <div class="keranjang-grid">
            <section class="keranjang-daftar" aria-label="Item keranjang">
                <div class="keranjang-daftar__header">
                    <h2 class="keranjang-daftar__judul">Produk di keranjang</h2>
                    <a class="keranjang-daftar__tambah" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">+ Tambah produk</a>
                </div>

                <ul class="keranjang-item-list">
                    <?php foreach ($baris as $r):
                        $nf = (string) ($r['nama_file'] ?? '');
                        $thumb = $nf !== '' ? katalog_url_gambar_produk($nf) : katalog_url_gambar_placeholder();
                        $kunci = (string) ($r['kunci'] ?? '');
                        $id_produk = (string) ($r['id_produk'] ?? '');
                        $u_detail = $id_produk !== ''
                            ? aplikasi_url('detail-produk?id=' . rawurlencode($id_produk))
                            : $u_produk;
                        $u_hapus = aplikasi_url('keranjang?hapus=' . rawurlencode($kunci));
                        $h = (int) ($r['harga'] ?? 0);
                        $q = (int) ($r['qty'] ?? 0);
                        $sub = $h * $q;
                        $kondisi_label = kondisi_label_pembeli((string) ($r['kondisi'] ?? ''));
                        ?>
                    <li class="keranjang-item">
                        <a class="keranjang-item__gambar-link" href="<?php echo htmlspecialchars($u_detail, ENT_QUOTES, 'UTF-8'); ?>">
                            <img class="keranjang-item__gambar" src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="96" height="96" loading="lazy">
                        </a>
                        <div class="keranjang-item__isi">
                            <p class="keranjang-item__brand"><?php echo htmlspecialchars((string) ($r['brand'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($kondisi_label, ENT_QUOTES, 'UTF-8'); ?></p>
                            <h3 class="keranjang-item__nama">
                                <a href="<?php echo htmlspecialchars($u_detail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($r['nama_produk'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
                            </h3>
                            <div class="keranjang-item__chip-wrap">
                                <span class="keranjang-chip">Ukuran <strong><?php echo htmlspecialchars((string) ($r['ukuran'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                                <span class="keranjang-chip">Qty <strong><?php echo (int) $q; ?></strong></span>
                            </div>
                        </div>
                        <div class="keranjang-item__harga">
                            <span class="keranjang-item__harga-satuan"><?php echo htmlspecialchars(katalog_format_rupiah($h), ENT_QUOTES, 'UTF-8'); ?> / pcs</span>
                            <span class="keranjang-item__subtotal"><?php echo htmlspecialchars(katalog_format_rupiah($sub), ENT_QUOTES, 'UTF-8'); ?></span>
                            <a class="keranjang-item__hapus" href="<?php echo htmlspecialchars($u_hapus, ENT_QUOTES, 'UTF-8'); ?>" title="Hapus dari keranjang">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                <span>Hapus</span>
                            </a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <aside class="keranjang-ringkas" aria-label="Ringkasan belanja">
                <div class="keranjang-ringkas__kartu">
                    <h2 class="keranjang-ringkas__judul">Ringkasan</h2>
                    <dl class="keranjang-ringkas__baris">
                        <div class="keranjang-ringkas__satu">
                            <dt>Subtotal (<?php echo (int) $jumlah_item; ?> item)</dt>
                            <dd><?php echo htmlspecialchars(katalog_format_rupiah($total), ENT_QUOTES, 'UTF-8'); ?></dd>
                        </div>
                        <div class="keranjang-ringkas__satu keranjang-ringkas__satu--muted">
                            <dt>Ongkos kirim</dt>
                            <dd>Dihitung di checkout</dd>
                        </div>
                    </dl>
                    <p class="keranjang-ringkas__total">
                        <span>Estimasi total</span>
                        <strong><?php echo htmlspecialchars(katalog_format_rupiah($total), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </p>
                    <p class="keranjang-ringkas__catatan">Ongkir mengikuti kurir, layanan, dan alamat tujuan Anda.</p>

                    <?php if ($sudah_login): ?>
                    <form class="keranjang-ringkas__form" method="post" action="<?php echo htmlspecialchars($u_checkout, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="aksi" value="dari_keranjang">
                        <button type="submit" class="keranjang-tombol keranjang-tombol--utama keranjang-tombol--blok">Lanjut ke checkout</button>
                    </form>
                    <?php else: ?>
                    <p class="keranjang-ringkas__login">Masuk dulu untuk melanjutkan pembayaran.</p>
                    <a class="keranjang-tombol keranjang-tombol--utama keranjang-tombol--blok" href="<?php echo htmlspecialchars($u_masuk, ENT_QUOTES, 'UTF-8'); ?>">Masuk untuk checkout</a>
                    <?php endif; ?>

                    <a class="keranjang-tombol keranjang-tombol--sekunder keranjang-tombol--blok" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">← Lanjut belanja</a>

                    <ul class="keranjang-trust" aria-label="Keunggulan">
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                            Produk original
                        </li>
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            Pembayaran aman
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</main>

</body>
</html>