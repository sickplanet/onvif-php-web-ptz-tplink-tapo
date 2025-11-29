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
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allow_audio" id="addCamAudio">
            <label class="form-check-label" for="addCamAudio">Allow Audio</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Camera</button>
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
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allow_audio" id="editCamAudio">
            <label class="form-check-label" for="editCamAudio">Allow Audio</label>
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

<?php require_once __DIR__ . '/footer.php'; ?>

<script>
const editCameraModal = new bootstrap.Modal(document.getElementById('editCameraModal'));
const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));

function editCamera(cam) {
  document.getElementById('editCamId').value = cam.id || '';
  document.getElementById('editCamName').value = cam.name || '';
  document.getElementById('editCamIp').value = cam.ip || '';
  document.getElementById('editCamUser').value = cam.username || '';
  document.getElementById('editCamPass').value = '';
  document.getElementById('editCamUrl').value = cam.device_service_url || '';
  document.getElementById('editCamPtz').checked = !!cam.allowptz;
  document.getElementById('editCamAudio').checked = !!cam.allow_audio;
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
</script>
