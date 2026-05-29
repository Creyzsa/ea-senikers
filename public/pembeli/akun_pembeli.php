<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/profil_pembeli_repositori.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$bilah_pembeli_aktif = 'akun';
$u_beranda = aplikasi_url('pembeli/beranda_pembeli.php');
$u_produk = aplikasi_url('pembeli/produk.php');
$u_pesanan = aplikasi_url('pembeli/pesanan_pembeli.php');
$u_keranjang = aplikasi_url('pembeli/keranjang_pembeli.php');
$u_keluar = aplikasi_url('login/keluar.php');
$u_akun = aplikasi_url('pembeli/akun_pembeli.php');
$u_bantuan = aplikasi_url('pembeli/bantuan_pembeli.php');
$u_lapor = aplikasi_url('pembeli/lapor_masalah.php');
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
                // Alamat profil berubah → state destinasi/kurir di checkout
                // tidak lagi sinkron. Hapus supaya saat masuk checkout lagi
                // sistem auto-pick ulang dari alamat profil terbaru.
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

$alamat_lengkap = false;
foreach (['no_hp', 'nama_penerima', 'provinsi', 'kota', 'kecamatan', 'alamat_detail'] as $__k) {
    if (trim((string) ($profil[$__k] ?? '')) === '') {
        $alamat_lengkap = false;
        break;
    }
    $alamat_lengkap = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akun saya - EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <main class="kontainer-toko halaman-akun" id="utama">
        <section class="akun-hero" aria-labelledby="judul-akun">
            <div class="akun-avatar" aria-hidden="true"><?php echo htmlspecialchars($inisial, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="akun-hero__isi">
                <p class="section-eyebrow">Akun pembeli</p>
                <h1 id="judul-akun"><?php echo htmlspecialchars($nama_tampil, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php echo htmlspecialchars($email_tampil, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <a class="tombol-page-sekunder akun-hero__keluar" href="<?php echo htmlspecialchars($u_keluar, ENT_QUOTES, 'UTF-8'); ?>">Keluar</a>
        </section>

        <section class="akun-grid" aria-label="Informasi akun">
            <article class="akun-info-card">
                <span>Nama tampil</span>
                <strong><?php echo htmlspecialchars($nama_tampil, ENT_QUOTES, 'UTF-8'); ?></strong>
                <p>Digunakan untuk sapaan dan identitas pemesanan.</p>
            </article>
            <article class="akun-info-card">
                <span>Email</span>
                <strong><?php echo htmlspecialchars($email_tampil, ENT_QUOTES, 'UTF-8'); ?></strong>
                <p>Dipakai untuk akses akun dan komunikasi transaksi.</p>
            </article>
            <article class="akun-info-card">
                <span>Profil pengiriman</span>
                <strong><?php echo $alamat_lengkap ? 'Lengkap' : 'Belum lengkap'; ?></strong>
                <p><?php echo $alamat_lengkap ? 'Alamat siap dipakai saat checkout.' : 'Isi alamat & nomor HP di bawah agar checkout cepat.'; ?></p>
            </article>
        </section>

        <section class="akun-section" aria-labelledby="judul-akses">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Menu akun</p>
                    <h2 id="judul-akses">Apa yang ingin Anda lakukan?</h2>
                </div>
            </div>
            <div class="akun-action-grid">
                <a href="<?php echo htmlspecialchars($u_pesanan, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Pesanan saya</strong>
                    <span>Pantau pembayaran dan pengiriman.</span>
                </a>
                <a href="<?php echo htmlspecialchars($u_bantuan, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Bantuan &amp; Hubungi Kami</strong>
                    <span>FAQ, WhatsApp, email, cara retur &amp; lacak pesanan.</span>
                </a>
                <a href="<?php echo htmlspecialchars($u_lapor, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Laporkan Masalah</strong>
                    <span>Kendala checkout, pembayaran, atau bug? Beri tahu kami.</span>
                </a>
                <a href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Katalog produk</strong>
                    <span>Cari sepatu baru atau preloved.</span>
                </a>
                <a href="<?php echo htmlspecialchars($u_keranjang, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Keranjang</strong>
                    <span>Lihat produk yang sudah disimpan.</span>
                </a>
                <?php if ($wa_utama !== ''): ?>
                    <a href="<?php echo htmlspecialchars($wa_utama, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <strong>Layanan pelanggan</strong>
                        <span>Tanyakan ukuran, kondisi, atau ketersediaan.</span>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <section class="akun-section" id="profil-pengiriman" aria-labelledby="judul-profil-pengiriman">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Alamat pengiriman</p>
                    <h2 id="judul-profil-pengiriman">Profil pengiriman default</h2>
                </div>
            </div>

            <?php if (is_array($flash)): ?>
                <div class="akun-alert akun-alert--<?php echo htmlspecialchars((string) ($flash['jenis'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars((string) ($flash['teks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="akun-alert akun-alert--error">
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
                        <button type="button" class="tombol-page-sekunder peta-alamat__tombol" data-peta-lokasi-saya>
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
                    <button type="submit" class="tombol-page-utama">Simpan profil pengiriman</button>
                </div>
            </form>
        </section>

        <p class="akun-kembali">
            <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">&larr; Kembali ke beranda</a>
        </p>
    </main>

    <script src="<?php echo htmlspecialchars(aplikasi_url('assets/js/cascading-alamat.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
    <script src="<?php echo htmlspecialchars(aplikasi_url('assets/js/peta-alamat.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
