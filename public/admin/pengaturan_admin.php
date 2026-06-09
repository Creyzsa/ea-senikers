<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/admin_pengaturan_repositori.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

$csrf = admin_csrf_token('pengaturan');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!admin_csrf_valid('pengaturan', $token)) {
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
            'rajaongkir_api_key' => (string) ($_POST['rajaongkir_api_key'] ?? ''),
            'rajaongkir_kota_asal_nama' => (string) ($_POST['rajaongkir_kota_asal_nama'] ?? ''),
            'rajaongkir_kota_asal_kode' => (string) ($_POST['rajaongkir_kota_asal_kode'] ?? ''),
            'rajaongkir_kota_asal_id' => (string) ($_POST['rajaongkir_kota_asal_id'] ?? '0'),
            'pakasir_mode' => (string) ($_POST['pakasir_mode'] ?? 'sandbox'),
            'pakasir_project_slug' => (string) ($_POST['pakasir_project_slug'] ?? ''),
            'pakasir_api_key' => (string) ($_POST['pakasir_api_key'] ?? ''),
            'pakasir_metode_default' => (string) ($_POST['pakasir_metode_default'] ?? 'all'),
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
                <p class="admin-sisi__nama"><?php $ukuran_logo = 'admin'; include __DIR__ . '/../../includes/komponen/logo_teks_merek.php'; ?></p>
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
                <a class="admin-nav__tautan" href="laporan_admin.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                    Laporan
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
                <p class="admin-salam">Identitas dan preferensi pembayaran toko. Perubahan disimpan saat Anda menekan tombol <strong>Simpan pengaturan</strong> di bawah.</p>

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
                            <p class="admin-form-keterangan">Ongkir dihitung lewat <strong>JNE</strong> (jne.co.id). Pembayaran digital lewat <strong>Pakasir</strong> (QRIS, VA, PayPal).</p>
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

                    <section class="admin-kartu" aria-labelledby="judul-rajaongkir">
                        <div class="admin-kartu__header">
                            <h2 id="judul-rajaongkir">Integrasi JNE (Cek Ongkir)</h2>
                            <span class="admin-lencana admin-lencana--tunda">Tahap 2</span>
                        </div>
                        <div class="admin-form-konten">
                            <p class="admin-form-keterangan">
                                Tarif diambil dari API publik situs
                                <a href="https://jne.co.id/shipping-fee" target="_blank" rel="noopener noreferrer">jne.co.id/shipping-fee</a>
                                (<code>api-origin</code>, <code>api-destination</code>, <code>api-price</code>).
                                Kode lokasi format JNE: <strong>3 huruf + 5 angka</strong> (contoh <code>PDG21100</code> = Padang Panjang, toko EA Senikers).
                            </p>
                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="rajaongkir-kota-asal-nama">Lokasi asal (label)</label>
                                    <input type="text" id="rajaongkir-kota-asal-nama" name="rajaongkir_kota_asal_nama" value="<?php echo htmlspecialchars((string) $cfg['rajaongkir_kota_asal_nama'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Padang Panjang, Sumatera Barat">
                                </div>
                                <div class="admin-field">
                                    <label for="rajaongkir-kota-asal-kode">Kode asal JNE</label>
                                    <input type="text" id="rajaongkir-kota-asal-kode" name="rajaongkir_kota_asal_kode" value="<?php echo htmlspecialchars((string) ($cfg['rajaongkir_kota_asal_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" pattern="[A-Za-z]{3}[0-9]{5}" maxlength="8" placeholder="PDG21100" style="text-transform:uppercase">
                                </div>
                            </div>
                            <p class="admin-form-keterangan" style="margin-top:0.6rem;">
                                <strong>Cara dapat kode:</strong> buka
                                <a href="<?php echo htmlspecialchars(aplikasi_url('admin/cek_rajaongkir.php'), ENT_QUOTES, 'UTF-8'); ?>"><strong>Cek Ongkir JNE</strong></a>,
                                cari kota/kecamatan (mis. <em>padang panjang</em> untuk asal, <em>batusangkar</em> untuk Tanah Datar), salin kode 8 karakter.
                            </p>
                        </div>
                    </section>

                    <?php
                    $__pakasir_mode = (string) $cfg['pakasir_mode'];
                    require_once __DIR__ . '/../../includes/integrasi/pakasir.php';
                    $__pakasir_metode = (string) $cfg['pakasir_metode_default'];
                    $__pakasir_metode_opsi = pakasir_daftar_metode();
                    $__pakasir_webhook = pakasir_url_webhook();
                    ?>
                    <section class="admin-kartu" aria-labelledby="judul-pakasir">
                        <div class="admin-kartu__header">
                            <h2 id="judul-pakasir">Integrasi Pakasir</h2>
                        </div>
                        <div class="admin-form-konten">
                            <p class="admin-form-keterangan">
                                Payment gateway untuk QRIS, Virtual Account multi-bank, dan PayPal. Daftar di
                                <a href="https://app.pakasir.com" target="_blank" rel="noopener noreferrer">app.pakasir.com</a>,
                                buat proyek, lalu salin <strong>Project Slug</strong> dan <strong>API Key</strong>.
                                Mulai dengan <strong>Sandbox</strong> untuk uji coba.
                            </p>
                            <p class="admin-form-keterangan">
                                <strong>Webhook URL</strong> (isi di dashboard Pakasir):<br>
                                <code><?php echo htmlspecialchars($__pakasir_webhook, ENT_QUOTES, 'UTF-8'); ?></code>
                            </p>
                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="pakasir-mode">Mode</label>
                                    <select id="pakasir-mode" name="pakasir_mode">
                                        <option value="sandbox" <?php echo $__pakasir_mode === 'sandbox' ? 'selected' : ''; ?>>Sandbox (uji coba)</option>
                                        <option value="production" <?php echo $__pakasir_mode === 'production' ? 'selected' : ''; ?>>Production (live)</option>
                                    </select>
                                </div>
                                <div class="admin-field">
                                    <label for="pakasir-metode-default">Metode default</label>
                                    <select id="pakasir-metode-default" name="pakasir_metode_default">
                                        <?php foreach ($__pakasir_metode_opsi as $kode => $label): ?>
                                            <option value="<?php echo htmlspecialchars($kode, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $__pakasir_metode === $kode ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="admin-field admin-field--full">
                                    <label for="pakasir-project-slug">Project Slug</label>
                                    <input type="text" id="pakasir-project-slug" name="pakasir_project_slug" value="<?php echo htmlspecialchars((string) $cfg['pakasir_project_slug'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="slug-proyek-anda" autocomplete="off">
                                </div>
                                <div class="admin-field admin-field--full">
                                    <label for="pakasir-api-key">API Key</label>
                                    <input type="password" id="pakasir-api-key" name="pakasir_api_key" value="<?php echo htmlspecialchars((string) $cfg['pakasir_api_key'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" placeholder="API key dari dashboard Pakasir">
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
