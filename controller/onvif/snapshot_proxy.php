<?php
/**
 * Snapshot Proxy - Fetches and serves camera snapshots
 * This allows the web browser to display snapshots without CORS issues
 * and handles camera authentication transparently.
 */
require_once __DIR__ . '/../../onvif_client.php';
session_start();

// Require authentication
if (empty($_SESSION['user'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Login required']);
    exit;
}

$deviceId = $_GET['deviceId'] ?? null;
$profileToken = $_GET['profileToken'] ?? null;

if (!$deviceId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'deviceId required']);
    exit;
}

$devices = load_json_cfg('cameras.json', ['cameras' => []])['cameras'];
$device = null;
foreach ($devices as $d) {
    if (($d['id'] ?? '') === $deviceId) {
        $device = $d;
        break;
    }
}

if (!$device) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Device not found']);
    exit;
}

$ip = $device['ip'];
$user = $device['username'] ?? '';
$pass = $device['password'] ?? '';
$deviceServiceUrl = $device['device_service_url'] ?? "http://{$ip}:2020/onvif/device_service";

// Try to get snapshot URI from ONVIF or cache
$snapshotUri = null;
$cacheDir = __DIR__ . '/../../cfg/cameras-info';
$cacheFile = $cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ip) . '.json';

// Check cache first
if (file_exists($cacheFile)) {
    $cachedData = json_decode(file_get_contents($cacheFile), true);
    if (!empty($cachedData['snapshotUri'])) {
        $snapshotUri = $cachedData['snapshotUri'];
    }
}

// If no cached URI, try to get from ONVIF
if (!$snapshotUri && $profileToken) {
    try {
        $onvif = onvif_client($ip, $user, $pass, $deviceServiceUrl);
        $snapshotUri = $onvif->media_GetSnapshotUri($profileToken);
        
        // Cache it
        if ($snapshotUri) {
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0750, true);
            }
            $cacheData = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
            $cacheData['snapshotUri'] = $snapshotUri;
            $cacheData['cached_at'] = date('c');
            file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
        }
    } catch (Exception $e) {
        // Ignore ONVIF errors, we'll try common URLs
    }
}

// Common fallback snapshot URLs for Tp-Link Tapo and other cameras
$fallbackUrls = [
    "http://{$ip}:2020/onvif-http/snapshot",
    "http://{$ip}/cgi-bin/snapshot.cgi",
    "http://{$ip}/snapshot.jpg",
    "http://{$ip}:8080/snapshot.cgi",
];

// Add the ONVIF URI to the beginning if we have it
if ($snapshotUri) {
    array_unshift($fallbackUrls, $snapshotUri);
}

// Try to fetch snapshot from each URL
$imageData = null;
$contentType = 'image/jpeg';

foreach ($fallbackUrls as $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // Add authentication if credentials are provided
    if ($user && $pass) {
        curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
    }
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $mimeType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    // Check if we got a valid image
    if ($httpCode === 200 && $result && strlen($result) > 1000) {
        // Verify it's actually an image
        if (strpos($mimeType, 'image/') === 0 || 
            substr($result, 0, 2) === "\xFF\xD8" ||  // JPEG magic bytes
            substr($result, 0, 8) === "\x89PNG\r\n\x1a\n") {  // PNG magic bytes
            $imageData = $result;
            if (strpos($mimeType, 'image/') === 0) {
                $contentType = $mimeType;
            }
            break;
        }
    }
}

if ($imageData) {
    // Add cache control headers to prevent browser caching (for live view)
    header('Content-Type: ' . $contentType);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $imageData;
} else {
    // Return a placeholder image or error
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false, 
        'error' => 'Could not fetch snapshot from camera',
        'tried' => $fallbackUrls
    ]);
}
