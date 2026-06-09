<?php
/**
 * Router untuk PHP built-in server (php -S localhost:8080 router.php).
 * Memetakan URL gaya Laragon /EASENIKERS/public/... ke file di folder public/.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = preg_replace('#/+#', '/', $uri) ?: '/';
$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';

foreach (['/EASENIKERS/public', '/easenikers/public'] as $prefix) {
    if (str_starts_with($uri, $prefix)) {
        $uri = substr($uri, strlen($prefix)) ?: '/';
        $_SERVER['REQUEST_URI'] = $uri . $query;
        break;
    }
}

$path = __DIR__ . $uri;
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
$aset_statis = ['css', 'js', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico', 'woff', 'woff2', 'map', 'txt'];

if ($uri !== '/' && is_file($path)) {
    return false;
}

if (in_array($ext, $aset_statis, true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Asset not found: ' . $uri;

    return true;
}

if ($uri === '/favicon.ico') {
    $logo = __DIR__ . '/assets/images/easenikers.png';
    if (is_file($logo)) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        readfile($logo);

        return true;
    }
}

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';

    return true;
}

if (!is_file($path) && !is_dir($path)) {
    require __DIR__ . '/index.php';

    return true;
}

return false;