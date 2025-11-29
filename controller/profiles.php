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

/**
 * Helper function to extract profile data from various ONVIF response formats
 * Normalizes property access across different naming conventions
 */
function extractProfileData(array $profileData, string $fallbackKey): ?array {
    // Try different property naming conventions
    $nameKeys = ['profilename', 'Name', 'name'];
    $tokenKeys = ['profiletoken', 'token', 'Token'];
    
    $profileName = null;
    $profileToken = null;
    
    foreach ($nameKeys as $key) {
        if (isset($profileData[$key])) {
            $profileName = $profileData[$key];
            break;
        }
    }
    
    foreach ($tokenKeys as $key) {
        if (isset($profileData[$key])) {
            $profileToken = $profileData[$key];
            break;
        }
    }
    
    // Use fallback name if not found
    if ($profileName === null) {
        $profileName = "Profile {$fallbackKey}";
    }
    
    // Only return if we have a valid token
    if ($profileToken !== null) {
        return [
            'name' => $profileName,
            'token' => $profileToken
        ];
    }
    
    return null;
}

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
    // ponvif's getSources() returns: 
    // [ sourceIndex => [ 'sourcetoken' => '...', 0 => ['profilename'=>..., 'profiletoken'=>...], 1 => [...], ... ] ]
    $profiles = [];
    
    if (is_array($sources)) {
        foreach ($sources as $sourceKey => $source) {
            // Each source is an array with 'sourcetoken' and indexed profile data
            if (is_array($source)) {
                foreach ($source as $profileKey => $profileData) {
                    // Skip 'sourcetoken' key, only process numeric indices (0, 1, 2...)
                    if ($profileKey === 'sourcetoken') {
                        continue;
                    }
                    
                    if (is_array($profileData)) {
                        $extracted = extractProfileData($profileData, (string)$profileKey);
                        if ($extracted !== null) {
                            $profiles[] = $extracted;
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
    
    // Log debug info if no profiles found
    if (empty($profiles)) {
        error_log("profiles.php: No profiles parsed from sources: " . json_encode($sources));
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
