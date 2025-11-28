<?php
// PTZ endpoint (POST): deviceId, profileToken, action, continuous (optional)
// Uses onvif_client helper and Ponvif class
require_once __DIR__ . '/../../onvif_client.php';
header('Content-Type: application/json');

$deviceId     = $_POST['deviceId'] ?? null;
$profileToken = $_POST['profileToken'] ?? null;
$action       = $_POST['action'] ?? null;
$continuous   = isset($_POST['continuous']) && ($_POST['continuous'] === '1' || $_POST['continuous'] === 'true');

if (!$deviceId || !$profileToken || !$action) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'deviceId, profileToken and action required']);
    exit;
}

$devices = load_json_cfg('cameras.json', ['cameras'=>[]])['cameras'];
$device = null;
foreach ($devices as $d) { if (($d['id'] ?? '') === $deviceId) { $device = $d; break; } }
if (!$device) {
    http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Device not found']); exit;
}

$ip = $device['ip']; $user = $device['username']; $pass = $device['password'];
$deviceServiceUrl = $device['device_service_url'] ?? "http://{$ip}:2020/onvif/device_service";

try {
    $onvif = onvif_client($ip, $user, $pass, $deviceServiceUrl);

    // Action mapping
    switch ($action) {
        case 'up':
            $onvif->ptz_ContinuousMove($profileToken, 0.0, 0.5);
            break;
        case 'down':
            $onvif->ptz_ContinuousMove($profileToken, 0.0, -0.5);
            break;
        case 'left':
            $onvif->ptz_ContinuousMove($profileToken, -0.5, 0.0);
            break;
        case 'right':
            $onvif->ptz_ContinuousMove($profileToken, 0.5, 0.0);
            break;
        case 'zoom_in':
            $onvif->ptz_ContinuousMoveZoom($profileToken, 0.5);
            break;
        case 'zoom_out':
            $onvif->ptz_ContinuousMoveZoom($profileToken, -0.5);
            break;
        case 'stop':
            // IMPORTANT: Ponvif::ptz_Stop expects the literal strings "true"/"false" for PanTilt/Zoom
            $onvif->ptz_Stop($profileToken, 'true', 'true');
            break;
        default:
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Unknown action']); exit;
    }

    echo json_encode(['ok'=>true,'action'=>$action,'continuous'=>$continuous]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}