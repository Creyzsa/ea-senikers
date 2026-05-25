<?php

declare(strict_types=1);

/**
 * Repositori profil pembeli — baca & simpan no HP, alamat default,
 * dan titik koordinat (lat/lng) yang nantinya dipakai sebagai pre-fill
 * saat checkout.
 *
 * Kolom yang dikelola di tabel `users`:
 *   no_hp, nama_penerima, provinsi, kota, kecamatan, kode_pos,
 *   alamat_detail, lat, lng
 */
require_once __DIR__ . '/../auth_db/database.php';

/**
 * @return array{
 *   no_hp: string,
 *   nama_penerima: string,
 *   provinsi: string,
 *   kota: string,
 *   kecamatan: string,
 *   kode_pos: string,
 *   alamat_detail: string,
 *   lat: string,
 *   lng: string
 * }
 */
function profil_pembeli_kosong(): array
{
    return [
        'no_hp' => '',
        'nama_penerima' => '',
        'provinsi' => '',
        'kota' => '',
        'kecamatan' => '',
        'kode_pos' => '',
        'alamat_detail' => '',
        'lat' => '',
        'lng' => '',
    ];
}

/**
 * Ambil profil pengiriman milik user. Jika user tidak ditemukan
 * atau kolom belum ada di DB, kembalikan struktur kosong.
 *
 * @return array<string, string>
 */
function profil_pembeli_ambil(int $user_id): array
{
    if ($user_id <= 0) {
        return profil_pembeli_kosong();
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT no_hp, nama_penerima, provinsi, kota, kecamatan, kode_pos, alamat_detail, lat, lng
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $user_id]);
        $baris = $stmt->fetch();
        if (!$baris) {
            return profil_pembeli_kosong();
        }
        return [
            'no_hp' => (string) ($baris['no_hp'] ?? ''),
            'nama_penerima' => (string) ($baris['nama_penerima'] ?? ''),
            'provinsi' => (string) ($baris['provinsi'] ?? ''),
            'kota' => (string) ($baris['kota'] ?? ''),
            'kecamatan' => (string) ($baris['kecamatan'] ?? ''),
            'kode_pos' => (string) ($baris['kode_pos'] ?? ''),
            'alamat_detail' => (string) ($baris['alamat_detail'] ?? ''),
            'lat' => $baris['lat'] !== null ? (string) $baris['lat'] : '',
            'lng' => $baris['lng'] !== null ? (string) $baris['lng'] : '',
        ];
    } catch (Throwable $e) {
        return profil_pembeli_kosong();
    }
}

/**
 * Validasi input profil. Kembalikan list pesan error (kosong = valid).
 *
 * @param array<string, string> $input
 * @return list<string>
 */
function profil_pembeli_validasi(array $input): array
{
    $errors = [];

    $no_hp_bersih = preg_replace('/\D+/', '', (string) ($input['no_hp'] ?? ''));
    if ($no_hp_bersih === '') {
        $errors[] = 'Nomor HP wajib diisi.';
    } elseif (strlen($no_hp_bersih) < 8 || strlen($no_hp_bersih) > 16) {
        $errors[] = 'Nomor HP harus 8 sampai 16 digit angka.';
    }

    if (trim((string) ($input['nama_penerima'] ?? '')) === '') {
        $errors[] = 'Nama penerima wajib diisi.';
    }
    if (trim((string) ($input['provinsi'] ?? '')) === '') {
        $errors[] = 'Provinsi wajib diisi.';
    }
    if (trim((string) ($input['kota'] ?? '')) === '') {
        $errors[] = 'Kota / kabupaten wajib diisi.';
    }
    if (trim((string) ($input['kecamatan'] ?? '')) === '') {
        $errors[] = 'Kecamatan wajib diisi.';
    }

    $kode_pos = trim((string) ($input['kode_pos'] ?? ''));
    if ($kode_pos !== '' && !preg_match('/^\d{4,6}$/', $kode_pos)) {
        $errors[] = 'Kode pos harus berupa 4 sampai 6 digit angka.';
    }

    if (trim((string) ($input['alamat_detail'] ?? '')) === '') {
        $errors[] = 'Alamat detail wajib diisi.';
    }

    $lat_raw = trim((string) ($input['lat'] ?? ''));
    $lng_raw = trim((string) ($input['lng'] ?? ''));
    if (($lat_raw === '') !== ($lng_raw === '')) {
        $errors[] = 'Titik peta tidak lengkap, ulangi pilih lokasi di peta.';
    } else {
        if ($lat_raw !== '') {
            if (!is_numeric($lat_raw) || (float) $lat_raw < -90 || (float) $lat_raw > 90) {
                $errors[] = 'Latitude titik peta tidak valid.';
            }
            if (!is_numeric($lng_raw) || (float) $lng_raw < -180 || (float) $lng_raw > 180) {
                $errors[] = 'Longitude titik peta tidak valid.';
            }
        }
    }

    return $errors;
}

/**
 * Simpan profil pengiriman. Mengembalikan true jika berhasil.
 *
 * @param array<string, string> $input
 */
function profil_pembeli_simpan(int $user_id, array $input): bool
{
    if ($user_id <= 0) {
        return false;
    }
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'UPDATE users SET
                no_hp = :no_hp,
                nama_penerima = :nama_penerima,
                provinsi = :provinsi,
                kota = :kota,
                kecamatan = :kecamatan,
                kode_pos = :kode_pos,
                alamat_detail = :alamat_detail,
                lat = :lat,
                lng = :lng
             WHERE id = :id'
        );

        $lat_raw = trim((string) ($input['lat'] ?? ''));
        $lng_raw = trim((string) ($input['lng'] ?? ''));
        $lat = $lat_raw !== '' && is_numeric($lat_raw) ? (float) $lat_raw : null;
        $lng = $lng_raw !== '' && is_numeric($lng_raw) ? (float) $lng_raw : null;

        return $stmt->execute([
            'no_hp' => preg_replace('/\D+/', '', (string) ($input['no_hp'] ?? '')),
            'nama_penerima' => trim((string) ($input['nama_penerima'] ?? '')),
            'provinsi' => trim((string) ($input['provinsi'] ?? '')),
            'kota' => trim((string) ($input['kota'] ?? '')),
            'kecamatan' => trim((string) ($input['kecamatan'] ?? '')),
            'kode_pos' => trim((string) ($input['kode_pos'] ?? '')),
            'alamat_detail' => trim((string) ($input['alamat_detail'] ?? '')),
            'lat' => $lat,
            'lng' => $lng,
            'id' => $user_id,
        ]);
    } catch (Throwable $e) {
        return false;
    }
}
