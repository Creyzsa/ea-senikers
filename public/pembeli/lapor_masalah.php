<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/laporan_repositori.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$bilah_pembeli_aktif = 'akun';
$u_akun = aplikasi_url('pembeli/akun_pembeli.php');
$u_bantuan = aplikasi_url('pembeli/bantuan_pembeli.php');
$u_lapor = aplikasi_url('pembeli/lapor_masalah.php');

if (!isset($_SESSION['csrf_lapor_masalah']) || !is_string($_SESSION['csrf_lapor_masalah'])) {
    $_SESSION['csrf_lapor_masalah'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_lapor_masalah'];

$id_pengguna = ambil_id_pengguna_efektif();
$tabel_ada = laporan_cek_tabel_ada();
$kategori_label = laporan_kategori_label();

$errors = [];
$flash = $_SESSION['flash_lapor_masalah'] ?? null;
unset($_SESSION['flash_lapor_masalah']);

$form = ['kategori' => '', 'deskripsi' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $errors[] = 'Mohon muat ulang halaman, lalu kirim lagi.';
    } elseif (!$tabel_ada) {
        $errors[] = 'Fitur laporan belum aktif. Hubungi admin toko.';
    }

    $form['kategori'] = trim((string) ($_POST['kategori'] ?? ''));
    $form['deskripsi'] = trim((string) ($_POST['deskripsi'] ?? ''));

    if (!array_key_exists($form['kategori'], $kategori_label)) {
        $errors[] = 'Pilih kategori masalah terlebih dahulu.';
    }
    if ($form['deskripsi'] === '') {
        $errors[] = 'Deskripsi masalah wajib diisi.';
    } elseif (mb_strlen($form['deskripsi']) > 2000) {
        $errors[] = 'Deskripsi terlalu panjang (maksimal 2000 karakter).';
    }

    $screenshot = null;
    if ($errors === []) {
        $upload = laporan_upload_screenshot($_FILES, 'screenshot');
        if ($upload['error'] !== null) {
            $errors[] = $upload['error'];
        } else {
            $screenshot = $upload['nama_file'];
        }
    }

    if ($errors === []) {
        $id_baru = laporan_simpan($id_pengguna, $form['kategori'], $form['deskripsi'], $screenshot);
        if ($id_baru !== null) {
            $_SESSION['flash_lapor_masalah'] = [
                'jenis' => 'sukses',
                'teks' => 'Laporan #' . $id_baru . ' berhasil dikirim. Tim kami akan menindaklanjutinya.',
            ];
            header('Location: ' . $u_lapor);
            exit;
        }
        $errors[] = 'Gagal menyimpan laporan. Coba lagi sebentar.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporkan Masalah - EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="kontainer-toko bantuan-wrap" id="utama">
    <nav class="artikel-breadcrumb" aria-label="Remah roti">
        <a href="<?php echo htmlspecialchars($u_akun, ENT_QUOTES, 'UTF-8'); ?>">Akun</a>
        <span aria-hidden="true">/</span>
        <a href="<?php echo htmlspecialchars($u_bantuan, ENT_QUOTES, 'UTF-8'); ?>">Bantuan</a>
        <span aria-hidden="true">/</span>
        <span>Laporkan Masalah</span>
    </nav>

    <header class="bantuan-hero">
        <div class="bantuan-hero__teks">
            <span class="artikel-tag">Laporan</span>
            <h1 class="artikel-judul">Laporkan Masalah</h1>
            <p class="bantuan-hero__sub">Mengalami kendala seperti gagal checkout, pembayaran tidak masuk, atau alamat tidak tersimpan? Ceritakan di sini agar bisa kami bantu perbaiki.</p>
        </div>
        <span class="bantuan-hero__ikon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
        </span>
    </header>

    <?php if (!$tabel_ada): ?>
        <div class="akun-alert akun-alert--error">
            Fitur laporan masalah belum aktif (tabel database belum dibuat). Hubungi admin toko.
        </div>
    <?php endif; ?>

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

    <form class="akun-form-profil lapor-form" method="post" action="<?php echo htmlspecialchars($u_lapor, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

        <fieldset class="lapor-fieldset">
            <legend>Kategori masalah</legend>
            <div class="lapor-kategori">
                <?php foreach ($kategori_label as $key => $label): ?>
                    <label class="lapor-kategori__item">
                        <input type="radio" name="kategori" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $form['kategori'] === $key ? ' checked' : ''; ?> required>
                        <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <label class="akun-field akun-field--penuh">
            <span>Deskripsi masalah</span>
            <textarea name="deskripsi" rows="5" maxlength="2000" required placeholder="Ceritakan masalahnya sedetail mungkin: apa yang Anda lakukan, apa yang terjadi, dan kapan."><?php echo htmlspecialchars($form['deskripsi'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>

        <label class="akun-field akun-field--penuh">
            <span>Upload screenshot <small>(opsional, JPG/PNG/WEBP, maks 3MB)</small></span>
            <input type="file" name="screenshot" accept=".jpg,.jpeg,.png,.webp,image/*">
        </label>

        <div class="akun-form-aksi">
            <button type="submit" class="tombol-page-utama">Kirim laporan</button>
            <a class="tombol-page-sekunder" href="<?php echo htmlspecialchars($u_bantuan, ENT_QUOTES, 'UTF-8'); ?>">Batal</a>
        </div>
    </form>

    <p class="artikel-kembali">
        <a href="<?php echo htmlspecialchars($u_bantuan, ENT_QUOTES, 'UTF-8'); ?>">&larr; Kembali ke Bantuan</a>
    </p>
</main>

</body>
</html>
