<?php
// Simple main page that loads the SPA JS + Bootstrap shell
$devices = load_json_cfg('cameras.json', ['cameras'=>[]])['cameras'] ?? [];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Tp-Link (Tapo) PHP Live Web UI with PTZ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#0b0b0b;color:#eee} .card{background:#111;border:1px solid #222}</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid">
    <span class="navbar-brand">Tp-Link (Tapo) PHP Live Web UI</span>
    <div>
      <a class="btn btn-outline-light btn-sm" href="/onvif-ui/login?logout=1">Logout</a>
    </div>
  </div>
</nav>

<div class="container-fluid py-3">
  <div class="row">
    <div class="col-md-3">
      <div class="card mb-3 p-2">
        <h6>Devices</h6>
        <select id="deviceSelect" class="form-select form-select-sm">
          <option value="">-- select --</option>
          <?php foreach ($devices as $d): ?>
            <option value="<?=htmlspecialchars($d['id'])?>"><?=htmlspecialchars($d['name'].' ('.$d['ip'].')')?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-check mt-2">
          <input id="continuousMoveCheckbox" class="form-check-input" type="checkbox">
          <label class="form-check-label text-muted" for="continuousMoveCheckbox">Continuous move</label>
        </div>
        <div class="mt-2">
          <button id="refreshBtn" class="btn btn-sm btn-outline-light">Refresh Info</button>
          <button id="scanBtn" class="btn btn-sm btn-outline-secondary">Scan</button>
        </div>
      </div>
    </div>

    <div class="col-md-9">
      <div id="mainPanel" class="card p-2">
        <div id="videoArea" class="video-wrapper" style="min-height:300px; display:flex;align-items:center;justify-content:center;background:#000;color:#666;">
          <div id="videoPlaceholder">Select camera</div>
          <img id="snapshotImg" style="display:none;max-width:100%" />
        </div>
        <div class="mt-2 small text-muted">
          Stream: <code id="streamUri">-</code> | Snapshot: <code id="snapshotUri">-</code>
        </div>

        <div class="mt-3">
          <div class="ptz-grid d-inline-grid" style="grid-template-columns:repeat(3,60px);grid-template-rows:repeat(3,40px);gap:6px;">
            <div></div><button class="btn btn-sm btn-secondary" onclick="ptz('up')">▲</button><div></div>
            <button class="btn btn-sm btn-secondary" onclick="ptz('left')">◀</button>
            <button class="btn btn-sm btn-danger" onclick="ptz('stop')">■</button>
            <button class="btn btn-sm btn-secondary" onclick="ptz('right')">▶</button>
            <div></div><button class="btn btn-sm btn-secondary" onclick="ptz('down')">▼</button><div></div>
          </div>
          <div class="d-inline-block ms-3">
            <button class="btn btn-sm btn-secondary" onclick="ptz('zoom_in')">Zoom +</button>
            <button class="btn btn-sm btn-secondary" onclick="ptz('zoom_out')">Zoom −</button>
          </div>
        </div>

        <pre id="debugArea" class="mt-3 text-muted" style="max-height:200px;overflow:auto;"></pre>
      </div>
    </div>
  </div>
</div>

<script>
let currentDeviceId = null;
let currentProfileToken = null;

document.getElementById('deviceSelect').addEventListener('change', async function(){
  const id = this.value;
  if (!id) return;
  currentDeviceId = id;
  await loadProfilesForDevice(id);
});

async function loadProfilesForDevice(deviceId) {
  const r = await fetch('/onvif-ui/onvif/profiles?deviceId=' + encodeURIComponent(deviceId));
  const j = await r.json();
  document.getElementById('debugArea').textContent = JSON.stringify(j, null, 2);
  if (!j.ok) { alert(j.error || 'Error'); return; }
  // pick first token from sources structure like earlier: sources[0][1]['profiletoken']
  const sources = j.sources || [];
  if (sources.length && sources[0][1] && sources[0][1].profiletoken) {
    currentProfileToken = sources[0][1].profiletoken;
    // load stream uri
    const s = await fetch('/onvif-ui/onvif/stream?deviceId=' + encodeURIComponent(deviceId) + '&profileToken=' + encodeURIComponent(currentProfileToken));
    const sj = await s.json();
    document.getElementById('debugArea').textContent = JSON.stringify(sj, null, 2);
    document.getElementById('streamUri').textContent = sj.streamUri || '-';
    document.getElementById('snapshotUri').textContent = sj.snapshotUri || '-';
    setupVideoDisplay(sj.snapshotUri, sj.streamUri, sj.rtspHints?.high);
  } else {
    alert('No profile token found for device.');
  }
}

function setupVideoDisplay(snapshotUri, streamUri, rtspHigh) {
  const img = document.getElementById('snapshotImg');
  const placeholder = document.getElementById('videoPlaceholder');
  img.style.display = 'none';
  placeholder.style.display = 'block';
  placeholder.textContent = 'No browser snapshot available. RTSP: ' + (rtspHigh || streamUri);
  if (snapshotUri && snapshotUri.startsWith('http')) {
    img.src = snapshotUri;
    img.style.display = 'block';
    placeholder.style.display = 'none';
  }
}

// PTZ
async function ptz(action) {
  if (!currentDeviceId || !currentProfileToken) { alert('Select device first'); return; }
  const continuous = document.getElementById('continuousMoveCheckbox').checked ? '1' : '0';
  const body = new URLSearchParams();
  body.set('deviceId', currentDeviceId);
  body.set('profileToken', currentProfileToken);
  body.set('action', action);
  body.set('continuous', continuous);
  const r = await fetch('/onvif-ui/onvif/ptz', { method:'POST', body });
  const j = await r.json();
  console.log('PTZ', j);
  // If continuous is not true and action != stop, auto-stop after 350ms
  if (!document.getElementById('continuousMoveCheckbox').checked && action !== 'stop' && j.ok) {
    setTimeout(()=>{ fetch('/onvif-ui/onvif/ptz', { method:'POST', body: new URLSearchParams({deviceId:currentDeviceId, profileToken:currentProfileToken, action:'stop'}) }); }, 350);
  }
}
</script>
</body>
</html>