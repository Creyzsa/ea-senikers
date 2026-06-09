<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/pesanan_repositori.php';
require_once __DIR__ . '/../../includes/repositori/admin_dashboard_repositori.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

$nama = htmlspecialchars($_SESSION['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8');

$stat = admin_dashboard_stat_kartu();
$delta_pendapatan = admin_dashboard_delta_persen((float) $stat['pendapatan_30'], (float) $stat['pendapatan_30_sebelumnya']);

$grafik_minggu = admin_dashboard_grafik_mingguan();
$aktivitas = admin_dashboard_aktivitas_terbaru(10);
$ringkasan_owner = admin_dashboard_ringkasan_owner();
$produk_perhatian = admin_dashboard_produk_perhatian();
$pesanan_terbaru = admin_dashboard_pesanan_terbaru(6);

$hit_status = pesanan_admin_hitung_per_status();
$total_pesanan_semua = array_sum($hit_status);
$status_labels = pesanan_status_label_id();
$badge_kelas = pesanan_status_kelas_badge();

$url_keluar = htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8');
$url_toko = htmlspecialchars(aplikasi_url(''), ENT_QUOTES, 'UTF-8');
$url_laporan = htmlspecialchars(aplikasi_url('admin/laporan_admin.php'), ENT_QUOTES, 'UTF-8');
$url_pesanan = htmlspecialchars(aplikasi_url('admin/pesanan_admin.php'), ENT_QUOTES, 'UTF-8');
$url_produk = htmlspecialchars(aplikasi_url('admin/produk_admin.php'), ENT_QUOTES, 'UTF-8');
$url_pengguna = htmlspecialchars(aplikasi_url('admin/pengguna_admin.php'), ENT_QUOTES, 'UTF-8');
$url_pengaturan = htmlspecialchars(aplikasi_url('admin/pengaturan_admin.php'), ENT_QUOTES, 'UTF-8');

$url_chip_pesanan = static function (string $status) use ($url_pesanan): string {
    return htmlspecialchars(
        aplikasi_url('admin/pesanan_admin.php?status=' . rawurlencode($status)),
        ENT_QUOTES,
        'UTF-8'
    );
};

$jam = (int) date('G');
if ($jam < 11) {
    $salam_waktu = 'Selamat pagi';
} elseif ($jam < 15) {
    $salam_waktu = 'Selamat siang';
} elseif ($jam < 18) {
    $salam_waktu = 'Selamat sore';
} else {
    $salam_waktu = 'Selamat malam';
}

$hari_id = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulan_id = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tanggal_tampil = htmlspecialchars(
    $hari_id[(int) date('w')] . ', ' . date('d') . ' ' . $bulan_id[(int) date('n')] . ' ' . date('Y') . ' · ' . date('H:i'),
    ENT_QUOTES,
    'UTF-8'
);

$d30 = number_format((int) round($stat['pendapatan_30']), 0, ',', '.');
$d30_prev = number_format((int) round($stat['pendapatan_30_sebelumnya']), 0, ',', '.');

$tren_pendapatan = '';
if ($delta_pendapatan !== null) {
    $abs = round(abs($delta_pendapatan), 1);
    if ($delta_pendapatan > 0.5) {
        $tren_pendapatan = '↑ ' . $abs . '% vs 30 hari sebelumnya (Rp ' . $d30_prev . ')';
    } elseif ($delta_pendapatan < -0.5) {
        $tren_pendapatan = '↓ ' . $abs . '% vs 30 hari sebelumnya (Rp ' . $d30_prev . ')';
    } else {
        $tren_pendapatan = 'Stabil dibanding periode sebelumnya (Rp ' . $d30_prev . ')';
    }
} else {
    $tren_pendapatan = ($stat['pendapatan_30'] <= 0 && $stat['pendapatan_30_sebelumnya'] <= 0)
        ? 'Belum ada pendapatan tercatat periode ini.'
        : 'Periode sebelumnya: Rp ' . $d30_prev;
}

$total_pesan_teks = number_format((int) $stat['pesanan_total'], 0, ',', '.');
$tren_pesan = '';
if ((int) $stat['pesanan_pending'] > 0) {
    $tren_pesan = (int) $stat['pesanan_pending'] . ' menunggu pembayaran · ' . number_format((int) $stat['pesanan_bulan_ini'], 0, ',', '.') . ' pesanan bulan ini';
} else {
    $tren_pesan = number_format((int) $stat['pesanan_bulan_ini'], 0, ',', '.') . ' pesanan bulan ini · total ' . $total_pesan_teks;
}

$tren_produk = ((int) $stat['produk_total'] <= 0)
    ? 'Tambahkan produk lewat menu Produk.'
    : number_format((int) $stat['produk_total'], 0, ',', '.') . ' total katalog'
        . ($produk_perhatian['jumlah_tidak_siap'] > 0
            ? ' · ' . (int) $produk_perhatian['jumlah_tidak_siap'] . ' habis stok'
            : '');

$tren_pengguna = number_format((int) $stat['pengguna'], 0, ',', '.') . ' pengguna terdaftar';

$grafik_nilai_terbesar = 0;
foreach ($grafik_minggu as $__b) {
    $grafik_nilai_terbesar = max($grafik_nilai_terbesar, (int) round($__b['nilai'] ?? 0));
}
$grafik_aria_nilai = 'Rp ' . number_format((int) $grafik_nilai_terbesar, 0, ',', '.');

$tren_pend_cls = 'admin-stat__tren';
if ($delta_pendapatan === null || abs((float) $delta_pendapatan) < 0.5) {
    $tren_pend_cls .= ' admin-stat__tren--netral';
} elseif ((float) $delta_pendapatan < -0.5) {
    $tren_pend_cls .= ' admin-stat__tren--turun';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin — EA SENIKERS</title>
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>

    <link rel="stylesheet" href="../assets/css/beranda-admin.css">
</head>
<body class="halaman-admin">

    <div class="admin-cangkang">
        <?php $admin_nav_aktif = 'dashboard'; include __DIR__ . '/../../includes/komponen/admin_sisi_nav.php'; ?>

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
                <a class="admin-tombol-keluar" href="<?php echo $url_keluar; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </a>
            </header>

            <main class="admin-isi admin-isi-dashboard" id="utama">
                <header class="admin-hero-owner">
                    <div class="admin-hero-owner__teks">
                        <p class="admin-hero-owner__salam"><?php echo htmlspecialchars($salam_waktu, ENT_QUOTES, 'UTF-8'); ?>, <strong><?php echo $nama; ?></strong></p>
                        <h1 class="admin-hero-owner__judul">Ringkasan toko Anda</h1>
                        <p class="admin-hero-owner__sub">Pantau pendapatan, pesanan, stok, dan aktivitas pembeli dari satu tempat.</p>
                        <p class="admin-hero-owner__tanggal"><?php echo $tanggal_tampil; ?></p>
                    </div>
                    <div class="admin-hero-owner__aksi">
                        <span class="admin-hero-owner__lencana">Owner / Admin</span>
                        <a class="admin-btn admin-btn--sekunder admin-btn--mini" href="<?php echo $url_toko; ?>" target="_blank" rel="noopener noreferrer">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Lihat toko
                        </a>
                    </div>
                </header>

                <?php if ($ringkasan_owner['total_pesanan_aktif'] > 0 || $ringkasan_owner['laporan_baru'] > 0 || $produk_perhatian['jumlah_tidak_siap'] > 0): ?>
                    <section class="admin-perlu-tindakan" aria-label="Perlu tindakan">
                        <h2 class="admin-perlu-tindakan__judul">Perlu perhatian</h2>
                        <div class="admin-perlu-tindakan__grid">
                            <?php if ($ringkasan_owner['paid'] > 0): ?>
                                <a class="admin-tindakan-kartu admin-tindakan-kartu--utama" href="<?php echo $url_chip_pesanan('paid'); ?>">
                                    <span class="admin-tindakan-kartu__angka"><?php echo (int) $ringkasan_owner['paid']; ?></span>
                                    <span class="admin-tindakan-kartu__teks"><strong>Perlu diproses</strong> — pembayaran sudah masuk</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($ringkasan_owner['processed'] > 0): ?>
                                <a class="admin-tindakan-kartu" href="<?php echo $url_chip_pesanan('processed'); ?>">
                                    <span class="admin-tindakan-kartu__angka"><?php echo (int) $ringkasan_owner['processed']; ?></span>
                                    <span class="admin-tindakan-kartu__teks"><strong>Siap dikirim</strong> — pesanan sudah diproses</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($ringkasan_owner['pending'] > 0): ?>
                                <a class="admin-tindakan-kartu" href="<?php echo $url_chip_pesanan('pending'); ?>">
                                    <span class="admin-tindakan-kartu__angka"><?php echo (int) $ringkasan_owner['pending']; ?></span>
                                    <span class="admin-tindakan-kartu__teks"><strong>Menunggu bayar</strong> — belum dikonfirmasi</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($produk_perhatian['jumlah_tidak_siap'] > 0): ?>
                                <a class="admin-tindakan-kartu admin-tindakan-kartu--peringatan" href="<?php echo $url_produk; ?>">
                                    <span class="admin-tindakan-kartu__angka"><?php echo (int) $produk_perhatian['jumlah_tidak_siap']; ?></span>
                                    <span class="admin-tindakan-kartu__teks"><strong>Stok habis</strong> — produk tidak siap jual</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($ringkasan_owner['laporan_baru'] > 0): ?>
                                <a class="admin-tindakan-kartu" href="<?php echo htmlspecialchars(aplikasi_url('admin/laporan_admin.php?status=baru'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="admin-tindakan-kartu__angka"><?php echo (int) $ringkasan_owner['laporan_baru']; ?></span>
                                    <span class="admin-tindakan-kartu__teks"><strong>Laporan baru</strong> — dari pembeli</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="admin-grid-stat" role="region" aria-label="Ringkasan statistik">
                    <a class="admin-stat admin-stat--klik admin-stat--biru" href="<?php echo $url_laporan; ?>">
                        <div class="admin-stat__label">
                            <span>Pendapatan (30 hari)</span>
                            <span class="admin-stat__ikon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </span>
                        </div>
                        <p class="admin-stat__nilai">Rp <?php echo htmlspecialchars($d30, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="<?php echo htmlspecialchars($tren_pend_cls, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tren_pendapatan, ENT_QUOTES, 'UTF-8'); ?></p>
                    </a>
                    <a class="admin-stat admin-stat--klik admin-stat--hijau" href="<?php echo $url_pesanan; ?>">
                        <div class="admin-stat__label">
                            <span>Pesanan</span>
                            <span class="admin-stat__ikon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                            </span>
                        </div>
                        <p class="admin-stat__nilai"><?php echo htmlspecialchars($total_pesan_teks, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="admin-stat__tren admin-stat__tren--netral"><?php echo htmlspecialchars($tren_pesan, ENT_QUOTES, 'UTF-8'); ?></p>
                    </a>
                    <a class="admin-stat admin-stat--klik admin-stat--kuning" href="<?php echo $url_produk; ?>">
                        <div class="admin-stat__label">
                            <span>Produk siap jual</span>
                            <span class="admin-stat__ikon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </span>
                        </div>
                        <p class="admin-stat__nilai"><?php echo htmlspecialchars(number_format((int) $stat['produk_ready'], 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="admin-stat__tren admin-stat__tren--netral"><?php echo htmlspecialchars($tren_produk, ENT_QUOTES, 'UTF-8'); ?></p>
                    </a>
                    <a class="admin-stat admin-stat--klik admin-stat--ungu" href="<?php echo $url_pengguna; ?>">
                        <div class="admin-stat__label">
                            <span>Pengguna</span>
                            <span class="admin-stat__ikon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </span>
                        </div>
                        <p class="admin-stat__nilai"><?php echo htmlspecialchars(number_format((int) $stat['pengguna'], 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="admin-stat__tren admin-stat__tren--netral"><?php echo htmlspecialchars($tren_pengguna, ENT_QUOTES, 'UTF-8'); ?></p>
                    </a>
                </div>

                <div class="admin-chip-bar" aria-label="Status pesanan">
                    <a class="admin-chip" href="<?php echo $url_pesanan; ?>">
                        Semua<span><?php echo htmlspecialchars((string) $total_pesanan_semua, ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <?php foreach ($status_labels as $ket => $nama_status): ?>
                        <?php $jumlah = (int) ($hit_status[$ket] ?? 0); ?>
                        <a class="admin-chip" href="<?php echo $url_chip_pesanan((string) $ket); ?>">
                            <?php echo htmlspecialchars($nama_status, ENT_QUOTES, 'UTF-8'); ?><span><?php echo htmlspecialchars((string) $jumlah, ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="admin-dashboard-bawah">
                    <section class="admin-panel admin-dashboard-bawah__grafik" aria-labelledby="judul-grafik">
                        <h2 id="judul-grafik" class="admin-panel__judul">Pendapatan 7 hari</h2>
                        <p class="admin-grafik__sub">Pendapatan tercatat per hari (maksimum minggu ini: Rp <?php echo htmlspecialchars(number_format((int) $grafik_nilai_terbesar, 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?>).</p>
                        <div class="admin-grafik" role="img" aria-label="Pendapatan tujuh hari terakhir, maksimum <?php echo htmlspecialchars($grafik_aria_nilai, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="admin-grafik__plot">
                                <?php foreach ($grafik_minggu as $batang): ?>
                                    <?php $h_pct = round((float) ($batang['height_pct'] ?? 0), 2); ?>
                                    <?php $nil_rp = number_format((int) round($batang['nilai'] ?? 0), 0, ',', '.'); ?>
                                    <?php $h_show_pct = max(6, min(100, (int) round((float) $h_pct))); ?>
                                    <div class="admin-grafik__batang-wrap" tabindex="0" role="presentation" title="Rp <?php echo htmlspecialchars($nil_rp, ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="admin-grafik__nilai-mini" aria-hidden="true"><?php echo $h_pct <= 10 ? '' : htmlspecialchars($nil_rp, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <div class="admin-grafik__batang" style="--tinggi-batang: <?php echo (string) $h_show_pct; ?>%;"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="admin-grafik__kaki">
                            <?php foreach ($grafik_minggu as $batang): ?>
                                <span><?php echo htmlspecialchars((string) ($batang['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <div class="admin-dashboard-bawah__samping-atas" aria-label="Aksi cepat dan perhatian stok">
                        <section class="admin-panel admin-panel--ringkas" aria-labelledby="judul-aksi-cepat">
                            <h2 id="judul-aksi-cepat" class="admin-panel__judul">Aksi cepat</h2>
                            <nav class="admin-aksi-grid">
                                <a class="admin-aksi-tile" href="<?php echo $url_produk; ?>">
                                    <span class="admin-aksi-tile__ikon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    </span>
                                    <span class="admin-aksi-tile__label">Tambah produk</span>
                                </a>
                                <a class="admin-aksi-tile" href="<?php echo $url_chip_pesanan('paid'); ?>">
                                    <span class="admin-aksi-tile__ikon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    </span>
                                    <span class="admin-aksi-tile__label">Proses pesanan</span>
                                </a>
                                <a class="admin-aksi-tile" href="<?php echo $url_laporan; ?>">
                                    <span class="admin-aksi-tile__ikon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 4H7a2 2 0 01-2-2V7a2 2 0 012-2h5l2 2h5a2 2 0 012 2v8a2 2 0 01-2 2z"/></svg>
                                    </span>
                                    <span class="admin-aksi-tile__label">Laporan</span>
                                </a>
                                <a class="admin-aksi-tile" href="<?php echo $url_pengaturan; ?>">
                                    <span class="admin-aksi-tile__ikon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </span>
                                    <span class="admin-aksi-tile__label">Pengaturan</span>
                                </a>
                            </nav>
                        </section>

                        <?php if ($produk_perhatian['jumlah_tidak_siap'] > 0 || $produk_perhatian['jumlah_stok_rendah'] > 0): ?>
                            <section class="admin-panel admin-panel--ringkas" aria-labelledby="judul-stok">
                                <h2 id="judul-stok" class="admin-panel__judul">Perhatian stok</h2>
                                <ul class="admin-perhatian-list">
                                    <?php foreach ($produk_perhatian['tidak_siap'] as $pr): ?>
                                        <li>
                                            <a href="<?php echo htmlspecialchars(aplikasi_url('admin/produk_admin.php?edit=' . rawurlencode((string) $pr['id'])), ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars((string) $pr['nama'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                            <span class="admin-perhatian-list__badge admin-perhatian-list__badge--habis">Habis</span>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php foreach ($produk_perhatian['stok_rendah'] as $pr): ?>
                                        <li>
                                            <a href="<?php echo htmlspecialchars(aplikasi_url('admin/produk_admin.php?edit=' . rawurlencode((string) $pr['id'])), ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars((string) $pr['nama'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                            <span class="admin-perhatian-list__badge"><?php echo (int) $pr['stok']; ?> pcs</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($produk_perhatian['jumlah_tidak_siap'] + $produk_perhatian['jumlah_stok_rendah'] > 5): ?>
                                    <p class="admin-panel__kaki-tautan">
                                        <a href="<?php echo $url_produk; ?>">Lihat semua produk →</a>
                                    </p>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>
                    </div>

                    <section class="admin-panel admin-dashboard-bawah__pesanan" aria-labelledby="judul-pesanan-terbaru">
                        <div class="admin-panel__kop">
                            <h2 id="judul-pesanan-terbaru" class="admin-panel__judul">Pesanan terbaru</h2>
                            <a class="admin-panel__tautan" href="<?php echo $url_pesanan; ?>">Semua pesanan</a>
                        </div>
                        <?php if ($pesanan_terbaru === []): ?>
                            <p class="admin-kosong">Belum ada pesanan masuk.</p>
                        <?php else: ?>
                            <div class="admin-tabel-wrap">
                                <table class="admin-tabel admin-tabel--padat">
                                    <thead>
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">Pembeli</th>
                                            <th scope="col">Total</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Waktu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pesanan_terbaru as $ps): ?>
                                            <?php
                                            $st_ps = (string) ($ps['status'] ?? '');
                                            $kelas_badge = $badge_kelas[$st_ps] ?? 'pesanan-badge';
                                            $lab_ps = $status_labels[$st_ps] ?? $st_ps;
                                            $rp_ps = number_format((int) round((float) ($ps['total_price'] ?? 0)), 0, ',', '.');
                                            ?>
                                            <tr>
                                                <td><a href="<?php echo htmlspecialchars((string) $ps['url'], ENT_QUOTES, 'UTF-8'); ?>"><strong>#<?php echo (int) $ps['id']; ?></strong></a></td>
                                                <td><?php echo htmlspecialchars((string) $ps['nama_pembeli'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>Rp <?php echo htmlspecialchars($rp_ps, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><span class="<?php echo htmlspecialchars($kelas_badge, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lab_ps, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td class="admin-meta"><?php echo htmlspecialchars((string) $ps['waktu'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="admin-panel admin-dashboard-bawah__aktivitas" aria-labelledby="judul-aktivitas">
                        <h2 id="judul-aktivitas" class="admin-panel__judul">Aktivitas terbaru</h2>
                        <ul class="admin-aktivitas">
                            <?php if ($aktivitas === []): ?>
                                <li>
                                    <span class="admin-aktivitas__titik admin-aktivitas__titik--kuning" aria-hidden="true"></span>
                                    <span class="admin-aktivitas__teks">Belum ada aktivitas terbaru.</span>
                                    <span class="admin-aktivitas__waktu">—</span>
                                </li>
                            <?php else: ?>
                                <?php foreach ($aktivitas as $ev): ?>
                                    <?php
                                    $titik_kelas = [
                                        'biru' => 'admin-aktivitas__titik--biru',
                                        'hijau' => 'admin-aktivitas__titik--hijau',
                                        'kuning' => 'admin-aktivitas__titik--kuning',
                                        'ungu' => 'admin-aktivitas__titik--ungu',
                                        'merah' => 'admin-aktivitas__titik--merah',
                                    ];
                                    $wk = (string) ($ev['warna'] ?? 'biru');
                                    $kelas_titik = $titik_kelas[$wk] ?? $titik_kelas['biru'];
                                    $url_ev = $ev['url'] ?? null;
                                    ?>
                                    <li>
                                        <span class="admin-aktivitas__titik <?php echo htmlspecialchars($kelas_titik, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
                                        <span class="admin-aktivitas__teks">
                                            <?php if (is_string($url_ev) && $url_ev !== ''): ?>
                                                <a href="<?php echo htmlspecialchars($url_ev, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($ev['teks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars((string) ($ev['teks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                        </span>
                                        <span class="admin-aktivitas__waktu"><?php echo htmlspecialchars((string) ($ev['waktu'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </section>
                </div>

            </main>
        </div>
    </div>

<?php include __DIR__ . '/../../includes/komponen/admin_skrip_responsif.php'; ?>
</body>
</html>
