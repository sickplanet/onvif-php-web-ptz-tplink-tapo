<?php
// Setup controller: create initial admin, basic configuration and cfg/configured flag
// Uses BASE_URL constant (defined in index.php)
require_once __DIR__ . '/../onvif_client.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
$cfgdir = __DIR__ . '/../cfg';
$configuredFile = $cfgdir . '/configured';

// Determine current step
$step = $_GET['step'] ?? '1';
$action = $_POST['action'] ?? '';

// If accessing setup page, delete all *.json files in /cfg/ for a clean slate
// But only on initial GET request (step 1), not during POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $step === '1' && !file_exists($configuredFile)) {
    // Clean up all JSON files for fresh setup
    if (is_dir($cfgdir)) {
        $jsonFiles = glob($cfgdir . '/*.json');
        foreach ($jsonFiles as $file) {
            @unlink($file);
        }
        // Also clean cameras-info directory
        $camerasInfoDir = $cfgdir . '/cameras-info';
        if (is_dir($camerasInfoDir)) {
            $infoFiles = glob($camerasInfoDir . '/*.json');
            foreach ($infoFiles as $file) {
                @unlink($file);
            }
        }
        // Clean event logs
        $eventLogsDir = $cfgdir . '/event_logs';
        if (is_dir($eventLogsDir)) {
            $logFiles = glob($eventLogsDir . '/*.json');
            foreach ($logFiles as $file) {
                @unlink($file);
            }
        }
    }
}

// if already configured, redirect with message (unless force reset)
if (file_exists($configuredFile) && ($_SERVER['REQUEST_METHOD'] !== 'POST') && !isset($_GET['reset'])) {
    header('Location: ' . $baseUrl . 'message?m=Already+configured');
    exit;
}

$errors = [];
$ok = false;
$scannedDevices = [];
$setupData = $_SESSION['setup_data'] ?? [];

// Handle AJAX scan request
if ($action === 'scan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (!class_exists('Ponvif')) {
            echo json_encode(['ok' => false, 'error' => 'Ponvif library not available']);
            exit;
        }
        
        $probe = new Ponvif();
        $probe->setDiscoveryTimeout(3);
        $result = $probe->discover();
        
        $devices = [];
        if (is_array($result)) {
            foreach ($result as $k => $v) {
                if (!is_array($v)) continue;
                $v['IPAddr'] = $v['IPAddr'] ?? $k;
                $v['XAddrs'] = $v['XAddrs'] ?? '';
                
                // Try to get device info
                if (!empty($v['XAddrs'])) {
                    $deviceService = is_array($v['XAddrs']) ? $v['XAddrs'][0] : $v['XAddrs'];
                    $pi = get_device_public_info($deviceService);
                    if ($pi['ok']) $v['info'] = $pi['info'];
                }
                $devices[] = $v;
            }
        }
        
        echo json_encode(['ok' => true, 'devices' => $devices]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle batch camera save from step 2
if ($action === 'save_cameras' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $camerasData = json_decode($_POST['cameras'] ?? '[]', true);
    if (!is_array($camerasData)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid cameras data']);
        exit;
    }
    
    // Load existing cameras
    $camerasCfg = load_json_cfg('cameras.json', ['cameras' => []]);
    $cameras = $camerasCfg['cameras'] ?? [];
    
    foreach ($camerasData as $camData) {
        if (empty($camData['ip']) || empty($camData['name'])) continue;
        
        $ip = $camData['ip'];
        $id = substr(md5($ip . time() . random_int(0, 9999)), 0, 12);
        $deviceServiceUrl = $camData['device_service_url'] ?: "http://{$ip}:2020/onvif/device_service";
        
        $newCam = [
            'id' => $id,
            'name' => $camData['name'],
            'ip' => $ip,
            'username' => $camData['username'] ?? '',
            'password' => $camData['password'] ?? '',
            'device_service_url' => $deviceServiceUrl,
            'manufacturer' => $camData['manufacturer'] ?? '',
            'model' => $camData['model'] ?? '',
            'streams' => [
                'high' => "rtsp://{$ip}:554/stream1",
                'low' => "rtsp://{$ip}:554/stream2",
            ],
            'allowptz' => true,
            'allow_audio' => false,
            'ispublic' => !empty($camData['ispublic']),
            'hasPTZ' => null,
            'ptzDirections' => []
        ];
        
        $cameras[] = $newCam;
    }
    
    $camerasCfg['cameras'] = $cameras;
    save_json_cfg('cameras.json', $camerasCfg);
    
    echo json_encode(['ok' => true, 'count' => count($camerasData)]);
    exit;
}

// Handle step 1 submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'step1') {
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $lan_cidr = trim($_POST['lan_cidr'] ?? '192.168.1.0/24');
    
    if ($admin_user === '' || $admin_pass === '') {
        $errors[] = "Admin username and password required.";
    } else {
        // Store in session for step 2
        $_SESSION['setup_data'] = [
            'admin_user' => $admin_user,
            'admin_pass' => $admin_pass,
            'lan_cidr' => $lan_cidr
        ];
        
        // Redirect to step 2
        header('Location: ' . $baseUrl . 'setup?step=2');
        exit;
    }
}

// Handle step 2 submission (finalize setup)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'finalize') {
    $setupData = $_SESSION['setup_data'] ?? [];
    
    if (empty($setupData['admin_user']) || empty($setupData['admin_pass'])) {
        header('Location: ' . $baseUrl . 'setup?step=1');
        exit;
    }
    
    // ensure cfg directory exists
    if (!is_dir($cfgdir)) mkdir($cfgdir, 0750, true);
    
    // Save users.json
    $users = ['users' => [
        [
            'username' => $setupData['admin_user'],
            'password_hash' => password_hash($setupData['admin_pass'], PASSWORD_DEFAULT),
            'isadmin' => true,
            'cameras' => []
        ]
    ]];
    file_put_contents($cfgdir . '/users.json', json_encode($users, JSON_PRETTY_PRINT));
    
    // Save config.json
    $config = [
        'base_url' => $baseUrl,
        'lan_cidr' => $setupData['lan_cidr'] ?? '192.168.1.0/24',
        'isPublic' => true,
        'camera_discovery_cidrs' => [$setupData['lan_cidr'] ?? '192.168.1.0/24'],
        'cache_ttl_seconds' => 86400
    ];
    file_put_contents($cfgdir . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
    
    // Create cameras.json if not exists
    if (!file_exists($cfgdir . '/cameras.json')) {
        file_put_contents($cfgdir . '/cameras.json', json_encode(['cameras' => []], JSON_PRETTY_PRINT));
    }
    
    // Create directories
    if (!is_dir($cfgdir . '/cameras-info')) mkdir($cfgdir . '/cameras-info', 0750, true);
    if (!is_dir($cfgdir . '/event_logs')) mkdir($cfgdir . '/event_logs', 0750, true);
    
    // Create configured flag
    touch($configuredFile);
    
    // Clear session setup data
    unset($_SESSION['setup_data']);
    
    $ok = true;
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Setup</title>
<link href="<?= htmlspecialchars($baseUrl) ?>view/external/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.camera-item { background: #1a1a1a; border-radius: 8px; padding: 12px; margin-bottom: 10px; }
.camera-item input { background: #2a2a2a; border-color: #444; color: #eee; }
.camera-item input:focus { background: #333; color: #fff; border-color: #0d6efd; }
</style>
</head>
<body class="bg-dark text-light">
<div class="container py-5">
  
  <?php if ($ok): ?>
    <h2>Setup Complete</h2>
    <div class="alert alert-success">Setup complete. You can login now.</div>
    <a class="btn btn-primary" href="<?= htmlspecialchars($baseUrl) ?>login">Go to login</a>
  
  <?php elseif ($step === '2'): ?>
    <!-- Step 2: Network Scan -->
    <h2>Setup - Step 2: Discover Cameras</h2>
    <p class="text-muted">Scan your network for ONVIF cameras. You can add them now or skip this step.</p>
    
    <div class="mb-3">
      <button id="scanBtn" class="btn btn-primary">
        <span id="scanBtnText">Scan Network</span>
        <span id="scanSpinner" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
      </button>
      <span id="scanStatus" class="ms-3 text-muted"></span>
    </div>
    
    <div id="camerasContainer" class="mb-4">
      <!-- Discovered cameras will be added here -->
    </div>
    
    <div class="d-flex gap-2">
      <button id="skipBtn" class="btn btn-outline-secondary" onclick="finishSetup()">Skip & Finish</button>
      <button id="saveAndFinishBtn" class="btn btn-success" onclick="saveAndFinish()" style="display:none;">Save Cameras & Finish</button>
    </div>
    
    <form id="finalizeForm" method="post" action="<?= htmlspecialchars($baseUrl) ?>setup" style="display:none;">
      <input type="hidden" name="action" value="finalize">
    </form>
    
    <script>
    const BASE_URL = <?= json_encode($baseUrl) ?>;
    let discoveredCameras = [];
    
    document.getElementById('scanBtn').addEventListener('click', async function() {
      const btn = this;
      const spinner = document.getElementById('scanSpinner');
      const status = document.getElementById('scanStatus');
      
      btn.disabled = true;
      spinner.style.display = 'inline-block';
      status.textContent = 'Scanning...';
      
      try {
        const formData = new FormData();
        formData.append('action', 'scan');
        
        const res = await fetch(BASE_URL + 'setup', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        
        if (data.ok && data.devices && data.devices.length > 0) {
          discoveredCameras = data.devices;
          status.textContent = 'Found ' + data.devices.length + ' device(s)';
          renderCameras(data.devices);
          document.getElementById('saveAndFinishBtn').style.display = 'inline-block';
        } else {
          status.textContent = data.error || 'No devices found';
        }
      } catch (err) {
        status.textContent = 'Error: ' + err.message;
      } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
      }
    });
    
    function renderCameras(devices) {
      const container = document.getElementById('camerasContainer');
      container.innerHTML = '';
      
      devices.forEach((d, i) => {
        const ip = d.IPAddr || '';
        const info = d.info || {};
        const xaddrs = Array.isArray(d.XAddrs) ? d.XAddrs[0] : (d.XAddrs || '');
        const suggestedName = (info.Manufacturer || '') + ' ' + (info.Model || '') || 'Camera ' + (i + 1);
        
        const html = `
          <div class="camera-item" data-index="${i}">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <strong>${ip}</strong>
              <div class="form-check">
                <input type="checkbox" class="form-check-input camera-enable" id="enable${i}" checked>
                <label class="form-check-label small" for="enable${i}">Add this camera</label>
              </div>
            </div>
            <div class="row g-2">
              <div class="col-md-4">
                <input type="text" class="form-control form-control-sm camera-name" placeholder="Name" value="${escapeHtml(suggestedName.trim())}">
              </div>
              <div class="col-md-4">
                <input type="text" class="form-control form-control-sm camera-user" placeholder="Username">
              </div>
              <div class="col-md-4">
                <input type="password" class="form-control form-control-sm camera-pass" placeholder="Password">
              </div>
            </div>
            <input type="hidden" class="camera-ip" value="${escapeHtml(ip)}">
            <input type="hidden" class="camera-xaddrs" value="${escapeHtml(xaddrs)}">
            <input type="hidden" class="camera-manufacturer" value="${escapeHtml(info.Manufacturer || '')}">
            <input type="hidden" class="camera-model" value="${escapeHtml(info.Model || '')}">
          </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
      });
    }
    
    function escapeHtml(s) {
      return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }
    
    async function saveAndFinish() {
      const items = document.querySelectorAll('.camera-item');
      const cameras = [];
      
      items.forEach(item => {
        if (!item.querySelector('.camera-enable').checked) return;
        
        cameras.push({
          ip: item.querySelector('.camera-ip').value,
          name: item.querySelector('.camera-name').value,
          username: item.querySelector('.camera-user').value,
          password: item.querySelector('.camera-pass').value,
          device_service_url: item.querySelector('.camera-xaddrs').value,
          manufacturer: item.querySelector('.camera-manufacturer').value,
          model: item.querySelector('.camera-model').value,
          ispublic: false
        });
      });
      
      if (cameras.length > 0) {
        const formData = new FormData();
        formData.append('action', 'save_cameras');
        formData.append('cameras', JSON.stringify(cameras));
        
        await fetch(BASE_URL + 'setup', {
          method: 'POST',
          body: formData
        });
      }
      
      finishSetup();
    }
    
    function finishSetup() {
      document.getElementById('finalizeForm').submit();
    }
    </script>
  
  <?php else: ?>
    <!-- Step 1: Admin Setup -->
    <h2>Initial Setup - Step 1</h2>
    <p class="text-muted">Create your admin account and configure network settings.</p>
    
    <?php if ($errors): foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?=htmlspecialchars($e, ENT_QUOTES)?></div>
    <?php endforeach; endif; ?>
    
    <form method="post" action="<?= htmlspecialchars($baseUrl) ?>setup">
      <input type="hidden" name="action" value="step1">
      <div class="mb-3">
        <label class="form-label">Administrator username</label>
        <input name="admin_user" class="form-control" required autocomplete="username">
      </div>
      <div class="mb-3">
        <label class="form-label">Administrator password</label>
        <input name="admin_pass" class="form-control" type="password" required autocomplete="new-password">
      </div>
      <div class="mb-3">
        <label class="form-label">Local LAN CIDR to scan</label>
        <input name="lan_cidr" class="form-control" value="192.168.1.0/24">
        <div class="form-text text-muted">Network range for camera discovery</div>
      </div>
      <button class="btn btn-primary">Next →</button>
    </form>
  <?php endif; ?>
  
</div>
</body></html>