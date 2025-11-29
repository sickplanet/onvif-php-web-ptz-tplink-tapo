<?php
// controller/add_camera.php - updated: when adding try to fetch device info and save manufacturer/model
header('Content-Type: application/json');
require_once __DIR__ . '/../onvif_client.php';
session_start();
if (empty($_SESSION['user'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'login required']); exit; }

$ip = trim($_POST['ip'] ?? '');
$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$device_service_url = trim($_POST['device_service_url'] ?? '');
$manufacturer = trim($_POST['manufacturer'] ?? '');
$model = trim($_POST['model'] ?? '');

if ($ip === '' || $name === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'ip and name required']); exit; }

$camerasCfg = load_json_cfg('cameras.json', ['cameras'=>[]]);
$cameras = $camerasCfg['cameras'] ?? [];

$id = substr(md5($ip . time()), 0, 12);
if ($device_service_url === '') $device_service_url = "http://{$ip}:2020/onvif/device_service";

$infoFromDevice = null;

// If user provided credentials, try authenticated onvif_client
if ($username !== '' && $password !== '' && class_exists('Ponvif')) {
    try {
        $onvif = onvif_client($ip, $username, $password, $device_service_url);
        $info = $onvif->core_GetDeviceInformation();
        if ($info && is_array($info)) {
            $infoFromDevice = $info;
        }
    } catch (Exception $e) {
        // ignore, fallback to unauthenticated attempt below
    }
}

// If not obtained via auth, try unauthenticated public info fetch
if (!$infoFromDevice) {
    $pi = get_device_public_info($device_service_url);
    if ($pi['ok']) $infoFromDevice = $pi['info'];
}

// Prefer provided manufacturer/model if present, else device info
if (empty($manufacturer) && !empty($infoFromDevice['Manufacturer'])) $manufacturer = $infoFromDevice['Manufacturer'];
if (empty($model) && !empty($infoFromDevice['Model'])) $model = $infoFromDevice['Model'];

$streams = [
    'high' => "rtsp://{$ip}:554/stream1",
    'low'  => "rtsp://{$ip}:554/stream2",
];

$new = [
    'id' => $id,
    'name' => $name,
    'ip' => $ip,
    'username' => $username,
    'password' => $password,
    'device_service_url' => $device_service_url,
    'manufacturer' => $manufacturer,
    'model' => $model,
    'streams' => $streams,
    'allowptz' => true,
    'allow_audio' => false
];

$cameras[] = $new;
$camerasCfg['cameras'] = $cameras;
save_json_cfg('cameras.json', $camerasCfg);

echo json_encode(['ok'=>true,'camera'=>$new], JSON_PRETTY_PRINT);