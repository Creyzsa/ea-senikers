<?php

declare(strict_types=1);

/**
 * Checkout pembeli — ongkir via API publik jne.co.id (sama dengan Cek Ongkir JNE).
 *
 * Step:
 *   1. Pembeli klik "Beli" di detail produk (POST: id_produk, ukuran).
 *      Sistem simpan ke session, redirect ke GET.
 *   2. Tampilkan ringkasan produk + alamat pembeli (dari profil).
 *      Sistem cocokkan wilayah tujuan dari alamat profil (nama kota/kecamatan, tanpa kode).
 *   3. Pembeli pilih destinasi → fetch daftar kurir+layanan+ongkir.
 *   4. Pembeli pilih layanan kurir → tampil review final.
 *   5. Pembeli klik "Konfirmasi pesanan" (POST + CSRF) → create order
 *      di DB, redirect ke halaman detail pesanan.
 */

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/checkout_sesi.php';
require_once __DIR__ . '/../../includes/url_bantu.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/keranjang_sesi.php';
require_once __DIR__ . '/../../includes/repositori/profil_pembeli_repositori.php';
require_once __DIR__ . '/../../includes/repositori/pesanan_repositori.php';
require_once __DIR__ . '/../../includes/integrasi/rajaongkir.php';

$u_self = aplikasi_url('checkout');
$u_katalog = aplikasi_url('produk');
$u_keranjang = aplikasi_url('keranjang');

// =========================================================================
// 0. ENTRY Beli: simpan produk SEBELUM cek login (cookie cadangan untuk Vercel).
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_produk'], $_POST['ukuran']) && !isset($_POST['aksi'])) {
    $id_beli = trim((string) $_POST['id_produk']);
    $uk_beli = trim((string) $_POST['ukuran']);
    if ($id_beli !== '' && $uk_beli !== '') {
        checkout_set_sesi_baris([
            ['id_produk' => $id_beli, 'ukuran' => $uk_beli, 'qty' => 1],
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (checkout_ambil_baris_aktif() === []) {
        $balik = $id_beli !== ''
            ? aplikasi_url('detail-produk?id=' . rawurlencode($id_beli) . '&checkout=habis')
            : $u_katalog . '?checkout=habis';
        header('Location: ' . $balik);
        exit;
    }
    header('Location: ' . $u_self);
    exit;
}

wajib_sudah_masuk();
checkout_pulihkan_dari_cookie();
$peran_checkout = ambil_peran();
if ($peran_checkout === 'admin') {
    header('Location: ' . aplikasi_url('admin/beranda_admin.php'));
    exit;
}
if ($peran_checkout !== null && $peran_checkout !== '' && $peran_checkout !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

if (!isset($_SESSION['csrf_checkout']) || !is_string($_SESSION['csrf_checkout'])) {
    $_SESSION['csrf_checkout'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_checkout'];

$id_pengguna = ambil_id_pengguna_efektif();
$u_akun = aplikasi_url('akun');
$u_pengaturan_toko = aplikasi_url('admin/pengaturan_admin.php');

// =========================================================================
// 0b. ENTRY dari keranjang: POST aksi=dari_keranjang
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['aksi'] ?? '') === 'dari_keranjang') {
    $err_keranjang = checkout_siapkan_dari_keranjang();
    if ($err_keranjang !== null) {
        $_SESSION['flash_checkout_error'] = $err_keranjang;
        header('Location: ' . $u_keranjang);
        exit;
    }
    header('Location: ' . $u_self);
    exit;
}

// =========================================================================
// 1a. POST aksi=pilih_destinasi → simpan destinasi terpilih ke session.
//     URL hasil cuma jadi /checkout_pembeli.php (clean) — hindari 403
//     dari mod_security karena URL panjang + banyak %2C (encoded comma).
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['aksi'] ?? '') === 'pilih_destinasi') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $_SESSION['flash_checkout_error'] = 'Mohon muat ulang halaman.';
    } else {
        $did = rajaongkir_normalisasi_kode_desa((string) ($_POST['destination_id'] ?? ''));
        $dlabel = trim((string) ($_POST['destination_label'] ?? ''));
        if ($did !== '' && $dlabel !== '') {
            $_SESSION['checkout_destinasi'] = [
                'id' => $did,
                'label' => $dlabel,
            ];
            // Reset kurir terpilih kalau destinasi berubah
            unset($_SESSION['checkout_kurir']);
        }
    }
    header('Location: ' . $u_self);
    exit;
}

// =========================================================================
// 1b. POST aksi=pilih_kurir → simpan kurir + layanan + ongkir ke session.
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['aksi'] ?? '') === 'pilih_kurir') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $_SESSION['flash_checkout_error'] = 'Mohon muat ulang halaman.';
    } else {
        $k = trim((string) ($_POST['kurir'] ?? ''));
        $svc = trim((string) ($_POST['layanan'] ?? ''));
        $ongk = (int) ($_POST['ongkir'] ?? 0);
        if ($k !== '' && $svc !== '' && $ongk >= 0) {
            $_SESSION['checkout_kurir'] = [
                'kurir' => $k,
                'layanan' => $svc,
                'ongkir' => $ongk,
            ];
        }
    }
    header('Location: ' . $u_self);
    exit;
}

// =========================================================================
// 1c. POST aksi=reset_destinasi → user mau ganti destinasi manual.
//     Reset juga flag auto-pick supaya saat search ulang sistem TIDAK
//     auto-pick lagi (user cari sendiri).
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['aksi'] ?? '') === 'reset_destinasi') {
    unset(
        $_SESSION['checkout_destinasi'],
        $_SESSION['checkout_kurir'],
        $_SESSION['checkout_destinasi_auto'],
        $_SESSION['checkout_destinasi_auto_attempted']
    );
    header('Location: ' . $u_self);
    exit;
}

// =========================================================================
// 1d. KONFIRMASI: user klik "Konfirmasi pesanan" → create order.
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['aksi'] ?? '') === 'konfirmasi') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $_SESSION['flash_checkout_error'] = 'Mohon muat ulang halaman.';
        header('Location: ' . $u_self);
        exit;
    }

    checkout_pulihkan_dari_cookie();
    $baris_konfirm = checkout_ambil_baris_aktif();
    if ($baris_konfirm === []) {
        header('Location: ' . $u_katalog . '?checkout=habis');
        exit;
    }

    $err_stok = checkout_validasi_stok_baris($baris_konfirm);
    if ($err_stok !== null) {
        $_SESSION['flash_checkout_error'] = $err_stok;
        unset($_SESSION['checkout_pesanan']);
        header('Location: ' . $u_keranjang);
        exit;
    }

    $detail_konfirm = checkout_muat_detail_baris($baris_konfirm);
    if ($detail_konfirm === []) {
        $_SESSION['flash_checkout_error'] = 'Produk sudah tidak tersedia.';
        unset($_SESSION['checkout_pesanan']);
        header('Location: ' . $u_katalog);
        exit;
    }


    $profil = profil_pembeli_ambil($id_pengguna);
    $alamat_lengkap = trim($profil['nama_penerima']) !== ''
        && trim($profil['no_hp']) !== ''
        && trim($profil['alamat_detail']) !== '';
    if (!$alamat_lengkap) {
        $_SESSION['flash_checkout_error'] = 'Lengkapi profil pengiriman dulu.';
        header('Location: ' . $u_akun . '#profil-pengiriman');
        exit;
    }

    $sesi_dest = $_SESSION['checkout_destinasi'] ?? null;
    $sesi_kur = $_SESSION['checkout_kurir'] ?? null;
    $destination_kode = is_array($sesi_dest)
        ? rajaongkir_normalisasi_kode_desa((string) ($sesi_dest['id'] ?? ''))
        : '';
    $destination_label = is_array($sesi_dest) ? trim((string) ($sesi_dest['label'] ?? '')) : '';
    $kurir = is_array($sesi_kur) ? trim((string) ($sesi_kur['kurir'] ?? '')) : '';
    $layanan = is_array($sesi_kur) ? trim((string) ($sesi_kur['layanan'] ?? '')) : '';
    $ongkir = is_array($sesi_kur) ? (int) ($sesi_kur['ongkir'] ?? 0) : 0;

    if ($destination_kode === '' || $kurir === '' || $layanan === '' || $ongkir < 0) {
        $_SESSION['flash_checkout_error'] = 'Pilih kurir dan layanan terlebih dahulu.';
        header('Location: ' . $u_self);
        exit;
    }

    $subtotal_produk = 0;
    $items = [];
    foreach ($detail_konfirm as $d) {
        $subtotal_produk += (int) $d['subtotal'];
        $items[] = [
            'product_name' => (string) $d['nama_produk'],
            'price' => (int) $d['harga'],
            'size' => (string) $d['ukuran'],
            'quantity' => (int) $d['qty'],
            'product_image' => (string) $d['nama_file'],
            'id_produk' => (string) $d['id_produk'],
        ];
    }

    $alamat_kirim_full = $profil['nama_penerima'] . ' (' . $profil['no_hp'] . ")\n"
        . $profil['alamat_detail'] . "\n"
        . trim($profil['kecamatan'] . ', ' . $profil['kota'] . ', ' . $profil['provinsi'] . ' ' . $profil['kode_pos'])
        . ($destination_label !== '' ? "\nDestinasi: " . $destination_label : '');

    $order_id = pesanan_buat(
        $id_pengguna,
        $alamat_kirim_full,
        [
            'kurir' => $kurir,
            'layanan' => $layanan,
            'ongkir' => $ongkir,
            'destination_id' => $destination_kode,
        ],
        $subtotal_produk,
        $items
    );

    if ($order_id === null || $order_id <= 0) {
        $_SESSION['flash_checkout_error'] = 'Gagal membuat pesanan. Coba lagi.';
        header('Location: ' . $u_self);
        exit;
    }

    unset($_SESSION['checkout_pesanan'], $_SESSION['checkout_destinasi'], $_SESSION['checkout_kurir']);
    checkout_hapus_cookie_baris();
    keranjang_kosongkan();
    $_SESSION['flash_pesanan_baru'] = 'Pesanan #' . $order_id . ' berhasil dibuat. Lanjutkan pembayaran via Pakasir di bawah.';
    header('Location: ' . aplikasi_url('detail-pesanan?id=' . $order_id));
    exit;
}

// =========================================================================
// 2. GET render: tampilkan checkout berdasar state session + query string.
// =========================================================================
$baris_checkout = checkout_ambil_baris_aktif();
if ($baris_checkout === []) {
    header('Location: ' . $u_katalog . '?checkout=habis');
    exit;
}
if (checkout_baris_dari_sesi($_SESSION['checkout_pesanan'] ?? null) === []) {
    checkout_set_sesi_baris($baris_checkout);
}

$detail_checkout = checkout_muat_detail_baris($baris_checkout);
if ($detail_checkout === []) {
    unset($_SESSION['checkout_pesanan']);
    header('Location: ' . $u_katalog);
    exit;
}

$harga_produk = 0;
$berat_produk = 0;
foreach ($detail_checkout as $d) {
    $harga_produk += (int) $d['subtotal'];
    $berat_produk += (int) $d['berat_gram'] * (int) $d['qty'];
}
$berat_produk = max(100, $berat_produk);

$profil = profil_pembeli_ambil($id_pengguna);
$profil_lengkap = trim($profil['nama_penerima']) !== ''
    && trim($profil['no_hp']) !== ''
    && trim($profil['alamat_detail']) !== '';

$asal_kode_toko = rajaongkir_asal_kode();
$ongkir_siap = rajaongkir_asal_kode() !== '';

$flash_error = $_SESSION['flash_checkout_error'] ?? null;
unset($_SESSION['flash_checkout_error']);

// State dari SESSION (destinasi + kurir) dan GET (cari saja)
$cari = trim((string) ($_GET['cari'] ?? ''));

$sesi_destinasi = $_SESSION['checkout_destinasi'] ?? null;
$destination_kode_pilih = is_array($sesi_destinasi)
    ? rajaongkir_normalisasi_kode_desa((string) ($sesi_destinasi['id'] ?? ''))
    : '';
$destination_label_pilih = is_array($sesi_destinasi) ? trim((string) ($sesi_destinasi['label'] ?? '')) : '';

$sesi_kurir = $_SESSION['checkout_kurir'] ?? null;
$kurir_pilih = is_array($sesi_kurir) ? trim((string) ($sesi_kurir['kurir'] ?? '')) : '';
$layanan_pilih = is_array($sesi_kurir) ? trim((string) ($sesi_kurir['layanan'] ?? '')) : '';
$ongkir_pilih = is_array($sesi_kurir) ? (int) ($sesi_kurir['ongkir'] ?? 0) : 0;

$hasil_cari = null;
$hasil_ongkir = null;
$cari_dari_profil = false;
$wilayah_butuh_pilih = false;
$skor_rekomendasi_min = 45;

if ($profil_lengkap && $asal_kode_toko !== '') {
    if ($destination_kode_pilih === '') {
        if ($cari !== '') {
            $hasil_cari = rajaongkir_cari_destinasi_tujuan($cari, 30);
        } else {
            $hasil_cari = rajaongkir_cari_untuk_profil($profil, 30);
            $cari_dari_profil = true;
        }

        if ($hasil_cari !== null && $hasil_cari['ok'] && is_array($hasil_cari['data']) && $hasil_cari['data'] !== []) {
            $hasil_cari['data'] = rajaongkir_urutkan_hasil_profil($hasil_cari['data'], $profil);

            if (empty($_SESSION['checkout_destinasi_auto_attempted'])) {
                $top = rajaongkir_pilih_destinasi_terbaik($hasil_cari['data'], $profil);
                if ($top !== null) {
                    $top_id = rajaongkir_normalisasi_kode_desa((string) ($top['id'] ?? ''));
                    $top_label = rajaongkir_baris_label_tampilan($top);
                    if ($top_id !== '' && $top_label !== '') {
                        $_SESSION['checkout_destinasi'] = ['id' => $top_id, 'label' => $top_label];
                        $_SESSION['checkout_destinasi_auto'] = true;
                        $_SESSION['checkout_destinasi_auto_attempted'] = true;
                        header('Location: ' . $u_self);
                        exit;
                    }
                }
                $_SESSION['checkout_destinasi_auto_attempted'] = true;
            }

            $wilayah_butuh_pilih = true;
        } elseif ($cari_dari_profil) {
            $_SESSION['checkout_destinasi_auto_attempted'] = true;
        }
    }

    if ($destination_kode_pilih !== '') {
        $hasil_ongkir = rajaongkir_cek_ongkir($asal_kode_toko, $destination_kode_pilih, $berat_produk, '');
    }
}

if ($destination_label_pilih !== '') {
    $destination_label_pilih = rajaongkir_label_tampilan($destination_label_pilih);
}

function checkout_url(string $base, array $params): string
{
    $clean = [];
    foreach ($params as $k => $v) {
        $v = (string) $v;
        if ($v !== '') {
            $clean[$k] = $v;
        }
    }
    return $clean === [] ? $base : $base . '?' . http_build_query($clean);
}

/**
 * Format ETD dari RajaOngkir agar selalu "X hari" tanpa duplikasi.
 * RajaOngkir bisa return "2-4", "2-4 day", "2-4 days", atau "2-4 hari".
 */
function checkout_format_etd(string $etd): string
{
    $bersih = trim((string) preg_replace('/\s*\b(days?|hari)\b\s*/i', '', $etd));
    if ($bersih === '') {
        return '';
    }
    return $bersih . ' hari';
}

$bilah_pembeli_aktif = 'produk';
$total_final = $harga_produk + $ongkir_pilih;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="checkout-wrap">
    <nav class="detail-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo htmlspecialchars($u_katalog, ENT_QUOTES, 'UTF-8'); ?>">Katalog</a>
        <span aria-hidden="true"> / </span>
        <span>Checkout</span>
    </nav>

    <h1 class="checkout-judul">Checkout pesanan</h1>

    <?php if (is_string($flash_error) && $flash_error !== ''): ?>
        <div class="checkout-alert checkout-alert--error" role="alert"><?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$profil_lengkap): ?>
        <div class="checkout-alert checkout-alert--peringatan">
            <strong>Lengkapi profil pengiriman dulu.</strong>
            Nama penerima, nomor HP, dan alamat detail wajib diisi sebelum checkout.
            <a href="<?php echo htmlspecialchars($u_akun, ENT_QUOTES, 'UTF-8'); ?>#profil-pengiriman">Buka profil →</a>
        </div>
    <?php elseif (!$ongkir_siap): ?>
        <div class="checkout-alert checkout-alert--peringatan">
            <strong>Toko belum siap menerima pesanan.</strong>
            Admin perlu mengatur <strong>lokasi asal toko</strong> di Pengaturan agar ongkir bisa dihitung.
            Sementara waktu, silakan hubungi admin lewat WhatsApp.
        </div>
    <?php else: ?>

    <div class="checkout-grid">
        <div class="checkout-kolom-utama">

            <section class="checkout-kartu">
                <h2 class="checkout-kartu__judul">Produk yang dibeli</h2>
                <?php foreach ($detail_checkout as $d): ?>
                <div class="checkout-produk-baris">
                    <img class="checkout-produk-gambar" src="<?php echo htmlspecialchars((string) $d['gambar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="84" height="84">
                    <div>
                        <p class="checkout-produk-brand"><?php echo htmlspecialchars((string) $d['brand'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(kondisi_label_pembeli((string) $d['kondisi']), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="checkout-produk-nama"><?php echo htmlspecialchars((string) $d['nama_produk'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="checkout-produk-meta">
                            Ukuran <strong><?php echo htmlspecialchars((string) $d['ukuran'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            · Qty <strong><?php echo (int) $d['qty']; ?></strong>
                        </p>
                    </div>
                    <p class="checkout-produk-harga"><?php echo htmlspecialchars(katalog_format_rupiah((int) $d['subtotal']), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <?php endforeach; ?>
                <p class="checkout-produk-meta checkout-produk-meta--total-berat">
                    Total berat kirim: <strong><?php echo (int) $berat_produk; ?> g</strong>
                    <?php if ($berat_produk > 15000): ?>
                        <span class="checkout-berat-anomali">⚠ berat tidak wajar</span>
                    <?php endif; ?>
                </p>
            </section>

            <section class="checkout-kartu">
                <h2 class="checkout-kartu__judul">Dikirim ke</h2>
                <p class="checkout-alamat">
                    <strong><?php echo htmlspecialchars($profil['nama_penerima'], ENT_QUOTES, 'UTF-8'); ?></strong> · <?php echo htmlspecialchars($profil['no_hp'], ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php echo htmlspecialchars($profil['alamat_detail'], ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php echo htmlspecialchars(trim($profil['kecamatan'] . ', ' . $profil['kota'] . ', ' . $profil['provinsi'] . ' ' . $profil['kode_pos'], ', '), ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <a class="checkout-link-kecil" href="<?php echo htmlspecialchars($u_akun, ENT_QUOTES, 'UTF-8'); ?>#profil-pengiriman">Ubah alamat di profil →</a>
            </section>

            <section class="checkout-kartu">
                <h2 class="checkout-kartu__judul">
                    <?php if ($destination_kode_pilih !== ''): ?>Kurir &amp; layanan
                    <?php else: ?>Wilayah pengiriman
                    <?php endif; ?>
                </h2>

                <?php if ($destination_kode_pilih !== ''):
                    $auto_picked = !empty($_SESSION['checkout_destinasi_auto']);
                ?>
                    <p class="checkout-destinasi-aktif">
                        <span><?php echo $auto_picked ? 'Wilayah dari alamat profil:' : 'Wilayah pengiriman:'; ?></span>
                        <strong><?php echo htmlspecialchars($destination_label_pilih, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <form method="post" action="<?php echo htmlspecialchars($u_self, ENT_QUOTES, 'UTF-8'); ?>" class="checkout-inline-form">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="aksi" value="reset_destinasi">
                            <button type="submit" class="checkout-tombol-link">Ganti</button>
                        </form>
                    </p>

                    <?php if ($hasil_ongkir === null || !$hasil_ongkir['ok']): ?>
                        <p class="checkout-error-baris">Gagal hitung ongkir JNE: <?php echo htmlspecialchars((string) ($hasil_ongkir['error'] ?? 'koneksi jne.co.id gagal'), ENT_QUOTES, 'UTF-8'); ?>. Coba ulang.</p>
                    <?php else: ?>
                        <?php
                        $opsi_ongkir = [];
                        foreach ((array) $hasil_ongkir['data'] as $row) {
                            if (!is_array($row)) continue;
                            if (isset($row['service']) && isset($row['cost'])) {
                                $opsi_ongkir[] = [
                                    'kurir' => (string) ($row['code'] ?? ''),
                                    'service' => (string) $row['service'],
                                    'desc' => (string) ($row['description'] ?? ''),
                                    'cost' => is_numeric($row['cost']) ? (int) $row['cost'] : 0,
                                    'etd' => (string) ($row['etd'] ?? ''),
                                ];
                            }
                        }
                        ?>
                        <?php if ($opsi_ongkir === []): ?>
                            <p class="checkout-error-baris">Tidak ada layanan kurir untuk rute ini.</p>
                        <?php else: ?>
                            <ul class="checkout-kurir-list">
                                <?php foreach ($opsi_ongkir as $o):
                                    $aktif = $kurir_pilih === $o['kurir'] && $layanan_pilih === $o['service'];
                                ?>
                                    <li class="checkout-kurir-item<?php echo $aktif ? ' checkout-kurir-item--aktif' : ''; ?>">
                                        <form method="post" action="<?php echo htmlspecialchars($u_self, ENT_QUOTES, 'UTF-8'); ?>" class="checkout-kurir-form">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="aksi" value="pilih_kurir">
                                            <input type="hidden" name="kurir" value="<?php echo htmlspecialchars($o['kurir'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="layanan" value="<?php echo htmlspecialchars($o['service'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="ongkir" value="<?php echo (int) $o['cost']; ?>">
                                            <button type="submit" class="checkout-kurir-link">
                                                <span class="checkout-kurir-nama"><?php echo htmlspecialchars(strtoupper($o['kurir']) . ' · ' . $o['service'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="checkout-kurir-meta">
                                                    <?php echo htmlspecialchars($o['desc'], ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php $etd_tampil = checkout_format_etd($o['etd']); ?>
                                                    <?php if ($etd_tampil !== ''): ?> · <?php echo htmlspecialchars($etd_tampil, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                                </span>
                                                <span class="checkout-kurir-harga"><?php echo htmlspecialchars(katalog_format_rupiah($o['cost']), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php else: ?>
                    <?php if ($wilayah_butuh_pilih && $hasil_cari !== null && $hasil_cari['ok']): ?>
                        <p class="checkout-auto-cari">
                            Pilih wilayah yang paling sesuai alamat di atas. Yang paling cocok ditandai <strong>Rekomendasi</strong>.
                        </p>
                    <?php elseif ($cari_dari_profil && ($hasil_cari === null || !$hasil_cari['ok'])): ?>
                        <p class="checkout-auto-cari checkout-auto-cari--peringatan">
                            Wilayah dari profil belum ketemu otomatis. Ketik <strong>nama kota</strong> terdekat (mis. Batusangkar, Padang, Jakarta Utara).
                        </p>
                    <?php endif; ?>
                    <form method="get" class="checkout-cari-form">
                        <label for="cari" class="visually-hidden">Cari kota tujuan</label>
                        <input type="search" id="cari" name="cari" value="<?php echo htmlspecialchars($cari, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Cari nama kota / kecamatan tujuan"<?php echo ($wilayah_butuh_pilih && $hasil_cari !== null && $hasil_cari['ok']) ? '' : ' required'; ?><?php echo $wilayah_butuh_pilih ? '' : ' autofocus'; ?>>
                        <button type="submit" class="tombol-page-utama">Cari</button>
                    </form>

                    <?php if ($hasil_cari !== null): ?>
                        <?php if (!$hasil_cari['ok']): ?>
                            <p class="checkout-error-baris">Gagal cari destinasi: <?php echo htmlspecialchars((string) ($hasil_cari['error'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php else: ?>
                            <?php $rows = is_array($hasil_cari['data']) ? $hasil_cari['data'] : []; ?>
                            <?php if ($rows === []): ?>
                                <p class="checkout-kosong-cari">Tidak ada hasil untuk "<?php echo htmlspecialchars($cari, ENT_QUOTES, 'UTF-8'); ?>". Coba kata kunci lain.</p>
                            <?php else: ?>
                                <ul class="checkout-destinasi-list">
                                    <?php foreach ($rows as $r):
                                        if (!is_array($r)) continue;
                                        $rid = rajaongkir_normalisasi_kode_desa((string) ($r['id'] ?? ''));
                                        if ($rid === '') continue;
                                        $label = rajaongkir_baris_label_tampilan($r);
                                        if ($label === '') continue;
                                        $skor_baris = rajaongkir_skor_cocok_lokasi($r, $profil);
                                        $rekomendasi = $skor_baris >= $skor_rekomendasi_min;
                                    ?>
                                        <li>
                                            <form method="post" action="<?php echo htmlspecialchars($u_self, ENT_QUOTES, 'UTF-8'); ?>" class="checkout-destinasi-form">
                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="aksi" value="pilih_destinasi">
                                                <input type="hidden" name="destination_id" value="<?php echo htmlspecialchars($rid, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="destination_label" value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="checkout-destinasi-baris<?php echo $rekomendasi ? ' checkout-destinasi-baris--match' : ''; ?>">
                                                    <span class="checkout-destinasi-isi">
                                                        <strong><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <?php if ($rekomendasi): ?>
                                                            <small class="checkout-destinasi-zip checkout-destinasi-zip--match">Rekomendasi · sesuai alamat profil</small>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="checkout-destinasi-pilih">Pilih →</span>
                                                </button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

        </div>

        <aside class="checkout-kolom-ringkas">
            <div class="checkout-kartu checkout-ringkas">
                <h2 class="checkout-kartu__judul">Ringkasan</h2>
                <div class="checkout-ringkas-baris">
                    <span>Subtotal produk</span>
                    <strong><?php echo htmlspecialchars(katalog_format_rupiah($harga_produk), ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="checkout-ringkas-baris">
                    <span>Ongkos kirim</span>
                    <?php if ($kurir_pilih !== '' && $layanan_pilih !== ''): ?>
                        <strong><?php echo htmlspecialchars(katalog_format_rupiah($ongkir_pilih), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <?php else: ?>
                        <em class="checkout-ringkas-redup">Pilih kurir dulu</em>
                    <?php endif; ?>
                </div>
                <?php if ($kurir_pilih !== '' && $layanan_pilih !== ''): ?>
                    <p class="checkout-ringkas-kurir"><?php echo htmlspecialchars(strtoupper($kurir_pilih) . ' · ' . $layanan_pilih, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <div class="checkout-ringkas-baris checkout-ringkas-baris--total">
                    <span>Total</span>
                    <strong><?php echo htmlspecialchars(katalog_format_rupiah($total_final), ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>

                <?php if ($kurir_pilih !== '' && $layanan_pilih !== '' && $destination_kode_pilih !== ''): ?>
                    <form method="post" action="<?php echo htmlspecialchars($u_self, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="aksi" value="konfirmasi">
                        <button type="submit" class="tombol-page-utama checkout-tombol-konfirmasi">Konfirmasi pesanan</button>
                    </form>
                    <p class="checkout-catatan-bayar">Setelah pesanan dibuat, lanjutkan pembayaran di halaman detail pesanan melalui <strong>Pakasir</strong> (QRIS, VA, PayPal).</p>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <?php endif; ?>
</main>

</body>
</html>
