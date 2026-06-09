<?php
require_once __DIR__ . '/../url_bantu.php';

easenikers_redirect_kanonikal_jika_perlu();

/**
 * Sesi dipakai untuk menyimpan status masuk (siapa yang sedang login).
 * Nama fungsi pakai bahasa Indonesia supaya mudah dibaca.
 *
 * "Tetap masuk" (ingat saya): cookie pembantu + masa hidup cookie sesi panjang,
 * sehingga pengguna tetap login setelah menutup browser sampai logout.
 */

/** Nama cookie pembantu jika pengguna memilih tetap masuk (~30 hari). */
const EASENIKERS_COOKIE_INGAT = 'easenikers_ingat';

/** Cookie token Supabase — cadangan bila sesi file PHP hilang (Vercel/serverless). */
const EASENIKERS_COOKIE_AT = 'easenikers_at';
const EASENIKERS_COOKIE_RT = 'easenikers_rt';

/** Durasi tetap masuk dalam detik (30 hari). */
const EASENIKERS_SESI_INGAT_DETIK = 60 * 60 * 24 * 30;

/**
 * Opsi cookie auth/sesi (path, secure, httponly).
 *
 * @return array{path:string, domain:string, secure:bool, httponly:bool, samesite:string}
 */
function sesi_opsi_cookie(): array
{
    $secure = easenikers_konfirmasi_https()
        || (isset($_SERVER['HTTP_HOST']) && strpos((string) $_SERVER['HTTP_HOST'], 'easenikers.shop') !== false);

    return [
        'path' => easenikers_path_cookie_sesi(),
        'domain' => easenikers_cookie_domain(),
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/**
 * Simpan token Supabase di cookie agar login tetap dikenali di serverless.
 */
function sesi_simpan_cookie_auth(string $access_token, string $refresh_token, bool $ingat_saya): void
{
    $access_token = trim($access_token);
    $refresh_token = trim($refresh_token);
    if ($access_token === '') {
        return;
    }

    $opsi = sesi_opsi_cookie();
    $expires = $ingat_saya ? time() + EASENIKERS_SESI_INGAT_DETIK : 0;
    $dasar = [
        'path' => $opsi['path'],
        'domain' => $opsi['domain'],
        'secure' => $opsi['secure'],
        'httponly' => $opsi['httponly'],
        'samesite' => $opsi['samesite'],
    ];
    if ($expires > 0) {
        $dasar['expires'] = $expires;
    }

    setcookie(EASENIKERS_COOKIE_AT, $access_token, $dasar);
    if ($refresh_token !== '') {
        setcookie(EASENIKERS_COOKIE_RT, $refresh_token, $dasar);
    }
}

/**
 * Hapus cookie token auth.
 */
function sesi_hapus_cookie_auth(): void
{
    $opsi = sesi_opsi_cookie();
    $hapus = [
        'path' => $opsi['path'],
        'domain' => $opsi['domain'],
        'secure' => $opsi['secure'],
        'httponly' => $opsi['httponly'],
        'samesite' => $opsi['samesite'],
        'expires' => time() - 3600,
    ];
    setcookie(EASENIKERS_COOKIE_AT, '', $hapus);
    setcookie(EASENIKERS_COOKIE_RT, '', $hapus);
}

/**
 * Apakah pengguna sebelumnya memilih "Ingat saya" di perangkat ini?
 */
function sesi_ingat_saya_aktif(): bool
{
    return !empty($_COOKIE[EASENIKERS_COOKIE_INGAT]) && $_COOKIE[EASENIKERS_COOKIE_INGAT] === '1';
}

/**
 * Hapus cookie pembantu "Ingat saya".
 */
function sesi_hapus_cookie_ingat(): void
{
    $opsi = sesi_opsi_cookie();
    setcookie(EASENIKERS_COOKIE_INGAT, '', [
        'path' => $opsi['path'],
        'domain' => $opsi['domain'],
        'secure' => $opsi['secure'],
        'httponly' => $opsi['httponly'],
        'samesite' => $opsi['samesite'],
        'expires' => time() - 3600,
    ]);
}

/**
 * Perbarui access_token memakai refresh_token (token Supabase ~1 jam).
 * Berlaku untuk semua login — di Vercel/serverless sesi file tidak persist antar request.
 */
function sesi_perbarui_token_jika_perlu(): void
{
    if (!sudah_masuk()) {
        return;
    }

    $at = trim((string) ($_SESSION['access_token'] ?? $_COOKIE[EASENIKERS_COOKIE_AT] ?? ''));
    $rt = trim((string) ($_SESSION['refresh_token'] ?? $_COOKIE[EASENIKERS_COOKIE_RT] ?? ''));
    if ($rt === '') {
        return;
    }

    require_once __DIR__ . '/supabase_auth.php';
    if ($at !== '' && supabase_auth_ambil_user_dengan_token($at) !== null) {
        if ($at !== trim((string) ($_SESSION['access_token'] ?? ''))) {
            $_SESSION['access_token'] = $at;
        }

        return;
    }

    $baru = supabase_auth_refresh_session($rt);
    if ($baru === null) {
        return;
    }

    $_SESSION['access_token'] = $baru['access_token'];
    $_SESSION['refresh_token'] = $baru['refresh_token'];
    sesi_simpan_cookie_auth($baru['access_token'], $baru['refresh_token'], sesi_ingat_saya_aktif());
}

/**
 * Pulihkan $_SESSION dari cookie token Supabase (wajib di Vercel/serverless — file sesi tidak persist).
 * Berlaku untuk login biasa (cookie sesi browser) dan "Ingat saya" (cookie 30 hari).
 */
function sesi_muat_dari_cookie_auth(): void
{
    if (sudah_masuk()) {
        sesi_perbarui_token_jika_perlu();

        return;
    }

    $at = trim((string) ($_COOKIE[EASENIKERS_COOKIE_AT] ?? ''));
    $rt = trim((string) ($_COOKIE[EASENIKERS_COOKIE_RT] ?? ''));
    if ($at === '' && $rt === '') {
        return;
    }

    require_once __DIR__ . '/supabase_auth.php';

    // Refresh token dulu — access token di cookie biasanya sudah kadaluarsa setelah beberapa jam.
    if ($rt !== '') {
        $baru = supabase_auth_refresh_session($rt);
        if ($baru !== null && easenikers_sesi_login_dari_token_supabase($baru['access_token'], $baru['refresh_token']) !== null) {
            return;
        }
    }

    if ($at !== '' && supabase_auth_ambil_user_dengan_token($at) !== null) {
        if (easenikers_sesi_login_dari_token_supabase($at, $rt) !== null) {
            return;
        }
    }

    sesi_hapus_cookie_auth();
    if (sesi_ingat_saya_aktif()) {
        sesi_hapus_cookie_ingat();
    }
}

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
    $tetap_masuk = sesi_ingat_saya_aktif();
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
        'domain' => easenikers_cookie_domain(),
        'secure' => $secure_cookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    sesi_muat_dari_cookie_auth();
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
    $domain = easenikers_cookie_domain();
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
        $domain = easenikers_cookie_domain() !== '' ? easenikers_cookie_domain() : (string) ($p['domain'] ?? '');
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
    sesi_hapus_cookie_auth();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => easenikers_path_cookie_sesi(),
        'domain' => easenikers_cookie_domain(),
        'secure' => $secure,
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
    $refresh_token = trim((string) ($data['refresh_token'] ?? ''));

    if ($ingat_saya) {
        if ($refresh_token === '') {
            $ingat_saya = false;
        } else {
            sesi_perbarui_id_aman();
            $_SESSION['id_pengguna'] = $data['id_pengguna'];
            $_SESSION['nama_pengguna'] = $data['nama_pengguna'];
            $_SESSION['peran'] = $data['peran'];
            $_SESSION['email_pengguna'] = $data['email_pengguna'];
            $_SESSION['access_token'] = $data['access_token'];
            $_SESSION['refresh_token'] = $refresh_token;
            sesi_terapkan_tetap_masuk(true);
            sesi_simpan_cookie_auth((string) $data['access_token'], $refresh_token, true);

            return;
        }
    }

    sesi_ganti_ke_mode_sementara($data);
    sesi_simpan_cookie_auth(
        (string) ($data['access_token'] ?? ''),
        $refresh_token,
        false
    );
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

/** Apakah pengguna sudah login? (Supabase: access_token + email; kompatibilitas: id_pengguna > 0) */
function sudah_masuk(): bool
{
    if (!empty($_SESSION['access_token']) && !empty($_SESSION['email_pengguna'])) {
        return true;
    }

    return ((int) ($_SESSION['id_pengguna'] ?? 0)) > 0
        && !empty($_SESSION['nama_pengguna']);
}

/**
 * Normalisasi nilai kolom users.role → 'admin' atau 'pembeli'.
 */
function sesi_normalisasi_peran(string $role): string
{
    $role = strtolower(trim($role));

    return $role === 'admin' ? 'admin' : 'pembeli';
}

/**
 * Ambil peran efektif: dari sesi, atau sekali coba dari DB lewat email (penting di serverless / device baru).
 */
function ambil_peran_efektif(bool $perbarui_sesi = true): ?string
{
    if (!sudah_masuk()) {
        return null;
    }

    $peran = isset($_SESSION['peran']) ? sesi_normalisasi_peran((string) $_SESSION['peran']) : null;
    if ($peran === 'admin' || $peran === 'pembeli') {
        if ($perbarui_sesi && session_status() === PHP_SESSION_ACTIVE && ($_SESSION['peran'] ?? null) !== $peran) {
            $_SESSION['peran'] = $peran;
        }

        return $peran;
    }

    $email = trim((string) ($_SESSION['email_pengguna'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        require_once __DIR__ . '/database.php';
        try {
            $pdo = koneksi_database();
            $stmt = $pdo->prepare('SELECT role FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
            $stmt->execute(['email' => $email]);
            $baris = $stmt->fetch();
            if ($baris) {
                $peran = sesi_normalisasi_peran((string) ($baris['role'] ?? 'pembeli'));
                if ($perbarui_sesi && session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['peran'] = $peran;
                }

                return $peran;
            }
        } catch (Throwable $e) {
            // lanjut ke default
        }
    }

    $peran = 'pembeli';
    if ($perbarui_sesi && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['peran'] = $peran;
    }

    return $peran;
}

/** Ambil peran: admin atau pembeli (null jika belum login). */
function ambil_peran(): ?string
{
    return ambil_peran_efektif();
}

/**
 * Hanya halaman pembeli — admin dialihkan ke panel admin, bukan pesan 403 mentah.
 */
function wajib_peran_pembeli(): void
{
    wajib_sudah_masuk();
    $peran = ambil_peran_efektif();
    if ($peran === 'admin') {
        header('Location: ' . aplikasi_url('admin/beranda_admin.php'));
        exit;
    }
    if ($peran !== 'pembeli') {
        header('Location: ' . aplikasi_url('login/masuk.php'));
        exit;
    }
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

/** Kalau belum login, tendang ke halaman masuk (simpan URL tujuan untuk redirect setelah login). */
function wajib_sudah_masuk(): void
{
    if (!sudah_masuk()) {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $login = aplikasi_url('login/masuk.php');
        if ($uri !== '' && $uri[0] === '/') {
            $_SESSION['login_redirect'] = $uri;
            $login .= (str_contains($login, '?') ? '&' : '?') . 'kembali=' . rawurlencode($uri);
        }
        header('Location: ' . $login);
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
        $domain = easenikers_cookie_domain() !== '' ? easenikers_cookie_domain() : (string) ($p['domain'] ?? '');
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
    }
    sesi_hapus_cookie_ingat();
    sesi_hapus_cookie_auth();
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
            $peran = sesi_normalisasi_peran((string) $baris['role']);
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

    sesi_setelah_login_supabase($data_sesi, sesi_ingat_saya_aktif());

    return $peran;
}

/**
 * Token CSRF untuk API wishlist (POST same-origin).
 */
function csrf_wishlist_token(): string
{
    if (empty($_SESSION['csrf_wishlist']) || !is_string($_SESSION['csrf_wishlist'])) {
        $_SESSION['csrf_wishlist'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['csrf_wishlist'];
}
