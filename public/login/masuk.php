<?php
require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/supabase_auth.php';
require_once __DIR__ . '/../../includes/database.php';

// URL lama ?daftar=cek_email → alihkan ke URL bersih (pesan lewat flash session)
if (isset($_GET['daftar']) && $_GET['daftar'] === 'cek_email') {
    $_SESSION['flash_daftar_cek_email'] = true;
    header('Location: ' . aplikasi_url('login/masuk.php'));
    exit;
}

$pesan_kesalahan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Supabase Auth: login = email + sandi. Input boleh email atau nama pengguna (email diambil dari tabel users).
    $identifier = trim($_POST['email'] ?? $_POST['nama_pengguna'] ?? '');
    $kata_sandi = $_POST['kata_sandi'] ?? '';
    $email_masuk = '';

    if ($identifier === '' || $kata_sandi === '') {
        $pesan_kesalahan = 'Email dan kata sandi wajib diisi.';
    } elseif (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $email_masuk = $identifier;
    } else {
        try {
            $pdo_id = koneksi_database();
            $stmt_id = $pdo_id->prepare('SELECT email FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
            $stmt_id->execute(['u' => $identifier]);
            $baris_id = $stmt_id->fetch();
            if ($baris_id) {
                $em_db = trim((string) ($baris_id['email'] ?? ''));
                if ($em_db !== '' && filter_var($em_db, FILTER_VALIDATE_EMAIL)) {
                    $email_masuk = $em_db;
                } else {
                    $pesan_kesalahan = 'Akun ini belum punya email di database. Isi kolom email, buat akun dengan email yang sama di panel Authentication, lalu coba lagi.';
                }
            } else {
                $pesan_kesalahan = 'Masukkan alamat email yang valid, atau nama pengguna yang sudah terdaftar.';
            }
        } catch (Throwable $e) {
            $pesan_kesalahan = 'Tidak dapat memverifikasi nama pengguna. Coba masuk memakai alamat email.';
        }
    }

    if ($pesan_kesalahan === '' && $email_masuk !== '') {
        // Panggilan login ke Supabase: /auth/v1/token?grant_type=password
        $hasil = supabase_auth_masuk($email_masuk, $kata_sandi);

        if (!$hasil['ok']) {
            $pesan_kesalahan = $hasil['pesan'] ?? 'Email atau kata sandi salah.';
        } else {
            $badan = $hasil['data'] ?? [];
            $access_token = $badan['access_token'] ?? '';
            $refresh_token = $badan['refresh_token'] ?? '';
            $user = is_array($badan['user'] ?? null) ? $badan['user'] : [];

            $email = (string) ($user['email'] ?? $email_masuk);
            $meta = is_array($user['user_metadata'] ?? null) ? $user['user_metadata'] : [];
            $nama_tampil = (string) ($meta['username'] ?? $meta['name'] ?? preg_replace('/@.*$/', '', $email));

            // Sinkron dulu ke public.users (insert bila baru), baru ambil id — supaya id_pengguna tidak tetap 0.
            try {
                $pdo_sinkron = koneksi_database();
                users_sinkron_dari_supabase($pdo_sinkron, $email, $nama_tampil);
            } catch (Throwable $e) {
                // Tanpa baris di DB tetap bisa masuk dengan data dari Supabase Auth
            }

            $id_pengguna = 0;
            $peran = 'pembeli';
            try {
                $pdo = koneksi_database();
                $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
                $stmt->execute(['email' => $email]);
                $baris = $stmt->fetch();
                if ($baris) {
                    $id_pengguna = (int) $baris['id'];
                    $nama_tampil = (string) $baris['username'];
                    $peran = (string) $baris['role'];
                }
            } catch (Throwable $e) {
                // Tanpa baris di DB tetap bisa masuk dengan data dari Supabase Auth
            }

            $ingat_saya = !empty($_POST['ingat_saya']);
            $data_sesi = [
                'id_pengguna' => $id_pengguna,
                'nama_pengguna' => $nama_tampil,
                'peran' => $peran,
                'email_pengguna' => $email,
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
            ];

            sesi_setelah_login_supabase($data_sesi, $ingat_saya);

            if ($peran === 'admin') {
                header('Location: ' . aplikasi_url('admin/beranda_admin.php'));
            } else {
                header('Location: ' . aplikasi_url('pembeli/beranda_pembeli.php'));
            }
            exit;
        }
    }
}

$kelas_error = 'pesan-error' . ($pesan_kesalahan !== '' ? ' pesan-error--goyang' : '');
$pesan_reset_berhasil = isset($_GET['reset']) && $_GET['reset'] === 'berhasil';
$pesan_daftar_cek_email = !empty($_SESSION['flash_daftar_cek_email']);
if ($pesan_daftar_cek_email) {
    unset($_SESSION['flash_daftar_cek_email']);
}
$pesan_konfirmasi_gagal = isset($_GET['konfirmasi']) && $_GET['konfirmasi'] === 'gagal';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Masuk - EA SENIKERS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="prefetch" href="daftar.php" as="document">
    <?php if (!sudah_masuk()): ?>
    <script>
    (function () {
        var h = window.location.hash || '';
        var s = window.location.search || '';
        if (h.indexOf('access_token=') !== -1 || h.indexOf('error=') !== -1
            || s.indexOf('access_token=') !== -1 || s.indexOf('code=') !== -1 || s.indexOf('error=') !== -1) {
            window.location.replace(<?php echo json_encode(aplikasi_url('login/konfirmasi_email.php'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?> + s + h);
        }
    })();
    </script>
    <?php endif; ?>
</head>
<body class="halaman-masuk">

    <div class="bungkus-halaman-login">
        <?php include __DIR__ . '/../../includes/deskripsi_merek_login.php'; ?>

        <main class="kartu-masuk" aria-labelledby="judul-masuk">
        <header class="merek">
            <h1 id="judul-masuk" class="merek__nama">Masuk</h1>
            <p class="merek__tagline">Masuk dengan email atau nama pengguna dan kata sandi Anda</p>
        </header>

        <?php if ($pesan_reset_berhasil): ?>
            <p class="pesan-sukses" role="status">Kata sandi diperbarui. Silakan masuk.</p>
        <?php endif; ?>
        <?php if (!empty($pesan_daftar_cek_email)): ?>
            <p class="pesan-sukses" role="status">Hampir selesai. Buka email Anda, klik tautan konfirmasi, lalu kembali ke sini untuk masuk. Periksa folder spam bila perlu.</p>
        <?php endif; ?>
        <?php if (!empty($pesan_konfirmasi_gagal)): ?>
            <p class="<?php echo htmlspecialchars($kelas_error); ?>" role="alert">Tautan tidak berlaku atau sudah kedaluwarsa. Daftar ulang atau gunakan Lupa kata sandi.</p>
        <?php endif; ?>
        <?php if ($pesan_kesalahan !== ''): ?>
            <p class="<?php echo htmlspecialchars($kelas_error); ?>" role="alert"><?php echo htmlspecialchars($pesan_kesalahan); ?></p>
        <?php endif; ?>

        <form class="form-masuk" method="post" action="" novalidate>
            <div class="grup-isian">
                <label for="email_akun">Email atau nama pengguna</label>
                <div class="bungkus-isian">
                    <svg class="ikon-input" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <input id="email_akun" class="isian-input" type="text" name="email" required autocomplete="username" spellcheck="false" placeholder="masukkan email atau nama pengguna" value="<?php echo htmlspecialchars($_POST['email'] ?? $_POST['nama_pengguna'] ?? ''); ?>">
                </div>
            </div>
            <div class="grup-isian">
                <label for="kata_sandi">Kata sandi</label>
                <div class="bungkus-isian bungkus-kata-sandi">
                    <svg class="ikon-input" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <input id="kata_sandi" class="isian-input" type="password" name="kata_sandi" required autocomplete="current-password" placeholder="masukkan kata sandi">
                    <button type="button" class="tombol-lihat-sandi" id="tombol-lihat-sandi" aria-pressed="false" aria-label="Tampilkan kata sandi">
                        <span class="ikon-mata-ruang" aria-hidden="true">
                            <!-- Satu ikon aktif: mata = sandi tersembunyi, bisa diklik untuk melihat -->
                            <svg class="ikon-mata-ikon ikon-mata-lihat" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" focusable="false">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <!-- Mata coret = sandi terlihat, bisa diklik untuk menyembunyikan -->
                            <svg class="ikon-mata-ikon ikon-mata-sembunyikan ikon-mata--nonaktif" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" focusable="false">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </span>
                    </button>
                </div>
                <p class="baris-lupa-sandi">
                    <a class="tautan-lupa-sandi" href="lupa_sandi.php">Lupa kata sandi?</a>
                </p>
            </div>
            <div class="grup-ingat-saya">
                <label class="label-ingat-saya" title="Tetap login setelah menutup browser. Gunakan Keluar untuk mengakhiri sesi.">
                    <input class="kotak-ingat-saya" type="checkbox" name="ingat_saya" value="1"<?php echo !empty($_POST['ingat_saya']) ? ' checked' : ''; ?>>
                    <span>Ingat saya</span>
                </label>
            </div>
            <button class="tombol-masuk" type="submit">Masuk</button>
        </form>

        <footer class="kaki-halaman kaki-halaman--masuk-daftar">
            <p class="kaki-halaman__satu-baris">
                <span class="kaki-halaman__teks-abu">Belum punya akun?</span>
                <a class="kaki-halaman__tautan-emas" href="daftar.php">Daftar sekarang</a>
            </p>
        </footer>
        </main>
    </div>

    <script src="../assets/js/toggle-sandi.js" defer></script>
</body>
</html>
