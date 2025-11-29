// --- State ---
let currentDeviceId = null;
let currentProfileToken = null;
// BASE_URL is defined in page_main.php before this script loads
const scanModal = new bootstrap.Modal(document.getElementById('scanModal'), {});
const addCameraModal = new bootstrap.Modal(document.getElementById('addCameraModal'), {});

// --- Alert Modal (replaces standard alert()) ---
// Create error/alert modal if not exists
function ensureAlertModal() {
  if (!document.getElementById('alertModal')) {
    const modalHtml = `
      <div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content bg-dark text-light">
            <div class="modal-header">
              <h5 class="modal-title" id="alertModalTitle">Notice</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="alertModalBody"></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
          </div>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  }
}
ensureAlertModal();
const alertModalEl = document.getElementById('alertModal');
const alertModal = new bootstrap.Modal(alertModalEl, {});

function showErrorModal(message, title) {
  document.getElementById('alertModalTitle').textContent = title || 'Error';
  document.getElementById('alertModalBody').innerHTML = escapeHtml(message);
  alertModal.show();
}

function showInfoModal(message, title) {
  document.getElementById('alertModalTitle').textContent = title || 'Notice';
  document.getElementById('alertModalBody').innerHTML = escapeHtml(message);
  alertModal.show();
}

// --- Helpers ---
function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, function (m) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]; });
}
function escapeJs(s) { return String(s || '').replace(/'/g,"\\'"); }

// Helper to create loading spinner HTML
function createLoadingSpinner(message) {
  return `<div class="text-center py-4">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <div class="mt-2 text-muted-custom">${escapeHtml(message)}</div>
  </div>`;
}

function createStatusSpinner(message) {
  return `<span class="spinner-border spinner-border-sm me-2"></span>${escapeHtml(message)}`;
}

function getDeviceUrl() {
  const ip = document.getElementById('addCamIp').value;
  let deviceUrl = document.getElementById('addCamDeviceUrl').value || document.getElementById('addCamXaddrs').value;
  if (!deviceUrl) deviceUrl = 'http://' + ip + ':2020/onvif/device_service';
  return deviceUrl;
}

function showAddCamError(message) {
  const resultDiv = document.getElementById('addCamTestResult');
  resultDiv.style.display = 'block';
  resultDiv.className = 'mt-2 alert alert-danger';
  resultDiv.innerHTML = escapeHtml(message);
}

// --- Scan button with loader & modal population ---
document.getElementById('scanBtn').addEventListener('click', async () => {
  // Show modal immediately with loading spinner
  document.getElementById('scanStatus').innerHTML = createStatusSpinner('Scanning network for cameras...');
  document.getElementById('scanResults').innerHTML = createLoadingSpinner('Discovering ONVIF devices...');
  scanModal.show();
  
  document.getElementById('scanStatusText').textContent = 'Scanning...';
  document.getElementById('scanLoader').style.display = 'inline-block';

  try {
    const res = await fetch(BASE_URL + 'cameras/scan', { method: 'GET', credentials: 'same-origin' });
    document.getElementById('scanLoader').style.display = 'none';
    if (!res.ok) {
      const text = await res.text();
      document.getElementById('scanStatusText').textContent = 'Scan failed: HTTP ' + res.status;
      document.getElementById('scanStatus').textContent = 'Scan failed: HTTP ' + res.status;
      document.getElementById('scanResults').innerHTML = '<pre class="text-danger">' + escapeHtml(text) + '</pre>';
      return;
    }
    const data = await res.json();
    if (!data.ok) {
      document.getElementById('scanStatusText').textContent = 'Scan failed: ' + (data.error || 'unknown');
      document.getElementById('scanStatus').textContent = 'Scan failed: ' + (data.error || 'unknown');
      document.getElementById('scanResults').innerHTML = '<pre class="text-danger">' + escapeHtml(JSON.stringify(data, null, 2)) + '</pre>';
      return;
    }

    const devices = data.devices || [];
    if (devices.length === 0) {
      // maybe server returned an object keyed by IP; convert it to array
      if (typeof data.devices === 'object' && Object.keys(data.devices).length > 0) {
        const arr = [];
        for (const k in data.devices) {
          const d = data.devices[k];
          if (!d.IPAddr) d.IPAddr = k;
          arr.push(d);
        }
        populateScanModal(arr);
      } else {
        document.getElementById('scanStatusText').textContent = 'No devices discovered (multicast may be blocked).';
        document.getElementById('scanStatus').textContent = 'No devices discovered';
        document.getElementById('scanResults').innerHTML = '<div class="alert alert-warning">No ONVIF devices found. Multicast discovery may be blocked on your network.</div>';
      }
      return;
    }

    document.getElementById('scanStatusText').textContent = 'Discovered ' + devices.length + ' device(s).';
    populateScanModal(devices);
  } catch (err) {
    document.getElementById('scanLoader').style.display = 'none';
    document.getElementById('scanStatusText').textContent = 'Scan error: ' + err.message;
    document.getElementById('scanStatus').textContent = 'Scan error: ' + err.message;
    document.getElementById('scanResults').innerHTML = '<pre class="text-danger">' + escapeHtml(err.stack || err.message) + '</pre>';
  }
});

function populateScanModal(devices) {
  const container = document.getElementById('scanResults');
  container.innerHTML = '';
  document.getElementById('scanStatus').textContent = 'Discovered ' + devices.length + ' device(s). Click "Add" to configure a camera.';
  devices.forEach((d, idx) => {
    const ip = d.IPAddr || (d.XAddrs ? (Array.isArray(d.XAddrs)?d.XAddrs[0]:d.XAddrs) : '');
    const info = d.info || {};
    const manuf = info.Manufacturer || '';
    const model = info.Model || '';
    const xaddrs = Array.isArray(d.XAddrs) ? d.XAddrs.join(', ') : (d.XAddrs || '');
    const html = `
      <div class="card bg-dark mb-2">
        <div class="card-body p-2">
          <div class="d-flex flex-column flex-md-row justify-content-between">
            <div class="flex-grow-1">
              <div><strong>${escapeHtml(ip)} ${manuf||model ? ' — ' + escapeHtml(manuf + ' ' + model) : ''}</strong></div>
              <div class="small text-muted-custom text-break">XAddrs: ${escapeHtml(xaddrs)}</div>
              <details class="mt-1">
                <summary class="small text-muted-custom" style="cursor:pointer">Raw data</summary>
                <pre class="small text-muted-custom" style="white-space:pre-wrap;max-height:150px;overflow:auto">${escapeHtml(JSON.stringify(d, null, 2))}</pre>
              </details>
            </div>
            <div class="ps-md-2 text-end mt-2 mt-md-0">
              <button class="btn btn-sm btn-outline-light" onclick="addDiscovered('${escapeJs(ip)}','${escapeJs(manuf)}','${escapeJs(model)}','${escapeJs(xaddrs)}')">Add</button>
            </div>
          </div>
        </div>
      </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
  });
  // Modal already shown with loading spinner, no need to show again
}

// --- Add discovered camera (opens modal for username/password and test) ---
function addDiscovered(ip, manufacturer, model, xaddrs) {
  const suggested = (manufacturer && model) ? (manufacturer + ' ' + model) : ('discovered-' + ip.replace(/[:.]/g,'-'));
  
  // Populate modal fields
  document.getElementById('addCamIp').value = ip;
  document.getElementById('addCamIpDisplay').value = ip;
  document.getElementById('addCamManufacturer').value = manufacturer || '';
  document.getElementById('addCamModel').value = model || '';
  document.getElementById('addCamXaddrs').value = xaddrs || ('http://' + ip + ':2020/onvif/device_service');
  document.getElementById('addCamName').value = suggested;
  document.getElementById('addCamUsername').value = '';
  document.getElementById('addCamPassword').value = '';
  document.getElementById('addCamDeviceUrl').value = xaddrs || '';
  document.getElementById('addCamTestResult').style.display = 'none';
  document.getElementById('addCamTestResult').innerHTML = '';
  
  addCameraModal.show();
}

// --- Test Connection button handler ---
document.getElementById('testConnectionBtn').addEventListener('click', async () => {
  const ip = document.getElementById('addCamIp').value;
  const username = document.getElementById('addCamUsername').value;
  const password = document.getElementById('addCamPassword').value;
  const deviceUrl = getDeviceUrl();
  
  const resultDiv = document.getElementById('addCamTestResult');
  resultDiv.style.display = 'block';
  resultDiv.className = 'mt-2 alert alert-info';
  resultDiv.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testing connection...';
  
  try {
    const body = new URLSearchParams();
    body.set('ip', ip);
    body.set('username', username);
    body.set('password', password);
    body.set('device_service_url', deviceUrl);
    
    const res = await fetch(BASE_URL + 'controller/add_camera.php?test=1', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    });
    const j = await res.json();
    if (j.ok) {
      resultDiv.className = 'mt-2 alert alert-success';
      let info = '✓ Connection successful!';
      if (j.manufacturer || j.model) {
        info += '<br><small>Device: ' + escapeHtml(j.manufacturer || '') + ' ' + escapeHtml(j.model || '') + '</small>';
      }
      resultDiv.innerHTML = info;
    } else {
      resultDiv.className = 'mt-2 alert alert-danger';
      resultDiv.innerHTML = '✗ Connection failed: ' + escapeHtml(j.error || 'Unknown error');
    }
  } catch (err) {
    resultDiv.className = 'mt-2 alert alert-danger';
    resultDiv.innerHTML = '✗ Test error: ' + escapeHtml(err.message);
  }
});

// --- Test PTZ button handler ---
document.getElementById('testPtzBtn').addEventListener('click', async () => {
  const ip = document.getElementById('addCamIp').value;
  const username = document.getElementById('addCamUsername').value;
  const password = document.getElementById('addCamPassword').value;
  const deviceUrl = getDeviceUrl();
  
  if (!ip) {
    showAddCamError('Please enter an IP address');
    return;
  }
  
  const resultDiv = document.getElementById('addCamTestResult');
  resultDiv.style.display = 'block';
  resultDiv.className = 'mt-2 alert alert-info';
  resultDiv.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testing PTZ... (moving camera slightly)';
  
  try {
    const body = new URLSearchParams();
    body.set('ip', ip);
    body.set('username', username);
    body.set('password', password);
    body.set('device_service_url', deviceUrl);
    
    const res = await fetch(BASE_URL + 'controller/add_camera.php?test=ptz', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    });
    const j = await res.json();
    if (j.ok) {
      resultDiv.className = 'mt-2 alert alert-success';
      let info = '✓ PTZ test successful!';
      if (j.message) {
        info += '<br><small>' + escapeHtml(j.message) + '</small>';
      }
      resultDiv.innerHTML = info;
    } else {
      resultDiv.className = 'mt-2 alert alert-danger';
      resultDiv.innerHTML = '✗ PTZ test failed: ' + escapeHtml(j.error || 'Unknown error');
    }
  } catch (err) {
    resultDiv.className = 'mt-2 alert alert-danger';
    resultDiv.innerHTML = '✗ PTZ test error: ' + escapeHtml(err.message);
  }
});

// --- Confirm Add Camera button handler ---
document.getElementById('confirmAddCameraBtn').addEventListener('click', async () => {
  const ip = document.getElementById('addCamIp').value;
  const name = document.getElementById('addCamName').value.trim();
  const username = document.getElementById('addCamUsername').value;
  const password = document.getElementById('addCamPassword').value;
  const manufacturer = document.getElementById('addCamManufacturer').value;
  const model = document.getElementById('addCamModel').value;
  const deviceUrl = getDeviceUrl();
  
  if (!name) {
    showAddCamError('Please enter a camera name');
    return;
  }
  
  const body = new URLSearchParams();
  body.set('ip', ip);
  body.set('name', name);
  body.set('manufacturer', manufacturer);
  body.set('model', model);
  body.set('device_service_url', deviceUrl);
  body.set('username', username);
  body.set('password', password);

  try {
    const res = await fetch(BASE_URL + 'controller/add_camera.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    });
    const j = await res.json();
    if (!j.ok) throw new Error(j.error || 'add failed');
    // success: close modals and reload to show new camera in selector
    addCameraModal.hide();
    scanModal.hide();
    location.reload();
  } catch (err) {
    showAddCamError('Failed to add camera: ' + err.message);
  }
});

// --- Device select & PTZ logic (updated to use simplified profiles structure) ---
document.getElementById('deviceSelect').addEventListener('change', async function(){
  const id = this.value;
  if (!id) return;
  currentDeviceId = id;
  const r = await fetch(BASE_URL + 'onvif/profiles?deviceId=' + encodeURIComponent(id));
  const j = await r.json();
  document.getElementById('debugArea').textContent = JSON.stringify(j, null, 2);
  if (!j.ok) { showErrorModal(j.error || 'Error fetching profiles'); return; }
  
  // Use new simplified profiles structure: {ok: true, profiles: [{name, token}, ...]}
  const profiles = j.profiles || [];
  
  if (profiles.length > 0 && profiles[0].token) {
    currentProfileToken = profiles[0].token;
    const s = await fetch(BASE_URL + 'onvif/stream?deviceId=' + encodeURIComponent(id) + '&profileToken=' + encodeURIComponent(currentProfileToken));
    const sj = await s.json();
    document.getElementById('debugArea').textContent = JSON.stringify(sj, null, 2);
    document.getElementById('streamUri').textContent = sj.streamUri || '-';
    document.getElementById('snapshotUri').textContent = sj.snapshotUri || '-';
    if (sj.snapshotUri && sj.snapshotUri.startsWith('http')) {
      document.getElementById('snapshotImg').src = sj.snapshotUri;
      document.getElementById('snapshotImg').style.display = 'block';
      document.getElementById('videoPlaceholder').style.display = 'none';
    } else {
      document.getElementById('snapshotImg').style.display = 'none';
      document.getElementById('videoPlaceholder').style.display = 'block';
      document.getElementById('videoPlaceholder').textContent = 'RTSP: ' + (sj.rtspHints?.high || sj.streamUri || '-');
    }
  } else {
    showErrorModal('No profile token found for device. The camera may require authentication or does not support media profiles.');
  }
});

async function ptz(action) {
  if (!currentDeviceId || !currentProfileToken) { showErrorModal('Please select a device first'); return; }
  const continuous = document.getElementById('continuousMoveCheckbox').checked ? '1' : '0';
  const body = new URLSearchParams();
  body.set('deviceId', currentDeviceId);
  body.set('profileToken', currentProfileToken);
  body.set('action', action);
  body.set('continuous', continuous);
  const r = await fetch(BASE_URL + 'onvif/ptz', { method:'POST', body });
  const j = await r.json();
  if (!j.ok) showErrorModal(j.error || 'PTZ error');
  if (continuous !== '1' && action !== 'stop' && j.ok) {
    setTimeout(()=>{ fetch(BASE_URL + 'onvif/ptz', { method:'POST', body: new URLSearchParams({deviceId:currentDeviceId, profileToken:currentProfileToken, action:'stop'}) }); }, 350);
  }
}