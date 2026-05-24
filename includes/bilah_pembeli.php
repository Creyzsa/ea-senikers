<?php
/**
 * Bilah navigasi area pembeli (header sticky).
 * Set sebelum include:
 *   $bilah_pembeli_aktif — 'beranda' | 'produk' | 'kategori' | 'pesanan' | 'tentang' | 'keranjang' | 'akun'
 *   $bilah_keranjang_jumlah — int (opsional; bila tidak di-set dipakai jumlah item dari sesi keranjang)
 */
require_once __DIR__ . '/url_bantu.php';
require_once __DIR__ . '/keranjang_sesi.php';
require_once __DIR__ . '/katalog_produk.php';

$bp_aktif = isset($bilah_pembeli_aktif) ? (string) $bilah_pembeli_aktif : 'beranda';
$bp_kj = isset($bilah_keranjang_jumlah) ? (int) $bilah_keranjang_jumlah : keranjang_hitung_jumlah_item();
if ($bp_kj < 0) {
    $bp_kj = 0;
}

$u_logo = aplikasi_url('assets/images/logo-easenikers.svg');
$u_beranda = aplikasi_url('pembeli/beranda_pembeli.php');
$u_produk = aplikasi_url('pembeli/produk.php');
$u_kategori = aplikasi_url('pembeli/kategori_pembeli.php');
$u_pesanan = aplikasi_url('pembeli/pesanan_pembeli.php');
$u_tentang = aplikasi_url('pembeli/tentang_pembeli.php');
$u_keranjang = aplikasi_url('pembeli/keranjang_pembeli.php');
$u_akun = aplikasi_url('pembeli/akun_pembeli.php');
$u_keluar = aplikasi_url('login/keluar.php');

$bp_meta = katalog_ambil_meta_navigasi();
$bp_brands = $bp_meta['brands'] ?? [];
$bp_kondisi_preloved = $bp_meta['kondisi_preloved'] ?? 'Second';
$bp_u_baru = aplikasi_url('pembeli/produk.php?kondisi=' . rawurlencode('Baru'));
$bp_u_preloved = aplikasi_url('pembeli/produk.php?kondisi=' . rawurlencode((string) $bp_kondisi_preloved));
$bp_u_termurah = aplikasi_url('pembeli/produk.php?sort=harga_asc');
$bp_u_terbaru = aplikasi_url('pembeli/produk.php?sort=terbaru');
$bp_u_js = aplikasi_url('assets/js/bilah-kategori.js');
?>
<div class="bilah-toko-wrap">
    <header class="bilah-toko">
        <a class="bilah-toko__merek" href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">
            <img class="bilah-toko__logo" src="<?php echo htmlspecialchars($u_logo, ENT_QUOTES, 'UTF-8'); ?>" width="200" height="38" alt="EA SENIKERS" decoding="async" fetchpriority="high">
        </a>
        <nav class="nav-toko" aria-label="Menu utama">
            <?php if ($bp_aktif === 'beranda'): ?>
                <span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">Beranda</span>
            <?php else: ?>
                <a class="nav-toko__tautan" href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">Beranda</a>
            <?php endif; ?>
            <?php if ($bp_aktif === 'produk'): ?>
                <span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">Produk</span>
            <?php else: ?>
                <a class="nav-toko__tautan" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Produk</a>
            <?php endif; ?>
            <?php if ($bp_aktif === 'kategori'): ?>
                <span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">Kategori</span>
            <?php else: ?>
                <a class="nav-toko__tautan" href="<?php echo htmlspecialchars($u_kategori, ENT_QUOTES, 'UTF-8'); ?>">Kategori</a>
            <?php endif; ?>
            <?php if ($bp_aktif === 'pesanan'): ?>
                <span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">Pesanan</span>
            <?php else: ?>
                <a class="nav-toko__tautan" href="<?php echo htmlspecialchars($u_pesanan, ENT_QUOTES, 'UTF-8'); ?>">Pesanan</a>
            <?php endif; ?>
            <?php if ($bp_aktif === 'tentang'): ?>
                <span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">Tentang</span>
            <?php else: ?>
                <a class="nav-toko__tautan" href="<?php echo htmlspecialchars($u_tentang, ENT_QUOTES, 'UTF-8'); ?>">Tentang</a>
            <?php endif; ?>
            <?php if ($bp_aktif === 'akun'): ?>
                <span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">Akun</span>
            <?php else: ?>
                <a class="nav-toko__tautan" href="<?php echo htmlspecialchars($u_akun, ENT_QUOTES, 'UTF-8'); ?>">Akun</a>
            <?php endif; ?>
        </nav>
        <div class="bilah-toko__aksi">
            <a class="tombol-keranjang-oranye<?php echo $bp_aktif === 'keranjang' ? ' tombol-keranjang-oranye--aktif' : ''; ?>"
               href="<?php echo htmlspecialchars($u_keranjang, ENT_QUOTES, 'UTF-8'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Keranjang (<?php echo $bp_kj > 99 ? '99+' : (string) (int) $bp_kj; ?>)
            </a>
            <a class="tautan-keluar-kecil" href="<?php echo htmlspecialchars($u_keluar, ENT_QUOTES, 'UTF-8'); ?>">Keluar</a>
        </div>
    </header>
    <nav class="bilah-kategori" aria-label="Kategori cepat">
        <div class="bilah-kategori__wrap">
            <?php if ($bp_brands !== []): ?>
            <div class="bilah-kategori__dropdown-wrap" data-bilah-dropdown>
                <button type="button" class="bilah-kategori__tautan bilah-kategori__tombol" aria-haspopup="true" aria-expanded="false" data-bilah-tombol>
                    <span>Brand</span>
                    <span class="bilah-kategori__panah" aria-hidden="true"></span>
                </button>
                <div class="bilah-kategori__menu" role="menu" data-bilah-menu>
                    <?php foreach ($bp_brands as $bp_merek => $bp_jumlah):
                        $bp_url_merek = aplikasi_url('pembeli/produk.php?brand=' . rawurlencode((string) $bp_merek));
                    ?>
                    <a class="bilah-kategori__menu-item" role="menuitem" href="<?php echo htmlspecialchars($bp_url_merek, ENT_QUOTES, 'UTF-8'); ?>">
                        <span><?php echo htmlspecialchars((string) $bp_merek, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="bilah-kategori__menu-jumlah"><?php echo (int) $bp_jumlah; ?></span>
                    </a>
                    <?php endforeach; ?>
                    <a class="bilah-kategori__menu-item bilah-kategori__menu-item--semua" role="menuitem" href="<?php echo htmlspecialchars($u_kategori, ENT_QUOTES, 'UTF-8'); ?>">Lihat semua merek &rarr;</a>
                </div>
            </div>
            <?php endif; ?>
            <a class="bilah-kategori__tautan" href="<?php echo htmlspecialchars($bp_u_baru, ENT_QUOTES, 'UTF-8'); ?>">Baru</a>
            <a class="bilah-kategori__tautan" href="<?php echo htmlspecialchars($bp_u_preloved, ENT_QUOTES, 'UTF-8'); ?>">Preloved</a>
            <a class="bilah-kategori__tautan" href="<?php echo htmlspecialchars($bp_u_termurah, ENT_QUOTES, 'UTF-8'); ?>">Termurah</a>
            <a class="bilah-kategori__tautan" href="<?php echo htmlspecialchars($bp_u_terbaru, ENT_QUOTES, 'UTF-8'); ?>">Terbaru</a>
        </div>
    </nav>
</div>
<script src="<?php echo htmlspecialchars($bp_u_js, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
