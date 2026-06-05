<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

wajib_sudah_masuk(); // atau izinkan guest? untuk mantap, wajib login

$id_pengguna = (int)($_SESSION['id_pengguna'] ?? 0);
$bilah_pembeli_aktif = 'wishlist';

$u_akun = aplikasi_url('akun');
$u_produk = aplikasi_url('produk');
$u_detail = aplikasi_url('detail-produk');

$flash = $_SESSION['flash_wishlist'] ?? null;
if ($flash) unset($_SESSION['flash_wishlist']);

// Handle POST hapus dari halaman wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_produk'], $_POST['aksi']) && $_POST['aksi'] === 'hapus') {
    $pid = trim((string)$_POST['id_produk']);
    $hapus_ok = wishlist_hapus($id_pengguna, $pid);
    $_SESSION['flash_wishlist'] = $hapus_ok 
        ? 'Produk dihapus dari wishlist.' 
        : 'Gagal menghapus (koneksi database bermasalah?).';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$items = wishlist_ambil_user($id_pengguna);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wishlist — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<div class="kontainer-toko">
    <div class="blok-terlaris__header">
        <div>
            <p class="section-eyebrow">Favorit Anda</p>
            <h1 class="blok-terlaris__judul">Wishlist</h1>
        </div>
        <a href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>" class="tombol-oranye-besar" style="font-size:0.85rem;padding:0.4rem 0.9rem;">Lihat Katalog →</a>
    </div>

    <?php if ($flash): ?>
        <p class="flash-success" style="margin-bottom:1rem;"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="panel-pembeli-teks">
            <p>Wishlist Anda kosong. Mulai jelajahi produk dan tambahkan favorit Anda!</p>
            <a href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>" class="tombol-oranye-besar">Jelajahi Produk</a>
            <p style="margin-top:1rem; font-size:0.8rem; color:#666;">
                Tidak muncul padahal sudah ditambah dari detail produk? 
                Pastikan <code>config.php</code> pakai <strong>Direct connection</strong> (bukan pooler) dari Supabase Dashboard → Database → Connect. 
                Lihat config.example.php untuk petunjuk.
            </p>
        </div>
    <?php else: ?>
        <div class="rekom-grid" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
            <?php foreach ($items as $p): ?>
                <?php 
                $pid = (string)($p['id_produk'] ?? '');
                $nama = (string)($p['nama_produk'] ?? '');
                $harga = (int)($p['harga'] ?? 0);
                $brand = (string)($p['brand'] ?? '');
                ?>
                <div class="rekom-card" style="position:relative;">
                    <a href="<?php echo htmlspecialchars($u_detail . '?id=' . rawurlencode($pid), ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars(katalog_url_gambar_utama($p), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($nama) ?>" style="height:140px;">
                        <div class="rekom-info">
                            <div class="rekom-brand"><?= htmlspecialchars($brand) ?></div>
                            <div class="rekom-nama"><?= htmlspecialchars($nama) ?></div>
                            <div class="rekom-harga"><?= htmlspecialchars(katalog_format_rupiah($harga)) ?></div>
                        </div>
                    </a>
                    <form method="post" style="position:absolute; top:8px; right:8px;">
                        <input type="hidden" name="id_produk" value="<?= htmlspecialchars($pid) ?>">
                        <button type="submit" name="aksi" value="hapus" class="detail-wishlist-btn" style="padding:0.2rem 0.5rem; font-size:0.7rem; background:#fff; border:1px solid #e5e7eb;" title="Hapus dari wishlist">✕</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
