<?php
// ============================================================
// views/admin/analytics.php  — Full analytics dashboard
// ============================================================
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../models/Structure.php';
requireAdmin();

$pdo = getDB();

// 30-day and 90-day registrations
$reg30  = Structure::registrationsByDay(30);
$reg90  = Structure::registrationsByDay(90);
$byType = Structure::byType();
$byZone = Structure::byZone(10);

// API calls by day (30d)
$apiTrend = $pdo->query('
    SELECT DATE(created_at) AS day, COUNT(*) AS count
    FROM service_requests WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
    GROUP BY DATE(created_at) ORDER BY day ASC')->fetchAll();

// API calls by purpose
$byPurpose = $pdo->query('
    SELECT purpose AS label, COUNT(*) AS value
    FROM service_requests GROUP BY purpose ORDER BY value DESC')->fetchAll();

// API calls by organisation (top 10)
$byOrg = $pdo->query('
    SELECT COALESCE(requester_org, requester_name, "Unknown") AS org,
           COUNT(*) AS calls, AVG(response_ms) AS avg_ms
    FROM service_requests WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
    GROUP BY org ORDER BY calls DESC LIMIT 10')->fetchAll();

// Hourly heatmap data (last 30 days)
$hourly = $pdo->query('
    SELECT HOUR(created_at) AS hr, DAYOFWEEK(created_at) AS dow, COUNT(*) AS cnt
    FROM structures WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
    GROUP BY hr, dow')->fetchAll();

// Monthly growth
$monthly = $pdo->query('
    SELECT DATE_FORMAT(created_at,"%Y-%m") AS month, COUNT(*) AS count
    FROM structures GROUP BY month ORDER BY month DESC LIMIT 12')->fetchAll();
$monthly = array_reverse($monthly);

// Verification stats
$verif = $pdo->query('
    SELECT
        SUM(status="pending")  AS pending,
        SUM(status="verified") AS verified,
        SUM(status="rejected") AS rejected,
        SUM(status="archived") AS archived,
        AVG(CASE WHEN verified_at IS NOT NULL
            THEN TIMESTAMPDIFF(HOUR,created_at,verified_at) END) AS avg_verify_hours
    FROM structures')->fetch();

// Response time percentiles
$respTime = $pdo->query('
    SELECT
        MIN(response_ms)  AS min_ms,
        AVG(response_ms)  AS avg_ms,
        MAX(response_ms)  AS max_ms
    FROM service_requests WHERE response_ms IS NOT NULL')->fetch();

// Confidence distribution
$confDist = $pdo->query('
    SELECT
        SUM(confidence_score>=90) AS high,
        SUM(confidence_score>=65 AND confidence_score<90) AS medium,
        SUM(confidence_score<65) AS low
    FROM structures')->fetch();

$pageTitle = 'Analytics';
$inlineJs  = 'initAnalyticsCharts('.
    json_encode($reg30).', '.
    json_encode($byType).', '.
    json_encode($byZone).', '.
    json_encode($apiTrend).
    '); initExtendedCharts('.
    json_encode($monthly).', '.
    json_encode($byPurpose).', '.
    json_encode($confDist).
    ');';

require_once __DIR__.'/../partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/../partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/../partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Analytics</h1>
        <p>System-wide metrics, trends, and usage data</p>
    </div>
    <div class="page-header-actions">
        <span class="text-muted text-sm"><i class="fas fa-clock"></i> Updated <?= date('H:i, d M Y') ?></span>
    </div>
</div>

<!-- KPI Row -->
<div class="stat-grid mb-3">
    <div class="stat-card stat-info">
        <div class="stat-card-left">
            <div class="stat-value"><?= number_format((int)$pdo->query('SELECT COUNT(*) FROM structures')->fetchColumn()) ?></div>
            <div class="stat-label">Total Structures</div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-building"></i></div></div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-card-left">
            <div class="stat-value"><?= number_format((int)$verif['verified']) ?></div>
            <div class="stat-label">Verified</div>
            <div class="stat-change up"><?= $verif['avg_verify_hours'] ? round($verif['avg_verify_hours']).'h avg verify time' : '' ?></div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-circle-check"></i></div></div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-card-left">
            <div class="stat-value"><?= number_format((int)$verif['pending']) ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-left">
            <div class="stat-value"><?= number_format((int)$pdo->query('SELECT COUNT(*) FROM service_requests')->fetchColumn()) ?></div>
            <div class="stat-label">Total API Calls</div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-plug"></i></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-left">
            <div class="stat-value"><?= $respTime['avg_ms'] ? round($respTime['avg_ms']).'ms' : '—' ?></div>
            <div class="stat-label">Avg API Response</div>
            <div class="stat-change" style="color:var(--text-muted);font-size:.72rem">
                min <?= $respTime['min_ms']??'—' ?>ms · max <?= $respTime['max_ms']??'—' ?>ms
            </div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-gauge-high"></i></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-left">
            <div class="stat-value"><?= $confDist['high'] ?? 0 ?></div>
            <div class="stat-label">High Confidence (&ge;90%)</div>
        </div>
        <div class="stat-card-right"><div class="stat-icon"><i class="fas fa-location-crosshairs"></i></div></div>
    </div>
</div>

<!-- Row 1: 30-day registrations + monthly growth -->
<div class="grid-2 mb-3">
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-chart-column"></i></div>
                <div><div class="card-title">Daily Registrations (30 days)</div></div>
            </div>
        </div>
        <div class="card-body"><div class="chart-wrap" style="height:220px">
            <canvas id="chart-registrations"></canvas>
        </div></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-chart-line"></i></div>
                <div><div class="card-title">Monthly Growth</div></div>
            </div>
        </div>
        <div class="card-body"><div class="chart-wrap" style="height:220px">
            <canvas id="chart-monthly"></canvas>
        </div></div>
    </div>
</div>

<!-- Row 2: Type doughnut + Zone bar + API pie -->
<div class="grid-3 mb-3">
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="card-title">By Structure Type</div>
            </div>
        </div>
        <div class="card-body"><div class="chart-wrap" style="height:200px">
            <canvas id="chart-types"></canvas>
        </div></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-layer-group"></i></div>
                <div class="card-title">Top Zones</div>
            </div>
        </div>
        <div class="card-body"><div class="chart-wrap" style="height:200px">
            <canvas id="chart-zones"></canvas>
        </div></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-truck"></i></div>
                <div class="card-title">API Calls by Purpose</div>
            </div>
        </div>
        <div class="card-body"><div class="chart-wrap" style="height:200px">
            <canvas id="chart-purpose"></canvas>
        </div></div>
    </div>
</div>

<!-- Row 3: API trend + Confidence distribution -->
<div class="grid-65 mb-3">
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-signal"></i></div>
                <div class="card-title">API Activity (30 days)</div>
            </div>
        </div>
        <div class="card-body"><div class="chart-wrap" style="height:200px">
            <canvas id="chart-api"></canvas>
        </div></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-crosshairs"></i></div>
                <div class="card-title">GPS Confidence</div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:200px">
                <canvas id="chart-confidence"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Row 4: Top API Consumers table -->
<div class="card mb-3">
    <div class="card-header">
        <div class="card-header-left">
            <div class="card-header-icon"><i class="fas fa-trophy"></i></div>
            <div class="card-title">Top API Consumers (last 30 days)</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Organisation</th><th>Calls</th><th>Avg Response</th><th>Share</th></tr>
            </thead>
            <tbody>
                <?php
                $totalCalls = array_sum(array_column($byOrg,'calls')) ?: 1;
                foreach ($byOrg as $org): ?>
                <tr>
                    <td class="fw-600"><?= htmlspecialchars($org['org']) ?></td>
                    <td><?= number_format($org['calls']) ?></td>
                    <td class="text-muted text-sm">
                        <?= $org['avg_ms'] ? round($org['avg_ms']).'ms' : '—' ?>
                    </td>
                    <td style="min-width:120px">
                        <?php $pct = round($org['calls']/$totalCalls*100); ?>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div style="flex:1;height:6px;background:var(--border);border-radius:3px;overflow:hidden">
                                <div style="height:100%;width:<?= $pct ?>%;background:var(--c-mid);border-radius:3px"></div>
                            </div>
                            <span class="text-xs text-muted"><?= $pct ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$byOrg): ?>
                <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">
                    No API requests recorded yet.
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Row 5: Verification funnel -->
<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <div class="card-header-icon"><i class="fas fa-filter"></i></div>
            <div class="card-title">Verification Funnel</div>
        </div>
    </div>
    <div class="card-body">
        <?php
        $total = max(1, (int)$pdo->query('SELECT COUNT(*) FROM structures')->fetchColumn());
        $funnel = [
            ['Registered', $total,               'var(--c-mid)'],
            ['Pending',    (int)$verif['pending'],   'var(--warning)'],
            ['Verified',   (int)$verif['verified'],  'var(--success)'],
            ['Rejected',   (int)$verif['rejected'],  'var(--danger)'],
        ];
        foreach ($funnel as [$label,$val,$color]):
            $w = round($val/$total*100);
        ?>
        <div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span class="text-sm fw-600"><?= $label ?></span>
                <span class="text-sm text-muted"><?= number_format($val) ?> (<?= $w ?>%)</span>
            </div>
            <div style="height:10px;background:var(--border);border-radius:5px;overflow:hidden">
                <div style="height:100%;width:<?= $w ?>%;background:<?= $color ?>;border-radius:5px;transition:width .8s ease"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__.'/../partials/footer.php'; ?>
<script>
function initExtendedCharts(monthly, byPurpose, confDist) {
    const baseOpts = {
        responsive:true, maintainAspectRatio:false,
        plugins:{legend:{labels:{font:{family:'IBM Plex Sans',size:11},color:'#4a6072',boxWidth:10}}}
    };

    // Monthly growth line
    const mCtx = document.getElementById('chart-monthly');
    if (mCtx && monthly.length) {
        new Chart(mCtx, {
            type:'line',
            data:{
                labels: monthly.map(m=>m.month),
                datasets:[{
                    label:'Structures',
                    data: monthly.map(m=>m.count),
                    borderColor:'#103a54',
                    backgroundColor:'rgba(16,58,84,.08)',
                    fill:true, tension:.35, borderWidth:2,
                    pointBackgroundColor:'#103a54', pointRadius:4
                }]
            },
            options:{...baseOpts,
                scales:{
                    x:{grid:{display:false},ticks:{font:{size:10},color:'#8096a7'}},
                    y:{grid:{color:'#f0f4f7'},ticks:{precision:0,font:{size:10},color:'#8096a7'}}
                }
            }
        });
    }

    // API purpose doughnut
    const pCtx = document.getElementById('chart-purpose');
    if (pCtx && byPurpose.length) {
        new Chart(pCtx, {
            type:'doughnut',
            data:{
                labels: byPurpose.map(p=>p.label),
                datasets:[{
                    data: byPurpose.map(p=>p.value),
                    backgroundColor:['#315d77','#103a54','#071c2c','#4a8aa8','#6aafcc'],
                    borderWidth:2, borderColor:'#fff'
                }]
            },
            options:{...baseOpts, cutout:'60%',
                plugins:{...baseOpts.plugins, legend:{...baseOpts.plugins.legend,position:'bottom'}}}
        });
    }

    // Confidence doughnut
    const cCtx = document.getElementById('chart-confidence');
    if (cCtx && confDist) {
        new Chart(cCtx, {
            type:'doughnut',
            data:{
                labels:['High (>=90%)','Medium (65-89%)','Low (<65%)'],
                datasets:[{
                    data:[confDist.high||0, confDist.medium||0, confDist.low||0],
                    backgroundColor:['#0d7c4a','#a05c00','#b91c1c'],
                    borderWidth:2, borderColor:'#fff'
                }]
            },
            options:{...baseOpts, cutout:'60%',
                plugins:{...baseOpts.plugins, legend:{...baseOpts.plugins.legend,position:'bottom'}}}
        });
    }
}
</script>
