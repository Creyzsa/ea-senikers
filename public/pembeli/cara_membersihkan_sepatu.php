<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_db/sesi.php';

$bilah_pembeli_aktif = 'beranda';
$u_beranda = aplikasi_url(''); // clean root homepage
$u_produk = aplikasi_url('produk');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cara Membersihkan Sepatu Biar Awet - EA SENIKERS</title>
    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<main class="artikel-wrap" id="utama">
    <nav class="artikel-breadcrumb" aria-label="Remah roti">
        <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">Beranda</a>
        <span aria-hidden="true">/</span>
        <span>Tips &amp; Panduan</span>
        <span aria-hidden="true">/</span>
        <span>Cara Membersihkan Sepatu</span>
    </nav>

    <header class="artikel-hero">
        <div class="artikel-hero__teks">
            <span class="artikel-tag">Panduan Perawatan</span>
            <h1 class="artikel-judul">Cara Membersihkan Sepatu Biar Awet &amp; Tetap Kinclong</h1>
            <div class="artikel-meta">
                <span>EA SENIKERS</span>
                <span>&middot; Sekitar 4 menit baca</span>
                <span>&middot; Sneakers baru &amp; preloved</span>
            </div>
        </div>
        <span class="artikel-hero__ikon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13l2.5-6.5A2 2 0 017.4 5h9.2a2 2 0 011.9 1.5L21 13M3 13h18M3 13v4a2 2 0 002 2h14a2 2 0 002-2v-4M7 17h.01M11 17h2"/>
            </svg>
        </span>
    </header>

    <article class="artikel-isi">
        <p class="artikel-lead">
            Sepatu yang dirawat dengan benar bisa bertahan jauh lebih lama dan tetap enak dipakai.
            Panduan ini merangkum langkah membersihkan sneakers sesuai bahannya — mulai dari menyiapkan
            alat, mencuci dengan aman, sampai cara mengeringkan dan menyimpan supaya tidak mudah rusak.
        </p>

        <h2>Alat &amp; bahan yang perlu disiapkan</h2>
        <ul class="artikel-bahan">
            <li>Sikat berbulu lembut (sikat gigi bekas bisa dipakai)</li>
            <li>Kain microfiber atau lap bersih</li>
            <li>Sabun lembut / sampo bayi / pembersih khusus sepatu</li>
            <li>Air hangat secukupnya</li>
            <li>Wadah kecil untuk larutan pembersih</li>
            <li>Silica gel untuk penyimpanan</li>
        </ul>

        <h2>Langkah membersihkan sepatu</h2>
        <ol class="artikel-langkah">
            <li>
                <span class="artikel-langkah__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                </span>
                <div class="artikel-langkah__isi">
                    <span class="artikel-langkah__label">Langkah 1</span>
                    <h3>Kenali dulu bahan sepatunya</h3>
                    <p>Kanvas, kulit, suede, dan mesh butuh perlakuan berbeda. Suede tidak boleh kena air berlebih,
                        sedangkan kanvas relatif lebih tahan. Mengenali bahan sejak awal mencegah salah cara yang justru merusak.</p>
                </div>
            </li>
            <li>
                <span class="artikel-langkah__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.848 8.25l1.536.887M7.848 8.25a3 3 0 11-5.196-3 3 3 0 015.196 3zm1.536.887a2.165 2.165 0 011.083 1.839c.005.351.054.695.14 1.024M9.384 9.137l10.962 6.331a3 3 0 010 5.196l-.024.013a2.25 2.25 0 01-3.06-.825l-2.43-4.207m0 0l-.806-1.395m.806 1.395a2.25 2.25 0 003.06.825l.024-.013a3 3 0 000-5.196L13.5 4.072"/></svg>
                </span>
                <div class="artikel-langkah__isi">
                    <span class="artikel-langkah__label">Langkah 2</span>
                    <h3>Lepas tali dan insole</h3>
                    <p>Keluarkan tali sepatu dan alas dalam (insole) supaya setiap bagian bisa dibersihkan menyeluruh.
                        Tali dan insole dicuci terpisah karena bahannya sering berbeda dari bagian luar.</p>
                </div>
            </li>
            <li>
                <span class="artikel-langkah__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
                </span>
                <div class="artikel-langkah__isi">
                    <span class="artikel-langkah__label">Langkah 3</span>
                    <h3>Sikat kering kotoran kasarnya</h3>
                    <p>Sebelum kena air, sikat dulu debu, pasir, dan tanah yang menempel dengan sikat kering.
                        Membersihkan kotoran kering lebih dulu membuat proses pencucian tidak menyebar jadi noda baru.</p>
                </div>
            </li>
            <li>
                <span class="artikel-langkah__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/></svg>
                </span>
                <div class="artikel-langkah__isi">
                    <span class="artikel-langkah__label">Langkah 4</span>
                    <h3>Buat larutan pembersih lembut</h3>
                    <p>Campur sedikit sabun lembut dengan air hangat. Hindari deterjen keras atau pemutih karena
                        bisa membuat warna pudar dan bahan jadi getas.</p>
                </div>
            </li>
            <li>
                <span class="artikel-langkah__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.5 3.5 5.5 6.5 5.5 10a5.5 5.5 0 11-11 0C6.5 9.5 9.5 6.5 12 3z"/></svg>
                </span>
                <div class="artikel-langkah__isi">
                    <span class="artikel-langkah__label">Langkah 5</span>
                    <h3>Bersihkan bagian upper dengan lembut</h3>
                    <p>Celupkan sikat ke larutan, lalu gosok perlahan mengikuti arah serat bahan. Jangan terlalu basah —
                        cukup lembap. Untuk noda membandel, ulangi pelan-pelan, bukan dengan tenaga berlebih.</p>
                </div>
            </li>
            <li>
                <span class="artikel-langkah__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="8.5" cy="13.5" r="3.25"/><circle cx="15" cy="9" r="2.25"/><circle cx="16.5" cy="15.5" r="1.5"/></svg>
                </span>
                <div class="artikel-langkah__isi">
                    <span class="artikel-langkah__label">Langkah 6</span>
                    <h3>Bersihkan sol dan bagian karet</h3>
                    <p>Bagian sol biasanya paling kotor, jadi boleh disikat lebih kuat. Lap sisa busa sabun dengan
                        kain lembap supaya tidak ada residu yang mengering dan meninggalkan bekas.</p>
                </div>
            </li>
            <li>
                <span class="artikel-langkah__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                </span>
                <div class="artikel-langkah__isi">
                    <span class="artikel-langkah__label">Langkah 7</span>
                    <h3>Keringkan dengan diangin-anginkan</h3>
                    <p>Jangan jemur langsung di bawah matahari karena sinar UV bikin warna pudar dan lem perekat melemah.
                        Angin-anginkan di tempat teduh, isi bagian dalam dengan kertas/koran agar bentuknya tetap terjaga.</p>
                </div>
            </li>
            <li>
                <span class="artikel-langkah__ikon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 4.5v3.75m-6.75-9.75h12.75c.621 0 1.125-.504 1.125-1.125V4.875c0-.621-.504-1.125-1.125-1.125H5.625c-.621 0-1.125.504-1.125 1.125V6.375c0 .621.504 1.125 1.125 1.125z"/></svg>
                </span>
                <div class="artikel-langkah__isi">
                    <span class="artikel-langkah__label">Langkah 8</span>
                    <h3>Pasang kembali &amp; simpan dengan benar</h3>
                    <p>Setelah benar-benar kering, pasang lagi insole dan tali. Simpan di tempat sejuk dan kering,
                        tambahkan silica gel untuk mencegah jamur — terutama saat musim hujan.</p>
                </div>
            </li>
        </ol>

        <h2>Tips tambahan biar makin awet</h2>
        <p>
            Bersihkan noda sesegera mungkin saat masih baru, karena noda yang dibiarkan semalam jauh lebih sulit hilang.
            Hindari mesin cuci karena putarannya bisa merusak bentuk sol dan melepas lem. Pakai dua sepatu secara
            bergantian agar masing-masing punya waktu &ldquo;istirahat&rdquo; dan kering sempurna. Untuk koleksi preloved,
            perawatan rutin justru membuat tampilannya bertahan lebih lama.
        </p>
    </article>

    <section class="artikel-cta" aria-labelledby="judul-cta-artikel">
        <h2 id="judul-cta-artikel">Cari sneakers baru atau preloved terkurasi?</h2>
        <p>Lihat koleksi EA SENIKERS dengan kondisi jelas, foto asli, dan harga transparan.</p>
        <a class="tombol-page-utama" href="<?php echo htmlspecialchars($u_produk, ENT_QUOTES, 'UTF-8'); ?>">Jelajahi katalog</a>
    </section>

    <p class="artikel-kembali">
        <a href="<?php echo htmlspecialchars($u_beranda, ENT_QUOTES, 'UTF-8'); ?>">&larr; Kembali ke beranda</a>
    </p>
</main>

</body>
</html>
