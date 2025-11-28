<?php
// Simple login controller
require_once __DIR__ . '/../onvif_client.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    $users = load_json_cfg('users.json', ['users' => []])['users'] ?? [];
    $found = null;
    foreach ($users as $u) {
        if ($u['username'] === $user) { $found = $u; break; }
    }
    if ($found && password_verify($pass, $found['password_hash'])) {
        $_SESSION['user'] = ['username' => $found['username'], 'isadmin' => $found['isadmin'] ?? false];
        header('Location: /onvif-ui/');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

// If already logged in, redirect
if (isset($_SESSION['user'])) {
    header('Location: /onvif-ui/');
    exit;
}

// Render a simple login page
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Login - ONVIF UI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card bg-secondary text-light">
        <div class="card-body">
          <h3 class="card-title">Login</h3>
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?=htmlspecialchars($error, ENT_QUOTES)?></div>
          <?php endif; ?>
          <form method="post" action="/onvif-ui/login">
            <div class="mb-2">
              <label class="form-label">Username</label>
              <input name="username" class="form-control" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Password</label>
              <input name="password" type="password" class="form-control" required>
            </div>
            <div class="d-flex justify-content-between">
              <button class="btn btn-primary">Login</button>
              <a class="btn btn-outline-light" href="/onvif-ui/setup">Run setup</a>
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