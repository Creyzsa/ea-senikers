<?php
require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/pesanan_repositori.php';
require_once __DIR__ . '/../../includes/admin_dashboard_repositori.php';

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
    : (string) ($stat['produk_ready'] ?? 0) . ' ready · ' . (string) ($stat['produk_total'] ?? 0) . ' total';

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
                <a class="admin-nav__tautan admin-nav__tautan--aktif" href="beranda_admin.php" aria-current="page">
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
                    <span class="admin-pengguna__nama"><?php echo $nama; ?></span>
                </div>
                <a class="admin-tombol-keluar" href="<?php echo htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </a>
            </header>

            <main class="admin-isi" id="utama">
                <h1 class="admin-judul-besar">Dashboard</h1>
                <p class="admin-salam">Halo, <strong><?php echo $nama; ?></strong>. Ringkasan toko untuk <strong><?php echo htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8'); ?></strong></p>

                <div class="admin-grid-stat" role="region" aria-label="Ringkasan statistik">
                    <article class="admin-stat admin-stat--biru">
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
                    </article>
                    <article class="admin-stat admin-stat--hijau">
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
                    </article>
                    <article class="admin-stat admin-stat--kuning">
                        <div class="admin-stat__label">
                            <span>Produk aktif</span>
                            <span class="admin-stat__ikon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </span>
                        </div>
                        <p class="admin-stat__nilai"><?php echo htmlspecialchars(number_format((int) $stat['produk_total'], 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="admin-stat__tren admin-stat__tren--netral"><?php echo htmlspecialchars($tren_produk, ENT_QUOTES, 'UTF-8'); ?></p>
                    </article>
                    <article class="admin-stat admin-stat--ungu">
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
                    </article>
                </div>

                <div class="admin-baris-dua">
                    <section class="admin-panel" aria-labelledby="judul-grafik">
                        <h2 id="judul-grafik" class="admin-panel__judul">Pendapatan 7 hari</h2>
                        <p class="admin-grafik__sub">Pendapatan tercatat per hari (maksimum minggu ini: Rp <?php echo htmlspecialchars(number_format((int) $grafik_nilai_terbesar, 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?>).</p>
                        <div class="admin-grafik" role="img" aria-label="Pendapatan tujuh hari terakhir, maksimum <?php echo htmlspecialchars($grafik_aria_nilai, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php foreach ($grafik_minggu as $batang): ?>
                                <?php $h_pct = round((float) ($batang['height_pct'] ?? 0), 2); ?>
                                <?php $nil_rp = number_format((int) round($batang['nilai'] ?? 0), 0, ',', '.'); ?>
                                <div class="admin-grafik__batang-wrap" tabindex="0" role="presentation" title="Rp <?php echo htmlspecialchars($nil_rp, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php $h_show_pct = max(14, min(100, (int) round((float) $h_pct))); ?>
                                    <span class="admin-grafik__nilai-mini" aria-hidden="true"><?php echo $h_pct <= 12 ? '' : htmlspecialchars($nil_rp, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div class="admin-grafik__batang" style="height: <?php echo (string) $h_show_pct; ?>%;"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="admin-grafik__kaki">
                            <?php foreach ($grafik_minggu as $batang): ?>
                                <span><?php echo htmlspecialchars((string) ($batang['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="admin-panel" aria-labelledby="judul-aktivitas">
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

</body>
</html>
