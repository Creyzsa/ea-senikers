<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/auth_db/supabase_auth.php';

if (sudah_masuk()) {
    header('Location: ' . aplikasi_url('index.php'));
    exit;
}

$pesan_sukses = '';
$pesan_kesalahan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan_kesalahan = 'Masukkan alamat email yang valid.';
    } else {
        // Lupa sandi lewat Supabase: POST /auth/v1/recover (email reset dari Supabase Auth)
        $hasil = supabase_auth_lupa_password($email);

        if ($hasil['ok']) {
            $pesan_sukses = 'Jika alamat ini terdaftar, kami mengirim tautan reset ke email Anda. Periksa kotak masuk atau spam.';
        } else {
            $pesan_kesalahan = $hasil['pesan'] ?? 'Permintaan gagal. Coba lagi.';
        }
    }
}

$kelas_error = 'pesan-error' . ($pesan_kesalahan !== '' ? ' pesan-error--goyang' : '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa kata sandi - EA SENIKERS</title>
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body class="halaman-masuk">

    <div class="bungkus-halaman-login">
        <?php include __DIR__ . '/../../includes/konfigurasi/deskripsi_merek_login.php'; ?>

        <main class="kartu-masuk" aria-labelledby="judul-lupa">
        <header class="merek">
            <h1 id="judul-lupa" class="merek__nama">Lupa kata sandi</h1>
            <p class="merek__tagline">Kami kirim tautan reset ke email Anda</p>
        </header>

        <?php if ($pesan_sukses !== ''): ?>
            <p class="pesan-sukses" role="status"><?php echo htmlspecialchars($pesan_sukses); ?></p>
        <?php endif; ?>
        <?php if ($pesan_kesalahan !== ''): ?>
            <p class="<?php echo htmlspecialchars($kelas_error); ?>" role="alert"><?php echo htmlspecialchars($pesan_kesalahan); ?></p>
        <?php endif; ?>

        <?php if ($pesan_sukses === ''): ?>
        <form class="form-masuk" method="post" action="">
            <div class="grup-isian">
                <label for="email">Email akun</label>
                <div class="bungkus-isian">
                    <svg class="ikon-input" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <input id="email" class="isian-input" type="email" name="email" required autocomplete="email" placeholder="Masukkan email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>
            <button class="tombol-masuk" type="submit">Kirim tautan</button>
        </form>
        <?php endif; ?>

        <footer class="kaki-halaman">
            <a class="tautan-kembali" href="masuk.php">← Kembali ke masuk</a>
        </footer>
        </main>
    </div>

</body>
</html>
