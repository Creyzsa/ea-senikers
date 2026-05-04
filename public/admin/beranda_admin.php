<?php
require_once __DIR__ . '/../../includes/sesi.php';

wajib_sudah_masuk();
if (ambil_peran() !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Halaman ini khusus admin.';
    exit;
}

$nama = htmlspecialchars($_SESSION['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin — EA SENIKERS</title>
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
                <a class="admin-nav__tautan admin-nav__tautan--aktif" href="beranda_admin.php" aria-current="page">
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
                <a class="admin-nav__tautan" href="pesanan_admin.php">
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
            <p class="admin-sisi__kaki">Versi panel 1.0 · Data contoh untuk tampilan</p>
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
                <a class="admin-tombol-keluar" href="<?php echo htmlspecialchars(aplikasi_url('login/keluar.php'), ENT_QUOTES, 'UTF-8'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </a>
            </header>

            <main class="admin-isi" id="utama">
                <h1 class="admin-judul-besar">Dashboard</h1>
                <p class="admin-salam">Halo <strong><?php echo $nama; ?></strong> — ringkasan performa toko dan aktivitas terbaru. Angka di bawah adalah contoh hingga backend terhubung.</p>

                <div class="admin-grid-stat" role="region" aria-label="Ringkasan statistik">
                    <article class="admin-stat admin-stat--biru">
                        <div class="admin-stat__label">
                            <span>Pendapatan (30 hari)</span>
                            <span class="admin-stat__ikon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </span>
                        </div>
                        <p class="admin-stat__nilai">Rp 12,4 jt</p>
                        <p class="admin-stat__tren">↑ 12% vs bulan lalu</p>
                    </article>
                    <article class="admin-stat admin-stat--hijau">
                        <div class="admin-stat__label">
                            <span>Pesanan</span>
                            <span class="admin-stat__ikon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                            </span>
                        </div>
                        <p class="admin-stat__nilai">48</p>
                        <p class="admin-stat__tren">↑ 8 pesanan baru</p>
                    </article>
                    <article class="admin-stat admin-stat--kuning">
                        <div class="admin-stat__label">
                            <span>Produk aktif</span>
                            <span class="admin-stat__ikon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </span>
                        </div>
                        <p class="admin-stat__nilai">12</p>
                        <p class="admin-stat__tren admin-stat__tren--netral">Stabil</p>
                    </article>
                    <article class="admin-stat admin-stat--ungu">
                        <div class="admin-stat__label">
                            <span>Pengguna</span>
                            <span class="admin-stat__ikon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </span>
                        </div>
                        <p class="admin-stat__nilai">156</p>
                        <p class="admin-stat__tren">↑ 3 pendaftar minggu ini</p>
                    </article>
                </div>

                <div class="admin-baris-dua">
                    <section class="admin-panel" aria-labelledby="judul-grafik">
                        <h2 id="judul-grafik" class="admin-panel__judul">Pendapatan mingguan</h2>
                        <div class="admin-grafik" role="img" aria-label="Diagram batang contoh tujuh hari terakhir">
                            <div class="admin-grafik__batang" style="height: 45%"></div>
                            <div class="admin-grafik__batang" style="height: 62%"></div>
                            <div class="admin-grafik__batang" style="height: 38%"></div>
                            <div class="admin-grafik__batang" style="height: 78%"></div>
                            <div class="admin-grafik__batang" style="height: 55%"></div>
                            <div class="admin-grafik__batang" style="height: 92%"></div>
                            <div class="admin-grafik__batang" style="height: 68%"></div>
                        </div>
                        <div class="admin-grafik__kaki">
                            <span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span>
                        </div>
                    </section>

                    <section class="admin-panel" aria-labelledby="judul-aktivitas">
                        <h2 id="judul-aktivitas" class="admin-panel__judul">Aktivitas terbaru</h2>
                        <ul class="admin-aktivitas">
                            <li>
                                <span class="admin-aktivitas__titik admin-aktivitas__titik--biru" aria-hidden="true"></span>
                                <span class="admin-aktivitas__teks">Pesanan <strong>#1042</strong> lunas — Sneakers Street Runner</span>
                                <span class="admin-aktivitas__waktu">12 mnt</span>
                            </li>
                            <li>
                                <span class="admin-aktivitas__titik admin-aktivitas__titik--hijau" aria-hidden="true"></span>
                                <span class="admin-aktivitas__teks">Produk baru ditambahkan — <strong>Sport Active Lite</strong></span>
                                <span class="admin-aktivitas__waktu">2 jam</span>
                            </li>
                            <li>
                                <span class="admin-aktivitas__titik admin-aktivitas__titik--kuning" aria-hidden="true"></span>
                                <span class="admin-aktivitas__teks">Stok diperbarui untuk 3 SKU</span>
                                <span class="admin-aktivitas__waktu">Kemarin</span>
                            </li>
                        </ul>
                    </section>
                </div>

                <section class="admin-bagian-tabel" aria-labelledby="judul-tabel">
                    <h2 id="judul-tabel" class="admin-panel__judul">Pesanan terbaru</h2>
                    <div class="admin-tabel-wrap">
                        <table class="admin-tabel">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Pelanggan</th>
                                    <th scope="col">Produk</th>
                                    <th scope="col">Total</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>#1042</strong></td>
                                    <td>andi_trader</td>
                                    <td>Sneakers Street Runner</td>
                                    <td>Rp 1.750.000</td>
                                    <td><span class="admin-lencana admin-lencana--sukses">Lunas</span></td>
                                </tr>
                                <tr>
                                    <td><strong>#1041</strong></td>
                                    <td>budi_fx</td>
                                    <td>Kasual Daily Comfort</td>
                                    <td>Rp 2.100.000</td>
                                    <td><span class="admin-lencana admin-lencana--tunda">Menunggu</span></td>
                                </tr>
                                <tr>
                                    <td><strong>#1040</strong></td>
                                    <td>citra_ea</td>
                                    <td>Classic Leather Series</td>
                                    <td>Rp 980.000</td>
                                    <td><span class="admin-lencana admin-lencana--sukses">Lunas</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

</body>
</html>
