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

// If empty or ends with /, use index.php
if ($path === '' || str_ends_with($path, '/')) {
    $path = rtrim($path, '/') . '/index.php';
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
    // Change working directory so relative includes in the included file work as if public/ is root
    chdir($publicRoot);
    
    // Set some server vars as if public/ is document root
    $_SERVER['DOCUMENT_ROOT'] = $publicRoot;
    $_SERVER['SCRIPT_NAME'] = '/' . $path;
    $_SERVER['SCRIPT_FILENAME'] = $fullPath;
    
    // Include the PHP file (it will output directly)
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
