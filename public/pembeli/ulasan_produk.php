<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/paginasi.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

$sudah_login = sudah_masuk();
$id_pengguna = $sudah_login ? ambil_id_pengguna_efektif() : 0;

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$produk = $id !== '' ? katalog_ambil_produk_ber_id($id) : null;

$bilah_pembeli_aktif = 'produk';
$u_katalog = aplikasi_url('produk');
$u_detail = $id !== '' ? aplikasi_url('detail-produk?id=' . rawurlencode($id)) : $u_katalog;

// POST ulasan (sama seperti halaman detail)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $produk !== null && $sudah_login) {
    $aksi = (string) ($_POST['aksi'] ?? '');
    if ($aksi === 'tambah_ulasan' || $aksi === 'edit_ulasan') {
        $id_post = ambil_id_pengguna_efektif(true);
        $order_id_ulasan = (int) ($_POST['order_id'] ?? 0);
        $rating = (int) ($_POST['rating'] ?? 0);
        $kom = trim((string) ($_POST['komentar'] ?? ''));
        $hasil_ulasan = ['ok' => false, 'pesan' => 'Akun tidak dikenali. Silakan login ulang.'];
        if ($id_post > 0) {
            if ($aksi === 'tambah_ulasan') {
                $hasil_ulasan = ulasan_buat($id_post, $order_id_ulasan, $id, $rating, $kom);
            } else {
                $hasil_ulasan = ulasan_perbarui($id_post, $order_id_ulasan, $id, $rating, $kom);
            }
        }
        if (!empty($hasil_ulasan['ok'])) {
            $hal = paginasi_halaman_dari_query('hal');
            $param = $aksi === 'edit_ulasan' ? 'ulasan_edit_ok=1' : 'ulasan_ok=1';
            $qs = 'id=' . rawurlencode($id) . '&hal=' . $hal . '&' . $param;
            header('Location: ' . aplikasi_url('ulasan-produk?' . $qs));
            exit;
        }
        $_SESSION['flash_keranjang_error'] = (string) ($hasil_ulasan['pesan'] ?? 'Gagal menyimpan ulasan.');
        header('Location: ' . aplikasi_url('ulasan-produk?id=' . rawurlencode($id)));
        exit;
    }
}

$flash_error = $_SESSION['flash_keranjang_error'] ?? null;
if ($flash_error !== null) {
    unset($_SESSION['flash_keranjang_error']);
}

$ulasan_labels = ulasan_label_rating();
$ulasan_stats = $produk !== null ? ulasan_stats_untuk_produk($id) : ['jumlah' => 0, 'rata' => 0.0];
$jml_ulasan = (int) ($ulasan_stats['jumlah'] ?? 0);
$rata_ulasan = (float) ($ulasan_stats['rata'] ?? 0);
if ($produk !== null && $jml_ulasan <= 0) {
    $jml_ulasan = (int) ($produk['jumlah_ulasan'] ?? 0);
    $rata_ulasan = (float) ($produk['rating_rata'] ?? 0);
}

$per_halaman = 10;
$pg = paginasi_hitung($jml_ulasan, paginasi_halaman_dari_query('hal'), $per_halaman);
$ulasan_list = $produk !== null
    ? ulasan_ambil_untuk_produk($id, $per_halaman, (int) $pg['offset'])
    : [];
$pg_url = paginasi_pembuat_url(aplikasi_url('ulasan-produk'), ['id' => $id], 'hal');

$order_id_param = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$ulasan_form = ($produk !== null && $sudah_login)
    ? ulasan_konteks_form($id_pengguna, $id, $order_id_param)
    : ['order_id' => 0, 'status' => 'tidak_berhak', 'ulasan' => null];
$ulasan_status = (string) ($ulasan_form['status'] ?? 'tidak_berhak');
$ulasan_order = (int) ($ulasan_form['order_id'] ?? 0);

$nama_produk = $produk !== null ? (string) ($produk['nama_produk'] ?? 'Produk') : 'Produk';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ulasan <?php echo htmlspecialchars($nama_produk, ENT_QUOTES, 'UTF-8'); ?> — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
</head>
<body class="halaman-toko halaman-ulasan-produk">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<div class="detail-kontainer">
    <nav class="detail-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo htmlspecialchars($u_katalog, ENT_QUOTES, 'UTF-8'); ?>">Katalog</a>
        <span aria-hidden="true"> / </span>
        <?php if ($produk !== null): ?>
            <a href="<?php echo htmlspecialchars($u_detail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($nama_produk, ENT_QUOTES, 'UTF-8'); ?></a>
            <span aria-hidden="true"> / </span>
        <?php endif; ?>
        <span>Ulasan</span>
    </nav>

    <?php if ($produk === null): ?>
        <div class="detail-404">
            <h1 style="margin:0 0 0.5rem;font-size:1.1rem;">Produk tidak ditemukan</h1>
            <p style="margin:0 0 1rem;color:#6b7280;font-size:0.9rem;">Periksa tautan atau kembali ke katalog.</p>
            <a class="tautan-kembali" href="<?php echo htmlspecialchars($u_katalog, ENT_QUOTES, 'UTF-8'); ?>">← Katalog produk</a>
        </div>
    <?php else: ?>
    <section class="detail-ulasan detail-ulasan--halaman-penuh">
        <header class="ulasan-halaman-header">
            <div>
                <h1 class="detail-panel__subjudul" style="margin:0;">Ulasan Pembeli</h1>
                <p class="ulasan-halaman-produk"><?php echo htmlspecialchars($nama_produk, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <a class="ulasan-halaman-kembali" href="<?php echo htmlspecialchars($u_detail, ENT_QUOTES, 'UTF-8'); ?>">← Kembali ke produk</a>
        </header>

        <div class="detail-rating-summary">
            <div class="rating-big">★ <?php echo number_format($rata_ulasan, 1); ?></div>
            <div class="rating-meta">
                <div><?php echo $jml_ulasan; ?> ulasan pembeli</div>
                <div>Setiap pesanan selesai boleh 1 ulasan</div>
            </div>
        </div>

        <p class="ulasan-subjudul">Satu akun bisa punya banyak ulasan untuk produk yang sama jika membeli berulang kali.</p>

        <?php if (is_string($flash_error) && $flash_error !== ''): ?>
            <p class="detail-flash-error" role="alert"><?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if ($ulasan_list !== []): ?>
            <?php ulasan_render_kartu_daftar($ulasan_list, $id, $id_pengguna, $ulasan_labels); ?>
            <?php if ((int) $pg['total_halaman'] > 1): ?>
                <p class="ulasan-halaman-info">
                    Menampilkan <?php echo (int) $pg['dari']; ?>–<?php echo (int) $pg['sampai']; ?>
                    dari <?php echo (int) $pg['total_item']; ?> ulasan
                </p>
                <?php echo paginasi_render($pg, $pg_url); ?>
            <?php endif; ?>
        <?php else: ?>
            <p class="no-review">Belum ada ulasan untuk produk ini.</p>
        <?php endif; ?>

        <?php if ($sudah_login && $ulasan_status === 'belum'): ?>
        <form method="post" class="form-ulasan">
            <input type="hidden" name="order_id" value="<?php echo $ulasan_order; ?>">
            <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
            <p class="form-ulasan-hint">1 ulasan per pesanan yang sudah <strong>selesai</strong>. Beli produk ini lagi? Setelah pesanan berikutnya selesai, Anda bisa ulas lagi.</p>
            <div class="form-row">
                <label for="rating-baru">Rating</label>
                <select id="rating-baru" name="rating" required>
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <option value="<?php echo $star; ?>"><?php echo htmlspecialchars($ulasan_labels[$star], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <textarea name="komentar" placeholder="Bagaimana kondisi & pengalaman Anda dengan produk ini?" required rows="3"></textarea>
            <button type="submit" name="aksi" value="tambah_ulasan" class="btn-ulasan btn-ulasan--utama">Kirim Ulasan</button>
        </form>
        <?php endif; ?>

        <?php if (isset($_GET['ulasan_ok'])): ?>
            <p class="flash-success">Terima kasih! Ulasan Anda telah dikirim.</p>
        <?php endif; ?>
        <?php if (isset($_GET['ulasan_edit_ok'])): ?>
            <p class="flash-success">Ulasan berhasil diperbarui. Ulasan ini sekarang dikunci dan tidak bisa diedit lagi.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>

</body>
</html>