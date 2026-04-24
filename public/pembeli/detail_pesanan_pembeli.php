<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/pesanan_repositori.php';
require_once __DIR__ . '/../../includes/katalog_produk.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'pembeli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus pembeli.';
    exit;
}

$id_pengguna = ambil_id_pengguna_efektif();
$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($order_id <= 0 || !pesanan_cek_tabel_ada()) {
    header('Location: ' . aplikasi_url('pembeli/pesanan_pembeli.php'));
    exit;
}

$order = pesanan_ambil_detail_untuk_user($order_id, $id_pengguna);
if ($order === null) {
    header('Location: ' . aplikasi_url('pembeli/pesanan_pembeli.php'));
    exit;
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
$u_list = aplikasi_url('pembeli/pesanan_pembeli.php');
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

    <?php if ($batal): ?>
        <div class="pesanan-batal-banner" role="status">Pesanan ini dibatalkan.</div>
    <?php else: ?>
        <div class="pesanan-panel" style="margin-bottom:1.25rem;">
            <h2 class="pesanan-panel__judul">Status pesanan</h2>
            <ol class="pesanan-stepper" aria-label="Progress pesanan">
                <?php foreach ($langkah as $i => $step): ?>
                    <?php
                    $cls = 'pesanan-stepper__item pesanan-stepper__item--belum';
                    if ($idxAktif >= 0 && $i < $idxAktif) {
                        $cls = 'pesanan-stepper__item pesanan-stepper__item--selesai';
                    } elseif ($idxAktif >= 0 && $i === $idxAktif) {
                        $cls = 'pesanan-stepper__item pesanan-stepper__item--aktif';
                    }
                    ?>
                    <li class="<?php echo htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endif; ?>

    <div class="pesanan-detail-grid">
        <div>
            <div class="pesanan-panel">
                <h2 class="pesanan-panel__judul">Produk</h2>
                <?php if (!is_array($items) || $items === []): ?>
                    <p style="margin:0;color:var(--teks-redup);font-size:0.9rem;">Tidak ada baris item (data tidak lengkap).</p>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                        <?php
                        $it = is_array($it) ? $it : [];
                        $gurl = pesanan_url_gambar_item($it);
                        $pn = (string) ($it['product_name'] ?? '—');
                        $pr = (int) ($it['price'] ?? 0);
                        $sz = trim((string) ($it['size'] ?? ''));
                        $qty = (int) ($it['quantity'] ?? 1);
                        ?>
                        <div class="pesanan-item-baris">
                            <img class="pesanan-item-baris__gambar" src="<?php echo htmlspecialchars($gurl, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="72" height="72" loading="lazy">
                            <div>
                                <p class="pesanan-item-baris__nama"><?php echo htmlspecialchars($pn, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="pesanan-item-baris__kecil">
                                    Ukuran: <strong><?php echo htmlspecialchars($sz !== '' ? $sz : '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                                    · Qty: <?php echo (int) $qty; ?>
                                </p>
                                <p class="pesanan-item-baris__kecil" style="margin-top:0.35rem;font-weight:700;color:var(--oranye-cta-hover);"><?php echo htmlspecialchars(katalog_format_rupiah($pr), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div class="pesanan-panel">
                <h2 class="pesanan-panel__judul">Ringkasan</h2>
                <div class="pesanan-ringkasan-baris">
                    <span>Total</span>
                    <span><?php echo htmlspecialchars(katalog_format_rupiah($total), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="pesanan-ringkasan-baris">
                    <span>Alamat pengiriman</span>
                    <span style="text-align:right;max-width:12rem;"><?php echo $alamat !== '' ? htmlspecialchars($alamat, ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                </div>
                <div class="pesanan-ringkasan-baris">
                    <span>Metode pembayaran</span>
                    <span style="text-align:right;"><?php echo $bayar !== '' ? htmlspecialchars($bayar, ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                </div>
                <div class="pesanan-ringkasan-baris pesanan-ringkasan-baris--total">
                    <span>Status</span>
                    <span><?php echo htmlspecialchars($labelStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>
    </div>
</main>

</body>
</html>
