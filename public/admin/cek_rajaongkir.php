<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/integrasi/rajaongkir.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

$nama = htmlspecialchars($_SESSION['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8');
$urlKeluar = htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8');
$urlPengaturan = aplikasi_url('admin/pengaturan_admin.php');

$api_key_ada = true;
$kota_asal_kode_tersimpan = rajaongkir_asal_kode();
$cfg = admin_pengaturan_muat_terapan();
$kota_asal_nama_tersimpan = (string) ($cfg['rajaongkir_kota_asal_nama'] ?? '');

$aksi = (string) ($_GET['aksi'] ?? '');
$cari_kata = trim((string) ($_GET['cari'] ?? ''));

$res_test = null;
$res_cari = null;
$res_ongkir = null;

if ($aksi === 'test_koneksi') {
    $res_test = rajaongkir_daftar_provinsi();
}

if ($aksi === 'cari' && $cari_kata !== '') {
    $res_cari = rajaongkir_cari_destinasi($cari_kata, 50);
}

if ($aksi === 'cek_ongkir') {
    $origin = rajaongkir_normalisasi_kode_desa((string) ($_GET['origin'] ?? ''));
    $destination = rajaongkir_normalisasi_kode_desa((string) ($_GET['destination'] ?? ''));
    $weight = (int) ($_GET['weight'] ?? 1000);
    $courier = (string) ($_GET['courier'] ?? '');
    if ($origin !== '' && $destination !== '' && $weight > 0) {
        $res_ongkir = rajaongkir_cek_ongkir($origin, $destination, $weight, $courier);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cek Ongkir JNE — EA SENIKERS Admin</title>
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
                <a class="admin-nav__tautan" href="beranda_admin.php">Dashboard</a>
                <a class="admin-nav__tautan" href="produk_admin.php">Produk</a>
                <a class="admin-nav__tautan" href="pesanan_admin.php">Pesanan</a>
                <a class="admin-nav__tautan" href="pengguna_admin.php">Pengguna</a>
                <a class="admin-nav__tautan admin-nav__tautan--aktif" href="pengaturan_admin.php" aria-current="page">Pengaturan</a>
            </nav>
            <p class="admin-sisi__kaki">© EA SENIKERS</p>
        </aside>

        <div class="admin-utama">
            <header class="admin-bilah">
                <div class="admin-pengguna">
                    <span class="admin-pengguna__nama"><?php echo $nama; ?></span>
                </div>
                <a class="admin-tombol-keluar" href="<?php echo $urlKeluar; ?>">Keluar</a>
            </header>

            <main class="admin-isi">
                <div class="admin-kop-halaman">
                    <div>
                        <h1 class="admin-judul-besar">Cek Ongkir JNE</h1>
                        <p class="admin-salam"><a href="<?php echo htmlspecialchars($urlPengaturan, ENT_QUOTES, 'UTF-8'); ?>">← Pengaturan</a></p>
                    </div>
                    <?php if ($api_key_ada): ?>
                        <a class="admin-btn admin-btn--sekunder" href="?aksi=test_koneksi">Tes koneksi</a>
                    <?php endif; ?>
                </div>

                <?php if ($kota_asal_kode_tersimpan === ''): ?>
                    <div class="admin-alert admin-alert--error">
                        Kode asal JNE belum diatur. <a href="<?php echo htmlspecialchars($urlPengaturan, ENT_QUOTES, 'UTF-8'); ?>">Buka Pengaturan toko</a> (contoh <code>PDG21100</code> untuk Padang Panjang).
                    </div>
                <?php else: ?>

                <?php if ($res_test !== null): ?>
                    <div class="admin-alert admin-alert--<?php echo $res_test['ok'] ? 'sukses' : 'error'; ?>">
                        <?php if ($res_test['ok'] && is_array($res_test['data'])): ?>
                            Koneksi berhasil · <?php echo (int) count($res_test['data']); ?> provinsi
                        <?php else: ?>
                            Gagal: <?php echo htmlspecialchars((string) ($res_test['error'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <section class="admin-kartu admin-status-rajaongkir">
                    <div class="admin-status-kartu">
                        <span>API Key</span>
                        <strong><span class="admin-lencana admin-lencana--sukses">Terisi</span></strong>
                    </div>
                    <div class="admin-status-kartu">
                        <span>Lokasi asal</span>
                        <strong><?php echo $kota_asal_nama_tersimpan !== '' ? htmlspecialchars($kota_asal_nama_tersimpan, ENT_QUOTES, 'UTF-8') : '<em class="admin-kosong">belum diatur</em>'; ?></strong>
                    </div>
                    <div class="admin-status-kartu">
                        <span>Kode asal JNE</span>
                        <strong><?php echo $kota_asal_kode_tersimpan !== '' ? htmlspecialchars($kota_asal_kode_tersimpan, ENT_QUOTES, 'UTF-8') : '<em class="admin-kosong">—</em>'; ?></strong>
                    </div>
                </section>

                <section class="admin-kartu">
                    <div class="admin-kartu__header">
                        <h2>Cari destinasi</h2>
                    </div>
                    <div class="admin-form-konten">
                        <form method="get" class="admin-form">
                            <input type="hidden" name="aksi" value="cari">
                            <div class="admin-form-grid">
                                <div class="admin-field admin-field--full">
                                    <label for="cari" class="visually-hidden">Cari</label>
                                    <input type="search" id="cari" name="cari" value="<?php echo htmlspecialchars($cari_kata, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Cari kota/kecamatan JNE — mis. padang panjang, jakarta" required autofocus>
                                </div>
                            </div>
                            <div class="admin-form-aksi">
                                <button type="submit" class="admin-btn admin-btn--utama">Cari</button>
                            </div>
                        </form>

                        <?php if ($res_cari !== null): ?>
                            <?php if (!$res_cari['ok']): ?>
                                <div class="admin-alert admin-alert--error">Gagal: <?php echo htmlspecialchars((string) ($res_cari['error'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php else: ?>
                                <?php $rows = is_array($res_cari['data']) ? $res_cari['data'] : []; ?>
                                <?php if ($rows === []): ?>
                                    <p class="admin-form-keterangan">Tidak ada hasil.</p>
                                <?php else: ?>
                                    <div class="admin-tabel-wrap">
                                        <table class="admin-tabel">
                                            <thead>
                                                <tr>
                                                    <th>Kode JNE</th>
                                                    <th>Lokasi</th>
                                                    <th>Kode Pos</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rows as $r):
                                                    if (!is_array($r)) continue;
                                                    $rid = rajaongkir_normalisasi_kode_desa((string) ($r['id'] ?? ''));
                                                    if ($rid === '') continue;
                                                    $label = (string) ($r['label'] ?? '');
                                                    if ($label === '') {
                                                        $parts = array_filter([
                                                            (string) ($r['subdistrict_name'] ?? ''),
                                                            (string) ($r['district_name'] ?? ''),
                                                            (string) ($r['city_name'] ?? ''),
                                                            (string) ($r['province_name'] ?? ''),
                                                        ]);
                                                        $label = implode(', ', $parts);
                                                    }
                                                    $pos = (string) ($r['zip_code'] ?? $r['postal_code'] ?? '');
                                                    $highlight = $rid === $kota_asal_kode_tersimpan;
                                                ?>
                                                <tr<?php echo $highlight ? ' style="background:rgba(168,144,95,0.12)"' : ''; ?>>
                                                    <td><strong><?php echo $rid; ?></strong></td>
                                                    <td><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($pos, ENT_QUOTES, 'UTF-8'); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="admin-kartu">
                    <div class="admin-kartu__header">
                        <h2>Simulasi ongkir</h2>
                    </div>
                    <div class="admin-form-konten">
                        <form method="get" class="admin-form">
                            <input type="hidden" name="aksi" value="cek_ongkir">
                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="origin">Kode asal JNE</label>
                                    <input type="text" id="origin" name="origin" pattern="[A-Za-z]{3}[0-9]{5}" maxlength="8" value="<?php echo htmlspecialchars(isset($_GET['origin']) ? (string) $_GET['origin'] : $kota_asal_kode_tersimpan, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="PDG21100" style="text-transform:uppercase">
                                </div>
                                <div class="admin-field">
                                    <label for="destination">Kode tujuan JNE</label>
                                    <input type="text" id="destination" name="destination" pattern="[A-Za-z]{3}[0-9]{5}" maxlength="8" value="<?php echo htmlspecialchars((string) ($_GET['destination'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required placeholder="CGK10400" style="text-transform:uppercase">
                                </div>
                                <div class="admin-field">
                                    <label for="weight">Berat (gram)</label>
                                    <input type="number" id="weight" name="weight" min="1" step="1" value="<?php echo isset($_GET['weight']) ? (int) $_GET['weight'] : 1000; ?>" required>
                                </div>
                                <div class="admin-field">
                                    <label for="courier">Kurir</label>
                                    <select id="courier" name="courier">
                                        <?php $courier_pilih = (string) ($_GET['courier'] ?? ''); ?>
                                        <option value=""<?php echo $courier_pilih === '' ? ' selected' : ''; ?>>Semua kurir</option>
                                        <option value="jne:pos:tiki"<?php echo $courier_pilih === 'jne:pos:tiki' ? ' selected' : ''; ?>>JNE + POS + TIKI (filter)</option>
                                        <?php foreach (rajaongkir_kurir_didukung() as $kode => $label): ?>
                                            <option value="<?php echo htmlspecialchars($kode, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $courier_pilih === $kode ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="admin-form-aksi">
                                <button type="submit" class="admin-btn admin-btn--utama">Hitung</button>
                            </div>
                        </form>

                        <?php if ($res_ongkir !== null): ?>
                            <?php if (!$res_ongkir['ok']): ?>
                                <div class="admin-alert admin-alert--error">Gagal: <?php echo htmlspecialchars((string) ($res_ongkir['error'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php elseif (is_array($res_ongkir['data'])): ?>
                                <?php
                                $rows_ongkir = [];
                                foreach ($res_ongkir['data'] as $row) {
                                    if (!is_array($row)) continue;
                                    if (isset($row['service']) && isset($row['cost'])) {
                                        $rows_ongkir[] = [
                                            'kurir' => (string) ($row['code'] ?? $row['name'] ?? ''),
                                            'service' => (string) $row['service'],
                                            'desc' => (string) ($row['description'] ?? ''),
                                            'cost' => is_numeric($row['cost']) ? (int) $row['cost'] : 0,
                                            'etd' => (string) ($row['etd'] ?? ''),
                                        ];
                                        continue;
                                    }
                                    $code = (string) ($row['code'] ?? '');
                                    $costs = is_array($row['costs'] ?? null) ? $row['costs'] : [];
                                    foreach ($costs as $c) {
                                        if (!is_array($c)) continue;
                                        $cost_inner = is_array($c['cost'] ?? null) ? $c['cost'] : [];
                                        $val = (int) ($cost_inner[0]['value'] ?? 0);
                                        $etd = (string) ($cost_inner[0]['etd'] ?? '');
                                        $rows_ongkir[] = [
                                            'kurir' => $code,
                                            'service' => (string) ($c['service'] ?? ''),
                                            'desc' => (string) ($c['description'] ?? ''),
                                            'cost' => $val,
                                            'etd' => $etd,
                                        ];
                                    }
                                }
                                ?>
                                <?php if ($rows_ongkir === []): ?>
                                    <p class="admin-form-keterangan">Tidak ada layanan untuk rute ini.</p>
                                <?php else: ?>
                                    <div class="admin-tabel-wrap">
                                        <table class="admin-tabel">
                                            <thead>
                                                <tr>
                                                    <th>Kurir</th>
                                                    <th>Layanan</th>
                                                    <th>Estimasi</th>
                                                    <th>Harga</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rows_ongkir as $o): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars(strtoupper($o['kurir']), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($o['service'], ENT_QUOTES, 'UTF-8'); ?>
                                                            <?php if ($o['desc'] !== ''): ?>
                                                                <br><small style="color:var(--teks-redup)"><?php echo htmlspecialchars($o['desc'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php
                                                            $etd_clean = trim((string) preg_replace('/\s*\b(days?|hari)\b\s*/i', '', $o['etd']));
                                                            echo htmlspecialchars($etd_clean !== '' ? $etd_clean . ' hari' : '—', ENT_QUOTES, 'UTF-8');
                                                        ?></td>
                                                        <td><strong>Rp <?php echo number_format($o['cost'], 0, ',', '.'); ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <?php endif; ?>
            </main>
        </div>
    </div>

</body>
</html>
