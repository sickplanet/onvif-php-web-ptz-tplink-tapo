<?php
/**
 * view/page_admin.php - Admin panel UI for managing cameras and users
 * Uses BASE_URL constant (defined in index.php)
 */
$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
$cameras = load_json_cfg('cameras.json', ['cameras' => []])['cameras'] ?? [];
$users = load_json_cfg('users.json', ['users' => []])['users'] ?? [];
$config = load_json_cfg('config.json', []);
$flash = ErrorHandler::getFlashMessages();

require_once __DIR__ . '/header.php';
?>

<?php if ($flash['error']): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <?= htmlspecialchars($flash['error']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($flash['success']): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?= htmlspecialchars($flash['success']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Admin Panel</h4>
  <a href="<?= htmlspecialchars($baseUrl) ?>" class="btn btn-outline-light">
    <span>←</span> Back to Home
  </a>
</div>

<div class="row g-3">
  <!-- Cameras Section -->
  <div class="col-12 col-lg-6">
    <div class="card mb-3">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Cameras</h5>
        <button class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#addCameraModal">Add Camera</button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-dark table-hover mb-0">
            <thead>
              <tr>
                <th>Name</th>
                <th>IP</th>
                <th class="d-none d-sm-table-cell">PTZ</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($cameras)): ?>
              <tr><td colspan="4" class="text-center text-muted">No cameras configured</td></tr>
              <?php else: ?>
              <?php foreach ($cameras as $cam): ?>
              <tr>
                <td class="text-break"><?= htmlspecialchars($cam['name'] ?? '') ?></td>
                <td><code class="text-break"><?= htmlspecialchars($cam['ip'] ?? '') ?></code></td>
                <td class="d-none d-sm-table-cell"><?= !empty($cam['allowptz']) ? '✓' : '✗' ?></td>
                <td>
                  <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-light" 
                            onclick="editCamera(<?= htmlspecialchars(json_encode($cam), ENT_QUOTES) ?>)">Edit</button>
                    <button class="btn btn-outline-danger" 
                            onclick="deleteCamera('<?= htmlspecialchars($cam['id'] ?? '', ENT_QUOTES) ?>')">Del</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Users Section -->
  <div class="col-12 col-lg-6">
    <div class="card mb-3">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Users</h5>
        <button class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-dark table-hover mb-0">
            <thead>
              <tr>
                <th>Username</th>
                <th>Admin</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
              <tr><td colspan="3" class="text-center text-muted">No users configured</td></tr>
              <?php else: ?>
              <?php foreach ($users as $user): ?>
              <tr>
                <td class="text-break"><?= htmlspecialchars($user['username'] ?? '') ?></td>
                <td><?= !empty($user['isadmin']) ? '✓ Admin' : 'User' ?></td>
                <td>
                  <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-light" 
                            onclick="editUser('<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?>', <?= !empty($user['isadmin']) ? 'true' : 'false' ?>)">Edit</button>
                    <?php if (($_SESSION['user']['username'] ?? '') !== ($user['username'] ?? '')): ?>
                    <button class="btn btn-outline-danger" 
                            onclick="deleteUser('<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?>')">Del</button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Settings Section -->
<div class="row mt-4">
  <div class="col-12">
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Application Settings</h5>
      </div>
      <div class="card-body">
        <form method="post" action="<?= htmlspecialchars($baseUrl) ?>admin">
          <input type="hidden" name="action" value="save_settings">
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="isPublicSwitch" name="isPublic" <?= !empty($config['isPublic']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="isPublicSwitch">
                    <strong>Public Access Mode</strong>
                  </label>
                </div>
                <small class="text-muted d-block mt-1">
                  When enabled, the application is accessible from any IP address.<br>
                  When disabled, only connections from allowed IP ranges are permitted (except public camera pages).
                </small>
              </div>
              
              <div class="mb-3">
                <label class="form-label"><strong>Camera Discovery IP Ranges (CIDRs)</strong></label>
                <textarea name="scan_cidrs" class="form-control bg-dark text-light" rows="3" 
                          placeholder="192.168.1.0/24&#10;10.0.0.0/8"><?= htmlspecialchars(implode("\n", $config['camera_discovery_cidrs'] ?? [])) ?></textarea>
                <small class="text-muted">Enter one CIDR per line. Used for scanning/discovering cameras on your network.</small>
              </div>
              
              <div class="mb-3">
                <label class="form-label"><strong>Cache TTL (seconds)</strong></label>
                <input type="number" name="cache_ttl_seconds" class="form-control bg-dark text-light" 
                       value="<?= htmlspecialchars($config['cache_ttl_seconds'] ?? 86400) ?>" min="60" max="604800">
                <small class="text-muted">
                  How long to cache camera capabilities and stream URIs. Default: 86400 (24 hours).<br>
                  Range: 60 seconds to 604800 (1 week).
                </small>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"><strong>Allowed Client IP Ranges (CIDRs)</strong></label>
                <textarea name="allowed_cidrs" class="form-control bg-dark text-light" rows="3" 
                          placeholder="192.168.1.0/24&#10;10.0.0.0/8"><?= htmlspecialchars(implode("\n", $config['allowed_cidrs'] ?? [])) ?></textarea>
                <small class="text-muted">
                  When Public Access is OFF, only clients from these IP ranges can access the admin interface.<br>
                  Public camera pages (share links) are always accessible.
                </small>
              </div>
              
              <div class="mb-3">
                <label class="form-label text-muted"><strong>Current Client IP:</strong></label>
                <code class="ms-2"><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown') ?></code>
              </div>
              
              <div class="mb-3">
                <label class="form-label"><strong>Event Settings</strong></label>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="motionDetectionEnabled" name="motionDetectionEnabled" <?= !empty($config['motionDetectionEnabled'] ?? true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="motionDetectionEnabled">Enable Motion Detection</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="browserNotificationsEnabled" name="browserNotificationsEnabled" <?= !empty($config['browserNotificationsEnabled'] ?? true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="browserNotificationsEnabled">Enable Browser Notifications</label>
                </div>
              </div>
            </div>
          </div>
          
          <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Add Camera Modal -->
<div class="modal fade" id="addCameraModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <form method="post" action="<?= htmlspecialchars($baseUrl) ?>admin">
        <input type="hidden" name="action" value="add_camera">
        <div class="modal-header">
          <h5 class="modal-title">Add Camera</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Name *</label>
            <input name="name" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">IP Address *</label>
            <input name="ip" class="form-control" required placeholder="192.168.1.100">
          </div>
          <div class="mb-2">
            <label class="form-label">Username</label>
            <input name="username" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">Device Service URL</label>
            <input name="device_service_url" class="form-control" placeholder="http://IP:2020/onvif/device_service">
            <small class="text-muted">Leave blank for default</small>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="allowptz" id="addCamPtz" checked>
            <label class="form-check-label" for="addCamPtz">Allow PTZ Control</label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="allow_audio" id="addCamAudio">
            <label class="form-check-label" for="addCamAudio">Allow Audio</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ispublic" id="addCamPublic">
            <label class="form-check-label" for="addCamPublic">Public Camera (visible without login)</label>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <button type="button" class="btn btn-outline-info" onclick="testPTZ()" id="testPtzBtn">Test PTZ</button>
          <div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Add Camera</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Camera Modal -->
<div class="modal fade" id="editCameraModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <form method="post" action="<?= htmlspecialchars($baseUrl) ?>admin">
        <input type="hidden" name="action" value="edit_camera">
        <input type="hidden" name="id" id="editCamId">
        <div class="modal-header">
          <h5 class="modal-title">Edit Camera</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Name *</label>
            <input name="name" id="editCamName" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">IP Address *</label>
            <input name="ip" id="editCamIp" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Username</label>
            <input name="username" id="editCamUser" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">Password</label>
            <input name="password" id="editCamPass" type="password" class="form-control" placeholder="Leave blank to keep current">
          </div>
          <div class="mb-2">
            <label class="form-label">Device Service URL</label>
            <input name="device_service_url" id="editCamUrl" class="form-control">
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="allowptz" id="editCamPtz">
            <label class="form-check-label" for="editCamPtz">Allow PTZ Control</label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="allow_audio" id="editCamAudio">
            <label class="form-check-label" for="editCamAudio">Allow Audio</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ispublic" id="editCamPublic">
            <label class="form-check-label" for="editCamPublic">Public Camera (visible without login)</label>
          </div>
          
          <!-- PTZ Test Results -->
          <div id="editCamPtzInfo" class="mt-3 small text-muted" style="display:none;">
            <strong>PTZ Capabilities:</strong>
            <span id="editCamPtzStatus"></span>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <button type="button" class="btn btn-outline-info" onclick="testPTZEdit()" id="testPtzBtnEdit">Test PTZ</button>
          <div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Camera Form (hidden) -->
<form id="deleteCameraForm" method="post" action="<?= htmlspecialchars($baseUrl) ?>admin" style="display:none;">
  <input type="hidden" name="action" value="delete_camera">
  <input type="hidden" name="id" id="deleteCamId">
</form>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <form method="post" action="<?= htmlspecialchars($baseUrl) ?>admin">
        <input type="hidden" name="action" value="add_user">
        <div class="modal-header">
          <h5 class="modal-title">Add User</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Username *</label>
            <input name="username" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Password *</label>
            <input name="password" type="password" class="form-control" required>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="isadmin" id="addUserAdmin">
            <label class="form-check-label" for="addUserAdmin">Administrator</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <form method="post" action="<?= htmlspecialchars($baseUrl) ?>admin">
        <input type="hidden" name="action" value="edit_user">
        <div class="modal-header">
          <h5 class="modal-title">Edit User</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Username</label>
            <input name="username" id="editUsername" class="form-control" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">New Password</label>
            <input name="password" type="password" class="form-control" placeholder="Leave blank to keep current">
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="isadmin" id="editUserAdmin">
            <label class="form-check-label" for="editUserAdmin">Administrator</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete User Form (hidden) -->
<form id="deleteUserForm" method="post" action="<?= htmlspecialchars($baseUrl) ?>admin" style="display:none;">
  <input type="hidden" name="action" value="delete_user">
  <input type="hidden" name="username" id="deleteUsername">
</form>

<!-- PTZ Test Results Modal -->
<div class="modal fade" id="ptzTestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">PTZ Test Results</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="ptzTestBody">
        <div class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Testing...</span>
          </div>
          <p class="mt-2">Testing PTZ capabilities...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

<script>
const BASE_URL = <?= json_encode($baseUrl) ?>;
const editCameraModal = new bootstrap.Modal(document.getElementById('editCameraModal'));
const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
const ptzTestModal = new bootstrap.Modal(document.getElementById('ptzTestModal'));

let currentEditCameraId = null;

function editCamera(cam) {
  currentEditCameraId = cam.id;
  document.getElementById('editCamId').value = cam.id || '';
  document.getElementById('editCamName').value = cam.name || '';
  document.getElementById('editCamIp').value = cam.ip || '';
  document.getElementById('editCamUser').value = cam.username || '';
  document.getElementById('editCamPass').value = '';
  document.getElementById('editCamUrl').value = cam.device_service_url || '';
  document.getElementById('editCamPtz').checked = !!cam.allowptz;
  document.getElementById('editCamAudio').checked = !!cam.allow_audio;
  document.getElementById('editCamPublic').checked = !!cam.ispublic;
  
  // Show PTZ info if available
  const ptzInfo = document.getElementById('editCamPtzInfo');
  const ptzStatus = document.getElementById('editCamPtzStatus');
  
  if (cam.hasPTZ !== null && cam.hasPTZ !== undefined) {
    ptzInfo.style.display = 'block';
    if (cam.hasPTZ) {
      const dirs = cam.ptzDirections || [];
      ptzStatus.innerHTML = '<span class="text-success">✓ PTZ Available</span> (' + dirs.join(', ') + ')';
    } else {
      ptzStatus.innerHTML = '<span class="text-warning">✗ PTZ Not Available</span>';
    }
  } else {
    ptzInfo.style.display = 'none';
  }
  
  editCameraModal.show();
}

function deleteCamera(id) {
  if (!confirm('Are you sure you want to delete this camera?')) return;
  document.getElementById('deleteCamId').value = id;
  document.getElementById('deleteCameraForm').submit();
}

function editUser(username, isadmin) {
  document.getElementById('editUsername').value = username;
  document.getElementById('editUserAdmin').checked = isadmin;
  editUserModal.show();
}

function deleteUser(username) {
  if (!confirm('Are you sure you want to delete user "' + username + '"?')) return;
  document.getElementById('deleteUsername').value = username;
  document.getElementById('deleteUserForm').submit();
}

// PTZ Test functions
async function testPTZ() {
  // This would need the camera to be saved first
  alert('Please save the camera first, then use "Test PTZ" from the Edit menu.');
}

async function testPTZEdit() {
  if (!currentEditCameraId) {
    alert('No camera selected');
    return;
  }
  
  const testBody = document.getElementById('ptzTestBody');
  testBody.innerHTML = `
    <div class="text-center">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Testing...</span>
      </div>
      <p class="mt-2">Testing PTZ capabilities...</p>
      <p class="small text-muted">This may take 10-15 seconds</p>
    </div>`;
  
  ptzTestModal.show();
  
  try {
    const formData = new FormData();
    formData.append('deviceId', currentEditCameraId);
    
    const res = await fetch(BASE_URL + 'onvif/test-ptz', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    
    let html = '';
    if (data.ok && data.hasPTZ) {
      html = `
        <div class="alert alert-success">
          <strong>✓ PTZ Supported</strong>
        </div>
        <h6>Test Results:</h6>
        <table class="table table-dark table-sm">
          <thead><tr><th>Direction</th><th>Status</th></tr></thead>
          <tbody>`;
      
      const results = data.results?.tests || [];
      results.forEach(test => {
        const icon = test.success ? '✓' : '✗';
        const cls = test.success ? 'text-success' : 'text-danger';
        html += `<tr><td>${test.action}</td><td class="${cls}">${icon} ${test.message}</td></tr>`;
      });
      
      html += `</tbody></table>
        <p class="small text-muted">Available directions: ${data.ptzDirections.join(', ')}</p>`;
      
      // Update the edit form PTZ info
      const ptzInfo = document.getElementById('editCamPtzInfo');
      const ptzStatus = document.getElementById('editCamPtzStatus');
      ptzInfo.style.display = 'block';
      ptzStatus.innerHTML = '<span class="text-success">✓ PTZ Available</span> (' + data.ptzDirections.join(', ') + ')';
      
    } else if (data.ok && !data.hasPTZ) {
      html = `
        <div class="alert alert-warning">
          <strong>✗ PTZ Not Supported</strong>
          <p class="mb-0 small">This camera does not have PTZ capabilities.</p>
        </div>`;
      
      // Update the edit form PTZ info  
      const ptzInfo = document.getElementById('editCamPtzInfo');
      const ptzStatus = document.getElementById('editCamPtzStatus');
      ptzInfo.style.display = 'block';
      ptzStatus.innerHTML = '<span class="text-warning">✗ PTZ Not Available</span>';
    } else {
      html = `
        <div class="alert alert-danger">
          <strong>Error</strong>
          <p class="mb-0">${data.error || 'Unknown error occurred'}</p>
        </div>`;
    }
    
    testBody.innerHTML = html;
    
  } catch (err) {
    testBody.innerHTML = `
      <div class="alert alert-danger">
        <strong>Error</strong>
        <p class="mb-0">${err.message}</p>
      </div>`;
  }
}
</script>
