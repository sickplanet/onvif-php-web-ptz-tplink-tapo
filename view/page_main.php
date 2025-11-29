<?php
// Updated page_main.php with proper scan modal (no alert fallback), loader, header/footer, and dark/light mode
$devices = load_json_cfg('cameras.json', ['cameras'=>[]])['cameras'] ?? [];
require_once __DIR__ . '/header.php';
?>
<div class="row">
  <div class="col-md-3">
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

      <div class="d-flex gap-2">
        <button id="refreshBtn" class="btn btn-sm btn-accent">Refresh Info</button>
        <button id="scanBtn" class="btn btn-sm btn-outline-light">Scan</button>
      </div>

      <div id="scanStatusWrap" class="mt-3 small text-muted-custom" style="min-height:24px;">
        <span id="scanStatusText">Idle</span>
        <span id="scanLoader" style="display:none;" class="loader-center"><div class="loader"></div></span>
      </div>
    </div>
  </div>

  <div class="col-md-9">
    <div id="mainPanel" class="card p-3">
      <div id="videoArea" class="video-wrapper">
        <div id="videoPlaceholder">Select a camera</div>
        <img id="snapshotImg" style="display:none;max-width:100%" />
      </div>

      <div class="mt-2 small text-muted-custom">
        Stream: <code id="streamUri">-</code> | Snapshot: <code id="snapshotUri">-</code>
      </div>

      <div class="mt-3">
        <div class="d-inline-grid" style="grid-template-columns:repeat(3,60px);grid-template-rows:repeat(3,40px);gap:6px;">
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

      <pre id="debugArea" class="mt-3 text-muted-custom" style="max-height:200px;overflow:auto;"></pre>
    </div>
  </div>
</div>

<!-- Scan results modal (ensure this exists so JS doesn't fallback to alert) -->
<div class="modal fade" id="scanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Scan results</h5>
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

<?php require_once __DIR__ . '/footer.php'; ?>

<script src="view/js/page_main.js"></script>