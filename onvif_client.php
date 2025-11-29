<?php
// onvif_client.php - robust loader + helper functions (updated with device public info fetch)
// Tries to include ponvif from common location(s). If Ponvif class already included, this is safe.
//
// Also exposes:
//  - onvif_client($ip,$username,$password,$deviceServiceUrl)
//  - get_device_public_info($deviceServiceUrl)  <-- attempts unauthenticated GetDeviceInformation
//  - cfg_path/load_json_cfg/save_json_cfg helpers

// Locate Ponvif class (if not already loaded)
if (!class_exists('Ponvif')) {
    $possible = [
        __DIR__ . '/ponvif/lib/class.ponvif.php',
        __DIR__ . '/model/ponvif/lib/class.ponvif.php',
        __DIR__ . '/model/ponvif/class.ponvif.php',
        __DIR__ . '/vendor/kuroneko-san/ponvif/lib/class.ponvif.php',
    ];
    $found = null;
    foreach ($possible as $p) {
        if (file_exists($p) && is_readable($p)) { $found = $p; break; }
    }
    if ($found) {
        require_once $found;
    } else {
        // Do not fatal here; we will let endpoints fail later with helpful message.
        error_log("Ponvif class not found. Checked:\n - " . implode("\n - ", $possible));
    }
}

/**
 * Attempt to perform unauthenticated GetDeviceInformation SOAP request
 * to the device service URI. Returns associative array with keys:
 *  - ok: bool
 *  - info: array (Manufacturer, Model, FirmwareVersion, SerialNumber, HardwareId) on success
 *  - error: string on failure
 *
 * This function sends a minimal SOAP envelope without WSSE.
 */
function get_device_public_info(string $deviceServiceUrl): array {
    $soap = <<<SOAP
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <GetDeviceInformation xmlns="http://www.onvif.org/ver10/device/wsdl"/>
  </s:Body>
</s:Envelope>
SOAP;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $deviceServiceUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $soap);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/soap+xml; charset=utf-8', 'Content-Length: ' . strlen($soap)]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    // Some devices use self-signed certificates if https; do not verify for discovery
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $result = @curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result === false || $httpCode >= 400) {
        return ['ok' => false, 'error' => $err ?: ("HTTP " . $httpCode)];
    }

    // Convert XML envelope to array similar style used by ponvif class _xml2array
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($result);
    if ($xml === false) {
        return ['ok' => false, 'error' => 'Invalid XML response'];
    }
    $json = json_decode(json_encode($xml), true);
    // The structure will include Envelope->Body->GetDeviceInformationResponse
    // Attempt to fetch the info
    $info = null;
    if (isset($json['Body']['GetDeviceInformationResponse'])) {
        $info = $json['Body']['GetDeviceInformationResponse'];
    } elseif (isset($json['Envelope']['Body']['GetDeviceInformationResponse'])) {
        $info = $json['Envelope']['Body']['GetDeviceInformationResponse'];
    } else {
        // Try deeper search
        $found = null;
        array_walk_recursive($json, function($v,$k) use (&$found) {
            if ($k === 'GetDeviceInformationResponse') $found = $v;
        });
        if ($found) $info = $found;
    }

    if (!$info || !is_array($info)) {
        return ['ok' => false, 'error' => 'No device info in response'];
    }

    // Normalize known fields if available
    $out = [];
    if (isset($info['Manufacturer'])) $out['Manufacturer'] = $info['Manufacturer'];
    if (isset($info['Model'])) $out['Model'] = $info['Model'];
    if (isset($info['FirmwareVersion'])) $out['FirmwareVersion'] = $info['FirmwareVersion'];
    if (isset($info['SerialNumber'])) $out['SerialNumber'] = $info['SerialNumber'];
    if (isset($info['HardwareId'])) $out['HardwareId'] = $info['HardwareId'];

    return ['ok' => true, 'info' => $out];
}

/**
 * Create and initialize Ponvif client.
 * If Ponvif class is not available, throws Exception.
 */
function onvif_client(string $ip, string $username, string $password, ?string $deviceServiceUrl = null): Ponvif {
    if (!class_exists('Ponvif')) {
        throw new Exception('Ponvif library not loaded. See server logs.');
    }
    $onvif = new Ponvif();
    $onvif->setUsername($username);
    $onvif->setPassword($password);
    $onvif->setIPAddress($ip);

    if ($deviceServiceUrl) {
        $onvif->setMediaUri($deviceServiceUrl);
    }
    $onvif->initialize();
    return $onvif;
}

function cfg_path(string $file = ''): string {
    return __DIR__ . '/cfg' . ($file ? '/' . $file : '');
}

function load_json_cfg(string $file, $default = []) {
    $path = cfg_path($file);
    if (!file_exists($path)) return $default;
    $s = @file_get_contents($path);
    if ($s === false) return $default;
    $d = json_decode($s, true);
    return is_array($d) ? $d : $default;
}

function save_json_cfg(string $file, $data) {
    $path = cfg_path($file);
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}