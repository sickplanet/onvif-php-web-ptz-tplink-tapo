<?php
session_start();

// Basic autoload / helpers
require __DIR__ . '/controller/message.php';
require __DIR__ . '/onvif_client.php';

// Setup detection: if cfg/configured does not exist, route to setup
$cfg_dir = __DIR__ . '/cfg';
$configured_flag = $cfg_dir . '/configured';

// Simple router based on REQUEST_URI relative to project
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$uri = substr($_SERVER['REQUEST_URI'], strlen($basePath));
$uri = strtok($uri, '?');
$uri = trim($uri, '/');
$segments = $uri === '' ? [] : explode('/', $uri);

// public routes: login, setup, public live
$publicRoutes = ['login', 'setup', 'cameras', 'cameras_public'];

// if not configured and not /setup, redirect to setup
if (!file_exists($configured_flag) && (empty($segments) || $segments[0] !== 'setup')) {
    header('Location: /onvif-ui/setup');
    exit;
}

// authentication (simple)
function is_logged_in() {
    return isset($_SESSION['user']);
}
function require_login() {
    if (!is_logged_in()) {
        header('Location: /onvif-ui/login');
        exit;
    }
}

// Routing table
$first = $segments[0] ?? '';

switch ($first) {
    case '':
    case 'home':
        // Main dashboard
        require_login();
        include __DIR__ . '/view/page_main.php';
        break;

    case 'login':
        include __DIR__ . '/controller/login.php';
        break;

    case 'setup':
        include __DIR__ . '/controller/setup.php';
        break;

    case 'message':
        include __DIR__ . '/controller/message.php';
        break;

    case 'cameras':
        // cameras subroutes: /cameras/scan /cameras/add /cameras/edit/{id} /cameras/live
        require_login();
        $sub = $segments[1] ?? '';
        if ($sub === 'scan') {
            include __DIR__ . '/controller/onvif/discover.php';
        } elseif ($sub === 'live') {
            include __DIR__ . '/controller/public_live.php';
        } else {
            include __DIR__ . '/view/page_cameras.php';
        }
        break;

    case 'onvif':
        // API endpoints under /onvif/...
        $sub = $segments[1] ?? '';
        if ($sub === 'ptz') {
            include __DIR__ . '/controller/onvif/ptz.php';
        } elseif ($sub === 'stream') {
            include __DIR__ . '/controller/onvif/stream.php';
        } elseif ($sub === 'profiles') {
            include __DIR__ . '/controller/profiles.php';
        } else {
            http_response_code(404);
            echo "Not Found";
        }
        break;

    case 'users':
        require_login();
        // Basic user management placeholder
        include __DIR__ . '/controller/users.php';
        break;

    case 'public':
        // public live view: /public/live/{hash}
        include __DIR__ . '/controller/public_live.php';
        break;

    default:
        // Default: if logged in show main, else login
        if (is_logged_in()) {
            include __DIR__ . '/view/page_main.php';
        } else {
            header('Location: /onvif-ui/login');
            exit;
        }
}