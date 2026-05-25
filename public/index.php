<?php
require_once __DIR__ . '/../includes/auth_db/sesi.php';

if (sudah_masuk()) {
    if (ambil_peran() === 'admin') {
        header('Location: ' . aplikasi_url('admin/beranda_admin.php'));
    } else {
        header('Location: ' . aplikasi_url('pembeli/beranda_pembeli.php'));
    }
    exit;
}

/**
 * Token dari email Supabase (konfirmasi / reset sandi) ada di #fragment — tidak sampai ke PHP.
 * Jika Site URL di dashboard mengarah ke folder .../public/ (bukan login/konfirmasi_email.php),
 * redirect server-side ke login/masuk.php akan membuang hash → layar kosong / alur gagal.
 * Alihkan ke login/konfirmasi_email.php bila hash/query berisi token (Supabase kadang mengisi
 * redirect_to hanya ke .../public jika URL callback penuh belum ada di Redirect URLs).
 */
$tujuan_konfirmasi = aplikasi_url('login/konfirmasi_email.php');
$tujuan_masuk = aplikasi_url('login/masuk.php');
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mengalihkan…</title>
</head>
<body style="font-family:system-ui,sans-serif;margin:2rem;text-align:center;color:#333">
    <p>Mengalihkan…</p>
    <script>
    (function () {
        var h = window.location.hash || '';
        var s = window.location.search || '';
        var keKonfirmasi = h.indexOf('access_token=') !== -1 || h.indexOf('error=') !== -1
            || s.indexOf('access_token=') !== -1 || s.indexOf('code=') !== -1 || s.indexOf('error=') !== -1;
        if (keKonfirmasi) {
            window.location.replace(<?php echo json_encode($tujuan_konfirmasi, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?> + s + h);
            return;
        }
        window.location.replace(<?php echo json_encode($tujuan_masuk, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>);
    })();
    </script>
    <noscript>
        <p>Aktifkan JavaScript, atau buka <a href="<?php echo htmlspecialchars($tujuan_masuk, ENT_QUOTES, 'UTF-8'); ?>">halaman masuk</a>
        <?php if ($tujuan_konfirmasi !== ''): ?> / <a href="<?php echo htmlspecialchars($tujuan_konfirmasi, ENT_QUOTES, 'UTF-8'); ?>">konfirmasi email</a><?php endif; ?>.</p>
    </noscript>
</body>
</html>
