<?php

declare(strict_types=1);

/** Skrip layout responsif admin (menu mobile + tabel kartu + notifikasi live). */
require_once __DIR__ . '/../auth_db/sesi.php';

$poll_url = '';
if (function_exists('sudah_masuk') && sudah_masuk() && function_exists('ambil_peran') && ambil_peran() === 'admin') {
    require_once __DIR__ . '/../url_bantu.php';
    $poll_url = aplikasi_url('api/admin_notifikasi_poll.php');
}
?>
<?php if ($poll_url !== ''): ?>
<script>document.body.setAttribute('data-admin-notif-poll', <?php echo json_encode($poll_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>);</script>
<div id="admin-notif-toast" class="admin-notif-toast" hidden role="status" aria-live="assertive"></div>
<script src="../assets/js/admin-notifikasi-live.js" defer></script>
<?php endif; ?>
<script src="../assets/js/admin-tabel-responsif.js" defer></script>
<script src="../assets/js/admin-mobile-nav.js" defer></script>