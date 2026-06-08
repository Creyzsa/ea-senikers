<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/pesanan_repositori.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/integrasi/pakasir.php';
require_once __DIR__ . '/../../includes/url_bantu.php';
$kontak_toko = require __DIR__ . '/../../includes/konfigurasi/kontak_toko.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$id_pengguna = ambil_id_pengguna_efektif();
$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$order = null;
try {
    if ($order_id <= 0 || !pesanan_cek_tabel_ada()) {
        header('Location: ' . aplikasi_url('pesanan'));
        exit;
    }
    $order = pesanan_ambil_detail_untuk_user($order_id, $id_pengguna);
} catch (Throwable $e) {
    error_log('[DB graceful detail pesanan] ' . $e->getMessage());
}
if ($order === null) {
    header('Location: ' . aplikasi_url('pesanan'));
    exit;
}

$total_awal = (int) ($order['total_price'] ?? 0);
$status_awal = (string) ($order['status'] ?? 'pending');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['aksi'] ?? '') === 'bayar_pakasir') {
    $metode_bayar = strtolower(trim((string) ($_POST['metode_pakasir'] ?? '')));
    if ($metode_bayar === '') {
        $metode_bayar = pakasir_konfigurasi()['metode_default'];
    }
    $hasil_bayar = pakasir_buat_pembayaran($metode_bayar, $order_id, $total_awal);
    if (!$hasil_bayar['ok']) {
        $_SESSION['flash_pesanan_bayar_error'] = (string) ($hasil_bayar['error'] ?? 'Gagal membuat pembayaran.');
        header('Location: ' . aplikasi_url('detail-pesanan?id=' . $order_id));
        exit;
    }
    $label = 'Pakasir · ' . pakasir_label_metode((string) ($hasil_bayar['payment_method'] ?? $metode_bayar));
    pesanan_perbarui_metode_bayar($order_id, $label);
    $url_bayar = trim((string) ($hasil_bayar['payment_url'] ?? ''));
    if ($url_bayar === '') {
        $_SESSION['flash_pesanan_bayar_error'] = 'URL pembayaran tidak tersedia.';
        header('Location: ' . aplikasi_url('detail-pesanan?id=' . $order_id));
        exit;
    }
    header('Location: ' . $url_bayar);
    exit;
}

if ($status_awal === 'pending' && pakasir_siap() && $total_awal >= 500) {
    $sinkron = pakasir_sinkronkan_pesanan_db($order_id, $total_awal, $status_awal);
    if ($sinkron === 'paid') {
        $order = pesanan_ambil_detail_untuk_user($order_id, $id_pengguna);
        if ($order === null) {
            header('Location: ' . aplikasi_url('pesanan'));
            exit;
        }
    }
}

$bilah_pembeli_aktif = 'pesanan';

$labels = pesanan_status_label_id();
$badgeClass = pesanan_status_kelas_badge();
$langkah = pesanan_langkah_progress();
$status = (string) ($order['status'] ?? 'pending');
$idxAktif = pesanan_indeks_langkah_aktif($status);
$batal = $status === 'cancelled';

function pesanan_format_tanggal_detail(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($iso);

        return $dt->format('d M Y · H:i');
    } catch (Throwable $e) {
        return '—';
    }
}

function pesanan_format_tanggal_panjang(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($iso);
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $n = (int) $dt->format('n');

        return $dt->format('d') . ' ' . ($bulan[$n] ?? $dt->format('M')) . ' ' . $dt->format('Y');
    } catch (Throwable $e) {
        return '—';
    }
}

/**
 * @return array{penerima:string,telepon:string,jalan:string,wilayah:string,destinasi:string}
 */
function pesanan_parse_alamat_kirim(string $raw): array
{
    $hasil = [
        'penerima' => '',
        'telepon' => '',
        'jalan' => '',
        'wilayah' => '',
        'destinasi' => '',
    ];
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
    if ($lines === []) {
        return $hasil;
    }
    if (count($lines) === 1 && !preg_match('/\([^)]+\)/', $lines[0])) {
        $hasil['jalan'] = $lines[0];

        return $hasil;
    }
    if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/u', $lines[0], $m)) {
        $hasil['penerima'] = trim($m[1]);
        $hasil['telepon'] = trim($m[2]);
        array_shift($lines);
    } else {
        $hasil['penerima'] = $lines[0];
        array_shift($lines);
    }
    $last = $lines !== [] ? $lines[count($lines) - 1] : '';
    if (is_string($last) && str_starts_with($last, 'Destinasi:')) {
        $hasil['destinasi'] = trim(substr($last, strlen('Destinasi:')));
        array_pop($lines);
    }
    if (count($lines) >= 2) {
        $hasil['jalan'] = $lines[0];
        $hasil['wilayah'] = implode("\n", array_slice($lines, 1));
    } elseif (count($lines) === 1) {
        $hasil['jalan'] = $lines[0];
    }

    return $hasil;
}

function pesanan_badge_modern_kelas(string $status): string
{
    $map = [
        'pending' => 'pd-badge pd-badge--warning',
        'paid' => 'pd-badge pd-badge--success',
        'processed' => 'pd-badge pd-badge--info',
        'shipped' => 'pd-badge pd-badge--info',
        'completed' => 'pd-badge pd-badge--success',
        'cancelled' => 'pd-badge pd-badge--danger',
    ];

    return $map[$status] ?? 'pd-badge pd-badge--neutral';
}

/**
 * Timeline UI dari status + created_at (estimasi menit antar langkah jika belum ada updated_at).
 *
 * @return list<array{waktu:string,label:string}>
 */
function pesanan_timeline_aktivitas(?string $created_at, int $idxAktif, bool $batal): array
{
    if ($batal || $created_at === null || $created_at === '' || $idxAktif < 0) {
        return [];
    }
    try {
        $base = new DateTimeImmutable($created_at);
    } catch (Throwable $e) {
        return [];
    }
    $langkah = [
        ['min_idx' => 0, 'label' => 'Pesanan dibuat'],
        ['min_idx' => 1, 'label' => 'Pembayaran berhasil'],
        ['min_idx' => 2, 'label' => 'Pesanan diproses'],
        ['min_idx' => 3, 'label' => 'Pesanan dikirim'],
        ['min_idx' => 4, 'label' => 'Pesanan selesai'],
    ];
    $events = [];
    foreach ($langkah as $i => $step) {
        if ($idxAktif < $step['min_idx']) {
            break;
        }
        $dt = $base->modify('+' . $i . ' minutes');
        $events[] = [
            'waktu' => $dt->format('d M Y H:i'),
            'label' => $step['label'],
        ];
    }

    return $events;
}

$badge = $badgeClass[$status] ?? 'pesanan-badge pesanan-badge--kuning';
$labelStatus = $labels[$status] ?? $status;
$items = $order['items'] ?? [];
$total = (int) ($order['total_price'] ?? 0);
$alamat = trim((string) ($order['shipping_address'] ?? ''));
$bayar = trim((string) ($order['payment_method'] ?? ''));
$kurir = trim((string) ($order['kurir'] ?? ''));
$layanan = trim((string) ($order['layanan'] ?? ''));
$ongkir = (int) ($order['ongkir'] ?? 0);
$nomor_resi = trim((string) ($order['nomor_resi'] ?? ''));
$subtotal_produk = max(0, $total - $ongkir);
$u_list = aplikasi_url('pesanan');

$flash_baru = $_SESSION['flash_pesanan_baru'] ?? null;
unset($_SESSION['flash_pesanan_baru']);
$flash_bayar_error = $_SESSION['flash_pesanan_bayar_error'] ?? null;
unset($_SESSION['flash_pesanan_bayar_error']);
$flash_bayar_kembali = isset($_GET['bayar']) && (string) $_GET['bayar'] === 'kembali';
$pakasir_aktif = pakasir_siap();
$pakasir_metode_opsi = pakasir_daftar_metode();
$pakasir_metode_default = pakasir_konfigurasi()['metode_default'];
$tampil_bayar = $status === 'pending' && $pakasir_aktif && !$batal;
$tampil_bayar_peringatan = $status === 'pending' && !$pakasir_aktif && !$batal;
$created_at_iso = isset($order['created_at']) ? (string) $order['created_at'] : null;
$alamat_parsed = pesanan_parse_alamat_kirim($alamat);
$timeline_events = pesanan_timeline_aktivitas($created_at_iso, $idxAktif, $batal);
$badge_modern = pesanan_badge_modern_kelas($status);
$tanggal_pesanan = pesanan_format_tanggal_panjang($created_at_iso);
$kurir_tampil = trim(strtoupper($kurir) . ($layanan !== '' ? ' - ' . strtoupper($layanan) : ''));

$wa_pesanan = '';
$wa_konfirmasi_bayar = '';
foreach ((array) ($kontak_toko['wa'] ?? []) as $wa) {
    $e164 = preg_replace('/\D+/', '', (string) ($wa['e164'] ?? ''));
    if ($e164 !== '') {
        $pesan_wa = "Halo EA SENIKERS, saya mau menanyakan pesanan #{$order_id} (status: {$labelStatus}). Terima kasih.";
        $wa_pesanan = 'https://wa.me/' . $e164 . '?text=' . rawurlencode($pesan_wa);
        $pesan_bayar = 'Halo EA SENIKERS, saya ingin konfirmasi pembayaran pesanan #' . $order_id
            . ' sebesar ' . katalog_format_rupiah($total) . '. Terima kasih.';
        $wa_konfirmasi_bayar = 'https://wa.me/' . $e164 . '?text=' . rawurlencode($pesan_bayar);
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail pesanan #<?php echo (int) $order_id; ?> — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/pesanan-pembeli.css">
    <link rel="stylesheet" href="../assets/css/detail-pesanan.css">
</head>
<body class="halaman-toko halaman-detail-pesanan">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="pesanan-wrap pd-wrap" id="utama">
    <a class="pesanan-detail-kembali" href="<?php echo htmlspecialchars($u_list, ENT_QUOTES, 'UTF-8'); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Kembali ke pesanan
    </a>

    <header class="pd-header-card" aria-label="Informasi pesanan">
        <div>
            <p class="pd-header-card__label">Pesanan</p>
            <h1 class="pd-header-card__nomor">#<?php echo (int) $order_id; ?></h1>
        </div>
        <div class="pd-header-card__meta">
            <div class="pd-header-card__meta-item">
                <span>Tanggal</span>
                <strong><?php echo htmlspecialchars($tanggal_pesanan, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="pd-header-card__meta-item">
                <span>Status</span>
                <span class="<?php echo htmlspecialchars($badge_modern, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper($labelStatus), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </header>

    <?php if (is_string($flash_baru) && $flash_baru !== ''): ?>
        <div class="pesanan-flash-sukses" role="status"><?php echo htmlspecialchars($flash_baru, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (is_string($flash_bayar_error) && $flash_bayar_error !== ''): ?>
        <div class="pesanan-peringatan" role="alert"><?php echo htmlspecialchars($flash_bayar_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($flash_bayar_kembali && $status === 'pending'): ?>
        <div class="pesanan-peringatan" role="status">Pembayaran belum terkonfirmasi. Jika sudah bayar, tunggu beberapa saat atau klik <strong>Bayar sekarang</strong> lagi.</div>
    <?php endif; ?>
    <?php if ($flash_bayar_kembali && $status === 'paid'): ?>
        <div class="pesanan-flash-sukses" role="status">Pembayaran berhasil dikonfirmasi. Pesanan sedang diproses.</div>
    <?php endif; ?>

    <?php if ($batal): ?>
        <div class="pesanan-batal-banner" role="status">Pesanan ini dibatalkan.</div>
    <?php else: ?>
        <?php
        $jumlah_langkah = count($langkah);
        $stepper_progress = $jumlah_langkah > 1
            ? max(0, min(100, (int) round(($idxAktif / ($jumlah_langkah - 1)) * 100)))
            : 0;
        ?>
        <div class="pd-card pd-card--stepper">
            <h2 class="pd-card__judul">Status pesanan</h2>
            <div class="pd-stepper-shell">
                <div class="pd-stepper-track" aria-hidden="true">
                    <span class="pd-stepper-track__fill" style="width: <?php echo (int) $stepper_progress; ?>%;"></span>
                </div>
                <ol class="pd-stepper" aria-label="Progress pesanan">
                <?php foreach ($langkah as $i => $step): ?>
                    <?php
                    $state = 'belum';
                    if ($idxAktif >= 0 && $i < $idxAktif) {
                        $state = 'selesai';
                    } elseif ($idxAktif >= 0 && $i === $idxAktif) {
                        $state = 'aktif';
                    }
                    $aria_current = $state === 'aktif' ? ' aria-current="step"' : '';
                    ?>
                    <li class="pd-stepper__langkah pd-stepper__langkah--<?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $aria_current; ?>>
                        <div class="pd-stepper__marker" aria-hidden="true">
                            <?php if ($state === 'selesai'): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <?php else: ?>
                                <span class="pd-stepper__nomor"><?php echo (int) ($i + 1); ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="pd-stepper__label"><?php echo htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                <?php endforeach; ?>
                </ol>
            </div>
        </div>
    <?php endif; ?>

    <div class="pesanan-detail-layout">
        <section class="pesanan-detail-utama" aria-labelledby="judul-produk-pesanan">
            <div class="pd-card">
                <h2 id="judul-produk-pesanan" class="pd-card__judul">Produk dipesan</h2>
                <?php if (!is_array($items) || $items === []): ?>
                    <p class="pesanan-panel__kosong">Tidak ada baris item (data tidak lengkap).</p>
                <?php else: ?>
                    <ul class="pd-produk-list">
                        <?php foreach ($items as $it): ?>
                            <?php
                            $it = is_array($it) ? $it : [];
                            $gurl = pesanan_url_gambar_item($it);
                            $pn = (string) ($it['product_name'] ?? '—');
                            $pr = (int) ($it['price'] ?? 0);
                            $sz = trim((string) ($it['size'] ?? ''));
                            $qty = max(1, (int) ($it['quantity'] ?? 1));
                            $sub_baris = $pr * $qty;
                            ?>
                            <li class="pd-produk-item">
                                <img class="pd-produk-item__gambar" src="<?php echo htmlspecialchars($gurl, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="112" height="112" loading="lazy">
                                <div>
                                    <p class="pd-produk-item__nama"><?php echo htmlspecialchars($pn, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="pd-produk-item__badges">
                                        <span class="pd-chip">Size <strong><?php echo htmlspecialchars($sz !== '' ? $sz : '—', ENT_QUOTES, 'UTF-8'); ?></strong></span>
                                        <span class="pd-chip">Qty <strong><?php echo (int) $qty; ?></strong></span>
                                    </div>
                                    <p class="pd-produk-item__harga"><?php echo htmlspecialchars(katalog_format_rupiah($sub_baris), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php if (in_array($status, ['shipped', 'completed'])): ?>
                                        <?php $pid = (string) ($it['id_produk'] ?? ''); ?>
                                        <?php if ($pid !== ''): ?>
                                            <a class="pd-produk-item__ulasan" href="<?php echo htmlspecialchars(aplikasi_url('detail-produk?id=' . rawurlencode($pid)), ENT_QUOTES, 'UTF-8'); ?>">Beri ulasan</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($timeline_events !== []): ?>
            <div class="pd-card pd-card--timeline">
                <h2 class="pd-card__judul">Aktivitas pesanan</h2>
                <ol class="pd-timeline">
                    <?php foreach ($timeline_events as $ev): ?>
                    <li class="pd-timeline__item">
                        <span class="pd-timeline__dot" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <p class="pd-timeline__waktu"><?php echo htmlspecialchars((string) ($ev['waktu'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="pd-timeline__teks"><?php echo htmlspecialchars((string) ($ev['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <p class="pd-timeline__hint">Status berikutnya akan muncul otomatis saat terjadi perubahan.</p>
            </div>
            <?php endif; ?>
        </section>

        <aside class="pesanan-detail-samping" aria-label="Ringkasan pesanan">
            <div class="pd-sidebar-sticky">
            <?php if ($tampil_bayar): ?>
            <div class="pd-card pd-card--bayar">
                <h2 class="pd-card__judul">Pembayaran Pakasir</h2>
                <p class="pesanan-bayar-teks">Total tagihan: <strong><?php echo htmlspecialchars(katalog_format_rupiah($total), ENT_QUOTES, 'UTF-8'); ?></strong> (belum termasuk biaya admin channel).</p>
                <form class="pesanan-bayar-form" method="post" action="<?php echo htmlspecialchars(aplikasi_url('detail-pesanan?id=' . $order_id), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="aksi" value="bayar_pakasir">
                    <label class="pesanan-bayar-label" for="metode-pakasir">Metode pembayaran</label>
                    <select id="metode-pakasir" name="metode_pakasir" class="pesanan-bayar-select">
                        <?php foreach ($pakasir_metode_opsi as $kode => $label_metode): ?>
                            <option value="<?php echo htmlspecialchars($kode, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $kode === $pakasir_metode_default ? ' selected' : ''; ?>><?php echo htmlspecialchars($label_metode, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="tombol-page-utama pesanan-bayar-tombol">Bayar sekarang</button>
                </form>
            </div>
            <?php elseif ($tampil_bayar_peringatan): ?>
            <div class="pd-card pd-card--bayar-manual" role="status">
                <div class="pd-bayar-manual__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2 class="pd-card__judul">Konfirmasi pembayaran</h2>
                <p class="pd-bayar-manual__teks">Pembayaran online (Pakasir) belum aktif di server ini. Silakan transfer manual lalu konfirmasi ke toko.</p>
                <p class="pd-bayar-manual__total">Total transfer: <strong><?php echo htmlspecialchars(katalog_format_rupiah($total), ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <?php if ($wa_konfirmasi_bayar !== ''): ?>
                    <a class="pd-bayar-manual__wa" href="<?php echo htmlspecialchars($wa_konfirmasi_bayar, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/></svg>
                        Konfirmasi via WhatsApp
                    </a>
                <?php else: ?>
                    <p class="pd-bayar-manual__catatan">Hubungi toko untuk detail rekening dan konfirmasi transfer.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="pd-card">
                <h2 class="pd-card__judul">Pengiriman</h2>
                <ul class="pd-pengiriman-list">
                    <?php if ($alamat_parsed['penerima'] !== ''): ?>
                    <li class="pd-pengiriman-item">
                        <span class="pd-pengiriman-item__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                        <div>
                            <p class="pd-pengiriman-item__label">Penerima</p>
                            <p class="pd-pengiriman-item__nilai"><?php echo htmlspecialchars($alamat_parsed['penerima'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </li>
                    <?php endif; ?>
                    <?php if ($alamat_parsed['telepon'] !== ''): ?>
                    <li class="pd-pengiriman-item">
                        <span class="pd-pengiriman-item__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </span>
                        <div>
                            <p class="pd-pengiriman-item__label">Nomor telepon</p>
                            <p class="pd-pengiriman-item__nilai"><?php echo htmlspecialchars($alamat_parsed['telepon'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </li>
                    <?php endif; ?>
                    <?php if ($alamat_parsed['jalan'] !== '' || $alamat_parsed['wilayah'] !== ''): ?>
                    <li class="pd-pengiriman-item">
                        <span class="pd-pengiriman-item__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                        <div>
                            <p class="pd-pengiriman-item__label">Alamat</p>
                            <p class="pd-pengiriman-item__nilai">
                                <?php if ($alamat_parsed['jalan'] !== ''): ?>
                                    <?php echo htmlspecialchars($alamat_parsed['jalan'], ENT_QUOTES, 'UTF-8'); ?><br>
                                <?php endif; ?>
                                <?php if ($alamat_parsed['wilayah'] !== ''): ?>
                                    <?php echo nl2br(htmlspecialchars($alamat_parsed['wilayah'], ENT_QUOTES, 'UTF-8')); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </li>
                    <?php elseif ($alamat === ''): ?>
                    <li class="pd-pengiriman-item">
                        <span class="pd-pengiriman-item__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                        <div>
                            <p class="pd-pengiriman-item__label">Alamat</p>
                            <p class="pd-pengiriman-item__nilai"><em class="pesanan-ringkasan-kosong">Belum diisi</em></p>
                        </div>
                    </li>
                    <?php endif; ?>
                    <?php if ($kurir_tampil !== ''): ?>
                    <li class="pd-pengiriman-item">
                        <span class="pd-pengiriman-item__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m10 0H3m10 0h2l3-3V9a1 1 0 00-1-1h-2"/></svg>
                        </span>
                        <div>
                            <p class="pd-pengiriman-item__label">Kurir</p>
                            <p class="pd-pengiriman-item__nilai"><?php echo htmlspecialchars($kurir_tampil, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </li>
                    <?php endif; ?>
                    <?php if ($nomor_resi !== ''): ?>
                    <li class="pd-pengiriman-item">
                        <span class="pd-pengiriman-item__ikon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </span>
                        <div>
                            <p class="pd-pengiriman-item__label">Nomor resi</p>
                            <p class="pd-pengiriman-item__nilai"><strong><?php echo htmlspecialchars($nomor_resi, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                        </div>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="pd-card">
                <h2 class="pd-card__judul">Ringkasan pembayaran</h2>
                <dl class="pd-harga-list">
                    <div class="pd-harga-baris">
                        <dt>Metode pembayaran</dt>
                        <dd>
                            <?php if ($bayar !== ''): ?>
                                <?php echo htmlspecialchars($bayar, ENT_QUOTES, 'UTF-8'); ?>
                            <?php elseif ($pakasir_aktif && $status === 'pending'): ?>
                                <em class="pesanan-ringkasan-kosong">Belum dibayar</em>
                            <?php else: ?>
                                <em class="pesanan-ringkasan-kosong">—</em>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="pd-harga-baris">
                        <dt>Subtotal produk</dt>
                        <dd><?php echo htmlspecialchars(katalog_format_rupiah($subtotal_produk), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <div class="pd-harga-baris">
                        <dt>Ongkos kirim</dt>
                        <dd><?php echo htmlspecialchars(katalog_format_rupiah($ongkir), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                </dl>
                <div class="pd-harga-total">
                    <span>Total</span>
                    <strong><?php echo htmlspecialchars(katalog_format_rupiah($total), ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
            </div>

            <?php if ($wa_pesanan !== ''): ?>
                <a class="pesanan-bantuan-wa" href="<?php echo htmlspecialchars($wa_pesanan, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/></svg>
                    Hubungi toko via WhatsApp
                </a>
            <?php endif; ?>
            </div>
        </aside>
    </div>
</main>

</body>
</html>
