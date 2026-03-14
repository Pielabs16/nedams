<?php
// ============================================================
// views/admin/structures.php  — v2.1 fixed
// - View structure card works
// - Search works across all fields
// - Pending filter works
// ============================================================
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../models/Structure.php';
require_once __DIR__.'/../../models/AddressGenerator.php';
requireLogin();

$pdo = getDB();

// ---- Actions ------------------------------------------------
if (isset($_GET['verify']) && isAdmin()) {
    $id = (int)$_GET['verify'];
    Structure::verify($id, (int)$_SESSION['user_id'], 'verified');
    $_SESSION['flash'] = ['type'=>'success','message'=>'Structure verified.'];
    // Preserve filters on redirect
    $back = array_filter($_GET, fn($k) => !in_array($k,['verify']), ARRAY_FILTER_USE_KEY);
    header('Location: structures.php?'.http_build_query($back)); exit;
}
if (isset($_POST['reject_id']) && isAdmin()) {
    Structure::verify(
        (int)$_POST['reject_id'],
        (int)$_SESSION['user_id'],
        'rejected',
        trim($_POST['reason'] ?? '')
    );
    $_SESSION['flash'] = ['type'=>'warning','message'=>'Structure rejected.'];
    header('Location: structures.php'); exit;
}
if (isset($_GET['archive']) && isAdmin()) {
    Structure::verify((int)$_GET['archive'], (int)$_SESSION['user_id'], 'archived');
    $_SESSION['flash'] = ['type'=>'info','message'=>'Structure archived.'];
    header('Location: structures.php'); exit;
}

// ---- Filters ------------------------------------------------
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, (int)setting('general.structures_per_page', 25));
$filters = [
    'status' => trim($_GET['status'] ?? ''),
    'type'   => trim($_GET['type']   ?? ''),
    'zone'   => trim($_GET['zone']   ?? ''),
    'q'      => trim($_GET['q']      ?? ''),
];
$data  = Structure::all($page, $perPage, $filters);
$zones = $pdo->query(
    'SELECT DISTINCT zone FROM structures WHERE zone IS NOT NULL ORDER BY zone'
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'All Structures';
require_once __DIR__.'/../partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/../partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/../partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Structures</h1>
        <p>
            <?= number_format($data['total']) ?> records
            <?php if ($filters['status']): ?>
            &nbsp;&middot;&nbsp;
            <span class="badge badge-<?= htmlspecialchars($filters['status']) ?>">
                <?= htmlspecialchars($filters['status']) ?>
            </span>
            <?php endif; ?>
            <?php if ($filters['q']): ?>
            &nbsp;&middot;&nbsp; searching <em>"<?= htmlspecialchars($filters['q']) ?>"</em>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header-actions">
        <?php if (canAccessNav('register')): ?>
        <a href="<?= appUrl() ?>/views/register.php" class="btn btn-accent">
            <i class="fas fa-plus"></i> Register New
        </a>
        <?php endif; ?>
        <a href="<?= appUrl() ?>/views/map.php" class="btn btn-ghost">
            <i class="fas fa-map"></i> Map View
        </a>
    </div>
</div>

<div id="flash-zone"></div>

<!-- Filter bar -->
<div class="card mb-3">
    <div class="card-body" style="padding:12px 16px">
        <form method="GET" class="d-flex gap-2 flex-wrap align-center">
            <div class="input-group" style="max-width:280px;flex:1">
                <span class="input-addon"><i class="fas fa-magnifying-glass"></i></span>
                <input type="text" name="q" class="form-control"
                       placeholder="Code, name, phone, zone…"
                       value="<?= htmlspecialchars($filters['q']) ?>">
            </div>
            <select name="status" class="form-control" style="max-width:140px">
                <option value="">All Status</option>
                <option value="pending"  <?= $filters['status']==='pending'  ?'selected':'' ?>>Pending</option>
                <option value="verified" <?= $filters['status']==='verified' ?'selected':'' ?>>Verified</option>
                <option value="rejected" <?= $filters['status']==='rejected' ?'selected':'' ?>>Rejected</option>
                <option value="archived" <?= $filters['status']==='archived' ?'selected':'' ?>>Archived</option>
            </select>
            <select name="type" class="form-control" style="max-width:150px">
                <option value="">All Types</option>
                <?php foreach(['residential','commercial','school','clinic','worship','government','ngo','other'] as $t): ?>
                <option value="<?= $t ?>" <?= $filters['type']===$t?'selected':'' ?>>
                    <?= ucfirst($t) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="zone" class="form-control" style="max-width:150px">
                <option value="">All Zones</option>
                <?php foreach($zones as $z): ?>
                <option value="<?= htmlspecialchars($z) ?>"
                        <?= $filters['zone']===$z?'selected':'' ?>>
                    <?= htmlspecialchars($z) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if (array_filter($filters)): ?>
            <a href="structures.php" class="btn btn-ghost">
                <i class="fas fa-xmark"></i> Clear
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Resident</th>
                    <th>Zone / Parish</th>
                    <th>Type</th>
                    <th>Confidence</th>
                    <th>Status</th>
                    <th>Mapper</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['data'])): ?>
                <tr>
                    <td colspan="9" style="text-align:center;padding:36px;color:var(--text-muted)">
                        <i class="fas fa-building" style="font-size:1.4rem;display:block;margin-bottom:8px"></i>
                        No structures found.
                        <?php if (array_filter($filters)): ?>
                        <a href="structures.php" style="display:block;margin-top:8px;font-size:.82rem">
                            Clear filters
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($data['data'] as $s): ?>
                <tr>
                    <td>
                        <!-- View card link — always works -->
                        <a href="<?= shareUrl($s) ?>"
                           target="_blank"
                           class="addr-code"
                           style="text-decoration:none"
                           title="View address card">
                            <?= htmlspecialchars($s['address_code']) ?>
                        </a>
                    </td>
                    <td>
                        <div class="fw-600" style="font-size:.85rem">
                            <?= htmlspecialchars($s['resident_name']) ?>
                        </div>
                        <?php if ($s['phone']): ?>
                        <div class="text-xs text-muted"><?= htmlspecialchars($s['phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm">
                        <?= htmlspecialchars($s['zone'] ?? '—') ?>
                        <?php if ($s['parish']): ?>
                        <div class="text-xs text-muted"><?= htmlspecialchars($s['parish']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="text-sm" style="text-transform:capitalize">
                            <?= htmlspecialchars($s['structure_type']) ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $c   = (int)$s['confidence_score'];
                        $cls = $c >= 90 ? 'high' : ($c >= 65 ? 'medium' : 'low');
                        ?>
                        <div class="conf-bar">
                            <div class="conf-track">
                                <div class="conf-fill <?= $cls ?>" style="width:<?= $c ?>%"></div>
                            </div>
                            <span class="text-xs text-muted"><?= $c ?>%</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-<?= htmlspecialchars($s['status']) ?>">
                            <?= htmlspecialchars($s['status']) ?>
                        </span>
                    </td>
                    <td class="text-xs text-muted">
                        <?= htmlspecialchars($s['mapper_name'] ?? '—') ?>
                    </td>
                    <td class="text-xs text-muted">
                        <?= date('d M Y', strtotime($s['created_at'])) ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <!-- View card -->
                            <a href="<?= shareUrl($s) ?>"
                               target="_blank"
                               class="btn btn-ghost btn-sm btn-icon"
                               title="View address card">
                                <i class="fas fa-eye"></i>
                            </a>
                            <!-- Map flyto -->
                            <a href="<?= appUrl() ?>/views/map.php?flyto=<?= urlencode($s['address_code']) ?>"
                               class="btn btn-ghost btn-sm btn-icon"
                               title="View on map">
                                <i class="fas fa-map-location-dot"></i>
                            </a>
                            <?php if (isAdmin()): ?>
                            <?php if ($s['status'] === 'pending'): ?>
                            <a href="?verify=<?= $s['id'] ?>&<?= http_build_query(array_filter($filters)) ?>"
                               class="btn btn-success btn-sm btn-icon"
                               title="Verify structure">
                                <i class="fas fa-check"></i>
                            </a>
                            <button class="btn btn-danger btn-sm btn-icon"
                                    onclick="openRejectModal(<?= $s['id'] ?>)"
                                    title="Reject structure">
                                <i class="fas fa-xmark"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($s['status'] === 'verified'): ?>
                            <a href="?archive=<?= $s['id'] ?>"
                               class="btn btn-ghost btn-sm btn-icon"
                               data-confirm="Archive this structure?"
                               title="Archive">
                                <i class="fas fa-box-archive"></i>
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($data['last_page'] > 1): ?>
    <div class="card-footer">
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($filters,['page'=>$page-1])) ?>"
               class="page-btn"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($data['last_page'], $page + 2);
            if ($start > 1): ?>
            <a href="?<?= http_build_query(array_merge($filters,['page'=>1])) ?>"
               class="page-btn">1</a>
            <?php if ($start > 2): ?><span class="text-muted" style="padding:0 4px">…</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($p = $start; $p <= $end; $p++): ?>
            <a href="?<?= http_build_query(array_merge($filters,['page'=>$p])) ?>"
               class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($end < $data['last_page']): ?>
            <?php if ($end < $data['last_page'] - 1): ?><span class="text-muted" style="padding:0 4px">…</span><?php endif; ?>
            <a href="?<?= http_build_query(array_merge($filters,['page'=>$data['last_page']])) ?>"
               class="page-btn"><?= $data['last_page'] ?></a>
            <?php endif; ?>
            <?php if ($page < $data['last_page']): ?>
            <a href="?<?= http_build_query(array_merge($filters,['page'=>$page+1])) ?>"
               class="page-btn"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <span class="text-muted text-xs" style="margin-left:auto">
            <?= number_format(($page-1)*$perPage + 1) ?>–<?= number_format(min($page*$perPage,$data['total'])) ?>
            of <?= number_format($data['total']) ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<!-- Reject modal -->
<div class="modal-overlay" id="modal-reject">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-xmark-circle"></i> Reject Structure</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="reject_id" id="reject-id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Reason for rejection</label>
                    <textarea name="reason" class="form-control" required rows="3"
                              placeholder="Describe why this structure is being rejected…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-xmark"></i> Reject
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__.'/../partials/footer.php'; ?>
<script>
function openRejectModal(id) {
    document.getElementById('reject-id').value = id;
    openModal('modal-reject');
}
</script>
