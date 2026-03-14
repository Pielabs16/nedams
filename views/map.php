<?php
// ============================================================
// views/map.php  — Full interactive live map
// ============================================================
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../models/Structure.php';
require_once __DIR__.'/../models/AddressGenerator.php';
requireLogin();

// Handle flyto from structures page
$flyLat = null; $flyLng = null;
$flyCode = strtoupper(trim($_GET['flyto'] ?? ''));
if ($flyCode && AddressGenerator::isValid($flyCode)) {
    $fs = Structure::findByCode($flyCode);
    if ($fs) { $flyLat = (float)$fs['latitude']; $flyLng = (float)$fs['longitude']; }
}

$pageTitle      = 'Live Map';
$loadGoogleMaps = true;
require_once __DIR__.'/partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/partials/topbar.php'; ?>
<div class="page-content" style="padding-bottom:0">

<div class="page-header">
    <div class="page-header-left">
        <h1>Live Map</h1>
        <p>All registered structures — pan and zoom to explore</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= appUrl() ?>/views/register.php" class="btn btn-accent">
            <i class="fas fa-plus"></i> Register Structure
        </a>
    </div>
</div>

<div class="card map-card" style="margin-bottom:0">
    <!-- Map controls bar -->
    <div class="map-controls">
        <form onsubmit="submitSearch(event)" class="d-flex gap-2" style="flex:1;max-width:400px">
            <input type="text" id="search-input" class="form-control" placeholder="Search address code or name...">
            <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass"></i></button>
        </form>
        <!-- Type legend -->
        <div class="d-flex gap-2 flex-wrap" style="margin-left:auto">
            <?php
            $legend = [
                ['residential','#315d77','Residential'],
                ['commercial','#a05c00','Commercial'],
                ['school','#1d4ed8','School'],
                ['clinic','#b91c1c','Clinic'],
                ['worship','#5b21b6','Worship'],
            ];
            foreach ($legend as [$type,$color,$label]):
            ?>
            <span style="display:flex;align-items:center;gap:5px;font-size:.76rem;color:var(--text-muted)">
                <span style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>;display:inline-block"></span>
                <?= $label ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Map fills remaining viewport height -->
    <div id="map" style="height:calc(100vh - 220px);min-height:400px;border-radius:0"></div>
</div>

<!-- Search results overlay -->
<div id="search-results" style="position:absolute;top:200px;left:280px;width:320px;
     z-index:50;max-height:60vh;overflow-y:auto"></div>

<?php require_once __DIR__.'/partials/footer.php'; ?>
<?php if ($flyLat): ?>
<script>
window.FLYTO_LAT = <?= $flyLat ?>;
window.FLYTO_LNG = <?= $flyLng ?>;
</script>
<?php endif; ?>
