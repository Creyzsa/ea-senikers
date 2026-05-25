<?php
/**
 * Koneksi ke database Supabase (PostgreSQL) memakai PDO.
 * Bagian teknis (PDO, dsn) tetap bahasa Inggris karena nama resmi fitur PHP.
 */

require_once __DIR__ . '/../../config.php';

function koneksi_database(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // sslmode=require biasanya dibutuhkan Supabase
    $dsn = 'pgsql:host=' . DB_HOST
        . ';port=' . DB_PORT
        . ';dbname=' . DB_NAME
        . ';sslmode=require';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

/**
 * Tambahkan baris di tabel `users` (PostgreSQL) agar sama dengan akun Supabase Auth.
 * Sandi tetap di Supabase — kolom password_hash diisi hash acak (tidak dipakai untuk login).
 */
function users_sinkron_dari_supabase(PDO $pdo, string $email, string $username): void
{
    $email = strtolower(trim($email));
    $username = trim($username);
    if ($email === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $cek = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:e) LIMIT 1');
    $cek->execute(['e' => $email]);
    if ($cek->fetch()) {
        return;
    }

    $hash_dummy = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    try {
        $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role) VALUES (:u, :e, :h, :r)'
        )->execute([
            'u' => $username,
            'e' => $email,
            'h' => $hash_dummy,
            'r' => 'pembeli',
        ]);
    } catch (PDOException $e) {
        $sqlstate = $e->errorInfo[0] ?? '';
        if ($sqlstate === '23505') {
            return;
        }
        throw $e;
    }
}
