<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

$flash_keranjang_error = $_SESSION['flash_keranjang_error'] ?? null;
if ($flash_keranjang_error !== null) {
    unset($_SESSION['flash_keranjang_error']);
}

$flash_wishlist = $_SESSION['flash_wishlist'] ?? null;
if ($flash_wishlist !== null) {
    unset($_SESSION['flash_wishlist']);
}

$sudah_login = sudah_masuk();
$id_pengguna = $sudah_login ? (int)($_SESSION['id_pengguna'] ?? 0) : 0;

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$produk = $id !== '' ? katalog_ambil_produk_ber_id($id) : null;
$urls_gambar = [];

// Handle POST ulasan & wishlist (hanya jika login & produk ada)
// Ulasan hanya untuk yang pernah beli (verified purchase)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $produk !== null && $sudah_login) {
    $aksi = (string)($_POST['aksi'] ?? '');
    if ($aksi === 'tambah_ulasan') {
        if ($id_pengguna > 0 && user_pernah_beli_produk($id_pengguna, $id)) {
            $rating = (int)($_POST['rating'] ?? 0);
            $kom = trim((string)($_POST['komentar'] ?? ''));
            if (ulasan_tambah($id_pengguna, $id, $rating, $kom)) {
                $qs = strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?';
                header('Location: ' . $_SERVER['REQUEST_URI'] . $qs . 'ulasan_ok=1');
                exit;
            } else {
                $_SESSION['flash_keranjang_error'] = 'Gagal mengirim ulasan (mungkin koneksi database bermasalah atau data invalid).';
            }
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($aksi === 'tambah_wishlist' || $aksi === 'hapus_wishlist') {
        $ok = false;
        if ($aksi === 'tambah_wishlist') {
            $ok = wishlist_tambah($id_pengguna, $id);
        } else {
            $ok = wishlist_hapus($id_pengguna, $id);
        }
        if ($ok) {
            $_SESSION['flash_wishlist'] = ($aksi === 'tambah_wishlist')
                ? 'Produk ditambahkan ke wishlist.'
                : 'Produk dihapus dari wishlist.';
        } else {
            $_SESSION['flash_keranjang_error'] = 'Gagal memperbarui wishlist (mungkin koneksi database lokal bermasalah). Coba lagi.';
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}


$bilah_pembeli_aktif = 'produk';
$u_katalog = aplikasi_url('produk');
$u_keranjang_tambah = aplikasi_url('keranjang-tambah');
$u_checkout = aplikasi_url('checkout');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $produk ? htmlspecialchars((string) $produk['nama_produk'], ENT_QUOTES, 'UTF-8') . ' — EA SENIKERS' : 'Produk — EA SENIKERS'; ?></title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
</head>
<body class="halaman-toko halaman-detail-produk">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<div class="detail-kontainer">
    <nav class="detail-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo htmlspecialchars($u_katalog, ENT_QUOTES, 'UTF-8'); ?>">Katalog</a>
        <span aria-hidden="true"> / </span>
        <span>Detail</span>
    </nav>

    <?php if ($produk === null): ?>
        <div class="detail-404">
            <h1 style="margin:0 0 0.5rem;font-size:1.1rem;">Produk tidak ditemukan</h1>
            <p style="margin:0 0 1rem;color:#6b7280;font-size:0.9rem;">Periksa tautan atau kembali ke katalog.</p>
            <a href="<?php echo htmlspecialchars($u_katalog, ENT_QUOTES, 'UTF-8'); ?>">← Katalog produk</a>
        </div>
    <?php else:
        $gambar = $produk['produk_gambar'] ?? [];
        $gambar = is_array($gambar) ? katalog_urutkan_gambar($gambar) : [];
        $ukuran = $produk['produk_ukuran'] ?? [];
        $ukuran = is_array($ukuran) ? katalog_urutkan_ukuran($ukuran) : [];
        $urls_gambar = [];
        foreach ($gambar as $g) {
            $nf = (string) ($g['nama_file'] ?? '');
            if ($nf !== '') {
                $urls_gambar[] = katalog_url_gambar_produk($nf);
            }
        }
        if ($urls_gambar === []) {
            $urls_gambar[] = katalog_url_gambar_placeholder();
        }
        $utama = $urls_gambar[0];
        $nama = (string) ($produk['nama_produk'] ?? '');
        $brand = (string) ($produk['brand'] ?? '');
        $kondisi = (string) ($produk['kondisi'] ?? '');
        $harga = (int) ($produk['harga'] ?? 0);
        $deskripsi = (string) ($produk['deskripsi'] ?? '');
        $chip_kondisi = strcasecmp($kondisi, 'Baru') === 0 ? 'detail-panel__chip--baru' : 'detail-panel__chip--second';
        $ada_ukuran_siap = false;
        foreach ($ukuran as $u) {
            if ((int) ($u['stok'] ?? 0) > 0) {
                $ada_ukuran_siap = true;
                break;
            }
        }
        ?>
    <article class="detail-kartu">
        <div class="detail-susunan">
            <div class="detail-galeri">
                <button type="button" class="detail-gambar-tombol" data-lightbox-buka aria-label="Perbesar gambar produk">
                    <img id="gambar-utama" class="detail-gambar-utama" src="<?php echo htmlspecialchars($utama, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>" width="600" height="600">
                    <span class="detail-gambar-zoom" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m-3-3h6"/></svg>
                    </span>
                </button>
                <?php if (count($urls_gambar) > 1): ?>
                <div class="detail-thumb-bar" role="tablist" aria-label="Pilih gambar">
                    <?php foreach ($urls_gambar as $i => $u): ?>
                    <button type="button" class="detail-thumb<?php echo $i === 0 ? ' detail-thumb--aktif' : ''; ?>" data-src="<?php echo htmlspecialchars($u, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Gambar <?php echo $i + 1; ?>">
                        <img src="<?php echo htmlspecialchars($u, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" width="64" height="64">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="detail-panel">
                <p class="detail-panel__brand"><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></p>
                <h1><?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="detail-panel__harga"><?php echo htmlspecialchars(katalog_format_rupiah($harga), ENT_QUOTES, 'UTF-8'); ?></p>
                <span class="detail-panel__chip <?php echo htmlspecialchars($chip_kondisi, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(kondisi_label_pembeli($kondisi), ENT_QUOTES, 'UTF-8'); ?></span>

                <div class="detail-extra-info">
                    <?php if (($produk['terjual'] ?? 0) > 0): ?>
                        <span class="info-badge sold-badge"><?= (int)$produk['terjual'] ?>+ terjual</span>
                    <?php endif; ?>
                    <?php if (($produk['jumlah_ulasan'] ?? 0) > 0): ?>
                        <span class="info-badge rating-badge">★ <?= number_format((float)($produk['rating_rata'] ?? 0), 1) ?> (<?= (int)$produk['jumlah_ulasan'] ?> ulasan)</span>
                    <?php endif; ?>
                </div>

                <?php if (is_string($flash_keranjang_error) && $flash_keranjang_error !== ''): ?>
                    <p class="detail-flash-error" role="alert"><?php echo htmlspecialchars($flash_keranjang_error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <form method="post" class="detail-form-aksi">
                    <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">

                    <h2 class="detail-panel__subjudul">Ukuran</h2>
                    <div class="detail-ukuran-grup" role="radiogroup" aria-label="Pilih ukuran">
                        <?php
                        $radio_pertama = true;
                        foreach ($ukuran as $u):
                            $uk = (string) ($u['ukuran'] ?? '');
                            if ($uk === '') {
                                continue;
                            }
                            $st = (int) ($u['stok'] ?? 0);
                            $habis = $st <= 0;
                            $id_radio = 'uk-' . substr(md5($id . '|' . $uk), 0, 12);
                            $cek = !$habis && $radio_pertama;
                            if (!$habis) {
                                $radio_pertama = false;
                            }
                            $kelas_ukuran = 'detail-ukuran';
                            if ($habis) {
                                $kelas_ukuran .= ' detail-ukuran--habis';
                            } elseif ($st <= 3) {
                                $kelas_ukuran .= ' detail-ukuran--terbatas';
                            }
                            ?>
                        <div class="<?php echo $kelas_ukuran; ?>">
                            <?php if ($habis): ?>
                            <span class="detail-ukuran__kotak" aria-disabled="true" title="Stok habis">
                                <span class="detail-ukuran__nomor"><?php echo htmlspecialchars($uk, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="detail-ukuran__habis">Habis</span>
                            </span>
                            <?php else: ?>
                            <input type="radio" name="ukuran" value="<?php echo htmlspecialchars($uk, ENT_QUOTES, 'UTF-8'); ?>" id="<?php echo htmlspecialchars($id_radio, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $cek ? 'checked' : ''; ?> <?php echo $ada_ukuran_siap ? 'required' : ''; ?>>
                            <label for="<?php echo htmlspecialchars($id_radio, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="detail-ukuran__nomor"><?php echo htmlspecialchars($uk, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($st <= 3): ?>
                                    <span class="detail-ukuran__sisa">sisa <?php echo (int) $st; ?></span>
                                <?php endif; ?>
                            </label>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($ada_ukuran_siap): ?>
                        <p class="detail-stok-hint"><strong class="detail-hint-keranjang">Keranjang</strong> untuk menambahkan produk ke keranjang belanja. <strong>Beli</strong> untuk langsung melanjutkan ke checkout dan melengkapi alamat pengiriman serta pembayaran.</p>
                    <?php else: ?>
                        <p class="detail-stok-hint">Semua ukuran sedang habis. Cek lagi nanti atau hubungi toko lewat WhatsApp.</p>
                    <?php endif; ?>

                    <div class="detail-baris-tombol">
                        <button type="submit" class="detail-tombol-keranjang" formaction="<?php echo htmlspecialchars($u_keranjang_tambah, ENT_QUOTES, 'UTF-8'); ?>" formmethod="post"<?php echo ($ukuran === [] || !$ada_ukuran_siap) ? ' disabled' : ''; ?>>Keranjang</button>
                        <button type="submit" class="detail-tombol-beli" formaction="<?php echo htmlspecialchars($u_checkout, ENT_QUOTES, 'UTF-8'); ?>" formmethod="post"<?php echo ($ukuran === [] || !$ada_ukuran_siap) ? ' disabled' : ''; ?>>Beli</button>
                    </div>
                </form>

                <div class="detail-wishlist-row">
                    <?php if ($sudah_login): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $sudah_wish = wishlist_ada($id_pengguna, $id); ?>
                            <button type="submit" name="aksi" value="<?= $sudah_wish ? 'hapus_wishlist' : 'tambah_wishlist' ?>" class="detail-wishlist-btn">
                                <?= $sudah_wish ? '❤️ Hapus dari Wishlist' : '♡ Tambah ke Wishlist' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars(aplikasi_url('login/masuk.php'), ENT_QUOTES, 'UTF-8'); ?>" class="detail-wishlist-btn">Login untuk Wishlist</a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars(aplikasi_url('chat?produk=' . rawurlencode($id)), ENT_QUOTES, 'UTF-8'); ?>" class="detail-chat-btn">💬 Chat Penjual</a>
                </div>

                <?php if ($flash_wishlist): ?>
                    <p class="flash-success" style="margin:0.25rem 0 0.5rem; font-size:0.8rem;"><?= htmlspecialchars($flash_wishlist, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <h2 class="detail-panel__subjudul">Deskripsi</h2>
                <?php if (strcasecmp($kondisi, 'Baru') !== 0 && $kondisi !== ''): ?>
                    <p class="detail-panel__catatan-kondisi">Foto adalah produk asli. Kondisi dijelaskan apa adanya.</p>
                <?php endif; ?>
                <p class="detail-panel__deskripsi"><?php echo htmlspecialchars($deskripsi, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </article>

    <!-- Ulasan & Rating -->
    <section class="detail-ulasan">
        <h2 class="detail-panel__subjudul">Ulasan & Rating</h2>
        <div class="detail-rating-summary">
            <?php $rata = (float)($produk['rating_rata'] ?? 0); $jml = (int)($produk['jumlah_ulasan'] ?? 0); ?>
            <div class="rating-big">★ <?= number_format($rata, 1) ?></div>
            <div class="rating-meta">
                <div><?= $jml ?> ulasan</div>
                <div><?= (int)($produk['terjual'] ?? 0) ?>+ terjual</div>
            </div>
        </div>

        <?php
        $ulasan_list = ulasan_ambil_untuk_produk($id, 5);
        if ($ulasan_list):
        ?>
        <div class="ulasan-list">
            <?php foreach ($ulasan_list as $u): ?>
            <div class="ulasan-item">
                <div class="ulasan-head">
                    <strong><?= htmlspecialchars( is_array($u['users'] ?? null) ? ($u['users']['nama_pengguna'] ?? 'Pembeli') : 'Pembeli' ) ?></strong>
                    <span class="stars"><?= str_repeat('★', (int)($u['rating'] ?? 0)) ?></span>
                    <time><?= date('d M Y', strtotime($u['created_at'] ?? 'now')) ?></time>
                </div>
                <p class="ulasan-text"><?= nl2br(htmlspecialchars($u['komentar'] ?? '')) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p class="no-review">Belum ada ulasan. Jadilah yang pertama setelah beli!</p>
        <?php endif; ?>

        <?php if ($sudah_login && user_pernah_beli_produk($id_pengguna, $id)): ?>
        <form method="post" class="form-ulasan">
            <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-row">
                <label>Rating</label>
                <select name="rating" required>
                    <option value="5">5 ★ Sangat Puas</option>
                    <option value="4">4 ★ Puas</option>
                    <option value="3">3 ★ Cukup</option>
                    <option value="2">2 ★ Kurang</option>
                    <option value="1">1 ★ Kecewa</option>
                </select>
            </div>
            <textarea name="komentar" placeholder="Bagaimana kondisi & pengalaman Anda dengan produk ini?" required rows="3"></textarea>
            <button type="submit" name="aksi" value="tambah_ulasan" class="btn-ulasan">Kirim Ulasan</button>
        </form>
        <?php elseif ($sudah_login): ?>
            <p class="no-review">Ulasan hanya bisa diberikan setelah Anda membeli dan menyelesaikan pesanan untuk produk ini.</p>
        <?php else: ?>
            <p><a href="<?php echo htmlspecialchars(aplikasi_url('login/masuk.php'), ENT_QUOTES, 'UTF-8'); ?>">Login</a> untuk memberikan ulasan (setelah pembelian).</p>
        <?php endif; ?>
        <?php if (isset($_GET['ulasan_ok'])): ?>
            <p class="flash-success">Terima kasih! Ulasan Anda telah dikirim.</p>
        <?php endif; ?>
    </section>

    <!-- Rekomendasi -->
    <?php
    $rekom = katalog_rekomendasi_untuk_produk($id, 4);
    if ($rekom):
    ?>
    <section class="detail-rekom">
        <h2 class="detail-panel__subjudul">Rekomendasi untuk Anda</h2>
        <div class="rekom-grid">
            <?php foreach ($rekom as $r): ?>
            <a href="<?php echo htmlspecialchars(aplikasi_url('detail-produk?id=' . rawurlencode((string)$r['id_produk'])), ENT_QUOTES, 'UTF-8'); ?>" class="rekom-card">
                <img src="<?php echo htmlspecialchars(katalog_url_gambar_utama($r), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($r['nama_produk'] ?? '') ?>" loading="lazy">
                <div class="rekom-info">
                    <div class="rekom-brand"><?= htmlspecialchars($r['brand'] ?? '') ?></div>
                    <div class="rekom-nama"><?= htmlspecialchars($r['nama_produk'] ?? '') ?></div>
                    <div class="rekom-harga"><?= htmlspecialchars(katalog_format_rupiah((int)($r['harga'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php if ($produk !== null): ?>
<div class="detail-lightbox" data-lightbox hidden role="dialog" aria-modal="true" aria-label="Pratinjau gambar produk">
    <button type="button" class="detail-lightbox__tutup" data-lightbox-tutup aria-label="Tutup pratinjau">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <img class="detail-lightbox__gambar" data-lightbox-gambar src="" alt="Pratinjau produk">
</div>
<script>
(function () {
    var utama = document.getElementById('gambar-utama');
    var thumbs = document.querySelectorAll('.detail-thumb');
    var lightbox = document.querySelector('[data-lightbox]');
    var lbGambar = document.querySelector('[data-lightbox-gambar]');
    var tombolBuka = document.querySelector('[data-lightbox-buka]');
    var tombolTutup = document.querySelector('[data-lightbox-tutup]');

    thumbs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var src = btn.getAttribute('data-src');
            if (src && utama) utama.src = src;
            thumbs.forEach(function (b) { b.classList.remove('detail-thumb--aktif'); });
            btn.classList.add('detail-thumb--aktif');
        });
    });

    function bukaLightbox() {
        if (!lightbox || !utama || !lbGambar) return;
        lbGambar.src = utama.src;
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
    }
    function tutupLightbox() {
        if (!lightbox) return;
        lightbox.hidden = true;
        document.body.style.overflow = '';
    }
    if (tombolBuka) tombolBuka.addEventListener('click', bukaLightbox);
    if (tombolTutup) tombolTutup.addEventListener('click', tutupLightbox);
    if (lightbox) {
        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox) tutupLightbox();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && lightbox && !lightbox.hidden) tutupLightbox();
    });
})();
</script>
<?php endif; ?>

</body>
</html>
