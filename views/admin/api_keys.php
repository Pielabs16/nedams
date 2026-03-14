<?php
// ============================================================
// views/admin/api_keys.php  — API Key management
// ============================================================
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../models/User.php';
requireAdmin();

$newKey = null;
// Create
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_key'])) {
    $result = ApiKey::create([
        'name'         => trim($_POST['name']??''),
        'organisation' => trim($_POST['organisation']??''),
        'permissions'  => $_POST['permissions']??'read',
        'rate_limit'   => (int)($_POST['rate_limit']??1000),
        'expires_at'   => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null,
        'notes'        => trim($_POST['notes']??''),
        'created_by'   => (int)$_SESSION['user_id'],
    ]);
    if ($result['success']) {
        $newKey = $result['key'];
        $_SESSION['flash'] = ['type'=>'success','message'=>'API key created. Copy it now — it will not be shown again.'];
    }
}
// Revoke
if (isset($_GET['revoke'])) {
    ApiKey::revoke((int)$_GET['revoke']);
    $_SESSION['flash'] = ['type'=>'warning','message'=>'API key revoked.'];
    header('Location: api_keys.php'); exit;
}

$keys = ApiKey::all();
$pageTitle = 'API Keys';
require_once __DIR__.'/../partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/../partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/../partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>API Key Management</h1>
        <p>Create and manage API keys for third-party services — no hardcoded keys</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-accent" onclick="openModal('modal-create-key')">
            <i class="fas fa-plus"></i> Create API Key
        </button>
    </div>
</div>

<div id="flash-zone"></div>

<?php if ($newKey): ?>
<div class="alert alert-success mb-3" id="new-key-alert">
    <i class="fas fa-key"></i>
    <div>
        <strong>New API Key Created</strong> — Copy this key now. It will never be shown again.<br>
        <code id="new-key-value" style="font-family:var(--font-mono);font-size:.9rem;
              background:rgba(0,0,0,.08);padding:4px 10px;border-radius:3px;
              display:inline-block;margin-top:6px;letter-spacing:.05em"><?= htmlspecialchars($newKey) ?></code>
        <button onclick="copyToClipboard('<?= htmlspecialchars($newKey) ?>',this)"
                class="btn btn-ghost btn-sm" style="margin-left:8px">
            <i class="fas fa-copy"></i> Copy
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Keys table -->
<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <div class="card-header-icon"><i class="fas fa-key"></i></div>
            <div class="card-title">Active API Keys</div>
        </div>
        <span class="text-muted text-sm"><?= count($keys) ?> total</span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Name / Organisation</th>
                    <th>Key Prefix</th>
                    <th>Permissions</th>
                    <th>Rate Limit</th>
                    <th>Usage</th>
                    <th>Last Used</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keys as $k): ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($k['name']) ?></div>
                        <div class="text-muted text-xs"><?= htmlspecialchars($k['organisation']??'') ?></div>
                        <?php if ($k['notes']): ?>
                        <div class="text-xs text-muted mt-1"><?= htmlspecialchars($k['notes']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="mono"><?= htmlspecialchars($k['key_prefix']) ?>…</td>
                    <td>
                        <?php foreach (explode(',',$k['permissions']) as $perm): ?>
                        <span class="badge badge-info"><?= htmlspecialchars($perm) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><?= number_format($k['rate_limit']) ?>/day</td>
                    <td><?= number_format($k['usage_count']) ?></td>
                    <td class="text-xs text-muted">
                        <?= $k['last_used'] ? date('d M Y H:i', strtotime($k['last_used'])) : '—' ?>
                    </td>
                    <td class="text-xs <?= ($k['expires_at']&&strtotime($k['expires_at'])<time()) ? 'text-danger' : 'text-muted' ?>">
                        <?= $k['expires_at'] ? date('d M Y', strtotime($k['expires_at'])) : 'Never' ?>
                    </td>
                    <td>
                        <span class="badge <?= $k['is_active'] ? 'badge-active' : 'badge-rejected' ?>">
                            <?= $k['is_active'] ? 'Active' : 'Revoked' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($k['is_active']): ?>
                        <a href="?revoke=<?= $k['id'] ?>"
                           class="btn btn-danger btn-sm"
                           data-confirm="Revoke this API key? This cannot be undone.">
                            <i class="fas fa-ban"></i> Revoke
                        </a>
                        <?php else: ?>
                        <span class="text-muted text-xs">Revoked</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$keys): ?>
                <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text-muted)">
                    No API keys yet.
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <i class="fas fa-info-circle"></i>
        Keys are stored as SHA-256 hashes. Once created, the plaintext key is shown only once.
        Delivery services and emergency responders authenticate using <code>X-NEDAMS-Key</code> header.
    </div>
</div>

<!-- Usage documentation -->
<div class="card mt-3">
    <div class="card-header">
        <div class="card-header-left">
            <div class="card-header-icon"><i class="fas fa-book-open"></i></div>
            <div class="card-title">API Authentication</div>
        </div>
    </div>
    <div class="card-body">
        <p class="text-sm text-muted mb-2">Include the key in every API request via the <code>X-NEDAMS-Key</code> header:</p>
        <pre style="background:#071c2c;color:#4a8aa8;padding:16px;border-radius:6px;font-family:var(--font-mono);font-size:.82rem;overflow-x:auto;line-height:1.8">GET <?= appUrl() ?>/api/get_coordinates.php?code=NE4K7X
X-NEDAMS-Key: nk_your_api_key_here
X-Requester: Jumia Delivery</pre>
    </div>
</div>

<!-- Create Key Modal -->
<div class="modal-overlay" id="modal-create-key">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-key"></i> Create New API Key</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Key Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Jumia Delivery" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Organisation</label>
                        <input type="text" name="organisation" class="form-control" placeholder="Company name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Permissions</label>
                        <select name="permissions" class="form-control">
                            <option value="read">Read only</option>
                            <option value="read,write">Read + Write</option>
                            <option value="read,write,admin">Full Access</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rate Limit (requests/day)</label>
                        <input type="number" name="rate_limit" class="form-control" value="1000" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Expiry Date (optional)</label>
                    <input type="date" name="expires_at" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Purpose or contact info..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                <button type="submit" name="create_key" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Generate Key
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__.'/../partials/footer.php'; ?>
