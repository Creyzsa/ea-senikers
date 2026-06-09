<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/paginasi.php';

$bilah_pembeli_aktif = 'produk';
$u_beranda = aplikasi_url('');
$u_masuk = aplikasi_url('login/masuk.php');
$u_cari_saran = aplikasi_url('api/cari-saran');
$u_wishlist_toggle = aplikasi_url('api/wishlist-toggle');
$daftar_produk = katalog_ambil_semua_produk();
$checkout_habis = isset($_GET['checkout']) && (string) $_GET['checkout'] === 'habis';

$sudah_login = sudah_masuk();
$id_pengguna = $sudah_login ? (int) ($_SESSION['id_pengguna'] ?? 0) : 0;
$wishlist_ids = $id_pengguna > 0 ? wishlist_id_set($id_pengguna) : [];

$q = trim(is_string($_GET['q'] ?? null) ? (string) $_GET['q'] : '');
$bilah_cari_q = $q;
$brand_filter = trim(is_string($_GET['brand'] ?? null) ? (string) $_GET['brand'] : '');
$kondisi_filter = trim(is_string($_GET['kondisi'] ?? null) ? (string) $_GET['kondisi'] : '');
$kategori_filter = trim(is_string($_GET['kategori'] ?? null) ? (string) $_GET['kategori'] : '');
$opsi_kategori = katalog_daftar_kategori_produk();
if ($kategori_filter !== '' && !in_array($kategori_filter, $opsi_kategori, true)) {
    $kategori_filter = '';
}
$sort = trim(is_string($_GET['sort'] ?? null) ? (string) $_GET['sort'] : 'terbaru');
$sort_valid = ['terbaru', 'harga_asc', 'harga_desc', 'nama'];
if (!in_array($sort, $sort_valid, true)) {
    $sort = 'terbaru';
}

$opsi_brand = [];
$opsi_kondisi = [];
foreach ($daftar_produk as $produk) {
    $brand = trim((string) ($produk['brand'] ?? ''));
    $kondisi = trim((string) ($produk['kondisi'] ?? ''));
    if ($brand !== '') {
        $opsi_brand[$brand] = true;
    }
    if ($kondisi !== '') {
        $opsi_kondisi[$kondisi] = true;
    }
}
$opsi_brand = array_values(array_keys($opsi_brand));
$opsi_kondisi = array_values(array_keys($opsi_kondisi));
natcasesort($opsi_brand);
natcasesort($opsi_kondisi);

$daftar_tersaring = array_values(array_filter($daftar_produk, static function (array $produk) use ($q, $brand_filter, $kondisi_filter, $kategori_filter): bool {
    $brand = (string) ($produk['brand'] ?? '');
    $kondisi = (string) ($produk['kondisi'] ?? '');
    $kategori = (string) ($produk['kategori'] ?? '');

    if ($brand_filter !== '' && strcasecmp($brand, $brand_filter) !== 0) {
        return false;
    }
    if ($kondisi_filter !== '' && strcasecmp($kondisi, $kondisi_filter) !== 0) {
        return false;
    }
    if ($kategori_filter !== '' && strcasecmp($kategori, $kategori_filter) !== 0) {
        return false;
    }
    if ($q !== '' && !katalog_produk_cocok_pencarian($produk, $q)) {
        return false;
    }

    return true;
}));

if ($sort === 'harga_asc') {
    usort($daftar_tersaring, static fn (array $a, array $b): int => ((int) ($a['harga'] ?? 0)) <=> ((int) ($b['harga'] ?? 0)));
} elseif ($sort === 'harga_desc') {
    usort($daftar_tersaring, static fn (array $a, array $b): int => ((int) ($b['harga'] ?? 0)) <=> ((int) ($a['harga'] ?? 0)));
} elseif ($sort === 'nama') {
    usort($daftar_tersaring, static fn (array $a, array $b): int => strnatcasecmp((string) ($a['nama_produk'] ?? ''), (string) ($b['nama_produk'] ?? '')));
}

function produk_url_filter(array $params): string
{
    $query = [];
    foreach ($params as $key => $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $query[$key] = $value;
        }
    }

    $url = aplikasi_url('produk');

    return $query === [] ? $url : $url . '?' . http_build_query($query);
}

$total_tersaring = count($daftar_tersaring);

$pg_params = [];
foreach (['q' => $q, 'brand' => $brand_filter, 'kondisi' => $kondisi_filter, 'kategori' => $kategori_filter, 'sort' => $sort] as $pg_k => $pg_v) {
    if (trim((string) $pg_v) !== '') {
        $pg_params[$pg_k] = $pg_v;
    }
}
$pg = paginasi_hitung($total_tersaring, paginasi_halaman_dari_query('hal'), 12);
$daftar_tersaring_hal = paginasi_potong($daftar_tersaring, $pg);
$pg_url = paginasi_pembuat_url(aplikasi_url('produk'), $pg_params, 'hal');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Katalog produk — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
</head>
<body class="halaman-toko halaman-katalog"
      data-wishlist-api="<?php echo htmlspecialchars($u_wishlist_toggle, ENT_QUOTES, 'UTF-8'); ?>"
      <?php if ($sudah_login): ?>data-wishlist-csrf="<?php echo htmlspecialchars(csrf_wishlist_token(), ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<div class="katalog-latar">
    <div class="katalog-kontainer">
        <section class="katalog-hero-premium" aria-labelledby="judul-katalog">
            <div class="katalog-hero-premium__kiri">
                <p class="katalog-hero-premium__eyebrow">EA SENIKERS</p>
                <h1 id="judul-katalog">Koleksi Sneaker Terbaik Untuk Gaya Terbaikmu</h1>
                <p class="katalog-hero-premium__deskripsi">Temukan sneaker original dan preloved berkualitas dengan kondisi terjamin dan harga transparan.</p>
            </div>
            <ul class="katalog-hero-premium__trust" aria-label="Keunggulan toko">
                <li>
                    <span class="katalog-trust__ikon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-9 0H5.25A2.25 2.25 0 013 18.75V9.75m15 0V18.75A2.25 2.25 0 0118.75 21h-1.5m-15 0h15m-15 0v-3.375c0-.621.504-1.125 1.125-1.125h13.5c.621 0 1.125.504 1.125 1.125V21M3 9.75V6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v3M12 4.5v15"/></svg>
                    </span>
                    <span class="katalog-trust__teks">
                        <strong>Seller Terpercaya</strong>
                        <span>Menyediakan sneaker berkualitas dengan layanan terbaik.</span>
                    </span>
                </li>
                <li>
                    <span class="katalog-trust__ikon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    <span class="katalog-trust__teks">
                        <strong>Kondisi Terjamin</strong>
                        <span>Setiap produk diperiksa secara detail.</span>
                    </span>
                </li>
                <li>
                    <span class="katalog-trust__ikon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    </span>
                    <span class="katalog-trust__teks">
                        <strong>Transaksi Aman</strong>
                        <span>Pembayaran aman dan terpercaya.</span>
                    </span>
                </li>
            </ul>
        </section>

        <?php if ($checkout_habis): ?>
            <p class="katalog-flash-checkout" role="alert">Data checkout tidak ditemukan. Buka produk, pilih ukuran, lalu klik <strong>Beli</strong> sekali lagi.</p>
        <?php endif; ?>

        <form class="katalog-filter-premium" method="get" action="<?php echo htmlspecialchars(aplikasi_url('produk'), ENT_QUOTES, 'UTF-8'); ?>" data-live data-target="#hasil-katalog" data-cari-saran="<?php echo htmlspecialchars($u_cari_saran, ENT_QUOTES, 'UTF-8'); ?>">
            <label class="katalog-filter-premium__field katalog-filter-premium__field--cari">
                <span>Search produk</span>
                <input type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nike, Adidas, Air Max..." autocomplete="off" aria-autocomplete="list">
            </label>
            <label class="katalog-filter-premium__field">
                <span>Kategori</span>
                <select name="kategori">
                    <option value="">Semua kategori</option>
                    <?php foreach ($opsi_kategori as $kategori): ?>
                        <option value="<?php echo htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strcasecmp($kategori_filter, $kategori) === 0 ? ' selected' : ''; ?>><?php echo htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="katalog-filter-premium__field">
                <span>Merek</span>
                <select name="brand">
                    <option value="">Semua merek</option>
                    <?php foreach ($opsi_brand as $brand): ?>
                        <option value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strcasecmp($brand_filter, $brand) === 0 ? ' selected' : ''; ?>><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="katalog-filter-premium__field">
                <span>Kondisi</span>
                <select name="kondisi">
                    <option value="">Semua kondisi</option>
                    <?php foreach ($opsi_kondisi as $kondisi): ?>
                        <option value="<?php echo htmlspecialchars($kondisi, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strcasecmp($kondisi_filter, $kondisi) === 0 ? ' selected' : ''; ?>><?php echo htmlspecialchars(kondisi_label_pembeli($kondisi), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="katalog-filter-premium__field">
                <span>Urutkan</span>
                <select name="sort">
                    <option value="terbaru"<?php echo $sort === 'terbaru' ? ' selected' : ''; ?>>Terbaru</option>
                    <option value="harga_asc"<?php echo $sort === 'harga_asc' ? ' selected' : ''; ?>>Harga terendah</option>
                    <option value="harga_desc"<?php echo $sort === 'harga_desc' ? ' selected' : ''; ?>>Harga tertinggi</option>
                    <option value="nama"<?php echo $sort === 'nama' ? ' selected' : ''; ?>>Nama A–Z</option>
                </select>
            </label>
            <div class="katalog-filter-premium__aksi">
                <button type="submit" class="katalog-filter-premium__tombol katalog-filter-premium__tombol--utama">Terapkan</button>
                <a class="katalog-filter-premium__tombol katalog-filter-premium__tombol--reset" href="<?php echo htmlspecialchars(aplikasi_url('produk'), ENT_QUOTES, 'UTF-8'); ?>">Reset</a>
            </div>
        </form>

        <div id="hasil-katalog">
            <?php include __DIR__ . '/../../includes/komponen/isi_katalog_produk.php'; ?>
        </div>

        <p class="katalog-kembali">
            <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">&larr; Beranda</a>
        </p>
    </div>
</div>
<script src="../assets/js/pencarian-langsung.js" defer></script>
<script src="../assets/js/katalog-premium.js" defer></script>
</body>
</html>