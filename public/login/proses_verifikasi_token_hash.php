<?php
/**
 * Verifikasi token_hash dari email (POST) — menghindari otp_expired karena pemindai email
 * memanggil GET ke URL Supabase sebelum pengguna klik.
 */
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/auth_db/supabase_auth.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

$gagal = aplikasi_url_auth('login/konfirmasi_email.php') . '?verify=gagal';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . aplikasi_url('login/masuk.php'));
    exit;
}

$tipe = strtolower(trim((string) ($_POST['type'] ?? '')));
$token_hash = trim((string) ($_POST['token_hash'] ?? ''));
$izin = ['recovery', 'signup', 'email', 'invite', 'magiclink'];

if ($token_hash === '' || !in_array($tipe, $izin, true)) {
    header('Location: ' . $gagal);
    exit;
}

$hasil = supabase_auth_verifikasi_token_hash($tipe, $token_hash);
if (!$hasil['ok'] || empty($hasil['access_token'])) {
    $alasan = isset($hasil['pesan']) ? (string) $hasil['pesan'] : 'gagal';
    header('Location: ' . $gagal . '&reason=' . rawurlencode($alasan));
    exit;
}

$access = (string) $hasil['access_token'];
$refresh = (string) ($hasil['refresh_token'] ?? '');

if ($tipe === 'recovery') {
    $user = supabase_auth_ambil_user_dengan_token($access);
    if ($user === null) {
        header('Location: ' . $gagal);
        exit;
    }
    sesi_simpan_reset_sandi_lalu_ke_form(
        $access,
        $refresh,
        (string) ($user['email'] ?? '')
    );
}

$peran = easenikers_sesi_login_dari_token_supabase($access, $refresh);
if ($peran === null) {
    header('Location: ' . $gagal);
    exit;
}

if ($peran === 'admin') {
    header('Location: ' . aplikasi_url('admin/beranda_admin.php'));
} else {
    header('Location: ' . aplikasi_url('')); // clean root homepage after verification
}
exit;
