<?php
/**
 * controller/profiles.php - Returns media profiles for a device
 * Strictly returns JSON and handles exceptions properly
 * 
 * Returns simplified structure:
 * {
 *   "ok": true,
 *   "deviceId": "...",
 *   "profiles": [{"name": "MainStream", "token": "profile_1"}, ...]
 * }
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
    
    // Parse and flatten the nested structure into simplified profiles array
    $profiles = [];
    
    if (is_array($sources)) {
        foreach ($sources as $sourceKey => $source) {
            // $source could be:
            // - A nested array like [0 => ['profilename' => '...', 'profiletoken' => '...'], 1 => [...]]
            // - Or a single profile object
            
            if (is_array($source)) {
                // Check if this is a source with indexed profiles (e.g., ["0" => {...}, "1" => {...}])
                foreach ($source as $profileKey => $profileData) {
                    if (is_array($profileData)) {
                        // Extract profile name and token from various possible structures
                        $profileName = $profileData['profilename'] ?? $profileData['Name'] ?? $profileData['name'] ?? "Profile {$profileKey}";
                        $profileToken = $profileData['profiletoken'] ?? $profileData['token'] ?? $profileData['Token'] ?? null;
                        
                        if ($profileToken) {
                            $profiles[] = [
                                'name' => $profileName,
                                'token' => $profileToken
                            ];
                        }
                    } elseif (is_string($profileData)) {
                        // Handle case where profile might be a simple string token
                        $profiles[] = [
                            'name' => "Profile {$profileKey}",
                            'token' => $profileData
                        ];
                    }
                }
            }
        }
    }
    
    // If no profiles found but we have sources, try alternative parsing
    if (empty($profiles) && is_array($sources) && count($sources) > 0) {
        // Try to handle the structure: [[0, {0: {profilename, profiletoken}, 1: {...}}]]
        foreach ($sources as $sourceEntry) {
            if (is_array($sourceEntry) && count($sourceEntry) >= 2) {
                $profilesObj = $sourceEntry[1] ?? null;
                if (is_array($profilesObj)) {
                    foreach ($profilesObj as $key => $profile) {
                        if (is_array($profile)) {
                            $profileName = $profile['profilename'] ?? $profile['Name'] ?? "Profile {$key}";
                            $profileToken = $profile['profiletoken'] ?? $profile['token'] ?? null;
                            
                            if ($profileToken) {
                                $profiles[] = [
                                    'name' => $profileName,
                                    'token' => $profileToken
                                ];
                            }
                        }
                    }
                }
            }
        }
    }

    ErrorHandler::json([
        'ok' => true,
        'deviceId' => $deviceId,
        'profiles' => $profiles
    ]);
} catch (Throwable $e) {
    error_log("profiles.php error: " . $e->getMessage());
    ErrorHandler::json(['ok' => false, 'error' => $e->getMessage()], 500);
}
