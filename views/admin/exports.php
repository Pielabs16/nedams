<?php
// ============================================================
// views/admin/exports.php  — v2.1 fixed CSV accuracy
// Headers exactly match SELECT column order
// ============================================================
require_once __DIR__.'/../../config/app.php';
requireRole('super_admin','admin');

$pdo = getDB();
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $type = $_POST['export_type'] ?? 'structures';

    // Each case: headers EXACTLY match SELECT column order, 1-to-1
    switch ($type) {

        case 'structures':
            $sql = 'SELECT
                      address_code,
                      resident_name,
                      phone,
                      email,
                      latitude,
                      longitude,
                      zone,
                      parish,
                      division,
                      structure_type,
                      floor_count,
                      occupant_count,
                      description,
                      landmarks,
                      confidence_score,
                      accuracy_meters,
                      status,
                      view_count,
                      DATE_FORMAT(created_at, "%Y-%m-%d %H:%i:%s") AS created_at
                    FROM structures
                    ORDER BY created_at DESC';
            $headers = [
                'Address Code',
                'Resident Name',
                'Phone',
                'Email',
                'Latitude',
                'Longitude',
                'Zone',
                'Parish',
                'Division',
                'Structure Type',
                'Floors',
                'Occupants',
                'Description',
                'Landmarks',
                'GPS Confidence (%)',
                'GPS Accuracy (m)',
                'Status',
                'View Count',
                'Registered At',
            ];
            break;

        case 'users':
            if (!isSuperAdmin()) { http_response_code(403); exit; }
            $sql = 'SELECT
                      full_name,
                      email,
                      phone,
                      role,
                      CASE is_active       WHEN 1 THEN "Yes" ELSE "No" END AS is_active,
                      CASE email_verified  WHEN 1 THEN "Yes" ELSE "No" END AS email_verified,
                      DATE_FORMAT(last_login,  "%Y-%m-%d %H:%i:%s") AS last_login,
                      login_count,
                      DATE_FORMAT(created_at,  "%Y-%m-%d %H:%i:%s") AS created_at
                    FROM users
                    ORDER BY created_at DESC';
            $headers = [
                'Full Name',
                'Email',
                'Phone',
                'Role',
                'Active',
                'Email Verified',
                'Last Login',
                'Login Count',
                'Created At',
            ];
            break;

        case 'service_requests':
            $sql = 'SELECT
                      r.address_code,
                      r.requester_name,
                      r.requester_org,
                      r.requester_phone,
                      r.purpose,
                      r.response_code,
                      r.response_ms,
                      r.country,
                      r.ip_address,
                      k.name AS api_key_name,
                      DATE_FORMAT(r.created_at, "%Y-%m-%d %H:%i:%s") AS created_at
                    FROM service_requests r
                    LEFT JOIN api_keys k ON k.id = r.api_key_id
                    ORDER BY r.created_at DESC
                    LIMIT 50000';
            $headers = [
                'Address Code',
                'Requester Name',
                'Organisation',
                'Phone',
                'Purpose',
                'HTTP Response Code',
                'Response Time (ms)',
                'Country',
                'IP Address',
                'API Key Name',
                'Date',
            ];
            break;

        case 'audit_log':
            if (!isSuperAdmin()) { http_response_code(403); exit; }
            $sql = 'SELECT
                      user_email,
                      action,
                      module,
                      target_type,
                      target_id,
                      description,
                      ip_address,
                      user_agent,
                      DATE_FORMAT(created_at, "%Y-%m-%d %H:%i:%s") AS created_at
                    FROM audit_log
                    ORDER BY created_at DESC
                    LIMIT 50000';
            $headers = [
                'User Email',
                'Action',
                'Module',
                'Target Type',
                'Target ID',
                'Description',
                'IP Address',
                'User Agent',
                'Date',
            ];
            break;

        default:
            header('Location: exports.php'); exit;
    }

    $rows     = $pdo->query($sql)->fetchAll(PDO::FETCH_NUM);
    $filename = 'nedams_'.$type.'_'.date('Ymd_His').'.csv';

    // Log the export
    $pdo->prepare(
        'INSERT INTO export_log(exported_by, export_type, row_count, filename, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$uid, $type, count($rows), $filename, $_SERVER['REMOTE_ADDR'] ?? null]);
    auditLog('export', 'exports', $type, '', 'Exported '.count($rows).' rows — '.$filename);

    // Stream CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: no-cache, no-store');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// ---- Recent exports log -------------------------------------
$recentExports = $pdo->query('
    SELECT e.*, u.full_name AS exporter_name
    FROM export_log e
    LEFT JOIN users u ON u.id = e.exported_by
    ORDER BY e.created_at DESC
    LIMIT 20
')->fetchAll();

$pageTitle = 'Export Data';
require_once __DIR__.'/../partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/../partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/../partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Export Data</h1>
        <p>Generate CSV files — headers exactly match database fields</p>
    </div>
</div>

<div id="flash-zone"></div>

<div class="grid-2 mb-3">

    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-file-csv"></i></div>
                <div class="card-title">Generate Export</div>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" id="export-form">
                <div class="form-group">
                    <label class="form-label">Export Type</label>
                    <select name="export_type" class="form-control" id="export-type"
                            onchange="updateNote(this.value)">
                        <option value="structures">Structures (addresses + GPS)</option>
                        <option value="service_requests">Service Requests (API call log)</option>
                        <?php if (isSuperAdmin()): ?>
                        <option value="users">Users (account data)</option>
                        <option value="audit_log">Audit Log</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div id="export-note" class="alert alert-info mb-3" style="font-size:.82rem">
                    <i class="fas fa-info-circle"></i>
                    <div id="export-note-text">
                        Exports all structures with GPS coordinates, zone, resident details
                        and confidence scores. 19 columns, suitable for GIS and research.
                    </div>
                </div>

                <button type="submit" name="export" class="btn btn-primary btn-block"
                        onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Preparing…'">
                    <i class="fas fa-download"></i> Download CSV
                </button>
            </form>

            <hr class="divider">
            <div class="text-sm text-muted">
                <i class="fas fa-shield-halved"></i>
                All exports are logged with your name, timestamp, and row count.
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-history"></i></div>
                <div class="card-title">Recent Exports</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>Date</th><th>By</th><th>Type</th><th>Rows</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentExports as $ex): ?>
                    <tr>
                        <td class="text-xs text-muted">
                            <?= date('d M Y H:i', strtotime($ex['created_at'])) ?>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($ex['exporter_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge-info text-xs">
                                <?= htmlspecialchars($ex['export_type']) ?>
                            </span>
                        </td>
                        <td class="text-sm"><?= number_format($ex['row_count']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentExports): ?>
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">
                        No exports yet.
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__.'/../partials/footer.php'; ?>
<script>
const notes = {
    structures: 'Exports all structures with GPS, zone, resident details and confidence score. 19 columns — suitable for GIS, model training and research.',
    service_requests: 'Exports API lookup history with requester, organisation, response times and IP. 11 columns.',
    users: 'User account data including role, verification status and login history. Handle with care — contains personal data. 9 columns.',
    audit_log: 'Complete system activity trail with action, module, user and IP. 9 columns. Suitable for security compliance.',
};
function updateNote(v) {
    document.getElementById('export-note-text').textContent = notes[v] || '';
}
</script>
