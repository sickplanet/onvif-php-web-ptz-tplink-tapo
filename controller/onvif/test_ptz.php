<?php
/**
 * PTZ Test Controller
 * Tests PTZ capabilities by performing a sequence of movements (left, right, up, down)
 * and reports which directions work.
 */
require_once __DIR__ . '/../../onvif_client.php';
require_once __DIR__ . '/../ErrorHandler.php';
header('Content-Type: application/json');

$deviceId = $_POST['deviceId'] ?? $_GET['deviceId'] ?? null;
$profileToken = $_POST['profileToken'] ?? $_GET['profileToken'] ?? null;

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

$results = [
    'hasPTZ' => false,
    'ptzDirections' => [],
    'tests' => []
];

try {
    $onvif = onvif_client($ip, $user, $pass, $deviceServiceUrl);
    
    // Check if PTZ service is available
    $ptzUri = $onvif->getPTZUri();
    if (empty($ptzUri)) {
        $results['tests'][] = ['action' => 'ptz_check', 'success' => false, 'message' => 'No PTZ service available'];
        
        // Update camera config
        updateCameraConfig($deviceId, $results);
        
        ErrorHandler::json([
            'ok' => true,
            'hasPTZ' => false,
            'ptzDirections' => [],
            'results' => $results
        ]);
    }
    
    // Get profile token if not provided
    if (!$profileToken) {
        $sources = $onvif->getSources();
        if (is_array($sources) && count($sources) > 0) {
            foreach ($sources as $source) {
                if (is_array($source)) {
                    foreach ($source as $profile) {
                        if (is_array($profile) && !empty($profile['profiletoken'])) {
                            $profileToken = $profile['profiletoken'];
                            break 2;
                        }
                    }
                }
            }
        }
    }
    
    if (!$profileToken) {
        $results['tests'][] = ['action' => 'profile_check', 'success' => false, 'message' => 'No profile token found'];
        updateCameraConfig($deviceId, $results);
        ErrorHandler::json([
            'ok' => true,
            'hasPTZ' => false,
            'ptzDirections' => [],
            'results' => $results
        ]);
    }
    
    $results['hasPTZ'] = true;
    
    // Define test movements
    $testSequence = [
        ['action' => 'left', 'x' => -0.3, 'y' => 0.0],
        ['action' => 'right', 'x' => 0.3, 'y' => 0.0],
        ['action' => 'up', 'x' => 0.0, 'y' => 0.3],
        ['action' => 'down', 'x' => 0.0, 'y' => -0.3]
    ];
    
    $workingDirections = [];
    
    foreach ($testSequence as $test) {
        $action = $test['action'];
        $success = false;
        $message = '';
        
        try {
            // Try continuous move
            $onvif->ptz_ContinuousMove($profileToken, $test['x'], $test['y']);
            usleep(300000); // 300ms
            
            // Stop movement
            $onvif->ptz_Stop($profileToken, 'true', 'true');
            usleep(200000); // 200ms
            
            $success = true;
            $message = 'OK';
            $workingDirections[] = $action;
        } catch (Exception $e) {
            $message = $e->getMessage();
            // Check if it's a limit/boundary error (still consider direction as working)
            if (stripos($message, 'limit') !== false || stripos($message, 'boundary') !== false) {
                $success = true;
                $message = 'OK (at limit)';
                $workingDirections[] = $action;
            }
        }
        
        $results['tests'][] = [
            'action' => $action,
            'success' => $success,
            'message' => $message
        ];
    }
    
    // Test zoom
    foreach (['zoom_in' => 0.3, 'zoom_out' => -0.3] as $action => $zoomValue) {
        $success = false;
        $message = '';
        
        try {
            $onvif->ptz_ContinuousMoveZoom($profileToken, $zoomValue);
            usleep(300000);
            $onvif->ptz_Stop($profileToken, 'true', 'true');
            usleep(200000);
            
            $success = true;
            $message = 'OK';
            $workingDirections[] = $action;
        } catch (Exception $e) {
            $message = $e->getMessage();
            if (stripos($message, 'limit') !== false || stripos($message, 'boundary') !== false) {
                $success = true;
                $message = 'OK (at limit)';
                $workingDirections[] = $action;
            }
        }
        
        $results['tests'][] = [
            'action' => $action,
            'success' => $success,
            'message' => $message
        ];
    }
    
    $results['ptzDirections'] = array_unique($workingDirections);
    
    // Update camera config with PTZ capabilities
    updateCameraConfig($deviceId, $results);
    
    ErrorHandler::json([
        'ok' => true,
        'hasPTZ' => $results['hasPTZ'],
        'ptzDirections' => $results['ptzDirections'],
        'results' => $results
    ]);
    
} catch (Exception $e) {
    // Failed to initialize ONVIF client
    $results['tests'][] = ['action' => 'init', 'success' => false, 'message' => $e->getMessage()];
    updateCameraConfig($deviceId, $results);
    
    ErrorHandler::json([
        'ok' => false,
        'error' => $e->getMessage(),
        'hasPTZ' => false,
        'ptzDirections' => [],
        'results' => $results
    ], 500);
}

/**
 * Update camera configuration with PTZ test results
 */
function updateCameraConfig(string $deviceId, array $results): void {
    $camerasCfg = load_json_cfg('cameras.json', ['cameras' => []]);
    $cameras = $camerasCfg['cameras'] ?? [];
    
    foreach ($cameras as &$cam) {
        if (($cam['id'] ?? '') === $deviceId) {
            $cam['hasPTZ'] = $results['hasPTZ'];
            $cam['ptzDirections'] = $results['ptzDirections'] ?? [];
            $cam['ptzTestedAt'] = date('c');
            break;
        }
    }
    unset($cam);
    
    $camerasCfg['cameras'] = $cameras;
    save_json_cfg('cameras.json', $camerasCfg);
}
