<?php
/**
 * Events Controller
 * Handles ONVIF event subscription and polling for motion detection, PTZ limits, etc.
 */
require_once __DIR__ . '/../../onvif_client.php';
require_once __DIR__ . '/../ErrorHandler.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? 'poll';
$deviceId = $_POST['deviceId'] ?? $_GET['deviceId'] ?? null;

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

// Load event settings
$eventSettings = load_json_cfg('event_settings.json', [
    'motionDetectionEnabled' => true,
    'browserNotificationsEnabled' => true,
    'pollIntervalSeconds' => 5
]);

switch ($action) {
    case 'poll':
        pollEvents($ip, $user, $pass, $deviceServiceUrl, $deviceId, $eventSettings);
        break;
    
    case 'get_settings':
        ErrorHandler::json([
            'ok' => true,
            'settings' => $eventSettings,
            'cameraEvents' => getCameraEventSettings($deviceId)
        ]);
        break;
    
    case 'save_settings':
        saveEventSettings($deviceId);
        break;
    
    default:
        ErrorHandler::json(['ok' => false, 'error' => 'Unknown action'], 400);
}

/**
 * Poll for events from the camera
 */
function pollEvents(string $ip, string $user, string $pass, string $deviceServiceUrl, string $deviceId, array $settings): void {
    $events = [];
    
    try {
        // Try to get event service and poll for events
        $onvif = onvif_client($ip, $user, $pass, $deviceServiceUrl);
        $capabilities = $onvif->getCapabilities();
        
        // Check if events service is available
        $eventsXAddr = $capabilities['Events']['XAddr'] ?? null;
        
        if ($eventsXAddr) {
            // Try to poll for events using PullPoint subscription
            $eventData = pollPullPointEvents($eventsXAddr, $user, $pass);
            
            if ($eventData) {
                // Parse events
                foreach ($eventData as $event) {
                    $events[] = parseEvent($event, $deviceId);
                }
            }
        }
        
        // Check PTZ status for limit events
        $ptzUri = $onvif->getPTZUri();
        if ($ptzUri) {
            $ptzStatus = getPTZStatus($ptzUri, $user, $pass, $deviceId);
            if ($ptzStatus && !empty($ptzStatus['limitReached'])) {
                $events[] = [
                    'type' => 'ptz_limit',
                    'deviceId' => $deviceId,
                    'timestamp' => date('c'),
                    'data' => $ptzStatus
                ];
            }
        }
        
        // Save events to event log
        if (!empty($events)) {
            logEvents($deviceId, $events);
        }
        
        ErrorHandler::json([
            'ok' => true,
            'events' => $events,
            'polledAt' => date('c')
        ]);
        
    } catch (Exception $e) {
        ErrorHandler::json([
            'ok' => true,
            'events' => [],
            'error' => $e->getMessage(),
            'polledAt' => date('c')
        ]);
    }
}

/**
 * Try to poll events using PullPoint subscription (simplified version)
 */
function pollPullPointEvents(string $eventsXAddr, string $user, string $pass): ?array {
    // This is a simplified implementation
    // Full ONVIF event subscription requires complex WS-BaseNotification handling
    
    // For now, we'll check for simple motion detection via the events service
    $soapEnvelope = buildPullMessagesRequest($user, $pass);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $eventsXAddr);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $soapEnvelope);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/soap+xml; charset=utf-8',
        'Content-Length: ' . strlen($soapEnvelope)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    // Parse response for motion events
    return parseEventsResponse($response);
}

/**
 * Build a minimal SOAP request for pulling messages
 */
function buildPullMessagesRequest(string $user, string $pass): string {
    $nonce = base64_encode(random_bytes(16));
    $created = gmdate('Y-m-d\TH:i:s\Z');
    $digest = base64_encode(sha1(base64_decode($nonce) . $created . $pass, true));
    
    return <<<SOAP
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"
            xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
            xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"
            xmlns:tev="http://www.onvif.org/ver10/events/wsdl">
    <s:Header>
        <wsse:Security s:mustUnderstand="1">
            <wsse:UsernameToken>
                <wsse:Username>{$user}</wsse:Username>
                <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">{$digest}</wsse:Password>
                <wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">{$nonce}</wsse:Nonce>
                <wsu:Created>{$created}</wsu:Created>
            </wsse:UsernameToken>
        </wsse:Security>
    </s:Header>
    <s:Body>
        <tev:GetEventProperties/>
    </s:Body>
</s:Envelope>
SOAP;
}

/**
 * Parse events response from SOAP
 */
function parseEventsResponse(string $response): array {
    $events = [];
    
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($response);
    if (!$xml) return $events;
    
    // Look for motion detection events
    $json = json_decode(json_encode($xml), true);
    
    // Check for RuleEngine/CellMotionDetector or VideoAnalytics motion events
    if (isset($json['Body']['GetEventPropertiesResponse']['TopicSet'])) {
        $topicSet = $json['Body']['GetEventPropertiesResponse']['TopicSet'];
        
        // Check if motion detection is supported
        if (isset($topicSet['RuleEngine']['CellMotionDetector']) ||
            isset($topicSet['VideoAnalytics']['Motion'])) {
            $events[] = [
                'type' => 'motion_capability',
                'supported' => true
            ];
        }
    }
    
    return $events;
}

/**
 * Get PTZ status and check for limits
 */
function getPTZStatus(string $ptzUri, string $user, string $pass, string $deviceId): ?array {
    // Simplified PTZ status check
    // In a full implementation, this would call GetStatus and parse the response
    return null;
}

/**
 * Parse a single event
 */
function parseEvent(array $event, string $deviceId): array {
    return [
        'type' => $event['type'] ?? 'unknown',
        'deviceId' => $deviceId,
        'timestamp' => date('c'),
        'data' => $event
    ];
}

/**
 * Log events to file
 */
function logEvents(string $deviceId, array $events): void {
    $logDir = cfg_path('event_logs');
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    
    $logFile = $logDir . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $deviceId) . '.json';
    
    $existingLogs = [];
    if (file_exists($logFile)) {
        $existingLogs = json_decode(file_get_contents($logFile), true) ?: [];
    }
    
    // Add new events
    $existingLogs = array_merge($existingLogs, $events);
    
    // Keep only last 1000 events
    if (count($existingLogs) > 1000) {
        $existingLogs = array_slice($existingLogs, -1000);
    }
    
    file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
}

/**
 * Get event settings for a specific camera
 */
function getCameraEventSettings(string $deviceId): array {
    $cameras = load_json_cfg('cameras.json', ['cameras' => []])['cameras'];
    foreach ($cameras as $cam) {
        if (($cam['id'] ?? '') === $deviceId) {
            return [
                'motionDetectionEnabled' => $cam['motionDetectionEnabled'] ?? true,
                'browserNotificationsEnabled' => $cam['browserNotificationsEnabled'] ?? true
            ];
        }
    }
    return [];
}

/**
 * Save event settings
 */
function saveEventSettings(string $deviceId): void {
    $motionEnabled = isset($_POST['motionDetectionEnabled']) && $_POST['motionDetectionEnabled'] === 'true';
    $notificationsEnabled = isset($_POST['browserNotificationsEnabled']) && $_POST['browserNotificationsEnabled'] === 'true';
    
    // Update camera-specific settings
    $camerasCfg = load_json_cfg('cameras.json', ['cameras' => []]);
    $cameras = $camerasCfg['cameras'] ?? [];
    
    foreach ($cameras as &$cam) {
        if (($cam['id'] ?? '') === $deviceId) {
            $cam['motionDetectionEnabled'] = $motionEnabled;
            $cam['browserNotificationsEnabled'] = $notificationsEnabled;
            break;
        }
    }
    unset($cam);
    
    $camerasCfg['cameras'] = $cameras;
    save_json_cfg('cameras.json', $camerasCfg);
    
    // Update global settings if provided
    if (isset($_POST['pollIntervalSeconds'])) {
        $globalSettings = load_json_cfg('event_settings.json', []);
        $globalSettings['pollIntervalSeconds'] = max(1, intval($_POST['pollIntervalSeconds']));
        save_json_cfg('event_settings.json', $globalSettings);
    }
    
    ErrorHandler::json(['ok' => true, 'message' => 'Settings saved']);
}
