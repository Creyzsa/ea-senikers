<?php
/**
 * Terima access_token dari konfirmasi_email.php (POST), validasi ke Supabase, lalu buat sesi PHP.
 */
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

$redirect_masuk = aplikasi_url('login/masuk.php');
$redirect_masuk_gagal = aplikasi_url('login/masuk.php') . '?konfirmasi=gagal';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $access_token = trim($_POST['access_token'] ?? '');
    $refresh_token = trim($_POST['refresh_token'] ?? '');
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['code'])) {
    header('Location: ' . $redirect_masuk . '?konfirmasi=perlu_browser');
    exit;
} else {
    header('Location: ' . $redirect_masuk);
    exit;
}

if ($access_token === '') {
    header('Location: ' . $redirect_masuk_gagal);
    exit;
}

$peran = easenikers_sesi_login_dari_token_supabase($access_token, $refresh_token);
if ($peran === null) {
    header('Location: ' . $redirect_masuk_gagal);
    exit;
}

if ($peran === 'admin') {
    header('Location: ' . aplikasi_url('admin/beranda_admin.php'));
} else {
    header('Location: ' . aplikasi_url('')); // clean root homepage after confirmation
}
exit;
