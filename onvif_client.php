<?php
// Common helper to create and initialize a Ponvif client for a given camera.
// Assumes ponvif/class.ponvif.php is at ponvif/lib/class.ponvif.php

require_once __DIR__ . '/ponvif/lib/class.ponvif.php';

function onvif_client(string $ip, string $username, string $password, ?string $deviceServiceUrl = null): Ponvif {
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
    $s = file_get_contents($path);
    return json_decode($s, true) ?: $default;
}

function save_json_cfg(string $file, $data) {
    $path = cfg_path($file);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}