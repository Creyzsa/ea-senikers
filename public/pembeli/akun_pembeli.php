<?php
require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/profil_pembeli_repositori.php';

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
$kontak_toko = require __DIR__ . '/../../includes/kontak_toko.php';

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
        ];
        $errors = profil_pembeli_validasi($input);
        if ($errors === []) {
            if (profil_pembeli_simpan($id_pengguna, $input)) {
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
                        <input type="text" name="provinsi" value="<?php echo htmlspecialchars($profil['provinsi'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="120" autocomplete="address-level1">
                    </label>
                    <label class="akun-field">
                        <span>Kota / kabupaten</span>
                        <input type="text" name="kota" value="<?php echo htmlspecialchars($profil['kota'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="120" autocomplete="address-level2">
                    </label>
                    <label class="akun-field">
                        <span>Kecamatan</span>
                        <input type="text" name="kecamatan" value="<?php echo htmlspecialchars($profil['kecamatan'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="120" autocomplete="address-level3">
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

                <div class="akun-form-aksi">
                    <button type="submit" class="tombol-page-utama">Simpan profil pengiriman</button>
                </div>
            </form>
        </section>

        <section class="akun-section" aria-labelledby="judul-akses">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Akses cepat</p>
                    <h2 id="judul-akses">Lanjutkan aktivitas belanja</h2>
                </div>
            </div>
            <div class="akun-action-grid">
                <a href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Katalog produk</strong>
                    <span>Cari sepatu baru atau preloved.</span>
                </a>
                <a href="<?php echo htmlspecialchars($u_keranjang, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Keranjang</strong>
                    <span>Lihat produk yang sudah disimpan.</span>
                </a>
                <a href="<?php echo htmlspecialchars($u_pesanan, ENT_QUOTES, 'UTF-8'); ?>">
                    <strong>Pesanan saya</strong>
                    <span>Pantau pembayaran dan pengiriman.</span>
                </a>
                <?php if ($wa_utama !== ''): ?>
                    <a href="<?php echo htmlspecialchars($wa_utama, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <strong>Layanan pelanggan</strong>
                        <span>Tanyakan ukuran, kondisi, atau ketersediaan.</span>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <p class="akun-kembali">
            <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">&larr; Kembali ke beranda</a>
        </p>
    </main>

</body>
</html>
