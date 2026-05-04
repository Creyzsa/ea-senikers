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

$bilah_pembeli_aktif = 'pesanan';
$id_pengguna = ambil_id_pengguna_efektif();
$tabel_ada = pesanan_cek_tabel_ada();
$daftar = $tabel_ada && $id_pengguna > 0 ? pesanan_ambil_oleh_user($id_pengguna) : [];

$labels = pesanan_status_label_id();
$badgeClass = pesanan_status_kelas_badge();

function pesanan_format_tanggal(?string $iso): string
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

$u_detail_base = aplikasi_url('pembeli/detail_pesanan_pembeli.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pesanan saya — EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/pesanan-pembeli.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="pesanan-wrap" id="utama">
    <h1 class="pesanan-judul">Pesanan saya</h1>
    <p class="pesanan-sub">Riwayat pesanan kamu, diurutkan dari yang terbaru.</p>

    <?php if (!$tabel_ada): ?>
        <div class="pesanan-setup-db" role="alert">
            <strong>Tabel belum dibuat.</strong> Jalankan skrip SQL di project:
            <code>database/orders_schema.sql</code> pada Supabase (SQL Editor), lalu muat ulang halaman ini.
        </div>
    <?php elseif ($id_pengguna <= 0): ?>
        <div class="pesanan-peringatan" role="alert">
            Akun belum punya <strong>ID pengguna</strong> di tabel <code>users</code>. Coba <strong>Keluar</strong> lalu <strong>Masuk</strong> lagi agar ID tersimpan, atau pastikan email akun ada di Supabase → Table <code>users</code>.
        </div>
    <?php endif; ?>

    <?php if ($tabel_ada && $id_pengguna > 0 && $daftar === []): ?>
        <div class="pesanan-kosong">
            <div class="pesanan-kosong__ikon" aria-hidden="true">📦</div>
            <p class="pesanan-kosong__judul">Belum ada pesanan</p>
            <p style="margin:0;font-size:0.92rem;">Yuk jelajahi katalog dan mulai belanja.</p>
            <p style="margin:1rem 0 0;">
                <a class="pesanan-tombol-detail" href="<?php echo htmlspecialchars(aplikasi_url('pembeli/produk.php'), ENT_QUOTES, 'UTF-8'); ?>">Lihat produk</a>
            </p>
        </div>
    <?php elseif ($tabel_ada && $id_pengguna > 0): ?>
        <div class="pesanan-grid">
            <?php foreach ($daftar as $order): ?>
                <?php
                $items = $order['items'] ?? [];
                $first = is_array($items) && $items !== [] ? $items[0] : null;
                $nItem = is_array($items) ? count($items) : 0;
                $st = (string) ($order['status'] ?? 'pending');
                $badge = $badgeClass[$st] ?? 'pesanan-badge pesanan-badge--kuning';
                $label = $labels[$st] ?? $st;
                $total = (int) ($order['total_price'] ?? 0);
                $oid = (int) ($order['id'] ?? 0);
                $namaTampil = $first
                    ? (string) ($first['product_name'] ?? 'Produk')
                    : 'Pesanan #' . $oid;
                if ($nItem > 1) {
                    $namaTampil .= ' +' . ($nItem - 1) . ' lainnya';
                }
                $imgUrl = $first ? pesanan_url_gambar_item($first) : katalog_url_gambar_placeholder();
                $uDetail = $u_detail_base . '?id=' . rawurlencode((string) $oid);
                ?>
                <article class="pesanan-kartu">
                    <img class="pesanan-kartu__gambar" src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="96" height="96" loading="lazy">
                    <div class="pesanan-kartu__isi">
                        <h2 class="pesanan-kartu__nama"><?php echo htmlspecialchars($namaTampil, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="pesanan-kartu__meta"><?php echo htmlspecialchars(pesanan_format_tanggal(isset($order['created_at']) ? (string) $order['created_at'] : null), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="pesanan-kartu__harga"><?php echo htmlspecialchars(katalog_format_rupiah($total), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="pesanan-kartu__aksi">
                        <span class="<?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                        <a class="pesanan-tombol-detail" href="<?php echo htmlspecialchars($uDetail, ENT_QUOTES, 'UTF-8'); ?>">
                            Lihat detail
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
