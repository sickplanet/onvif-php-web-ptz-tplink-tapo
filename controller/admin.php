<?php
/**
 * controller/admin.php - Admin controller for managing cameras and users
 * Handles adding/editing/deleting cameras and users
 */
require_once __DIR__ . '/../onvif_client.php';
require_once __DIR__ . '/ErrorHandler.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '/';

// Require admin authentication
if (empty($_SESSION['user']) || empty($_SESSION['user']['isadmin'])) {
    ErrorHandler::handleError('Admin access required', 403, $baseUrl . 'login');
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'view';
$type = $_POST['type'] ?? $_GET['type'] ?? 'cameras'; // 'cameras' or 'users'

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ErrorHandler::wrap(function() use ($action, $type, $baseUrl) {
        switch ($action) {
            case 'add_camera':
                addCamera();
                break;
            case 'edit_camera':
                editCamera();
                break;
            case 'delete_camera':
                deleteCamera();
                break;
            case 'add_user':
                addUser();
                break;
            case 'edit_user':
                editUser();
                break;
            case 'delete_user':
                deleteUser();
                break;
            case 'save_settings':
                saveSettings();
                break;
            default:
                ErrorHandler::handleError('Unknown action: ' . $action, 400);
        }
    }, $baseUrl . 'admin');
}

// === Settings Functions ===

function saveSettings(): void {
    $isPublic = !empty($_POST['isPublic']);
    $scanCidrs = trim($_POST['scan_cidrs'] ?? '');
    $allowedCidrs = trim($_POST['allowed_cidrs'] ?? '');
    
    // Parse CIDRs (one per line or comma-separated)
    $scanCidrsArray = array_filter(array_map('trim', preg_split('/[\n,]+/', $scanCidrs)));
    $allowedCidrsArray = array_filter(array_map('trim', preg_split('/[\n,]+/', $allowedCidrs)));
    
    // Validate CIDR format (basic check)
    foreach ($scanCidrsArray as $cidr) {
        if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $cidr) && 
            !preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $cidr)) {
            ErrorHandler::handleError('Invalid scan CIDR format: ' . $cidr, 400);
        }
    }
    
    foreach ($allowedCidrsArray as $cidr) {
        if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $cidr) && 
            !preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $cidr)) {
            ErrorHandler::handleError('Invalid allowed CIDR format: ' . $cidr, 400);
        }
    }
    
    $config = load_json_cfg('config.json', []);
    $config['isPublic'] = $isPublic;
    $config['camera_discovery_cidrs'] = $scanCidrsArray;
    $config['allowed_cidrs'] = $allowedCidrsArray;
    
    save_json_cfg('config.json', $config);
    
    ErrorHandler::handleSuccess([], null, 'Settings saved successfully');
}

// === Camera Functions ===

function addCamera(): void {
    $ip = trim($_POST['ip'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $device_service_url = trim($_POST['device_service_url'] ?? '');
    
    if ($ip === '' || $name === '') {
        ErrorHandler::handleError('IP and name are required', 400);
    }
    
    $camerasCfg = load_json_cfg('cameras.json', ['cameras' => []]);
    $cameras = $camerasCfg['cameras'] ?? [];
    
    $id = substr(md5($ip . time()), 0, 12);
    if ($device_service_url === '') {
        $device_service_url = "http://{$ip}:2020/onvif/device_service";
    }
    
    // Try to get device info
    $manufacturer = '';
    $model = '';
    if ($username !== '' && $password !== '' && class_exists('Ponvif')) {
        try {
            $onvif = onvif_client($ip, $username, $password, $device_service_url);
            $info = $onvif->core_GetDeviceInformation();
            if ($info && is_array($info)) {
                $manufacturer = $info['Manufacturer'] ?? '';
                $model = $info['Model'] ?? '';
            }
        } catch (Exception $e) {
            // Ignore - try unauthenticated
        }
    }
    
    if (empty($manufacturer)) {
        $pi = get_device_public_info($device_service_url);
        if ($pi['ok']) {
            $manufacturer = $pi['info']['Manufacturer'] ?? '';
            $model = $pi['info']['Model'] ?? '';
        }
    }
    
    $new = [
        'id' => $id,
        'name' => $name,
        'ip' => $ip,
        'username' => $username,
        'password' => $password,
        'device_service_url' => $device_service_url,
        'manufacturer' => $manufacturer,
        'model' => $model,
        'streams' => [
            'high' => "rtsp://{$ip}:554/stream1",
            'low' => "rtsp://{$ip}:554/stream2",
        ],
        'allowptz' => !empty($_POST['allowptz']),
        'allow_audio' => !empty($_POST['allow_audio'])
    ];
    
    $cameras[] = $new;
    $camerasCfg['cameras'] = $cameras;
    save_json_cfg('cameras.json', $camerasCfg);
    
    ErrorHandler::handleSuccess(['camera' => $new], null, 'Camera added successfully');
}

function editCamera(): void {
    $id = $_POST['id'] ?? '';
    if ($id === '') {
        ErrorHandler::handleError('Camera ID required', 400);
    }
    
    $camerasCfg = load_json_cfg('cameras.json', ['cameras' => []]);
    $cameras = $camerasCfg['cameras'] ?? [];
    
    $found = false;
    foreach ($cameras as &$cam) {
        if (($cam['id'] ?? '') === $id) {
            $cam['name'] = trim($_POST['name'] ?? $cam['name']);
            $cam['ip'] = trim($_POST['ip'] ?? $cam['ip']);
            $cam['username'] = trim($_POST['username'] ?? $cam['username']);
            $cam['password'] = trim($_POST['password'] ?? $cam['password']);
            $cam['device_service_url'] = trim($_POST['device_service_url'] ?? $cam['device_service_url']);
            $cam['allowptz'] = !empty($_POST['allowptz']);
            $cam['allow_audio'] = !empty($_POST['allow_audio']);
            $found = true;
            break;
        }
    }
    unset($cam);
    
    if (!$found) {
        ErrorHandler::handleError('Camera not found', 404);
    }
    
    $camerasCfg['cameras'] = $cameras;
    save_json_cfg('cameras.json', $camerasCfg);
    
    ErrorHandler::handleSuccess([], null, 'Camera updated successfully');
}

function deleteCamera(): void {
    $id = $_POST['id'] ?? '';
    if ($id === '') {
        ErrorHandler::handleError('Camera ID required', 400);
    }
    
    $camerasCfg = load_json_cfg('cameras.json', ['cameras' => []]);
    $cameras = $camerasCfg['cameras'] ?? [];
    
    $newCameras = array_filter($cameras, function($cam) use ($id) {
        return ($cam['id'] ?? '') !== $id;
    });
    
    if (count($newCameras) === count($cameras)) {
        ErrorHandler::handleError('Camera not found', 404);
    }
    
    $camerasCfg['cameras'] = array_values($newCameras);
    save_json_cfg('cameras.json', $camerasCfg);
    
    ErrorHandler::handleSuccess([], null, 'Camera deleted successfully');
}

// === User Functions ===

function addUser(): void {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $isadmin = !empty($_POST['isadmin']);
    
    if ($username === '' || $password === '') {
        ErrorHandler::handleError('Username and password are required', 400);
    }
    
    $usersCfg = load_json_cfg('users.json', ['users' => []]);
    $users = $usersCfg['users'] ?? [];
    
    // Check if username exists
    foreach ($users as $u) {
        if (($u['username'] ?? '') === $username) {
            ErrorHandler::handleError('Username already exists', 400);
        }
    }
    
    $new = [
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'isadmin' => $isadmin,
        'cameras' => []
    ];
    
    $users[] = $new;
    $usersCfg['users'] = $users;
    save_json_cfg('users.json', $usersCfg);
    
    ErrorHandler::handleSuccess(['username' => $username], null, 'User added successfully');
}

function editUser(): void {
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $isadmin = !empty($_POST['isadmin']);
    
    if ($username === '') {
        ErrorHandler::handleError('Username required', 400);
    }
    
    $usersCfg = load_json_cfg('users.json', ['users' => []]);
    $users = $usersCfg['users'] ?? [];
    
    $found = false;
    foreach ($users as &$u) {
        if (($u['username'] ?? '') === $username) {
            if ($newPassword !== '') {
                $u['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            $u['isadmin'] = $isadmin;
            $found = true;
            break;
        }
    }
    unset($u);
    
    if (!$found) {
        ErrorHandler::handleError('User not found', 404);
    }
    
    $usersCfg['users'] = $users;
    save_json_cfg('users.json', $usersCfg);
    
    ErrorHandler::handleSuccess([], null, 'User updated successfully');
}

function deleteUser(): void {
    $username = trim($_POST['username'] ?? '');
    
    if ($username === '') {
        ErrorHandler::handleError('Username required', 400);
    }
    
    // Prevent deleting self
    if (!empty($_SESSION['user']['username']) && $_SESSION['user']['username'] === $username) {
        ErrorHandler::handleError('Cannot delete your own account', 400);
    }
    
    $usersCfg = load_json_cfg('users.json', ['users' => []]);
    $users = $usersCfg['users'] ?? [];
    
    $newUsers = array_filter($users, function($u) use ($username) {
        return ($u['username'] ?? '') !== $username;
    });
    
    if (count($newUsers) === count($users)) {
        ErrorHandler::handleError('User not found', 404);
    }
    
    $usersCfg['users'] = array_values($newUsers);
    save_json_cfg('users.json', $usersCfg);
    
    ErrorHandler::handleSuccess([], null, 'User deleted successfully');
}

// If GET request, show admin page
include __DIR__ . '/../view/page_admin.php';
