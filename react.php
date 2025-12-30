<?php
/**
 * Simple React App Loader
 * Serves app-react folder files with correct MIME types
 */

$request_uri = $_SERVER['REQUEST_URI'];
// Remove /NWCBilling/react/ or /NWCBilling/app-react/
$path = preg_replace('~^/NWCBilling/(react|app-react)/?~', '', $request_uri);
// Remove query string
$path = explode('?', $path)[0];

if (empty($path)) {
    $path = 'index.html';
}

$file = __DIR__ . '/app-react/' . $path;

// Security check
if (!file_exists($file) || strpos(realpath($file), realpath(__DIR__ . '/app-react/')) !== 0) {
    $file = __DIR__ . '/app-react/index.html';
}

// MIME types
$mime_types = [
    'html' => 'text/html; charset=UTF-8',
    'css' => 'text/css; charset=UTF-8',
    'js' => 'application/javascript; charset=UTF-8',
    'json' => 'application/json',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
];

$ext = pathinfo($file, PATHINFO_EXTENSION);
$mime = $mime_types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=3600');
readfile($file);
?>
