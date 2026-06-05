<?php
require_once __DIR__ . '/../url_bantu.php';

/**
 * Sesi dipakai untuk menyimpan status masuk (siapa yang sedang login).
 * Nama fungsi pakai bahasa Indonesia supaya mudah dibaca.
 *
 * "Tetap masuk" (ingat saya): cookie pembantu + masa hidup cookie sesi panjang,
 * sehingga pengguna tetap login setelah menutup browser sampai logout.
 */

/** Nama cookie pembantu jika pengguna memilih tetap masuk (~30 hari). */
const EASENIKERS_COOKIE_INGAT = 'easenikers_ingat';

/** Durasi tetap masuk dalam detik (30 hari). */
const EASENIKERS_SESI_INGAT_DETIK = 60 * 60 * 24 * 30;

/** Kunci $_SESSION untuk token reset sandi (alur email Supabase). */
const EASENIKERS_SESI_RESET_SANDI = 'easenikers_reset_sandi_sb';

/**
 * Simpan token reset sandi lalu redirect ke form (tanpa regenerate_id agar cookie tidak hilang).
 */
function sesi_simpan_reset_sandi_lalu_ke_form(string $access_token, string $refresh_token, string $email): void
{
    $_SESSION[EASENIKERS_SESI_RESET_SANDI] = [
        'access_token' => $access_token,
        'refresh_token' => $refresh_token,
        'email' => $email,
        'ts' => time(),
    ];
    session_write_close();
    header('Location: ' . aplikasi_url_auth('login/setel_sandi_baru.php'), true, 303);
    exit;
}

function easenikers_konfirmasi_https(): bool
{
    // Support for proxies like Vercel, Cloudflare, etc.
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
}

if (session_status() === PHP_SESSION_NONE) {
    $tetap_masuk = !empty($_COOKIE[EASENIKERS_COOKIE_INGAT]) && $_COOKIE[EASENIKERS_COOKIE_INGAT] === '1';
    // Hanya perpanjang umur file sesi di server jika mode "tetap masuk" — bukan untuk semua pengunjung.
    if ($tetap_masuk) {
        ini_set('session.gc_maxlifetime', (string) EASENIKERS_SESI_INGAT_DETIK);
    }

    $lifetime = $tetap_masuk ? EASENIKERS_SESI_INGAT_DETIK : 0;
    $path_sesi = easenikers_path_cookie_sesi();

    $secure_cookie = easenikers_konfirmasi_https() || (isset($_SERVER['HTTP_HOST']) && strpos((string)$_SERVER['HTTP_HOST'], 'easenikers.shop') !== false);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => $path_sesi,
        'domain' => '',
        'secure' => $secure_cookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Dipanggil setelah login atau daftar berhasil jika pengguna mencentang "tetap masuk".
 * Memperbarui cookie sesi dan cookie pembantu agar sesuai pilihan.
 */
function sesi_terapkan_tetap_masuk(bool $ingat): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $p = session_get_cookie_params();
    $path = $p['path'] !== '' ? $p['path'] : '/';
    $domain = $p['domain'] ?? '';
    $secure = !empty($p['secure']) ? (bool) $p['secure'] : easenikers_konfirmasi_https();
    $httponly = (bool) ($p['httponly'] ?? true);
    $dasar = [
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax',
    ];

    if ($ingat) {
        $exp = time() + EASENIKERS_SESI_INGAT_DETIK;
        setcookie(EASENIKERS_COOKIE_INGAT, '1', array_merge($dasar, ['expires' => $exp]));
        setcookie(session_name(), session_id(), array_merge($dasar, ['expires' => $exp]));
    } else {
        setcookie(EASENIKERS_COOKIE_INGAT, '', array_merge($dasar, ['expires' => time() - 3600]));
        setcookie(session_name(), session_id(), array_merge($dasar, ['expires' => 0]));
    }
}

/**
 * Login/daftar **tanpa** "tetap masuk": mulai sesi baru dengan cookie sesi-saja (tutup browser = tidak login).
 * Wajib dipakai di sini karena bila cookie `easenikers_ingat` masih ada dari sebelumnya, sesi bisa sudah
 * terbuka dengan masa hidup panjang sebelum `session_start()` — centang tidak terpakai.
 *
 * @param array{id_pengguna: int, nama_pengguna: string, peran: string, email_pengguna?: string, access_token?: string, refresh_token?: string} $data
 */
function sesi_ganti_ke_mode_sementara(array $data): void
{
    $path = easenikers_path_cookie_sesi();
    $domain = '';
    $secure = easenikers_konfirmasi_https() || (isset($_SERVER['HTTP_HOST']) && strpos((string)$_SERVER['HTTP_HOST'], 'easenikers.shop') !== false);
    $httponly = true;
    $opts_hapus = [
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax',
    ];

    if (session_status() === PHP_SESSION_ACTIVE) {
        $nama_sesi = session_name();
        $p = session_get_cookie_params();
        $path = $p['path'] !== '' ? $p['path'] : '/';
        $domain = $p['domain'] ?? '';
        $secure = !empty($p['secure']) ? (bool) $p['secure'] : easenikers_konfirmasi_https();
        $httponly = (bool) ($p['httponly'] ?? true);
        $opts_hapus = [
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax',
        ];
        $_SESSION = [];
        session_destroy();
        setcookie($nama_sesi, '', array_merge($opts_hapus, ['expires' => time() - 42000]));
    }

    setcookie(EASENIKERS_COOKIE_INGAT, '', array_merge($opts_hapus, ['expires' => time() - 3600]));

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => easenikers_path_cookie_sesi(),
        'domain' => '',
        'secure' => easenikers_konfirmasi_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    session_regenerate_id(true);
    $_SESSION['id_pengguna'] = $data['id_pengguna'];
    $_SESSION['nama_pengguna'] = $data['nama_pengguna'];
    $_SESSION['peran'] = $data['peran'];
    if (!empty($data['email_pengguna'])) {
        $_SESSION['email_pengguna'] = $data['email_pengguna'];
    }
    if (!empty($data['access_token'])) {
        $_SESSION['access_token'] = $data['access_token'];
    }
    if (isset($data['refresh_token'])) {
        $_SESSION['refresh_token'] = $data['refresh_token'];
    }
}

/**
 * Setelah login Supabase berhasil: isi sesi (termasuk email + token) lalu terapkan mode ingat/tidak.
 *
 * @param array{id_pengguna: int, nama_pengguna: string, peran: string, email_pengguna: string, access_token: string, refresh_token?: string} $data
 */
function sesi_setelah_login_supabase(array $data, bool $ingat_saya): void
{
    if ($ingat_saya) {
        sesi_perbarui_id_aman();
        $_SESSION['id_pengguna'] = $data['id_pengguna'];
        $_SESSION['nama_pengguna'] = $data['nama_pengguna'];
        $_SESSION['peran'] = $data['peran'];
        $_SESSION['email_pengguna'] = $data['email_pengguna'];
        $_SESSION['access_token'] = $data['access_token'];
        $_SESSION['refresh_token'] = $data['refresh_token'] ?? '';
        sesi_terapkan_tetap_masuk(true);
    } else {
        sesi_ganti_ke_mode_sementara($data);
    }
}

/**
 * Panggil setelah login/daftar berhasil — mengurangi risiko session fixation / hijacking.
 */
function sesi_perbarui_id_aman(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/** Apakah pengguna sudah login? (Supabase: access_token + email; kompatibilitas: id_pengguna lama) */
function sudah_masuk(): bool
{
    if (!empty($_SESSION['access_token']) && !empty($_SESSION['email_pengguna'])) {
        return true;
    }
    return isset($_SESSION['id_pengguna']);
}

/** Ambil peran: admin atau pembeli (null jika belum login). */
function ambil_peran(): ?string
{
    return $_SESSION['peran'] ?? null;
}

/**
 * ID baris `users.id` untuk filter pesanan, keranjang, dll.
 * Jika sesi punya `id_pengguna` 0 (login lama) tapi ada email, sekali coba baca dari DB dan perbarui sesi.
 */
function ambil_id_pengguna_efektif(bool $perbarui_sesi = true): int
{
    $id = (int) ($_SESSION['id_pengguna'] ?? 0);
    if ($id > 0) {
        return $id;
    }
    $email = trim((string) ($_SESSION['email_pengguna'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 0;
    }
    require_once __DIR__ . '/database.php';
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $stmt->execute(['email' => $email]);
        $baris = $stmt->fetch();
        if ($baris && isset($baris['id'])) {
            $ditemukan = (int) $baris['id'];
            if ($ditemukan > 0 && $perbarui_sesi && session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['id_pengguna'] = $ditemukan;
            }

            return $ditemukan;
        }
    } catch (Throwable $e) {
        // abaikan
    }

    return 0;
}

/** Kalau belum login, tendang ke halaman masuk. */
function wajib_sudah_masuk(): void
{
    if (!sudah_masuk()) {
        header('Location: ' . aplikasi_url('login/masuk.php'));
        exit;
    }
}

/** Hanya boleh peran tertentu (misalnya admin). */
function wajib_peran(string $peran): void
{
    wajib_sudah_masuk();
    if (ambil_peran() !== $peran) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Akses ditolak.';
        exit;
    }
}

/**
 * Hapus data sesi dan cookie sesi (setara alur keluar).
 * Dipakai setelah reset kata sandi agar token lama tidak tertinggal.
 */
function sesi_hancurkan_total(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        $path = $p['path'] !== '' ? $p['path'] : '/';
        $domain = $p['domain'] ?? '';
        $secure = !empty($p['secure']) ? (bool) $p['secure'] : easenikers_konfirmasi_https();
        $httponly = (bool) ($p['httponly'] ?? true);
        $opts = [
            'expires' => time() - 42000,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax',
        ];
        setcookie(session_name(), '', $opts);
        setcookie(EASENIKERS_COOKIE_INGAT, '', array_merge($opts, ['expires' => time() - 3600]));
    }
    session_destroy();
}

/**
 * Konfirmasi email selesai: isi sesi masuk dari access_token Supabase (mode tidak ingat).
 *
 * @return 'admin'|'pembeli'|null null jika token tidak valid
 */
function easenikers_sesi_login_dari_token_supabase(string $access_token, string $refresh_token): ?string
{
    require_once __DIR__ . '/supabase_auth.php';
    require_once __DIR__ . '/database.php';

    $user = supabase_auth_ambil_user_dengan_token($access_token);
    if ($user === null) {
        return null;
    }

    $email = (string) ($user['email'] ?? '');
    $meta = is_array($user['user_metadata'] ?? null) ? $user['user_metadata'] : [];
    $nama_tampil = (string) ($meta['username'] ?? $meta['name'] ?? ($email !== '' ? preg_replace('/@.*$/', '', $email) : 'pengguna'));

    $id_pengguna = 0;
    $peran = 'pembeli';
    try {
        $pdo = koneksi_database();
        users_sinkron_dari_supabase($pdo, $email, $nama_tampil);
        $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $stmt->execute(['email' => $email]);
        $baris = $stmt->fetch();
        if ($baris) {
            $id_pengguna = (int) $baris['id'];
            $nama_tampil = (string) $baris['username'];
            $peran = (string) $baris['role'];
        }
    } catch (Throwable $e) {
        // lanjut dengan data Supabase saja
    }

    $data_sesi = [
        'id_pengguna' => $id_pengguna,
        'nama_pengguna' => $nama_tampil,
        'peran' => $peran,
        'email_pengguna' => $email,
        'access_token' => $access_token,
        'refresh_token' => $refresh_token,
    ];

    sesi_setelah_login_supabase($data_sesi, false);

    return $peran;
}
