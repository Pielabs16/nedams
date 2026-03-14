<?php
// ============================================================
// views/search.php  — Address search + Google Maps deep-link
// ============================================================
require_once __DIR__.'/../config/app.php';
requireLogin();

$pageTitle      = 'Search Address';
$loadGoogleMaps = true;
require_once __DIR__.'/partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Search Address</h1>
        <p>Look up any registered structure, then open directly in Google Maps</p>
    </div>
</div>

<div class="grid-65">

    <!-- Left: Search + Results -->
    <div>
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon"><i class="fas fa-magnifying-glass"></i></div>
                    <div class="card-title">Search</div>
                </div>
            </div>
            <div class="card-body">
                <form onsubmit="doSearch(event)" autocomplete="off">
                    <div class="input-group">
                        <span class="input-addon"><i class="fas fa-magnifying-glass"></i></span>
                        <input type="text" id="search-input" class="form-control"
                               placeholder="Address code, resident name, zone, phone…"
                               autofocus>
                        <button type="submit" class="btn btn-primary">
                            Search
                        </button>
                    </div>
                </form>

                <div id="search-state" style="margin-top:12px"></div>

                <!-- Results list -->
                <div id="search-results" style="margin-top:8px"></div>
            </div>
        </div>

        <!-- Google Maps Integration info card -->
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon"><i class="fas fa-location-arrow"></i></div>
                    <div class="card-title">Third-Party Map Integration</div>
                </div>
            </div>
            <div class="card-body" style="font-size:.84rem">
                <p class="text-muted mb-2">
                    Every NEDAMS address generates a unique link that opens directly in Google Maps,
                    Apple Maps, or any navigation app via GPS coordinates.
                </p>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <div style="background:var(--body-bg);border-radius:5px;padding:10px 12px;
                                font-size:.78rem;border:1px solid var(--border)">
                        <div class="text-xs fw-600 text-muted mb-1">Google Maps (browser / Android)</div>
                        <code style="color:var(--c-mid);word-break:break-all">
                            https://www.google.com/maps?q={latitude},{longitude}
                        </code>
                    </div>
                    <div style="background:var(--body-bg);border-radius:5px;padding:10px 12px;
                                font-size:.78rem;border:1px solid var(--border)">
                        <div class="text-xs fw-600 text-muted mb-1">Google Maps Directions</div>
                        <code style="color:var(--c-mid);word-break:break-all">
                            https://www.google.com/maps/dir/?api=1&amp;destination={latitude},{longitude}
                        </code>
                    </div>
                    <div style="background:var(--body-bg);border-radius:5px;padding:10px 12px;
                                font-size:.78rem;border:1px solid var(--border)">
                        <div class="text-xs fw-600 text-muted mb-1">Apple Maps (iOS)</div>
                        <code style="color:var(--c-mid);word-break:break-all">
                            https://maps.apple.com/?q={latitude},{longitude}
                        </code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Map -->
    <div>
        <div class="card map-card">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon"><i class="fas fa-map"></i></div>
                    <div class="card-title">Map Preview</div>
                </div>
                <span class="text-xs text-muted">Click result to fly to location</span>
            </div>
            <div id="map"
                 style="height:calc(100vh - 260px);min-height:400px;
                        border-radius:0 0 var(--radius-md) var(--radius-md)">
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__.'/partials/footer.php'; ?>

<style>
.result-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px 14px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: border-color .15s, box-shadow .15s;
    background: #fff;
}
.result-card:hover {
    border-color: var(--c-mid);
    box-shadow: 0 2px 8px rgba(49,93,119,.1);
}
.result-card .rc-code {
    font-family: var(--font-mono);
    font-size: .78rem;
    background: var(--info-bg);
    color: var(--c-dark);
    border: 1px solid rgba(49,93,119,.15);
    border-radius: 3px;
    padding: 2px 7px;
    letter-spacing: .06em;
    text-decoration: none;
}
</style>

<script>
window.NEDAMS_BASE_URL = '<?= appUrl() ?>';

async function doSearch(e) {
    if (e) e.preventDefault();
    const q     = document.getElementById('search-input').value.trim();
    const state = document.getElementById('search-state');
    const res   = document.getElementById('search-results');

    if (q.length < 2) {
        state.innerHTML = '<p class="text-muted text-sm">Enter at least 2 characters.</p>';
        res.innerHTML   = '';
        return;
    }

    state.innerHTML = '<p class="text-sm text-muted"><i class="fas fa-spinner fa-spin"></i> Searching…</p>';
    res.innerHTML   = '';

    try {
        const r = await fetch(`${window.NEDAMS_BASE_URL}/api/search_location.php?q=${encodeURIComponent(q)}`);
        const d = await r.json();

        if (!d.success || d.count === 0) {
            state.innerHTML = `<div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>No results found for <strong>${esc(q)}</strong>.</div>
            </div>`;
            return;
        }

        state.innerHTML = `<p class="text-xs text-muted">${d.count} result${d.count!==1?'s':''} found</p>`;

        res.innerHTML = d.results.map(item => {
            const lat  = item.latitude;
            const lng  = item.longitude;
            const gMap = `https://www.google.com/maps?q=${lat},${lng}`;
            const dir  = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
            const apple= `https://maps.apple.com/?q=${lat},${lng}`;

            return `
            <div class="result-card" onclick="flyToMarker(${lat},${lng})">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
                            <span class="rc-code">${esc(item.address_code)}</span>
                            <span class="badge badge-${esc(item.status)} text-xs">${esc(item.status)}</span>
                            <span class="text-xs text-muted">${esc(item.structure_type||'')}</span>
                        </div>
                        <div style="font-weight:600;font-size:.88rem;color:var(--text-primary)">
                            ${esc(item.resident_name)}
                        </div>
                        <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px">
                            ${esc(item.zone||'')}
                            ${item.latitude ? `&nbsp;·&nbsp; ${item.latitude.toFixed(5)}, ${item.longitude.toFixed(5)}` : ''}
                        </div>
                        ${item.description ? `<div style="font-size:.76rem;color:var(--text-muted);margin-top:3px">${esc(item.description)}</div>` : ''}
                    </div>
                    <div style="flex-shrink:0;display:flex;flex-direction:column;gap:4px;align-items:flex-end">
                        <!-- View card -->
                        <a href="${esc(item.share_url)}" target="_blank"
                           onclick="event.stopPropagation()"
                           class="btn btn-ghost btn-sm" title="View address card">
                            <i class="fas fa-id-card"></i> Card
                        </a>
                        <!-- Google Maps deep-link -->
                        <a href="${esc(gMap)}" target="_blank"
                           onclick="event.stopPropagation()"
                           class="btn btn-accent btn-sm" title="Open in Google Maps">
                            <i class="fas fa-location-arrow"></i> Maps
                        </a>
                        <!-- Copy share link -->
                        <button onclick="event.stopPropagation();copyToClipboard('${esc(item.share_url)}',this)"
                                class="btn btn-ghost btn-sm" title="Copy share link">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <!-- Map action bar -->
                <div style="margin-top:10px;padding-top:8px;border-top:1px solid #f0f4f7;
                            display:flex;gap:8px;flex-wrap:wrap">
                    <a href="${esc(dir)}" target="_blank" onclick="event.stopPropagation()"
                       style="font-size:.75rem;color:var(--c-mid);text-decoration:none">
                        <i class="fas fa-route"></i> Get Directions (Google)
                    </a>
                    <a href="${esc(apple)}" target="_blank" onclick="event.stopPropagation()"
                       style="font-size:.75rem;color:var(--text-muted);text-decoration:none">
                        <i class="fas fa-apple" style="font-family:FontAwesome"></i>
                        <i class="fas fa-map-location"></i> Apple Maps
                    </a>
                    <span style="font-size:.75rem;color:var(--text-muted)">
                        <i class="fas fa-location-dot"></i>
                        ${lat.toFixed(7)}, ${lng.toFixed(7)}
                    </span>
                </div>
            </div>`;
        }).join('');

        // Fly map to first result
        if (d.results.length > 0) {
            flyToMarker(d.results[0].latitude, d.results[0].longitude);
        }

    } catch(err) {
        state.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><div>Search failed. Please try again.</div></div>';
    }
}

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
