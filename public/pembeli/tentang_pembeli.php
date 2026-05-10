<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/sesi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$bilah_pembeli_aktif = 'tentang';
$u_beranda = aplikasi_url('pembeli/beranda_pembeli.php');
$u_produk = aplikasi_url('pembeli/produk.php');
$logo_toko = aplikasi_url('assets/images/logo-easenikers.svg');
$merek_ringkas = require __DIR__ . '/../../includes/merek_ringkas.php';
$kontak_toko = require __DIR__ . '/../../includes/kontak_toko.php';

$instagram = trim((string) ($kontak_toko['sosial']['instagram'] ?? 'easenikers'));
$tiktok = trim((string) ($kontak_toko['sosial']['tiktok'] ?? 'easecondbrandofficial'));
$u_ig = 'https://www.instagram.com/' . rawurlencode($instagram) . '/';
$u_tt = 'https://www.tiktok.com/@' . rawurlencode($tiktok);
$wa_utama = '';
foreach ((array) ($kontak_toko['wa'] ?? []) as $wa) {
    $e164 = preg_replace('/\D+/', '', (string) ($wa['e164'] ?? ''));
    if ($e164 !== '') {
        $wa_utama = 'https://wa.me/' . $e164;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tentang - EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <main class="kontainer-toko halaman-tentang" id="utama">
        <section class="tentang-hero" aria-labelledby="judul-tentang">
            <div class="tentang-hero__brand">
                <img src="<?php echo htmlspecialchars($logo_toko, ENT_QUOTES, 'UTF-8'); ?>" width="260" height="50" alt="EA SENIKERS" decoding="async">
                <p class="section-eyebrow"><?php echo htmlspecialchars((string) $merek_ringkas['badge_toko'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="tentang-hero__teks">
                <h1 id="judul-tentang">Sneakers baru dan preloved yang dipilih dengan lebih teliti.</h1>
                <p>EA SENIKERS adalah toko sepatu online yang berfokus pada sneakers multi-merek, produk baru, dan pilihan preloved terkurasi. Setiap produk ditampilkan dengan harga jelas, foto produk, serta informasi kondisi yang mudah dipahami.</p>
                <div class="tentang-hero__aksi">
                    <a class="tombol-page-utama" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Jelajahi katalog</a>
                    <?php if ($wa_utama !== ''): ?>
                        <a class="tombol-page-sekunder" href="<?php echo htmlspecialchars($wa_utama, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Hubungi WhatsApp</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="tentang-section" aria-labelledby="judul-nilai">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Nilai toko</p>
                    <h2 id="judul-nilai">Dibuat untuk belanja yang terasa aman</h2>
                </div>
            </div>
            <div class="tentang-value-grid">
                <article>
                    <span>01</span>
                    <h3>Kurasi kualitas</h3>
                    <p>Produk dipilih agar layak tampil dan nyaman dipertimbangkan sebelum dibeli.</p>
                </article>
                <article>
                    <span>02</span>
                    <h3>Kondisi transparan</h3>
                    <p>Informasi produk dibuat jelas, terutama untuk koleksi preloved yang butuh detail kondisi.</p>
                </article>
                <article>
                    <span>03</span>
                    <h3>Harga terbuka</h3>
                    <p>Harga ditampilkan langsung di katalog sehingga pembeli bisa membandingkan dengan cepat.</p>
                </article>
                <article>
                    <span>04</span>
                    <h3>Layanan terhubung</h3>
                    <p>Kontak toko dan sosial media disediakan agar pembeli mudah bertanya sebelum checkout.</p>
                </article>
            </div>
        </section>

        <section class="tentang-section tentang-dua-kolom" aria-labelledby="judul-sosial">
            <div class="tentang-panel">
                <p class="section-eyebrow">Sosial media</p>
                <h2 id="judul-sosial">Kenali aktivitas EA SENIKERS</h2>
                <p>Aplikasi ini menggunakan Instagram <strong>@<?php echo htmlspecialchars($instagram, ENT_QUOTES, 'UTF-8'); ?></strong> dan TikTok <strong>@<?php echo htmlspecialchars($tiktok, ENT_QUOTES, 'UTF-8'); ?></strong> sebagai kanal sosial toko.</p>
                <div class="tentang-link-row">
                    <a href="<?php echo htmlspecialchars($u_ig, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Instagram</a>
                    <a href="<?php echo htmlspecialchars($u_tt, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">TikTok</a>
                </div>
            </div>

            <div class="tentang-panel tentang-panel--gelap">
                <p class="section-eyebrow">Kontak toko</p>
                <h2>Butuh bantuan memilih?</h2>
                <p>Gunakan WhatsApp toko atau cek lokasi offline dari footer beranda bila informasi sudah diisi oleh admin.</p>
                <div class="tentang-link-row">
                    <?php if ($wa_utama !== ''): ?>
                        <a href="<?php echo htmlspecialchars($wa_utama, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars((string) ($kontak_toko['url_peta'] ?? 'https://www.google.com/maps'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Google Maps</a>
                    <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>#kontak-toko">Footer kontak</a>
                </div>
            </div>
        </section>
    </main>

</body>
</html>
