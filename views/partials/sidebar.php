<?php
// ============================================================
// views/partials/sidebar.php  — v2.1 final
// Profile block restored to BOTTOM (V2.0 position)
// Nav body is scrollable with overflow-y:auto
// All items gated via canAccessNav()
// ============================================================
require_once __DIR__ . '/../../config/app.php';
startSession();

$role    = $_SESSION['role']    ?? 'viewer';
$uid     = (int)($_SESSION['user_id'] ?? 0);
$uname   = $_SESSION['user_name'] ?? 'User';
$initials = strtoupper(
    substr($uname, 0, 1) .
    (strpos($uname, ' ') !== false ? substr(strstr($uname, ' '), 1, 1) : '')
);

$pendingCount = 0;
$unreadMsg    = 0;
try {
    $pdo          = getDB();
    $pendingCount = (int)$pdo->query('SELECT COUNT(*) FROM structures WHERE status="pending"')->fetchColumn();
    $unreadMsg    = isAdmin() ? unreadMessageCount() : 0;
} catch (Throwable $e) {}

$cur = basename($_SERVER['PHP_SELF'], '.php');
function sActive(string ...$p): string { global $cur; return in_array($cur,$p)?'active':''; }
?>
<aside class="sidebar" id="sidebar">

    <!-- Brand bar -->
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-map-pin"></i></div>
        <div class="brand-text">
            <strong><?= htmlspecialchars(appName()) ?></strong>
            <span>Addressing System</span>
        </div>
    </div>

    <!-- ===== Scrollable nav ===== -->
    <nav class="sidebar-nav" id="sidebar-nav">

        <div class="nav-section-label">Main</div>

        <?php if (canAccessNav('dashboard')): ?>
        <a href="<?= appUrl() ?>/views/dashboard.php" class="nav-item <?= sActive('dashboard') ?>">
            <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
            <span class="nav-label">Dashboard</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('map')): ?>
        <a href="<?= appUrl() ?>/views/map.php" class="nav-item <?= sActive('map') ?>">
            <span class="nav-icon"><i class="fas fa-map"></i></span>
            <span class="nav-label">Live Map</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('search')): ?>
        <a href="<?= appUrl() ?>/views/search.php" class="nav-item <?= sActive('search') ?>">
            <span class="nav-icon"><i class="fas fa-magnifying-glass"></i></span>
            <span class="nav-label">Search Address</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('register')): ?>
        <a href="<?= appUrl() ?>/views/register.php" class="nav-item <?= sActive('register') ?>">
            <span class="nav-icon"><i class="fas fa-location-dot"></i></span>
            <span class="nav-label">Register Structure</span>
        </a>
        <?php endif; ?>

        <div class="nav-section-label">Structures</div>

        <?php if (canAccessNav('structures')): ?>
        <a href="<?= appUrl() ?>/views/admin/structures.php" class="nav-item <?= sActive('structures') ?>">
            <span class="nav-icon"><i class="fas fa-building"></i></span>
            <span class="nav-label">All Structures</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('pending')): ?>
        <a href="<?= appUrl() ?>/views/admin/structures.php?status=pending"
           class="nav-item <?= ($cur==='structures' && ($_GET['status']??'')==='pending') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-clock"></i></span>
            <span class="nav-label">Pending Review</span>
            <?php if ($pendingCount > 0): ?>
            <span class="nav-badge danger"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('zones')): ?>
        <a href="<?= appUrl() ?>/views/admin/zones.php" class="nav-item <?= sActive('zones') ?>">
            <span class="nav-icon"><i class="fas fa-layer-group"></i></span>
            <span class="nav-label">Zones &amp; Parishes</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('analytics') || canAccessNav('service_requests') || canAccessNav('audit_log')): ?>
        <div class="nav-section-label">Analytics</div>
        <?php endif; ?>

        <?php if (canAccessNav('analytics')): ?>
        <a href="<?= appUrl() ?>/views/admin/analytics.php" class="nav-item <?= sActive('analytics') ?>">
            <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
            <span class="nav-label">Analytics</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('service_requests')): ?>
        <a href="<?= appUrl() ?>/views/admin/service_requests.php" class="nav-item <?= sActive('service_requests') ?>">
            <span class="nav-icon"><i class="fas fa-truck"></i></span>
            <span class="nav-label">Service Requests</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('audit_log')): ?>
        <a href="<?= appUrl() ?>/views/admin/audit_log.php" class="nav-item <?= sActive('audit_log') ?>">
            <span class="nav-icon"><i class="fas fa-list-check"></i></span>
            <span class="nav-label">Audit Log</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('users') || canAccessNav('api_keys') || canAccessNav('messages')
               || canAccessNav('exports') || canAccessNav('settings')): ?>
        <div class="nav-section-label">Administration</div>
        <?php endif; ?>

        <?php if (canAccessNav('users')): ?>
        <a href="<?= appUrl() ?>/views/admin/users.php" class="nav-item <?= sActive('users') ?>">
            <span class="nav-icon"><i class="fas fa-users"></i></span>
            <span class="nav-label">Manage Users</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('messages')): ?>
        <a href="<?= appUrl() ?>/views/messages.php" class="nav-item <?= sActive('messages') ?>">
            <span class="nav-icon"><i class="fas fa-comments"></i></span>
            <span class="nav-label">Messages</span>
            <?php if ($unreadMsg > 0): ?>
            <span class="nav-badge danger"><?= $unreadMsg ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('api_keys')): ?>
        <a href="<?= appUrl() ?>/views/admin/api_keys.php" class="nav-item <?= sActive('api_keys') ?>">
            <span class="nav-icon"><i class="fas fa-key"></i></span>
            <span class="nav-label">API Keys</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('exports')): ?>
        <a href="<?= appUrl() ?>/views/admin/exports.php" class="nav-item <?= sActive('exports') ?>">
            <span class="nav-icon"><i class="fas fa-file-csv"></i></span>
            <span class="nav-label">Export Data</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('settings')): ?>
        <a href="<?= appUrl() ?>/views/admin/settings.php" class="nav-item <?= sActive('settings') ?>">
            <span class="nav-icon"><i class="fas fa-gear"></i></span>
            <span class="nav-label">Settings</span>
        </a>
        <?php endif; ?>

        <?php if (canAccessNav('api_docs')): ?>
        <div class="nav-section-label">Developer</div>
        <a href="<?= appUrl() ?>/docs/api.php" class="nav-item <?= sActive('api') ?>">
            <span class="nav-icon"><i class="fas fa-plug"></i></span>
            <span class="nav-label">API Docs</span>
        </a>
        <?php if (isSuperAdmin()): ?>
        <a href="<?= appUrl() ?>/views/admin/role_permissions.php"
           class="nav-item <?= sActive('role_permissions') ?>">
            <span class="nav-icon"><i class="fas fa-shield-halved"></i></span>
            <span class="nav-label">Role Permissions</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <div class="nav-section-label">Account</div>

        <a href="<?= appUrl() ?>/views/profile.php" class="nav-item <?= sActive('profile') ?>">
            <span class="nav-icon"><i class="fas fa-circle-user"></i></span>
            <span class="nav-label">My Profile</span>
        </a>

    </nav>

    <!-- ===== Profile footer (V2.0 position — bottom) ===== -->
    <div class="sidebar-footer">
        <a href="<?= appUrl() ?>/views/profile.php" class="user-card">
            <div class="user-avatar"><?= $initials ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($uname) ?></div>
                <div class="user-role"><?= htmlspecialchars(str_replace('_', ' ', $role)) ?></div>
            </div>
        </a>
    </div>

</aside>
