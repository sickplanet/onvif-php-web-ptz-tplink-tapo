<?php
// header.php - modular header, includes CSS and dark/light toggle script
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Tp-Link (Tapo) PHP Live Web UI</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="view/css/loader.css" rel="stylesheet">
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
    .video-wrapper { background:#000; border:1px solid #222; min-height:300px; display:flex; align-items:center; justify-content:center; color:#888; }
    .theme-toggle { cursor:pointer; }
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
<nav class="navbar navbar-expand-lg" style="background:var(--card)">
  <div class="container-fluid">
    <a class="navbar-brand text-light" href="/onvif-ui/">Tp-Link (Tapo) Web UI</a>
    <div class="d-flex align-items-center gap-2">
      <div class="me-2 text-muted-custom small" id="themeToggleText">Dark</div>
      <button class="btn btn-sm btn-outline-light theme-toggle" onclick="toggleTheme();">Toggle Theme</button>
      <a class="btn btn-sm btn-outline-light" href="/onvif-ui/login?logout=1">Logout</a>
    </div>
  </div>
</nav>
<main class="container-fluid py-3">