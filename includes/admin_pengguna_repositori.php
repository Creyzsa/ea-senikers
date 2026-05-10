<?php

declare(strict_types=1);

/**
 * Pengguna untuk panel admin — tabel PostgreSQL users.
 */
require_once __DIR__ . '/database.php';

/**
 * @param string|null $q Pencarian di username/email (kosong = semua)
 * @param int $batas Angka makslimal daftar rows
 *
 * @return list<array<string, mixed>>
 */
function admin_pengguna_ambil_daftar(?string $q = null, int $batas = 500): array
{
    $batas = max(10, min(5000, $batas));

    try {
        $pdo = koneksi_database();
        $q = $q !== null ? trim($q) : '';

        if ($q === '') {
            $stmt = $pdo->prepare('SELECT id, username, email, role FROM users ORDER BY id DESC LIMIT :lim');
            $stmt->bindValue(':lim', $batas, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        }

        $like = '%' . $q . '%';
        $stmt = $pdo->prepare(
            'SELECT id, username, email, role FROM users
             WHERE username ILIKE :q OR email ILIKE :q
             ORDER BY id DESC LIMIT :lim'
        );
        $stmt->bindValue(':q', $like, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $batas, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
