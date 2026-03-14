<?php
// ============================================================
// docs/api.php  — Interactive API documentation
// ============================================================
require_once __DIR__.'/../config/app.php';
startSession();
$isLoggedIn = !empty($_SESSION['user_id']);
$pageTitle  = 'API Documentation';
require_once __DIR__.'/../views/partials/head.php';
?>
<div class="app-wrapper">
<?php if ($isLoggedIn): require_once __DIR__.'/../views/partials/sidebar.php'; endif; ?>
<div class="main-content <?= !$isLoggedIn?'':''; ?>" id="main-content"
     style="<?= !$isLoggedIn?'margin-left:0':'' ?>">
<?php if ($isLoggedIn): require_once __DIR__.'/../views/partials/topbar.php'; endif; ?>

<?php if (!$isLoggedIn): ?>
<div style="background:var(--c-darkest);padding:20px 32px;display:flex;align-items:center;gap:12px">
    <div style="width:32px;height:32px;background:var(--c-mid);border-radius:5px;
                display:flex;align-items:center;justify-content:center">
        <i class="fas fa-map-pin" style="color:#fff;font-size:14px"></i>
    </div>
    <span style="color:#fff;font-weight:700;font-size:1rem"><?= htmlspecialchars(appName()) ?></span>
    <span style="color:rgba(255,255,255,.4);font-size:.85rem">/ API Documentation</span>
    <div style="margin-left:auto">
        <a href="<?= appUrl() ?>/views/login.php" class="btn btn-accent btn-sm">Sign in</a>
    </div>
</div>
<?php endif; ?>

<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>REST API Documentation</h1>
        <p>Integration guide for delivery services, emergency responders, and third-party applications</p>
    </div>
    <div class="page-header-actions">
        <?php if ($isLoggedIn): ?>
        <a href="<?= appUrl() ?>/views/admin/api_keys.php" class="btn btn-accent">
            <i class="fas fa-key"></i> Manage API Keys
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Base URL -->
<div class="card mb-3">
    <div class="card-header">
        <div class="card-header-left">
            <div class="card-header-icon"><i class="fas fa-globe"></i></div>
            <div class="card-title">Base URL &amp; Authentication</div>
        </div>
    </div>
    <div class="card-body">
        <div class="form-group">
            <label class="form-label">Base URL</label>
            <div class="d-flex gap-2">
                <code id="base-url-code" style="flex:1;background:var(--c-darkest);color:#4a8aa8;
                      padding:10px 14px;border-radius:5px;font-family:var(--font-mono);font-size:.88rem;
                      display:block"><?= htmlspecialchars(appUrl()) ?>/api/</code>
                <button onclick="copyToClipboard('<?= addslashes(appUrl()) ?>/api/',this)"
                        class="btn btn-ghost btn-sm"><i class="fas fa-copy"></i></button>
            </div>
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Authentication Header</label>
            <pre style="background:var(--c-darkest);color:#6aafcc;padding:12px 16px;
                 border-radius:5px;font-family:var(--font-mono);font-size:.82rem;margin:0;line-height:1.8">X-NEDAMS-Key: nk_your_api_key_here
X-Requester: Organisation Name   (optional, for logging)</pre>
        </div>
    </div>
</div>

<!-- Endpoint cards -->
<?php
$endpoints = [
    [
        'method'=>'GET','path'=>'/api/get_coordinates.php','auth'=>'Key or public',
        'title'=>'Resolve Address → GPS',
        'desc'=>'Look up the GPS coordinates and full details for a NEDAMS address code. Used by delivery apps, mapping tools, and emergency dispatch.',
        'params'=>[
            ['code','required','string','NEDAMS address code e.g. NE4K7X'],
            ['purpose','optional','enum','delivery|emergency|visit|survey|other (logged for analytics)'],
            ['requester','optional','string','Requester name (logged for audit)'],
            ['org','optional','string','Organisation name (logged for analytics)'],
        ],
        'example_req'=>appUrl().'/api/get_coordinates.php?code=NE4K7X&purpose=delivery&org=Jumia',
        'example_res'=>'{"success":true,"address_code":"NE4K7X",
  "coordinates":{"latitude":0.34761230,"longitude":32.61524560},
  "resident":{"name":"John Mukasa","phone":"+256712345678"},
  "location":{"description":"Blue gate near water point","zone":"Kireka B"},
  "confidence_score":95,
  "navigation":{
    "google_maps":"https://www.google.com/maps?q=0.34761230,32.61524560",
    "directions":"https://www.google.com/maps/dir/?api=1&destination=..."
  },
  "meta":{"response_ms":42}}',
    ],
    [
        'method'=>'GET','path'=>'/api/search_location.php','auth'=>'Public',
        'title'=>'Search Structures',
        'desc'=>'Full-text search across address codes, names, zones, and descriptions.',
        'params'=>[
            ['q','required','string','Search term (min 2 chars)'],
        ],
        'example_req'=>appUrl().'/api/search_location.php?q=NE4K7X',
        'example_res'=>'{"success":true,"count":1,"query":"NE4K7X",
  "results":[{"address_code":"NE4K7X","latitude":0.34761,"longitude":32.61524,
    "resident_name":"John Mukasa","zone":"Kireka B","status":"verified",
    "confidence_score":95,"share_url":"..."}]}',
    ],
    [
        'method'=>'GET','path'=>'/api/get_markers.php','auth'=>'Public',
        'title'=>'Map Markers (Viewport)',
        'desc'=>'Returns all structure pins within a geographic bounding box. Used for map rendering.',
        'params'=>[
            ['swLat','required','float','South-west latitude bound'],
            ['swLng','required','float','South-west longitude bound'],
            ['neLat','required','float','North-east latitude bound'],
            ['neLng','required','float','North-east longitude bound'],
        ],
        'example_req'=>appUrl().'/api/get_markers.php?swLat=0.34&swLng=32.60&neLat=0.36&neLng=32.63',
        'example_res'=>'{"success":true,"count":3,
  "markers":[{"code":"NE4K7X","lat":0.34761,"lng":32.61524,
    "name":"John Mukasa","type":"residential","status":"verified","confidence":95},...]}',
    ],
    [
        'method'=>'POST','path'=>'/api/register_location.php','auth'=>'Key (write)',
        'title'=>'Register Structure',
        'desc'=>'Create a new structure and receive a NEDAMS digital address code. Requires write-permission API key.',
        'params'=>[
            ['latitude','required','float','GPS latitude'],
            ['longitude','required','float','GPS longitude'],
            ['resident_name','required','string','Occupant full name'],
            ['phone','optional','string','Phone number'],
            ['zone','optional','string','Zone name'],
            ['description','optional','string','Description / landmarks'],
            ['structure_type','optional','enum','residential|commercial|school|clinic|worship|government|other'],
            ['accuracy_meters','optional','float','GPS accuracy (from browser geolocation)'],
        ],
        'example_req'=>'POST '.appUrl().'/api/register_location.php
Content-Type: application/json
X-NEDAMS-Key: nk_your_key_here

{
  "latitude": 0.34761,
  "longitude": 32.61524,
  "resident_name": "Grace Nambi",
  "phone": "+256756789012",
  "zone": "Kireka B",
  "structure_type": "residential",
  "accuracy_meters": 5.2
}',
        'example_res'=>'{"success":true,"message":"Structure registered successfully.",
  "address_code":"NE9X2K","id":42,"status":"pending",
  "share_url":"'.appUrl().'/s/a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4",
  "whatsapp_url":"https://wa.me/?text=..."}',
    ],
];
foreach ($endpoints as $ep):
    $methodColor = $ep['method']==='POST' ? 'var(--success)' : 'var(--c-mid)';
?>
<div class="card mb-3">
    <div class="card-header">
        <div class="card-header-left" style="gap:12px">
            <span style="background:<?= $methodColor ?>;color:#fff;font-family:var(--font-mono);
                   font-size:.72rem;font-weight:700;padding:3px 8px;border-radius:3px">
                <?= $ep['method'] ?>
            </span>
            <code style="font-family:var(--font-mono);font-size:.85rem;color:var(--c-dark)">
                <?= htmlspecialchars($ep['path']) ?>
            </code>
            <span class="badge badge-dark"><?= htmlspecialchars($ep['auth']) ?></span>
        </div>
        <span style="font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($ep['title']) ?></span>
    </div>
    <div class="card-body">
        <p class="text-sm text-muted mb-3"><?= htmlspecialchars($ep['desc']) ?></p>

        <!-- Parameters table -->
        <div class="table-responsive mb-3">
            <table class="table table-sm">
                <thead><tr><th>Parameter</th><th>Required</th><th>Type</th><th>Description</th></tr></thead>
                <tbody>
                    <?php foreach ($ep['params'] as $p): ?>
                    <tr>
                        <td><code style="font-family:var(--font-mono);font-size:.8rem"><?= htmlspecialchars($p[0]) ?></code></td>
                        <td><span class="badge <?= $p[1]==='required'?'badge-danger':'badge-dark' ?>"><?= $p[1] ?></span></td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($p[2]) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($p[3]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Example -->
        <div class="grid-2" style="gap:12px">
            <div>
                <div class="text-xs fw-600 text-muted mb-1">REQUEST</div>
                <pre style="background:var(--c-darkest);color:#315d77;padding:12px 14px;
                     border-radius:5px;font-family:var(--font-mono);font-size:.74rem;
                     overflow-x:auto;white-space:pre-wrap;line-height:1.7;margin:0"><?= htmlspecialchars($ep['example_req']) ?></pre>
            </div>
            <div>
                <div class="text-xs fw-600 text-muted mb-1">RESPONSE <span class="badge badge-success">200</span></div>
                <pre style="background:var(--c-darkest);color:#4a8aa8;padding:12px 14px;
                     border-radius:5px;font-family:var(--font-mono);font-size:.74rem;
                     overflow-x:auto;white-space:pre-wrap;line-height:1.7;margin:0"><?= htmlspecialchars($ep['example_res']) ?></pre>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Error codes -->
<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <div class="card-header-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="card-title">HTTP Status Codes</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Code</th><th>Meaning</th><th>Common Cause</th></tr></thead>
            <tbody>
                <?php $codes=[
                    ['200','OK','Request succeeded'],
                    ['201','Created','Structure registered successfully'],
                    ['204','No Content','OPTIONS preflight response'],
                    ['401','Unauthorized','Missing or invalid X-NEDAMS-Key header'],
                    ['403','Forbidden','API key lacks required permissions (e.g., write)'],
                    ['404','Not Found','Address code not in database'],
                    ['405','Method Not Allowed','Wrong HTTP method used'],
                    ['422','Unprocessable','Invalid parameters (missing lat/lng, invalid code format)'],
                    ['500','Server Error','Internal error — contact admin'],
                ];
                foreach ($codes as [$code,$meaning,$cause]):
                    $color = (int)$code<300?'badge-success':((int)$code<400?'badge-info':((int)$code<500?'badge-warning':'badge-danger'));
                ?>
                <tr>
                    <td><span class="badge <?= $color ?>"><?= $code ?></span></td>
                    <td class="fw-600 text-sm"><?= htmlspecialchars($meaning) ?></td>
                    <td class="text-sm text-muted"><?= htmlspecialchars($cause) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__.'/../views/partials/footer.php'; ?>
