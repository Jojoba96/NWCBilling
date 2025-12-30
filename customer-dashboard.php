<?php
/**
 * NWC Customer Dashboard Entry Point
 * This file serves the React-based customer dashboard
 * Access via: localhost/NWCBilling/customer-dashboard.php
 */

// Check if user is logged in (customer session)
session_start();

// Allow CORS for React app to communicate with PHP backend
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// If this is an OPTIONS request, respond with 200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the path to the React build dist folder
$dist_path = __DIR__ . '/frontend/dist';

// Check if dist folder exists
if (!is_dir($dist_path)) {
    http_response_code(500);
    die('<h1>Error: React app not built</h1><p>Please run in frontend folder: npm run build</p>');
}

// Get the requested file
$request_uri = $_SERVER['REQUEST_URI'];
$request_path = parse_url($request_uri, PHP_URL_PATH);

// Remove the base path '/NWCBilling/'
$request_path = str_replace('/NWCBilling/', '', $request_path);
$request_path = ltrim($request_path, '/');

// Remove 'customer-dashboard.php' if it's in the path
if (strpos($request_path, 'customer-dashboard.php') === 0) {
    $request_path = substr($request_path, strlen('customer-dashboard.php'));
    $request_path = ltrim($request_path, '/');
}

// Default to index.html if root is requested
if (empty($request_path)) {
    $request_path = 'index.html';
} else {
    // Check if the file exists in dist
    $full_path = $dist_path . '/' . $request_path;
    if (!file_exists($full_path)) {
        // If not found, it's probably a SPA route - serve index.html
        $request_path = 'index.html';
    }
}

// Construct full file path
$full_path = $dist_path . '/' . $request_path;

// Security check - prevent directory traversal with a simpler method
$full_path_normalized = str_replace('\\', '/', $full_path);
$dist_path_normalized = str_replace('\\', '/', $dist_path);

if (strpos($full_path_normalized, $dist_path_normalized) !== 0) {
    http_response_code(403);
    die('Forbidden');
}

// If file exists, serve it with correct content type
if (file_exists($full_path) && is_file($full_path)) {
    // Get MIME type
    $mime_types = [
        'html' => 'text/html; charset=UTF-8',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];
    
    $ext = pathinfo($full_path, PATHINFO_EXTENSION);
    $mime_type = $mime_types[$ext] ?? 'application/octet-stream';
    
    header('Content-Type: ' . $mime_type);
    header('Cache-Control: public, max-age=3600');
    readfile($full_path);
    exit;
}

// If we get here, serve index.html for SPA routing
header('Content-Type: text/html; charset=UTF-8');
readfile($dist_path . '/index.html');

