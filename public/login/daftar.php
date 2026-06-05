<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/auth_db/supabase_auth.php';
require_once __DIR__ . '/../../includes/auth_db/database.php';

/** Panjang string — pakai strlen jika mbstring tidak aktif (Laragon biasanya ada mbstring). */
function panjang_teks(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

function catat_galat_daftar(string $pesan): void
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($dir . '/daftar.log', date('c') . ' ' . $pesan . "\n", FILE_APPEND | LOCK_EX);
}

if (sudah_masuk()) {
    if (ambil_peran() === 'admin') {
        header('Location: ' . aplikasi_url('admin/beranda_admin.php'));
    } else {
        header('Location: ' . aplikasi_url('')); // clean root homepage after register
    }
    exit;
}

$pesan_kesalahan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pengguna = trim($_POST['nama_pengguna'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $kata_sandi = $_POST['kata_sandi'] ?? '';
    $ulang_kata_sandi = $_POST['ulang_kata_sandi'] ?? '';

    if ($nama_pengguna === '' || $email === '' || $kata_sandi === '' || $ulang_kata_sandi === '') {
        $pesan_kesalahan = 'Semua kolom wajib diisi.';
    } elseif (panjang_teks($nama_pengguna) < 3 || panjang_teks($nama_pengguna) > 50) {
        $pesan_kesalahan = 'Nama pengguna 3–50 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nama_pengguna)) {
        $pesan_kesalahan = 'Nama pengguna hanya huruf, angka, dan garis bawah (_).';
    } elseif (panjang_teks($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan_kesalahan = 'Format email tidak valid.';
    } elseif (panjang_teks($kata_sandi) < 8) {
        $pesan_kesalahan = 'Kata sandi minimal 8 karakter.';
    } elseif (strcasecmp($nama_pengguna, $kata_sandi) === 0) {
        $pesan_kesalahan = 'Kata sandi tidak boleh sama dengan nama pengguna.';
    } elseif (strcasecmp($email, $kata_sandi) === 0) {
        $pesan_kesalahan = 'Kata sandi tidak boleh sama dengan email.';
    } elseif ($kata_sandi !== $ulang_kata_sandi) {
        $pesan_kesalahan = 'Ulang kata sandi tidak sama.';
    } else {
        // Daftar lewat Supabase: POST /auth/v1/signup (metadata: nama pengguna)
        $hasil = supabase_auth_daftar($email, $kata_sandi, [
            'username' => $nama_pengguna,
        ]);

        if (!$hasil['ok']) {
            $pesan_kesalahan = $hasil['pesan'] ?? 'Pendaftaran tidak dapat diproses. Coba lagi.';
        } else {
            $badan = $hasil['data'] ?? [];
            $user = is_array($badan['user'] ?? null) ? $badan['user'] : [];
            // Supabase menyimpan akun di auth.users — sinkronkan ke tabel public.users untuk data toko (keranjang, dll.)
            $email_untuk_db = strtolower(trim((string) ($user['email'] ?? $email)));
            try {
                $pdo_sinkron = koneksi_database();
                users_sinkron_dari_supabase($pdo_sinkron, $email_untuk_db, $nama_pengguna);
            } catch (Throwable $e) {
                catat_galat_daftar('[sinkron users] ' . $e->getMessage());
            }

            $session_b = is_array($badan['session'] ?? null) ? $badan['session'] : [];
            $access_token = $badan['access_token'] ?? $session_b['access_token'] ?? '';
            $refresh_token = $badan['refresh_token'] ?? $session_b['refresh_token'] ?? '';

            // Jika konfirmasi email dimatikan di Supabase, biasanya langsung ada access_token → auto login
            if ($access_token !== '') {
                $email_user = (string) ($user['email'] ?? $email);
                $id_pengguna = 0;
                $peran = 'pembeli';
                try {
                    $pdo = koneksi_database();
                    $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
                    $stmt->execute(['email' => $email_user]);
                    $baris = $stmt->fetch();
                    if ($baris) {
                        $id_pengguna = (int) $baris['id'];
                        $peran = (string) $baris['role'];
                    }
                } catch (Throwable $e) {
                    // lanjut sebagai pembeli baru tanpa baris di tabel lokal
                }

                $ingat_saya = !empty($_POST['ingat_saya']);
                $data_sesi = [
                    'id_pengguna' => $id_pengguna,
                    'nama_pengguna' => $nama_pengguna,
                    'peran' => $peran,
                    'email_pengguna' => $email_user,
                    'access_token' => $access_token,
                    'refresh_token' => $refresh_token,
                ];
                sesi_setelah_login_supabase($data_sesi, $ingat_saya);
                header('Location: ' . aplikasi_url('')); // clean root homepage after register
                exit;
            }

            // Konfirmasi email wajib: tidak ada sesi — flash pesan lalu URL bersih (tanpa ?daftar= di address bar)
            $_SESSION['flash_daftar_cek_email'] = true;
            header('Location: ' . aplikasi_url('login/masuk.php'));
            exit;
        }
    }
}

$kelas_error = 'pesan-error' . ($pesan_kesalahan !== '' ? ' pesan-error--goyang' : '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar - EA SENIKERS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="prefetch" href="masuk.php" as="document">
</head>
<body class="halaman-daftar">

    <div class="bungkus-halaman-login">
        <?php include __DIR__ . '/../../includes/konfigurasi/deskripsi_merek_login.php'; ?>

        <main class="kartu-masuk" aria-labelledby="judul-daftar">
        <header class="merek">
            <h1 id="judul-daftar" class="merek__nama">Daftar</h1>
            <p class="merek__tagline">Buat akun untuk mulai belanja</p>
        </header>

        <?php if (defined('URL_APLIKASI') && is_local_dev_url(URL_APLIKASI)): ?>
        <div style="background:#fef3c7; border:1px solid #f59e0b; color:#92400e; padding:0.6rem 0.8rem; font-size:0.85rem; border-radius:6px; margin-bottom:1rem; text-align:left;">
            <strong>⚠️ Test di localhost:</strong> Link konfirmasi di email akan pakai URL lokal.<br>
            <strong>Tidak harus pakai ngrok</strong> kalau test di satu mesin yang sama: set URL_APLIKASI ke <code>http://localhost:8080/EASENIKERS/public</code>, tambahkan ke Supabase Redirect URLs, buka daftar via localhost, dan buka email di browser Gmail di komputer yang sama.<br>
            Kalau butuh dari HP/jaringan lain → baru pakai ngrok.
        </div>
        <?php endif; ?>

        <?php if ($pesan_kesalahan !== ''): ?>
            <p class="<?php echo htmlspecialchars($kelas_error); ?>" role="alert"><?php echo htmlspecialchars($pesan_kesalahan); ?></p>
        <?php endif; ?>

        <form class="form-masuk" method="post" action="" novalidate>
            <div class="grup-isian">
                <label for="nama_pengguna">Nama pengguna</label>
                <div class="bungkus-isian">
                    <svg class="ikon-input" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <input id="nama_pengguna" class="isian-input" type="text" name="nama_pengguna" required maxlength="50" autocomplete="username" placeholder="masukkan nama pengguna" value="<?php echo htmlspecialchars($_POST['nama_pengguna'] ?? ''); ?>">
                </div>
            </div>
            <div class="grup-isian">
                <label for="email">Email</label>
                <div class="bungkus-isian">
                    <svg class="ikon-input" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <input id="email" class="isian-input" type="email" name="email" required maxlength="255" autocomplete="email" placeholder="Masukkan email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>
            <div class="grup-isian">
                <label for="kata_sandi">Kata sandi</label>
                <div class="bungkus-isian bungkus-kata-sandi">
                    <svg class="ikon-input" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <input id="kata_sandi" class="isian-input" type="password" name="kata_sandi" required minlength="8" autocomplete="new-password" placeholder="minimal 8 karakter">
                    <button type="button" class="tombol-lihat-sandi" aria-pressed="false" aria-label="Tampilkan kata sandi">
                        <span class="ikon-mata-ruang" aria-hidden="true">
                            <svg class="ikon-mata-ikon ikon-mata-lihat" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" focusable="false">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg class="ikon-mata-ikon ikon-mata-sembunyikan ikon-mata--nonaktif" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" focusable="false">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </div>
            <div class="grup-isian">
                <label for="ulang_kata_sandi">Ulang kata sandi</label>
                <div class="bungkus-isian bungkus-kata-sandi">
                    <svg class="ikon-input" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <input id="ulang_kata_sandi" class="isian-input" type="password" name="ulang_kata_sandi" required minlength="8" autocomplete="new-password" placeholder="ulang kata sandi">
                    <button type="button" class="tombol-lihat-sandi" aria-pressed="false" aria-label="Tampilkan ulang kata sandi" data-label-tampil="Tampilkan ulang kata sandi" data-label-sembunyi="Sembunyikan ulang kata sandi">
                        <span class="ikon-mata-ruang" aria-hidden="true">
                            <svg class="ikon-mata-ikon ikon-mata-lihat" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" focusable="false">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg class="ikon-mata-ikon ikon-mata-sembunyikan ikon-mata--nonaktif" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" focusable="false">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </div>
            <div class="grup-ingat-saya">
                <label class="label-ingat-saya" title="Tetap login setelah menutup browser. Gunakan Keluar untuk mengakhiri sesi.">
                    <input class="kotak-ingat-saya" type="checkbox" name="ingat_saya" value="1"<?php echo !empty($_POST['ingat_saya']) ? ' checked' : ''; ?>>
                    <span>Ingat saya</span>
                </label>
            </div>
            <button class="tombol-masuk" type="submit">Daftar</button>
        </form>

        <footer class="kaki-halaman">
            <span class="teks-kaki">Sudah punya akun?</span>
            <a class="tautan-daftar" href="masuk.php">Masuk</a>
        </footer>
        </main>
    </div>

    <script src="../assets/js/toggle-sandi.js" defer></script>
</body>
</html>
