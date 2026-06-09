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
$u_kategori_halaman = aplikasi_url('kategori');
$u_artikel_rawat = aplikasi_url('cara-membersihkan');
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
$ringkasan_kondisi = katalog_ringkasan_kondisi_beranda($semua_produk);
$ringkasan_kategori = katalog_ringkasan_kategori_beranda($semua_produk);
$ringkasan_brand = katalog_ringkasan_brand($semua_produk);
$produk_terlaris = pesanan_produk_terlaris_gabung_katalog(4);
if ($produk_terlaris === [] && $semua_produk !== []) {
    $produk_terlaris = array_slice($semua_produk, 0, 4);
}

$sudah_login = sudah_masuk();
$id_pengguna = $sudah_login ? (int) ($_SESSION['id_pengguna'] ?? 0) : 0;
$wishlist_ids = $id_pengguna > 0 ? wishlist_id_set($id_pengguna) : [];
$u_masuk = aplikasi_url('login/masuk.php');
$u_wishlist_toggle = aplikasi_url('api/wishlist-toggle');

$u_ig = 'https://www.instagram.com/' . rawurlencode((string) ($kontak_toko['sosial']['instagram'] ?? 'easenikers')) . '/';
$u_tt = 'https://www.tiktok.com/@' . rawurlencode((string) ($kontak_toko['sosial']['tiktok'] ?? 'easecondbrandofficial'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beranda - EA SENIKERS</title>
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>

    <link rel="stylesheet" href="<?php echo htmlspecialchars(aplikasi_url_aset('assets/css/beranda-toko.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(aplikasi_url_aset('assets/css/katalog-produk.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <script>
    // Handle Supabase email confirmation tokens in hash (common case) or query
    (function () {
        var h = window.location.hash || '';
        var s = window.location.search || '';
        var keKonfirmasi = h.indexOf('access_token=') !== -1 || h.indexOf('error=') !== -1
            || h.indexOf('token_hash=') !== -1
            || s.indexOf('access_token=') !== -1 || s.indexOf('code=') !== -1 || s.indexOf('error=') !== -1
            || s.indexOf('token_hash=') !== -1;
        if (keKonfirmasi) {
            var tujuan = <?php echo json_encode(aplikasi_url('login/konfirmasi_email.php'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
            window.location.replace(tujuan + s + h);
        }
    })();
    </script>
</head>
<body class="halaman-toko"
      data-wishlist-api="<?php echo htmlspecialchars($u_wishlist_toggle, ENT_QUOTES, 'UTF-8'); ?>"
      <?php if ($sudah_login): ?>data-wishlist-csrf="<?php echo htmlspecialchars(csrf_wishlist_token(), ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <section class="hero-toko hero-toko--kompak" aria-labelledby="hero-judul">
        <div class="hero-toko__isi">
            <div class="hero-toko__teks">
                <p class="hero-toko__logo-wrap">
                    <?php $ukuran_logo = 'hero'; include __DIR__ . '/../../includes/komponen/logo_teks_merek.php'; ?>
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
            <div class="baris-keunggulan" aria-label="Keunggulan belanja">
                <article class="kotak-fitur kotak-fitur--kartu">
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
                </article>
                <article class="kotak-fitur kotak-fitur--kartu">
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
                </article>
                <article class="kotak-fitur kotak-fitur--kartu">
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
                </article>
            </div>

            <section class="beranda-kondisi" aria-labelledby="judul-beranda-kondisi">
                <div class="beranda-kondisi__header">
                    <div>
                        <p class="section-eyebrow">Kondisi</p>
                        <h2 id="judul-beranda-kondisi" class="beranda-kondisi__judul">Baru atau bekas?</h2>
                    </div>
                    <a class="beranda-kondisi__lihat" href="<?php echo htmlspecialchars($tautan_produk, ENT_QUOTES, 'UTF-8'); ?>">Semua produk &rarr;</a>
                </div>
                <div class="kategori-kondisi-grid beranda-kondisi__grid">
                    <?php foreach ($ringkasan_kondisi as $kondisi_item): ?>
                        <a class="kategori-kondisi-card <?php echo htmlspecialchars((string) $kondisi_item['kelas'], ENT_QUOTES, 'UTF-8'); ?>"
                           href="<?php echo htmlspecialchars((string) $kondisi_item['url'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="kategori-kondisi-card__media">
                                <img src="<?php echo htmlspecialchars((string) $kondisi_item['gambar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="120" height="120" loading="lazy">
                            </div>
                            <div class="kategori-kondisi-card__isi">
                                <strong><?php echo htmlspecialchars((string) $kondisi_item['nama'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo (int) $kondisi_item['jumlah']; ?> produk · <?php echo htmlspecialchars((string) $kondisi_item['deskripsi'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="kategori-kondisi-card__cta">Lihat koleksi</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="beranda-kategori" aria-labelledby="judul-beranda-kategori">
                <div class="beranda-kategori__header">
                    <div>
                        <p class="section-eyebrow">Kategori</p>
                        <h2 id="judul-beranda-kategori" class="beranda-kategori__judul">Jelajahi koleksi</h2>
                    </div>
                    <a class="beranda-kategori__lihat" href="<?php echo htmlspecialchars($u_kategori_halaman, ENT_QUOTES, 'UTF-8'); ?>">Semua kategori &rarr;</a>
                </div>
                <div class="beranda-kategori__grid kategori-feature-grid">
                    <?php foreach ($ringkasan_kategori as $kategori_item): ?>
                        <a class="kategori-feature-card" href="<?php echo htmlspecialchars((string) $kategori_item['url'], ENT_QUOTES, 'UTF-8'); ?>">
                            <img src="<?php echo htmlspecialchars((string) $kategori_item['gambar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="480" height="360" loading="lazy">
                            <span class="kategori-feature-card__label"><?php echo (int) $kategori_item['jumlah']; ?> produk</span>
                            <strong><?php echo htmlspecialchars((string) $kategori_item['nama'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="kategori-feature-card__panah" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="beranda-brand" aria-labelledby="judul-beranda-brand">
                <div class="beranda-brand__header">
                    <div>
                        <p class="section-eyebrow">Brand</p>
                        <h2 id="judul-beranda-brand" class="beranda-brand__judul">Belanja berdasarkan merek</h2>
                    </div>
                    <a class="beranda-brand__lihat" href="<?php echo htmlspecialchars($u_kategori_halaman, ENT_QUOTES, 'UTF-8'); ?>#judul-merek">Semua brand &rarr;</a>
                </div>
                <?php if ($ringkasan_brand === []): ?>
                    <p class="beranda-brand__kosong">Brand akan muncul otomatis ketika katalog produk sudah terisi.</p>
                <?php else: ?>
                    <div class="beranda-brand__grid kategori-brand-grid">
                        <?php foreach ($ringkasan_brand as $brand_item): ?>
                            <a class="kategori-brand-card" href="<?php echo htmlspecialchars((string) $brand_item['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="kategori-brand-card__media">
                                    <img class="kategori-brand-card__gambar" src="<?php echo htmlspecialchars((string) $brand_item['gambar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="200" height="200" loading="lazy">
                                </div>
                                <div class="kategori-brand-card__isi">
                                    <strong><?php echo htmlspecialchars((string) $brand_item['nama'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span><?php echo (int) $brand_item['jumlah']; ?> produk</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div class="blok-terlaris__header">
                <div>
                    <p class="section-eyebrow">Produk pilihan</p>
                    <h2 id="judul-terlaris" class="blok-terlaris__judul">Rekomendasi untuk Anda</h2>
                </div>
                <a class="blok-terlaris__lihat" href="<?php echo htmlspecialchars($tautan_produk, ENT_QUOTES, 'UTF-8'); ?>">Lihat semua &rarr;</a>
            </div>

            <div class="grid-produk-terlaris katalog-grid-premium">
                    <?php if ($produk_terlaris === []): ?>
                    <p class="beranda-terlaris-kosong">Katalog akan tampil di sini ketika sudah ada produk.</p>
                    <?php else: ?>
                    <?php foreach ($produk_terlaris as $p):
                        katalog_render_kartu_produk($p, $sudah_login, $u_masuk, $wishlist_ids);
                    endforeach; ?>
                    <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="beranda-toko__footer" id="kontak-toko">
        <div class="beranda-toko__footer-isi">
            <div class="beranda-toko__footer-merek">
                <?php $ukuran_logo = 'footer'; include __DIR__ . '/../../includes/komponen/logo_teks_merek.php'; ?>
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
                <ul class="beranda-toko__footer-list beranda-toko__footer-list--ikon">
                    <?php foreach ((array) ($kontak_toko['wa'] ?? []) as $w):
                        $e = preg_replace('/\D+/', '', (string) ($w['e164'] ?? ''));
                        if ($e === '') {
                            continue;
                        }
                        $tampil = (string) ($w['tampil'] ?? '');
                        $waUrl = 'https://wa.me/' . $e;
                    ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                            <svg class="footer-ikon-svg footer-ikon-svg--wa" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            <?php echo htmlspecialchars($tampil !== '' ? $tampil : ('+' . $e), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <h2 class="beranda-toko__footer-judul beranda-toko__footer-judul--lanjut">Sosial media</h2>
                <ul class="beranda-toko__footer-list beranda-toko__footer-list--ikon">
                    <li>
                        <a href="<?php echo htmlspecialchars($u_ig, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                            <svg class="footer-ikon-svg footer-ikon-svg--ig" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="5"/>
                                <circle cx="12" cy="12" r="4"/>
                                <circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/>
                            </svg>
                            Instagram @<?php echo htmlspecialchars((string) ($kontak_toko['sosial']['instagram'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($u_tt, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                            <svg class="footer-ikon-svg footer-ikon-svg--tt" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.27 6.27 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.34-6.34V8.69a8.18 8.18 0 004.78 1.52V6.76a4.85 4.85 0 01-1.01-.07z"/>
                            </svg>
                            TikTok @<?php echo htmlspecialchars((string) ($kontak_toko['sosial']['tiktok'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <p class="beranda-toko__footer-hakcipta">&copy; <?php echo date('Y'); ?> EA SENIKERS. Hak cipta dilindungi undang-undang.</p>
    </footer>

<script src="<?php echo htmlspecialchars(aplikasi_url_aset('assets/js/katalog-premium.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
