<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';

$bilah_pembeli_aktif = 'tentang';
$u_beranda = aplikasi_url(''); // clean root homepage
$u_produk = aplikasi_url('produk');

$merek_ringkas = require __DIR__ . '/../../includes/konfigurasi/merek_ringkas.php';
$kontak_toko = require __DIR__ . '/../../includes/konfigurasi/kontak_toko.php';

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
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>

    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <main class="kontainer-toko halaman-tentang" id="utama">
        <section class="tentang-hero" aria-labelledby="judul-tentang">
            <div class="tentang-hero__brand">
                <?php $ukuran_logo = 'tentang'; include __DIR__ . '/../../includes/komponen/logo_teks_merek.php'; ?>
            </div>
            <div class="tentang-hero__salin">
                <h1 id="judul-tentang">Sneakers baru dan preloved yang dipilih dengan lebih teliti.</h1>
                <p>EA SENIKERS adalah toko sepatu online yang berfokus pada sneakers multi-merek, produk baru, dan pilihan preloved terkurasi. Setiap produk ditampilkan dengan harga jelas, foto produk, serta informasi kondisi yang mudah dipahami.</p>
            </div>
            <div class="tentang-hero__aksi">
                <a class="tombol-page-utama" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Jelajahi katalog</a>
                <?php if ($wa_utama !== ''): ?>
                    <a class="tombol-page-sekunder" href="<?php echo htmlspecialchars($wa_utama, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Hubungi WhatsApp</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="tentang-section" aria-labelledby="judul-tentang-kami">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Tentang kami</p>
                    <h2 id="judul-tentang-kami">Sneakers untuk setiap langkah pemakaiannya</h2>
                </div>
            </div>
            <div class="tentang-cerita">
                <p>EA SENIKERS adalah toko sepatu online yang menjual <strong>sneakers multi-merek</strong> — Nike, Adidas, Vans, Converse, New Balance, dan beberapa label lain. Kami melayani dua kategori utama: produk <strong>baru</strong> langsung dari supplier resmi, dan koleksi <strong>preloved terkurasi</strong> yang kami pilih satu per satu agar layak dipertimbangkan.</p>
                <p>Komitmen kami sederhana: <strong>transparansi</strong>. Setiap produk preloved kami beri penjelasan kondisi yang jujur — bukan sekadar &ldquo;mulus&rdquo;, tapi detail seperti ada bercak, jahitan yang mulai longgar, atau sol yang sedikit aus. Harga selalu tampil di katalog tanpa perlu chat. Foto produk diambil sendiri, bukan stok katalog merek.</p>
                <p>Cocok untuk <strong>sneakerhead</strong> yang ingin menambah koleksi tanpa khawatir, <strong>pengguna kasual</strong> yang mencari sepatu nyaman sehari-hari, juga <strong>pembeli pertama</strong> yang ingin masuk ke dunia sneakers dengan harga ramah lewat koleksi preloved kami.</p>
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
                <p>Gunakan WhatsApp toko atau kunjungi toko offline kami di Padang Panjang.</p>
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
