<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/admin_brand_logo_repositori.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

$csrf = admin_csrf_token('brand_logo');
$errors = [];
$flash = $_SESSION['flash_admin_brand'] ?? null;
unset($_SESSION['flash_admin_brand']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!admin_csrf_valid('brand_logo', $token)) {
        $errors[] = 'Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $aksi = (string) ($_POST['aksi'] ?? '');
        $brand = trim((string) ($_POST['brand'] ?? ''));

        try {
            if ($aksi === 'simpan_logo') {
                admin_brand_logo_unggah($brand, (array) ($_FILES['logo'] ?? []));
                $_SESSION['flash_admin_brand'] = [
                    'jenis' => 'sukses',
                    'teks' => 'Logo brand "' . $brand . '" berhasil diperbarui.',
                ];
            } elseif ($aksi === 'hapus_logo') {
                admin_brand_logo_hapus_untuk_brand($brand);
                $_SESSION['flash_admin_brand'] = [
                    'jenis' => 'sukses',
                    'teks' => 'Logo brand "' . $brand . '" dihapus.',
                ];
            } else {
                $errors[] = 'Aksi tidak dikenali.';
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        if ($errors === [] && $aksi !== '') {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            header('Location: ' . aplikasi_url('admin/brand_admin.php'));
            exit;
        }
    }
}

$daftar_brand = admin_brand_logo_daftar_kelola();
$storage_siap = brand_logo_siap_unggah();
$namaAdmin = htmlspecialchars((string) ($_SESSION['nama_pengguna'] ?? ''), ENT_QUOTES, 'UTF-8');
$urlKeluar = htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8');
$admin_nav_aktif = 'brand';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logo Brand — EA SENIKERS Admin</title>
    <link rel="icon" href="<?php echo htmlspecialchars(aplikasi_url('assets/images/easenikers.png'), ENT_QUOTES, 'UTF-8'); ?>" type="image/png">
    <link rel="stylesheet" href="../assets/css/beranda-admin.css">
</head>
<body class="halaman-admin">
<div class="admin-cangkang">
    <?php include __DIR__ . '/../../includes/komponen/admin_sisi_nav.php'; ?>

    <div class="admin-utama">
        <header class="admin-bilah">
            <div class="admin-pengguna">
                <span class="admin-pengguna__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </span>
                <span class="admin-pengguna__nama"><?php echo $namaAdmin; ?></span>
            </div>
            <a class="admin-tombol-keluar" href="<?php echo $urlKeluar; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Keluar
            </a>
        </header>

        <main class="admin-isi">
            <h1 class="admin-judul-besar">Logo Brand</h1>
            <p class="admin-salam">Unggah ikon/thumbnail brand untuk tampilan beranda, kategori, dan filter produk. Disarankan gambar persegi PNG/WebP dengan latar transparan.</p>

            <?php if (is_array($flash)): ?>
                <div class="admin-alert admin-alert--<?php echo htmlspecialchars((string) ($flash['jenis'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars((string) ($flash['teks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!$storage_siap && brand_logo_pakai_cloud()): ?>
                <div class="admin-alert admin-alert--error">
                    Upload logo di Vercel membutuhkan <strong>SUPABASE_URL</strong> dan <strong>SUPABASE_SERVICE_ROLE_KEY</strong>. Logo disimpan di bucket yang sama dengan foto produk.
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

            <?php if ($daftar_brand === []): ?>
                <div class="admin-panel">
                    <p class="admin-meta">Belum ada brand di katalog. Tambahkan produk dengan nama brand terlebih dahulu di halaman <a href="<?php echo htmlspecialchars(aplikasi_url('admin/produk_admin.php'), ENT_QUOTES, 'UTF-8'); ?>">Manajemen Produk</a>.</p>
                </div>
            <?php else: ?>
                <div class="admin-brand-logo-grid">
                    <?php foreach ($daftar_brand as $item): ?>
                        <?php
                        $brand = (string) ($item['brand'] ?? '');
                        $url_logo = (string) ($item['url_logo'] ?? '');
                        $punya_logo = !empty($item['punya_logo']);
                        ?>
                        <article class="admin-brand-logo-card">
                            <div class="admin-brand-logo-card__preview">
                                <?php if ($punya_logo): ?>
                                    <img src="<?php echo htmlspecialchars($url_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="120" height="120" loading="lazy">
                                <?php else: ?>
                                    <span class="admin-brand-logo-card__placeholder" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="admin-brand-logo-card__isi">
                                <h2 class="admin-brand-logo-card__nama"><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></h2>
                                <p class="admin-brand-logo-card__meta"><?php echo (int) ($item['jumlah_produk'] ?? 0); ?> produk · <?php echo $punya_logo ? 'Logo aktif' : 'Belum ada logo'; ?></p>
                                <form class="admin-brand-logo-card__form" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="aksi" value="simpan_logo">
                                    <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>">
                                    <label class="admin-field">
                                        <span>Upload logo</span>
                                        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                                        <small>JPG, PNG, atau WEBP · maks. 2MB · rasio 1:1 disarankan</small>
                                    </label>
                                    <button type="submit" class="admin-btn admin-btn--utama">Simpan logo</button>
                                </form>
                                <?php if ($punya_logo): ?>
                                    <form class="admin-brand-logo-card__hapus" method="post" onsubmit="return confirm('Hapus logo <?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>?');">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="aksi" value="hapus_logo">
                                        <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="admin-btn admin-btn--sekunder admin-btn--mini">Hapus logo</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php include __DIR__ . '/../../includes/komponen/admin_skrip_responsif.php'; ?>
</body>
</html>