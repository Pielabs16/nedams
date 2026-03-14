<?php
// ============================================================
// views/admin/audit_log.php
// ============================================================
require_once __DIR__.'/../../config/app.php';
requireAdmin();

$pdo  = getDB();
$page = max(1,(int)($_GET['page']??1));
$per  = 40;

$where  = ['1=1']; $params = [];
if (!empty($_GET['module'])) { $where[]='module=?';     $params[]=$_GET['module']; }
if (!empty($_GET['uid']))    { $where[]='user_id=?';    $params[]=(int)$_GET['uid']; }
if (!empty($_GET['q'])) {
    $like='%'.$_GET['q'].'%';
    $where[]='(action LIKE ? OR description LIKE ? OR user_email LIKE ?)';
    $params[]=$like;$params[]=$like;$params[]=$like;
}
$wq = implode(' AND ',$where);

$cStmt=$pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE $wq");
$cStmt->execute($params);
$total = (int)$cStmt->fetchColumn();
$lastPage = max(1,(int)ceil($total/$per));
$offset = ($page-1)*$per;

$stmt = $pdo->prepare("SELECT * FROM audit_log WHERE $wq ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([...$params,$per,$offset]);
$logs = $stmt->fetchAll();

// Module list for filter
$modules = $pdo->query('SELECT DISTINCT module FROM audit_log WHERE module IS NOT NULL ORDER BY module')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle='Audit Log';
require_once __DIR__.'/../partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/../partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/../partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Audit Log</h1>
        <p>Complete record of all system actions — <?= number_format($total) ?> entries</p>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body" style="padding:12px 16px">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="q" class="form-control" style="max-width:240px"
                   placeholder="Action, description, email..." value="<?= htmlspecialchars($_GET['q']??'') ?>">
            <select name="module" class="form-control" style="max-width:160px">
                <option value="">All Modules</option>
                <?php foreach($modules as $m): ?>
                <option value="<?= htmlspecialchars($m) ?>" <?= ($_GET['module']??'')===$m?'selected':'' ?>>
                    <?= htmlspecialchars($m) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="audit_log.php" class="btn btn-ghost">Clear</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Target</th><th>Description</th><th>IP</th></tr>
            </thead>
            <tbody>
                <?php
                $actionColors = [
                    'login'=>'badge-success','logout'=>'badge-dark',
                    'create'=>'badge-info','update'=>'badge-warning',
                    'delete'=>'badge-danger','verify'=>'badge-success',
                    'revoke'=>'badge-danger','register'=>'badge-info',
                    'save_settings'=>'badge-warning',
                ];
                foreach ($logs as $l):
                    $badge = $actionColors[$l['action']] ?? 'badge-dark';
                ?>
                <tr>
                    <td class="text-xs text-muted" style="white-space:nowrap">
                        <?= date('d M Y', strtotime($l['created_at'])) ?><br>
                        <?= date('H:i:s', strtotime($l['created_at'])) ?>
                    </td>
                    <td class="text-sm">
                        <?= htmlspecialchars($l['user_email']??'System') ?>
                    </td>
                    <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($l['action']) ?></span></td>
                    <td class="text-xs text-muted"><?= htmlspecialchars($l['module']??'—') ?></td>
                    <td class="text-xs mono text-muted">
                        <?= htmlspecialchars($l['target_type']??'') ?>
                        <?= $l['target_id'] ? '#'.htmlspecialchars($l['target_id']) : '' ?>
                    </td>
                    <td class="text-sm text-muted"><?= htmlspecialchars(substr($l['description']??'',0,80)) ?></td>
                    <td class="mono text-xs text-muted"><?= htmlspecialchars($l['ip_address']??'—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">
                    No audit entries found.
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($lastPage > 1): ?>
    <div class="card-footer">
        <div class="pagination">
            <?php for ($p=1;$p<=$lastPage;$p++): ?>
            <a href="?<?= http_build_query(array_merge(['module'=>$_GET['module']??'','q'=>$_GET['q']??''],['page'=>$p])) ?>"
               class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__.'/../partials/footer.php'; ?>
