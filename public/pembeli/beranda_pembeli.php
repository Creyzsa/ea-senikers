<?php
require_once __DIR__ . '/../../includes/sesi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$nama_sapa = trim((string) ($_SESSION['nama_pengguna'] ?? ''));
if ($nama_sapa === '') {
    $nama_sapa = 'Pembeli';
}
$bilah_pembeli_aktif = 'beranda';
$tautan_produk = aplikasi_url('pembeli/produk.php');
$merek_ringkas = require __DIR__ . '/../../includes/merek_ringkas.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beranda — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <section class="hero-toko hero-toko--kompak" aria-labelledby="hero-judul">
        <div class="hero-toko__isi">
            <div class="hero-toko__teks">
                <p class="hero-toko__meta"><?php echo htmlspecialchars($merek_ringkas['hero_meta_satu_baris'], ENT_QUOTES, 'UTF-8'); ?></p>
                <h1 id="hero-judul" class="hero-toko__judul"><?php echo htmlspecialchars($merek_ringkas['hero_judul'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="hero-toko__sapa">Halo, <span class="hero-toko__nama"><?php echo htmlspecialchars($nama_sapa, ENT_QUOTES, 'UTF-8'); ?></span> · <span class="hero-toko__nama-merek">EA SENIKERS</span></p>
                <p class="hero-toko__sub"><?php echo htmlspecialchars($merek_ringkas['hero_sub_bullet'], ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="hero-toko__aksi">
                    <a class="tombol-oranye-besar hero-toko__cta" href="<?php echo htmlspecialchars($tautan_produk, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($merek_ringkas['hero_teks_tombol'], ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            </div>
        </div>
    </section>

    <main class="kontainer-toko" id="utama">
        <section class="blok-terlaris" aria-labelledby="judul-terlaris">
            <div class="blok-terlaris__header">
                <h2 id="judul-terlaris" class="blok-terlaris__judul">Produk Terlaris 🔥</h2>
                <a class="blok-terlaris__lihat" href="<?php echo htmlspecialchars($tautan_produk, ENT_QUOTES, 'UTF-8'); ?>">Lihat Semua →</a>
            </div>

            <div class="susunan-produk-fitur">
                <div class="grid-produk-terlaris">
                    <?php
                    $produk_demo = [
                        ['nama' => 'Sneakers Street Runner', 'harga' => 'Rp 899.000'],
                        ['nama' => 'Kasual Daily Comfort', 'harga' => 'Rp 649.000'],
                        ['nama' => 'Sport Active Lite', 'harga' => 'Rp 1.199.000'],
                        ['nama' => 'Classic Leather Series', 'harga' => 'Rp 1.450.000'],
                    ];
                    foreach ($produk_demo as $p):
                    ?>
                    <article class="kartu-produk">
                        <div class="kartu-produk__gambar">
                            <svg class="kartu-produk__ikon-sepatu" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 72 44" fill="none" aria-hidden="true">
                                <ellipse cx="36" cy="36" rx="28" ry="5" fill="currentColor" opacity="0.1"/>
                                <path d="M6 30c2-9 12-14 26-13l22 3a6 6 0 016 5v2a3 3 0 01-3 3H8l-2-2v-5z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" fill="currentColor" fill-opacity="0.07"/>
                                <path d="M10 28V24l8-12c6-2 16-2 24 2l10 8 4 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.42"/>
                                <path d="M14 30h40" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" opacity="0.2"/>
                            </svg>
                        </div>
                        <div class="kartu-produk__isi">
                            <h3 class="kartu-produk__nama"><?php echo htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="kartu-produk__harga"><?php echo htmlspecialchars($p['harga'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <a class="tombol-beli" href="<?php echo htmlspecialchars($tautan_produk, ENT_QUOTES, 'UTF-8'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                Beli
                            </a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>

                <aside class="kotak-fitur" aria-label="Keunggulan belanja">
                    <div class="kotak-fitur__baris">
                        <span class="kotak-fitur__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                            </svg>
                        </span>
                        <div class="kotak-fitur__teks">
                            <strong>Produk Berkualitas</strong>
                            <span>Sepatu baru &amp; second dengan kualitas terpilih dan layak pakai</span>
                        </div>
                    </div>
                    <div class="kotak-fitur__baris">
                        <span class="kotak-fitur__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </span>
                        <div class="kotak-fitur__teks">
                            <strong>Kondisi Transparan</strong>
                            <span>Detail kondisi produk dijelaskan secara jujur (real pict &amp; deskripsi)</span>
                        </div>
                    </div>
                    <div class="kotak-fitur__baris">
                        <span class="kotak-fitur__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <div class="kotak-fitur__teks">
                            <strong>Harga Kompetitif</strong>
                            <span>Harga bersaing sesuai kondisi dan kualitas produk</span>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </main>

</body>
</html>
