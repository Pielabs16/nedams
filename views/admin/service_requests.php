<?php
// ============================================================
// views/admin/service_requests.php
// ============================================================
require_once __DIR__.'/../../config/app.php';
requireAdmin();

$pdo  = getDB();
$page = max(1,(int)($_GET['page']??1));
$per  = 30;

$where = ['1=1']; $params = [];
if (!empty($_GET['purpose'])) { $where[]='purpose=?'; $params[]=$_GET['purpose']; }
if (!empty($_GET['q'])) {
    $like='%'.$_GET['q'].'%';
    $where[]='(address_code LIKE ? OR requester_org LIKE ? OR requester_name LIKE ?)';
    $params[]=$like;$params[]=$like;$params[]=$like;
}
$wq = implode(' AND ', $where);

$cStmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE $wq");
$cStmt->execute($params);
$total = (int)$cStmt->fetchColumn();

$offset = ($page-1)*$per;
$stmt   = $pdo->prepare("
    SELECT r.*, k.name AS key_name
    FROM service_requests r
    LEFT JOIN api_keys k ON k.id=r.api_key_id
    WHERE $wq ORDER BY r.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([...$params, $per, $offset]);
$requests = $stmt->fetchAll();
$lastPage = max(1,(int)ceil($total/$per));

$pageTitle = 'Service Requests';
require_once __DIR__.'/../partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/../partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/../partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Service Requests</h1>
        <p>API address lookups by delivery services and emergency responders</p>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body" style="padding:12px 16px">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="q" class="form-control" style="max-width:240px"
                   placeholder="Code, org, name..." value="<?= htmlspecialchars($_GET['q']??'') ?>">
            <select name="purpose" class="form-control" style="max-width:150px">
                <option value="">All Purposes</option>
                <?php foreach(['delivery','emergency','visit','verification','survey','other'] as $p): ?>
                <option value="<?= $p ?>" <?= ($_GET['purpose']??'')===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="service_requests.php" class="btn btn-ghost">Clear</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <div class="card-header-icon"><i class="fas fa-truck"></i></div>
            <div class="card-title">Request Log</div>
        </div>
        <span class="text-muted text-sm"><?= number_format($total) ?> total</span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Time</th><th>Address</th><th>Organisation</th>
                    <th>Purpose</th><th>IP</th><th>Status</th><th>Response</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                <tr>
                    <td class="text-xs text-muted" style="white-space:nowrap">
                        <?= date('d M Y', strtotime($r['created_at'])) ?><br>
                        <?= date('H:i:s', strtotime($r['created_at'])) ?>
                    </td>
                    <td>
                        <a href="<?= appUrl() ?>/views/view.php?id=<?= urlencode($r['address_code']) ?>"
                           target="_blank"
                           class="addr-code" style="text-decoration:none"
                           title="View address card">
                            <?= htmlspecialchars($r['address_code']) ?>
                        </a>
                    </td>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($r['requester_org']??$r['requester_name']??'—') ?></div>
                        <?php if ($r['key_name']): ?>
                        <div class="text-xs text-muted">Key: <?= htmlspecialchars($r['key_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $purposeColors = ['delivery'=>'badge-info','emergency'=>'badge-danger',
                                                'visit'=>'badge-dark','survey'=>'badge-warning',
                                                'verification'=>'badge-success','other'=>'badge-dark']; ?>
                        <span class="badge <?= $purposeColors[$r['purpose']]??'badge-dark' ?>">
                            <?= htmlspecialchars($r['purpose']) ?>
                        </span>
                    </td>
                    <td class="mono text-xs text-muted"><?= htmlspecialchars($r['ip_address']??'—') ?></td>
                    <td>
                        <span class="badge <?= (int)$r['response_code']===200?'badge-success':'badge-danger' ?>">
                            <?= htmlspecialchars($r['response_code']) ?>
                        </span>
                    </td>
                    <td class="text-xs text-muted">
                        <?= $r['response_ms'] ? $r['response_ms'].'ms' : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$requests): ?>
                <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">
                    No requests recorded yet.
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($lastPage > 1): ?>
    <div class="card-footer">
        <div class="pagination">
            <?php for ($p=1;$p<=$lastPage;$p++): ?>
            <a href="?<?= http_build_query(array_merge(['purpose'=>$_GET['purpose']??'','q'=>$_GET['q']??''],['page'=>$p])) ?>"
               class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__.'/../partials/footer.php'; ?>
