<?php
// ============================================================
// api/generate_pdf.php  — Printable address card with QR code
// Called either directly (?code=NE4K7X) or via router.php
// where $s and $code are already resolved from a share token.
// QR code encodes the clean /s/{token} URL — no paths exposed.
// ============================================================

// If called via router.php, $s and $code are already set.
// If called directly with ?code=, resolve here.
if (!isset($s) || !isset($code)) {
    require_once __DIR__ . '/../config/app.php';
    require_once __DIR__ . '/../models/Structure.php';

    $code = strtoupper(trim($_GET['code'] ?? ''));
    if (!$code || !preg_match('/^[A-Z0-9]{4,16}$/', $code)) {
        http_response_code(400);
        die('Invalid address code.');
    }
    $s = Structure::findByCode($code);
    if (!$s) {
        http_response_code(404);
        die('Address not found: ' . htmlspecialchars($code));
    }
}

// Ensure share token exists
if (empty($s['share_token'])) {
    $newToken = bin2hex(random_bytes(16));
    getDB()->prepare('UPDATE structures SET share_token=? WHERE address_code=?')
           ->execute([$newToken, $code]);
    $s['share_token'] = $newToken;
}

$lat      = (float)$s['latitude'];
$lng      = (float)$s['longitude'];

// All URLs use clean token-based format — no internal paths
$cleanUrl = appUrl() . '/s/' . $s['share_token'];
$mapsUrl  = "https://www.google.com/maps?q={$lat},{$lng}";
$dirUrl   = "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}";
$today    = date(setting('general.date_format', 'd M Y'));
$appName  = appName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Address Card <?= htmlspecialchars($code) ?> — <?= htmlspecialchars($appName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap"
          rel="stylesheet">

    <!-- qrcode.js — pure JS QR generator, no external API call needed -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'IBM Plex Sans', Arial, sans-serif;
            background: #e8eef2;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            min-height: 100vh;
            padding: 28px 16px 48px;
        }

        /* ---- Card ---- */
        .card {
            width: 148mm;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 6px 28px rgba(0,0,0,.16);
            border: 1px solid #d8e2e9;
        }

        /* ---- Header ---- */
        .card-header {
            background: #071c2c;
            padding: 22px 26px 18px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }
        .brand-row {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 14px;
        }
        .brand-icon {
            width: 26px; height: 26px;
            background: #315d77;
            border-radius: 5px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .brand-icon svg { width: 13px; height: 13px; fill: #fff; }
        .brand-name { color: rgba(255,255,255,.88); font-size: .76rem; font-weight: 700; }
        .code-label {
            font-size: .58rem;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: rgba(255,255,255,.38);
            margin-bottom: 7px;
        }
        .code-value {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 2.1rem;
            font-weight: 500;
            letter-spacing: .16em;
            color: #fff;
            line-height: 1;
        }
        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(13,124,74,.2);
            border: 1px solid rgba(13,124,74,.3);
            color: #6ee7b7;
            font-size: .64rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            white-space: nowrap;
            margin-top: 4px;
        }

        /* ---- Body detail rows ---- */
        .card-body { padding: 16px 26px; }
        .detail-row {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f4f7;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-icon {
            width: 26px; height: 26px;
            background: #e8f2f8;
            border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .detail-icon svg { width: 11px; height: 11px; fill: #315d77; }
        .detail-label {
            font-size: .59rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #8096a7;
        }
        .detail-value {
            font-size: .8rem;
            font-weight: 600;
            color: #071c2c;
            margin-top: 2px;
            line-height: 1.35;
        }

        /* ---- Footer with QR ---- */
        .card-footer {
            background: #f5f8fa;
            border-top: 1px solid #e2eaee;
            padding: 16px 26px 14px;
        }
        .qr-section {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        /* QR container — holds canvas during generation, then shows <img> */
        .qr-wrap {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            border: 1px solid #d8e2e9;
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        /* The generated QR image fills the wrap */
        .qr-wrap img {
            width: 80px !important;
            height: 80px !important;
            display: block;
        }
        /* NEDAMS logo overlaid at centre of QR */
        .qr-logo {
            position: absolute;
            width: 18px;
            height: 18px;
            background: #071c2c;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }
        .qr-logo svg { width: 10px; height: 10px; fill: #315d77; }

        .qr-info { flex: 1; min-width: 0; }
        .qr-info .scan-label {
            font-size: .6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #8096a7;
            margin-bottom: 5px;
        }
        .url-box {
            background: #fff;
            border: 1px solid #dde4ea;
            border-radius: 4px;
            padding: 6px 9px;
            font-family: 'IBM Plex Mono', monospace;
            font-size: .6rem;
            color: #315d77;
            word-break: break-all;
            line-height: 1.5;
            margin-bottom: 7px;
        }
        .map-links { display: flex; gap: 10px; flex-wrap: wrap; }
        .map-link {
            font-size: .62rem;
            color: #315d77;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .map-link svg { width: 9px; height: 9px; fill: currentColor; }

        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #e2eaee;
            font-size: .62rem;
            color: #8096a7;
        }
        .meta-row .confidence {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .conf-bar-mini {
            width: 40px;
            height: 4px;
            background: #e2eaee;
            border-radius: 2px;
            overflow: hidden;
        }
        .conf-fill {
            height: 100%;
            border-radius: 2px;
            background: #0d7c4a;
        }

        /* ---- Toolbar (screen only) ---- */
        .toolbar {
            position: fixed;
            top: 16px; right: 16px;
            display: flex;
            gap: 8px;
            z-index: 100;
        }
        .toolbar-btn {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: 6px;
            font-family: 'IBM Plex Sans', sans-serif;
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: opacity .15s;
        }
        .toolbar-btn:hover { opacity: .85; }
        .toolbar-btn.primary { background: #103a54; color: #fff; }
        .toolbar-btn.secondary { background: #f0f4f7; color: #4a6072; border: 1px solid #e4eaee; }
        .toolbar-btn svg { flex-shrink: 0; }

        /* ---- Print ---- */
        @media print {
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                display: block !important;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                width: 100% !important;
            }
            .toolbar { display: none !important; }
            @page {
                size: A5 portrait;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>

<!-- ===== Toolbar (screen only) ===== -->
<div class="toolbar">
    <button class="toolbar-btn primary" onclick="printCard()">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
            <path d="M19 8H5a3 3 0 00-3 3v6h4v4h12v-4h4v-6a3 3 0 00-3-3zM16 19H8v-5h8v5zm1-10a1 1 0 110-2 1 1 0 010 2zM15 3H9v4h6V3z"/>
        </svg>
        Print / Save PDF
    </button>
    <a href="<?= htmlspecialchars($cleanUrl) ?>" class="toolbar-btn secondary">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor">
            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
        </svg>
        Back to Card
    </a>
</div>

<!-- ===== Address Card ===== -->
<div class="card">

    <!-- Header -->
    <div class="card-header">
        <div>
            <div class="brand-row">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                </div>
                <span class="brand-name"><?= htmlspecialchars($appName) ?></span>
            </div>
            <div class="code-label">Digital Address Code</div>
            <div class="code-value"><?= htmlspecialchars($code) ?></div>
        </div>
        <div style="text-align:right">
            <?php if ($s['status'] === 'verified'): ?>
            <div class="verified-badge">
                <svg viewBox="0 0 24 24" width="9" height="9" fill="currentColor">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
                Verified
            </div>
            <?php endif; ?>
            <div style="font-size:.58rem;color:rgba(255,255,255,.3);margin-top:8px">
                <?= htmlspecialchars(ucfirst($s['structure_type'] ?? '')) ?>
            </div>
        </div>
    </div>

    <!-- Detail rows -->
    <div class="card-body">
        <?php
        $svgPaths = [
            'person' => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
            'phone'  => 'M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z',
            'zone'   => 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z',
            'desc'   => 'M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z',
            'gps'    => 'M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06z',
        ];

        $rows = [];
        $rows[] = [$svgPaths['person'], 'Resident / Occupant', htmlspecialchars($s['resident_name'])];
        if ($s['phone'])
            $rows[] = [$svgPaths['phone'],  'Phone',            htmlspecialchars($s['phone'])];
        if ($s['zone'])
            $rows[] = [$svgPaths['zone'],   'Zone / Area',
                       htmlspecialchars($s['zone'] . ($s['parish'] ? ', ' . $s['parish'] : ''))];
        if ($s['description'])
            $rows[] = [$svgPaths['desc'],   'Description / Landmark',
                       htmlspecialchars(mb_substr($s['description'], 0, 90))];
        $rows[] = [$svgPaths['gps'], 'GPS Coordinates',
                   number_format($lat, 7) . ', ' . number_format($lng, 7)];

        foreach ($rows as [$path, $label, $value]):
        ?>
        <div class="detail-row">
            <div class="detail-icon">
                <svg viewBox="0 0 24 24"><path d="<?= $path ?>"/></svg>
            </div>
            <div>
                <div class="detail-label"><?= $label ?></div>
                <div class="detail-value"><?= $value ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Footer with QR code -->
    <div class="card-footer">
        <div class="qr-section">

            <!-- QR Code container — filled by JS -->
            <div class="qr-wrap" id="qr-wrap">
                <!-- qrcode.js renders a canvas here, we convert to img -->
                <div id="qr-canvas-holder"></div>
                <!-- Centre logo overlay -->
                <div class="qr-logo">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                    </svg>
                </div>
            </div>

            <div class="qr-info">
                <div class="scan-label">Scan to view digital address</div>
                <div class="url-box"><?= htmlspecialchars($cleanUrl) ?></div>
                <div class="map-links">
                    <a href="<?= htmlspecialchars($mapsUrl) ?>" class="map-link">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        Open in Google Maps
                    </a>
                    <a href="<?= htmlspecialchars($dirUrl) ?>" class="map-link">
                        <svg viewBox="0 0 24 24">
                            <path d="M21.71 11.29l-9-9a1 1 0 00-1.41 0l-9 9a1 1 0 000 1.41l9 9a1 1 0 001.41 0l9-9a1 1 0 000-1.41zM14 14.5V12h-4v3H8v-4a1 1 0 011-1h5V7.5l3.5 3.5-3.5 3.5z"/>
                        </svg>
                        Get Directions
                    </a>
                </div>
            </div>
        </div>

        <!-- Meta row -->
        <div class="meta-row">
            <span><?= htmlspecialchars($appName) ?> &mdash; Nakawa East, Kampala, Uganda</span>
            <div class="confidence">
                <?php $conf = (int)($s['confidence_score'] ?? 0); ?>
                <span>GPS <?= $conf ?>%</span>
                <div class="conf-bar-mini">
                    <div class="conf-fill" style="width:<?= $conf ?>%;background:<?= $conf>=90?'#0d7c4a':($conf>=65?'#a05c00':'#b91c1c') ?>"></div>
                </div>
                <span>Issued: <?= $today ?></span>
            </div>
        </div>
    </div>

</div><!-- .card -->

<script>
(function () {
    'use strict';

    const shareUrl = <?= json_encode($cleanUrl) ?>;
    const holder   = document.getElementById('qr-canvas-holder');
    const wrap     = document.getElementById('qr-wrap');

    // Generate QR using qrcode.js
    // error correction level H (30%) so centre logo doesn't break readability
    try {
        const qr = new QRCode(holder, {
            text:           shareUrl,
            width:          80,
            height:         80,
            colorDark:      '#071c2c',
            colorLight:     '#ffffff',
            correctLevel:   QRCode.CorrectLevel.H,
        });

        // qrcode.js creates a canvas; wait one tick then convert to <img>
        // so it survives the print dialog (canvas can disappear in some browsers)
        setTimeout(function () {
            const canvas = holder.querySelector('canvas');
            if (!canvas) return;

            const img    = document.createElement('img');
            img.src      = canvas.toDataURL('image/png');
            img.alt      = 'QR Code for ' + <?= json_encode($code) ?>;
            img.style.cssText = 'width:80px;height:80px;display:block;border-radius:5px';

            // Replace canvas with img
            holder.innerHTML = '';
            holder.appendChild(img);
        }, 100);

    } catch (e) {
        // Graceful fallback: show the URL as text
        wrap.innerHTML = '<div style="padding:6px;font-size:.5rem;word-break:break-all;color:#315d77;text-align:center">' +
            <?= json_encode($cleanUrl) ?> + '</div>';
    }
})();

function printCard() {
    // Give the QR image 200ms to fully render before printing
    setTimeout(function () { window.print(); }, 200);
}
</script>

</body>
</html>
