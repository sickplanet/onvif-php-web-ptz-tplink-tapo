<?php
// header.php - modular header, includes CSS and dark/light toggle script
// Uses BASE_URL constant (defined in index.php)
$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Tp-Link (Tapo) PHP Live Web UI</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS (local submodule) -->
  <link href="<?= htmlspecialchars($baseUrl) ?>view/external/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= htmlspecialchars($baseUrl) ?>view/css/loader.css" rel="stylesheet">
  <style>
    :root {
      --bg: #0b0b0b;
      --card: #111;
      --text: #eee;
      --muted: #9aa0a6;
      --accent: #0d6efd;
    }
    body.lightmode {
      --bg: #f8f9fa;
      --card: #fff;
      --text: #111;
      --muted: #6c757d;
    }
    body { background: var(--bg); color: var(--text); }
    .card { background: var(--card); color: var(--text); border: 1px solid rgba(255,255,255,0.03); }
    .text-muted-custom { color: var(--muted); }
    .btn-accent { background: var(--accent); border-color: var(--accent); color:#fff; }
    .btn-accent:hover { background: #0b5ed7; border-color: #0a58ca; color:#fff; }
    .video-wrapper { background:#000; border:1px solid #222; min-height:200px; display:flex; align-items:center; justify-content:center; color:#888; }
    @media (min-width: 768px) {
      .video-wrapper { min-height:300px; }
    }
    .theme-toggle { cursor:pointer; }
    .navbar-nav-scroll { max-height: none; }
  </style>
  <script>
    // Dark / light mode toggle using localStorage, default dark
    function applyTheme() {
      const mode = localStorage.getItem('theme') || 'dark';
      if (mode === 'light') document.body.classList.add('lightmode'); else document.body.classList.remove('lightmode');
      // update toggle text if present
      const t = document.getElementById('themeToggleText');
      if (t) t.textContent = (mode === 'light') ? 'Light' : 'Dark';
    }
    function toggleTheme() {
      const mode = localStorage.getItem('theme') || 'dark';
      localStorage.setItem('theme', (mode === 'light') ? 'dark' : 'light');
      applyTheme();
    }
    document.addEventListener('DOMContentLoaded', applyTheme);
  </script>
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark" style="background:var(--card)">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= htmlspecialchars($baseUrl) ?>">Tp-Link (Tapo) Web UI</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <div class="navbar-nav ms-auto align-items-md-center gap-2 py-2 py-md-0">
        <div class="d-flex align-items-center gap-2">
          <span class="text-muted-custom small" id="themeToggleText">Dark</span>
          <button class="btn btn-sm btn-outline-light theme-toggle" onclick="toggleTheme();">Toggle Theme</button>
        </div>
        <?php if (!empty($_SESSION['user']['isadmin'])): ?>
        <a class="btn btn-sm btn-outline-warning" href="<?= htmlspecialchars($baseUrl) ?>admin">Admin</a>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-light" href="<?= htmlspecialchars($baseUrl) ?>login?logout=1">Logout</a>
      </div>
    </div>
  </div>
</nav>
<main class="container-fluid py-3">