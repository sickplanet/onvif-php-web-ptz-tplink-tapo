<?php
// Returns stream URIs and snapshot for a device/profile
require_once __DIR__ . '/../../onvif_client.php';
header('Content-Type: application/json');

$deviceId = $_GET['deviceId'] ?? null;
$profileToken = $_GET['profileToken'] ?? null;

if (!$deviceId || !$profileToken) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'deviceId and profileToken required']); exit;
}

$devices = load_json_cfg('cameras.json', ['cameras'=>[]])['cameras'];
$device = null;
foreach ($devices as $d) { if (($d['id'] ?? '') === $deviceId) { $device = $d; break; } }
if (!$device) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Device not found']); exit; }

$ip = $device['ip']; $user = $device['username']; $pass = $device['password'];
$deviceServiceUrl = $device['device_service_url'] ?? "http://{$ip}:2020/onvif/device_service";

// Check for cached capabilities
$cacheDir = __DIR__ . '/../../cfg/cameras-info';
$cacheFile = $cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ip) . '.json';
$config = load_json_cfg('config.json', ['cache_ttl_seconds' => 86400]);
$cacheTtl = $config['cache_ttl_seconds'] ?? 86400;
$cachedData = null;

if (file_exists($cacheFile)) {
    $cacheAge = time() - filemtime($cacheFile);
    if ($cacheAge < $cacheTtl) {
        $cachedData = json_decode(file_get_contents($cacheFile), true);
    }
}

try {
    $onvif = onvif_client($ip, $user, $pass, $deviceServiceUrl);
    
    // Try to get stream URI using different approaches
    $streamUri = null;
    $snapshotUri = null;
    $errorDetails = [];
    
    // Method 1: Try standard ONVIF GetStreamUri
    try {
        $streamUri = $onvif->media_GetStreamUri($profileToken);
    } catch (Exception $e) {
        $errorDetails[] = 'GetStreamUri failed: ' . $e->getMessage();
        
        // Method 2: Try using cached stream URI if available
        if ($cachedData && !empty($cachedData['streamUri'])) {
            $streamUri = $cachedData['streamUri'];
            $errorDetails[] = 'Using cached streamUri';
        }
    }
    
    // Try to get snapshot URI
    try { 
        $snapshotUri = $onvif->media_GetSnapshotUri($profileToken); 
    } catch (Exception $e) {
        // If snapshot fails, try cached or construct common fallback URLs
        if ($cachedData && !empty($cachedData['snapshotUri'])) {
            $snapshotUri = $cachedData['snapshotUri'];
        }
    }

    // Build common RTSP URI hints for Tp-Link Tapo cameras
    $rtsp_hints = [
        'high' => "rtsp://{$user}:{$pass}@{$ip}:554/stream1",
        'low'  => "rtsp://{$user}:{$pass}@{$ip}:554/stream2"
    ];
    
    // If we still don't have a streamUri, use the high quality hint
    if (!$streamUri) {
        $streamUri = $rtsp_hints['high'];
        $errorDetails[] = 'Using RTSP fallback URI';
    }
    
    // Cache the successful response
    if ($streamUri || $snapshotUri) {
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }
        $cacheData = [
            'streamUri' => $streamUri,
            'snapshotUri' => $snapshotUri,
            'profileToken' => $profileToken,
            'cached_at' => date('c')
        ];
        file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
    }

    echo json_encode([
        'ok' => true,
        'streamUri' => $streamUri,
        'snapshotUri' => $snapshotUri,
        'rtspHints' => $rtsp_hints,
        'debug' => $errorDetails
    ]);
} catch (Exception $e) {
    // Even on failure, provide fallback RTSP URIs that commonly work with Tp-Link Tapo
    $rtsp_hints = [
        'high' => "rtsp://{$user}:{$pass}@{$ip}:554/stream1",
        'low'  => "rtsp://{$user}:{$pass}@{$ip}:554/stream2"
    ];
    
    echo json_encode([
        'ok' => true,
        'streamUri' => $rtsp_hints['high'],
        'snapshotUri' => null,
        'rtspHints' => $rtsp_hints,
        'error' => $e->getMessage(),
        'fallback' => true
    ]);
}