<?php
/**
 * Bilah navigasi area pembeli (header sticky).
 * Set sebelum include:
 *   $bilah_pembeli_aktif — 'beranda' | 'produk' | 'kategori' | 'pesanan' | 'tentang' | 'keranjang' | 'akun' | 'wishlist'
 *   $bilah_keranjang_jumlah — int (opsional; bila tidak di-set dipakai jumlah item dari sesi keranjang)
 *   $bilah_cari_q — string kata kunci di search bar (opsional)
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
$peran_nav = $sudah_login ? ambil_peran_efektif(false) : null;

$u_beranda = aplikasi_url('');
$u_produk = aplikasi_url('produk');
$u_kategori = aplikasi_url('kategori');
$u_pesanan = aplikasi_url('pesanan');
$u_tentang = aplikasi_url('tentang');
$u_keranjang = aplikasi_url('keranjang');
$u_akun = aplikasi_url('akun');
$u_wishlist = aplikasi_url('wishlist');
$u_masuk = aplikasi_url('login/masuk.php');
$u_daftar = aplikasi_url('login/daftar.php');
$u_admin = aplikasi_url('admin/beranda_admin.php');
$bp_tampil_login_daftar = !$sudah_login && $bp_aktif === 'beranda';
$bp_nama_pengguna = trim((string) ($_SESSION['nama_pengguna'] ?? ''));
$u_pesanan_admin = aplikasi_url('admin/pesanan_admin.php');

if ($peran_nav === 'admin') {
    $u_akun_tujuan = $u_admin;
    $u_pesanan_tampil = $u_pesanan_admin;
    $label_akun_nav = 'Panel admin';
} elseif ($sudah_login) {
    $u_akun_tujuan = $u_akun;
    $u_pesanan_tampil = $u_pesanan;
    $label_akun_nav = 'Akun saya';
} else {
    $u_akun_tujuan = $u_masuk;
    $u_pesanan_tampil = $u_pesanan;
    $label_akun_nav = 'Masuk ke akun';
}
$u_wishlist_tujuan = $sudah_login ? $u_wishlist : $u_masuk;

$bp_cari_q = isset($bilah_cari_q)
    ? trim((string) $bilah_cari_q)
    : trim((string) ($_GET['q'] ?? ''));

$u_cari_saran = aplikasi_url('api/cari-saran');

$bp_label_keranjang = $bp_kj > 99 ? '99+' : (string) (int) $bp_kj;

/**
 * @param list<array{0: string, 1: string, 2: string}> $item Menu: [id aktif, label, url]
 */
$bp_render_tautan = static function (array $item) use ($bp_aktif): void {
    [$id, $label, $url] = $item;
    if ($bp_aktif === $id) {
        echo '<span class="nav-toko__tautan nav-toko__tautan--aktif" aria-current="page">';
        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        echo '</span>';
        return;
    }
    echo '<a class="nav-toko__tautan" href="';
    echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '">';
    echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    echo '</a>';
};
?>
    <header class="bilah-toko">
        <a class="bilah-toko__merek" href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">
            <?php $ukuran_logo = 'nav'; include __DIR__ . '/komponen/logo_teks_merek.php'; ?>
        </a>
        <nav class="nav-toko" aria-label="Menu utama">
            <div class="nav-toko__menu">
                <?php
                $bp_render_tautan(['beranda', 'Beranda', $u_beranda]);
                $bp_render_tautan(['produk', 'Produk', $u_produk]);
                $bp_render_tautan(['kategori', 'Kategori', $u_kategori]);
                $bp_render_tautan(['pesanan', 'Pesanan', $u_pesanan_tampil]);
                $bp_render_tautan(['tentang', 'Tentang', $u_tentang]);
                ?>
            </div>

            <form class="nav-toko__cari" method="get" action="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>" role="search" data-cari-saran="<?php echo htmlspecialchars($u_cari_saran, ENT_QUOTES, 'UTF-8'); ?>">
                <label class="nav-toko__cari-label" for="nav-toko-cari">Cari produk</label>
                <span class="nav-toko__cari-ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/>
                    </svg>
                </span>
                <input type="search" id="nav-toko-cari" name="q" value="<?php echo htmlspecialchars($bp_cari_q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Cari sneakers, merek..." autocomplete="off" aria-autocomplete="list" aria-controls="nav-toko-cari-saran" aria-expanded="false">
            </form>

            <div class="nav-toko__ikon-grup" aria-label="Aksi cepat">
                <?php if ($bp_tampil_login_daftar): ?>
                    <div class="nav-toko__login-daftar" role="group" aria-label="Masuk atau daftar">
                        <a class="nav-toko__login-daftar-tautan" href="<?php echo htmlspecialchars($u_masuk, ENT_QUOTES, 'UTF-8'); ?>">Login</a>
                        <span class="nav-toko__login-daftar-pisah" aria-hidden="true">/</span>
                        <a class="nav-toko__login-daftar-tautan" href="<?php echo htmlspecialchars($u_daftar, ENT_QUOTES, 'UTF-8'); ?>">Daftar</a>
                    </div>
                <?php elseif ($sudah_login): ?>
                    <a class="nav-toko__ikon nav-toko__ikon--dengan-nama<?php echo $bp_aktif === 'akun' ? ' nav-toko__ikon--aktif' : ''; ?>"
                       href="<?php echo htmlspecialchars($u_akun_tujuan, ENT_QUOTES, 'UTF-8'); ?>"
                       aria-label="<?php echo htmlspecialchars($label_akun_nav, ENT_QUOTES, 'UTF-8'); ?>"
                       title="<?php echo htmlspecialchars($bp_nama_pengguna !== '' ? $bp_nama_pengguna : $label_akun_nav, ENT_QUOTES, 'UTF-8'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <?php if ($bp_nama_pengguna !== ''): ?>
                            <span class="nav-toko__nama-pengguna"><?php echo htmlspecialchars($bp_nama_pengguna, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <a class="nav-toko__ikon<?php echo $bp_aktif === 'akun' ? ' nav-toko__ikon--aktif' : ''; ?>"
                       href="<?php echo htmlspecialchars($u_akun_tujuan, ENT_QUOTES, 'UTF-8'); ?>"
                       aria-label="<?php echo htmlspecialchars($label_akun_nav, ENT_QUOTES, 'UTF-8'); ?>"
                       title="Masuk">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </a>
                <?php endif; ?>
                <a class="nav-toko__ikon<?php echo $bp_aktif === 'wishlist' ? ' nav-toko__ikon--aktif' : ''; ?>"
                   href="<?php echo htmlspecialchars($u_wishlist_tujuan, ENT_QUOTES, 'UTF-8'); ?>"
                   aria-label="Wishlist"
                   title="Wishlist">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </a>
                <a class="nav-toko__ikon nav-toko__ikon--keranjang<?php echo $bp_aktif === 'keranjang' ? ' nav-toko__ikon--aktif' : ''; ?>"
                   href="<?php echo htmlspecialchars($u_keranjang, ENT_QUOTES, 'UTF-8'); ?>"
                   aria-label="Keranjang, <?php echo htmlspecialchars($bp_label_keranjang, ENT_QUOTES, 'UTF-8'); ?> item"
                   title="Keranjang">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="nav-toko__badge" aria-hidden="true"><?php echo htmlspecialchars($bp_label_keranjang, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </div>
        </nav>
    </header>
    <script src="<?php echo htmlspecialchars(aplikasi_url_aset('assets/js/nav-cari-saran.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>