<?php
// api/index.php - Vercel Serverless Entrypoint & Front Controller
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);

if (file_exists(__DIR__ . '/../includes/mongo_polyfill.php')) {
    require_once __DIR__ . '/../includes/mongo_polyfill.php';
}
if (file_exists(__DIR__ . '/../includes/helpers.php')) {
    require_once __DIR__ . '/../includes/helpers.php';
}
if (file_exists(__DIR__ . '/../includes/db.php')) {
    require_once __DIR__ . '/../includes/db.php';
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// 1. Static asset bypass (images, icons, styles, fonts)
if (preg_match('/\.(?:png|jpg|jpeg|gif|svg|ico|css|js|woff|woff2|ttf|pdf|webp)$/i', $uri)) {
    $cleanUri = ltrim($uri, '/');
    $basename = basename($cleanUri);

    $candidates = [
        __DIR__ . '/../' . $cleanUri,
        __DIR__ . '/../public/' . $basename,
        __DIR__ . '/../public/' . $cleanUri,
        __DIR__ . '/../public/uploads/documents/' . $basename,
        __DIR__ . '/../public/uploads/avatars/' . $basename,
        __DIR__ . '/../' . $basename
    ];

    foreach ($candidates as $filePath) {
        if (file_exists($filePath) && is_file($filePath)) {
            $mimeTypes = [
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon',
                'css' => 'text/css',
                'js' => 'application/javascript',
                'pdf' => 'application/pdf',
                'webp' => 'image/webp',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf'
            ];
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
            header('Cache-Control: public, max-age=31536000, immutable');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit();
        }
    }
}

// 2. Resolve Target PHP Script
$script = trim($uri, '/');
if (empty($script)) {
    $script = 'index.php';
} elseif (is_dir(__DIR__ . '/../' . $script)) {
    $script = rtrim($script, '/') . '/index.php';
} elseif (!preg_match('/\.php$/i', $script)) {
    if (file_exists(__DIR__ . '/../' . $script . '.php')) {
        $script .= '.php';
    } elseif (file_exists(__DIR__ . '/../' . $script . '/index.php')) {
        $script .= '/index.php';
    }
}

$target = __DIR__ . '/../' . $script;

if (file_exists($target) && is_file($target)) {
    $_SERVER['SCRIPT_NAME'] = '/' . $script;
    $_SERVER['SCRIPT_FILENAME'] = $target;
    $_SERVER['PHP_SELF'] = '/' . $script;
    chdir(dirname($target));
    require $target;
} else {
    http_response_code(404);
    echo "<!DOCTYPE html><html><head><title>404 Not Found</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-slate-50 min-h-screen flex items-center justify-center p-4 text-center'><div class='bg-white p-8 rounded-3xl border border-slate-200 shadow-xl max-w-md'><h1 class='text-4xl font-black text-slate-900 mb-2'>404</h1><p class='text-sm text-slate-500 mb-6'>The requested page was not found.</p><a href='/' class='bg-[#FE5E04] text-white text-xs font-bold px-6 py-3 rounded-xl shadow-md'>Return Home</a></div></body></html>";
}
