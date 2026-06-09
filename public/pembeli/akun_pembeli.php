<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/profil_pembeli_repositori.php';

wajib_peran_pembeli();

$bilah_pembeli_aktif = 'akun';
$u_beranda = aplikasi_url('');
$u_produk = aplikasi_url('produk');
$u_pesanan = aplikasi_url('pesanan');
$u_keranjang = aplikasi_url('keranjang');
$u_wishlist = aplikasi_url('wishlist');
$u_keluar = aplikasi_url('login/keluar.php');
$u_akun = aplikasi_url('akun');
$u_bantuan = aplikasi_url('bantuan');
$u_lapor = aplikasi_url('lapor-masalah');
$kontak_toko = require __DIR__ . '/../../includes/konfigurasi/kontak_toko.php';

if (!isset($_SESSION['csrf_akun_pembeli']) || !is_string($_SESSION['csrf_akun_pembeli'])) {
    $_SESSION['csrf_akun_pembeli'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_akun_pembeli'];

$id_pengguna = ambil_id_pengguna_efektif();
$errors = [];
$flash = $_SESSION['flash_akun_pembeli'] ?? null;
unset($_SESSION['flash_akun_pembeli']);

$profil = profil_pembeli_ambil($id_pengguna);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $errors[] = 'Mohon muat ulang halaman.';
    } elseif ($id_pengguna <= 0) {
        $errors[] = 'Akun belum terhubung, mohon masuk ulang.';
    } else {
        $input = [
            'no_hp' => (string) ($_POST['no_hp'] ?? ''),
            'nama_penerima' => (string) ($_POST['nama_penerima'] ?? ''),
            'provinsi' => (string) ($_POST['provinsi'] ?? ''),
            'kota' => (string) ($_POST['kota'] ?? ''),
            'kecamatan' => (string) ($_POST['kecamatan'] ?? ''),
            'kode_pos' => (string) ($_POST['kode_pos'] ?? ''),
            'alamat_detail' => (string) ($_POST['alamat_detail'] ?? ''),
            'lat' => (string) ($_POST['lat'] ?? ''),
            'lng' => (string) ($_POST['lng'] ?? ''),
        ];
        $errors = profil_pembeli_validasi($input);
        if ($errors === []) {
            if (profil_pembeli_simpan($id_pengguna, $input)) {
                unset(
                    $_SESSION['checkout_destinasi'],
                    $_SESSION['checkout_kurir'],
                    $_SESSION['checkout_destinasi_auto']
                );
                $_SESSION['flash_akun_pembeli'] = ['jenis' => 'sukses', 'teks' => 'Profil pengiriman berhasil disimpan.'];
                header('Location: ' . $u_akun . '#profil-pengiriman');
                exit;
            }
            $errors[] = 'Gagal menyimpan profil. Coba lagi sebentar.';
        }
        $profil = array_merge($profil, $input);
    }
}

$nama = trim((string) ($_SESSION['nama_pengguna'] ?? ''));
$email = trim((string) ($_SESSION['email_pengguna'] ?? ''));
$nama_tampil = $nama !== '' ? $nama : 'Pembeli EA SENIKERS';
$email_tampil = $email !== '' ? $email : 'Email belum tersedia';
$inisial = strtoupper(function_exists('mb_substr') ? mb_substr($nama_tampil, 0, 1, 'UTF-8') : substr($nama_tampil, 0, 1));
$wa_utama = '';
foreach ((array) ($kontak_toko['wa'] ?? []) as $wa) {
    $e164 = preg_replace('/\D+/', '', (string) ($wa['e164'] ?? ''));
    if ($e164 !== '') {
        $wa_utama = 'https://wa.me/' . $e164;
        break;
    }
}

$alamat_lengkap = true;
foreach (['no_hp', 'nama_penerima', 'provinsi', 'kota', 'kecamatan', 'alamat_detail'] as $__k) {
    if (trim((string) ($profil[$__k] ?? '')) === '') {
        $alamat_lengkap = false;
        break;
    }
}

$menu_akun = [
    [
        'url' => $u_pesanan,
        'judul' => 'Pesanan saya',
        'deskripsi' => 'Pantau pembayaran & pengiriman',
        'ikon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.5a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>',
    ],
    [
        'url' => $u_keranjang,
        'judul' => 'Keranjang',
        'deskripsi' => 'Produk siap checkout',
        'ikon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c.432 0 .82.268.982.656l1.2 3c.149.373.456.624.8.624H18.75M7.5 14.25L5.106 5.272M15.75 14.25l2.106-8.978M9.75 18.75a1.125 1.125 0 100-2.25 1.125 1.125 0 000 2.25zm7.5 0a1.125 1.125 0 100-2.25 1.125 1.125 0 000 2.25z"/>',
    ],
    [
        'url' => $u_wishlist,
        'judul' => 'Wishlist',
        'deskripsi' => 'Favorit yang disimpan',
        'ikon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>',
    ],
    [
        'url' => $u_produk,
        'judul' => 'Katalog produk',
        'deskripsi' => 'Cari sneakers favorit',
        'ikon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>',
    ],
    [
        'url' => $u_bantuan,
        'judul' => 'Bantuan',
        'deskripsi' => 'FAQ & hubungi kami',
        'ikon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/>',
    ],
    [
        'url' => $u_lapor,
        'judul' => 'Laporkan masalah',
        'deskripsi' => 'Kendala transaksi atau bug',
        'ikon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>',
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akun saya — EA SENIKERS</title>
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>

    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
</head>
<body class="halaman-toko halaman-akun">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="akun-wrap" id="utama">
    <nav class="detail-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">Beranda</a>
        <span aria-hidden="true"> / </span>
        <span>Akun</span>
    </nav>

    <header class="akun-hero" aria-labelledby="judul-akun">
        <div class="akun-hero__profil">
            <div class="akun-avatar" aria-hidden="true"><?php echo htmlspecialchars($inisial, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="akun-hero__teks">
                <p class="akun-hero__eyebrow">Akun pembeli</p>
                <h1 id="judul-akun" class="akun-hero__judul"><?php echo htmlspecialchars($nama_tampil, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="akun-hero__meta"><?php echo htmlspecialchars($email_tampil, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <div class="akun-hero__aksi">
            <span class="akun-hero__chip<?php echo $alamat_lengkap ? ' akun-hero__chip--hijau' : ' akun-hero__chip--kuning'; ?>">
                <?php echo $alamat_lengkap ? 'Alamat lengkap' : 'Alamat belum lengkap'; ?>
            </span>
            <a class="akun-hero__keluar" href="<?php echo htmlspecialchars($u_keluar, ENT_QUOTES, 'UTF-8'); ?>">Keluar</a>
        </div>
    </header>

    <div class="akun-stats" aria-label="Ringkasan akun">
        <article class="akun-stat">
            <span class="akun-stat__label">Nama tampil</span>
            <strong class="akun-stat__nilai"><?php echo htmlspecialchars($nama_tampil, ENT_QUOTES, 'UTF-8'); ?></strong>
        </article>
        <article class="akun-stat">
            <span class="akun-stat__label">Email</span>
            <strong class="akun-stat__nilai"><?php echo htmlspecialchars($email_tampil, ENT_QUOTES, 'UTF-8'); ?></strong>
        </article>
        <article class="akun-stat">
            <span class="akun-stat__label">Profil pengiriman</span>
            <strong class="akun-stat__nilai"><?php echo $alamat_lengkap ? 'Siap checkout' : 'Perlu dilengkapi'; ?></strong>
        </article>
    </div>

    <div class="akun-layout">
        <aside class="akun-panel akun-panel--menu" aria-labelledby="judul-menu-akun">
            <div class="akun-panel__header">
                <h2 id="judul-menu-akun" class="akun-panel__judul">Menu cepat</h2>
                <p class="akun-panel__sub">Kelola belanja &amp; bantuan</p>
            </div>
            <nav class="akun-menu-grid">
                <?php foreach ($menu_akun as $item): ?>
                    <a class="akun-menu-card" href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="akun-menu-card__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><?php echo $item['ikon']; ?></svg>
                        </span>
                        <span class="akun-menu-card__teks">
                            <strong><?php echo htmlspecialchars($item['judul'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo htmlspecialchars($item['deskripsi'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                        <svg class="akun-menu-card__panah" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                <?php endforeach; ?>
                <?php if ($wa_utama !== ''): ?>
                    <a class="akun-menu-card akun-menu-card--wa" href="<?php echo htmlspecialchars($wa_utama, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <span class="akun-menu-card__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </span>
                        <span class="akun-menu-card__teks">
                            <strong>Layanan pelanggan</strong>
                            <span>Chat via WhatsApp</span>
                        </span>
                        <svg class="akun-menu-card__panah" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                <?php endif; ?>
            </nav>
        </aside>

        <section class="akun-panel akun-panel--profil" id="profil-pengiriman" aria-labelledby="judul-profil-pengiriman">
            <div class="akun-panel__header">
                <h2 id="judul-profil-pengiriman" class="akun-panel__judul">Profil pengiriman</h2>
                <p class="akun-panel__sub">Dipakai otomatis saat checkout</p>
            </div>

            <?php if (is_array($flash)): ?>
                <div class="akun-alert akun-alert--<?php echo htmlspecialchars((string) ($flash['jenis'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>" role="status">
                    <?php echo htmlspecialchars((string) ($flash['teks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="akun-alert akun-alert--error" role="alert">
                    <strong>Periksa kembali isian:</strong>
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="akun-form-profil" method="post" action="<?php echo htmlspecialchars($u_akun, ENT_QUOTES, 'UTF-8'); ?>#profil-pengiriman" novalidate>
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="akun-form-grid">
                    <label class="akun-field">
                        <span>Nama penerima</span>
                        <input type="text" name="nama_penerima" value="<?php echo htmlspecialchars($profil['nama_penerima'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="120" autocomplete="name">
                    </label>
                    <label class="akun-field">
                        <span>Nomor HP</span>
                        <input type="tel" name="no_hp" value="<?php echo htmlspecialchars($profil['no_hp'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="20" inputmode="numeric" autocomplete="tel" placeholder="08xxxxxxxxxx">
                    </label>
                    <label class="akun-field">
                        <span>Provinsi</span>
                        <select name="provinsi" required data-cascading="provinsi" data-saved="<?php echo htmlspecialchars($profil['provinsi'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="address-level1">
                            <option value="">Memuat provinsi...</option>
                            <?php if (trim($profil['provinsi']) !== ''): ?>
                                <option value="<?php echo htmlspecialchars($profil['provinsi'], ENT_QUOTES, 'UTF-8'); ?>" selected><?php echo htmlspecialchars($profil['provinsi'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endif; ?>
                        </select>
                    </label>
                    <label class="akun-field">
                        <span>Kota / kabupaten</span>
                        <select name="kota" required data-cascading="kota" data-saved="<?php echo htmlspecialchars($profil['kota'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="address-level2">
                            <option value="">-- Pilih provinsi dulu --</option>
                            <?php if (trim($profil['kota']) !== ''): ?>
                                <option value="<?php echo htmlspecialchars($profil['kota'], ENT_QUOTES, 'UTF-8'); ?>" selected><?php echo htmlspecialchars($profil['kota'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endif; ?>
                        </select>
                    </label>
                    <label class="akun-field">
                        <span>Kecamatan</span>
                        <select name="kecamatan" required data-cascading="kecamatan" data-saved="<?php echo htmlspecialchars($profil['kecamatan'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="address-level3">
                            <option value="">-- Pilih kota dulu --</option>
                            <?php if (trim($profil['kecamatan']) !== ''): ?>
                                <option value="<?php echo htmlspecialchars($profil['kecamatan'], ENT_QUOTES, 'UTF-8'); ?>" selected><?php echo htmlspecialchars($profil['kecamatan'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endif; ?>
                        </select>
                    </label>
                    <label class="akun-field">
                        <span>Kode pos</span>
                        <input type="text" name="kode_pos" value="<?php echo htmlspecialchars($profil['kode_pos'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="6" inputmode="numeric" autocomplete="postal-code" placeholder="60123">
                    </label>
                </div>

                <label class="akun-field akun-field--penuh">
                    <span>Alamat detail (jalan, nomor rumah, RT/RW, patokan)</span>
                    <textarea name="alamat_detail" rows="3" required maxlength="500" autocomplete="street-address"><?php echo htmlspecialchars($profil['alamat_detail'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </label>

                <div class="akun-field akun-field--penuh peta-alamat" data-peta-wrap>
                    <div class="peta-alamat__judul-baris">
                        <span>Titik lokasi di peta <small>(opsional, untuk kurir)</small></span>
                        <button type="button" class="peta-alamat__tombol" data-peta-lokasi-saya>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 11a3 3 0 100-6 3 3 0 000 6z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 11a7.5 7.5 0 11-15 0c0-4.97 7.5-13 7.5-13s7.5 8.03 7.5 13z"/>
                            </svg>
                            Lokasi saya
                        </button>
                    </div>
                    <div class="peta-alamat__kanvas" id="peta-alamat" role="application" aria-label="Peta untuk memilih titik lokasi"></div>
                    <p class="peta-alamat__info" data-peta-info>
                        <?php if (trim($profil['lat']) !== '' && trim($profil['lng']) !== ''): ?>
                            Titik tersimpan: <strong data-peta-koordinat><?php echo htmlspecialchars($profil['lat'] . ', ' . $profil['lng'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php else: ?>
                            Klik di peta atau tekan <em>Lokasi saya</em> untuk menentukan titik. Boleh dikosongi.
                        <?php endif; ?>
                    </p>
                    <input type="hidden" name="lat" data-peta-lat value="<?php echo htmlspecialchars($profil['lat'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="lng" data-peta-lng value="<?php echo htmlspecialchars($profil['lng'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="akun-form-aksi">
                    <button type="submit" class="akun-tombol-simpan">Simpan profil pengiriman</button>
                </div>
            </form>
        </section>
    </div>

    <p class="akun-kembali">
        <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">&larr; Kembali ke beranda</a>
    </p>
</main>

<script src="<?php echo htmlspecialchars(aplikasi_url_aset('assets/js/cascading-alamat.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
<script src="<?php echo htmlspecialchars(aplikasi_url_aset('assets/js/peta-alamat.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>