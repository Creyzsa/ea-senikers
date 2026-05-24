<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/pesanan_repositori.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

if (!isset($_SESSION['csrf_admin_pesanan']) || !is_string($_SESSION['csrf_admin_pesanan'])) {
    $_SESSION['csrf_admin_pesanan'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_admin_pesanan'];

$nama = htmlspecialchars($_SESSION['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8');
$urlKeluar = htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8');

$order_id = (int) ($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header('Location: pesanan_admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');
    $token = (string) ($_POST['csrf'] ?? '');
    $id_post = (int) ($_POST['order_id'] ?? 0);
    $redirUrl = aplikasi_url('admin/detail_pesanan_admin.php?id=' . rawurlencode((string) $order_id));

    if ($id_post !== $order_id || !hash_equals($csrf, $token)) {
        $_SESSION['flash_detail_order'] = 'Mohon muat ulang halaman.';
        $_SESSION['flash_detail_order_warna'] = 'error';
    } elseif ($aksi === 'ubah_status') {
        $nb = strtolower(trim((string) ($_POST['status_baru'] ?? '')));

        $ok = pesanan_admin_ubah_status($order_id, $nb);

        $_SESSION['flash_detail_order'] = $ok ? 'Status pesanan diperbarui.' : 'Status tidak dapat diubah pada tahap ini.';
        $_SESSION['flash_detail_order_warna'] = $ok ? 'sukses' : 'error';
    }

    header('Location: ' . $redirUrl);
    exit;
}

$flash_detail = $_SESSION['flash_detail_order'] ?? null;
$flash_warna = $_SESSION['flash_detail_order_warna'] ?? 'sukses';
unset($_SESSION['flash_detail_order'], $_SESSION['flash_detail_order_warna']);

$pesanan = pesanan_admin_detail($order_id);
if (!$pesanan) {
    header('Location: pesanan_admin.php');
    exit;
}

$status_labels = pesanan_status_label_id();
$badge_kelas = pesanan_status_kelas_badge();
$st = (string) ($pesanan['status'] ?? '');
$badgeClass = $badge_kelas[$st] ?? 'pesanan-badge pesanan-badge--kuning';

$langkah_tampil = pesanan_langkah_progress();
$langkah_idx = pesanan_indeks_langkah_aktif($st);

$status_opsi_selanjutnya = pesanan_admin_opsi_status_selanjutnya($st);

$pembeli_no_hp_raw = trim((string) ($pesanan['no_hp'] ?? ''));
$pembeli_no_hp_digit = preg_replace('/\D+/', '', $pembeli_no_hp_raw);
$pembeli_wa = '';
if (is_string($pembeli_no_hp_digit) && $pembeli_no_hp_digit !== '') {
    $nomor_internasional = (string) $pembeli_no_hp_digit;
    if (strncmp($nomor_internasional, '0', 1) === 0) {
        $nomor_internasional = '62' . substr($nomor_internasional, 1);
    }
    $pesan_admin_wa = "Halo, saya admin EA SENIKERS. Mau konfirmasi pesanan #{$order_id} (status: " . ($status_labels[$st] ?? $st) . '). Terima kasih.';
    $pembeli_wa = 'https://wa.me/' . $nomor_internasional . '?text=' . rawurlencode($pesan_admin_wa);
}
$alamat_pengiriman = trim((string) ($pesanan['shipping_address'] ?? ''));
$bayar_pengiriman = trim((string) ($pesanan['payment_method'] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Pesanan #<?php echo htmlspecialchars((string) $pesanan['id'], ENT_QUOTES, 'UTF-8'); ?> — EA SENIKERS Admin</title>
    <link rel="stylesheet" href="../assets/css/beranda-admin.css">
</head>
<body class="halaman-admin">

    <div class="admin-cangkang">
        <aside class="admin-sisi" aria-label="Navigasi admin">
            <a class="admin-sisi__merek" href="beranda_admin.php">
                <p class="admin-sisi__nama">EA SENIKERS</p>
                <p class="admin-sisi__sub">Panel Admin</p>
            </a>
            <nav class="admin-nav">
                <a class="admin-nav__tautan" href="beranda_admin.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                    Dashboard
                </a>
                <a class="admin-nav__tautan" href="produk_admin.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Produk
                </a>
                <a class="admin-nav__tautan admin-nav__tautan--aktif" href="pesanan_admin.php" aria-current="page">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Pesanan
                </a>
                <a class="admin-nav__tautan" href="pengguna_admin.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Pengguna
                </a>
                <a class="admin-nav__tautan" href="pengaturan_admin.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Pengaturan
                </a>
            </nav>
            <p class="admin-sisi__kaki">© EA SENIKERS</p>
        </aside>

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
                <h1 class="admin-judul-besar">Pesanan #<?php echo htmlspecialchars((string) $pesanan['id'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="admin-salam"><?php echo htmlspecialchars($pesanan['nama_pengguna'] ?? 'Pembeli', ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($pesanan['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></p>

                <?php if (is_string($flash_detail) && $flash_detail !== ''): ?>
                    <div class="admin-alert admin-alert--<?php echo htmlspecialchars($flash_warna === 'error' ? 'error' : 'sukses', ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($flash_detail, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <section class="admin-kartu" aria-labelledby="judul-info-pesanan">
                    <div class="admin-kartu__header">
                        <h2 id="judul-info-pesanan">Informasi pesanan</h2>
                        <a href="pesanan_admin.php" class="admin-btn admin-btn--sekunder">← Daftar pesanan</a>
                    </div>
                    <div class="admin-form-konten">
                        <div class="admin-form-grid">
                            <div>
                                <strong>ID</strong><br>
                                #<?php echo htmlspecialchars((string) $pesanan['id'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div>
                                <strong>Pembeli</strong><br>
                                <?php echo htmlspecialchars($pesanan['nama_pengguna'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                <span class="admin-meta"><br><?php echo htmlspecialchars($pesanan['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($pembeli_no_hp_raw !== ''): ?>
                                    <span class="admin-meta"><br>HP: <?php echo htmlspecialchars($pembeli_no_hp_raw, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <?php if ($pembeli_wa !== ''): ?>
                                    <a class="admin-btn-wa-mini" href="<?php echo htmlspecialchars($pembeli_wa, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/></svg>
                                        Hubungi via WhatsApp
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong>Status</strong><br>
                                <span class="<?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($status_labels[$st] ?? $st, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <div>
                                <strong>Metode pembayaran</strong><br>
                                <?php if ($bayar_pengiriman !== ''): ?>
                                    <?php echo htmlspecialchars($bayar_pengiriman, ENT_QUOTES, 'UTF-8'); ?>
                                <?php else: ?>
                                    <em class="admin-kosong">Belum dipilih saat checkout</em>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong>Total</strong><br>
                                Rp <?php echo htmlspecialchars(number_format((float) ($pesanan['total_price'] ?? 0), 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>

                        <?php if ($st === 'cancelled'): ?>
                            <p class="admin-alert admin-alert--error" style="margin-top: 1rem;">Pesanan ini dibatalkan.</p>
                        <?php elseif ($langkah_idx >= 0): ?>
                            <div style="margin-top: 1.1rem;">
                                <strong>Alur di toko</strong>
                                <ul class="admin-progress-pesanan" style="margin: 0.6rem 0 0;">
                                    <?php foreach ($langkah_tampil as $ix => $__row): ?>
                                        <?php $selesai = $ix <= $langkah_idx; ?>
                                        <li class="admin-progress-pesanan__langkah">
                                            <?php if ($selesai): ?>
                                                <span class="admin-progress-pesanan__cek" aria-hidden="true">✓</span>
                                            <?php else: ?>
                                                <span aria-hidden="true">○</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars((string) ($__row['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($status_opsi_selanjutnya !== []): ?>
                            <form class="admin-form-status" method="post" aria-label="Ubah tahap pesanan"
                                  data-konfirm-batal="<?php echo htmlspecialchars($status_labels['cancelled'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="aksi" value="ubah_status">
                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars((string) $pesanan['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="admin-form-grid" style="align-items:end;">
                                    <label class="admin-field">
                                        <span>Perbarui tahap</span>
                                        <select name="status_baru" required class="detail-select-status">
                                            <?php foreach ($status_opsi_selanjutnya as $val): ?>
                                                <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars((string) ($status_labels[$val] ?? $val), ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <button type="submit" class="admin-btn admin-btn--utama">Terapkan</button>
                                </div>
                            </form>
                            <script>
(function () {
    var sel = document.querySelector('.detail-select-status');
    var form = sel && sel.closest('form[data-konfirm-batal]');
    if (!form || !sel) return;
    form.addEventListener('submit', function (e) {
        if (sel.value !== 'cancelled') return;
        var msg = form.getAttribute('data-konfirm-batal');
        if (msg && !window.confirm('Yakin membatalkan pesanan ini? Status tidak bisa dibuka kembali.')) {
            e.preventDefault();
        }
    });
})();
                            </script>
                        <?php endif; ?>

                        <div class="admin-detail-alamat">
                            <strong>Alamat pengiriman</strong>
                            <div>
                                <?php if ($alamat_pengiriman !== ''): ?>
                                    <?php echo nl2br(htmlspecialchars($alamat_pengiriman, ENT_QUOTES, 'UTF-8')); ?>
                                <?php else: ?>
                                    <em class="admin-kosong">Belum diisi pembeli saat checkout</em>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-kartu" aria-labelledby="judul-item">
                    <div class="admin-kartu__header">
                        <h2 id="judul-item">Item pesanan</h2>
                    </div>
                    <div class="admin-tabel-wrap">
                        <table class="admin-tabel">
                            <thead>
                                <tr>
                                    <th scope="col">Produk</th>
                                    <th scope="col">Ukuran</th>
                                    <th scope="col">Jumlah</th>
                                    <th scope="col">Harga</th>
                                    <th scope="col">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $items_pesanan = (array) ($pesanan['items'] ?? []); ?>
                                <?php if ($items_pesanan === []): ?>
                                    <tr class="admin-tr-kosong">
                                        <td colspan="5">Tidak ada item tercatat untuk pesanan ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items_pesanan as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="admin-detail-produk-baris">
                                                    <img src="<?php echo htmlspecialchars(pesanan_url_gambar_item($item), ENT_QUOTES, 'UTF-8'); ?>" alt="" width="52" height="52" loading="lazy" decoding="async">
                                                    <strong><?php echo htmlspecialchars((string) ($item['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars((string) ($item['size'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($item['quantity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>Rp <?php echo htmlspecialchars(number_format((float) ($item['price'] ?? 0), 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>Rp <?php echo htmlspecialchars(number_format((float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0), 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

</body>
</html>
