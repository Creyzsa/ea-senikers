<?php

declare(strict_types=1);

/**
 * Repositori laporan masalah (Report Bug) — PostgreSQL (Supabase) via PDO.
 */
require_once __DIR__ . '/../auth_db/database.php';
require_once __DIR__ . '/../url_bantu.php';

/** Folder penyimpanan screenshot laporan (di bawah web root public/). */
const LAPORAN_FOLDER_GAMBAR = 'assets/images/laporan';

/**
 * @return array<string, string> kategori => label tampil
 */
function laporan_kategori_label(): array
{
    return [
        'pesanan' => 'Pesanan',
        'pembayaran' => 'Pembayaran',
        'pengiriman' => 'Pengiriman',
        'akun' => 'Akun',
        'bug' => 'Bug Aplikasi',
    ];
}

/**
 * @return array<string, string> status => label tampil
 */
function laporan_status_label(): array
{
    return [
        'baru' => 'Baru',
        'diproses' => 'Diproses',
        'selesai' => 'Selesai',
    ];
}

/**
 * @return array<string, string> status => kelas badge
 */
function laporan_status_badge(): array
{
    return [
        'baru' => 'pesanan-badge pesanan-badge--kuning',
        'diproses' => 'pesanan-badge pesanan-badge--biru',
        'selesai' => 'pesanan-badge pesanan-badge--hijau',
    ];
}

function laporan_cek_tabel_ada(): bool
{
    try {
        $pdo = koneksi_database();
        $pdo->query('SELECT 1 FROM laporan_masalah LIMIT 1');

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function laporan_url_screenshot(string $nama_file): string
{
    $nama_file = str_replace(['/', '\\'], '', $nama_file);
    if ($nama_file === '') {
        return '';
    }

    return aplikasi_url(LAPORAN_FOLDER_GAMBAR . '/' . rawurlencode($nama_file));
}

/**
 * Proses upload screenshot opsional.
 *
 * @param array<string, mixed> $files $_FILES
 * @return array{nama_file: ?string, error: ?string}
 */
function laporan_upload_screenshot(array $files, string $field = 'screenshot'): array
{
    $f = $files[$field] ?? null;
    if (!is_array($f) || !isset($f['error'])) {
        return ['nama_file' => null, 'error' => null];
    }

    $kode = (int) $f['error'];
    if ($kode === UPLOAD_ERR_NO_FILE) {
        return ['nama_file' => null, 'error' => null];
    }
    if ($kode !== UPLOAD_ERR_OK) {
        return ['nama_file' => null, 'error' => 'Gagal mengunggah gambar. Coba lagi.'];
    }

    $tmp = (string) ($f['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['nama_file' => null, 'error' => 'Berkas gambar tidak valid.'];
    }
    if ((int) ($f['size'] ?? 0) > 3 * 1024 * 1024) {
        return ['nama_file' => null, 'error' => 'Ukuran gambar maksimal 3MB.'];
    }

    $info = @getimagesize($tmp);
    if ($info === false) {
        return ['nama_file' => null, 'error' => 'Berkas harus berupa gambar (JPG, PNG, atau WEBP).'];
    }
    $ext_map = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    $ext = $ext_map[$info[2]] ?? '';
    if ($ext === '') {
        return ['nama_file' => null, 'error' => 'Format gambar harus JPG, PNG, atau WEBP.'];
    }

    $dir = dirname(__DIR__, 2) . '/public/' . LAPORAN_FOLDER_GAMBAR;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['nama_file' => null, 'error' => 'Folder penyimpanan tidak tersedia.'];
    }

    $nama_file = uniqid('lapor_', true) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $nama_file)) {
        return ['nama_file' => null, 'error' => 'Gagal menyimpan gambar.'];
    }

    return ['nama_file' => $nama_file, 'error' => null];
}

/**
 * Simpan laporan baru. Mengembalikan ID laporan atau null bila gagal.
 */
function laporan_simpan(int $user_id, string $kategori, string $deskripsi, ?string $screenshot): ?int
{
    $kategori = trim($kategori);
    $deskripsi = trim($deskripsi);
    if (!array_key_exists($kategori, laporan_kategori_label()) || $deskripsi === '') {
        return null;
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'INSERT INTO laporan_masalah (user_id, kategori, deskripsi, screenshot, status, created_at)
             VALUES (:uid, :kat, :desk, :ss, :st, NOW())
             RETURNING id'
        );
        $stmt->execute([
            'uid' => $user_id > 0 ? $user_id : null,
            'kat' => $kategori,
            'desk' => $deskripsi,
            'ss' => $screenshot !== null && $screenshot !== '' ? $screenshot : null,
            'st' => 'baru',
        ]);
        $row = $stmt->fetch();

        return $row ? (int) $row['id'] : null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Admin: daftar laporan dengan filter status & pencarian opsional.
 *
 * @return list<array<string, mixed>>
 */
function laporan_admin_daftar(?string $filter_status = null, string $q = ''): array
{
    $filter_status = $filter_status !== null ? trim(strtolower($filter_status)) : null;
    if ($filter_status === '' || ($filter_status !== null && !array_key_exists($filter_status, laporan_status_label()))) {
        $filter_status = null;
    }
    $q = trim($q);

    try {
        $pdo = koneksi_database();
        $sql = 'SELECT l.id, l.user_id, l.kategori, l.deskripsi, l.screenshot, l.status, l.created_at,
                       u.username, u.email
                FROM laporan_masalah l
                LEFT JOIN users u ON u.id = l.user_id';
        $where = [];
        $params = [];
        if ($filter_status !== null) {
            $where[] = 'l.status = :st';
            $params['st'] = $filter_status;
        }
        if ($q !== '') {
            $where[] = '(l.deskripsi ILIKE :q OR u.username ILIKE :q OR u.email ILIKE :q OR l.id::text LIKE :qid)';
            $params['q'] = '%' . $q . '%';
            $params['qid'] = '%' . $q . '%';
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY l.created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array<string, mixed>|null
 */
function laporan_admin_detail(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT l.id, l.user_id, l.kategori, l.deskripsi, l.screenshot, l.status, l.created_at,
                    u.username, u.email
             FROM laporan_masalah l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function laporan_admin_ubah_status(int $id, string $status): bool
{
    if ($id <= 0 || !array_key_exists($status, laporan_status_label())) {
        return false;
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('UPDATE laporan_masalah SET status = :s WHERE id = :id');

        return $stmt->execute(['s' => $status, 'id' => $id]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @return array<string, int> status => jumlah
 */
function laporan_admin_hitung_per_status(): array
{
    $basis = [];
    foreach (array_keys(laporan_status_label()) as $k) {
        $basis[$k] = 0;
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->query('SELECT status, COUNT(*)::int AS jumlah FROM laporan_masalah GROUP BY status');
        foreach ($stmt ? $stmt->fetchAll() : [] as $r) {
            $s = (string) ($r['status'] ?? '');
            if ($s !== '' && array_key_exists($s, $basis)) {
                $basis[$s] = (int) ($r['jumlah'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        return $basis;
    }

    return $basis;
}
