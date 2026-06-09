<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/admin_pengguna_repositori.php';
require_once __DIR__ . '/../../includes/paginasi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

$nama = htmlspecialchars($_SESSION['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8');
$urlKeluar = htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8');

$q_nilai = trim((string) ($_GET['q'] ?? ''));

$rows = admin_pengguna_ambil_daftar($q_nilai === '' ? null : $q_nilai);

$pg = paginasi_hitung(count($rows), paginasi_halaman_dari_query('hal'), 10);
$rowsHal = paginasi_potong($rows, $pg);
$pg_url = paginasi_pembuat_url(aplikasi_url('admin/pengguna_admin.php'), $q_nilai !== '' ? ['q' => $q_nilai] : [], 'hal');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengguna — EA SENIKERS Admin</title>
    <link rel="stylesheet" href="../assets/css/beranda-admin.css">
</head>
<body class="halaman-admin">

    <div class="admin-cangkang">
        <?php $admin_nav_aktif = 'pengguna'; include __DIR__ . '/../../includes/komponen/admin_sisi_nav.php'; ?>

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
                <h1 class="admin-judul-besar">Pengguna</h1>
                <p class="admin-salam">Daftar nama, email, dan peran pembeli yang terhubung dengan akun toko.</p>

                <section class="admin-kartu" aria-labelledby="judul-pengguna">
                    <div class="admin-kartu__header">
                        <h2 id="judul-pengguna">Daftar pengguna</h2>
                        <form method="get" class="admin-cari" action="" data-live data-target="#hasil-pengguna">
                            <input type="search" name="q" value="<?php echo htmlspecialchars($q_nilai, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Filter nama atau email..." aria-label="Cari pengguna" autocomplete="off">
                            <button type="submit" class="admin-btn admin-btn--sekunder">Cari</button>
                            <?php if ($q_nilai !== ''): ?>
                                <a href="pengguna_admin.php" class="admin-btn admin-btn--sekunder">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div id="hasil-pengguna">
                    <div class="admin-tabel-wrap">
                        <table class="admin-tabel">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Nama</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Peran</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($rows === []): ?>
                                    <tr class="admin-tr-kosong">
                                        <td colspan="5">Tidak ada baris atau pencarian tidak cocok dengan data aktual.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rowsHal as $__u): ?>
                                        <?php
                                        $role = strtolower((string) ($__u['role'] ?? 'pembeli'));
                                        $lencana = $role === 'admin' ? 'role-lencana role-lencana--admin' : 'role-lencana';
                                        $peran_txt = $role === 'admin' ? 'Admin' : 'Pembeli';
                                        ?>
                                        <tr>
                                            <td><strong>#<?php echo htmlspecialchars((string) ($__u['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                            <td><?php echo htmlspecialchars((string) ($__u['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($__u['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span class="<?php echo htmlspecialchars($lencana, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($peran_txt, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><span class="status status--aktif">Aktif</span></td>
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
