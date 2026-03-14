<?php
// ============================================================
// views/register.php  — Register a new structure
// ============================================================
require_once __DIR__.'/../config/app.php';
requireLogin();

// Load zones for dropdown
$zones = getDB()->query('SELECT name FROM zones WHERE is_active=1 ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle      = 'Register Structure';
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
        <h1>Register Structure</h1>
        <p>Drop a pin on the map or use GPS, then complete the form</p>
    </div>
</div>

<div class="grid-2">

    <!-- Left: Map -->
    <div>
        <div class="card mb-2">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon"><i class="fas fa-map-pin"></i></div>
                    <div class="card-title">Pin Location</div>
                </div>
                <button type="button" id="gps-btn" class="btn btn-ghost btn-sm" onclick="getGPSLocation()">
                    <i class="fas fa-location-crosshairs"></i> Use GPS
                </button>
            </div>
            <div class="card-body" style="padding:12px">
                <p class="text-sm text-muted mb-2">
                    <i class="fas fa-info-circle"></i>
                    Click on the map to place a pin. Drag the pin to fine-tune position.
                </p>
                <div class="needs-pin hidden" id="coord-row">
                    <div class="d-flex align-center gap-2">
                        <span class="text-sm text-muted">Coordinates:</span>
                        <span class="coords-display" id="coord-display">—</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map -->
        <div class="card map-card" style="overflow:hidden">
            <div id="map" style="height:420px"></div>
        </div>
    </div>

    <!-- Right: Form -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon"><i class="fas fa-building"></i></div>
                    <div class="card-title">Structure Details</div>
                </div>
            </div>
            <div class="card-body">

                <div id="flash-zone"></div>
                <div id="register-result"></div>

                <form id="register-form" onsubmit="submitRegistration(event)">
                    <!-- Hidden GPS fields -->
                    <input type="hidden" id="latitude"  name="latitude">
                    <input type="hidden" id="longitude" name="longitude">
                    <input type="hidden" id="accuracy-meters" name="accuracy_meters">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Resident / Occupant Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="resident_name" class="form-control"
                                   placeholder="Full name of occupant" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+256 7XX XXX XXX">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Zone</label>
                            <select name="zone" class="form-control">
                                <option value="">— Select zone —</option>
                                <?php foreach ($zones as $z): ?>
                                <option value="<?= htmlspecialchars($z) ?>"><?= htmlspecialchars($z) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Parish</label>
                            <input type="text" name="parish" class="form-control" placeholder="Parish name">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Structure Type</label>
                            <select name="structure_type" class="form-control">
                                <option value="residential">Residential</option>
                                <option value="commercial">Commercial / Shop</option>
                                <option value="school">School</option>
                                <option value="clinic">Health Clinic</option>
                                <option value="worship">Place of Worship</option>
                                <option value="government">Government</option>
                                <option value="ngo">NGO / CBO</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Number of Floors</label>
                            <input type="number" name="floor_count" class="form-control" value="1" min="1" max="50">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description / Nearby Landmarks</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="e.g. Blue gate, second house from the main road junction..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Additional Landmarks</label>
                        <input type="text" name="landmarks" class="form-control"
                               placeholder="Near borehole, opposite primary school...">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Occupant Email (optional)</label>
                            <input type="email" name="email" class="form-control" placeholder="contact@example.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Number of Occupants</label>
                            <input type="number" name="occupant_count" class="form-control" value="1" min="1">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-plus"></i> Register Structure
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/partials/footer.php'; ?>
