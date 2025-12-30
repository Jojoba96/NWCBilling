<?php
/**
 * React Customer Dashboard App
 * Access at: localhost/NWCBilling/app.php
 */

// Get the request path
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/NWCBilling/';
$path = str_replace($base_path, '', $request_uri);
$path = str_replace('app.php', '', $path);
$path = ltrim($path, '/');

// Handle static assets from dist folder
if (!empty($path)) {
    $file = __DIR__ . '/frontend/dist/' . $path;
    if (file_exists($file) && is_file($file)) {
        $mime_types = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
        ];
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        header('Content-Type: ' . ($mime_types[$ext] ?? 'application/octet-stream'));
        readfile($file);
        exit;
    }
}

// Serve index.html for SPA routing
header('Content-Type: text/html; charset=UTF-8');
readfile(__DIR__ . '/frontend/dist/index.html');
?>
