<?php
// ============================================================
// views/dashboard.php  — Main analytics dashboard
// ============================================================
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../models/Structure.php';
require_once __DIR__.'/../models/User.php';
requireLogin();

$stats        = Structure::dashboardStats();
$byType       = Structure::byType();
$byZone       = Structure::byZone(8);
$registrations= Structure::registrationsByDay(30);

// API calls by day
try {
    $apiTrend = getDB()->query('
        SELECT DATE(created_at) AS day, COUNT(*) AS count
        FROM service_requests WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
        GROUP BY DATE(created_at) ORDER BY day ASC'
    )->fetchAll();
} catch(Throwable $e) { $apiTrend=[]; }

// Recent structures
$recent = Structure::all(1,8)['data'];

// Recent service requests
try {
    $recentReq = getDB()->query('
        SELECT address_code,requester_name,requester_org,purpose,response_code,created_at
        FROM service_requests ORDER BY created_at DESC LIMIT 6'
    )->fetchAll();
} catch(Throwable $e) { $recentReq=[]; }

$pageTitle   = 'Dashboard';
$loadGoogleMaps = false; // no map on dashboard for speed
$inlineJs    = 'initAnalyticsCharts('.
    json_encode($registrations).', '.
    json_encode($byType).', '.
    json_encode($byZone).', '.
    json_encode($apiTrend).');';

require_once __DIR__.'/partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Dashboard</h1>
        <p>System overview and key metrics — <?= date('l, d F Y') ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= appUrl() ?>/views/register.php" class="btn btn-accent">
            <i class="fas fa-plus"></i> Register Structure
        </a>
        <a href="<?= appUrl() ?>/views/admin/analytics.php" class="btn btn-ghost">
            <i class="fas fa-chart-bar"></i> Full Analytics
        </a>
    </div>
</div>

<!-- ---- STAT TILES ---- -->
<div class="stat-grid">
    <div class="stat-card stat-info">
        <div class="stat-card-left">
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
            <div class="stat-label">Total Structures</div>
            <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?= $stats['this_week'] ?> this week</div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-building"></i></div></div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-card-left">
            <div class="stat-value"><?= number_format($stats['verified']) ?></div>
            <div class="stat-label">Verified</div>
            <div class="stat-change" style="color:var(--text-muted)">
                <?= $stats['total'] > 0 ? round($stats['verified']/$stats['total']*100) : 0 ?>% verified
            </div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-circle-check"></i></div></div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-card-left">
            <div class="stat-value"><?= number_format($stats['pending']) ?></div>
            <div class="stat-label">Pending Review</div>
            <?php if ($stats['pending']>0): ?>
            <div class="stat-change"><a href="<?= appUrl() ?>/views/admin/structures.php?status=pending"
                style="color:var(--warning);font-size:.76rem">Review now</a></div>
            <?php endif; ?>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
    </div>
    <div class="stat-card stat-dark">
        <div class="stat-card-left">
            <div class="stat-value"><?= number_format($stats['today']) ?></div>
            <div class="stat-label">Registered Today</div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-calendar-day"></i></div></div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-card-left">
            <div class="stat-value"><?= number_format($stats['api_calls']) ?></div>
            <div class="stat-label">API Lookups</div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-plug"></i></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-left">
            <div class="stat-value"><?= number_format($stats['users']) ?></div>
            <div class="stat-label">Active Mappers</div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-users"></i></div></div>
    </div>
</div>

<!-- ---- ROW 2: Charts ---- -->
<div class="grid-65 mb-3">
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-chart-column"></i></div>
                <div>
                    <div class="card-title">Registrations (30 days)</div>
                    <div class="card-subtitle">Daily structure registrations</div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:200px">
                <canvas id="chart-registrations"></canvas>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="card-title">By Structure Type</div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:200px">
                <canvas id="chart-types"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ---- ROW 3: Zone Chart + Recent Requests ---- -->
<div class="grid-2 mb-3">
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-layer-group"></i></div>
                <div class="card-title">Top Zones</div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:220px">
                <canvas id="chart-zones"></canvas>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-chart-line"></i></div>
                <div class="card-title">API Calls (30 days)</div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:220px">
                <canvas id="chart-api"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ---- ROW 4: Recent Structures + Recent Requests ---- -->
<div class="grid-2">

    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-building"></i></div>
                <div class="card-title">Recent Structures</div>
            </div>
            <a href="<?= appUrl() ?>/views/admin/structures.php" class="btn btn-ghost btn-sm">
                View all <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>Code</th><th>Resident</th><th>Zone</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $s): ?>
                    <tr>
                        <td>
                            <a href="<?= shareUrl($s) ?>"
                               target="_blank"
                               class="addr-code" style="text-decoration:none">
                               <?= htmlspecialchars($s['address_code']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($s['resident_name']) ?></td>
                        <td class="text-muted text-xs"><?= htmlspecialchars($s['zone']??'—') ?></td>
                        <td><span class="badge badge-<?= htmlspecialchars($s['status']) ?>"><?= htmlspecialchars($s['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-truck"></i></div>
                <div class="card-title">Recent Service Requests</div>
            </div>
            <a href="<?= appUrl() ?>/views/admin/service_requests.php" class="btn btn-ghost btn-sm">
                View all <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>Code</th><th>Organisation</th><th>Purpose</th><th>Time</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentReq as $r): ?>
                    <tr>
                        <td><span class="addr-code"><?= htmlspecialchars($r['address_code']) ?></span></td>
                        <td class="text-sm"><?= htmlspecialchars($r['requester_org']??$r['requester_name']??'—') ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($r['purpose']) ?></span></td>
                        <td class="text-xs text-muted"><?= date('H:i d/m', strtotime($r['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__.'/partials/footer.php'; ?>
