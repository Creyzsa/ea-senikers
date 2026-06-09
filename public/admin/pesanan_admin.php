<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/pesanan_repositori.php';
require_once __DIR__ . '/../../includes/paginasi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

$csrf = admin_csrf_token('pesanan');

$nama = htmlspecialchars($_SESSION['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'batalkan') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!admin_csrf_valid('pesanan', $token)) {
        $_SESSION['flash_pesanan_error'] = 'Mohon muat ulang halaman.';
    } else {
        $order_id = (int) ($_POST['order_id'] ?? 0);
        if ($order_id > 0 && pesanan_admin_batalkan($order_id)) {
            $_SESSION['flash_pesanan'] = 'Pesanan berhasil dibatalkan.';
        } else {
            $_SESSION['flash_pesanan_error'] = 'Gagal membatalkan pesanan.';
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$filter_raw = strtolower(trim((string) ($_GET['status'] ?? '')));
$filter_status_kunci = '';

if ($filter_raw !== '' && $filter_raw !== 'all' && $filter_raw !== 'semua') {
    $label_semua_status = pesanan_status_label_id();
    if (array_key_exists($filter_raw, $label_semua_status)) {
        $filter_status_kunci = $filter_raw;
    }
}

$pesanan = pesanan_admin_daftar_berfilter($filter_status_kunci !== '' ? $filter_status_kunci : null, $query);

$pg_params = [];
if ($query !== '') {
    $pg_params['q'] = $query;
}
if ($filter_status_kunci !== '') {
    $pg_params['status'] = $filter_status_kunci;
}
$pg = paginasi_hitung(count($pesanan), paginasi_halaman_dari_query('hal'), 10);
$pesananHal = paginasi_potong($pesanan, $pg);
$pg_url = paginasi_pembuat_url(aplikasi_url('admin/pesanan_admin.php'), $pg_params, 'hal');

$flash = $_SESSION['flash_pesanan'] ?? null;
$flash_error = $_SESSION['flash_pesanan_error'] ?? null;
unset($_SESSION['flash_pesanan'], $_SESSION['flash_pesanan_error']);

$status_labels = pesanan_status_label_id();
$badge_kelas = pesanan_status_kelas_badge();
$urlKeluar = htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8');

$hit_status = pesanan_admin_hitung_per_status();
$total_semua = array_sum($hit_status);

$qs_simpan_q = [];
if ($query !== '') {
    $qs_simpan_q['q'] = $query;
}
$url_semua_filter = aplikasi_url('admin/pesanan_admin.php'
    . ($qs_simpan_q !== [] ? '?' . http_build_query($qs_simpan_q) : ''));

$url_chip = function (string $status) use ($qs_simpan_q): string {
    $gab = ['status' => $status] + $qs_simpan_q;

    return aplikasi_url('admin/pesanan_admin.php?' . http_build_query($gab));
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pesanan — EA SENIKERS Admin</title>
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
                <a class="admin-nav__tautan admin-nav__tautan--aktif" href="pesanan_admin.php" aria-current="page">
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
                <div class="admin-judul-baris">
                    <h1 class="admin-judul-besar">Pesanan</h1>
                    <span class="admin-live-dot" id="admin-pesanan-live" aria-live="polite">
                        <span class="admin-live-dot__label">Live</span>
                        <span class="admin-live-dot__status" id="admin-pesanan-live-status" hidden></span>
                    </span>
                </div>
                <p class="admin-salam">Saring pesanan menurut status atau cari nama / email pembeli. Daftar diperbarui otomatis setiap 10 detik.</p>

                <?php if ($flash): ?>
                    <div class="admin-alert admin-alert--sukses"><?php echo htmlspecialchars((string) $flash, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if ($flash_error): ?>
                    <div class="admin-alert admin-alert--error"><?php echo htmlspecialchars((string) $flash_error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <div id="admin-chip-pesanan" class="admin-chip-bar" aria-label="Filter status">
                    <?php $chip_aktif_semua = $filter_status_kunci === ''; ?>
                    <a class="admin-chip<?php echo $chip_aktif_semua ? ' admin-chip--aktif' : ''; ?>" href="<?php echo htmlspecialchars($url_semua_filter, ENT_QUOTES, 'UTF-8'); ?>">
                        Semua<span><?php echo htmlspecialchars((string) $total_semua, ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <?php foreach ($status_labels as $ket => $nama_status): ?>
                        <?php $jumlah = (int) ($hit_status[$ket] ?? 0); ?>
                        <?php $aktif = $filter_status_kunci === $ket; ?>
                        <a class="admin-chip<?php echo $aktif ? ' admin-chip--aktif' : ''; ?>" href="<?php echo htmlspecialchars($url_chip((string) $ket), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($nama_status, ENT_QUOTES, 'UTF-8'); ?><span><?php echo htmlspecialchars((string) $jumlah, ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <section class="admin-kartu" aria-labelledby="judul-daftar-pesanan">
                    <div class="admin-kartu__header">
                        <h2 id="judul-daftar-pesanan">Daftar pesanan</h2>
                        <form method="get" class="admin-cari" data-live data-target="#hasil-pesanan-admin">
                            <?php if ($filter_status_kunci !== ''): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status_kunci, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <input type="search" name="q" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Cari nama, email, atau nomor pesanan…" aria-label="Cari pesanan" autocomplete="off">
                            <button type="submit" class="admin-btn admin-btn--sekunder">Cari</button>
                            <?php if ($query !== '' || $filter_status_kunci !== ''): ?>
                                <a href="<?php echo htmlspecialchars(aplikasi_url('admin/pesanan_admin.php'), ENT_QUOTES, 'UTF-8'); ?>" class="admin-btn admin-btn--sekunder">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div id="hasil-pesanan-admin">
                    <div class="admin-tabel-wrap">
                        <table class="admin-tabel">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Pembeli</th>
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Total</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pesanan === []): ?>
                                    <tr class="admin-tr-kosong">
                                        <td colspan="6">Tidak ada pesanan yang cocok dengan filter atau pencarian.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pesananHal as $p): ?>
                                        <?php
                                        $st = (string) ($p['status'] ?? '');
                                        $badgeClass = $badge_kelas[$st] ?? 'pesanan-badge pesanan-badge--kuning';
                                        ?>
                                        <tr>
                                            <td><strong>#<?php echo htmlspecialchars((string) $p['id'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars((string) ($p['nama_pengguna'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                                <span class="admin-meta"><?php echo htmlspecialchars((string) ($p['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($p['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>Rp <?php echo htmlspecialchars(number_format((float) ($p['total_price'] ?? 0), 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <span class="<?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars($status_labels[$st] ?? $st, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="admin-aksi-sel">
                                                    <?php
                                                    $detail_qs = ['id' => (int) $p['id']];
                                                    if ($filter_status_kunci !== '') {
                                                        $detail_qs['status'] = $filter_status_kunci;
                                                    }
                                                    $url_detail = 'detail_pesanan_admin.php?' . http_build_query($detail_qs);
                                                    ?>
                                                    <a href="<?php echo htmlspecialchars($url_detail, ENT_QUOTES, 'UTF-8'); ?>" class="admin-btn admin-btn--mini admin-btn--sekunder">Detail</a>
                                                    <?php if (!in_array($st, ['shipped', 'completed', 'cancelled'], true)): ?>
                                                        <form method="post" onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?');">
                                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="aksi" value="batalkan">
                                                            <input type="hidden" name="order_id" value="<?php echo (int) $p['id']; ?>">
                                                            <button type="submit" class="admin-btn admin-btn--mini admin-btn--bahaya">Batalkan</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
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
<script src="../assets/js/admin-pesanan-realtime.js" defer></script>
<?php include __DIR__ . '/../../includes/komponen/admin_skrip_responsif.php'; ?>
</body>
</html>
