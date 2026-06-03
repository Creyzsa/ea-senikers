<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';

$bilah_pembeli_aktif = 'akun';
$u_beranda = aplikasi_url('pembeli/beranda_pembeli.php');
$u_akun = aplikasi_url('pembeli/akun_pembeli.php');
$u_pesanan = aplikasi_url('pembeli/pesanan_pembeli.php');
$u_lapor = aplikasi_url('pembeli/lapor_masalah.php');

$kontak_toko = require __DIR__ . '/../../includes/konfigurasi/kontak_toko.php';
$pengaturan = admin_pengaturan_muat_terapan();
$email_toko = trim((string) ($pengaturan['email_toko'] ?? ''));

$wa_list = [];
foreach ((array) ($kontak_toko['wa'] ?? []) as $wa) {
    $e164 = preg_replace('/\D+/', '', (string) ($wa['e164'] ?? ''));
    if ($e164 === '') {
        continue;
    }
    $tampil = trim((string) ($wa['tampil'] ?? ''));
    $wa_list[] = [
        'url' => 'https://wa.me/' . $e164,
        'tampil' => $tampil !== '' ? $tampil : ('+' . $e164),
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bantuan & Hubungi Kami - EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="kontainer-toko bantuan-wrap" id="utama">
    <nav class="artikel-breadcrumb" aria-label="Remah roti">
        <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">Beranda</a>
        <span aria-hidden="true">/</span>
        <a href="<?php echo htmlspecialchars($u_akun, ENT_QUOTES, 'UTF-8'); ?>">Akun</a>
        <span aria-hidden="true">/</span>
        <span>Bantuan &amp; Hubungi Kami</span>
    </nav>

    <header class="bantuan-hero">
        <div class="bantuan-hero__teks">
            <span class="artikel-tag">Pusat Bantuan</span>
            <h1 class="artikel-judul">Bantuan &amp; Hubungi Kami</h1>
            <p class="bantuan-hero__sub">Ada pertanyaan soal pesanan, ukuran, atau pengiriman? Temukan jawabannya di sini atau hubungi tim kami langsung.</p>
        </div>
        <span class="bantuan-hero__ikon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/>
            </svg>
        </span>
    </header>

    <section class="bantuan-seksi" aria-labelledby="judul-kontak">
        <h2 id="judul-kontak" class="bantuan-judul">Hubungi kami langsung</h2>
        <div class="bantuan-kontak-grid">
            <?php foreach ($wa_list as $wa): ?>
                <a class="bantuan-kontak-card bantuan-kontak-card--wa" href="<?php echo htmlspecialchars($wa['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="bantuan-kontak-card__ikon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
                    </span>
                    <span class="bantuan-kontak-card__isi">
                        <span class="bantuan-kontak-card__label">WhatsApp Admin</span>
                        <strong><?php echo htmlspecialchars($wa['tampil'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="bantuan-kontak-card__aksi">Chat sekarang &rarr;</span>
                    </span>
                </a>
            <?php endforeach; ?>

            <?php if ($email_toko !== ''): ?>
                <a class="bantuan-kontak-card" href="mailto:<?php echo htmlspecialchars($email_toko, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="bantuan-kontak-card__ikon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                    </span>
                    <span class="bantuan-kontak-card__isi">
                        <span class="bantuan-kontak-card__label">Email Support</span>
                        <strong><?php echo htmlspecialchars($email_toko, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="bantuan-kontak-card__aksi">Kirim email &rarr;</span>
                    </span>
                </a>
            <?php endif; ?>

            <a class="bantuan-kontak-card" href="<?php echo htmlspecialchars($u_lapor, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="bantuan-kontak-card__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </span>
                <span class="bantuan-kontak-card__isi">
                    <span class="bantuan-kontak-card__label">Laporkan Masalah</span>
                    <strong>Form laporan kendala</strong>
                    <span class="bantuan-kontak-card__aksi">Buat laporan &rarr;</span>
                </span>
            </a>
        </div>
    </section>

    <section class="bantuan-seksi" aria-labelledby="judul-faq">
        <h2 id="judul-faq" class="bantuan-judul">Pertanyaan yang sering ditanyakan</h2>
        <div class="bantuan-faq">
            <details class="bantuan-faq__item">
                <summary>Bagaimana cara memesan produk?</summary>
                <div class="bantuan-faq__isi">
                    <p>Pilih produk di katalog, tekan tombol beli untuk memasukkannya ke keranjang, lalu buka keranjang dan tekan <strong>Checkout</strong>. Lengkapi alamat pengiriman, pilih kurir, lalu selesaikan pembayaran sesuai instruksi yang muncul.</p>
                </div>
            </details>
            <details class="bantuan-faq__item">
                <summary>Apa bedanya produk Baru dan Preloved?</summary>
                <div class="bantuan-faq__isi">
                    <p><strong>Baru</strong> berarti belum pernah dipakai. <strong>Preloved</strong> adalah produk bekas terkurasi — kondisinya kami jelaskan apa adanya di halaman detail produk (misalnya ada bercak atau sol sedikit aus), lengkap dengan foto asli.</p>
                </div>
            </details>
            <details class="bantuan-faq__item">
                <summary>Bagaimana memilih ukuran yang pas?</summary>
                <div class="bantuan-faq__isi">
                    <p>Setiap produk menampilkan ketersediaan ukuran EU 36–45. Cek ukuran sepatu yang biasa Anda pakai, lalu sesuaikan. Bila ragu, silakan chat WhatsApp admin untuk dibantu menyarankan ukuran.</p>
                </div>
            </details>
            <details class="bantuan-faq__item">
                <summary>Berapa lama pesanan diproses dan dikirim?</summary>
                <div class="bantuan-faq__isi">
                    <p>Pesanan diproses 1–2 hari kerja setelah pembayaran terverifikasi. Lama pengiriman mengikuti layanan kurir dan jarak alamat tujuan. Nomor resi akan tersedia di halaman <strong>Pesanan Saya</strong> setelah barang dikirim.</p>
                </div>
            </details>
            <details class="bantuan-faq__item">
                <summary>Apakah harga sudah termasuk ongkos kirim?</summary>
                <div class="bantuan-faq__isi">
                    <p>Belum. Ongkos kirim dihitung otomatis saat checkout berdasarkan alamat tujuan, berat produk, dan kurir yang Anda pilih.</p>
                </div>
            </details>
        </div>
    </section>

    <section class="bantuan-seksi bantuan-dua-kolom" aria-labelledby="judul-panduan">
        <h2 id="judul-panduan" class="bantuan-judul bantuan-judul--penuh">Panduan singkat</h2>

        <div class="bantuan-panduan">
            <h3>Cara melacak pesanan</h3>
            <ol class="bantuan-langkah">
                <li>Buka menu <a href="<?php echo htmlspecialchars($u_pesanan, ENT_QUOTES, 'UTF-8'); ?>"><strong>Pesanan Saya</strong></a>.</li>
                <li>Pilih pesanan yang ingin Anda pantau.</li>
                <li>Lihat status pesanan: <em>menunggu pembayaran &rarr; dibayar &rarr; diproses &rarr; dikirim &rarr; selesai</em>.</li>
                <li>Jika sudah dikirim, salin <strong>nomor resi</strong>, lalu lacak di situs/aplikasi kurir terkait.</li>
            </ol>
        </div>

        <div class="bantuan-panduan">
            <h3>Cara retur barang</h3>
            <ol class="bantuan-langkah">
                <li>Hubungi WhatsApp admin maksimal <strong>1×24 jam</strong> setelah barang diterima.</li>
                <li>Sertakan nomor pesanan, foto/video saat unboxing, dan alasan retur.</li>
                <li>Tunggu konfirmasi admin sebelum mengirim barang kembali.</li>
                <li>Kirim barang sesuai instruksi (kondisi lengkap, label utuh).</li>
                <li>Penggantian atau pengembalian dana diproses setelah barang diterima &amp; diperiksa.</li>
            </ol>
        </div>
    </section>

    <p class="artikel-kembali">
        <a href="<?php echo htmlspecialchars($u_akun, ENT_QUOTES, 'UTF-8'); ?>">&larr; Kembali ke Akun</a>
    </p>
</main>

</body>
</html>
