# EA SENIKERS

Website e-commerce penjualan sneakers (baru & preloved) berbasis PHP Native dan Supabase. Project tugas yang dibuat untuk membantu proses penjualan sepatu secara online, mulai dari pendaftaran pengguna, katalog produk, keranjang belanja, hingga pengelolaan pesanan oleh admin.

**Status:** Beroperasi. Ongkir via RajaOngkir (Komerce) aktif, pembayaran Pakasir terintegrasi, plus ulasan produk, wishlist, chat toko, laporan masalah, dan notifikasi admin (Telegram + Web Push).

> **Catatan setup database:** file SQL skema/migration sengaja **tidak disertakan** di repo agar tampilan root tetap bersih. Jalankan skema Supabase secara manual lewat SQL Editor (lihat [Setup Database](#setup-database)).

---

## Daftar Isi

- [Fitur](#fitur)
- [Teknologi](#teknologi)
- [Anggota Tim](#anggota-tim)
- [Cara Menjalankan](#cara-menjalankan)
- [Struktur Folder](#struktur-folder)
- [Setup Database](#setup-database)
- [Dependensi Eksternal](#dependensi-eksternal)
- [Roadmap](#roadmap)

---

## Fitur

### Area Pembeli

- Pendaftaran & login (terhubung Supabase Auth, konfirmasi email + reset sandi)
- Beranda dengan produk rekomendasi & terlaris
- Katalog produk dengan filter (merek, kondisi, harga) & pencarian (saran cari)
- Halaman Kategori (jelajah per merek atau kondisi)
- Detail produk:
  - Lightbox zoom untuk foto produk
  - Indikator stok rendah per ukuran (≤3 tersisa)
  - Label "Preloved" konsisten di seluruh situs
  - Ulasan & rating produk (hanya pembeli yang sudah membeli)
- Keranjang belanja dengan ringkasan subtotal
- Wishlist produk
- Checkout dengan ongkir RajaOngkir (multi-kurir) + pembayaran Pakasir (QRIS, VA, PayPal)
- Halaman Pesanan: daftar + filter status + detail pesanan, tombol bayar & "Hubungi toko via WhatsApp"
- Chat ke toko & lapor masalah pesanan
- Profil pengiriman lengkap di halaman Akun:
  - Cascading dropdown alamat (provinsi → kota → kecamatan) dari API BPS
  - Peta interaktif untuk menentukan titik lokasi (Leaflet + OpenStreetMap)
  - Tombol "Lokasi saya" via Geolocation browser
- Halaman Tentang, Bantuan, dan tips merawat sneakers

### Area Admin

- Dashboard dengan statistik (pendapatan, pesanan, produk aktif, pengguna), grafik, dan aktivitas terbaru
- Manajemen Produk: CRUD lengkap, upload multi-foto (Supabase Storage), stok per ukuran (EU 36–45), input berat untuk perhitungan ongkir
- Manajemen Brand: upload logo/ikon brand
- Manajemen Pesanan: filter status, pencarian, transisi status berjenjang (pending → paid → processed → shipped → completed), input nomor resi, pembatalan dengan CSRF
- Detail pesanan dengan informasi kontak pembeli (nama, email, no HP, tombol WhatsApp)
- Laporan penjualan & daftar pengguna
- Notifikasi pesanan/pembayaran masuk via Telegram bot dan Web Push browser
- Pengaturan toko: identitas, kontak WhatsApp, API key RajaOngkir & lokasi asal, kredensial Pakasir
- Cek Ongkir RajaOngkir: tes koneksi, cari ID lokasi, simulasi tarif multi-kurir

---

## Teknologi

- **Backend:** PHP 8.x (native, tanpa framework)
- **Database:** PostgreSQL via [Supabase](https://supabase.com/)
- **Auth:** Supabase Auth (email + password, konfirmasi email)
- **Storage:** Supabase Storage (gambar produk & logo brand)
- **Ongkir:** RajaOngkir (platform Komerce) — multi-kurir
- **Pembayaran:** Pakasir (QRIS, Virtual Account, PayPal)
- **Notifikasi admin:** Telegram Bot API + Web Push (VAPID)
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

### 5. Siapkan Skema Database

Buka **Supabase Dashboard → SQL Editor → New query**, lalu buat skema tabel inti (`users`, `produk`, `produk_ukuran`, `produk_gambar`, `orders`, `order_items`, `ulasan`, `wishlist`, dll) sesuai kebutuhan aplikasi.

> File SQL skema/migration tidak disertakan di repo agar root tetap bersih (lihat [Setup Database](#setup-database)). Beberapa kolom pelengkap (mis. `orders.stok_dipotong`, pelebaran `orders.destination_id`) di-migrasi otomatis oleh aplikasi saat runtime.

### 6. Buat Akun Admin

Daftar lewat halaman `/login/daftar.php` dengan email apa saja, lalu di Supabase **Table Editor → users**, ubah kolom `role` baris user tersebut dari `pembeli` menjadi `admin`.

### 7. Buka di Browser

Akses `http://localhost/EASENIKERS/public/` dan masuk dengan akun yang sudah dibuat.

---

## Struktur Folder

Project disusun dengan pemisahan tegas antara **backend logic** (`includes/`)
dan **document root web** (`public/`). Folder `includes/` dipecah menjadi
subfolder bertema agar mudah dilacak.

```
EASENIKERS/
├── config.php                       # kredensial Supabase & API key (gitignored)
├── config.example.php               # contoh konfigurasi
├── vercel.json                      # konfigurasi deploy Vercel (routing + env)
├── serve.bat                        # launcher dev lokal (php -S localhost:8080)
├── README.md                        # dokumentasi ini
│
├── api/
│   └── index.php                    # front controller untuk Vercel (serverless)
│
├── scripts/                         # utilitas maintenance CLI (sekali jalan)
│   ├── cek_gambar_produk.php
│   └── migrasi_gambar_lokal_ke_supabase.php
│
├── includes/                        # backend PHP (di-require oleh public/)
│   │
│   ├── auth_db/                     # AUTENTIKASI, KONEKSI DB & STORAGE
│   │   ├── sesi.php                 # sesi login, cookie ingat-saya
│   │   ├── database.php             # koneksi PDO ke Supabase Postgres
│   │   ├── supabase_auth.php        # wrapper Supabase Auth (signup/login)
│   │   ├── supabase_rest.php        # wrapper PostgREST untuk CRUD
│   │   └── supabase_storage.php     # wrapper Supabase Storage (upload)
│   │
│   ├── repositori/                  # AKSES DATA (query & CRUD per fitur)
│   │   ├── katalog_produk.php
│   │   ├── pesanan_repositori.php
│   │   ├── profil_pembeli_repositori.php
│   │   ├── laporan_repositori.php
│   │   ├── brand_logo_repositori.php
│   │   └── admin_*_repositori.php   # dashboard, produk, pengguna, brand, notifikasi, pengaturan
│   │
│   ├── integrasi/                   # API EKSTERNAL
│   │   ├── rajaongkir.php           # ongkir RajaOngkir (Komerce)
│   │   ├── jne_destinasi_populer.php# fallback destinasi populer
│   │   ├── pakasir.php              # payment gateway Pakasir
│   │   ├── produk_gambar_storage.php / brand_logo_storage.php
│   │   └── notifikasi_*.php         # Telegram, Web Push, email SMTP
│   │
│   ├── konfigurasi/                 # KONFIGURASI STATIS (dibaca halaman)
│   │   ├── kontak_toko.php
│   │   ├── merek_ringkas.php
│   │   └── deskripsi_merek_login.php
│   │
│   ├── komponen/                    # potongan UI reusable (nav, bilah, favicon)
│   ├── config_loader.php            # muat config.php / env Vercel
│   ├── bilah_pembeli.php            # header pembeli (sticky nav)
│   ├── keranjang_sesi.php / checkout_sesi.php / paginasi.php / url_bantu.php
│   └── pengaturan_toko_admin.json   # config disimpan admin (gitignored)
│
└── public/                          # DOCUMENT ROOT WEB SERVER
    ├── index.php                    # entry point
    ├── router.php                   # router untuk php -S (dev lokal)
    ├── .htaccess                    # rewrite Apache (Laragon/XAMPP)
    ├── sw-admin-notifikasi.js       # service worker Web Push admin
    │
    ├── login/                       # daftar, masuk, reset sandi, konfirmasi token
    ├── pembeli/                     # halaman pembeli (beranda, katalog, checkout, dll)
    ├── admin/                       # panel admin (produk, pesanan, cek ongkir, dll)
    │
    ├── api/                         # endpoint JSON & webhook
    │   ├── payment_callback.php     # webhook Pakasir
    │   ├── telegram_webhook.php     # webhook Telegram bot
    │   ├── wishlist-toggle.php / cari-saran.php
    │   └── admin_notifikasi_*.php / admin_push_*.php
    │
    └── assets/                      # css, js, images, sounds
```

**Aturan penamaan subfolder includes/:**
- `auth_db/` — kredensial, koneksi DB, dan Storage
- `repositori/` — fungsi yang **membaca/menulis data**
- `integrasi/` — wrapper API eksternal (RajaOngkir, Pakasir, notifikasi)
- `konfigurasi/` — file statis yang **dibaca** oleh halaman (alamat, copy)
- `komponen/` — potongan UI yang dipakai berulang
- Root `includes/` — utilitas lintas-modul (helper, sesi/state, loader config)

---

## Setup Database

File SQL skema/migration **tidak disertakan di repo** agar tampilan root tetap bersih. Skema dibuat manual di Supabase. Tabel inti yang dipakai aplikasi:

| Tabel | Fungsi |
|---|---|
| `users` | Akun + profil pengiriman (`no_hp`, `nama_penerima`, `provinsi`, `kota`, `kecamatan`, `kode_pos`, `alamat_detail`, `lat`, `lng`) |
| `produk` | Data produk + `berat_gram` untuk perhitungan ongkir |
| `produk_ukuran` | Stok per ukuran (EU 36–45) |
| `produk_gambar` | Multi-foto produk (path di Supabase Storage) |
| `orders` | Pesanan + pengiriman (`kurir`, `layanan`, `ongkir`, `destination_id`, `nomor_resi`, `stok_dipotong`) |
| `order_items` | Item per pesanan |
| `ulasan` | Rating & komentar produk (per pesanan) |
| `wishlist` | Produk favorit pembeli |
| Tabel pendukung | laporan masalah, chat, notifikasi admin, brand logo |

Catatan:
- Untuk CRUD produk lewat REST (anon key), Row Level Security pada `produk`, `produk_gambar`, `produk_ukuran` perlu dimatikan/diatur sesuai kebijakan.
- Sebagian kolom pelengkap di-migrasi otomatis oleh aplikasi saat runtime (mis. `orders.stok_dipotong`, pelebaran tipe `orders.destination_id` ke `VARCHAR`).
- Untuk Storage gambar produk, set `SUPABASE_SERVICE_ROLE_KEY`, lalu (opsional) jalankan `php scripts/migrasi_gambar_lokal_ke_supabase.php` untuk mengunggah gambar lokal ke bucket.

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

Pondasi data & UX:

- Skema database lengkap (produk, ukuran, gambar, orders, users, profil pengiriman)
- Admin CRUD produk + upload multi-foto ke Supabase Storage
- Profil pengiriman pembeli dengan cascading dropdown alamat & peta titik lokasi
- Polish UX lengkap pada area pembeli & admin

### ✅ Tahap 2 — Selesai

Integrasi pengiriman & pembayaran:

- **RajaOngkir** (Komerce API) — wrapper, admin tool cek koneksi, search destinasi, hitung ongkir multi-kurir, integrasi penuh di checkout (auto-pick destinasi dari profil, fallback manual)
- Checkout dengan create order ke database (kurir, layanan, ongkir, destination_id)
- Form admin input nomor resi saat status pesanan berubah ke "Dikirim"
- **Pakasir** payment gateway — konfigurasi di Pengaturan admin, bayar dari detail pesanan, webhook `api/payment_callback.php`

### ✅ Tahap 3 — Selesai

Fitur interaksi & operasional:

- Ulasan & rating produk per pesanan
- Wishlist produk
- Chat ke toko & lapor masalah pesanan
- Notifikasi admin otomatis: Telegram bot + Web Push browser (pesanan/pembayaran masuk)
- Laporan penjualan admin

### 🔜 Tahap 4 — Lanjutan

- Notifikasi WhatsApp/email otomatis ke pembeli (pembayaran berhasil, pengiriman)
- Export pesanan ke Excel/PDF
- Halaman tracking resi otomatis
- Auto-resolve `destination_id` RajaOngkir dari koordinat peta profil pembeli (checkout langsung hitung tanpa pilih kelurahan)

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
     - URL_APLIKASI=https://www.easenikers.shop   (use your full custom domain with https)
     - SUPABASE_SERVICE_ROLE_KEY   (untuk upload gambar ke Supabase Storage)
     - RAJAONGKIR_API_KEY, RAJAONGKIR_ORIGIN_ID   (ongkir RajaOngkir Komerce; origin contoh 48850)
     - PAKASIR_PROJECT_SLUG, PAKASIR_API_KEY, PAKASIR_MODE, PAKASIR_METODE_DEFAULT   (pembayaran)
     - (optional) PAYMENT_CALLBACK_SECRET, EMAIL_DRIVER, EMAIL_PENGIRIM, TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID
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
