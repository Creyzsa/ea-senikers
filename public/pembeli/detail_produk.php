<?php
require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/katalog_produk.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$flash_keranjang_error = $_SESSION['flash_keranjang_error'] ?? null;
if ($flash_keranjang_error !== null) {
    unset($_SESSION['flash_keranjang_error']);
}

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$produk = $id !== '' ? katalog_ambil_produk_ber_id($id) : null;
$urls_gambar = [];

$bilah_pembeli_aktif = 'produk';
$u_katalog = aplikasi_url('pembeli/produk.php');
$u_keranjang_tambah = aplikasi_url('pembeli/keranjang_tambah.php');
$u_checkout = aplikasi_url('pembeli/checkout_pembeli.php');
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

                <?php if (strcasecmp($kondisi, 'Baru') !== 0 && $kondisi !== ''): ?>
                    <p class="detail-panel__trust" aria-label="Catatan untuk produk preloved">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Foto asli (bukan stok merek). Kondisi dijelaskan apa adanya di deskripsi di bawah.</span>
                    </p>
                <?php endif; ?>

                <?php if (is_string($flash_keranjang_error) && $flash_keranjang_error !== ''): ?>
                    <p class="detail-flash-error" role="alert"><?php echo htmlspecialchars($flash_keranjang_error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <form method="post" class="detail-form-aksi">
                    <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">

                    <h2 class="detail-panel__subjudul">Ukuran</h2>
                    <?php if ($ukuran === []): ?>
                        <p class="detail-stok-hint">Ukuran dan stok akan segera tersedia.</p>
                    <?php else: ?>
                        <div class="detail-ukuran-grup" role="radiogroup" aria-label="Pilih ukuran">
                            <?php
                            $radio_pertama = true;
                            foreach ($ukuran as $u):
                                $uk = (string) ($u['ukuran'] ?? '');
                                $st = (int) ($u['stok'] ?? 0);
                                $id_radio = 'uk-' . substr(md5($id . '|' . $uk), 0, 12);
                                $cek = $st > 0 && $radio_pertama;
                                if ($st > 0) {
                                    $radio_pertama = false;
                                }
                                $kelas_ukuran = 'detail-ukuran';
                                if ($st <= 0) {
                                    $kelas_ukuran .= ' detail-ukuran--habis';
                                } elseif ($st <= 3) {
                                    $kelas_ukuran .= ' detail-ukuran--terbatas';
                                }
                                ?>
                            <div class="<?php echo $kelas_ukuran; ?>">
                                <input type="radio" name="ukuran" value="<?php echo htmlspecialchars($uk, ENT_QUOTES, 'UTF-8'); ?>" id="<?php echo htmlspecialchars($id_radio, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $st <= 0 ? 'disabled' : ''; ?> <?php echo $cek ? 'checked' : ''; ?> <?php echo $ada_ukuran_siap ? 'required' : ''; ?>>
                                <label for="<?php echo htmlspecialchars($id_radio, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="detail-ukuran__nomor"><?php echo htmlspecialchars($uk, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($st > 0 && $st <= 3): ?>
                                        <span class="detail-ukuran__sisa">sisa <?php echo (int) $st; ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="detail-stok-hint"><strong>Keranjang</strong> menambah barang ke keranjang. <strong>Beli</strong> langsung ke ringkasan checkout (alamat &amp; bayar menyusul).</p>
                    <?php endif; ?>

                    <div class="detail-baris-tombol">
                        <button type="submit" class="detail-tombol-keranjang" formaction="<?php echo htmlspecialchars($u_keranjang_tambah, ENT_QUOTES, 'UTF-8'); ?>" formmethod="post"<?php echo ($ukuran === [] || !$ada_ukuran_siap) ? ' disabled' : ''; ?>>Keranjang</button>
                        <button type="submit" class="detail-tombol-beli" formaction="<?php echo htmlspecialchars($u_checkout, ENT_QUOTES, 'UTF-8'); ?>" formmethod="post"<?php echo ($ukuran === [] || !$ada_ukuran_siap) ? ' disabled' : ''; ?>>Beli</button>
                    </div>
                </form>

                <h2 class="detail-panel__subjudul">Deskripsi</h2>
                <p class="detail-panel__deskripsi"><?php echo htmlspecialchars($deskripsi, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </article>
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
