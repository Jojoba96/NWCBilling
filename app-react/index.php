<?php
// Serve React app from dist folder
// Disable error reporting to stdout
error_reporting(0);
ini_set('display_errors', 0);

$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/NWCBilling/app-react';

// Remove base path and query string
$requestPath = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
if (empty($requestPath) || $requestPath === '/') {
    $requestPath = '/index.html';
}

// Security: prevent directory traversal
$requestPath = str_replace('..', '', $requestPath);
$requestPath = str_replace('//', '/', $requestPath);

// Build the file path
$filePath = __DIR__ . '/dist' . $requestPath;
$realPath = realpath($filePath);
$distPath = realpath(__DIR__ . '/dist');

// Verify file is in dist directory
if ($realPath && $distPath && strpos($realPath, $distPath) === 0 && is_file($realPath)) {
    // Set appropriate MIME types
    $mimeTypes = [
        'html' => 'text/html; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json',
        'css' => 'text/css; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject'
    ];
    
    $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
    $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
    
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    
    readfile($realPath);
    exit;
}

// For all other requests, serve index.html (React Router handles it)
$indexPath = realpath(__DIR__ . '/dist/index.html');
if ($indexPath && file_exists($indexPath)) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile($indexPath);
    exit;
}

// If nothing works, show error
http_response_code(500);
echo "Error: Could not load application";
?>

