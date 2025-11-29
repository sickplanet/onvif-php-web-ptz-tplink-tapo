<?php
// Updated page_main.php with proper scan modal (no alert fallback), loader, header/footer, and dark/light mode
// Uses BASE_URL constant (defined in index.php)
$devices = load_json_cfg('cameras.json', ['cameras'=>[]])['cameras'] ?? [];
$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
require_once __DIR__ . '/header.php';
?>
<div class="row g-3">
  <!-- Sidebar - responsive: col-12 on mobile, col-lg-3 on large screens -->
  <div class="col-12 col-lg-3">
    <div class="card mb-3 p-3">
      <h6>Devices</h6>
      <select id="deviceSelect" class="form-select form-select-sm mb-2">
        <option value="">-- select --</option>
        <?php foreach ($devices as $d): ?>
          <option value="<?=htmlspecialchars($d['id'])?>"><?=htmlspecialchars($d['name'].' ('.$d['ip'].')')?></option>
        <?php endforeach; ?>
      </select>

      <div class="mb-2">
        <div class="form-check">
          <input id="continuousMoveCheckbox" class="form-check-input" type="checkbox">
          <label class="form-check-label text-muted-custom" for="continuousMoveCheckbox">Continuous move</label>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button id="refreshBtn" class="btn btn-sm btn-accent">Refresh Info</button>
        <button id="scanBtn" class="btn btn-sm btn-outline-light">Scan</button>
      </div>

      <div id="scanStatusWrap" class="mt-3 small text-muted-custom" style="min-height:24px;">
        <span id="scanStatusText">Idle</span>
        <span id="scanLoader" style="display:none;" class="loader-center"><div class="loader"></div></span>
      </div>
    </div>
  </div>

  <!-- Main content - responsive: col-12 on mobile, col-lg-9 on large screens -->
  <div class="col-12 col-lg-9">
    <div id="mainPanel" class="card p-3">
      <div id="videoArea" class="video-wrapper position-relative">
        <div id="videoPlaceholder">Select a camera</div>
        <img id="snapshotImg" class="img-fluid" style="display:none" alt="Camera snapshot" />
        <!-- Live view controls -->
        <div id="liveViewControls" class="position-absolute top-0 end-0 p-2" style="display:none;">
          <div class="btn-group btn-group-sm">
            <button id="playPauseBtn" class="btn btn-outline-light" onclick="toggleLiveView()" title="Play/Pause live view">
              <span id="playIcon">▶</span>
            </button>
            <button class="btn btn-outline-light" onclick="refreshSnapshot()" title="Refresh snapshot">⟳</button>
          </div>
          <span id="liveIndicator" class="badge bg-danger ms-2" style="display:none;">● LIVE</span>
        </div>
        <!-- Loading spinner -->
        <div id="videoLoader" class="position-absolute top-50 start-50 translate-middle" style="display:none;">
          <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>

      <!-- Stream URIs - responsive text -->
      <div class="mt-2 small text-muted-custom text-break">
        <span class="d-block d-sm-inline">Stream: <code id="streamUri" class="text-break">-</code></span>
        <span class="d-none d-sm-inline"> | </span>
        <span class="d-block d-sm-inline">Snapshot: <code id="snapshotUri" class="text-break">-</code></span>
      </div>

      <!-- PTZ Controls - responsive layout -->
      <div id="ptzControls" class="mt-3">
        <div class="d-flex flex-wrap align-items-start gap-3">
          <!-- D-pad -->
          <div id="ptzDpad" class="d-inline-grid" style="grid-template-columns:repeat(3,50px);grid-template-rows:repeat(3,40px);gap:4px;">
            <div></div><button id="ptzUp" class="btn btn-sm btn-secondary ptz-btn" data-action="up">▲</button><div></div>
            <button id="ptzLeft" class="btn btn-sm btn-secondary ptz-btn" data-action="left">◀</button>
            <button id="ptzStop" class="btn btn-sm btn-danger ptz-btn" data-action="stop">■</button>
            <button id="ptzRight" class="btn btn-sm btn-secondary ptz-btn" data-action="right">▶</button>
            <div></div><button id="ptzDown" class="btn btn-sm btn-secondary ptz-btn" data-action="down">▼</button><div></div>
          </div>
          <!-- Zoom buttons -->
          <div id="ptzZoom" class="d-flex flex-column gap-2">
            <button id="ptzZoomIn" class="btn btn-sm btn-secondary ptz-btn" data-action="zoom_in">Zoom +</button>
            <button id="ptzZoomOut" class="btn btn-sm btn-secondary ptz-btn" data-action="zoom_out">Zoom −</button>
          </div>
        </div>
      </div>

      <!-- Toast container for notifications -->
      <div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;"></div>

      <!-- Debug area - scrollable -->
      <pre id="debugArea" class="mt-3 text-muted-custom small" style="max-height:200px;overflow:auto;word-break:break-all;white-space:pre-wrap;"></pre>
    </div>
  </div>
</div>

<!-- Scan results modal (ensure this exists so JS doesn't fallback to alert) -->
<div class="modal fade" id="scanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Scan Results</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="scanStatus" class="mb-2 small text-muted-custom">Press Scan to start discovery on the server.</div>
        <div id="scanResults">
          <!-- populated by JS -->
        </div>
      </div>
      <div class="modal-footer">
        <button id="modalCloseBtn" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Camera Modal (used when adding discovered camera) -->
<div class="modal fade" id="addCameraModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Add Camera</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="addCamIp">
        <input type="hidden" id="addCamManufacturer">
        <input type="hidden" id="addCamModel">
        <input type="hidden" id="addCamXaddrs">
        
        <div class="mb-3">
          <label class="form-label">IP Address</label>
          <input type="text" id="addCamIpDisplay" class="form-control" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Camera Name <span class="text-danger">*</span></label>
          <input type="text" id="addCamName" class="form-control" required aria-required="true" placeholder="e.g. Front Door Camera">
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" id="addCamUsername" class="form-control" placeholder="Camera ONVIF username" autocomplete="username">
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" id="addCamPassword" class="form-control" placeholder="Camera ONVIF password" autocomplete="current-password">
        </div>
        <div class="mb-3">
          <label class="form-label">Device Service URL</label>
          <input type="text" id="addCamDeviceUrl" class="form-control" placeholder="Auto-detected">
          <div class="form-text text-muted">Leave blank to use default</div>
        </div>
        
        <div id="addCamTestResult" class="mt-2" style="display:none;"></div>
      </div>
      <div class="modal-footer flex-wrap gap-2">
        <button type="button" id="testConnectionBtn" class="btn btn-outline-info">Test Connection</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmAddCameraBtn" class="btn btn-primary">Add Camera</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

<!-- Define BASE_URL for JavaScript -->
<script>
const BASE_URL = <?= json_encode($baseUrl) ?>;
</script>
<script src="<?= htmlspecialchars($baseUrl) ?>view/js/page_main.js"></script>