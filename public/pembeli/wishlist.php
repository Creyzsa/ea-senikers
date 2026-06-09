<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

wajib_sudah_masuk();

$id_pengguna = (int) ($_SESSION['id_pengguna'] ?? 0);
$bilah_pembeli_aktif = 'wishlist';

$u_beranda = aplikasi_url('');
$u_produk = aplikasi_url('produk');
$u_keranjang = aplikasi_url('keranjang');
$u_wishlist_toggle = aplikasi_url('api/wishlist-toggle');
$u_masuk = aplikasi_url('login/masuk.php');
$sudah_login = true;

$flash = $_SESSION['flash_wishlist'] ?? null;
if ($flash) {
    unset($_SESSION['flash_wishlist']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_produk'], $_POST['aksi']) && $_POST['aksi'] === 'hapus') {
    $pid = trim((string) $_POST['id_produk']);
    $hapus_ok = wishlist_hapus($id_pengguna, $pid);
    $_SESSION['flash_wishlist'] = $hapus_ok
        ? 'Produk dihapus dari wishlist.'
        : 'Gagal menghapus produk dari wishlist.';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$items = wishlist_ambil_user($id_pengguna);
$jumlah_wishlist = count($items);
$wishlist_ids = [];
foreach ($items as $p) {
    if (!is_array($p)) {
        continue;
    }
    $pid = (string) ($p['id_produk'] ?? '');
    if ($pid !== '') {
        $wishlist_ids[$pid] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wishlist — EA SENIKERS</title>
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>

    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
</head>
<body class="halaman-toko halaman-wishlist"
      data-wishlist-api="<?php echo htmlspecialchars($u_wishlist_toggle, ENT_QUOTES, 'UTF-8'); ?>"
      data-wishlist-csrf="<?php echo htmlspecialchars(csrf_wishlist_token(), ENT_QUOTES, 'UTF-8'); ?>"
      data-wishlist-halaman="1">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="wishlist-wrap">
    <nav class="detail-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Katalog</a>
        <span aria-hidden="true"> / </span>
        <span>Wishlist</span>
    </nav>

    <header class="wishlist-hero">
        <div class="wishlist-hero__teks">
            <p class="wishlist-hero__eyebrow">Favorit Anda</p>
            <h1 class="wishlist-hero__judul">Wishlist</h1>
            <p class="wishlist-hero__deskripsi">
                <?php if ($jumlah_wishlist === 0): ?>
                    Simpan sneaker impianmu di sini dan pantau kapan siap dibeli.
                <?php else: ?>
                    <?php echo (int) $jumlah_wishlist; ?> produk tersimpan · siap dibandingkan atau dibeli kapan saja
                <?php endif; ?>
            </p>
        </div>
        <?php if ($jumlah_wishlist > 0): ?>
        <div class="wishlist-hero__badge" aria-hidden="true">
            <span class="wishlist-hero__badge-angka" data-wishlist-jumlah><?php echo (int) $jumlah_wishlist; ?></span>
            <span class="wishlist-hero__badge-label">Favorit</span>
        </div>
        <?php endif; ?>
    </header>

    <?php if (is_string($flash) && $flash !== ''): ?>
        <div class="wishlist-alert wishlist-alert--ok" role="status"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <section class="wishlist-kosong" aria-labelledby="wishlist-kosong-judul">
            <div class="wishlist-kosong__ikon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                </svg>
            </div>
            <h2 id="wishlist-kosong-judul" class="wishlist-kosong__judul">Wishlist masih kosong</h2>
            <p class="wishlist-kosong__teks">Jelajahi katalog, ketuk ikon hati pada produk favoritmu, lalu kembali ke sini untuk memantau daftar simpanan.</p>
            <div class="wishlist-kosong__aksi">
                <a class="wishlist-tombol wishlist-tombol--utama" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Jelajahi katalog</a>
                <a class="wishlist-tombol wishlist-tombol--sekunder" href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">Beranda</a>
            </div>
        </section>
    <?php else: ?>
        <div class="wishlist-grid">
            <section class="wishlist-daftar" aria-label="Produk wishlist">
                <div class="wishlist-daftar__header">
                    <h2 class="wishlist-daftar__judul">Produk favorit</h2>
                    <a class="wishlist-daftar__tambah" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">+ Tambah favorit</a>
                </div>

                <div class="wishlist-produk-grid" data-wishlist-grid>
                    <?php foreach ($items as $p): ?>
                        <?php if (is_array($p)): ?>
                            <?php katalog_render_kartu_produk($p, $sudah_login, $u_masuk, $wishlist_ids); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="wishlist-ringkas" aria-label="Ringkasan wishlist">
                <div class="wishlist-ringkas__kartu">
                    <h2 class="wishlist-ringkas__judul">Ringkasan</h2>
                    <dl class="wishlist-ringkas__baris">
                        <div class="wishlist-ringkas__satu">
                            <dt>Total favorit</dt>
                            <dd data-wishlist-jumlah><?php echo (int) $jumlah_wishlist; ?> produk</dd>
                        </div>
                        <div class="wishlist-ringkas__satu wishlist-ringkas__satu--muted">
                            <dt>Status</dt>
                            <dd>Tersimpan di akun Anda</dd>
                        </div>
                    </dl>
                    <p class="wishlist-ringkas__catatan">Ketuk ikon hati pada kartu untuk menghapus dari wishlist, atau buka detail produk untuk memilih ukuran dan checkout.</p>

                    <a class="wishlist-tombol wishlist-tombol--utama wishlist-tombol--blok" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Lihat katalog</a>
                    <a class="wishlist-tombol wishlist-tombol--sekunder wishlist-tombol--blok" href="<?php echo htmlspecialchars($u_keranjang, ENT_QUOTES, 'UTF-8'); ?>">Ke keranjang</a>

                    <ul class="wishlist-trust" aria-label="Tips wishlist">
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                            Simpan produk yang ingin dibeli nanti
                        </li>
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Bandingkan kondisi &amp; harga sebelum checkout
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</main>

<script src="<?php echo htmlspecialchars(aplikasi_url_aset('assets/js/katalog-premium.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>