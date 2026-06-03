<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';

$bilah_pembeli_aktif = 'kategori';
$u_produk = aplikasi_url('pembeli/produk.php');
$daftar_produk = katalog_ambil_semua_produk();

$brand_map = [];
$kondisi_map = [];
foreach ($daftar_produk as $produk) {
    $brand = trim((string) ($produk['brand'] ?? ''));
    $kondisi = trim((string) ($produk['kondisi'] ?? ''));
    if ($brand !== '') {
        if (!isset($brand_map[$brand])) {
            $brand_map[$brand] = ['jumlah' => 0, 'gambar' => katalog_url_gambar_utama($produk)];
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

$koleksi_utama = [
    [
        'judul' => 'Semua sneakers',
        'sub' => count($daftar_produk) . ' produk tersedia',
        'url' => $u_produk,
        'gambar' => $daftar_produk !== [] ? katalog_url_gambar_utama($daftar_produk[0]) : katalog_url_gambar_placeholder(),
    ],
    [
        'judul' => 'Produk baru',
        'sub' => (string) ($kondisi_map[$kondisi_baru]['jumlah'] ?? 0) . ' produk',
        'url' => aplikasi_url('pembeli/produk.php?kondisi=' . rawurlencode($kondisi_baru)),
        'gambar' => (string) ($kondisi_map[$kondisi_baru]['gambar'] ?? katalog_url_gambar_placeholder()),
    ],
    [
        'judul' => 'Preloved terkurasi',
        'sub' => 'Kondisi dijelaskan transparan',
        'url' => aplikasi_url('pembeli/produk.php?kondisi=' . rawurlencode($kondisi_preloved)),
        'gambar' => (string) ($kondisi_map[$kondisi_preloved]['gambar'] ?? katalog_url_gambar_placeholder()),
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kategori - EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <main class="kontainer-toko halaman-kategori" id="utama">
        <section class="pembeli-page-hero" aria-labelledby="judul-kategori">
            <div>
                <p class="section-eyebrow">Kategori</p>
                <h1 id="judul-kategori">Belanja berdasarkan koleksi yang paling relevan.</h1>
                <p>Susuri produk berdasarkan merek, kondisi, dan koleksi utama EA SENIKERS agar pilihan terasa lebih cepat dan terarah.</p>
            </div>
            <a class="tombol-page-utama" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Lihat semua produk</a>
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
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="kategori-section" aria-labelledby="judul-merek">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Merek</p>
                    <h2 id="judul-merek">Temukan berdasarkan brand</h2>
                </div>
                <span><?php echo (string) count($brand_map); ?> brand</span>
            </div>

            <?php if ($brand_map === []): ?>
                <div class="panel-pembeli-teks">
                    <h1>Belum ada kategori merek</h1>
                    <p>Kategori akan muncul otomatis ketika katalog produk sudah terisi.</p>
                </div>
            <?php else: ?>
                <div class="kategori-grid">
                    <?php foreach ($brand_map as $brand => $meta): ?>
                        <a class="kategori-card" href="<?php echo htmlspecialchars(aplikasi_url('pembeli/produk.php?brand=' . rawurlencode((string) $brand)), ENT_QUOTES, 'UTF-8'); ?>">
                            <img class="kategori-card__gambar" src="<?php echo htmlspecialchars((string) $meta['gambar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="160" height="160" loading="lazy">
                            <span class="kategori-card__meta"><?php echo (int) $meta['jumlah']; ?> produk</span>
                            <strong><?php echo htmlspecialchars((string) $brand, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="kategori-section" aria-labelledby="judul-kondisi">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Kondisi</p>
                    <h2 id="judul-kondisi">Pilih sesuai kebutuhan</h2>
                </div>
            </div>
            <div class="kategori-chip-panel">
                <?php foreach ($kondisi_map as $kondisi => $meta): ?>
                    <a href="<?php echo htmlspecialchars(aplikasi_url('pembeli/produk.php?kondisi=' . rawurlencode((string) $kondisi)), ENT_QUOTES, 'UTF-8'); ?>">
                        <strong><?php echo htmlspecialchars(kondisi_label_pembeli((string) $kondisi), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo (int) $meta['jumlah']; ?> produk</span>
                    </a>
                <?php endforeach; ?>
                <?php if ($kondisi_map === []): ?>
                    <p>Data kondisi produk belum tersedia.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

</body>
</html>
