<?php
/**
 * controller/profiles.php - Returns media profiles for a device
 * Strictly returns JSON and handles exceptions properly
 */
require_once __DIR__ . '/../onvif_client.php';
require_once __DIR__ . '/ErrorHandler.php';

// Always set JSON content type first
header('Content-Type: application/json');

try {
    $deviceId = $_GET['deviceId'] ?? null;

    if (!$deviceId) {
        ErrorHandler::json(['ok' => false, 'error' => 'deviceId required'], 400);
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
        ErrorHandler::json(['ok' => false, 'error' => 'Device not found'], 404);
    }

    $ip = $device['ip'];
    $user = $device['username'] ?? '';
    $pass = $device['password'] ?? '';
    $deviceServiceUrl = $device['device_service_url'] ?? "http://{$ip}:2020/onvif/device_service";

    $onvif = onvif_client($ip, $user, $pass, $deviceServiceUrl);
    
    // Get media profiles - returns array of profile sources
    $sources = $onvif->getSources();
    
    // Format sources for the frontend
    $formattedSources = [];
    if (is_array($sources)) {
        foreach ($sources as $key => $source) {
            $formattedSources[] = [$key, $source];
        }
    }

    ErrorHandler::json([
        'ok' => true,
        'deviceId' => $deviceId,
        'sources' => $formattedSources
    ]);
} catch (Throwable $e) {
    error_log("profiles.php error: " . $e->getMessage());
    ErrorHandler::json(['ok' => false, 'error' => $e->getMessage()], 500);
}
