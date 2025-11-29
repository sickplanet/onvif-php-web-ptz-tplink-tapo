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

try {
    $onvif = onvif_client($ip, $user, $pass, $deviceServiceUrl);
    $streamUri = $onvif->media_GetStreamUri($profileToken);
    $snapshotUri = null;
    try { $snapshotUri = $onvif->media_GetSnapshotUri($profileToken); } catch (Exception $e) {}

    $rtsp_hints = [
        'high' => "rtsp://{$ip}:554/stream1",
        'low'  => "rtsp://{$ip}:554/stream2"
    ];

    echo json_encode(['ok'=>true,'streamUri'=>$streamUri,'snapshotUri'=>$snapshotUri,'rtspHints'=>$rtsp_hints]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}