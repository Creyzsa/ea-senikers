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
            // Pesan generik agar tidak membocorkan apakah email terdaftar
            $redirect_for_email = (defined('URL_APLIKASI') && URL_APLIKASI !== '') ? rtrim(URL_APLIKASI, '/') . '/login/konfirmasi_email.php' : 'NOT SET';
            $pesan_sukses = 'Jika alamat ini terdaftar, kami mengirim tautan reset ke email Anda. Periksa kotak masuk atau spam. <strong>Request tautan baru setelah perubahan config/template!</strong>';
            $pesan_sukses .= ' <span style="font-size:0.8em;color:#b45309;">(Redirect yang dikirim: ' . htmlspecialchars($redirect_for_email) . ' — minta tautan BARU dari sini setelah update. Email lama masih pakai 192.)</span>';
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

        <?php
        $configured_redirect = (defined('URL_APLIKASI') && URL_APLIKASI !== '') ? rtrim(URL_APLIKASI, '/') . '/login/konfirmasi_email.php' : 'NOT SET';
        // Compute current base from request
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        $current_base = $scheme . '://' . $host . $script_dir;
        $mismatch = (defined('URL_APLIKASI') && URL_APLIKASI !== '' && strpos($current_base, rtrim(URL_APLIKASI, '/')) === false);
        ?>
        <div style="background:#fff3cd; border:2px solid #ffc107; color:#856404; padding:0.75rem 1rem; font-size:0.9rem; border-radius:6px; margin-bottom:1rem; text-align:left;">
            <strong>⚠️ PENTING untuk email link:</strong> Redirect yang akan dikirim ke Supabase sekarang: <code><?= htmlspecialchars($configured_redirect) ?></code><br>
            <strong>Harus match dengan URL yang kamu buka di browser + yang didaftarkan di Supabase Redirect URLs.</strong><br>
            <?php if ($mismatch): ?>
            <strong style="color:#721c24; font-size:1.05em;">⚠️ MISMATCH TERDETEKSI! Kamu membuka via <code><?= htmlspecialchars($current_base) ?></code> tapi config pakai <code><?= htmlspecialchars(URL_APLIKASI) ?></code>.<br>
            <strong>Buka ulang halaman ini di <a href="<?= htmlspecialchars(URL_APLIKASI . '/login/lupa_sandi.php') ?>">URL config yang benar</a>, lalu submit form lagi untuk dapat email dengan link yang benar!</strong></strong><br>
            <?php endif; ?>
            Jika salah, buka halaman ini lewat URL yang sesuai dengan config.php, lalu submit lagi untuk minta tautan baru.
        </div>

        <?php if (defined('URL_APLIKASI') && is_local_dev_url(URL_APLIKASI)): ?>
        <div style="background:#fef3c7; border:1px solid #f59e0b; color:#92400e; padding:0.6rem 0.8rem; font-size:0.85rem; border-radius:6px; margin-bottom:1rem; text-align:left;">
            <strong>⚠️ Untuk reset password di localhost:</strong><br>
            - URL_APLIKASI harus persis <code>http://localhost:8080/EASENIKERS/public</code><br>
            - Buka halaman ini lewat <strong>URL_APLIKASI + /login/lupa_sandi.php</strong><br>
            - Template "Reset password" di Supabase WAJIB pakai token_hash + {{ .RedirectTo }}<br>
            - <strong>Minta tautan YANG BARU</strong> setelah ganti config/template. Email lama di inbox tetap pakai URL 192 lama!
        </div>
        <?php endif; ?>

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
            <a class="tautan-daftar" href="masuk.php">← Kembali ke masuk</a>
        </footer>
        </main>
    </div>

</body>
</html>
