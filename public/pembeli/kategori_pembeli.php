<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';

$bilah_pembeli_aktif = 'kategori';
$u_produk = aplikasi_url('produk');
$daftar_produk = katalog_ambil_semua_produk();

$brand_map = [];
$kondisi_map = [];
foreach ($daftar_produk as $produk) {
    $brand = trim((string) ($produk['brand'] ?? ''));
    $kondisi = trim((string) ($produk['kondisi'] ?? ''));
    if ($brand !== '') {
        if (!isset($brand_map[$brand])) {
            $gambar_produk = katalog_url_gambar_utama($produk);
            $brand_map[$brand] = [
                'jumlah' => 0,
                'gambar' => katalog_brand_logo_url($brand, $gambar_produk),
            ];
        }
        $brand_map[$brand]['jumlah']++;
    }
    if ($kondisi !== '') {
        if (!isset($kondisi_map[$kondisi])) {
            $kondisi_map[$kondisi] = ['jumlah' => 0, 'gambar' => katalog_url_gambar_utama($produk)];
        }
        $kondisi_map[$kondisi]['jumlah']++;
    }
}
ksort($brand_map, SORT_NATURAL | SORT_FLAG_CASE);
ksort($kondisi_map, SORT_NATURAL | SORT_FLAG_CASE);

$kondisi_baru = null;
$kondisi_preloved = null;
foreach (array_keys($kondisi_map) as $kondisi) {
    if ($kondisi_baru === null && strcasecmp((string) $kondisi, 'Baru') === 0) {
        $kondisi_baru = (string) $kondisi;
    }
    if ($kondisi_preloved === null && strcasecmp((string) $kondisi, 'Baru') !== 0) {
        $kondisi_preloved = (string) $kondisi;
    }
}
$kondisi_baru = $kondisi_baru ?? 'Baru';
$kondisi_preloved = $kondisi_preloved ?? 'Second';

$total_produk = count($daftar_produk);
$total_brand = count($brand_map);

$koleksi_utama = [
    [
        'judul' => 'Semua sneakers',
        'sub' => $total_produk . ' produk tersedia',
        'url' => $u_produk,
        'gambar' => $daftar_produk !== [] ? katalog_url_gambar_utama($daftar_produk[0]) : katalog_url_gambar_placeholder(),
    ],
    [
        'judul' => 'Produk baru',
        'sub' => (string) ($kondisi_map[$kondisi_baru]['jumlah'] ?? 0) . ' produk',
        'url' => aplikasi_url('produk?kondisi=' . rawurlencode($kondisi_baru)),
        'gambar' => (string) ($kondisi_map[$kondisi_baru]['gambar'] ?? katalog_url_gambar_placeholder()),
    ],
    [
        'judul' => 'Preloved terkurasi',
        'sub' => (string) ($kondisi_map[$kondisi_preloved]['jumlah'] ?? 0) . ' produk · kondisi transparan',
        'url' => aplikasi_url('produk?kondisi=' . rawurlencode($kondisi_preloved)),
        'gambar' => (string) ($kondisi_map[$kondisi_preloved]['gambar'] ?? katalog_url_gambar_placeholder()),
    ],
];

function kategori_kelas_kondisi(string $kondisi): string
{
    return strcasecmp($kondisi, 'Baru') === 0
        ? 'kategori-kondisi-card--baru'
        : 'kategori-kondisi-card--preloved';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kategori - EA SENIKERS</title>
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>

    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko halaman-kategori">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <main class="kontainer-toko" id="utama">
        <section class="kategori-hero" aria-labelledby="judul-kategori">
            <div class="kategori-hero__salin">
                <p class="section-eyebrow">Kategori</p>
                <h1 id="judul-kategori">Temukan sneakers favoritmu lebih cepat</h1>
                <p>Jelajahi koleksi berdasarkan merek, kondisi, dan kurasi utama EA SENIKERS — dari produk baru hingga preloved terpilih.</p>
                <ul class="kategori-hero__stats" aria-label="Ringkasan katalog">
                    <li><strong><?php echo (int) $total_produk; ?></strong> produk</li>
                    <li><strong><?php echo (int) $total_brand; ?></strong> brand</li>
                    <li><strong><?php echo (int) count($kondisi_map); ?></strong> kondisi</li>
                </ul>
            </div>
            <div class="kategori-hero__aksi">
                <a class="tombol-page-utama" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Lihat semua produk</a>
            </div>
        </section>

        <section class="kategori-section" aria-labelledby="judul-koleksi">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Koleksi</p>
                    <h2 id="judul-koleksi">Pilihan utama</h2>
                </div>
            </div>
            <div class="kategori-feature-grid">
                <?php foreach ($koleksi_utama as $koleksi): ?>
                    <a class="kategori-feature-card" href="<?php echo htmlspecialchars((string) $koleksi['url'], ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars((string) $koleksi['gambar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="480" height="360" loading="lazy">
                        <span class="kategori-feature-card__label"><?php echo htmlspecialchars((string) $koleksi['sub'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <strong><?php echo htmlspecialchars((string) $koleksi['judul'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="kategori-feature-card__panah" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="kategori-section" aria-labelledby="judul-merek">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Merek</p>
                    <h2 id="judul-merek">Belanja berdasarkan brand</h2>
                </div>
                <span class="kategori-section__badge"><?php echo (string) $total_brand; ?> brand</span>
            </div>

            <?php if ($brand_map === []): ?>
                <div class="kategori-kosong">
                    <p class="kategori-kosong__judul">Belum ada kategori merek</p>
                    <p class="kategori-kosong__teks">Kategori akan muncul otomatis ketika katalog produk sudah terisi.</p>
                </div>
            <?php else: ?>
                <div class="kategori-brand-grid">
                    <?php foreach ($brand_map as $brand => $meta): ?>
                        <a class="kategori-brand-card" href="<?php echo htmlspecialchars(aplikasi_url('produk?brand=' . rawurlencode((string) $brand)), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="kategori-brand-card__media">
                                <img class="kategori-brand-card__gambar" src="<?php echo htmlspecialchars((string) $meta['gambar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="200" height="200" loading="lazy">
                            </div>
                            <div class="kategori-brand-card__isi">
                                <strong><?php echo htmlspecialchars((string) $brand, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo (int) $meta['jumlah']; ?> produk</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="kategori-section kategori-section--akhir" aria-labelledby="judul-kondisi">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Kondisi</p>
                    <h2 id="judul-kondisi">Pilih sesuai kebutuhan</h2>
                </div>
            </div>
            <?php if ($kondisi_map === []): ?>
                <div class="kategori-kosong">
                    <p class="kategori-kosong__judul">Data kondisi belum tersedia</p>
                    <p class="kategori-kosong__teks">Kondisi produk akan tampil setelah katalog terisi.</p>
                </div>
            <?php else: ?>
                <div class="kategori-kondisi-grid">
                    <?php foreach ($kondisi_map as $kondisi => $meta): ?>
                        <a class="kategori-kondisi-card <?php echo htmlspecialchars(kategori_kelas_kondisi((string) $kondisi), ENT_QUOTES, 'UTF-8'); ?>"
                           href="<?php echo htmlspecialchars(aplikasi_url('produk?kondisi=' . rawurlencode((string) $kondisi)), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="kategori-kondisi-card__media">
                                <img src="<?php echo htmlspecialchars((string) $meta['gambar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="120" height="120" loading="lazy">
                            </div>
                            <div class="kategori-kondisi-card__isi">
                                <strong><?php echo htmlspecialchars(kondisi_label_pembeli((string) $kondisi), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo (int) $meta['jumlah']; ?> produk</span>
                                <span class="kategori-kondisi-card__cta">Lihat koleksi</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

</body>
</html>