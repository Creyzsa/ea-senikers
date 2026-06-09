<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

$flash_keranjang_error = $_SESSION['flash_keranjang_error'] ?? null;
if ($flash_keranjang_error !== null) {
    unset($_SESSION['flash_keranjang_error']);
}
$checkout_habis = isset($_GET['checkout']) && (string) $_GET['checkout'] === 'habis';

$flash_wishlist = $_SESSION['flash_wishlist'] ?? null;
if ($flash_wishlist !== null) {
    unset($_SESSION['flash_wishlist']);
}

$sudah_login = sudah_masuk();
$id_pengguna = $sudah_login ? ambil_id_pengguna_efektif() : 0;

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$produk = $id !== '' ? katalog_ambil_produk_ber_id($id) : null;
$urls_gambar = [];

// Handle POST ulasan & wishlist (hanya jika login & produk ada)
// Ulasan: 1 per pesanan selesai, boleh edit sekali, lalu dikunci
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $produk !== null && $sudah_login) {
    $aksi = (string)($_POST['aksi'] ?? '');
    if ($aksi === 'tambah_ulasan' || $aksi === 'edit_ulasan') {
        $id_post = ambil_id_pengguna_efektif(true);
        $order_id_ulasan = (int)($_POST['order_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $kom = trim((string)($_POST['komentar'] ?? ''));
        $hasil_ulasan = ['ok' => false, 'pesan' => 'Akun tidak dikenali. Silakan login ulang.'];
        if ($id_post > 0) {
            if ($aksi === 'tambah_ulasan') {
                $hasil_ulasan = ulasan_buat($id_post, $order_id_ulasan, $id, $rating, $kom);
            } else {
                $hasil_ulasan = ulasan_perbarui($id_post, $order_id_ulasan, $id, $rating, $kom);
            }
        }
        if (!empty($hasil_ulasan['ok'])) {
            $qs = strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?';
            $param = $aksi === 'edit_ulasan' ? 'ulasan_edit_ok=1' : 'ulasan_ok=1';
            header('Location: ' . $_SERVER['REQUEST_URI'] . $qs . $param);
            exit;
        }
        $_SESSION['flash_keranjang_error'] = (string) ($hasil_ulasan['pesan'] ?? 'Gagal menyimpan ulasan.');
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
            <a class="tautan-kembali" href="<?php echo htmlspecialchars($u_katalog, ENT_QUOTES, 'UTF-8'); ?>">← Katalog produk</a>
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
        $ulasan_stats = ulasan_stats_untuk_produk($id);
        $jml_ulasan = (int) ($ulasan_stats['jumlah'] ?? 0);
        $rata_ulasan = (float) ($ulasan_stats['rata'] ?? 0);
        if ($jml_ulasan <= 0) {
            $jml_ulasan = (int) ($produk['jumlah_ulasan'] ?? 0);
            $rata_ulasan = (float) ($produk['rating_rata'] ?? 0);
        }
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
                    <?php if ($jml_ulasan > 0): ?>
                        <span class="info-badge rating-badge">★ <?= number_format($rata_ulasan, 1) ?> (<?= $jml_ulasan ?> ulasan)</span>
                    <?php endif; ?>
                </div>

                <?php if (is_string($flash_keranjang_error) && $flash_keranjang_error !== ''): ?>
                    <p class="detail-flash-error" role="alert"><?php echo htmlspecialchars($flash_keranjang_error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if ($checkout_habis): ?>
                    <p class="detail-flash-error" role="alert">Checkout gagal dimuat. Pilih ukuran lalu klik <strong>Beli</strong> sekali lagi.</p>
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
            <div class="rating-big">★ <?php echo number_format($rata_ulasan, 1); ?></div>
            <div class="rating-meta">
                <div><?php echo $jml_ulasan; ?> ulasan pembeli</div>
                <div><?php echo (int)($produk['terjual'] ?? 0); ?>+ terjual</div>
            </div>
        </div>

        <?php
        $order_id_param = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        $ulasan_form = $sudah_login ? ulasan_konteks_form($id_pengguna, $id, $order_id_param) : ['order_id' => 0, 'status' => 'tidak_berhak', 'ulasan' => null];
        $ulasan_status = (string) ($ulasan_form['status'] ?? 'tidak_berhak');
        $ulasan_order = (int) ($ulasan_form['order_id'] ?? 0);
        $ulasan_labels = [5 => '5 ★ Sangat Puas', 4 => '4 ★ Puas', 3 => '3 ★ Cukup', 2 => '2 ★ Kurang', 1 => '1 ★ Kecewa'];
        $ulasan_limit = max(1, min(100, $jml_ulasan > 0 ? $jml_ulasan : 50));
        $ulasan_list = ulasan_ambil_untuk_produk($id, $ulasan_limit);
        ?>
        <?php if ($ulasan_list !== []): ?>
        <p class="ulasan-subjudul">Ulasan dari pembeli lain membantu Anda menilai kualitas produk.</p>
        <div class="ulasan-list">
            <?php foreach ($ulasan_list as $u): ?>
            <?php
            $oid_review = (int) ($u['order_id'] ?? 0);
            $ulasan_user = (int) ($u['user_id'] ?? 0);
            $ulasan_milik_saya = $id_pengguna > 0 && $ulasan_user === $id_pengguna;
            $status_item = ($ulasan_milik_saya && $oid_review > 0)
                ? ulasan_status_untuk_order($id_pengguna, $oid_review, $id)
                : ($ulasan_milik_saya ? 'dikunci' : 'orang_lain');
            $nama_ulasan = ulasan_nama_tampilan($u, $id_pengguna);
            $inisial = ulasan_inisial_nama($nama_ulasan === 'Anda' ? (string) ($u['nama_pengguna'] ?? 'A') : $nama_ulasan);
            $rating_item = (int) ($u['rating'] ?? 0);
            $komentar_item = (string) ($u['komentar'] ?? '');
            $tgl_item = date('d M Y', strtotime((string) ($u['created_at'] ?? 'now')));
            ?>
            <div class="ulasan-item<?php echo $ulasan_milik_saya ? ' ulasan-item--milik-saya' : ''; ?>">
                <div class="ulasan-head">
                    <span class="ulasan-avatar" aria-hidden="true"><?php echo htmlspecialchars($inisial, ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="ulasan-head-meta">
                        <div class="ulasan-head-baris">
                            <strong><?php echo htmlspecialchars($nama_ulasan, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if (!$ulasan_milik_saya): ?>
                                <span class="ulasan-badge-pembeli">Pembeli</span>
                            <?php endif; ?>
                            <span class="ulasan-rating-angka"><?php echo $rating_item; ?>.0</span>
                            <span class="stars" aria-label="Rating <?php echo $rating_item; ?> dari 5"><?php echo str_repeat('★', $rating_item); ?></span>
                        </div>
                        <time class="ulasan-waktu"><?php echo htmlspecialchars($tgl_item, ENT_QUOTES, 'UTF-8'); ?></time>
                    </div>
                    <?php if ($ulasan_milik_saya && $status_item === 'dikunci'): ?>
                        <span class="ulasan-badge-terkirim">Ulasan terkirim</span>
                    <?php endif; ?>
                </div>

                <?php if ($ulasan_milik_saya && $status_item === 'bisa_edit'): ?>
                <form method="post" class="ulasan-edit-form">
                    <input type="hidden" name="order_id" value="<?php echo $oid_review; ?>">
                    <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                    <p class="ulasan-edit-hint">Ulasan Anda — bisa diedit <strong>1 kali</strong>. Setelah disimpan, tidak bisa diubah lagi.</p>
                    <div class="form-row">
                        <label for="rating-edit-<?php echo $oid_review; ?>">Rating</label>
                        <select id="rating-edit-<?php echo $oid_review; ?>" name="rating" required>
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                                <option value="<?php echo $star; ?>"<?php echo $rating_item === $star ? ' selected' : ''; ?>><?php echo $ulasan_labels[$star]; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <textarea name="komentar" placeholder="Bagaimana kondisi & pengalaman Anda dengan produk ini?" required rows="3"><?php echo htmlspecialchars($komentar_item, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <button type="submit" name="aksi" value="edit_ulasan" class="btn-ulasan btn-ulasan--edit">Simpan Perubahan Ulasan</button>
                </form>
                <?php else: ?>
                <p class="ulasan-text"><?php echo nl2br(htmlspecialchars($komentar_item, ENT_QUOTES, 'UTF-8')); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p class="no-review">Belum ada ulasan. Jadilah yang pertama setelah beli!</p>
        <?php endif; ?>

        <?php if ($sudah_login && $ulasan_status === 'belum'): ?>
        <form method="post" class="form-ulasan">
            <input type="hidden" name="order_id" value="<?php echo $ulasan_order; ?>">
            <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
            <p class="form-ulasan-hint">Satu ulasan per pesanan. Jika membeli produk ini lagi, Anda bisa memberi ulasan baru.</p>
            <div class="form-row">
                <label for="rating-baru">Rating</label>
                <select id="rating-baru" name="rating" required>
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <option value="<?php echo $star; ?>"><?php echo $ulasan_labels[$star]; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <textarea name="komentar" placeholder="Bagaimana kondisi & pengalaman Anda dengan produk ini?" required rows="3"></textarea>
            <button type="submit" name="aksi" value="tambah_ulasan" class="btn-ulasan btn-ulasan--utama">Kirim Ulasan</button>
        </form>
        <?php endif; ?>
        <?php if (isset($_GET['ulasan_ok'])): ?>
            <p class="flash-success">Terima kasih! Ulasan Anda telah dikirim.</p>
        <?php endif; ?>
        <?php if (isset($_GET['ulasan_edit_ok'])): ?>
            <p class="flash-success">Ulasan berhasil diperbarui. Ulasan ini sekarang dikunci dan tidak bisa diedit lagi.</p>
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
                    <?php katalog_render_rating_kartu($r, 'rekom-rating'); ?>
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
