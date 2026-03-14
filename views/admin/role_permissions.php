<?php
// ============================================================
// views/admin/role_permissions.php
// Super admin controls which nav sections each role can access
// ============================================================
require_once __DIR__.'/../../config/app.php';
requireRole('super_admin');

$pdo  = getDB();
$roles = ['admin','developer','mapper','viewer'];

// Nav items with labels and recommended defaults
$navItems = [
    ['dashboard',        'Dashboard',         ['admin','developer','mapper','viewer']],
    ['map',              'Live Map',           ['admin','developer','mapper','viewer']],
    ['search',           'Search Address',     ['admin','developer','mapper','viewer']],
    ['register',         'Register Structure', ['admin','mapper']],
    ['structures',       'All Structures',     ['admin','developer','mapper','viewer']],
    ['pending',          'Pending Review',     ['admin']],
    ['zones',            'Zones & Parishes',   ['admin']],
    ['analytics',        'Analytics',          ['admin']],
    ['service_requests', 'Service Requests',   ['admin','developer']],
    ['audit_log',        'Audit Log',          ['admin']],
    ['users',            'Manage Users',       ['admin']],
    ['messages',         'Messages',           ['admin','developer','mapper','viewer']],
    ['api_keys',         'API Keys',           ['admin','developer']],
    ['exports',          'Export Data',        ['admin']],
    ['settings',         'Settings',           []],
    ['api_docs',         'API Documentation',  ['developer']],
];

// Save changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    foreach ($roles as $role) {
        foreach ($navItems as [$key, $label, $rec]) {
            $allowed = isset($_POST['perm'][$role][$key]) ? 1 : 0;
            $pdo->prepare(
                'INSERT INTO role_permissions(role,nav_key,is_allowed,updated_by)
                 VALUES(?,?,?,?)
                 ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed),updated_by=VALUES(updated_by)'
            )->execute([$role, $key, $allowed, (int)$_SESSION['user_id']]);
        }
    }
    auditLog('save_permissions','role_permissions','roles','all','Nav permissions updated');
    $_SESSION['flash'] = ['type'=>'success','message'=>'Role permissions saved.'];
    header('Location: role_permissions.php'); exit;
}

// Apply recommendations
if (isset($_GET['apply_recommended'])) {
    foreach ($roles as $role) {
        foreach ($navItems as [$key, $label, $recommended]) {
            $allowed = in_array($role, $recommended) ? 1 : 0;
            $pdo->prepare(
                'INSERT INTO role_permissions(role,nav_key,is_allowed,updated_by)
                 VALUES(?,?,?,?)
                 ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed),updated_by=VALUES(updated_by)'
            )->execute([$role, $key, $allowed, (int)$_SESSION['user_id']]);
        }
    }
    $_SESSION['flash'] = ['type'=>'success','message'=>'Recommended permissions applied.'];
    header('Location: role_permissions.php'); exit;
}

// Load current permissions
$current = [];
$rows = $pdo->query('SELECT role,nav_key,is_allowed FROM role_permissions')->fetchAll();
foreach ($rows as $r) {
    $current[$r['role']][$r['nav_key']] = (bool)$r['is_allowed'];
}

$pageTitle = 'Role Permissions';
require_once __DIR__.'/../partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/../partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/../partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Role Permissions</h1>
        <p>Control which navigation items each role can access. Super Admin always has full access.</p>
    </div>
    <div class="page-header-actions">
        <a href="?apply_recommended=1" class="btn btn-ghost"
           data-confirm="Apply recommended defaults? This will overwrite current settings.">
            <i class="fas fa-wand-magic-sparkles"></i> Apply Recommended
        </a>
    </div>
</div>

<div id="flash-zone"></div>

<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <div class="card-header-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="card-title">Navigation Access Matrix</div>
        </div>
    </div>
    <form method="POST">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width:180px">Navigation Item</th>
                        <?php foreach ($roles as $role): ?>
                        <th style="text-align:center;min-width:110px">
                            <span class="badge badge-<?= $role ?>">
                                <?= htmlspecialchars(str_replace('_',' ',ucfirst($role))) ?>
                            </span>
                        </th>
                        <?php endforeach; ?>
                        <th style="text-align:center;color:var(--text-muted)">Recommended</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($navItems as [$key, $label, $recommended]): ?>
                    <tr>
                        <td class="fw-600 text-sm"><?= htmlspecialchars($label) ?></td>
                        <?php foreach ($roles as $role): ?>
                        <td style="text-align:center">
                            <label class="toggle" style="display:inline-block">
                                <input type="checkbox"
                                       name="perm[<?= $role ?>][<?= $key ?>]"
                                       value="1"
                                       <?= ($current[$role][$key] ?? false) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </td>
                        <?php endforeach; ?>
                        <td style="text-align:center">
                            <?php if ($recommended): ?>
                            <span class="text-xs text-muted">
                                <?= implode(', ', array_map(fn($r)=>ucfirst($r),$recommended)) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-xs text-muted">None</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <button type="submit" name="save_permissions" class="btn btn-primary">
                <i class="fas fa-floppy-disk"></i> Save Permissions
            </button>
            <span class="text-sm text-muted">
                <i class="fas fa-info-circle"></i>
                Super Admin always has access to everything regardless of this matrix.
            </span>
        </div>
    </form>
</div>

<?php require_once __DIR__.'/../partials/footer.php'; ?>
