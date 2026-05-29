<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/paginasi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$bilah_pembeli_aktif = 'produk';
$u_beranda = aplikasi_url('pembeli/beranda_pembeli.php');
$daftar_produk = katalog_ambil_semua_produk();

$q = trim(is_string($_GET['q'] ?? null) ? (string) $_GET['q'] : '');
$brand_filter = trim(is_string($_GET['brand'] ?? null) ? (string) $_GET['brand'] : '');
$kondisi_filter = trim(is_string($_GET['kondisi'] ?? null) ? (string) $_GET['kondisi'] : '');
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
$opsi_brand = array_keys($opsi_brand);
$opsi_kondisi = array_keys($opsi_kondisi);
natcasesort($opsi_brand);
natcasesort($opsi_kondisi);
$opsi_brand = array_values($opsi_brand);
$opsi_kondisi = array_values($opsi_kondisi);

$daftar_tersaring = array_values(array_filter($daftar_produk, static function (array $produk) use ($q, $brand_filter, $kondisi_filter): bool {
    $nama = (string) ($produk['nama_produk'] ?? '');
    $brand = (string) ($produk['brand'] ?? '');
    $kondisi = (string) ($produk['kondisi'] ?? '');

    if ($brand_filter !== '' && strcasecmp($brand, $brand_filter) !== 0) {
        return false;
    }
    if ($kondisi_filter !== '' && strcasecmp($kondisi, $kondisi_filter) !== 0) {
        return false;
    }
    if ($q !== '' && !katalog_teks_cocok($nama . ' ' . $brand . ' ' . $kondisi, $q)) {
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

    $url = aplikasi_url('pembeli/produk.php');
    return $query === [] ? $url : $url . '?' . http_build_query($query);
}

$total_produk = count($daftar_produk);
$total_tersaring = count($daftar_tersaring);
$jumlah_filter_aktif = ($q !== '' ? 1 : 0) + ($brand_filter !== '' ? 1 : 0) + ($kondisi_filter !== '' ? 1 : 0);

$pg_params = [];
foreach (['q' => $q, 'brand' => $brand_filter, 'kondisi' => $kondisi_filter, 'sort' => $sort] as $pg_k => $pg_v) {
    if (trim((string) $pg_v) !== '') {
        $pg_params[$pg_k] = $pg_v;
    }
}
$pg = paginasi_hitung($total_tersaring, paginasi_halaman_dari_query('hal'), 12);
$daftar_tersaring_hal = paginasi_potong($daftar_tersaring, $pg);
$pg_url = paginasi_pembuat_url(aplikasi_url('pembeli/produk.php'), $pg_params, 'hal');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Katalog produk - EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
</head>
<body class="halaman-toko halaman-katalog">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<div class="katalog-latar">
    <div class="katalog-kontainer">
        <section class="katalog-hero" aria-labelledby="judul-katalog">
            <div class="katalog-hero__teks">
                <p class="section-eyebrow">Katalog EA SENIKERS</p>
                <h1 id="judul-katalog">Pilih sneakers yang siap dipakai.</h1>
                <p>Temukan sepatu baru dan preloved terkurasi dengan kondisi jelas, foto produk, dan harga transparan.</p>
            </div>
            <div class="katalog-hero__stat" aria-label="Ringkasan katalog">
                <div>
                    <strong><?php echo (string) $total_produk; ?></strong>
                    <span>Produk</span>
                </div>
                <div>
                    <strong><?php echo (string) count($opsi_brand); ?></strong>
                    <span>Merek</span>
                </div>
                <div>
                    <strong><?php echo (string) $jumlah_filter_aktif; ?></strong>
                    <span>Filter aktif</span>
                </div>
            </div>
        </section>

        <form class="katalog-filter" method="get" action="<?php echo htmlspecialchars(aplikasi_url('pembeli/produk.php'), ENT_QUOTES, 'UTF-8'); ?>" data-live data-target="#hasil-katalog">
            <label class="katalog-filter__cari">
                <span>Cari produk</span>
                <input type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nike, Vans, Air Max..." autocomplete="off">
            </label>
            <label>
                <span>Merek</span>
                <select name="brand">
                    <option value="">Semua merek</option>
                    <?php foreach ($opsi_brand as $brand): ?>
                        <option value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strcasecmp($brand_filter, $brand) === 0 ? ' selected' : ''; ?>><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Kondisi</span>
                <select name="kondisi">
                    <option value="">Semua kondisi</option>
                    <?php foreach ($opsi_kondisi as $kondisi): ?>
                        <option value="<?php echo htmlspecialchars($kondisi, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strcasecmp($kondisi_filter, $kondisi) === 0 ? ' selected' : ''; ?>><?php echo htmlspecialchars(kondisi_label_pembeli($kondisi), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Urutkan</span>
                <select name="sort">
                    <option value="terbaru"<?php echo $sort === 'terbaru' ? ' selected' : ''; ?>>Terbaru</option>
                    <option value="harga_asc"<?php echo $sort === 'harga_asc' ? ' selected' : ''; ?>>Harga rendah</option>
                    <option value="harga_desc"<?php echo $sort === 'harga_desc' ? ' selected' : ''; ?>>Harga tinggi</option>
                    <option value="nama"<?php echo $sort === 'nama' ? ' selected' : ''; ?>>Nama A-Z</option>
                </select>
            </label>
            <div class="katalog-filter__aksi">
                <button type="submit" class="tombol-filter">Terapkan</button>
                <a class="tombol-filter tombol-filter--sekunder" href="<?php echo htmlspecialchars(aplikasi_url('pembeli/produk.php'), ENT_QUOTES, 'UTF-8'); ?>">Reset</a>
            </div>
        </form>

        <div id="hasil-katalog">
        <?php if ($daftar_produk === []): ?>
            <div class="katalog-kosong">
                <strong>Katalog kosong atau tidak dapat dimuat.</strong>
                Silakan refresh halaman. Jika masalah berlanjut, hubungi admin toko.
            </div>
        <?php elseif ($daftar_tersaring === []): ?>
            <div class="katalog-hasil-bar">
                <p>Tidak ada produk yang cocok dengan filter saat ini.</p>
                <a href="<?php echo htmlspecialchars(aplikasi_url('pembeli/produk.php'), ENT_QUOTES, 'UTF-8'); ?>">Tampilkan semua produk</a>
            </div>
            <div class="katalog-kosong">
                <strong>Produk tidak ditemukan.</strong>
                Coba gunakan kata kunci lain atau reset filter katalog.
            </div>
        <?php else: ?>
            <div class="katalog-hasil-bar">
                <p>Menampilkan <strong><?php echo (string) $pg['dari']; ?>&ndash;<?php echo (string) $pg['sampai']; ?></strong> dari <?php echo (string) $total_tersaring; ?> produk.</p>
                <div class="katalog-chip-row" aria-label="Filter aktif">
                    <?php if ($q !== ''): ?>
                        <a href="<?php echo htmlspecialchars(produk_url_filter(['brand' => $brand_filter, 'kondisi' => $kondisi_filter, 'sort' => $sort]), ENT_QUOTES, 'UTF-8'); ?>">Cari: <?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endif; ?>
                    <?php if ($brand_filter !== ''): ?>
                        <a href="<?php echo htmlspecialchars(produk_url_filter(['q' => $q, 'kondisi' => $kondisi_filter, 'sort' => $sort]), ENT_QUOTES, 'UTF-8'); ?>">Merek: <?php echo htmlspecialchars($brand_filter, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endif; ?>
                    <?php if ($kondisi_filter !== ''): ?>
                        <a href="<?php echo htmlspecialchars(produk_url_filter(['q' => $q, 'brand' => $brand_filter, 'sort' => $sort]), ENT_QUOTES, 'UTF-8'); ?>">Kondisi: <?php echo htmlspecialchars(kondisi_label_pembeli($kondisi_filter), ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="katalog-grid">
                <?php foreach ($daftar_tersaring_hal as $p):
                    $id = (string) ($p['id_produk'] ?? '');
                    $nama = (string) ($p['nama_produk'] ?? '');
                    $brand = (string) ($p['brand'] ?? '');
                    $kondisi = (string) ($p['kondisi'] ?? '');
                    $harga = (int) ($p['harga'] ?? 0);
                    $url_detail = aplikasi_url('pembeli/detail_produk.php?id=' . rawurlencode($id));
                    $url_gambar = katalog_url_gambar_utama($p);
                    $kelas_kondisi = $kondisi === ''
                        ? 'kartu-katalog__badge-kondisi--netral'
                        : (strcasecmp($kondisi, 'Baru') === 0 ? 'kartu-katalog__badge-kondisi--baru' : 'kartu-katalog__badge-kondisi--second');
                    ?>
                <a class="kartu-katalog" href="<?php echo htmlspecialchars($url_detail, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="kartu-katalog__gambar-wrap">
                        <img class="kartu-katalog__gambar" src="<?php echo htmlspecialchars($url_gambar, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" width="400" height="400">
                        <?php if ($kondisi !== ''): ?>
                            <span class="kartu-katalog__badge-kondisi <?php echo htmlspecialchars($kelas_kondisi, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(kondisi_label_pembeli($kondisi), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="kartu-katalog__isi">
                        <span class="kartu-katalog__brand"><?php echo htmlspecialchars($brand !== '' ? $brand : 'EA SENIKERS', ENT_QUOTES, 'UTF-8'); ?></span>
                        <p class="kartu-katalog__nama"><?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="kartu-katalog__harga"><?php echo htmlspecialchars(katalog_format_rupiah($harga), ENT_QUOTES, 'UTF-8'); ?></p>
                        <span class="kartu-katalog__cta">Lihat detail</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php echo paginasi_render($pg, $pg_url); ?>
        <?php endif; ?>
        </div>

        <p class="katalog-kembali">
            <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">&larr; Beranda</a>
        </p>
    </div>
</div>
<script src="../assets/js/pencarian-langsung.js" defer></script>
</body>
</html>
