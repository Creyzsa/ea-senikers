<?php
/**
 * Logo EA SENIKERS — EA putih, SENIKERS merah (#D62828).
 * Gambar easenikers.png pada ukuran nav (navbar) dan tentang (hero halaman Tentang).
 *
 * Set sebelum include:
 *   $ukuran_logo — nav | hero | footer | admin | besar | tentang (opsional, default nav)
 */
if (!function_exists('aplikasi_url_aset')) {
    require_once __DIR__ . '/../url_bantu.php';
}

$ukuran_logo = isset($ukuran_logo) ? (string) $ukuran_logo : 'nav';
$izin_ukuran = ['nav', 'hero', 'footer', 'admin', 'besar', 'tentang'];
if (!in_array($ukuran_logo, $izin_ukuran, true)) {
    $ukuran_logo = 'nav';
}
$kelas_logo = 'logo-teks-merek logo-teks-merek--' . $ukuran_logo;
$tampilkan_gambar = in_array($ukuran_logo, ['nav', 'tentang'], true);
$url_logo_gambar = $tampilkan_gambar ? aplikasi_url_aset('assets/images/easenikers.png') : '';
?>
<span class="<?php echo htmlspecialchars($kelas_logo, ENT_QUOTES, 'UTF-8'); ?>" role="img" aria-label="EA SENIKERS">
    <?php if ($ukuran_logo === 'tentang'): ?>
    <img class="logo-teks-merek__gambar"
         src="<?php echo htmlspecialchars($url_logo_gambar, ENT_QUOTES, 'UTF-8'); ?>"
         alt="EA SENIKERS"
         width="280"
         height="280"
         decoding="async">
    <?php elseif ($ukuran_logo === 'nav'): ?>
    <img class="logo-teks-merek__gambar"
         src="<?php echo htmlspecialchars($url_logo_gambar, ENT_QUOTES, 'UTF-8'); ?>"
         alt=""
         width="64"
         height="64"
         decoding="async">
    <span class="logo-teks-merek__teks" aria-hidden="true">
        <span class="logo-teks-merek__ea">EA</span><span class="logo-teks-merek__senikers">SENIKERS</span>
    </span>
    <?php else: ?>
    <span class="logo-teks-merek__ea">EA</span><span class="logo-teks-merek__senikers">SENIKERS</span>
    <?php endif; ?>
</span>