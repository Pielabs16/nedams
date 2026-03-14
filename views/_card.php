<?php
// ============================================================
// views/_card.php  — Public address card template
// Called by router.php — $s and $code are already resolved.
// The URL is /s/{token} — no internal paths exposed.
// ============================================================

$lat      = (float)$s['latitude'];
$lng      = (float)$s['longitude'];

// All URLs use the clean /s/{token} format
$cleanUrl = appUrl() . '/s/' . $s['share_token'];
$pdfUrl   = appUrl() . '/p/' . $s['share_token'];
$mapsUrl  = "https://www.google.com/maps?q={$lat},{$lng}";
$dirUrl   = "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}";
$waMsg    = urlencode("My NEDAMS digital address: {$code}. View here: {$cleanUrl}");
$waUrl    = "https://wa.me/?text={$waMsg}";
$appName  = appName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Open Graph — clean URL, no internal paths -->
    <meta property="og:type"        content="website">
    <meta property="og:title"       content="<?= htmlspecialchars($code) ?> — <?= htmlspecialchars($appName) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($s['resident_name']) ?>, <?= htmlspecialchars($s['zone'] ?? 'Nakawa') ?>, Kampala">
    <meta property="og:url"         content="<?= htmlspecialchars($cleanUrl) ?>">

    <title><?= htmlspecialchars($code) ?> — <?= htmlspecialchars($appName) ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= appUrl() ?>/assets/css/style.css">
    <link rel="icon" href="<?= appUrl() ?>/assets/img/favicon.svg" type="image/svg+xml">
</head>
<body style="background:var(--body-bg)">

<!-- Minimal topbar -->
<div style="background:var(--c-darkest);height:52px;display:flex;align-items:center;
            padding:0 20px;gap:12px;position:sticky;top:0;z-index:100">
    <a href="<?= appUrl() ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none">
        <div style="width:28px;height:28px;background:var(--c-mid);border-radius:5px;
                    display:flex;align-items:center;justify-content:center">
            <i class="fas fa-map-pin" style="color:#fff;font-size:12px"></i>
        </div>
        <span style="color:#fff;font-weight:700;font-size:.95rem"><?= htmlspecialchars($appName) ?></span>
    </a>
    <div style="margin-left:auto;display:flex;gap:8px">
        <a href="<?= appUrl() ?>/views/search.php"
           style="color:rgba(255,255,255,.55);font-size:.8rem;text-decoration:none;
                  border:1px solid rgba(255,255,255,.15);border-radius:4px;padding:4px 10px">
            <i class="fas fa-magnifying-glass"></i> Search
        </a>
    </div>
</div>

<div style="max-width:680px;margin:28px auto;padding:0 16px 48px">

    <div style="border-radius:var(--r-lg);overflow:hidden;
                box-shadow:var(--card-shadow-md);border:1px solid var(--card-border)">

        <!-- Header -->
        <div style="background:var(--c-darkest);padding:24px 28px;
                    display:flex;align-items:flex-start;justify-content:space-between;gap:16px">
            <div>
                <div style="font-size:.65rem;letter-spacing:.15em;text-transform:uppercase;
                            color:rgba(255,255,255,.38);margin-bottom:10px">
                    Digital Address Code
                </div>
                <div class="addr-code-lg"><?= htmlspecialchars($code) ?></div>
            </div>
            <div style="text-align:right;flex-shrink:0">
                <?php if ($s['status'] === 'verified'): ?>
                <span style="display:inline-flex;align-items:center;gap:6px;
                             background:rgba(13,124,74,.25);color:#6ee7b7;
                             font-size:.72rem;font-weight:700;padding:5px 12px;
                             border-radius:20px;border:1px solid rgba(13,124,74,.3)">
                    <i class="fas fa-circle-check"></i> Verified
                </span>
                <?php else: ?>
                <span class="badge badge-warning"><?= htmlspecialchars($s['status']) ?></span>
                <?php endif; ?>
                <div style="color:rgba(255,255,255,.28);font-size:.68rem;margin-top:8px">
                    <i class="fas fa-eye"></i> <?= number_format($s['view_count']) ?> views
                </div>
            </div>
        </div>

        <!-- Details -->
        <div style="background:#fff;padding:20px 28px">
            <?php
            $rows = [
                ['fa-user',       'Resident / Occupant', $s['resident_name']],
            ];
            if ($s['phone'])
                $rows[] = ['fa-phone',       'Phone',         '<a href="tel:'.htmlspecialchars($s['phone']).'">'.htmlspecialchars($s['phone']).'</a>'];
            if ($s['zone'])
                $rows[] = ['fa-layer-group', 'Zone / Area',   htmlspecialchars($s['zone']).($s['parish']?', '.htmlspecialchars($s['parish']):'')];
            if ($s['description'])
                $rows[] = ['fa-align-left',  'Description',   htmlspecialchars($s['description'])];
            if ($s['landmarks'])
                $rows[] = ['fa-signs-post',  'Landmarks',     htmlspecialchars($s['landmarks'])];
            $rows[] = ['fa-location-dot', 'GPS Coordinates',
                '<span class="coords-display">'.$lat.', '.$lng.'</span>'];
            $rows[] = ['fa-building', 'Structure Type',
                '<span style="text-transform:capitalize">'.htmlspecialchars($s['structure_type']).'</span>'];

            foreach ($rows as [$icon, $label, $value]):
            ?>
            <div class="addr-detail-row">
                <div class="addr-detail-icon"><i class="fas <?= $icon ?>"></i></div>
                <div>
                    <div class="addr-detail-label"><?= htmlspecialchars($label) ?></div>
                    <div class="addr-detail-value"><?= $value ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($s['confidence_score']): ?>
            <div class="addr-detail-row">
                <div class="addr-detail-icon"><i class="fas fa-crosshairs"></i></div>
                <div style="flex:1">
                    <div class="addr-detail-label">GPS Confidence</div>
                    <div class="addr-detail-value">
                        <?php $c=(int)$s['confidence_score']; $cls=$c>=90?'high':($c>=65?'medium':'low'); ?>
                        <div class="conf-bar">
                            <div class="conf-track" style="max-width:120px">
                                <div class="conf-fill <?= $cls ?>" style="width:<?= $c ?>%"></div>
                            </div>
                            <span><?= $c ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Map -->
        <?php if (gmapsKey()): ?>
        <div id="map" style="height:260px;border-top:1px solid var(--border)"></div>
        <?php else: ?>
        <div style="background:#f5f8fa;border-top:1px solid var(--border);
                    padding:14px;text-align:center">
            <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank"
               style="color:var(--c-mid);font-size:.84rem;text-decoration:none">
                <i class="fas fa-external-link-alt"></i>
                Open in Google Maps (<?= $lat ?>, <?= $lng ?>)
            </a>
        </div>
        <?php endif; ?>

        <!-- Action buttons -->
        <div style="background:#f5f8fa;padding:18px 28px;border-top:1px solid var(--border)">
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
                <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="btn btn-accent">
                    <i class="fab fa-whatsapp"></i> Share on WhatsApp
                </a>
                <a href="<?= htmlspecialchars($dirUrl) ?>" target="_blank" class="btn btn-primary">
                    <i class="fas fa-route"></i> Get Directions
                </a>
                <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" class="btn btn-ghost">
                    <i class="fas fa-file-pdf"></i> PDF Card
                </a>
                <button onclick="copyToClipboard('<?= addslashes($cleanUrl) ?>',this)"
                        class="btn btn-ghost">
                    <i class="fas fa-copy"></i> Copy Link
                </button>
            </div>

            <!-- Clean share URL display — token only, no internal path -->
            <div style="background:#fff;border-radius:5px;padding:9px 12px;
                        font-family:var(--font-mono);font-size:.74rem;color:var(--text-muted);
                        word-break:break-all;border:1px solid var(--border)">
                <i class="fas fa-link"></i> <?= htmlspecialchars($cleanUrl) ?>
            </div>

            <?php if ($s['mapper_name']): ?>
            <div style="margin-top:10px;font-size:.72rem;color:var(--text-muted)">
                <i class="fas fa-user-check"></i>
                Mapped by <?= htmlspecialchars($s['mapper_name']) ?>
                &middot; <?= date('d M Y', strtotime($s['created_at'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<footer style="text-align:center;padding:16px;font-size:.72rem;color:var(--text-muted)">
    <?= htmlspecialchars($appName) ?> v<?= NEDAMS_VERSION ?>
    &middot; Nakawa East, Kampala, Uganda
</footer>

<script src="<?= appUrl() ?>/assets/js/app.js"></script>
<script>
window.NEDAMS_BASE_URL = '<?= appUrl() ?>';
window.ADDR_LAT = <?= $lat ?>;
window.ADDR_LNG = <?= $lng ?>;
window.MAP_LAT  = <?= $lat ?>;
window.MAP_LNG  = <?= $lng ?>;
window.MAP_ZOOM = 17;
</script>
<?php if (gmapsKey()): ?>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(gmapsKey()) ?>&callback=initMap">
</script>
<?php endif; ?>
</body>
</html>
