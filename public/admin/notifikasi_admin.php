<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/admin_notifikasi_repositori.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

$csrf = admin_csrf_token('notifikasi');
$errors = [];
$flash = $_SESSION['flash_admin_notifikasi'] ?? null;
unset($_SESSION['flash_admin_notifikasi']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!admin_csrf_valid('notifikasi', $token)) {
        $errors[] = 'Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $aksi = (string) ($_POST['aksi'] ?? 'simpan');
        $cfg_post = admin_notifikasi_gabung_form_dengan_simpan([
            'telegram_bot_token' => (string) ($_POST['telegram_bot_token'] ?? ''),
            'telegram_chat_id' => (string) ($_POST['telegram_chat_id'] ?? ''),
            'telegram_aktif' => !empty($_POST['telegram_aktif']),
            'smtp_host' => (string) ($_POST['smtp_host'] ?? ''),
            'smtp_port' => (string) ($_POST['smtp_port'] ?? '587'),
            'smtp_user' => (string) ($_POST['smtp_user'] ?? ''),
            'smtp_pass' => (string) ($_POST['smtp_pass'] ?? ''),
            'smtp_from' => (string) ($_POST['smtp_from'] ?? ''),
            'smtp_to' => (string) ($_POST['smtp_to'] ?? ''),
            'email_aktif' => !empty($_POST['email_aktif']),
            'notif_browser_aktif' => !empty($_POST['notif_browser_aktif']),
        ]);

        try {
            if ($aksi === 'ambil_chat_id') {
                $hasil = notifikasi_telegram_ambil_chat_id($cfg_post['telegram_bot_token']);
                if ($hasil['ok']) {
                    $cfg_post['telegram_chat_id'] = (string) $hasil['chat_id'];
                    admin_notifikasi_simpan_pengaturan($cfg_post);
                    $_SESSION['flash_admin_notifikasi'] = [
                        'jenis' => 'sukses',
                        'teks' => (string) $hasil['pesan'],
                    ];
                } else {
                    $errors[] = (string) $hasil['pesan'];
                }
            } elseif ($aksi === 'tes_telegram') {
                $hasil = admin_notifikasi_tes_telegram($cfg_post);
                if ($hasil['ok']) {
                    $_SESSION['flash_admin_notifikasi'] = ['jenis' => 'sukses', 'teks' => (string) $hasil['pesan']];
                } else {
                    $errors[] = (string) $hasil['pesan'];
                }
            } elseif ($aksi === 'tes_email') {
                $hasil = admin_notifikasi_tes_email($cfg_post);
                if ($hasil['ok']) {
                    $_SESSION['flash_admin_notifikasi'] = ['jenis' => 'sukses', 'teks' => (string) $hasil['pesan']];
                } else {
                    $errors[] = (string) $hasil['pesan'];
                }
            } else {
                admin_notifikasi_simpan_pengaturan($cfg_post);
                $_SESSION['flash_admin_notifikasi'] = [
                    'jenis' => 'sukses',
                    'teks' => 'Pengaturan notifikasi berhasil disimpan.',
                ];
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        if ($errors === [] && ($aksi === 'simpan' || $aksi === 'ambil_chat_id')) {
            header('Location: ' . aplikasi_url('admin/notifikasi_admin.php'));
            exit;
        }
    }
}

$cfg = admin_notifikasi_muat_pengaturan();
$nama = htmlspecialchars($_SESSION['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8');
$urlKeluar = htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8');
$poll_url = htmlspecialchars(aplikasi_url('api/admin_notifikasi_poll.php'), ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifikasi — EA SENIKERS Admin</title>
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>
    <link rel="stylesheet" href="../assets/css/beranda-admin.css">
</head>
<body class="halaman-admin" data-admin-notif-poll="<?php echo $poll_url; ?>">

<div class="admin-cangkang">
    <?php $admin_nav_aktif = 'notifikasi'; include __DIR__ . '/../../includes/komponen/admin_sisi_nav.php'; ?>

    <div class="admin-utama">
        <header class="admin-bilah">
            <div class="admin-pengguna">
                <span class="admin-pengguna__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </span>
                <span class="admin-pengguna__nama"><?php echo $nama; ?></span>
            </div>
            <a class="admin-tombol-keluar" href="<?php echo $urlKeluar; ?>">Keluar</a>
        </header>

        <main class="admin-isi">
            <h1 class="admin-judul-besar">Notifikasi admin</h1>
            <p class="admin-salam">Atur Telegram, email SMTP, dan notifikasi browser (getar &amp; bunyi) saat pembayaran masuk.</p>

            <?php if (is_array($flash)): ?>
                <div class="admin-alert admin-alert--<?php echo htmlspecialchars((string) (($flash['jenis'] ?? '') === 'error' ? 'error' : 'sukses'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars((string) ($flash['teks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="admin-alert admin-alert--error">
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?php echo htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="admin-form-stack">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="aksi" value="simpan" id="notifikasi-aksi">

                <section class="admin-kartu" aria-labelledby="judul-notif-browser">
                    <header class="admin-kartu__header">
                        <h2 id="judul-notif-browser">Notifikasi browser</h2>
                    </header>
                    <p class="admin-meta">Saat panel admin terbuka, sistem memeriksa pembayaran baru setiap 12 detik. Jika ada pesanan berstatus <strong>paid</strong>, perangkat akan bergetar dan berbunyi.</p>
                    <label class="admin-check">
                        <input type="checkbox" name="notif_browser_aktif" value="1"<?php echo !empty($cfg['notif_browser_aktif']) ? ' checked' : ''; ?>>
                        Aktifkan getar &amp; bunyi di panel admin
                    </label>
                </section>

                <section class="admin-kartu" aria-labelledby="judul-notif-telegram">
                    <header class="admin-kartu__header">
                        <h2 id="judul-notif-telegram">Koneksi Telegram</h2>
                    </header>
                    <p class="admin-meta">
                        1) Tempel <strong>Bot Token</strong> dari @BotFather.<br>
                        2) Buka bot di Telegram, kirim <code>/start</code>.<br>
                        3) Klik <strong>Ambil Chat ID</strong> atau isi manual Chat ID.<br>
                        Pengaturan disimpan di Supabase (aman untuk Vercel).
                    </p>
                    <label class="admin-check admin-check--blok">
                        <input type="checkbox" name="telegram_aktif" value="1"<?php echo !empty($cfg['telegram_aktif']) ? ' checked' : ''; ?>>
                        Kirim notifikasi ke Telegram saat pembayaran masuk
                    </label>
                    <div class="admin-field">
                        <label for="telegram-bot-token">Bot Token</label>
                        <input type="password" id="telegram-bot-token" name="telegram_bot_token" value="<?php echo htmlspecialchars((string) $cfg['telegram_bot_token'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" placeholder="123456789:AA...">
                    </div>
                    <div class="admin-field">
                        <label for="telegram-chat-id">Chat ID</label>
                        <input type="text" id="telegram-chat-id" name="telegram_chat_id" value="<?php echo htmlspecialchars((string) $cfg['telegram_chat_id'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" placeholder="contoh: 123456789">
                    </div>
                    <div class="admin-form-aksi admin-form-aksi--inline">
                        <button type="submit" class="admin-btn admin-btn--sekunder" formaction="" onclick="document.getElementById('notifikasi-aksi').value='ambil_chat_id';">Ambil Chat ID</button>
                        <button type="submit" class="admin-btn admin-btn--sekunder" formaction="" onclick="document.getElementById('notifikasi-aksi').value='tes_telegram';">Tes Telegram</button>
                    </div>
                </section>

                <section class="admin-kartu" aria-labelledby="judul-notif-email">
                    <header class="admin-kartu__header">
                        <h2 id="judul-notif-email">Email SMTP</h2>
                    </header>
                    <p class="admin-meta">
                        Notifikasi email dikirim ke alamat admin saat pembayaran berhasil.<br>
                        <strong>Gmail:</strong> host <code>smtp.gmail.com</code>, port <code>587</code>, user = email lengkap,
                        password = <strong>App Password</strong> (16 karakter dari Google Account → Security → App passwords), bukan password login Gmail.<br>
                        <strong>From</strong> harus sama dengan akun Gmail yang dipakai login SMTP.
                    </p>
                    <label class="admin-check admin-check--blok">
                        <input type="checkbox" name="email_aktif" value="1"<?php echo !empty($cfg['email_aktif']) ? ' checked' : ''; ?>>
                        Kirim email saat pembayaran masuk
                    </label>
                    <div class="admin-field-grid admin-field-grid--2">
                        <div class="admin-field">
                            <label for="smtp-host">Host SMTP</label>
                            <input type="text" id="smtp-host" name="smtp_host" value="<?php echo htmlspecialchars((string) $cfg['smtp_host'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="admin-field">
                            <label for="smtp-port">Port</label>
                            <input type="number" id="smtp-port" name="smtp_port" value="<?php echo (int) $cfg['smtp_port']; ?>" min="1" max="65535">
                        </div>
                        <div class="admin-field">
                            <label for="smtp-user">Username SMTP</label>
                            <input type="text" id="smtp-user" name="smtp_user" value="<?php echo htmlspecialchars((string) $cfg['smtp_user'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                        </div>
                        <div class="admin-field">
                            <label for="smtp-pass">Password SMTP</label>
                            <input type="password" id="smtp-pass" name="smtp_pass" value="<?php echo htmlspecialchars((string) $cfg['smtp_pass'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="new-password" placeholder="App Password / password mailbox">
                            <small class="admin-meta">Kosongkan hanya jika tidak ingin mengubah password yang sudah tersimpan.</small>
                        </div>
                        <div class="admin-field">
                            <label for="smtp-from">Dari (From)</label>
                            <input type="text" id="smtp-from" name="smtp_from" value="<?php echo htmlspecialchars((string) $cfg['smtp_from'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="EA SENIKERS &lt;noreply@easenikers.shop&gt;">
                        </div>
                        <div class="admin-field">
                            <label for="smtp-to">Kirim ke (admin)</label>
                            <input type="email" id="smtp-to" name="smtp_to" value="<?php echo htmlspecialchars((string) $cfg['smtp_to'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="admin@easenikers.com">
                        </div>
                    </div>
                    <div class="admin-form-aksi admin-form-aksi--inline">
                        <button type="submit" class="admin-btn admin-btn--sekunder" onclick="document.getElementById('notifikasi-aksi').value='tes_email';">Tes Email</button>
                    </div>
                </section>

                <div class="admin-form-aksi">
                    <button type="submit" class="admin-btn admin-btn--utama" onclick="document.getElementById('notifikasi-aksi').value='simpan';">Simpan pengaturan</button>
                </div>
            </form>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../includes/komponen/admin_skrip_responsif.php'; ?>
</body>
</html>