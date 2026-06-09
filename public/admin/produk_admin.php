<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/admin_produk_repositori.php';
require_once __DIR__ . '/../../includes/paginasi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

$csrf = admin_csrf_token('produk');
$storage_siap = produk_gambar_siap_unggah();

$errors = [];
$flash = $_SESSION['flash_admin_produk'] ?? null;
unset($_SESSION['flash_admin_produk']);

$mode = 'tambah';
$editId = trim((string) ($_GET['edit'] ?? ''));
$form = [
    'nama_produk' => '',
    'brand' => '',
    'kategori' => 'Sneakers',
    'kondisi' => 'Baru',
    'harga' => '',
    'berat_gram' => '1000',
    'deskripsi' => '',
];
$stokForm = [];
foreach (admin_daftar_ukuran_default() as $uk) {
    $stokForm[$uk] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!admin_csrf_valid('produk', $token)) {
        $errors[] = 'Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    }

    $aksi = (string) ($_POST['aksi'] ?? '');
    $idProdukPost = trim((string) ($_POST['id_produk'] ?? ''));
    if ($aksi === 'hapus') {
        if ($idProdukPost === '') {
            $errors[] = 'Produk tidak ditemukan untuk dihapus.';
        } elseif ($errors === []) {
            try {
                admin_produk_hapus($idProdukPost);
                $_SESSION['flash_admin_produk'] = ['jenis' => 'sukses', 'teks' => 'Produk berhasil dihapus.'];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                header('Location: ' . aplikasi_url('admin/produk_admin.php'));
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Gagal menghapus produk: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'hapus_gambar') {
        $idGambar = trim((string) ($_POST['id_gambar'] ?? ''));
        if ($idProdukPost === '' || $idGambar === '') {
            $errors[] = 'Data gambar tidak lengkap.';
        } elseif ($errors === []) {
            try {
                admin_produk_hapus_gambar($idProdukPost, $idGambar);
                $_SESSION['flash_admin_produk'] = ['jenis' => 'sukses', 'teks' => 'Gambar produk berhasil dihapus.'];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                header('Location: ' . aplikasi_url('admin/produk_admin.php?edit=' . rawurlencode($idProdukPost)));
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Gagal menghapus gambar: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'simpan') {
        $form = [
            'nama_produk' => trim((string) ($_POST['nama_produk'] ?? '')),
            'brand' => trim((string) ($_POST['brand'] ?? '')),
            'kategori' => trim((string) ($_POST['kategori'] ?? '')),
            'kondisi' => trim((string) ($_POST['kondisi'] ?? '')),
            'harga' => trim((string) ($_POST['harga'] ?? '')),
            'berat_gram' => trim((string) ($_POST['berat_gram'] ?? '')),
            'deskripsi' => trim((string) ($_POST['deskripsi'] ?? '')),
        ];
        $stokForm = admin_normalisasi_stok_ukuran((array) ($_POST['stok'] ?? []));

        if ($form['nama_produk'] === '') {
            $errors[] = 'Nama produk wajib diisi.';
        }
        if ($form['brand'] === '') {
            $errors[] = 'Brand wajib diisi.';
        }
        if ($form['kategori'] === '') {
            $errors[] = 'Kategori wajib diisi.';
        }
        if (!in_array($form['kondisi'], ['Baru', 'Second'], true)) {
            $errors[] = 'Kondisi produk tidak valid.';
        }
        $hargaAngka = admin_produk_parse_angka($form['harga']);
        if ($hargaAngka === null || $hargaAngka <= 0) {
            $errors[] = 'Harga harus berupa angka lebih dari 0.';
        }
        $beratAngka = admin_produk_parse_angka($form['berat_gram']);
        if ($beratAngka === null || $beratAngka <= 0 || $beratAngka > 50000) {
            $errors[] = 'Berat (gram) wajib diisi, antara 1 sampai 50.000 gram.';
        }

        if ($errors === []) {
            $payload = $form;
            $payload['harga'] = $hargaAngka;
            $payload['berat_gram'] = $beratAngka;

            try {
                if ($idProdukPost !== '') {
                    admin_produk_update($idProdukPost, $payload, $stokForm, $_FILES);
                    $_SESSION['flash_admin_produk'] = ['jenis' => 'sukses', 'teks' => 'Produk berhasil diperbarui.'];
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }
                    header('Location: ' . aplikasi_url('admin/produk_admin.php?edit=' . rawurlencode($idProdukPost)));
                    exit;
                }

                $idBaru = admin_produk_tambah($payload, $stokForm, $_FILES);
                $_SESSION['flash_admin_produk'] = ['jenis' => 'sukses', 'teks' => 'Produk baru berhasil ditambahkan.'];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                header('Location: ' . aplikasi_url('admin/produk_admin.php?edit=' . rawurlencode($idBaru)));
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Gagal menyimpan produk: ' . $e->getMessage();
            }
        }
    }
}

if ($editId !== '' && $errors === []) {
    $detail = admin_produk_ambil_detail($editId);
    if ($detail !== null) {
        $mode = 'edit';
        $form = [
            'nama_produk' => (string) ($detail['nama_produk'] ?? ''),
            'brand' => (string) ($detail['brand'] ?? ''),
            'kategori' => (string) ($detail['kategori'] ?? ''),
            'kondisi' => (string) ($detail['kondisi'] ?? 'Baru'),
            'harga' => (string) ((int) ($detail['harga'] ?? 0)),
            'berat_gram' => (string) ((int) ($detail['berat_gram'] ?? 1000)),
            'deskripsi' => (string) ($detail['deskripsi'] ?? ''),
        ];
        $stokForm = admin_normalisasi_stok_ukuran([]);
        foreach ((array) ($detail['produk_ukuran'] ?? []) as $u) {
            $uk = (string) ($u['ukuran'] ?? '');
            if (isset($stokForm[$uk])) {
                $stokForm[$uk] = max(0, (int) ($u['stok'] ?? 0));
            }
        }
    } else {
        $errors[] = 'Produk yang ingin diedit tidak ditemukan.';
    }
}

$q = trim((string) ($_GET['q'] ?? ''));

$daftar_utuh = admin_produk_ambil_semua('');
$daftarProduk = $q === '' ? $daftar_utuh : admin_produk_ambil_semua($q);

$pg = paginasi_hitung(count($daftarProduk), paginasi_halaman_dari_query('hal'), 8);
$daftarProdukHal = paginasi_potong($daftarProduk, $pg);
$pg_url = paginasi_pembuat_url(aplikasi_url('admin/produk_admin.php'), $q !== '' ? ['q' => $q] : [], 'hal');

$total_sku = count($daftar_utuh);
$siap_hit = 0;

foreach ($daftar_utuh as $__sku) {
    if (!empty($__sku['siap_jual'])) {
        ++$siap_hit;
    }
}

$namaAdmin = htmlspecialchars((string) ($_SESSION['nama_pengguna'] ?? ''), ENT_QUOTES, 'UTF-8');
$urlKeluar = htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8');
$detailEdit = $mode === 'edit' ? admin_produk_ambil_detail($editId) : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Produk — EA SENIKERS</title>
    <link rel="icon" href="<?php echo htmlspecialchars(aplikasi_url('assets/images/easenikers.png'), ENT_QUOTES, 'UTF-8'); ?>" type="image/png">
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
            <a class="admin-nav__tautan admin-nav__tautan--aktif" href="produk_admin.php" aria-current="page">
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
            <a class="admin-nav__tautan" href="pengaturan_admin.php">
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
                <span class="admin-pengguna__nama"><?php echo $namaAdmin; ?></span>
            </div>
            <a class="admin-tombol-keluar" href="<?php echo $urlKeluar; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Keluar
            </a>
        </header>

        <main class="admin-isi admin-isi-produk">
            <h1 class="admin-judul-besar">Manajemen Produk</h1>
            <p class="admin-salam">Tambahkan produk, atur stok ukuran (36–45), dan unggah foto.</p>

            <div class="admin-pil-strip" role="presentation" aria-label="Ringkasan katalog">
                <span class="admin-pil-dat"><strong><?php echo (int) $total_sku; ?></strong> item</span>
                <span class="admin-pil-dat"><strong><?php echo (int) $siap_hit; ?></strong> ready</span>
                <span class="admin-pil-dat"><strong><?php echo max(0, $total_sku - $siap_hit); ?></strong> habis stok</span>
            </div>

            <?php if (is_array($flash)): ?>
                <div class="admin-alert admin-alert--<?php echo htmlspecialchars((string) ($flash['jenis'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars((string) ($flash['teks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!$storage_siap && produk_gambar_pakai_cloud()): ?>
                <div class="admin-alert admin-alert--error">
                    Upload foto di Vercel membutuhkan <strong>SUPABASE_URL</strong>, <strong>SUPABASE_ANON_KEY</strong>, dan disarankan <strong>SUPABASE_SERVICE_ROLE_KEY</strong> di Environment Variables Vercel. Jalankan juga <code>database/migrations/tahap5_supabase_storage_produk.sql</code> di Supabase. Produk tanpa foto tetap bisa disimpan.
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="admin-alert admin-alert--error">
                    <strong>Periksa input berikut:</strong>
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?php echo htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <section class="admin-panel admin-panel-produk" aria-labelledby="judul-form-produk">
                <div class="admin-panel-produk__judul">
                    <h2 id="judul-form-produk" class="admin-panel__judul">
                        <?php echo $mode === 'edit' ? 'Edit produk' : 'Tambah produk baru'; ?>
                    </h2>
                    <?php if ($mode === 'edit'): ?>
                        <a class="admin-btn admin-btn--sekunder" href="<?php echo htmlspecialchars(aplikasi_url('admin/produk_admin.php'), ENT_QUOTES, 'UTF-8'); ?>">+ Produk baru</a>
                    <?php endif; ?>
                </div>

                <form class="admin-form-produk" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="aksi" value="simpan">
                    <?php if ($mode === 'edit'): ?>
                        <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($editId, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>

                    <div class="admin-form-grid">
                        <label class="admin-field">
                            <span>Nama produk</span>
                            <input type="text" name="nama_produk" value="<?php echo htmlspecialchars($form['nama_produk'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="180">
                        </label>
                        <label class="admin-field">
                            <span>Brand</span>
                            <input type="text" name="brand" value="<?php echo htmlspecialchars($form['brand'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="120">
                        </label>
                        <label class="admin-field">
                            <span>Kategori</span>
                            <input type="text" name="kategori" value="<?php echo htmlspecialchars($form['kategori'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
                        </label>
                        <label class="admin-field">
                            <span>Kondisi</span>
                            <select name="kondisi" required>
                                <option value="Baru" <?php echo $form['kondisi'] === 'Baru' ? 'selected' : ''; ?>>Baru</option>
                                <option value="Second" <?php echo $form['kondisi'] === 'Second' ? 'selected' : ''; ?>>Second</option>
                            </select>
                        </label>
                        <label class="admin-field">
                            <span>Harga (Rp)</span>
                            <input type="number" name="harga" value="<?php echo htmlspecialchars($form['harga'], ENT_QUOTES, 'UTF-8'); ?>" min="1" step="1" required>
                        </label>
                        <label class="admin-field">
                            <span>Berat (gram)</span>
                            <input type="number" name="berat_gram" value="<?php echo htmlspecialchars($form['berat_gram'], ENT_QUOTES, 'UTF-8'); ?>" min="1" max="50000" step="1" required>
                            <small>Berat satu pasang sepatu beserta kemasan, untuk hitung ongkir. Contoh: 1000 = 1 kg.</small>
                        </label>
                        <label class="admin-field">
                            <span>Upload foto produk</span>
                            <input type="file" name="gambar[]" multiple accept=".jpg,.jpeg,.png,.webp">
                            <small>Maks. 3MB/file. Format: JPG, PNG, WEBP.</small>
                        </label>
                    </div>

                    <label class="admin-field admin-field--full">
                        <span>Deskripsi produk</span>
                        <textarea name="deskripsi" rows="4" maxlength="4000"><?php echo htmlspecialchars($form['deskripsi'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>

                    <fieldset class="admin-stok-box">
                        <legend>Stok ukuran EU 36-45 (status ready/habis ditentukan dari stok)</legend>
                        <div class="admin-stok-grid">
                            <?php foreach (admin_daftar_ukuran_default() as $uk): ?>
                                <label class="admin-stok-item">
                                    <span><?php echo htmlspecialchars($uk, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <input type="number" name="stok[<?php echo htmlspecialchars($uk, ENT_QUOTES, 'UTF-8'); ?>]" min="0" step="1" value="<?php echo (int) ($stokForm[$uk] ?? 0); ?>">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="admin-form-aksi">
                        <button type="submit" class="admin-btn admin-btn--utama">
                            <?php echo $mode === 'edit' ? 'Simpan Perubahan' : 'Tambah Produk'; ?>
                        </button>
                        <?php if ($mode === 'edit'): ?>
                            <button type="submit" class="admin-btn admin-btn--bahaya" formaction="" formmethod="post" name="aksi" value="hapus" onclick="return confirm('Hapus produk ini beserta gambar dan stok?');">Hapus Produk</button>
                            <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($editId, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($mode === 'edit' && is_array($detailEdit)): ?>
                    <div class="admin-galeri-kecil">
                        <h3>Galeri saat ini</h3>
                        <?php $gambar = (array) ($detailEdit['produk_gambar'] ?? []); ?>
                        <?php if ($gambar === []): ?>
                            <p class="admin-galeri-kosong">Belum ada foto untuk produk ini.</p>
                        <?php else: ?>
                            <div class="admin-galeri-grid">
                                <?php foreach ($gambar as $g): ?>
                                    <?php $namaFile = (string) ($g['nama_file'] ?? ''); ?>
                                    <figure class="admin-galeri-item">
                                        <img src="<?php echo htmlspecialchars(katalog_url_gambar_produk($namaFile), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($namaFile, ENT_QUOTES, 'UTF-8'); ?>">
                                        <figcaption><?php echo htmlspecialchars($namaFile, ENT_QUOTES, 'UTF-8'); ?></figcaption>
                                        <form method="post">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="aksi" value="hapus_gambar">
                                            <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($editId, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id_gambar" value="<?php echo htmlspecialchars((string) ($g['id_gambar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="admin-btn admin-btn--mini admin-btn--bahaya" onclick="return confirm('Hapus gambar ini?');">Hapus foto</button>
                                        </form>
                                    </figure>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-bagian-tabel" aria-labelledby="judul-daftar-produk">
                <div class="admin-panel-produk__judul">
                    <h2 id="judul-daftar-produk" class="admin-panel__judul">Daftar produk</h2>
                    <form class="admin-cari" method="get" data-live data-target="#hasil-produk-admin">
                        <input type="search" name="q" placeholder="Cari nama / brand / kategori..." value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                        <button type="submit" class="admin-btn admin-btn--sekunder">Cari</button>
                    </form>
                </div>

                <div id="hasil-produk-admin">
                <div class="admin-tabel-wrap">
                    <table class="admin-tabel">
                        <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Produk</th>
                            <th>Harga</th>
                            <th>Kondisi</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($daftarProduk === []): ?>
                            <tr>
                                <td colspan="7">Belum ada produk atau hasil pencarian kosong.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daftarProdukHal as $p): ?>
                                <?php
                                $id = (string) ($p['id_produk'] ?? '');
                                $nama = (string) ($p['nama_produk'] ?? '');
                                $brand = (string) ($p['brand'] ?? '');
                                $kategori = (string) ($p['kategori'] ?? '');
                                $harga = katalog_format_rupiah((int) ($p['harga'] ?? 0));
                                $stokTotal = (int) ($p['total_stok'] ?? 0);
                                $siap = (bool) ($p['siap_jual'] ?? false);
                                $urlEdit = aplikasi_url('admin/produk_admin.php?edit=' . rawurlencode($id));
                                ?>
                                <tr>
                                    <td><img class="admin-thumb" src="<?php echo htmlspecialchars(katalog_url_gambar_utama($p), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>"></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                        <span class="admin-meta"><?php echo htmlspecialchars($brand . ' · ' . $kategori, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($harga, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($p['kondisi'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $stokTotal; ?></td>
                                    <td><span class="admin-lencana <?php echo $siap ? 'admin-lencana--sukses' : 'admin-lencana--tunda'; ?>"><?php echo $siap ? 'Ready' : 'Habis'; ?></span></td>
                                    <td><a class="admin-btn admin-btn--mini admin-btn--sekunder" href="<?php echo htmlspecialchars($urlEdit, ENT_QUOTES, 'UTF-8'); ?>">Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php echo paginasi_render($pg, $pg_url); ?>
                </div>
            </section>
        </main>
    </div>
</div>
<script src="../assets/js/pencarian-langsung.js" defer></script>
<?php include __DIR__ . '/../../includes/komponen/admin_skrip_responsif.php'; ?>
</body>
</html>
