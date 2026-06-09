<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/laporan_repositori.php';
require_once __DIR__ . '/../../includes/paginasi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

$csrf = admin_csrf_token('laporan');

$nama = htmlspecialchars($_SESSION['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['aksi'] ?? '') === 'ubah_status') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!admin_csrf_valid('laporan', $token)) {
        $_SESSION['flash_laporan_error'] = 'Mohon muat ulang halaman.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $status_baru = (string) ($_POST['status_baru'] ?? '');
        if ($id > 0 && laporan_admin_ubah_status($id, $status_baru)) {
            $_SESSION['flash_laporan'] = 'Status laporan #' . $id . ' diperbarui.';
        } else {
            $_SESSION['flash_laporan_error'] = 'Gagal memperbarui status laporan.';
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$status_label = laporan_status_label();
$kategori_label = laporan_kategori_label();
$badge_kelas = laporan_status_badge();

$filter_raw = strtolower(trim((string) ($_GET['status'] ?? '')));
$filter_status = array_key_exists($filter_raw, $status_label) ? $filter_raw : '';

$tabel_ada = laporan_cek_tabel_ada();
$daftar = laporan_admin_daftar($filter_status !== '' ? $filter_status : null, $query);

$hit_status = laporan_admin_hitung_per_status();
$total_semua = array_sum($hit_status);

$flash = $_SESSION['flash_laporan'] ?? null;
$flash_error = $_SESSION['flash_laporan_error'] ?? null;
unset($_SESSION['flash_laporan'], $_SESSION['flash_laporan_error']);

$pg_params = [];
if ($query !== '') {
    $pg_params['q'] = $query;
}
if ($filter_status !== '') {
    $pg_params['status'] = $filter_status;
}
$pg = paginasi_hitung(count($daftar), paginasi_halaman_dari_query('hal'), 10);
$daftarHal = paginasi_potong($daftar, $pg);
$pg_url = paginasi_pembuat_url(aplikasi_url('admin/laporan_admin.php'), $pg_params, 'hal');

$qs_simpan_q = $query !== '' ? ['q' => $query] : [];
$url_semua = aplikasi_url('admin/laporan_admin.php' . ($qs_simpan_q !== [] ? '?' . http_build_query($qs_simpan_q) : ''));
$url_chip = static function (string $status) use ($qs_simpan_q): string {
    return aplikasi_url('admin/laporan_admin.php?' . http_build_query(['status' => $status] + $qs_simpan_q));
};

function laporan_potong_teks(string $teks, int $maks = 90): string
{
    $teks = trim($teks);
    if (function_exists('mb_strlen') && mb_strlen($teks) > $maks) {
        return mb_substr($teks, 0, $maks) . '…';
    }
    if (strlen($teks) > $maks) {
        return substr($teks, 0, $maks) . '…';
    }
    return $teks;
}

$urlKeluar = htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Masalah — EA SENIKERS Admin</title>
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>

    <link rel="stylesheet" href="../assets/css/beranda-admin.css">
</head>
<body class="halaman-admin">

    <div class="admin-cangkang">
        <?php $admin_nav_aktif = 'laporan'; include __DIR__ . '/../../includes/komponen/admin_sisi_nav.php'; ?>

        <div class="admin-utama">
            <header class="admin-bilah">
                <?php include __DIR__ . '/../../includes/komponen/admin_bilah_pengguna.php'; ?>
                <a class="admin-tombol-keluar" href="<?php echo $urlKeluar; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </a>
            </header>

            <main class="admin-isi">
                <h1 class="admin-judul-besar">Laporan Masalah</h1>
                <p class="admin-salam">Kendala yang dilaporkan pembeli — tindak lanjuti dan perbarui statusnya.</p>

                <?php if (!$tabel_ada): ?>
                    <div class="admin-alert admin-alert--error">
                        Tabel <code>laporan_masalah</code> belum ada di database. Jalankan migrasi
                        <code>database/migrations/tahap3_laporan_masalah.sql</code> di Supabase terlebih dahulu.
                    </div>
                <?php endif; ?>

                <?php if ($flash): ?>
                    <div class="admin-alert admin-alert--sukses"><?php echo htmlspecialchars((string) $flash, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($flash_error): ?>
                    <div class="admin-alert admin-alert--error"><?php echo htmlspecialchars((string) $flash_error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <div class="admin-chip-bar" aria-label="Filter status laporan">
                    <a class="admin-chip<?php echo $filter_status === '' ? ' admin-chip--aktif' : ''; ?>" href="<?php echo htmlspecialchars($url_semua, ENT_QUOTES, 'UTF-8'); ?>">
                        Semua<span><?php echo (int) $total_semua; ?></span>
                    </a>
                    <?php foreach ($status_label as $ket => $lbl): ?>
                        <a class="admin-chip<?php echo $filter_status === $ket ? ' admin-chip--aktif' : ''; ?>" href="<?php echo htmlspecialchars($url_chip((string) $ket), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?><span><?php echo (int) ($hit_status[$ket] ?? 0); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <section class="admin-kartu" aria-labelledby="judul-daftar-laporan">
                    <div class="admin-kartu__header">
                        <h2 id="judul-daftar-laporan">Daftar laporan</h2>
                        <form method="get" class="admin-cari" data-live data-target="#hasil-laporan-admin">
                            <?php if ($filter_status !== ''): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <input type="search" name="q" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Cari deskripsi, nama, email, atau nomor…" aria-label="Cari laporan" autocomplete="off">
                            <button type="submit" class="admin-btn admin-btn--sekunder">Cari</button>
                            <?php if ($query !== '' || $filter_status !== ''): ?>
                                <a href="<?php echo htmlspecialchars(aplikasi_url('admin/laporan_admin.php'), ENT_QUOTES, 'UTF-8'); ?>" class="admin-btn admin-btn--sekunder">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div id="hasil-laporan-admin">
                    <div class="admin-tabel-wrap">
                        <table class="admin-tabel">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Pelapor</th>
                                    <th scope="col">Kategori</th>
                                    <th scope="col">Deskripsi</th>
                                    <th scope="col">Bukti</th>
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($daftarHal === []): ?>
                                    <tr class="admin-tr-kosong">
                                        <td colspan="8">Belum ada laporan yang cocok dengan filter atau pencarian.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($daftarHal as $l): ?>
                                        <?php
                                        $st = (string) ($l['status'] ?? 'baru');
                                        $badge = $badge_kelas[$st] ?? 'pesanan-badge pesanan-badge--kuning';
                                        $deskripsi = (string) ($l['deskripsi'] ?? '');
                                        $ss = trim((string) ($l['screenshot'] ?? ''));
                                        $pelapor = trim((string) ($l['username'] ?? ''));
                                        $emailPelapor = trim((string) ($l['email'] ?? ''));
                                        ?>
                                        <tr>
                                            <td><strong>#<?php echo (int) ($l['id'] ?? 0); ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($pelapor !== '' ? $pelapor : 'Pembeli', ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                                <span class="admin-meta"><?php echo htmlspecialchars($emailPelapor, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($kategori_label[(string) ($l['kategori'] ?? '')] ?? (string) ($l['kategori'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span title="<?php echo htmlspecialchars($deskripsi, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(laporan_potong_teks($deskripsi), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td>
                                                <?php if ($ss !== ''): ?>
                                                    <a class="admin-laporan-bukti" href="<?php echo htmlspecialchars(laporan_url_screenshot($ss), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Lihat</a>
                                                <?php else: ?>
                                                    <span class="admin-meta">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($l['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span class="<?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status_label[$st] ?? $st, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td>
                                                <form method="post" class="admin-laporan-status">
                                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="aksi" value="ubah_status">
                                                    <input type="hidden" name="id" value="<?php echo (int) ($l['id'] ?? 0); ?>">
                                                    <select name="status_baru" aria-label="Ubah status laporan">
                                                        <?php foreach ($status_label as $sk => $sl): ?>
                                                            <option value="<?php echo htmlspecialchars($sk, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $sk ? ' selected' : ''; ?>><?php echo htmlspecialchars($sl, ENT_QUOTES, 'UTF-8'); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="admin-btn admin-btn--mini admin-btn--sekunder">Simpan</button>
                                                </form>
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
<?php include __DIR__ . '/../../includes/komponen/admin_skrip_responsif.php'; ?>
</body>
</html>
