<?php

declare(strict_types=1);

/**
 * Favicon situs — logo EA SENIKERS (easenikers.png).
 */
if (!function_exists('aplikasi_url_aset')) {
    require_once __DIR__ . '/../url_bantu.php';
}

$url_favicon = aplikasi_url_aset('assets/images/easenikers.png');
?>
<link rel="icon" href="<?php echo htmlspecialchars($url_favicon, ENT_QUOTES, 'UTF-8'); ?>" type="image/png" sizes="32x32">
<link rel="shortcut icon" href="<?php echo htmlspecialchars($url_favicon, ENT_QUOTES, 'UTF-8'); ?>" type="image/png">
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars($url_favicon, ENT_QUOTES, 'UTF-8'); ?>">