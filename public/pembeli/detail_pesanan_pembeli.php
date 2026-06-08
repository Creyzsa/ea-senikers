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

$wa_pesanan = '';
foreach ((array) ($kontak_toko['wa'] ?? []) as $wa) {
    $e164 = preg_replace('/\D+/', '', (string) ($wa['e164'] ?? ''));
    if ($e164 !== '') {
        $pesan_wa = "Halo EA SENIKERS, saya mau menanyakan pesanan #{$order_id} (status: {$labelStatus}). Terima kasih.";
        $wa_pesanan = 'https://wa.me/' . $e164 . '?text=' . rawurlencode($pesan_wa);
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
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="pesanan-wrap" id="utama">
    <a class="pesanan-detail-kembali" href="<?php echo htmlspecialchars($u_list, ENT_QUOTES, 'UTF-8'); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Kembali ke pesanan
    </a>

    <h1 class="pesanan-judul">Detail pesanan #<?php echo (int) $order_id; ?></h1>
    <p class="pesanan-sub">
        <?php echo htmlspecialchars(pesanan_format_tanggal_detail(isset($order['created_at']) ? (string) $order['created_at'] : null), ENT_QUOTES, 'UTF-8'); ?>
        · <span class="<?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($labelStatus, ENT_QUOTES, 'UTF-8'); ?></span>
    </p>

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
        <div class="pesanan-panel pesanan-panel--status">
            <h2 class="pesanan-panel__judul">Status pesanan</h2>
            <ol class="pesanan-stepper" aria-label="Progress pesanan">
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
                    <li class="pesanan-stepper__langkah pesanan-stepper__langkah--<?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $aria_current; ?>>
                        <div class="pesanan-stepper__marker" aria-hidden="true">
                            <?php if ($state === 'selesai'): ?>
                                <svg class="pesanan-stepper__ikon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <?php else: ?>
                                <span class="pesanan-stepper__nomor"><?php echo (int) ($i + 1); ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="pesanan-stepper__label"><?php echo htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endif; ?>

    <div class="pesanan-detail-layout">
        <section class="pesanan-detail-utama" aria-labelledby="judul-produk-pesanan">
            <div class="pesanan-panel">
                <h2 id="judul-produk-pesanan" class="pesanan-panel__judul">Produk</h2>
                <?php if (!is_array($items) || $items === []): ?>
                    <p class="pesanan-panel__kosong">Tidak ada baris item (data tidak lengkap).</p>
                <?php else: ?>
                    <ul class="pesanan-produk-list">
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
                            <li class="pesanan-item-baris">
                                <img class="pesanan-item-baris__gambar" src="<?php echo htmlspecialchars($gurl, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="84" height="84" loading="lazy">
                                <div class="pesanan-item-baris__info">
                                    <p class="pesanan-item-baris__nama"><?php echo htmlspecialchars($pn, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="pesanan-item-baris__meta">
                                        Ukuran <strong><?php echo htmlspecialchars($sz !== '' ? $sz : '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        · Qty <strong><?php echo (int) $qty; ?></strong>
                                    </p>
                                    <?php if (in_array($status, ['shipped', 'completed'])): ?>
                                        <?php $pid = (string) ($it['id_produk'] ?? ''); ?>
                                        <?php if ($pid !== ''): ?>
                                            <a class="pesanan-item-baris__link" href="<?php echo htmlspecialchars(aplikasi_url('detail-produk?id=' . rawurlencode($pid)), ENT_QUOTES, 'UTF-8'); ?>">Beri ulasan</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <p class="pesanan-item-baris__harga"><?php echo htmlspecialchars(katalog_format_rupiah($sub_baris), ENT_QUOTES, 'UTF-8'); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <aside class="pesanan-detail-samping" aria-label="Ringkasan pesanan">
            <?php if ($tampil_bayar): ?>
            <div class="pesanan-panel pesanan-panel--bayar">
                <h2 class="pesanan-panel__judul">Pembayaran Pakasir</h2>
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
            <div class="pesanan-panel pesanan-panel--peringatan" role="status">
                <h2 class="pesanan-panel__judul">Pembayaran</h2>
                <p class="pesanan-bayar-teks pesanan-bayar-teks--padat">Pembayaran online belum aktif. Hubungi toko via WhatsApp untuk konfirmasi transfer.</p>
            </div>
            <?php endif; ?>

            <div class="pesanan-panel pesanan-panel--ringkasan">
                <h2 class="pesanan-panel__judul">Ringkasan</h2>
                <dl class="pesanan-ringkasan-dl">
                    <div class="pesanan-ringkasan-baris pesanan-ringkasan-baris--stack">
                        <dt>Alamat pengiriman</dt>
                        <dd>
                            <?php if ($alamat !== ''): ?>
                                <?php echo nl2br(htmlspecialchars($alamat, ENT_QUOTES, 'UTF-8')); ?>
                            <?php else: ?>
                                <em class="pesanan-ringkasan-kosong">Belum diisi</em>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php if ($kurir !== '' || $layanan !== ''): ?>
                    <div class="pesanan-ringkasan-baris">
                        <dt>Kurir</dt>
                        <dd><?php echo htmlspecialchars(strtoupper($kurir) . ($layanan !== '' ? ' · ' . $layanan : ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($nomor_resi !== ''): ?>
                    <div class="pesanan-ringkasan-baris">
                        <dt>Nomor resi</dt>
                        <dd><strong><?php echo htmlspecialchars($nomor_resi, ENT_QUOTES, 'UTF-8'); ?></strong></dd>
                    </div>
                    <?php endif; ?>
                    <div class="pesanan-ringkasan-baris">
                        <dt>Metode pembayaran</dt>
                        <dd>
                            <?php if ($bayar !== ''): ?>
                                <?php echo htmlspecialchars($bayar, ENT_QUOTES, 'UTF-8'); ?>
                            <?php elseif ($pakasir_aktif && $status === 'pending'): ?>
                                <em class="pesanan-ringkasan-kosong">Belum dibayar (Pakasir)</em>
                            <?php else: ?>
                                <em class="pesanan-ringkasan-kosong">—</em>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="pesanan-ringkasan-baris">
                        <dt>Subtotal produk</dt>
                        <dd><?php echo htmlspecialchars(katalog_format_rupiah($subtotal_produk), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <div class="pesanan-ringkasan-baris">
                        <dt>Ongkos kirim</dt>
                        <dd><?php echo htmlspecialchars(katalog_format_rupiah($ongkir), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                </dl>
                <div class="pesanan-ringkasan-total">
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
        </aside>
    </div>
</main>

</body>
</html>
