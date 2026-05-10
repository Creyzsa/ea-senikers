<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/admin_pengaturan_repositori.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

if (!isset($_SESSION['csrf_admin_pengaturan']) || !is_string($_SESSION['csrf_admin_pengaturan'])) {
    $_SESSION['csrf_admin_pengaturan'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_admin_pengaturan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $_SESSION['flash_admin_pengaturan'] = ['jenis' => 'error', 'teks' => 'Mohon muat ulang halaman.'];
    } else {
        $ok = admin_pengaturan_simpan_terapan([
            'nama_toko' => (string) ($_POST['nama_toko'] ?? ''),
            'email_toko' => (string) ($_POST['email_toko'] ?? ''),
            'telepon_toko' => (string) ($_POST['telepon_toko'] ?? ''),
            'alamat_toko' => (string) ($_POST['alamat_toko'] ?? ''),
            'metode_pembayaran' => (string) ($_POST['metode_pembayaran'] ?? 'transfer'),
            'biaya_pengiriman' => (string) ($_POST['biaya_pengiriman'] ?? '0'),
            'nomor_wa_1' => (string) ($_POST['nomor_wa_1'] ?? ''),
            'nomor_wa_2' => (string) ($_POST['nomor_wa_2'] ?? ''),
        ]);
        if ($ok) {
            $_SESSION['flash_admin_pengaturan'] = ['jenis' => 'sukses', 'teks' => 'Pengaturan berhasil disimpan.'];
        } else {
            $_SESSION['flash_admin_pengaturan'] = ['jenis' => 'error', 'teks' => 'Penyimpanan gagal (periksa izin akses server).'];
        }
    }

    header('Location: ' . aplikasi_url('admin/pengaturan_admin.php'));
    exit;
}

$flash_pa = $_SESSION['flash_admin_pengaturan'] ?? null;
unset($_SESSION['flash_admin_pengaturan']);

$cfg = admin_pengaturan_muat_terapan();

$nama = htmlspecialchars($_SESSION['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8');
$urlKeluar = htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengaturan — EA SENIKERS Admin</title>
    <link rel="stylesheet" href="../assets/css/beranda-admin.css">
</head>
<body class="halaman-admin">

    <div class="admin-cangkang">
        <aside class="admin-sisi" aria-label="Navigasi admin">
            <a class="admin-sisi__merek" href="beranda_admin.php">
                <p class="admin-sisi__nama">EA SENIKERS</p>
                <p class="admin-sisi__sub">Panel Admin</p>
            </a>
            <nav class="admin-nav">
                <a class="admin-nav__tautan" href="beranda_admin.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                    Dashboard
                </a>
                <a class="admin-nav__tautan" href="produk_admin.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Produk
                </a>
                <a class="admin-nav__tautan" href="pesanan_admin.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Pesanan
                </a>
                <a class="admin-nav__tautan" href="pengguna_admin.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Pengguna
                </a>
                <a class="admin-nav__tautan admin-nav__tautan--aktif" href="pengaturan_admin.php" aria-current="page">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Pengaturan
                </a>
            </nav>
            <p class="admin-sisi__kaki">© EA SENIKERS</p>
        </aside>

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
                <a class="admin-tombol-keluar" href="<?php echo $urlKeluar; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </a>
            </header>

            <main class="admin-isi">
                <h1 class="admin-judul-besar">Pengaturan toko</h1>
                <p class="admin-salam">Identitas dan preferensi pembayaran toko. Perubahan disimpan secara otomatis setelah Anda menekan simpan.</p>

                <?php if (is_array($flash_pa)): ?>
                    <div class="admin-alert admin-alert--<?php echo htmlspecialchars((string) (($flash_pa['jenis'] ?? '') === 'error' ? 'error' : 'sukses'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) ($flash_pa['teks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form class="admin-form" method="post">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

                    <section class="admin-kartu" aria-labelledby="judul-info-toko">
                        <div class="admin-kartu__header">
                            <h2 id="judul-info-toko">Informasi toko</h2>
                        </div>
                        <div class="admin-form-konten">
                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="nama-toko">Nama toko</label>
                                    <input type="text" id="nama-toko" name="nama_toko" value="<?php echo htmlspecialchars((string) $cfg['nama_toko'], ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="organization">
                                </div>
                                <div class="admin-field">
                                    <label for="email-toko">Email toko</label>
                                    <input type="email" id="email-toko" name="email_toko" value="<?php echo htmlspecialchars((string) $cfg['email_toko'], ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="email">
                                </div>
                                <div class="admin-field">
                                    <label for="telepon-toko">Telepon</label>
                                    <input type="tel" id="telepon-toko" name="telepon_toko" value="<?php echo htmlspecialchars((string) $cfg['telepon_toko'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="tel">
                                </div>
                                <div class="admin-field admin-field--full">
                                    <label for="alamat-toko">Alamat</label>
                                    <textarea id="alamat-toko" name="alamat_toko" rows="3"><?php echo htmlspecialchars((string) $cfg['alamat_toko'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </section>

                    <?php
                    $__wa1 = (string) $cfg['nomor_wa_1'];
                    $__wa2 = (string) $cfg['nomor_wa_2'];
                    $__wa1_tampil = $__wa1 !== '' ? '+' . $__wa1 : '';
                    $__wa2_tampil = $__wa2 !== '' ? '+' . $__wa2 : '';
                    ?>
                    <section class="admin-kartu" aria-labelledby="judul-wa">
                        <div class="admin-kartu__header">
                            <h2 id="judul-wa">Layanan WhatsApp</h2>
                        </div>
                        <div class="admin-form-konten">
                            <p class="admin-form-keterangan">Nomor ini ditampilkan di footer beranda pembeli sebagai tautan layanan WhatsApp. Gunakan format internasional (mis. <code>+6282259343380</code>).</p>
                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="nomor-wa-1">Nomor WhatsApp 1</label>
                                    <input type="tel" id="nomor-wa-1" name="nomor_wa_1" value="<?php echo htmlspecialchars($__wa1_tampil, ENT_QUOTES, 'UTF-8'); ?>" placeholder="+6282259343380" autocomplete="tel">
                                </div>
                                <div class="admin-field">
                                    <label for="nomor-wa-2">Nomor WhatsApp 2</label>
                                    <input type="tel" id="nomor-wa-2" name="nomor_wa_2" value="<?php echo htmlspecialchars($__wa2_tampil, ENT_QUOTES, 'UTF-8'); ?>" placeholder="+6282171590759" autocomplete="tel">
                                </div>
                            </div>
                        </div>
                    </section>

                    <?php $__m = (string) $cfg['metode_pembayaran']; ?>
                    <section class="admin-kartu" aria-labelledby="judul-bayar">
                        <div class="admin-kartu__header">
                            <h2 id="judul-bayar">Pembayaran &amp; pengiriman</h2>
                        </div>
                        <div class="admin-form-konten">
                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="metode-pembayaran">Metode pembayaran utama</label>
                                    <select id="metode-pembayaran" name="metode_pembayaran">
                                        <option value="transfer" <?php echo $__m === 'transfer' ? 'selected' : ''; ?>>Transfer bank</option>
                                        <option value="cod" <?php echo $__m === 'cod' ? 'selected' : ''; ?>>Cash on delivery</option>
                                        <option value="ewallet" <?php echo $__m === 'ewallet' ? 'selected' : ''; ?>>E-wallet</option>
                                    </select>
                                </div>
                                <div class="admin-field">
                                    <label for="biaya-pengiriman">Biaya pengiriman default (Rp)</label>
                                    <input type="number" id="biaya-pengiriman" name="biaya_pengiriman" value="<?php echo htmlspecialchars((string) (int) $cfg['biaya_pengiriman'], ENT_QUOTES, 'UTF-8'); ?>" min="0" step="1000">
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="admin-form-aksi">
                        <button type="submit" class="admin-btn admin-btn--utama">Simpan pengaturan</button>
                        <a class="admin-btn admin-btn--sekunder" href="<?php echo htmlspecialchars(aplikasi_url('admin/pengaturan_admin.php'), ENT_QUOTES, 'UTF-8'); ?>">Batal perubahan</a>
                    </div>
                </form>
            </main>
        </div>
    </div>

</body>
</html>
