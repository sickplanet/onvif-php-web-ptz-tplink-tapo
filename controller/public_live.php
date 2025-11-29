<?php
/**
 * Public live view endpoint (no auth). Supports URL forms:
 *  - /public/live/{hash} (dynamic path)
 * Also supports query ?hash=...
 * Uses cfg/public-cameras/{hash}.json
 *
 * The public JSON file must not contain credentials. It should contain:
 * {
 *   "id":"<hash>",
 *   "title":"Public Camera",
 *   "streams":[{"name":"high","url":"rtsp://.../stream1"}],
 *   "allowptz": false,
 *   "allow_audio": false,
 *   "expires": 0 // optional unix timestamp
 * }
 */
require_once __DIR__ . '/ErrorHandler.php';

// Get BASE_URL from index.php (should be defined)
$baseUrl = defined('BASE_URL') ? BASE_URL : '/';

$hash = null;

// try query param first
if (!empty($_GET['hash'])) $hash = preg_replace('/[^a-f0-9]/', '', $_GET['hash']);

// fallback parse URI: /public/live/{hash}
if (!$hash) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/public/live/([a-f0-9]{32})#i', $uri, $m)) {
        $hash = $m[1];
    }
}

if (!$hash) {
    ErrorHandler::handleError('Public camera not found.', 404, $baseUrl);
}

$cfgFile = __DIR__ . '/../cfg/public-cameras/' . $hash . '.json';
if (!file_exists($cfgFile)) {
    ErrorHandler::handleError('Public camera not found.', 404, $baseUrl);
}

$public = json_decode(file_get_contents($cfgFile), true);
if (!$public) {
    ErrorHandler::handleError('Invalid public camera configuration.', 500, $baseUrl);
}

// check expiry if present
if (!empty($public['expires']) && time() > intval($public['expires'])) {
    ErrorHandler::handleError('This public camera link has expired.', 410, $baseUrl);
}

// pick first stream url
$streamUrl = null;
if (!empty($public['streams']) && is_array($public['streams'])) {
    $streamUrl = $public['streams'][0]['url'] ?? null;
}

$title = $public['title'] ?? 'Public Camera';
$allowPtz = !empty($public['allowptz']);
$allowAudio = !empty($public['allow_audio']);

// check if html5_rtsp_player assets exist in model folder (use dynamic paths)
$playerJs = null;
$playerCss = null;
$playerInitHint = null;
$playerAssetPath = $baseUrl . 'model/html5_rtsp_player/dist';
if (file_exists(__DIR__ . '/../model/html5_rtsp_player/dist/player.js')) {
    $playerJs = $playerAssetPath . '/player.js';
    // some builds include css
    if (file_exists(__DIR__ . '/../model/html5_rtsp_player/dist/player.css')) {
        $playerCss = $playerAssetPath . '/player.css';
    }
    $playerInitHint = true;
} elseif (file_exists(__DIR__ . '/../model/html5_rtsp_player/public/player.js')) {
    $playerJs = $baseUrl . 'model/html5_rtsp_player/public/player.js';
    $playerCss = file_exists(__DIR__ . '/../model/html5_rtsp_player/public/player.css') ? $baseUrl . 'model/html5_rtsp_player/public/player.css' : null;
    $playerInitHint = true;
}

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?=htmlspecialchars($title)?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <link href="<?= htmlspecialchars($baseUrl) ?>view/external/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php if ($playerCss): ?><link href="<?=htmlspecialchars($playerCss)?>" rel="stylesheet"><?php endif; ?>
  <style>
    :root {
      --bg: #0b0b0b;
      --card: #111;
      --text: #eee;
      --muted: #9aa0a6;
      --accent: #0d6efd;
    }
    body { 
      background: var(--bg); 
      color: var(--text);
      min-height: 100vh;
      margin: 0;
      padding: 0;
    }
    .public-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 1rem;
    }
    .public-header {
      text-align: center;
      padding: 1rem 0;
      border-bottom: 1px solid #222;
      margin-bottom: 1rem;
    }
    .public-header h1 {
      font-size: 1.5rem;
      margin: 0;
    }
    .player-wrapper {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      aspect-ratio: 16/9;
      max-height: 70vh;
      background: #000;
      border: 1px solid #222;
      border-radius: 8px;
      overflow: hidden;
    }
    .player-wrapper #player,
    .player-wrapper #playerContainer {
      width: 100%;
      height: 100%;
    }
    .ptz-controls {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 0.5rem;
      padding: 1rem 0;
    }
    .ptz-grid {
      display: grid;
      grid-template-columns: repeat(3, 50px);
      grid-template-rows: repeat(3, 40px);
      gap: 4px;
    }
    .ptz-grid .btn {
      padding: 0.25rem;
    }
    .zoom-controls {
      display: flex;
      flex-direction: column;
      gap: 4px;
      margin-left: 1rem;
    }
    .public-footer {
      text-align: center;
      padding: 1rem;
      color: var(--muted);
      font-size: 0.875rem;
      border-top: 1px solid #222;
      margin-top: 1rem;
    }
    .stream-info {
      background: var(--card);
      border-radius: 8px;
      padding: 1rem;
      margin-top: 1rem;
    }
    .stream-info code {
      word-break: break-all;
    }
    @media (max-width: 576px) {
      .public-header h1 { font-size: 1.25rem; }
      .ptz-grid { grid-template-columns: repeat(3, 45px); grid-template-rows: repeat(3, 36px); }
      .zoom-controls { margin-left: 0.5rem; }
      .zoom-controls .btn { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
    }
  </style>
</head>
<body>
<div class="public-container">
  <header class="public-header">
    <h1><?=htmlspecialchars($title)?></h1>
    <?php if ($allowPtz): ?>
      <span class="badge bg-success">PTZ Enabled</span>
    <?php endif; ?>
    <?php if ($allowAudio): ?>
      <span class="badge bg-info">Audio Enabled</span>
    <?php endif; ?>
  </header>

  <div class="player-wrapper">
    <?php if ($playerJs && $streamUrl): ?>
      <div id="player"></div>
    <?php else: ?>
      <div class="text-light text-center p-3">
        <p class="mb-2"><i class="text-muted">No in-browser player available</i></p>
        <?php if ($streamUrl): ?>
          <div class="stream-info">
            <p class="mb-1">Open this URL in VLC or your NVR:</p>
            <code><?=htmlspecialchars($streamUrl)?></code>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($allowPtz): ?>
  <div class="ptz-controls">
    <div class="ptz-grid">
      <div></div>
      <button class="btn btn-secondary btn-sm" onclick="ptz('up')">▲</button>
      <div></div>
      <button class="btn btn-secondary btn-sm" onclick="ptz('left')">◀</button>
      <button class="btn btn-danger btn-sm" onclick="ptz('stop')">■</button>
      <button class="btn btn-secondary btn-sm" onclick="ptz('right')">▶</button>
      <div></div>
      <button class="btn btn-secondary btn-sm" onclick="ptz('down')">▼</button>
      <div></div>
    </div>
    <div class="zoom-controls">
      <button class="btn btn-secondary btn-sm" onclick="ptz('zoom_in')">Zoom +</button>
      <button class="btn btn-secondary btn-sm" onclick="ptz('zoom_out')">Zoom −</button>
    </div>
  </div>
  <?php endif; ?>

  <footer class="public-footer">
    Public view • No login required
  </footer>
</div>

<script src="<?= htmlspecialchars($baseUrl) ?>view/external/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($playerJs && $streamUrl): ?>
<script src="<?=htmlspecialchars($playerJs)?>"></script>
<script>
  // Initialize html5_rtsp_player (example init; adapt to the specific player API in the submodule)
  (function(){
    const rtsp = <?=json_encode($streamUrl)?>;
    // Many html5_rtsp_player implementations require a WebSocket RTSP proxy (server side).
    // If you have a proxy that serves ws://<host>/ws?url=rtsp://..., initialize accordingly.
    // Here we attempt a direct player init. Adjust params to your player API.
    if (typeof Html5RtspPlayer !== 'undefined') {
      const player = new Html5RtspPlayer('#player', {
        source: rtsp,
        autoplay: true,
        audio: <?= $allowAudio ? 'true' : 'false' ?>,
        // Additional options depend on the specific player build
      });
      window.__publicPlayer = player;
    } else if (typeof createPlayer === 'function') {
      // alternative API
      createPlayer({
        el: document.getElementById('player'),
        src: rtsp,
        autoplay: true,
        audio: <?= $allowAudio ? 'true' : 'false' ?>
      });
    } else {
      console.warn('html5_rtsp_player present but initialization API not detected. See README for model/html5_rtsp_player.');
      document.getElementById('player').innerHTML = '<div style="color:#999">Player JS loaded but automatic init failed. See console.</div>';
    }
  })();
</script>
<?php endif; ?>

<script>
const BASE_URL = <?= json_encode($baseUrl) ?>;

async function ptz(action) {
  // Public PTZ: we DO NOT include credentials in public file.
  // If PTZ is allowed, the public configuration must have a server-side token mapping to a camera.
  // We'll POST to the public PTZ proxy and pass the public hash and action.
  const matches = location.pathname.match(/\/public\/live\/([a-f0-9]{32})/i);
  if (!matches) { alert('Invalid public token'); return; }
  const hash = matches[1];

  const form = new URLSearchParams();
  form.set('hash', hash);
  form.set('action', action);

  const resp = await fetch(BASE_URL + 'controller/public_ptz.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: form.toString()
  });
  const j = await resp.json();
  if (!j.ok) alert('PTZ failed: ' + (j.error||'unknown'));
  else console.log('PTZ', j);
}
</script>
</body>
</html>