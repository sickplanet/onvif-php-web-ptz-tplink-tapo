<?php
// Public PTZ proxy: this endpoint performs PTZ for public cameras without exposing camera credentials.
// It expects POST: hash, action, and optionally presetToken.
// Security: only act if a mapping exists in cfg/public-cameras/{hash}.json
// The public JSON must store internal camera id or ip (server-side) or a safe mapping (not credentials).
//
// Example public file could contain:
// { "id":"...", "camera_id":"tapo-c200-1", "allowptz":true }
//
// This proxy will look up camera_id in cfg/cameras.json and perform PTZ using stored credentials.
require_once __DIR__ . '/../onvif_client.php';
header('Content-Type: application/json');

$hash = preg_replace('/[^a-f0-9]/', '', ($_POST['hash'] ?? ''));
$action = $_POST['action'] ?? '';
$presetToken = $_POST['presetToken'] ?? null;

if (!$hash || !$action) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'hash and action required']);
    exit;
}

$pubFile = __DIR__ . '/../cfg/public-cameras/' . $hash . '.json';
if (!file_exists($pubFile)) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'public token not found']);
    exit;
}

$pub = json_decode(file_get_contents($pubFile), true);
if (!$pub) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'invalid public config']); exit; }
if (empty($pub['allowptz'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'ptz not allowed']); exit; }
if (empty($pub['camera_id'])) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'public config missing camera mapping']); exit; }

$cameraId = $pub['camera_id'];

// find camera in cfg/cameras.json
$camerasCfg = load_json_cfg('cameras.json', ['cameras'=>[]])['cameras'];
$camera = null;
foreach ($camerasCfg as $c) {
    if (($c['id'] ?? '') === $cameraId) { $camera = $c; break; }
}
if (!$camera) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'mapped camera not found']); exit; }

$ip = $camera['ip']; $user = $camera['username']; $pass = $camera['password'];
$deviceServiceUrl = $camera['device_service_url'] ?? "http://{$ip}:2020/onvif/device_service";

try {
    $onvif = onvif_client($ip, $user, $pass, $deviceServiceUrl);

    // Need a profile token: if public config stored profileToken use it, otherwise pick first source
    $profileToken = $pub['profileToken'] ?? null;
    if (!$profileToken) {
        $sources = $onvif->getSources();
        if (empty($sources) || !isset($sources[0][1]['profiletoken'])) {
            throw new Exception('No profile token found');
        }
        $profileToken = $sources[0][1]['profiletoken'];
    }

    switch ($action) {
        case 'up': $onvif->ptz_ContinuousMove($profileToken, 0.0, 0.5); break;
        case 'down': $onvif->ptz_ContinuousMove($profileToken, 0.0, -0.5); break;
        case 'left': $onvif->ptz_ContinuousMove($profileToken, -0.5, 0.0); break;
        case 'right': $onvif->ptz_ContinuousMove($profileToken, 0.5, 0.0); break;
        case 'zoom_in': $onvif->ptz_ContinuousMoveZoom($profileToken, 0.5); break;
        case 'zoom_out': $onvif->ptz_ContinuousMoveZoom($profileToken, -0.5); break;
        case 'stop': $onvif->ptz_Stop($profileToken, 'true', 'true'); break;
        case 'preset_goto':
            if (!$presetToken) throw new Exception('presetToken required');
            $onvif->ptz_GotoPreset($profileToken, $presetToken, 0.5, 0.5, 0.5);
            break;
        default:
            throw new Exception('Unknown action');
    }

    echo json_encode(['ok'=>true,'action'=>$action]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}