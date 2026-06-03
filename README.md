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

## Cara Menjalankan (Lokal)

### 1. Clone Repository

```bash
git clone https://github.com/Creyzsa/ea-senikers.git
cd ea-senikers
```

### 2. Pasang di Laragon / XAMPP

Letakkan folder `ea-senikers` di direktori `www` Laragon (mis. `D:\laragon\www\EASENIKERS`) atau `htdocs` XAMPP. Pastikan Apache & PHP aktif.

---

## Deployment ke Production (Hostinger) — Paling Penting

**JANGAN pakai GitHub Pages** untuk project ini (GitHub Pages tidak bisa jalanin PHP).

### Langkah 1: Siapkan Repo (sudah dilakukan di branch main)

- File `CNAME` sudah dihapus dari repo (supaya GitHub Pages tidak klaim domain).
- Ada file `config.example.php` sebagai template.
- Ada `.htaccess` di dalam folder `public/`.

### Langkah 2: Download Kode

1. Di GitHub repo → klik **Code** → **Download ZIP** (pastikan branch **main**).
2. Extract ZIP.

### Langkah 3: Buat `config.php`

1. Copy `config.example.php` → rename menjadi `config.php`.
2. Buka `config.php` dan isi dengan data asli dari Supabase kamu.
3. Ubah baris ini untuk production:

```php
define('URL_APLIKASI', 'https://easenikers.shop');
```

**JANGAN upload config.php ke GitHub.**

### Langkah 4: Upload ke Hostinger via File Manager

1. Login hPanel Hostinger.
2. Buka **File Manager**.
3. Masuk ke `public_html`.
4. Buat folder baru: `easenikers` (atau nama lain).
5. Upload seluruh folder hasil extract ke dalam `public_html/easenikers`.
6. Extract ZIP tersebut di sana.

Struktur yang benar setelah upload:

```
public_html/
└── easenikers/
    ├── public/               ← ini jadi Document Root
    │   ├── .htaccess
    │   ├── index.php
    │   ├── admin/
    │   ├── pembeli/
    │   └── assets/
    ├── includes/
    ├── database/
    ├── config.php            ← file rahasia yang kamu buat
    └── ...
```

### Langkah 5: Atur Document Root (SANGAT PENTING)

1. Di hPanel, buka **Hosting** → pilih paket hosting kamu.
2. Klik **Manage** pada website / domain `easenikers.shop`.
3. Cari **Document Root** atau **Root Directory**.
4. Ubah menjadi:
   ```
   public_html/easenikers/public
   ```
5. Save.

Tunggu 30-60 detik, lalu test buka https://easenikers.shop

### Langkah 6: Atur DNS di Hostinger (https://hpanel.hostinger.com/domain/easenikers.shop/dns)

Karena sebelumnya pakai GitHub Pages, kemungkinan besar DNS masih pakai A record GitHub.

**Yang harus kamu lakukan di tab DNS Records:**

- Hapus / Edit semua record **A** yang nilainya:
  - 185.199.108.153
  - 185.199.109.153
  - 185.199.110.153
  - 185.199.111.153

- Hapus record **CNAME** yang mengarah ke `*.github.io` (jika ada).

Setelah dihapus:

- Hostinger biasanya otomatis menambahkan record yang benar untuk hosting mereka.
- Atau kamu bisa klik **"Reset to default"** / **"Default records"** jika tersedia.
- Pastikan ada record **A** atau **CNAME** yang mengarah ke IP hosting Hostinger kamu (bisa dilihat di detail hosting).

Jika domain belum terhubung ke hosting:
- Pergi ke **Hosting** → **Websites** → tambahkan / hubungkan domain `easenikers.shop` ke paket hosting.

**Catatan TTL:** Perubahan DNS bisa butuh 5 menit sampai 4 jam untuk propagate.

### Langkah 7: Jalankan Migration SQL

Buka Supabase → SQL Editor, jalankan semua file di `database/migrations/` (termasuk `tahap3_laporan_masalah.sql`).

### Langkah 8: Supabase Auth Configuration (WAJIB)

Pergi ke Supabase Dashboard → **Authentication** → **URL Configuration**:

- **Site URL**: `https://easenikers.shop`
- **Redirect URLs**: tambahkan
  ```
  https://easenikers.shop/**
  ```

### Langkah 9: Test & Buat Admin

- Buka https://easenikers.shop
- Daftar akun baru.
- Di Supabase Table Editor → tabel `users`, ubah kolom `role` user tersebut menjadi `admin`.

### Troubleshooting Umum di Hostinger

- Masih muncul README → berarti Document Root belum diarahkan ke `/public` atau DNS masih ke GitHub.
- 500 Internal Server Error → cek `config.php` sudah benar, dan PHP version minimal 8.1.
- Tidak bisa upload gambar → pastikan folder `public/assets/images/produk` dan `public/assets/images/laporan` permissionnya 755 atau 775.
- Halaman kosong → pastikan `config.php` sudah di-upload di root project (satu level di atas `public/`).

---

**Selesai.** Setelah semua langkah di atas, domain kamu akan menjalankan aplikasi PHP dengan benar.

### 3. Buat Project Supabase

1. Daftar di [supabase.com](https://supabase.com/) (gratis).
2. Buat project baru, pilih region terdekat (mis. Singapore).
3. Catat: **Project URL**, **anon key**, dan kredensial koneksi PostgreSQL (Host, Port, Database, User, Password) — terlihat di Project Settings → Database.

### 4. Konfigurasi `config.php` (Lokal)

Salin `config.example.php` menjadi `config.php`, lalu isi dengan kredensial Supabase lokal kamu.

> **Catatan:** `config.php` sudah di-`.gitignore` agar kredensial tidak ikut ter-push ke GitHub. Lihat bagian **Deployment ke Hostinger** di bawah untuk panduan production.

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
