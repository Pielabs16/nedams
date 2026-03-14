<?php
// ============================================================
// views/admin/zones.php  — Zone / Parish management
// ============================================================
require_once __DIR__.'/../../config/app.php';
requireAdmin();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['save_zone'])) {
        if (!empty($_POST['zone_id'])) {
            $pdo->prepare('UPDATE zones SET name=?,parish=?,division=?,code_prefix=?,description=?,is_active=? WHERE id=?')
                ->execute([trim($_POST['name']),trim($_POST['parish']),trim($_POST['division']),
                           strtoupper(trim($_POST['code_prefix']??'')),trim($_POST['description']??''),
                           (int)($_POST['is_active']??1),(int)$_POST['zone_id']]);
        } else {
            $pdo->prepare('INSERT INTO zones(name,parish,division,code_prefix,description) VALUES(?,?,?,?,?)')
                ->execute([trim($_POST['name']),trim($_POST['parish']??''),trim($_POST['division']??'Nakawa'),
                           strtoupper(trim($_POST['code_prefix']??'')),trim($_POST['description']??'')]);
        }
        auditLog('save','zones','zone',$_POST['zone_id']??'new','Zone saved');
        $_SESSION['flash']=['type'=>'success','message'=>'Zone saved.'];
        header('Location: zones.php'); exit;
    }
}
if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM zones WHERE id=?')->execute([(int)$_GET['delete']]);
    header('Location: zones.php'); exit;
}

$zones = $pdo->query('
    SELECT z.*, COUNT(s.id) AS structure_count
    FROM zones z
    LEFT JOIN structures s ON s.zone=z.name
    GROUP BY z.id ORDER BY z.name ASC')->fetchAll();

$pageTitle='Zones';
require_once __DIR__.'/../partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/../partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/../partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Zones &amp; Parishes</h1>
        <p>Manage geographic zones used for structure classification</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-accent" onclick="openModal('modal-zone')">
            <i class="fas fa-plus"></i> Add Zone
        </button>
    </div>
</div>

<div id="flash-zone-msg"></div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Zone Name</th><th>Parish</th><th>Division</th><th>Code Prefix</th>
                    <th>Structures</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($zones as $z): ?>
                <tr>
                    <td class="fw-600"><?= htmlspecialchars($z['name']) ?></td>
                    <td><?= htmlspecialchars($z['parish']??'—') ?></td>
                    <td><?= htmlspecialchars($z['division']??'—') ?></td>
                    <td><span class="addr-code"><?= htmlspecialchars($z['code_prefix']??'—') ?></span></td>
                    <td><?= number_format($z['structure_count']) ?></td>
                    <td><span class="badge <?= $z['is_active']?'badge-active':'badge-inactive' ?>">
                        <?= $z['is_active']?'Active':'Inactive' ?></span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-ghost btn-sm btn-icon"
                                    onclick="editZone(<?= htmlspecialchars(json_encode($z)) ?>)">
                                <i class="fas fa-pencil"></i>
                            </button>
                            <?php if ($z['structure_count']==0): ?>
                            <a href="?delete=<?= $z['id'] ?>" class="btn btn-danger btn-sm btn-icon"
                               data-confirm="Delete this zone?">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Zone Modal -->
<div class="modal-overlay" id="modal-zone">
    <div class="modal">
        <div class="modal-header">
            <h3 id="zone-modal-title"><i class="fas fa-layer-group"></i> Add Zone</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="zone_id" id="zone-id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Zone Name *</label>
                        <input type="text" name="name" id="zone-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Code Prefix</label>
                        <input type="text" name="code_prefix" id="zone-prefix" class="form-control"
                               maxlength="4" placeholder="e.g. KB">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Parish</label>
                        <input type="text" name="parish" id="zone-parish" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Division</label>
                        <input type="text" name="division" id="zone-division" class="form-control" value="Nakawa">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="zone-desc" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="is_active" id="zone-active" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                <button type="submit" name="save_zone" class="btn btn-primary">
                    <i class="fas fa-floppy-disk"></i> Save Zone
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__.'/../partials/footer.php'; ?>
<script>
function editZone(z) {
    document.getElementById('zone-modal-title').innerHTML = '<i class="fas fa-pencil"></i> Edit Zone';
    document.getElementById('zone-id').value      = z.id;
    document.getElementById('zone-name').value    = z.name;
    document.getElementById('zone-prefix').value  = z.code_prefix||'';
    document.getElementById('zone-parish').value  = z.parish||'';
    document.getElementById('zone-division').value= z.division||'Nakawa';
    document.getElementById('zone-desc').value    = z.description||'';
    document.getElementById('zone-active').value  = z.is_active;
    openModal('modal-zone');
}
</script>
