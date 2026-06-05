<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/repositori/pesanan_repositori.php';

$nama_sapa = trim((string) ($_SESSION['nama_pengguna'] ?? ''));
$sudah_login_untuk_sapa = sudah_masuk();
if ($nama_sapa === '') {
    $nama_sapa = 'Pengunjung';
}

$bilah_pembeli_aktif = 'beranda';
$tautan_produk = aplikasi_url('produk');
$u_artikel_rawat = aplikasi_url('cara-membersihkan');
$logo_toko = aplikasi_url('assets/images/logo.jpeg');
$merek_ringkas = require __DIR__ . '/../../includes/konfigurasi/merek_ringkas.php';
$kontak_toko = require __DIR__ . '/../../includes/konfigurasi/kontak_toko.php';

$whatsapp_ada = false;
foreach ((array) ($kontak_toko['wa'] ?? []) as $__wa) {
    if (trim((string) ($__wa['e164'] ?? '')) !== '') {
        $whatsapp_ada = true;
        break;
    }
}

$semua_produk = katalog_ambil_semua_produk();
$produk_terlaris = pesanan_produk_terlaris_gabung_katalog(4);
if ($produk_terlaris === [] && $semua_produk !== []) {
    $produk_terlaris = array_slice($semua_produk, 0, 4);
}

$u_ig = 'https://www.instagram.com/' . rawurlencode((string) ($kontak_toko['sosial']['instagram'] ?? 'easenikers')) . '/';
$u_tt = 'https://www.tiktok.com/@' . rawurlencode((string) ($kontak_toko['sosial']['tiktok'] ?? 'easenikers'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beranda - EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <script>
    // Handle Supabase email confirmation tokens in hash (common case) or query
    (function () {
        var h = window.location.hash || '';
        var s = window.location.search || '';
        var keKonfirmasi = h.indexOf('access_token=') !== -1 || h.indexOf('error=') !== -1
            || s.indexOf('access_token=') !== -1 || s.indexOf('code=') !== -1 || s.indexOf('error=') !== -1;
        if (keKonfirmasi) {
            var tujuan = <?php echo json_encode(aplikasi_url('login/konfirmasi_email.php'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
            window.location.replace(tujuan + s + h);
        }
    })();
    </script>
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <section class="hero-toko hero-toko--kompak" aria-labelledby="hero-judul">
        <div class="hero-toko__isi">
            <div class="hero-toko__teks">
                <p class="hero-toko__logo-wrap">
                    <img class="hero-toko__logo" src="<?php echo htmlspecialchars($logo_toko, ENT_QUOTES, 'UTF-8'); ?>" width="80" height="80" alt="EA SENIKERS" decoding="async" fetchpriority="high">
                </p>
                <p class="hero-toko__meta"><?php echo htmlspecialchars($merek_ringkas['hero_meta_satu_baris'], ENT_QUOTES, 'UTF-8'); ?></p>
                <h1 id="hero-judul" class="hero-toko__judul"><?php echo htmlspecialchars($merek_ringkas['hero_judul'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php if ($sudah_login_untuk_sapa): ?>
                <p class="hero-toko__sapa">Halo, <span class="hero-toko__nama"><?php echo htmlspecialchars($nama_sapa, ENT_QUOTES, 'UTF-8'); ?></span> - <span class="hero-toko__nama-merek">EA SENIKERS</span></p>
                <?php else: ?>
                <p class="hero-toko__sapa">Selamat datang di <span class="hero-toko__nama-merek">EA SENIKERS</span></p>
                <?php endif; ?>
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
                <div>
                    <p class="section-eyebrow">Produk pilihan</p>
                    <h2 id="judul-terlaris" class="blok-terlaris__judul">Rekomendasi untuk Anda</h2>
                </div>
                <a class="blok-terlaris__lihat" href="<?php echo htmlspecialchars($tautan_produk, ENT_QUOTES, 'UTF-8'); ?>">Lihat semua &rarr;</a>
            </div>

            <div class="susunan-produk-fitur">
                <div class="grid-produk-terlaris">
                    <?php if ($produk_terlaris === []): ?>
                    <p class="beranda-terlaris-kosong">Katalog akan tampil di sini ketika sudah ada produk.</p>
                    <?php else: ?>
                    <?php foreach ($produk_terlaris as $p):
                        $id = (string) ($p['id_produk'] ?? '');
                        $nama = (string) ($p['nama_produk'] ?? '');
                        $brand = (string) ($p['brand'] ?? '');
                        $harga = (int) ($p['harga'] ?? 0);
                        $u_detail = aplikasi_url('detail-produk?id=' . rawurlencode($id));
                        $u_gambar = katalog_url_gambar_utama($p);
                    ?>
                    <article class="kartu-produk">
                        <a class="kartu-produk__tautan-gambar" href="<?php echo htmlspecialchars($u_detail, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="kartu-produk__gambar">
                            <img class="kartu-produk__foto" src="<?php echo htmlspecialchars($u_gambar, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" width="400" height="275">
                        </div>
                        </a>
                        <div class="kartu-produk__isi">
                            <?php if ($brand !== ''): ?><p class="kartu-produk__brand"><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                            <h3 class="kartu-produk__nama">
                                <a class="kartu-produk__tautan-nama" href="<?php echo htmlspecialchars($u_detail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></a>
                            </h3>
                            <p class="kartu-produk__harga"><?php echo htmlspecialchars(katalog_format_rupiah($harga), ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php if (($p['terjual'] ?? 0) > 0): ?>
                                <p style="font-size:0.65rem; color:var(--color-text-muted); margin:-0.1rem 0 0.2rem;"><?= (int)$p['terjual'] ?>+ terjual</p>
                            <?php endif; ?>
                            <a class="tombol-beli" href="<?php echo htmlspecialchars($u_detail, ENT_QUOTES, 'UTF-8'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                Beli
                            </a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <aside class="kotak-fitur" aria-label="Keunggulan belanja">
                    <div class="kotak-fitur__baris">
                        <span class="kotak-fitur__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                            </svg>
                        </span>
                        <div class="kotak-fitur__teks">
                            <strong>Produk berkualitas</strong>
                            <span>Sepatu baru dan preloved dengan kualitas terpilih.</span>
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
                            <strong>Kondisi transparan</strong>
                            <span>Detail produk dibuat jelas sebelum Anda checkout.</span>
                        </div>
                    </div>
                    <div class="kotak-fitur__baris">
                        <span class="kotak-fitur__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <div class="kotak-fitur__teks">
                            <strong>Harga terbuka</strong>
                            <span>Harga katalog tampil jelas sesuai kondisi produk.</span>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </main>

    <footer class="beranda-toko__footer" id="kontak-toko">
        <div class="beranda-toko__footer-isi">
            <div class="beranda-toko__footer-merek">
                <img class="beranda-toko__footer-logo" src="<?php echo htmlspecialchars($logo_toko, ENT_QUOTES, 'UTF-8'); ?>" width="120" height="120" alt="EA SENIKERS" loading="lazy" decoding="async">
                <p class="beranda-toko__footer-tagline">Belanja sepatu nyaman dan terpercaya.</p>
            </div>
            <div class="beranda-toko__footer-kolom">
                <h2 class="beranda-toko__footer-judul">Tips &amp; Panduan</h2>
                <ul class="beranda-toko__footer-list beranda-toko__footer-list--ikon">
                    <li>
                        <a href="<?php echo htmlspecialchars($u_artikel_rawat, ENT_QUOTES, 'UTF-8'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                            </svg>
                            Cara membersihkan sepatu
                        </a>
                    </li>
                </ul>
                <p class="beranda-toko__footer-keterangan">Rawat sneakers biar awet &amp; tetap kinclong.</p>
            </div>
            <div class="beranda-toko__footer-kolom">
                <h2 class="beranda-toko__footer-judul">Lokasi toko (offline)</h2>
                <a class="beranda-toko__tautan-cta" href="<?php echo htmlspecialchars((string) $kontak_toko['url_peta'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    Buka di Google Maps
                </a>
                <p class="beranda-toko__footer-keterangan"><?php echo htmlspecialchars((string) ($kontak_toko['teks_peta'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="beranda-toko__footer-kolom">
                <h2 class="beranda-toko__footer-judul">WhatsApp</h2>
                <?php if (!$whatsapp_ada): ?>
                    <p class="beranda-toko__footer-keterangan">Nomor layanan akan ditampilkan melalui pengaturan toko.</p>
                <?php endif; ?>
                <ul class="beranda-toko__footer-list">
                    <?php foreach ((array) ($kontak_toko['wa'] ?? []) as $w):
                        $e = preg_replace('/\D+/', '', (string) ($w['e164'] ?? ''));
                        if ($e === '') {
                            continue;
                        }
                        $tampil = (string) ($w['tampil'] ?? '');
                        $waUrl = 'https://wa.me/' . $e;
                    ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($tampil !== '' ? $tampil : ('+' . $e), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <h2 class="beranda-toko__footer-judul beranda-toko__footer-judul--lanjut">Sosial media</h2>
                <ul class="beranda-toko__footer-list">
                    <li>
                        <a href="<?php echo htmlspecialchars($u_ig, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Instagram @<?php echo htmlspecialchars((string) ($kontak_toko['sosial']['instagram'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($u_tt, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">TikTok @<?php echo htmlspecialchars((string) ($kontak_toko['sosial']['tiktok'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                </ul>
            </div>
        </div>
        <p class="beranda-toko__footer-hakcipta">&copy; <?php echo date('Y'); ?> EA SENIKERS. Hak cipta dilindungi undang-undang.</p>
    </footer>

</body>
</html>
