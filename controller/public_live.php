<?php
// Public live view endpoint (no auth). Supports URL forms:
//  - /onvif-ui/public/live/{hash}
// Also supports query ?hash=...
// Uses cfg/public-cameras/{hash}.json
//
// The public JSON file must not contain credentials. It should contain:
// {
//   "id":"<hash>",
//   "title":"Public Camera",
//   "streams":[{"name":"high","url":"rtsp://.../stream1"}],
//   "allowptz": false,
//   "allow_audio": false,
//   "expires": 0 // optional unix timestamp
// }
//
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
    http_response_code(404);
    echo "Public camera not found.";
    exit;
}

$cfgFile = __DIR__ . '/../../cfg/public-cameras/' . $hash . '.json';
if (!file_exists($cfgFile)) {
    http_response_code(404);
    echo "Public camera not found.";
    exit;
}

$public = json_decode(file_get_contents($cfgFile), true);
if (!$public) {
    http_response_code(500);
    echo "Invalid public camera configuration.";
    exit;
}

// check expiry if present
if (!empty($public['expires']) && time() > intval($public['expires'])) {
    http_response_code(410);
    echo "This public camera link has expired.";
    exit;
}

// pick first stream url
$streamUrl = null;
if (!empty($public['streams']) && is_array($public['streams'])) {
    $streamUrl = $public['streams'][0]['url'] ?? null;
}

$title = $public['title'] ?? 'Public Camera';
$allowPtz = !empty($public['allowptz']);
$allowAudio = !empty($public['allow_audio']);

// check if html5_rtsp_player assets exist in model folder
$playerJs = null;
$playerCss = null;
$playerInitHint = null;
$playerAssetPath = '/onvif-ui/model/html5_rtsp_player/dist';
if (file_exists(__DIR__ . '/../../model/html5_rtsp_player/dist/player.js')) {
    $playerJs = $playerAssetPath . '/player.js';
    // some builds include css
    if (file_exists(__DIR__ . '/../../model/html5_rtsp_player/dist/player.css')) {
        $playerCss = $playerAssetPath . '/player.css';
    }
    $playerInitHint = true;
} elseif (file_exists(__DIR__ . '/../../model/html5_rtsp_player/public/player.js')) {
    $playerJs = '/onvif-ui/model/html5_rtsp_player/public/player.js';
    $playerCss = file_exists(__DIR__ . '/../../model/html5_rtsp_player/public/player.css') ? '/onvif-ui/model/html5_rtsp_player/public/player.css' : null;
    $playerInitHint = true;
}

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?=htmlspecialchars($title)?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php if ($playerCss): ?><link href="<?=htmlspecialchars($playerCss)?>" rel="stylesheet"><?php endif; ?>
  <style>body{background:#000;color:#ddd} .player-wrapper{display:flex;align-items:center;justify-content:center;height:80vh}</style>
</head>
<body>
<div class="container">
  <div class="py-3 text-center text-light">
    <h3><?=htmlspecialchars($title)?></h3>
    <?php if ($allowPtz): ?><div class="text-success small">PTZ enabled</div><?php endif; ?>
  </div>

  <div class="player-wrapper">
    <div id="playerContainer" style="width:100%;max-width:1024px;height:576px;background:#000;border:1px solid #222;display:flex;align-items:center;justify-content:center;">
      <?php if ($playerJs && $streamUrl): ?>
        <div id="player" style="width:100%;height:100%"></div>
      <?php else: ?>
        <div class="text-light text-center">
          <p>No in-browser player available.</p>
          <?php if ($streamUrl): ?>
            <p>Open this URL in VLC or your NVR: <br><code><?=htmlspecialchars($streamUrl)?></code></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="py-3 text-center">
    <?php if ($allowPtz): ?>
      <div id="ptzControls" class="btn-group" role="group">
        <button class="btn btn-light btn-sm" onclick="ptz('left')">◀</button>
        <button class="btn btn-danger btn-sm" onclick="ptz('stop')">■</button>
        <button class="btn btn-light btn-sm" onclick="ptz('right')">▶</button>
        <button class="btn btn-light btn-sm" onclick="ptz('up')">▲</button>
        <button class="btn btn-light btn-sm" onclick="ptz('down')">▼</button>
        <button class="btn btn-light btn-sm" onclick="ptz('zoom_in')">Zoom +</button>
        <button class="btn btn-light btn-sm" onclick="ptz('zoom_out')">Zoom −</button>
      </div>
    <?php endif; ?>
  </div>

  <div class="text-center text-muted small mb-4">This is a public view. No login required.</div>
</div>

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
async function ptz(action) {
  // Public PTZ: we DO NOT include credentials in public file.
  // If PTZ is allowed, the public configuration must have a server-side token mapping to a camera.
  // We'll POST to /onvif-ui/public/ptz-proxy and pass the public hash and action.
  const matches = location.pathname.match(/\/public\/live\/([a-f0-9]{32})/i);
  if (!matches) { alert('Invalid public token'); return; }
  const hash = matches[1];

  const form = new URLSearchParams();
  form.set('hash', hash);
  form.set('action', action);

  const resp = await fetch('/onvif-ui/controller/public_ptz.php', {
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