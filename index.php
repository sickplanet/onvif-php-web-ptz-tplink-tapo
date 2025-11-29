<?php
session_start();

// Define BASE_URL automatically – works in root OR subfolder
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];                   // example.com
    $uri    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // /onvif-ui  or /
    
    // If script is in root → $uri becomes ".", we fix it to ""
    if ($uri === '.' || $uri === '\\') $uri = '';
    
    define('BASE_URL', $scheme . '://' . $host . $uri . '/');
}

// Remove unconditional includes of controller/message.php — include when routing to it only
require_once __DIR__ . '/onvif_client.php';

// Helper functions
function is_logged_in(): bool {
    return !empty($_SESSION['user']);
}
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login');
        exit;
    }
}

// Configured flag path
$cfg_dir = __DIR__ . '/cfg';
$configured_flag = $cfg_dir . '/configured';

// Determine request path relative to script base
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$base = rtrim(dirname($scriptName), '/\\');
if ($base === '/' || $base === '\\') $base = ''; // normalize
$path = $requestUri;
if ($base !== '' && strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}
$path = trim($path, '/');
$segments = $path === '' ? [] : explode('/', $path);

// If not configured and not going to setup, redirect there.
// Allow access to /setup and assets (assets are handled by .htaccess serving files directly).
if (!file_exists($configured_flag)) {
    // If the request already targets setup, fall through and show setup page.
    $firstSegment = $segments[0] ?? '';
    if ($firstSegment !== 'setup') {
        header('Location: ' . ($base ?: '') . '/setup');
        exit;
    }
}

// Routing
$first = $segments[0] ?? '';

switch ($first) {

    case '':
    case 'home':
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
        // /cameras or /cameras/...
        // require authentication for camera management and views except public endpoints handled separately
        $sub = $segments[1] ?? '';
        if ($sub === 'scan') {
            require_login();
            include __DIR__ . '/controller/onvif/discover.php';
        } elseif ($sub === 'public') {
            // Accessing public subroutes (public view may be shown without login)
            include __DIR__ . '/controller/public_live.php';
        } else {
            require_login();
            include __DIR__ . '/view/page_cameras.php';
        }
        break;

    case 'onvif':
        // API endpoints under /onvif/...
        $sub = $segments[1] ?? '';
        if ($sub === 'ptz') {
            require_login();
            include __DIR__ . '/controller/onvif/ptz.php';
        } elseif ($sub === 'stream') {
            require_login();
            include __DIR__ . '/controller/onvif/stream.php';
        } elseif ($sub === 'profiles') {
            require_login();
            include __DIR__ . '/controller/profiles.php';
        } else {
            http_response_code(404);
            echo "Not Found";
        }
        break;

    case 'public':
        // /public/live/{hash}
        include __DIR__ . '/controller/public_live.php';
        break;

    default:
        if (is_logged_in()) {
            include __DIR__ . '/view/page_main.php';
        } else {
            header('Location: ' . ($base ?: '') . '/login');
        }
        break;
}