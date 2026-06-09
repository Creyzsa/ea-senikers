<?php

declare(strict_types=1);

/** Skrip layout responsif admin (menu mobile + tabel kartu + notifikasi live). */
require_once __DIR__ . '/../auth_db/sesi.php';

$poll_url = '';
$panel_url = '';
$sw_url = '';
$sound_url = '';
if (function_exists('sudah_masuk') && sudah_masuk() && function_exists('ambil_peran') && ambil_peran() === 'admin') {
    require_once __DIR__ . '/../url_bantu.php';
    $poll_url = aplikasi_url('api/admin_notifikasi_poll.php');
    $panel_url = aplikasi_url('api/admin_notifikasi_panel.php');
    $sw_url = aplikasi_url('sw-admin-notifikasi.js');
    $sound_url = aplikasi_url_aset('sounds/admin-notif.mp3');
}
?>
<?php if ($poll_url !== ''): ?>
<script>
document.body.setAttribute('data-admin-notif-poll', <?php echo json_encode($poll_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>);
document.body.setAttribute('data-admin-notif-panel', <?php echo json_encode($panel_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>);
document.body.setAttribute('data-admin-notif-sound', <?php echo json_encode($sound_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>);
</script>
<script src="../assets/js/admin-notifikasi-live.js" defer></script>
<?php if ($sw_url !== ''): ?>
<script>
(function () {
    if (!('serviceWorker' in navigator)) return;
    window.addEventListener('load', function () {
        navigator.serviceWorker.register(<?php echo json_encode($sw_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>, { scope: '/' }).catch(function () {});
    });
})();
</script>
<?php endif; ?>
<?php endif; ?>
<script src="../assets/js/admin-tabel-responsif.js" defer></script>
<script src="../assets/js/admin-mobile-nav.js" defer></script>