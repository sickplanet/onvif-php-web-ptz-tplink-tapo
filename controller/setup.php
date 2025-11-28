<?php
// Setup controller: create initial admin, basic configuration and cfg/configured flag
require_once __DIR__ . '/../onvif_client.php';

$cfgdir = __DIR__ . '/../cfg';
$configuredFile = $cfgdir . '/configured';

// if already configured, redirect with message
if (file_exists($configuredFile) && ($_SERVER['REQUEST_METHOD'] !== 'POST')) {
    header('Location: /onvif-ui/message?m=Already+configured');
    exit;
}

$errors = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $lan_cidr = trim($_POST['lan_cidr'] ?? '192.168.1.0/24');
    // basic validation
    if ($admin_user === '' || $admin_pass === '') {
        $errors[] = "Admin username and password required.";
    } else {
        // ensure cfg directory exists and is not web accessible (htaccess present)
        if (!is_dir($cfgdir)) mkdir($cfgdir, 0750, true);
        // Save users.json
        $users = ['users' => [
            [
                'username' => $admin_user,
                'password_hash' => password_hash($admin_pass, PASSWORD_DEFAULT),
                'isadmin' => true,
                'cameras' => []
            ]
        ]];
        file_put_contents($cfgdir . '/users.json', json_encode($users, JSON_PRETTY_PRINT));
        // Save config.json
        $config = [
            'lan_cidr' => $lan_cidr,
            'isPublic' => true,
            'camera_discovery_cidrs' => [$lan_cidr],
            'cache_ttl_seconds' => 86400
        ];
        file_put_contents($cfgdir . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
        // create empty cameras files
        file_put_contents($cfgdir . '/cameras.json', json_encode(['cameras' => []], JSON_PRETTY_PRINT));
        if (!is_dir($cfgdir . '/cameras-info')) mkdir($cfgdir . '/cameras-info', 0750, true);
        // Create configured flag
        touch($configuredFile);
        $ok = true;
    }
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Setup</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-dark text-light">
<div class="container py-5">
  <h2>Initial Setup</h2>
  <?php if ($ok): ?>
    <div class="alert alert-success">Setup complete. You can login now.</div>
    <a class="btn btn-primary" href="/onvif-ui/login">Go to login</a>
  <?php else: ?>
    <?php if ($errors): foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?=htmlspecialchars($e, ENT_QUOTES)?></div>
    <?php endforeach; endif; ?>
    <form method="post" action="/onvif-ui/setup">
      <div class="mb-2">
        <label class="form-label">Administrator username</label>
        <input name="admin_user" class="form-control" required>
      </div>
      <div class="mb-2">
        <label class="form-label">Administrator password</label>
        <input name="admin_pass" class="form-control" type="password" required>
      </div>
      <div class="mb-2">
        <label class="form-label">Local LAN CIDR to allow (recommended static IP range)</label>
        <input name="lan_cidr" class="form-control" value="192.168.1.0/24">
      </div>
      <button class="btn btn-primary">Create Admin & Save Config</button>
    </form>
  <?php endif; ?>
</div>
</body></html>