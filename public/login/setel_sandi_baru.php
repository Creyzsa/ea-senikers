<?php
/**
 * Form atur kata sandi baru setelah klik tautan reset di email (token di sesi dari proses_reset_email.php).
 */
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/auth_db/supabase_auth.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

/** Maksimal waktu mengisi form setelah tautan dibuka (detik). */
const EASENIKERS_RESET_FORM_TTL = 1800;

function ambil_sesi_reset_sandi(): ?array
{
    if (empty($_SESSION[EASENIKERS_SESI_RESET_SANDI]) || !is_array($_SESSION[EASENIKERS_SESI_RESET_SANDI])) {
        return null;
    }
    $d = $_SESSION[EASENIKERS_SESI_RESET_SANDI];
    if (empty($d['access_token']) || !is_string($d['access_token'])) {
        return null;
    }
    if (time() - (int) ($d['ts'] ?? 0) > EASENIKERS_RESET_FORM_TTL) {
        unset($_SESSION[EASENIKERS_SESI_RESET_SANDI]);
        return null;
    }
    return $d;
}

$pesan_kesalahan = '';
$sesi_reset = ambil_sesi_reset_sandi();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sesi_reset !== null) {
    $kata_sandi = $_POST['kata_sandi'] ?? '';
    $ulang = $_POST['ulang_kata_sandi'] ?? '';

    if (strlen($kata_sandi) < 8) {
        $pesan_kesalahan = 'Kata sandi minimal 8 karakter.';
    } elseif ($kata_sandi !== $ulang) {
        $pesan_kesalahan = 'Ulang kata sandi tidak sama.';
    } else {
        $hasil = supabase_auth_perbarui_kata_sandi($sesi_reset['access_token'], $kata_sandi);
        if ($hasil['ok']) {
            unset($_SESSION[EASENIKERS_SESI_RESET_SANDI]);
            sesi_hancurkan_total();
            header('Location: ' . aplikasi_url('login/masuk.php') . '?reset=berhasil');
            exit;
        }
        $pesan_kesalahan = $hasil['pesan'] ?? 'Tidak dapat memperbarui kata sandi. Coba lagi atau minta tautan baru.';
    }
    $sesi_reset = ambil_sesi_reset_sandi();
}

$tampilkan_form = $sesi_reset !== null;
if (!$tampilkan_form && $_SERVER['REQUEST_METHOD'] === 'GET' && $pesan_kesalahan === '') {
    $pesan_kesalahan = 'Tautan reset tidak berlaku atau sudah dipakai. Minta tautan baru dari halaman Lupa kata sandi, lalu buka link di email sekali saja.';
}
$email_tampil = $sesi_reset ? htmlspecialchars((string) ($sesi_reset['email'] ?? ''), ENT_QUOTES, 'UTF-8') : '';
$kelas_error = 'pesan-error' . ($pesan_kesalahan !== '' ? ' pesan-error--goyang' : '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Setel kata sandi baru - EA SENIKERS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body class="halaman-daftar">

    <div class="bungkus-halaman-login">
        <?php include __DIR__ . '/../../includes/konfigurasi/deskripsi_merek_login.php'; ?>

        <main class="kartu-masuk" aria-labelledby="judul-setel-sandi">
        <header class="merek">
            <h1 id="judul-setel-sandi" class="merek__nama">Kata sandi baru</h1>
            <p class="merek__tagline">Buat kata sandi baru untuk akun Anda<?php echo $email_tampil !== '' ? ' (' . $email_tampil . ')' : ''; ?></p>
        </header>

        <?php if (!$tampilkan_form): ?>
            <p class="<?php echo htmlspecialchars($kelas_error); ?>" role="alert"><?php echo htmlspecialchars($pesan_kesalahan !== '' ? $pesan_kesalahan : 'Sesi reset tidak berlaku. Minta tautan baru dari halaman lupa sandi.'); ?></p>
            <footer class="kaki-halaman">
                <a class="tautan-daftar" href="lupa_sandi.php">Lupa kata sandi</a>
            </footer>
        <?php else: ?>
            <?php if ($pesan_kesalahan !== ''): ?>
                <p class="<?php echo htmlspecialchars($kelas_error); ?>" role="alert"><?php echo htmlspecialchars($pesan_kesalahan); ?></p>
            <?php endif; ?>

            <form class="form-masuk" method="post" action="" novalidate>
                <div class="grup-isian">
                    <label for="kata_sandi">Kata sandi baru</label>
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
                <button class="tombol-masuk" type="submit">Simpan kata sandi</button>
            </form>
            <footer class="kaki-halaman">
                <a class="tautan-daftar" href="masuk.php">Kembali ke masuk</a>
            </footer>
            <script src="../assets/js/toggle-sandi.js" defer></script>
        <?php endif; ?>
        </main>
    </div>

</body>
</html>
