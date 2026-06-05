<?php
/**
 * Front controller for Vercel PHP deployment.
 * This emulates the traditional public/ document root.
 * 
 * All requests are routed here via vercel.json.
 * It includes the corresponding file from the public/ folder.
 * 
 * Limitations on Vercel:
 * - File uploads to local disk (public/assets/images/) will not persist (use /tmp or switch to Supabase Storage).
 * - Some superglobals and paths may need adjustment.
 * - For production, test thoroughly.
 */

// Set the document root to public/ for includes and paths
$publicRoot = __DIR__ . '/../public';

// Get the requested path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';

// Remove leading slash
$path = ltrim($path, '/');

// Support requesting with or without .php for clean routes
$lookup = $path;
if (substr($lookup, -4) === '.php') {
    $lookup = substr($lookup, 0, -4);
}

// Clean simple URLs for buyer area (no /pembeli/ prefix, no .php)
$cleanRoutes = [
    '' => 'pembeli/beranda_pembeli.php',
    'index.php' => 'pembeli/beranda_pembeli.php',
    'produk' => 'pembeli/produk.php',
    'kategori' => 'pembeli/kategori_pembeli.php',
    'tentang' => 'pembeli/tentang_pembeli.php',
    'bantuan' => 'pembeli/bantuan_pembeli.php',
    'cara-membersihkan' => 'pembeli/cara_membersihkan_sepatu.php',
    'detail-produk' => 'pembeli/detail_produk.php',
    'keranjang' => 'pembeli/keranjang_pembeli.php',
    'akun' => 'pembeli/akun_pembeli.php',
    'pesanan' => 'pembeli/pesanan_pembeli.php',
    'wishlist' => 'pembeli/wishlist.php',
    'checkout' => 'pembeli/checkout_pembeli.php',
    'lapor-masalah' => 'pembeli/lapor_masalah.php',
    'detail-pesanan' => 'pembeli/detail_pesanan_pembeli.php',
    'keranjang-tambah' => 'pembeli/keranjang_tambah.php',
    'chat' => 'pembeli/chat.php',
    'api/cari-saran' => 'api/cari-saran.php',
];

if (array_key_exists($lookup, $cleanRoutes)) {
    $path = $cleanRoutes[$lookup];
} elseif (strpos($path, 'admin/') === 0) {
    // admin paths kept as-is (e.g. /admin/beranda_admin.php)
} elseif ($path === '' || str_ends_with($path, '/')) {
    $path = 'pembeli/beranda_pembeli.php';
}

// If the path doesn't have extension and is not a directory request, try adding .php or look for index
if (!pathinfo($path, PATHINFO_EXTENSION)) {
    $phpPath = $path . '.php';
    if (file_exists($publicRoot . '/' . $phpPath)) {
        $path = $phpPath;
    } elseif (is_dir($publicRoot . '/' . $path)) {
        $path .= '/index.php';
    }
}

// Full file path in public/
$fullPath = $publicRoot . '/' . $path;

// Security: only allow files inside public/
$realPublic = realpath($publicRoot);
$realTarget = realpath($fullPath);

if ($realTarget === false || strpos($realTarget, $realPublic) !== 0) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

if (file_exists($fullPath)) {
    // Change working directory so relative includes and __DIR__ in the included files work as if public/ is the document root
    chdir($publicRoot);
    
    // Emulate traditional web server environment variables
    $_SERVER['DOCUMENT_ROOT'] = $publicRoot;
    $_SERVER['SCRIPT_NAME'] = '/' . ltrim($path, '/');
    $_SERVER['SCRIPT_FILENAME'] = $fullPath;
    $_SERVER['PHP_SELF'] = '/' . ltrim($path, '/');
    
    // Include the target PHP file — it will run as if accessed directly from public/
    include $fullPath;
} else {
    // Try to serve static files if they exist (css, js, images in public/assets etc.)
    if (file_exists($fullPath) && !str_ends_with($fullPath, '.php')) {
        // For static, but since this is PHP function, for static assets better to use routes in vercel.json
        // For now, fall to 404 or you can add more routes
        http_response_code(404);
        echo 'File not found: ' . htmlspecialchars($path);
    } else {
        http_response_code(404);
        echo 'Page not found';
    }
}
