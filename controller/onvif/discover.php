<?php
// controller/onvif/discover.php
// Discovery endpoint used by the UI. Returns JSON list of discovered devices + best-effort public device info.
header('Content-Type: application/json');
require_once __DIR__ . '/../../onvif_client.php';

$probe = new Ponvif();
$probe->setDiscoveryTimeout(2);

try {
    $result = $probe->discover();
    if (!is_array($result)) $result = [];

    // Normalize to array of devices with IPAddr and XAddrs
    $out = [];
    if (is_assoc($result)) {
        // keyed by IP
        foreach ($result as $k => $v) {
            if (!is_array($v)) continue;
            $v['IPAddr'] = $v['IPAddr'] ?? $k;
            $v['XAddrs'] = $v['XAddrs'] ?? ($v['XAddrs'] ?? '');
            // attempt public info retrieval from device service if XAddrs present
            if (!empty($v['XAddrs'])) {
                $deviceService = is_array($v['XAddrs']) ? $v['XAddrs'][0] : $v['XAddrs'];
                $pi = get_device_public_info($deviceService);
                if ($pi['ok']) $v['info'] = $pi['info'];
            }
            $out[] = $v;
        }
    } else {
        // indexed array
        foreach ($result as $v) {
            if (!is_array($v)) continue;
            $v['IPAddr'] = $v['IPAddr'] ?? ($v['EndpointReference']['Address'] ?? '');
            $v['XAddrs'] = $v['XAddrs'] ?? '';
            if (!empty($v['XAddrs'])) {
                $deviceService = is_array($v['XAddrs']) ? $v['XAddrs'][0] : $v['XAddrs'];
                $pi = get_device_public_info($deviceService);
                if ($pi['ok']) $v['info'] = $pi['info'];
            }
            $out[] = $v;
        }
    }

    echo json_encode(['ok' => true, 'devices' => $out], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

function is_assoc($arr) {
    if (!is_array($arr)) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}