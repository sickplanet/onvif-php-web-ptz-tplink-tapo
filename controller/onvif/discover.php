<?php
// Run discovery and return devices; requires network support for multicast from the server.
require_once __DIR__ . '/../../ponvif/lib/class.ponvif.php';
header('Content-Type: application/json');

$onvif = new Ponvif();
$onvif->setDiscoveryTimeout(2);
$result = $onvif->discover();
echo json_encode(['ok'=>true,'devices'=>$result]);