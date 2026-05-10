<?php
require_once __DIR__ . '/../../includes/sesi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$bilah_pembeli_aktif = 'akun';
$u_beranda = aplikasi_url('pembeli/beranda_pembeli.php');

$nama = trim((string) ($_SESSION['nama_pengguna'] ?? ''));
$email = trim((string) ($_SESSION['email_pengguna'] ?? ''));
if ($nama === '') {
    $nama = '—';
}
if ($email === '') {
    $email = '—';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akun saya — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

    <main class="kontainer-toko" id="utama">
        <div class="panel-pembeli-teks">
            <h1>Akun saya</h1>
            <p><strong>Nama tampil:</strong> <?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></p>
            <p style="margin-top:0.65rem;"><strong>Email:</strong> <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></p>
            <p style="margin-top:0.65rem;font-size:0.9rem;opacity:0.85;">Nama dan email menampilkan data dari akun Anda saat ini.</p>
            <a class="tautan-balik" href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">← Kembali ke beranda</a>
        </div>
    </main>

</body>
</html>
