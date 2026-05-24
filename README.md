# EA SENIKERS

Website e-commerce penjualan sneakers (baru & preloved) berbasis PHP Native dan Supabase. Project tugas yang dibuat untuk membantu proses penjualan sepatu secara online, mulai dari pendaftaran pengguna, katalog produk, keranjang belanja, hingga pengelolaan pesanan oleh admin.

**Status:** Tahap 1 selesai (siap input data). Tahap 2 (integrasi RajaOngkir + Tripay) menyusul setelah akun layanan siap.

---

## Daftar Isi

- [Fitur](#fitur)
- [Teknologi](#teknologi)
- [Anggota Tim](#anggota-tim)
- [Cara Menjalankan](#cara-menjalankan)
- [Struktur Folder](#struktur-folder)
- [Daftar SQL Migration](#daftar-sql-migration)
- [Dependensi Eksternal](#dependensi-eksternal)
- [Roadmap](#roadmap)

---

## Fitur

### Area Pembeli

- Pendaftaran & login (terhubung Supabase Auth)
- Beranda dengan produk rekomendasi
- Katalog produk dengan filter (merek, kondisi, harga) & pencarian
- Halaman Kategori (jelajah per merek atau kondisi)
- Detail produk:
  - Lightbox zoom untuk foto produk
  - Indikator stok rendah per ukuran (≤3 tersisa)
  - Label "Preloved" konsisten di seluruh situs
- Keranjang belanja dengan ringkasan subtotal
- Halaman Pesanan: daftar + filter status + detail pesanan dengan tombol "Hubungi toko via WhatsApp"
- Profil pengiriman lengkap di halaman Akun:
  - Cascading dropdown alamat (provinsi → kota → kecamatan) dari API BPS
  - Peta interaktif untuk menentukan titik lokasi (Leaflet + OpenStreetMap)
  - Tombol "Lokasi saya" via Geolocation browser
- Halaman Tentang berisi cerita brand & tips merawat sneakers

### Area Admin

- Dashboard dengan statistik (pendapatan 30 hari, pesanan, produk aktif, pengguna), grafik mingguan, dan aktivitas terbaru
- Manajemen Produk: CRUD lengkap, upload multi-foto, stok per ukuran (EU 36–45), input berat untuk perhitungan ongkir
- Manajemen Pesanan: filter status, pencarian, transisi status berjenjang (pending → paid → processed → shipped → completed), pembatalan dengan CSRF
- Detail pesanan dengan informasi kontak pembeli (nama, email, no HP, tombol WhatsApp)
- Daftar Pengguna
- Pengaturan toko: identitas, kontak WhatsApp, metode pembayaran sementara

---

## Teknologi

- **Backend:** PHP 8.x (native, tanpa framework)
- **Database:** PostgreSQL via [Supabase](https://supabase.com/)
- **Auth:** Supabase Auth (email + password, konfirmasi email)
- **Frontend:** HTML, CSS, JavaScript vanilla (tanpa build tool)
- **Peta:** [Leaflet 1.9.4](https://leafletjs.com/) + [OpenStreetMap](https://www.openstreetmap.org/) tiles
- **Server lokal:** Laragon (Windows) / XAMPP

---

## Anggota Tim

| Nama | NIM |
|---|---|
| Annisa Aulia Husna | 2330407006 |
| Tomi Marta Anggeni | 2330407026 |
| Rania Aprilia | 2430407058 |
| Mowendry | 2430407061 |

---

## Cara Menjalankan

### 1. Clone Repository

```bash
git clone https://github.com/Creyzsa/ea-senikers.git
cd ea-senikers
```

### 2. Pasang di Laragon / XAMPP

Letakkan folder `ea-senikers` di direktori `www` Laragon (mis. `D:\laragon\www\EASENIKERS`) atau `htdocs` XAMPP. Pastikan Apache & PHP aktif.

### 3. Buat Project Supabase

1. Daftar di [supabase.com](https://supabase.com/) (gratis).
2. Buat project baru, pilih region terdekat (mis. Singapore).
3. Catat: **Project URL**, **anon key**, dan kredensial koneksi PostgreSQL (Host, Port, Database, User, Password) — terlihat di Project Settings → Database.

### 4. Konfigurasi `config.php`

Salin `config.example.php` (bila tersedia) atau buat `config.php` baru di root project dengan isi seperti berikut:

```php
<?php
// Database PostgreSQL Supabase
define('DB_HOST', 'aws-0-...supabase.com');
define('DB_PORT', '5432');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres');
define('DB_PASS', 'password-anda');

// Supabase REST + Auth
define('SUPABASE_URL', 'https://xxxxxxxx.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOi...');

// URL dasar aplikasi (sesuaikan path)
define('URL_APLIKASI', 'http://localhost/EASENIKERS/public/');
```

> **Catatan:** `config.php` sudah di-`.gitignore` agar kredensial tidak ikut ter-push ke GitHub.

### 5. Jalankan SQL Migration

Buka **Supabase Dashboard → SQL Editor → New query**, lalu jalankan file-file di [database/migrations/](database/migrations/) **berurutan**:

1. `tahap1_berat_dan_profil.sql` — kolom berat produk + profil pengiriman pembeli
2. `tahap1_2_peta_alamat.sql` — kolom lat/lng untuk peta pembeli
3. `tahap1_3_perbaiki_rls_produk.sql` — matikan RLS pada tabel produk agar admin bisa CRUD

Lihat [Daftar SQL Migration](#daftar-sql-migration) untuk penjelasan masing-masing.

### 6. Buat Akun Admin

Daftar lewat halaman `/login/daftar.php` dengan email apa saja, lalu di Supabase **Table Editor → users**, ubah kolom `role` baris user tersebut dari `pembeli` menjadi `admin`.

### 7. Buka di Browser

Akses `http://localhost/EASENIKERS/public/` dan masuk dengan akun yang sudah dibuat.

---

## Struktur Folder

```
EASENIKERS/
├── config.php                 # konfigurasi DB & Supabase (gitignored)
├── database/
│   └── migrations/            # file SQL untuk Supabase
├── includes/                  # logika PHP yang di-include
│   ├── sesi.php               # autentikasi sesi
│   ├── database.php           # koneksi PDO ke Supabase
│   ├── supabase_auth.php      # wrapper Supabase Auth
│   ├── supabase_rest.php      # wrapper Supabase REST/PostgREST
│   ├── katalog_produk.php     # fungsi katalog (fetch, format, label)
│   ├── pesanan_repositori.php # query pesanan (admin & pembeli)
│   ├── admin_*_repositori.php # repositori per fitur admin
│   ├── profil_pembeli_repositori.php
│   ├── kontak_toko.php        # config alamat toko & sosmed
│   ├── merek_ringkas.php      # config copy brand
│   └── url_bantu.php          # helper URL aplikasi
└── public/                    # document root web
    ├── index.php
    ├── login/                 # daftar, masuk, reset sandi, dll.
    ├── pembeli/               # halaman pembeli
    ├── admin/                 # halaman admin
    ├── api/
    │   └── payment_callback.php  # webhook payment (Tahap 2)
    └── assets/
        ├── css/
        ├── js/
        └── images/
```

---

## Daftar SQL Migration

| File | Fungsi |
|---|---|
| `tahap1_berat_dan_profil.sql` | Tambah kolom `berat_gram` (int) di tabel `produk`. Tambah kolom profil pengiriman (`no_hp`, `nama_penerima`, `provinsi`, `kota`, `kecamatan`, `kode_pos`, `alamat_detail`) di tabel `users`. |
| `tahap1_2_peta_alamat.sql` | Tambah kolom `lat`, `lng` (double precision, nullable) di tabel `users` dengan CHECK constraint rentang valid lat/lng. |
| `tahap1_3_perbaiki_rls_produk.sql` | Matikan Row Level Security pada tabel `produk`, `produk_gambar`, `produk_ukuran` agar admin CRUD lewat REST API (anon key) tidak diblok. |

Semua migration aman dijalankan ulang (pakai `IF NOT EXISTS` / cek constraint).

---

## Dependensi Eksternal

Semua dependensi dimuat dari CDN — **tidak ada `npm install`** atau build step.

| Pustaka | Tujuan | Sumber |
|---|---|---|
| Leaflet 1.9.4 | Peta interaktif di halaman Akun pembeli | `unpkg.com/leaflet@1.9.4` (CSS + JS dengan SRI hash) |
| OpenStreetMap | Tile peta untuk Leaflet | `tile.openstreetmap.org` |
| Nominatim | Reverse geocoding (nama tempat dari koordinat) | `nominatim.openstreetmap.org` |
| API Wilayah Indonesia | Cascading dropdown provinsi/kota/kecamatan | [emsifa/api-wilayah-indonesia](https://www.emsifa.com/api-wilayah-indonesia/) (data BPS) |
| Supabase | Database PostgreSQL, Auth, Storage | [supabase.com](https://supabase.com/) |

---

## Roadmap

### ✅ Tahap 1 — Selesai

Pondasi data & UX siap untuk input produk:

- Skema database lengkap (produk, ukuran, gambar, orders, users, profil pengiriman)
- Admin CRUD produk berfungsi (termasuk perbaikan bug `supabase_rest_request` dan RLS)
- Profil pengiriman pembeli dengan cascading dropdown alamat & peta titik lokasi
- Polish UX lengkap pada area pembeli & admin

### ⏳ Tahap 2 — Menunggu akun layanan

Integrasi pengiriman & pembayaran:

- RajaOngkir API untuk hitung ongkos kirim (butuh berat produk → sudah tersedia, butuh kota asal/tujuan → sudah tersedia)
- Tripay sebagai payment gateway (callback signature, link bayar, status sinkronisasi)
- Form checkout asli (pre-fill dari profil pengiriman pembeli)
- Input nomor resi pengiriman di admin

### 🔜 Tahap 3 — Pasca-launch

- Notifikasi WhatsApp/email otomatis (pesanan masuk, pembayaran berhasil, pengiriman)
- Export pesanan ke Excel/PDF
- Halaman tracking resi otomatis

---

## Lisensi

Project tugas perkuliahan. Tidak untuk distribusi komersial tanpa izin tim pengembang.
