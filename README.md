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

Project disusun dengan pemisahan tegas antara **backend logic** (`includes/`),
**database** (`database/`), dan **document root web** (`public/`). Folder
`includes/` lalu dipecah menjadi subfolder bertema agar mudah dilacak.

```
EASENIKERS/
├── config.php                       # kredensial Supabase (gitignored)
├── README.md                        # dokumentasi ini
│
├── database/
│   └── migrations/                  # file SQL urutan migrasi Supabase
│
├── includes/                        # backend PHP (di-require oleh public/)
│   │
│   ├── auth_db/                     # AUTENTIKASI & KONEKSI DATABASE
│   │   ├── sesi.php                 # sesi login, cookie ingat-saya
│   │   ├── database.php             # koneksi PDO ke Supabase Postgres
│   │   ├── supabase_auth.php        # wrapper Supabase Auth (signup/login)
│   │   └── supabase_rest.php        # wrapper PostgREST untuk CRUD katalog
│   │
│   ├── repositori/                  # AKSES DATA (query & CRUD per fitur)
│   │   ├── katalog_produk.php       # fetch produk, format harga, label
│   │   ├── pesanan_repositori.php   # CRUD pesanan + status transisi
│   │   ├── profil_pembeli_repositori.php  # profil pengiriman pembeli
│   │   ├── admin_dashboard_repositori.php
│   │   ├── admin_pengaturan_repositori.php
│   │   ├── admin_pengguna_repositori.php
│   │   └── admin_produk_repositori.php
│   │
│   ├── integrasi/                   # API EKSTERNAL
│   │   └── rajaongkir.php           # wrapper RajaOngkir Komerce API
│   │
│   ├── konfigurasi/                 # KONFIGURASI STATIS (file PHP & JSON)
│   │   ├── kontak_toko.php          # alamat toko, WA, sosial media
│   │   ├── merek_ringkas.php        # copy hero, tagline merek
│   │   └── deskripsi_merek_login.php
│   │
│   ├── bilah_pembeli.php            # komponen header pembeli (sticky nav)
│   ├── keranjang_sesi.php           # state keranjang di $_SESSION
│   ├── url_bantu.php                # helper aplikasi_url() & path
│   └── pengaturan_toko_admin.json   # config disimpan admin (gitignored)
│
└── public/                          # DOCUMENT ROOT WEB SERVER
    ├── index.php                    # entry point
    │
    ├── login/                       # daftar, masuk, reset sandi, OTP
    ├── pembeli/                     # halaman pembeli (beranda, katalog, dll)
    ├── admin/                       # panel admin
    │
    ├── api/
    │   └── payment_callback.php     # webhook payment Tripay (Tahap 2)
    │
    └── assets/
        ├── css/                     # stylesheet
        ├── js/                      # JavaScript (peta, dropdown alamat)
        └── images/                  # logo, ikon, gambar produk
```

**Aturan penamaan subfolder includes/:**
- `auth_db/` — semua hal yang berurusan dengan kredensial & koneksi DB
- `repositori/` — kelas/fungsi yang **membaca/menulis data**
- `integrasi/` — wrapper API eksternal (RajaOngkir, Tripay nanti, dll)
- `konfigurasi/` — file statis yang **dibaca** oleh halaman (alamat, copy)
- Root `includes/` — utilitas yang dipakai lintas-modul (helper, sesi
  state, komponen UI)

---

## Daftar SQL Migration

| File | Fungsi |
|---|---|
| `tahap1_berat_dan_profil.sql` | Tambah kolom `berat_gram` (int) di tabel `produk`. Tambah kolom profil pengiriman (`no_hp`, `nama_penerima`, `provinsi`, `kota`, `kecamatan`, `kode_pos`, `alamat_detail`) di tabel `users`. |
| `tahap1_2_peta_alamat.sql` | Tambah kolom `lat`, `lng` (double precision, nullable) di tabel `users` dengan CHECK constraint rentang valid lat/lng. |
| `tahap1_3_perbaiki_rls_produk.sql` | Matikan Row Level Security pada tabel `produk`, `produk_gambar`, `produk_ukuran` agar admin CRUD lewat REST API (anon key) tidak diblok. |
| `tahap2_orders_shipping.sql` | Tambah kolom `kurir`, `layanan`, `ongkir`, `destination_id`, `nomor_resi` di tabel `orders` untuk informasi pengiriman dari RajaOngkir. |

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

### ⏳ Tahap 2 — Sebagian Selesai

Integrasi pengiriman & pembayaran:

- ✅ **RajaOngkir** (Komerce API) — wrapper, admin tool cek koneksi, search destinasi, hitung ongkir, integrasi penuh di checkout pembeli (auto-pick destinasi via kode pos profil, fallback manual)
- ✅ Form checkout asli dengan create order ke database (kurir, layanan, ongkir, destination_id)
- ✅ Form admin input nomor resi saat status pesanan berubah ke "Dikirim"
- ⏳ **Tripay** sebagai payment gateway — slot konfigurasi sudah disediakan di Pengaturan admin, integrasi nyata (callback signature, link bayar, status sinkronisasi) menyusul

### 🔜 Tahap 3 — Pasca-launch

- Notifikasi WhatsApp/email otomatis (pesanan masuk, pembayaran berhasil, pengiriman)
- Export pesanan ke Excel/PDF
- Halaman tracking resi otomatis
- Auto-resolve `destination_id` RajaOngkir dari koordinat peta profil pembeli (saat profil disimpan, sistem cari ID otomatis sehingga checkout langsung hitung tanpa pilih kelurahan)

---

## Lisensi

Project tugas perkuliahan. Tidak untuk distribusi komersial tanpa izin tim pengembang.

---

## Deployment ke Vercel (Serverless PHP)

**Catatan penting:** Vercel adalah platform serverless yang bagus untuk frontend. Untuk aplikasi PHP tradisional seperti ini, dukungannya terbatas dan **membutuhkan penyesuaian**.

Kami sudah menambahkan:
- `vercel.json` — konfigurasi dengan PHP runtime (`vercel-php`).
- `api/index.php` — front controller sederhana yang mencoba merutekan request ke file-file di `public/`.

### Langkah Deploy ke Vercel

1. Buka [vercel.com](https://vercel.com), login dengan GitHub.
2. **Add New Project** → Import Git Repository → pilih repo `ea-senikers` (branch `main`).
3. **Application presets / Framework Preset**: 
   - **Leave default** (atau pilih **"Other"**).
   - **JANGAN** pilih Next.js, Vite, Create React App, atau framework JS lainnya.
   - Karena kita pakai `vercel.json` dengan `"framework": null` dan PHP runtime, "Other" / default adalah yang benar.
4. **Root Directory** (folder root project):
   - **Leave as default** (kosong / `.` / repo root).
   - **JANGAN** ubah ke `public`.
   - Alasan: `vercel.json` dan `api/` ada di root repo, bukan di dalam `public/`.
5. Biarkan Build Command dan Output Directory kosong/default.
6. **IMPORTANT for secrets (config):** 
   - Do NOT commit real config.php (it's gitignored).
   - Before or after first deploy, go to Vercel Project → **Settings** → **Environment Variables**
   - Add these (copy values from your local config.php or config.example.php):
     - DB_HOST
     - DB_PORT=5432
     - DB_NAME=postgres
     - DB_USER
     - DB_PASS
     - SUPABASE_URL
     - SUPABASE_ANON_KEY
     - URL_APLIKASI=https://your-vercel-domain.vercel.app   (or your custom domain later)
     - (optional) PAYMENT_CALLBACK_SECRET, EMAIL_DRIVER, EMAIL_PENGIRIM
   - Redeploy after adding env vars.
7. Klik **Deploy**.

Setelah deploy pertama berhasil (mungkin butuh beberapa menit untuk build PHP runtime):

7. Buka project di Vercel Dashboard → **Settings** → **Domains**.
8. Tambahkan `easenikers.shop`.
9. Vercel akan kasih instruksi DNS yang harus ditambahkan di Hostinger DNS.

### Langkah DNS di Hostinger (https://hpanel.hostinger.com/domain/easenikers.shop/dns)

**Pertama, bersihkan GitHub:**
- Hapus 4 record **A**:
  - 185.199.108.153
  - 185.199.109.153
  - 185.199.110.153
  - 185.199.111.153
- Hapus CNAME yang mengarah ke GitHub jika ada.

**Kemudian tambahkan record dari Vercel** (biasanya):
- Untuk apex domain (`easenikers.shop`): tambahkan A records ke IP Vercel (Vercel akan kasih).
- Untuk `www`: CNAME ke `cname.vercel-dns.com` atau sesuai instruksi.

Atau gunakan nameservers Vercel jika Hostinger mendukung (lebih mudah untuk apex).

Tunggu propagasi DNS.

### Keterbatasan & Penyesuaian yang Mungkin Dibutuhkan

- **File Upload (gambar produk, laporan)**: Tidak bisa simpan permanen di disk Vercel (serverless). 
  Solusi: Ubah kode upload agar pakai **Supabase Storage** (bukan local folder `assets/images/`).
- **Path & Includes**: Front controller `api/index.php` mencoba emulate, tapi beberapa halaman mungkin butuh fix path (`__DIR__`, relative assets).
- **Session & State**: Harusnya kerja, tapi test login, keranjang, dll.
- **Static Assets**: Sudah di-route di vercel.json untuk css/js/gambar.

Jika banyak error setelah deploy:
- Cek Logs di Vercel dashboard.
- Mungkin perlu edit `api/index.php` atau beberapa file PHP untuk menyesuaikan `$_SERVER['DOCUMENT_ROOT']` dan path.
- Untuk production yang lebih stabil, pertimbangkan Hostinger, Railway, atau Render daripada Vercel untuk app PHP full ini.

Setelah domain terhubung dan DNS propagate, buka https://easenikers.shop — harusnya tidak lagi menampilkan README GitHub.

Test fitur utama (login, katalog, admin jika sudah setup user admin di Supabase).

Jika butuh bantuan lebih lanjut untuk fix path atau upload, kasih tau error yang muncul di Vercel logs.
