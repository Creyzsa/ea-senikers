<?php
/**
 * Terima access_token dari tautan reset sandi (hash di konfirmasi_email.php), simpan sementara di sesi, lalu ke form setel sandi.
 */
require_once __DIR__ . '/../../includes/sesi.php';
require_once __DIR__ . '/../../includes/supabase_auth.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . aplikasi_url('login/masuk.php'));
    exit;
}

$access = trim($_POST['access_token'] ?? '');
$refresh = trim($_POST['refresh_token'] ?? '');

if ($access === '') {
    header('Location: ' . aplikasi_url('login/masuk.php') . '?konfirmasi=gagal');
    exit;
}

$user = supabase_auth_ambil_user_dengan_token($access);
if ($user === null) {
    header('Location: ' . aplikasi_url('login/masuk.php') . '?konfirmasi=gagal');
    exit;
}

sesi_perbarui_id_aman();

$_SESSION[EASENIKERS_SESI_RESET_SANDI] = [
    'access_token' => $access,
    'refresh_token' => $refresh,
    'email' => (string) ($user['email'] ?? ''),
    'ts' => time(),
];

header('Location: ' . aplikasi_url('login/setel_sandi_baru.php'), true, 303);
exit;
