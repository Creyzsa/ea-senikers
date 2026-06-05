<?php

declare(strict_types=1);

/**
 * Checkout pembeli — flow lengkap dengan RajaOngkir.
 *
 * Step:
 *   1. Pembeli klik "Beli" di detail produk (POST: id_produk, ukuran).
 *      Sistem simpan ke session, redirect ke GET.
 *   2. Tampilkan ringkasan produk + alamat pembeli (dari profil).
 *      Pembeli cari kecamatan tujuan (search RajaOngkir).
 *   3. Pembeli pilih destinasi → fetch daftar kurir+layanan+ongkir.
 *   4. Pembeli pilih layanan kurir → tampil review final.
 *   5. Pembeli klik "Konfirmasi pesanan" (POST + CSRF) → create order
 *      di DB, redirect ke halaman detail pesanan.
 */

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/keranjang_sesi.php';
require_once __DIR__ . '/../../includes/checkout_sesi.php';
require_once __DIR__ . '/../../includes/url_bantu.php';
require_once __DIR__ . '/../../includes/repositori/profil_pembeli_repositori.php';
require_once __DIR__ . '/../../includes/repositori/pesanan_repositori.php';
require_once __DIR__ . '/../../includes/integrasi/rajaongkir.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

if (!isset($_SESSION['csrf_checkout']) || !is_string($_SESSION['csrf_checkout'])) {
    $_SESSION['csrf_checkout'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_checkout'];

$id_pengguna = ambil_id_pengguna_efektif();
$u_self = aplikasi_url('checkout');
$u_katalog = aplikasi_url('produk');
$u_keranjang = aplikasi_url('keranjang');
$u_akun = aplikasi_url('akun');
$u_pengaturan_toko = aplikasi_url('admin/pengaturan_admin.php');

// =========================================================================
// 0. ENTRY: kalau ada POST id_produk + ukuran dari detail produk,
//    simpan ke session lalu redirect (post-redirect-get).
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_produk'], $_POST['ukuran']) && !isset($_POST['aksi'])) {
    $id_beli = trim((string) $_POST['id_produk']);
    $uk_beli = trim((string) $_POST['ukuran']);
    if ($id_beli !== '' && $uk_beli !== '') {
        checkout_set_sesi_baris([
            ['id_produk' => $id_beli, 'ukuran' => $uk_beli, 'qty' => 1],
        ]);
    }
    header('Location: ' . $u_self);
    exit;
}

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
        $did = (int) ($_POST['destination_id'] ?? 0);
        $dlabel = trim((string) ($_POST['destination_label'] ?? ''));
        if ($did > 0 && $dlabel !== '') {
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
        $_SESSION['checkout_destinasi_auto']
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

    $sesi = $_SESSION['checkout_pesanan'] ?? null;
    $baris_konfirm = checkout_baris_dari_sesi(is_array($sesi) ? $sesi : null);
    if ($baris_konfirm === []) {
        header('Location: ' . $u_keranjang);
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
    $destination_id = is_array($sesi_dest) ? (int) ($sesi_dest['id'] ?? 0) : 0;
    $destination_label = is_array($sesi_dest) ? trim((string) ($sesi_dest['label'] ?? '')) : '';
    $kurir = is_array($sesi_kur) ? trim((string) ($sesi_kur['kurir'] ?? '')) : '';
    $layanan = is_array($sesi_kur) ? trim((string) ($sesi_kur['layanan'] ?? '')) : '';
    $ongkir = is_array($sesi_kur) ? (int) ($sesi_kur['ongkir'] ?? 0) : 0;

    if ($destination_id <= 0 || $kurir === '' || $layanan === '' || $ongkir < 0) {
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
            'destination_id' => $destination_id,
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
    keranjang_kosongkan();
    $_SESSION['flash_pesanan_baru'] = 'Pesanan #' . $order_id . ' berhasil dibuat. Selanjutnya menunggu pembayaran.';
    header('Location: ' . aplikasi_url('detail-pesanan?id=' . $order_id));
    exit;
}

// =========================================================================
// 2. GET render: tampilkan checkout berdasar state session + query string.
// =========================================================================
$sesi_pesanan = $_SESSION['checkout_pesanan'] ?? null;
$baris_checkout = checkout_baris_dari_sesi(is_array($sesi_pesanan) ? $sesi_pesanan : null);
if ($baris_checkout === []) {
    header('Location: ' . $u_keranjang);
    exit;
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

$origin_id_toko = rajaongkir_kota_asal_id();
$api_key_toko = rajaongkir_api_key();

$flash_error = $_SESSION['flash_checkout_error'] ?? null;
unset($_SESSION['flash_checkout_error']);

// State dari SESSION (destinasi + kurir) dan GET (cari saja)
$cari = trim((string) ($_GET['cari'] ?? ''));

$sesi_destinasi = $_SESSION['checkout_destinasi'] ?? null;
$destination_id_pilih = is_array($sesi_destinasi) ? (int) ($sesi_destinasi['id'] ?? 0) : 0;
$destination_label_pilih = is_array($sesi_destinasi) ? trim((string) ($sesi_destinasi['label'] ?? '')) : '';

$sesi_kurir = $_SESSION['checkout_kurir'] ?? null;
$kurir_pilih = is_array($sesi_kurir) ? trim((string) ($sesi_kurir['kurir'] ?? '')) : '';
$layanan_pilih = is_array($sesi_kurir) ? trim((string) ($sesi_kurir['layanan'] ?? '')) : '';
$ongkir_pilih = is_array($sesi_kurir) ? (int) ($sesi_kurir['ongkir'] ?? 0) : 0;

// Auto-search: kalau pembeli belum apa-apa & profil ada kecamatan/kota,
// otomatis cari pakai data profil sebagai default. Hemat 1 langkah ngetik.
$cari_otomatis = false;
if ($cari === '' && $destination_id_pilih === 0 && $profil_lengkap) {
    $kecamatan_profil = trim($profil['kecamatan']);
    $kota_profil = trim($profil['kota']);
    if ($kecamatan_profil !== '') {
        $cari = $kecamatan_profil;
        $cari_otomatis = true;
    } elseif ($kota_profil !== '') {
        $cari = $kota_profil;
        $cari_otomatis = true;
    }
}

$hasil_cari = null;
$hasil_ongkir = null;

if ($profil_lengkap && $api_key_toko !== '' && $origin_id_toko > 0) {
    if ($cari !== '') {
        $hasil_cari = rajaongkir_cari_destinasi($cari, 30);
    }

    // Auto-pick HANYA kalau kode pos profil EXACT MATCH dengan kode pos
    // salah satu hasil pencarian. Kalau tidak match, pembeli pilih manual
    // dari daftar (di-render normal di bawah) — supaya tidak salah anter.
    // Flag checkout_destinasi_auto_attempted dipasang sekali agar tidak
    // looping auto-pick attempt di setiap render.
    $kode_pos_profil = trim((string) ($profil['kode_pos'] ?? ''));
    $auto_no_match = false;
    if ($cari_otomatis
        && $destination_id_pilih === 0
        && empty($_SESSION['checkout_destinasi_auto'])
        && $hasil_cari !== null
        && $hasil_cari['ok']
        && is_array($hasil_cari['data'])
        && $hasil_cari['data'] !== []
    ) {
        $top = null;
        if ($kode_pos_profil !== '') {
            foreach ($hasil_cari['data'] as $row) {
                if (!is_array($row)) continue;
                $z = trim((string) ($row['zip_code'] ?? $row['postal_code'] ?? ''));
                if ($z === $kode_pos_profil) {
                    $top = $row;
                    break;
                }
            }
        }

        if ($top !== null) {
            $top_id = (int) ($top['id'] ?? 0);
            $top_label = (string) ($top['label'] ?? '');
            if ($top_label === '') {
                $parts = array_filter([
                    (string) ($top['subdistrict_name'] ?? ''),
                    (string) ($top['district_name'] ?? ''),
                    (string) ($top['city_name'] ?? ''),
                    (string) ($top['province_name'] ?? ''),
                ]);
                $top_label = implode(', ', $parts);
            }
            if ($top_id > 0 && $top_label !== '') {
                $_SESSION['checkout_destinasi'] = ['id' => $top_id, 'label' => $top_label];
                $_SESSION['checkout_destinasi_auto'] = true;
                header('Location: ' . $u_self);
                exit;
            }
        } else {
            // Tidak ada hasil yang kode posnya match — minta pembeli pilih manual
            $auto_no_match = true;
        }
    }

    if ($destination_id_pilih > 0) {
        $hasil_ongkir = rajaongkir_cek_ongkir($origin_id_toko, $destination_id_pilih, $berat_produk, 'jne:pos:tiki');
    }
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
    <?php elseif ($api_key_toko === '' || $origin_id_toko <= 0): ?>
        <div class="checkout-alert checkout-alert--peringatan">
            <strong>Toko belum siap menerima pesanan.</strong>
            Admin perlu mengisi API key &amp; lokasi asal RajaOngkir terlebih dahulu.
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
                    <?php if ($destination_id_pilih > 0): ?>Kurir &amp; layanan
                    <?php else: ?>Cari kecamatan tujuan
                    <?php endif; ?>
                </h2>

                <?php if ($destination_id_pilih > 0):
                    $auto_picked = !empty($_SESSION['checkout_destinasi_auto']);
                ?>
                    <p class="checkout-destinasi-aktif">
                        <span><?php echo $auto_picked ? 'Tujuan otomatis dari profil:' : 'Tujuan terpilih:'; ?></span>
                        <strong><?php echo htmlspecialchars($destination_label_pilih, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <form method="post" action="<?php echo htmlspecialchars($u_self, ENT_QUOTES, 'UTF-8'); ?>" class="checkout-inline-form">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="aksi" value="reset_destinasi">
                            <button type="submit" class="checkout-tombol-link">Ganti</button>
                        </form>
                    </p>

                    <?php if ($hasil_ongkir === null || !$hasil_ongkir['ok']): ?>
                        <p class="checkout-error-baris">Gagal hitung ongkir: <?php echo htmlspecialchars((string) ($hasil_ongkir['error'] ?? 'koneksi RajaOngkir gagal'), ENT_QUOTES, 'UTF-8'); ?>. Coba ulang.</p>
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
                    <?php if ($auto_no_match): ?>
                        <p class="checkout-auto-cari checkout-auto-cari--peringatan">
                            Kode pos profil kamu (<strong><?php echo htmlspecialchars($kode_pos_profil, ENT_QUOTES, 'UTF-8'); ?></strong>) tidak persis cocok dengan kelurahan tersedia di RajaOngkir.
                            Pilih kelurahan terdekat di bawah agar ongkir akurat.
                        </p>
                    <?php elseif ($cari_otomatis && $hasil_cari !== null && $hasil_cari['ok'] && is_array($hasil_cari['data']) && $hasil_cari['data'] !== []): ?>
                        <p class="checkout-auto-cari">Dicari otomatis dari alamat profil: <strong><?php echo htmlspecialchars($cari, ENT_QUOTES, 'UTF-8'); ?></strong>. Pilih salah satu di bawah, atau cari ulang.</p>
                    <?php endif; ?>
                    <form method="get" class="checkout-cari-form">
                        <label for="cari" class="visually-hidden">Cari</label>
                        <input type="search" id="cari" name="cari" value="<?php echo htmlspecialchars($cari, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ketik kecamatan / kota tujuan, mis. Jakarta Pusat" required<?php echo $cari_otomatis ? '' : ' autofocus'; ?>>
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
                                        $rid = (int) ($r['id'] ?? 0);
                                        if ($rid <= 0) continue;
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
                                        $zip_row = trim((string) ($r['zip_code'] ?? $r['postal_code'] ?? ''));
                                        $zip_match = ($kode_pos_profil !== '' && $zip_row === $kode_pos_profil);
                                    ?>
                                        <li>
                                            <form method="post" action="<?php echo htmlspecialchars($u_self, ENT_QUOTES, 'UTF-8'); ?>" class="checkout-destinasi-form">
                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="aksi" value="pilih_destinasi">
                                                <input type="hidden" name="destination_id" value="<?php echo (int) $rid; ?>">
                                                <input type="hidden" name="destination_label" value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="checkout-destinasi-baris<?php echo $zip_match ? ' checkout-destinasi-baris--match' : ''; ?>">
                                                    <span class="checkout-destinasi-isi">
                                                        <strong><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <?php if ($zip_row !== ''): ?>
                                                            <small class="checkout-destinasi-zip<?php echo $zip_match ? ' checkout-destinasi-zip--match' : ''; ?>">
                                                                Kode pos: <?php echo htmlspecialchars($zip_row, ENT_QUOTES, 'UTF-8'); ?>
                                                                <?php if ($zip_match): ?> ✓ sama dengan profil<?php endif; ?>
                                                            </small>
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

                <?php if ($kurir_pilih !== '' && $layanan_pilih !== '' && $destination_id_pilih > 0): ?>
                    <form method="post" action="<?php echo htmlspecialchars($u_self, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="aksi" value="konfirmasi">
                        <button type="submit" class="tombol-page-utama checkout-tombol-konfirmasi">Konfirmasi pesanan</button>
                    </form>
                    <p class="checkout-catatan-bayar">Pembayaran akan dilakukan via Tripay (segera aktif). Sementara hubungi WA toko setelah pesanan dibuat.</p>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <?php endif; ?>
</main>

</body>
</html>
