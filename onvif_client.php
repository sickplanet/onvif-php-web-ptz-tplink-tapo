<?php
// onvif_client.php - require the ponvif class from model/ponvif
// Adjust if your ponvif path changes.

$possible = __DIR__ . '/model/ponvif/lib/class.ponvif.php';
if (!file_exists($possible)) {
    // helpful error for logs and browser
    error_log("Ponvif class not found at {$possible}");
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Server configuration error: Ponvif library not found. See server error log for details.";
    exit;
}
require_once $possible;

// Create and initialize Ponvif client for given camera.
function onvif_client(string $ip, string $username, string $password, ?string $deviceServiceUrl = null): Ponvif {
    $onvif = new Ponvif();
    $onvif->setUsername($username);
    $onvif->setPassword($password);
    $onvif->setIPAddress($ip);
    if ($deviceServiceUrl) $onvif->setMediaUri($deviceServiceUrl);
    $onvif->initialize();
    return $onvif;
}

function cfg_path(string $file = ''): string {
    return __DIR__ . '/cfg' . ($file ? '/' . $file : '');
}

function load_json_cfg(string $file, $default = []) {
    $path = cfg_path($file);
    if (!file_exists($path)) return $default;
    $s = @file_get_contents($path);
    if ($s === false) return $default;
    $d = json_decode($s, true);
    return is_array($d) ? $d : $default;
}

function save_json_cfg(string $file, $data) {
    $path = cfg_path($file);
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}