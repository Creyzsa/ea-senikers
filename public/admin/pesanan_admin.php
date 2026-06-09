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
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>

    <link rel="stylesheet" href="../assets/css/beranda-admin.css">
</head>
<body class="halaman-admin">

    <div class="admin-cangkang">
        <?php $admin_nav_aktif = 'pesanan'; include __DIR__ . '/../../includes/komponen/admin_sisi_nav.php'; ?>

        <div class="admin-utama">
            <header class="admin-bilah">
                <?php include __DIR__ . '/../../includes/komponen/admin_notifikasi_bilah.php'; ?>
                <div class="admin-bilah__kanan">
                <?php include __DIR__ . '/../../includes/komponen/admin_bilah_pengguna.php'; ?>
                <a class="admin-tombol-keluar" href="<?php echo $urlKeluar; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </a>
                </div>
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
