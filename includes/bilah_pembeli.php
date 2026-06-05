<?php
/**
 * Bilah navigasi area pembeli (header sticky).
 * Set sebelum include:
 *   $bilah_pembeli_aktif — 'beranda' | 'produk' | 'kategori' | 'pesanan' | 'tentang' | 'keranjang' | 'akun'
 *   $bilah_keranjang_jumlah — int (opsional; bila tidak di-set dipakai jumlah item dari sesi keranjang)
 */
require_once __DIR__ . '/url_bantu.php';
require_once __DIR__ . '/keranjang_sesi.php';
require_once __DIR__ . '/auth_db/sesi.php';

$bp_aktif = isset($bilah_pembeli_aktif) ? (string) $bilah_pembeli_aktif : 'beranda';
$bp_kj = isset($bilah_keranjang_jumlah) ? (int) $bilah_keranjang_jumlah : keranjang_hitung_jumlah_item();
if ($bp_kj < 0) {
    $bp_kj = 0;
}

$sudah_login = sudah_masuk();

$u_logo = aplikasi_url_aset('assets/images/logo-easenikers.svg');
$u_beranda = aplikasi_url(''); // homepage at root for clean URL
$u_produk = aplikasi_url('produk');
$u_kategori = aplikasi_url('kategori');
$u_pesanan = aplikasi_url('pesanan');
$u_tentang = aplikasi_url('tentang');
$u_keranjang = aplikasi_url('keranjang');
$u_akun = aplikasi_url('akun');
$u_masuk = aplikasi_url('login/masuk.php');
$u_keluar = aplikasi_url('login/keluar.php');
?>
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
            <?php if ($sudah_login): ?>
                <?php if ($bp_aktif === 'pesanan'): ?>
                    <span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">Pesanan</span>
                <?php else: ?>
                    <a class="nav-toko__tautan" href="<?php echo htmlspecialchars($u_pesanan, ENT_QUOTES, 'UTF-8'); ?>">Pesanan</a>
                <?php endif; ?>
                <?php if ($bp_aktif === 'wishlist'): ?>
                    <span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">Wishlist</span>
                <?php else: ?>
                    <a class="nav-toko__tautan" href="<?php echo htmlspecialchars(aplikasi_url('wishlist'), ENT_QUOTES, 'UTF-8'); ?>">Wishlist</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($bp_aktif === 'tentang'): ?>
                <span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">Tentang</span>
            <?php else: ?>
                <a class="nav-toko__tautan" href="<?php echo htmlspecialchars($u_tentang, ENT_QUOTES, 'UTF-8'); ?>">Tentang</a>
            <?php endif; ?>
            <?php if ($sudah_login): ?>
                <?php if ($bp_aktif === 'akun'): ?>
                    <span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">Akun</span>
                <?php else: ?>
                    <a class="nav-toko__tautan" href="<?php echo htmlspecialchars($u_akun, ENT_QUOTES, 'UTF-8'); ?>">Akun</a>
                <?php endif; ?>
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
            <?php if ($sudah_login): ?>
                <a class="tautan-keluar-kecil" href="<?php echo htmlspecialchars($u_keluar, ENT_QUOTES, 'UTF-8'); ?>">Keluar</a>
            <?php else: ?>
                <a class="tautan-keluar-kecil" href="<?php echo htmlspecialchars($u_masuk, ENT_QUOTES, 'UTF-8'); ?>">Masuk</a>
            <?php endif; ?>
        </div>
    </header>
