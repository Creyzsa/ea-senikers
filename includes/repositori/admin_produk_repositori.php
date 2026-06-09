<?php

declare(strict_types=1);

/**
 * Repositori admin produk — operasi CRUD untuk panel admin (PDO langsung).
 */
require_once __DIR__ . '/../auth_db/database.php';
require_once __DIR__ . '/../url_bantu.php';
require_once __DIR__ . '/../integrasi/produk_gambar_storage.php';
require_once __DIR__ . '/katalog_produk.php';

/** Ukuran default untuk produk. */
function admin_daftar_ukuran_default(): array
{
    return katalog_daftar_ukuran_default();
}

/** Kategori produk yang bisa dipilih admin (dropdown). */
function admin_daftar_kategori_produk(): array
{
    return [
        'Sneaker',
        'Sport Sneaker',
        'Running',
    ];
}

/**
 * Opsi kategori untuk form — menyertakan nilai lama jika belum ada di daftar.
 *
 * @return list<string>
 */
function admin_kategori_produk_opsi_form(string $kategori_saat_ini = ''): array
{
    $opsi = admin_daftar_kategori_produk();
    $kategori_saat_ini = trim($kategori_saat_ini);
    if ($kategori_saat_ini !== '' && !in_array($kategori_saat_ini, $opsi, true)) {
        array_unshift($opsi, $kategori_saat_ini);
    }

    return $opsi;
}

/**
 * Daftar brand unik dari tabel produk (untuk dropdown admin).
 *
 * @return list<string>
 */
function admin_daftar_brand_produk(): array
{
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->query(
            "SELECT DISTINCT TRIM(brand) AS brand
             FROM produk
             WHERE brand IS NOT NULL AND TRIM(brand) <> ''
             ORDER BY brand ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasil = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $brand = trim((string) ($row['brand'] ?? ''));
            if ($brand !== '') {
                $hasil[] = $brand;
            }
        }

        return $hasil;
    } catch (Throwable $e) {
        $brand_set = [];
        foreach (admin_produk_ambil_semua('') as $produk) {
            $brand = trim((string) ($produk['brand'] ?? ''));
            if ($brand !== '') {
                $brand_set[$brand] = true;
            }
        }
        $hasil = array_keys($brand_set);
        sort($hasil, SORT_NATURAL | SORT_FLAG_CASE);

        return $hasil;
    }
}

/** Gabungkan pilihan dropdown brand dengan input brand baru. */
function admin_produk_resolve_brand_form(string $brand_pilih, string $brand_baru): string
{
    $brand_pilih = trim($brand_pilih);
    $brand_baru = trim($brand_baru);

    if ($brand_pilih === '__baru__') {
        return $brand_baru;
    }

    if ($brand_pilih !== '') {
        return $brand_pilih;
    }

    return $brand_baru;
}

/**
 * Ubah input angka (mis. "1.500.000") menjadi integer non-negatif.
 */
function admin_produk_parse_angka(string $raw): ?int
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $normalized = preg_replace('/\D/', '', $raw);
    if ($normalized === '' || !ctype_digit($normalized)) {
        return null;
    }

    return (int) $normalized;
}

/**
 * Normalisasi array stok ukuran menjadi array dengan semua ukuran default.
 *
 * @param array<string, mixed> $stok_input
 * @return array<string, int>
 */
function admin_normalisasi_stok_ukuran(array $stok_input): array
{
    $hasil = [];
    foreach (admin_daftar_ukuran_default() as $uk) {
        $nilai = $stok_input[$uk] ?? 0;
        $hasil[$uk] = is_numeric($nilai) ? (int) $nilai : 0;
    }

    return $hasil;
}

/**
 * @return array<string, mixed>|null
 */
function admin_produk_ambil_detail(string $id_produk): ?array
{
    return katalog_ambil_produk_ber_id($id_produk);
}

/**
 * @return list<array<string, mixed>>
 */
function admin_produk_ambil_semua(string $query = ''): array
{
    $produk = katalog_ambil_semua_produk();
    $query = trim($query);
    if ($query === '') {
        return $produk;
    }
    $hasil = [];
    foreach ($produk as $p) {
        $teks = ((string) ($p['nama_produk'] ?? '')) . ' '
            . ((string) ($p['brand'] ?? '')) . ' '
            . ((string) ($p['kategori'] ?? ''));
        if (katalog_teks_cocok($teks, $query)) {
            $hasil[] = $p;
        }
    }

    return $hasil;
}

/**
 * @param array<string, mixed> $payload
 */
function admin_produk_validasi_payload(array $payload): array
{
    $nama = trim((string) ($payload['nama_produk'] ?? ''));
    $brand = trim((string) ($payload['brand'] ?? ''));
    $kategori = trim((string) ($payload['kategori'] ?? ''));
    $kondisi = trim((string) ($payload['kondisi'] ?? ''));
    $harga = (int) ($payload['harga'] ?? 0);
    $berat_gram = (int) ($payload['berat_gram'] ?? 0);
    $deskripsi = trim((string) ($payload['deskripsi'] ?? ''));

    if ($nama === '') {
        throw new RuntimeException('Nama produk wajib diisi.');
    }
    if ($brand === '') {
        throw new RuntimeException('Brand wajib diisi.');
    }
    if ($kategori === '') {
        throw new RuntimeException('Kategori wajib diisi.');
    }
    if (!in_array($kategori, admin_daftar_kategori_produk(), true)) {
        throw new RuntimeException('Kategori produk tidak valid.');
    }
    if (!in_array($kondisi, ['Baru', 'Second'], true)) {
        throw new RuntimeException('Kondisi produk tidak valid.');
    }
    if ($harga <= 0) {
        throw new RuntimeException('Harga harus lebih dari 0.');
    }
    if ($berat_gram <= 0 || $berat_gram > 50000) {
        throw new RuntimeException('Berat produk wajib antara 1 sampai 50.000 gram.');
    }

    return [
        'nama_produk' => $nama,
        'brand' => $brand,
        'kategori' => $kategori,
        'kondisi' => $kondisi,
        'harga' => $harga,
        'berat_gram' => $berat_gram,
        'deskripsi' => $deskripsi,
    ];
}

function admin_produk_pastikan_id_valid(string $id_produk): void
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_produk)) {
        throw new RuntimeException('ID produk tidak valid.');
    }
}

/**
 * @param array<string, int> $stok_ukuran
 */
function admin_produk_simpan_stok_ukuran(PDO $pdo, string $id_produk, array $stok_ukuran): void
{
    $hapus = $pdo->prepare('DELETE FROM produk_ukuran WHERE id_produk = :id');
    $hapus->execute(['id' => $id_produk]);

    $insert = $pdo->prepare(
        'INSERT INTO produk_ukuran (id_produk, ukuran, stok) VALUES (:id, :uk, :stok)'
    );
    foreach ($stok_ukuran as $ukuran => $stok) {
        $insert->execute([
            'id' => $id_produk,
            'uk' => (string) $ukuran,
            'stok' => max(0, (int) $stok),
        ]);
    }
}

/**
 * @param array<string, mixed> $payload
 * @param array<string, int> $stok_ukuran
 * @param array<string, mixed> $files
 * @return string ID produk baru
 */
function admin_produk_tambah(array $payload, array $stok_ukuran, array $files): string
{
    $data = admin_produk_validasi_payload($payload);
    $pdo = koneksi_database();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO produk (nama_produk, brand, kategori, kondisi, harga, berat_gram, deskripsi)
             VALUES (:nama, :brand, :kat, :kond, :harga, :berat, :desk)
             RETURNING id_produk'
        );
        $stmt->execute([
            'nama' => $data['nama_produk'],
            'brand' => $data['brand'],
            'kat' => $data['kategori'],
            'kond' => $data['kondisi'],
            'harga' => $data['harga'],
            'berat' => $data['berat_gram'],
            'desk' => $data['deskripsi'],
        ]);
        $row = $stmt->fetch();
        $id_produk = is_array($row) ? (string) ($row['id_produk'] ?? '') : '';
        if ($id_produk === '') {
            throw new RuntimeException('ID produk tidak diterima dari database.');
        }

        admin_produk_simpan_stok_ukuran($pdo, $id_produk, $stok_ukuran);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof RuntimeException) {
            throw $e;
        }
        $rls = database_pesan_error_rls($e);
        throw new RuntimeException($rls ?? 'Gagal menambah produk ke database.', 0, $e);
    }

    try {
        admin_produk_upload_gambar($id_produk, $files, $pdo);
    } catch (Throwable $e) {
        $rls = database_pesan_error_rls($e);
        throw new RuntimeException(
            ($rls ?? $e->getMessage()) . ' Data produk sudah tersimpan; perbaiki upload lalu tambah foto dari mode edit.',
            0,
            $e
        );
    }

    return $id_produk;
}

/**
 * @param array<string, mixed> $payload
 * @param array<string, int> $stok_ukuran
 * @param array<string, mixed> $files
 */
function admin_produk_update(string $id_produk, array $payload, array $stok_ukuran, array $files): void
{
    admin_produk_pastikan_id_valid($id_produk);
    $data = admin_produk_validasi_payload($payload);
    $pdo = koneksi_database();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'UPDATE produk
             SET nama_produk = :nama, brand = :brand, kategori = :kat, kondisi = :kond,
                 harga = :harga, berat_gram = :berat, deskripsi = :desk
             WHERE id_produk = :id'
        );
        $stmt->execute([
            'nama' => $data['nama_produk'],
            'brand' => $data['brand'],
            'kat' => $data['kategori'],
            'kond' => $data['kondisi'],
            'harga' => $data['harga'],
            'berat' => $data['berat_gram'],
            'desk' => $data['deskripsi'],
            'id' => $id_produk,
        ]);
        if ($stmt->rowCount() === 0) {
            $cek = $pdo->prepare('SELECT 1 FROM produk WHERE id_produk = :id LIMIT 1');
            $cek->execute(['id' => $id_produk]);
            if (!$cek->fetch()) {
                throw new RuntimeException('Produk tidak ditemukan.');
            }
        }

        admin_produk_simpan_stok_ukuran($pdo, $id_produk, $stok_ukuran);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof RuntimeException) {
            throw $e;
        }
        $rls = database_pesan_error_rls($e);
        throw new RuntimeException($rls ?? 'Gagal memperbarui produk.', 0, $e);
    }

    try {
        admin_produk_upload_gambar($id_produk, $files, $pdo);
    } catch (Throwable $e) {
        $rls = database_pesan_error_rls($e);
        throw new RuntimeException(
            ($rls ?? $e->getMessage()) . ' Data produk sudah diperbarui; unggah foto lagi bila perlu.',
            0,
            $e
        );
    }
}

function admin_produk_hapus(string $id_produk): void
{
    admin_produk_pastikan_id_valid($id_produk);
    $pdo = koneksi_database();

    $stmtG = $pdo->prepare('SELECT nama_file FROM produk_gambar WHERE id_produk = :id');
    $stmtG->execute(['id' => $id_produk]);
    foreach ($stmtG->fetchAll() as $g) {
        admin_produk_hapus_gambar_file((string) ($g['nama_file'] ?? ''));
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM produk_gambar WHERE id_produk = :id')->execute(['id' => $id_produk]);
        $pdo->prepare('DELETE FROM produk_ukuran WHERE id_produk = :id')->execute(['id' => $id_produk]);
        $stmt = $pdo->prepare('DELETE FROM produk WHERE id_produk = :id');
        $stmt->execute(['id' => $id_produk]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Produk tidak ditemukan.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof RuntimeException) {
            throw $e;
        }
        throw new RuntimeException('Gagal menghapus produk.', 0, $e);
    }
}

function admin_produk_hapus_gambar(string $id_produk, string $id_gambar): void
{
    admin_produk_pastikan_id_valid($id_produk);
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id_gambar)) {
        throw new RuntimeException('ID gambar tidak valid.');
    }

    $pdo = koneksi_database();
    $stmt = $pdo->prepare(
        'SELECT nama_file FROM produk_gambar WHERE id_gambar = :gid AND id_produk = :pid LIMIT 1'
    );
    $stmt->execute(['gid' => $id_gambar, 'pid' => $id_produk]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new RuntimeException('Gambar produk tidak ditemukan.');
    }

    $nama_file = (string) ($row['nama_file'] ?? '');
    $pdo->prepare('DELETE FROM produk_gambar WHERE id_gambar = :gid')->execute(['gid' => $id_gambar]);
    admin_produk_hapus_gambar_file($nama_file);
}

/**
 * @param array<string, mixed> $files
 */
function admin_produk_ada_file_gambar(array $files): bool
{
    $gambar_files = $files['gambar'] ?? [];
    if (!is_array($gambar_files) || !isset($gambar_files['name'])) {
        return false;
    }
    foreach ((array) ($gambar_files['error'] ?? []) as $err) {
        if ((int) $err !== UPLOAD_ERR_NO_FILE) {
            return true;
        }
    }

    return false;
}

/**
 * Upload gambar untuk produk (dalam transaksi PDO bila disediakan).
 *
 * @param array<string, mixed> $files
 */
function admin_produk_upload_gambar(string $id_produk, array $files, ?PDO $pdo = null): void
{
    $gambar_files = $files['gambar'] ?? [];
    if (!is_array($gambar_files) || !isset($gambar_files['name'])) {
        return;
    }

    if (admin_produk_ada_file_gambar($files) && !produk_gambar_siap_unggah()) {
        throw new RuntimeException(
            'Upload gambar belum dikonfigurasi. Set SUPABASE_SERVICE_ROLE_KEY di Vercel '
            . 'dan jalankan database/migrations/tahap5_supabase_storage_produk.sql.'
        );
    }

    $names = (array) ($gambar_files['name'] ?? []);
    $tmp_names = (array) ($gambar_files['tmp_name'] ?? []);
    $errors = (array) ($gambar_files['error'] ?? []);
    $sizes = (array) ($gambar_files['size'] ?? []);

    if (produk_gambar_pakai_cloud()) {
        $siap = supabase_storage_pastikan_bucket(produk_gambar_bucket(), true);
        if (!$siap['ok']) {
            throw new RuntimeException($siap['pesan'] !== '' ? $siap['pesan'] : 'Bucket Supabase Storage belum siap.');
        }
    }

    $pdo = $pdo ?? koneksi_database();
    $stmtUrutan = $pdo->prepare('SELECT COALESCE(MAX(urutan), -1) AS maks FROM produk_gambar WHERE id_produk = :id');
    $stmtUrutan->execute(['id' => $id_produk]);
    $urutan = (int) (($stmtUrutan->fetch()['maks'] ?? -1));
    $stmtInsert = $pdo->prepare(
        'INSERT INTO produk_gambar (id_produk, nama_file, urutan) VALUES (:pid, :nama, :urut)'
    );

    foreach ($names as $i => $name) {
        $err = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload gambar gagal (kode error ' . $err . ').');
        }

        $tmp = (string) ($tmp_names[$i] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('File gambar tidak valid.');
        }
        if ((int) ($sizes[$i] ?? 0) > 3 * 1024 * 1024) {
            throw new RuntimeException('Ukuran gambar melebihi 3MB.');
        }

        $info = @getimagesize($tmp);
        if ($info === false) {
            throw new RuntimeException('File bukan gambar yang didukung.');
        }
        $ext_map = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];
        $ext = $ext_map[$info[2]] ?? '';
        if ($ext === '') {
            throw new RuntimeException('Format gambar harus JPG, PNG, atau WEBP.');
        }

        $nama_file = 'produk_' . bin2hex(random_bytes(12)) . '.' . $ext;
        produk_gambar_simpan_tmp($tmp, $nama_file, produk_gambar_mime_dari_ekstensi($ext), true, false);

        ++$urutan;
        $stmtInsert->execute([
            'pid' => $id_produk,
            'nama' => $nama_file,
            'urut' => $urutan,
        ]);
    }
}

function admin_produk_hapus_gambar_file(string $nama_file): void
{
    produk_gambar_hapus($nama_file);
}