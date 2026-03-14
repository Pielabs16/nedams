'use strict';

/* ============================================================
   SIDEBAR TOGGLE
   ============================================================ */
(function () {
    const sidebar  = document.getElementById('sidebar');
    const main     = document.getElementById('main-content');
    const toggleBtn= document.getElementById('sidebar-toggle');

    if (!sidebar) return;

    const MOBILE = () => window.innerWidth <= 768;
    const STORED = localStorage.getItem('nedams_sidebar');

    // Restore desktop state
    if (!MOBILE() && STORED === '1') {
        sidebar.classList.add('collapsed');
        main?.classList.add('expanded');
    }

    function toggle() {
        if (MOBILE()) {
            sidebar.classList.toggle('mobile-open');
        } else {
            const col = sidebar.classList.toggle('collapsed');
            main?.classList.toggle('expanded', col);
            localStorage.setItem('nedams_sidebar', col ? '1' : '0');
        }
    }

    toggleBtn?.addEventListener('click', toggle);

    // Close mobile sidebar on outside click
    document.addEventListener('click', function (e) {
        if (MOBILE() && sidebar.classList.contains('mobile-open')) {
            if (!sidebar.contains(e.target) && !toggleBtn?.contains(e.target)) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });
})();

/* ============================================================
   GOOGLE MAPS
   ============================================================ */
const NEDAMS = {
    map: null,
    markers: [],
    currentPin: null,
    selectedLat: null,
    selectedLng: null,
    infoWindow: null,
    baseUrl: window.NEDAMS_BASE_URL || '',
};

function initMap() {
    const lat  = parseFloat(window.MAP_LAT  ?? 0.3476);
    const lng  = parseFloat(window.MAP_LNG  ?? 32.6152);
    const zoom = parseInt(window.MAP_ZOOM   ?? 15);

    NEDAMS.map = new google.maps.Map(document.getElementById('map'), {
        center: { lat, lng },
        zoom,
        mapTypeId: 'roadmap',
        mapTypeControl: true,
        fullscreenControl: true,
        streetViewControl: false,
        mapTypeControlOptions: {
            style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
        },
        styles: [
            { featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] },
        ],
    });

    NEDAMS.infoWindow = new google.maps.InfoWindow();

    // Register form: click to drop pin
    if (document.getElementById('register-form')) {
        NEDAMS.map.addListener('click', e => placePin(e.latLng.lat(), e.latLng.lng()));
    }

    // Single address card map
    if (window.ADDR_LAT && window.ADDR_LNG) {
        const pos = { lat: parseFloat(window.ADDR_LAT), lng: parseFloat(window.ADDR_LNG) };
        new google.maps.Marker({ position: pos, map: NEDAMS.map, animation: google.maps.Animation.DROP });
        return;
    }

    // Fly-to from structures list
    if (window.FLYTO_LAT && window.FLYTO_LNG) {
        NEDAMS.map.setCenter({ lat: parseFloat(window.FLYTO_LAT), lng: parseFloat(window.FLYTO_LNG) });
        NEDAMS.map.setZoom(18);
    }

    NEDAMS.map.addListener('idle', loadMarkersForView);
    loadMarkersForView();
}

function loadMarkersForView() {
    if (!NEDAMS.map) return;
    const b = NEDAMS.map.getBounds();
    if (!b) return;
    const sw = b.getSouthWest(), ne = b.getNorthEast();
    fetch(
        `${NEDAMS.baseUrl}/api/get_markers.php` +
        `?swLat=${sw.lat()}&swLng=${sw.lng()}&neLat=${ne.lat()}&neLng=${ne.lng()}`
    )
        .then(r => r.json())
        .then(d => { if (d.success) renderMarkers(d.markers); })
        .catch(() => {});
}

function renderMarkers(data) {
    NEDAMS.markers.forEach(m => m.setMap(null));
    NEDAMS.markers = [];

    const colours = {
        residential: '#315d77',
        commercial:  '#a05c00',
        school:      '#1d4ed8',
        clinic:      '#b91c1c',
        worship:     '#5b21b6',
        government:  '#0d7c4a',
        ngo:         '#0d7c4a',
        other:       '#4a6072',
    };

    data.forEach(item => {
        const marker = new google.maps.Marker({
            position: { lat: item.lat, lng: item.lng },
            map: NEDAMS.map,
            title: item.name,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                fillColor: colours[item.type] || colours.other,
                fillOpacity: 1,
                strokeColor: '#fff',
                strokeWeight: item.status === 'verified' ? 2 : 1.5,
                scale: item.status === 'verified' ? 9 : 7,
            },
        });

        marker.addListener('click', () => {
            const cardUrl = item.share_url || `${NEDAMS.baseUrl}/views/view.php?id=${item.code}`;
            NEDAMS.infoWindow.setContent(`
                <div style="font-family:'IBM Plex Sans',sans-serif;min-width:200px;padding:4px 2px">
                    <div style="font-weight:700;color:#071c2c;font-size:.92rem;margin-bottom:4px">
                        ${esc(item.name)}
                    </div>
                    <div style="font-family:'IBM Plex Mono',monospace;color:#103a54;
                         font-size:.76rem;background:#e8f2f8;padding:2px 7px;
                         border-radius:3px;display:inline-block;margin-bottom:6px;
                         letter-spacing:.06em">
                        ${esc(item.code)}
                    </div>
                    ${item.description
                        ? `<p style="font-size:.8rem;color:#4a6072;margin:3px 0 8px">${esc(item.description)}</p>`
                        : ''}
                    <a href="${cardUrl}" target="_blank"
                       style="display:inline-flex;align-items:center;gap:5px;
                              background:#103a54;color:#fff;
                              padding:5px 12px;border-radius:4px;font-size:.76rem;
                              text-decoration:none;font-weight:600">
                        View Card
                    </a>
                </div>`);
            NEDAMS.infoWindow.open(NEDAMS.map, marker);
        });

        NEDAMS.markers.push(marker);
    });

    // Clustering
    if (typeof MarkerClusterer !== 'undefined' && NEDAMS.markers.length > 20) {
        new MarkerClusterer({ map: NEDAMS.map, markers: NEDAMS.markers });
    }
}

function placePin(lat, lng) {
    NEDAMS.selectedLat = lat;
    NEDAMS.selectedLng = lng;

    if (NEDAMS.currentPin) NEDAMS.currentPin.setMap(null);

    NEDAMS.currentPin = new google.maps.Marker({
        position: { lat, lng },
        map: NEDAMS.map,
        draggable: true,
        animation: google.maps.Animation.DROP,
        icon: {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
                '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="0 0 28 36">' +
                '<path d="M14 0C6.27 0 0 6.27 0 14c0 9.45 14 22 14 22S28 23.45 28 14C28 6.27 21.73 0 14 0z" fill="#103a54"/>' +
                '<circle cx="14" cy="14" r="6" fill="#fff"/></svg>'
            ),
            scaledSize: new google.maps.Size(28, 36),
            anchor: new google.maps.Point(14, 36),
        },
    });

    NEDAMS.currentPin.addListener('dragend', e => {
        NEDAMS.selectedLat = e.latLng.lat();
        NEDAMS.selectedLng = e.latLng.lng();
        updateCoordDisplay();
    });

    updateCoordDisplay();
}

function updateCoordDisplay() {
    const latEl = document.getElementById('latitude');
    const lngEl = document.getElementById('longitude');
    const disp  = document.getElementById('coord-display');
    if (latEl) latEl.value = NEDAMS.selectedLat.toFixed(7);
    if (lngEl) lngEl.value = NEDAMS.selectedLng.toFixed(7);
    if (disp)  disp.textContent = `${NEDAMS.selectedLat.toFixed(6)}, ${NEDAMS.selectedLng.toFixed(6)}`;
    document.querySelectorAll('.needs-pin').forEach(el => el.classList.remove('hidden'));
}

function getGPSLocation() {
    const btn = document.getElementById('gps-btn');
    if (!navigator.geolocation) {
        showAlert('GPS not supported in this browser.', 'danger');
        return;
    }
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating…'; }
    navigator.geolocation.getCurrentPosition(
        pos => {
            placePin(pos.coords.latitude, pos.coords.longitude);
            const accEl = document.getElementById('accuracy-meters');
            if (accEl) accEl.value = pos.coords.accuracy?.toFixed(1) || '';
            NEDAMS.map?.setCenter({ lat: pos.coords.latitude, lng: pos.coords.longitude });
            NEDAMS.map?.setZoom(18);
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Use GPS'; }
        },
        err => {
            showAlert('GPS error: ' + err.message, 'danger');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Use GPS'; }
        },
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
    );
}

function flyToMarker(lat, lng) {
    if (!NEDAMS.map) return;
    NEDAMS.map.setCenter({ lat, lng });
    NEDAMS.map.setZoom(18);
}

/* ============================================================
   REGISTRATION FORM AJAX
   ============================================================ */
function submitRegistration(e) {
    e.preventDefault();
    const form   = e.target;
    const btn    = form.querySelector('[type=submit]');
    const result = document.getElementById('register-result');

    if (!NEDAMS.selectedLat) {
        showAlert('Drop a pin on the map first, or use GPS.', 'warning', result);
        return;
    }

    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering…';

    const fd = new FormData(form);
    fd.set('latitude',  NEDAMS.selectedLat);
    fd.set('longitude', NEDAMS.selectedLng);

    fetch(`${NEDAMS.baseUrl}/api/register_location.php`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                result.innerHTML = `
                    <div class="alert alert-success mt-2">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            Registered. Address: <strong class="addr-code">${esc(data.address_code)}</strong><br>
                            <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                                <a href="${data.share_url}" target="_blank" class="btn btn-accent btn-sm">
                                    <i class="fas fa-id-card"></i> View Card
                                </a>
                                <a href="${data.whatsapp_url}" target="_blank" class="btn btn-ghost btn-sm">
                                    <i class="fab fa-whatsapp"></i> Share
                                </a>
                                <button onclick="copyToClipboard('${data.share_url}',this)" class="btn btn-ghost btn-sm">
                                    <i class="fas fa-copy"></i> Copy Link
                                </button>
                            </div>
                        </div>
                    </div>`;
                form.reset();
                NEDAMS.selectedLat = NEDAMS.selectedLng = null;
                NEDAMS.currentPin?.setMap(null);
                loadMarkersForView();
            } else {
                showAlert(data.message || 'Registration failed.', 'danger', result);
            }
        })
        .catch(() => showAlert('Network error. Please try again.', 'danger', result))
        .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

/* ============================================================
   SEARCH
   ============================================================ */
function submitSearch(e) {
    e.preventDefault();
    const q   = (document.getElementById('search-input')?.value || '').trim();
    const res = document.getElementById('search-results');
    if (!q || q.length < 2 || !res) return;

    res.innerHTML = '<p class="text-muted text-sm"><i class="fas fa-spinner fa-spin"></i> Searching…</p>';

    fetch(`${NEDAMS.baseUrl}/api/search_location.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || data.count === 0) {
                res.innerHTML = '<div class="alert alert-info mt-1"><i class="fas fa-info-circle"></i><div>No results found.</div></div>';
                return;
            }
            res.innerHTML = data.results.map(item => `
                <div class="card mb-1" style="cursor:pointer"
                     onclick="flyToMarker(${item.latitude},${item.longitude})">
                    <div class="card-body" style="padding:11px 14px">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                            <div>
                                <span class="addr-code">${esc(item.address_code)}</span>
                                <span class="badge badge-${esc(item.status)} ms-1">${esc(item.status)}</span>
                                <div class="fw-600 mt-1" style="font-size:.85rem">${esc(item.resident_name)}</div>
                                <div class="text-muted text-xs">${esc(item.zone || '')} &nbsp;${item.latitude.toFixed(5)}, ${item.longitude.toFixed(5)}</div>
                            </div>
                            <a href="${esc(item.share_url)}" target="_blank"
                               class="btn btn-ghost btn-sm" onclick="event.stopPropagation()">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>`).join('');
        })
        .catch(() => res.innerHTML = '<div class="alert alert-danger mt-1"><i class="fas fa-exclamation-circle"></i><div>Search failed.</div></div>');
}

/* ============================================================
   CHART.JS ANALYTICS
   ============================================================ */
function initAnalyticsCharts(registrations, byType, byZone, apiTrend) {
    const base = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { font: { family: 'IBM Plex Sans', size: 11 }, color: '#7a92a3', boxWidth: 10 },
            },
        },
    };

    const regCtx = document.getElementById('chart-registrations');
    if (regCtx && registrations?.length) {
        new Chart(regCtx, {
            type: 'bar',
            data: {
                labels: registrations.map(r => r.day),
                datasets: [{
                    label: 'Registrations',
                    data: registrations.map(r => r.count),
                    backgroundColor: '#315d77',
                    borderRadius: 3,
                    borderSkipped: false,
                }],
            },
            options: {
                ...base,
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#7a92a3' } },
                    y: { grid: { color: '#f0f4f7' }, ticks: { precision: 0, font: { size: 9 }, color: '#7a92a3' } },
                },
            },
        });
    }

    const typeCtx = document.getElementById('chart-types');
    if (typeCtx && byType?.length) {
        const palette = ['#315d77','#103a54','#071c2c','#4a8aa8','#6aafcc','#8ecde0','#b0dded'];
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: byType.map(t => t.label),
                datasets: [{
                    data: byType.map(t => t.value),
                    backgroundColor: palette.slice(0, byType.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: { ...base, cutout: '62%', plugins: { ...base.plugins, legend: { ...base.plugins.legend, position: 'right' } } },
        });
    }

    const zoneCtx = document.getElementById('chart-zones');
    if (zoneCtx && byZone?.length) {
        new Chart(zoneCtx, {
            type: 'bar',
            data: {
                labels: byZone.map(z => z.zone),
                datasets: [{ label: 'Structures', data: byZone.map(z => z.count), backgroundColor: '#103a54', borderRadius: 3 }],
            },
            options: {
                ...base,
                indexAxis: 'y',
                scales: {
                    x: { grid: { color: '#f0f4f7' }, ticks: { precision: 0, font: { size: 9 }, color: '#7a92a3' } },
                    y: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#3d5568' } },
                },
            },
        });
    }

    const apiCtx = document.getElementById('chart-api');
    if (apiCtx && apiTrend?.length) {
        new Chart(apiCtx, {
            type: 'line',
            data: {
                labels: apiTrend.map(d => d.day),
                datasets: [{
                    label: 'API Calls',
                    data: apiTrend.map(d => d.count),
                    borderColor: '#315d77',
                    backgroundColor: 'rgba(49,93,119,.07)',
                    fill: true, tension: .3, borderWidth: 2,
                    pointBackgroundColor: '#315d77', pointRadius: 3,
                }],
            },
            options: {
                ...base,
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#7a92a3' } },
                    y: { grid: { color: '#f0f4f7' }, ticks: { precision: 0, font: { size: 9 }, color: '#7a92a3' } },
                },
            },
        });
    }
}

/* ============================================================
   TABS
   ============================================================ */
function initTabs(selector) {
    document.querySelectorAll(`${selector || ''} .tab-btn`).forEach(btn => {
        btn.addEventListener('click', () => {
            const wrapper = btn.closest('.tabs-wrapper') || document;
            wrapper.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            wrapper.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            wrapper.querySelector('#' + btn.dataset.tab)?.classList.add('active');
        });
    });
}

/* ============================================================
   SETTINGS NAV
   ============================================================ */
function initSettingsNav() {
    document.querySelectorAll('.settings-nav-item').forEach(item => {
        item.addEventListener('click', () => {
            document.querySelectorAll('.settings-nav-item').forEach(i => i.classList.remove('active'));
            document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
            item.classList.add('active');
            document.getElementById('section-' + item.dataset.section)?.classList.add('active');
            history.replaceState(null, '', '?section=' + item.dataset.section);
        });
    });
    const sec = new URLSearchParams(location.search).get('section');
    if (sec) document.querySelector(`[data-section="${sec}"]`)?.click();
}

/* ============================================================
   MODAL HELPERS
   ============================================================ */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay'))
        e.target.classList.remove('open');
    if (e.target.classList.contains('modal-close')) {
        const overlay = e.target.closest('.modal-overlay');
        if (overlay) overlay.classList.remove('open');
    }
});

/* ============================================================
   ALERT HELPER
   ============================================================ */
function showAlert(msg, type = 'info', container = null) {
    const icons = { success: 'check-circle', danger: 'exclamation-circle', warning: 'triangle-exclamation', info: 'info-circle' };
    const el = document.createElement('div');
    el.className = `alert alert-${type}`;
    el.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i><div>${msg}</div>
        <button class="alert-dismiss"><i class="fas fa-xmark"></i></button>`;
    const target = container || document.getElementById('flash-zone');
    if (target) {
        target.prepend(el);
        setTimeout(() => el.remove(), 6000);
    }
}

/* ============================================================
   CLIPBOARD
   ============================================================ */
function copyToClipboard(text, btn) {
    navigator.clipboard?.writeText(text).then(() => {
        if (!btn) return;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied';
        setTimeout(() => btn.innerHTML = orig, 2200);
    });
}

/* ============================================================
   CONFIRM ACTION
   ============================================================ */
function confirmAction(msg, url) {
    if (confirm(msg)) window.location.href = url;
}

/* ============================================================
   HTML ESCAPE
   ============================================================ */
function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ============================================================
   DOM READY
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initSettingsNav();

    // Auto-dismiss flash alerts
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el =>
        setTimeout(() => el.remove(), 5000)
    );

    // Confirm data-confirm buttons
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!confirm(btn.dataset.confirm)) e.preventDefault();
        });
    });
});
