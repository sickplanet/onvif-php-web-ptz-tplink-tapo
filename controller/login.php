<?php
// Improved login controller with logout handling and basic session security
// Uses BASE_URL constant (defined in index.php)
require_once __DIR__ . '/../onvif_client.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '/';

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    // logout action
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header('Location: ' . $baseUrl . 'login');
    exit;
}

$error = '';

// If already logged in redirect
if (isset($_SESSION['user'])) {
    header('Location: ' . $baseUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    $usersCfg = load_json_cfg('users.json', ['users' => []]);
    $users = $usersCfg['users'] ?? [];

    $found = null;
    foreach ($users as $u) {
        if (isset($u['username']) && $u['username'] === $user) {
            $found = $u;
            break;
        }
    }

    if ($found && isset($found['password_hash']) && password_verify($pass, $found['password_hash'])) {
        // Good login
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'username' => $found['username'],
            'isadmin'  => !empty($found['isadmin'])
        ];
        header('Location: ' . $baseUrl);
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

// Render login form
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Login - ONVIF UI</title>
<link href="<?= htmlspecialchars($baseUrl) ?>view/external/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card bg-secondary text-light">
        <div class="card-body">
          <h3 class="card-title">Login</h3>
          <?php if ($error): ?>
            <div class="alert alert-danger"><?=htmlspecialchars($error, ENT_QUOTES)?></div>
          <?php endif; ?>
          <form method="post" action="<?= htmlspecialchars($baseUrl) ?>login">
            <div class="mb-2">
              <label class="form-label">Username</label>
              <input name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-2">
              <label class="form-label">Password</label>
              <input name="password" type="password" class="form-control" required>
            </div>
            <div class="d-flex justify-content-between">
              <button class="btn btn-primary">Login</button>
              <a class="btn btn-outline-light" href="<?= htmlspecialchars($baseUrl) ?>setup">Run setup</a>
            </div>
          </form>
        </div>
      </div>
      <p class="mt-2 text-muted small">If you do not have an account run the setup to create the first administrator.</p>
    </div>
  </div>
</div>
</body>
</html>