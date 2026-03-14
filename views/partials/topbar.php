<?php
// ============================================================
// views/partials/topbar.php  — v2.1
// Functional notifications dropdown, message count
// ============================================================
$pageTitle  = $pageTitle ?? 'NEDAMS';
$uid        = (int)($_SESSION['user_id'] ?? 0);
$notifCount = unreadNotifCount();
$msgCount   = isAdmin() ? unreadMessageCount() : 0;

// Fetch recent notifications for dropdown
$notifList = [];
try {
    $ns = getDB()->prepare(
        'SELECT id, title, message, type, link, is_read, created_at
         FROM notifications WHERE user_id=?
         ORDER BY created_at DESC LIMIT 8'
    );
    $ns->execute([$uid]);
    $notifList = $ns->fetchAll();
} catch(Throwable $e) {}

// Mark all read if requested
if (isset($_GET['mark_notif_read'])) {
    try {
        getDB()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);
    } catch(Throwable $e) {}
    header('Location: '.strtok($_SERVER['REQUEST_URI'],'?')); exit;
}
?>
<header class="topbar">
    <button class="topbar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <nav class="topbar-breadcrumb">
        <span><?= htmlspecialchars(appName()) ?></span>
        <i class="fas fa-chevron-right"></i>
        <span class="crumb-current"><?= htmlspecialchars($pageTitle) ?></span>
    </nav>

    <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>

    <div class="topbar-right" style="position:relative">

        <!-- Messages button (admin only) -->
        <?php if ($msgCount > 0): ?>
        <a href="<?= appUrl() ?>/views/messages.php" class="topbar-btn" title="Unread messages">
            <i class="fas fa-envelope"></i>
            <span class="notif-dot" style="background:var(--danger)"></span>
        </a>
        <?php endif; ?>

        <!-- Notifications button -->
        <button class="topbar-btn" id="notif-btn" title="Notifications" style="position:relative">
            <i class="fas fa-bell"></i>
            <?php if ($notifCount > 0): ?>
            <span class="notif-dot"></span>
            <?php endif; ?>
        </button>

        <!-- Notifications dropdown -->
        <div id="notif-dropdown" style="
            display:none;position:absolute;top:calc(100% + 8px);right:0;
            width:340px;background:#fff;border:1px solid var(--card-border);
            border-radius:var(--radius-md);box-shadow:var(--card-shadow-md);
            z-index:500;overflow:hidden">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);
                        display:flex;align-items:center;justify-content:space-between">
                <span style="font-weight:700;font-size:.88rem">Notifications</span>
                <?php if ($notifCount > 0): ?>
                <a href="?mark_notif_read=1" style="font-size:.76rem;color:var(--c-mid);
                   text-decoration:none">Mark all read</a>
                <?php endif; ?>
            </div>
            <div style="max-height:360px;overflow-y:auto">
                <?php if (empty($notifList)): ?>
                <div style="padding:28px;text-align:center;color:var(--text-muted);font-size:.84rem">
                    <i class="fas fa-bell-slash" style="display:block;font-size:1.4rem;margin-bottom:8px"></i>
                    No notifications
                </div>
                <?php else: ?>
                <?php foreach ($notifList as $n):
                    $typeIcons = ['info'=>'fa-info-circle','success'=>'fa-check-circle',
                                  'warning'=>'fa-triangle-exclamation','danger'=>'fa-exclamation-circle'];
                    $typeColors= ['info'=>'var(--c-mid)','success'=>'var(--success)',
                                  'warning'=>'var(--warning)','danger'=>'var(--danger)'];
                    $icon  = $typeIcons[$n['type']]  ?? 'fa-info-circle';
                    $color = $typeColors[$n['type']] ?? 'var(--c-mid)';
                ?>
                <a href="<?= $n['link'] ? htmlspecialchars($n['link']) : '#' ?>"
                   style="display:flex;align-items:flex-start;gap:10px;padding:11px 16px;
                          text-decoration:none;border-bottom:1px solid #f5f8fa;
                          background:<?= $n['is_read'] ? '#fff' : 'var(--info-bg)' ?>;
                          transition:background .15s"
                   onmouseover="this.style.background='#f5f8fa'"
                   onmouseout="this.style.background='<?= $n['is_read'] ? '#fff' : 'var(--info-bg)' ?>'">
                    <i class="fas <?= $icon ?>" style="color:<?= $color ?>;margin-top:2px;flex-shrink:0"></i>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.83rem;font-weight:<?= $n['is_read']?'500':'700' ?>;
                                    color:var(--text-primary)">
                            <?= htmlspecialchars($n['title']) ?>
                        </div>
                        <?php if ($n['message']): ?>
                        <div style="font-size:.76rem;color:var(--text-muted);margin-top:2px;
                                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            <?= htmlspecialchars($n['message']) ?>
                        </div>
                        <?php endif; ?>
                        <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px">
                            <?= date('d M, H:i', strtotime($n['created_at'])) ?>
                        </div>
                    </div>
                    <?php if (!$n['is_read']): ?>
                    <span style="width:7px;height:7px;border-radius:50%;background:var(--c-mid);
                                 flex-shrink:0;margin-top:5px"></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div style="padding:10px 16px;border-top:1px solid var(--border);text-align:center">
                <a href="<?= appUrl() ?>/views/notifications.php"
                   style="font-size:.78rem;color:var(--c-mid);text-decoration:none">
                    View all notifications
                </a>
            </div>
        </div>

        <!-- Profile -->
        <a href="<?= appUrl() ?>/views/profile.php" class="topbar-btn" title="My Profile">
            <i class="fas fa-circle-user"></i>
        </a>

        <!-- Logout -->
        <a href="<?= appUrl() ?>/controllers/auth.php?action=logout"
           class="topbar-btn"
           title="Sign Out"
           onclick="return confirm('Sign out of <?= htmlspecialchars(appName()) ?>?')">
            <i class="fas fa-right-from-bracket"></i>
        </a>

    </div>
</header>

<?php if (!empty($_SESSION['flash'])): ?>
<div style="padding:0 24px;margin-top:8px">
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']??'info') ?>" data-auto-dismiss>
        <i class="fas fa-<?= ($_SESSION['flash']['type']??'info')==='success'?'check-circle':'info-circle' ?>"></i>
        <div><?= htmlspecialchars($_SESSION['flash']['message']??'') ?></div>
        <button class="alert-dismiss"><i class="fas fa-xmark"></i></button>
    </div>
</div>
<?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<script>
// Notifications dropdown toggle
(function() {
    const btn  = document.getElementById('notif-btn');
    const drop = document.getElementById('notif-dropdown');
    if (!btn || !drop) return;
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const open = drop.style.display !== 'none';
        drop.style.display = open ? 'none' : 'block';
    });
    document.addEventListener('click', function(e) {
        if (!drop.contains(e.target) && e.target !== btn) {
            drop.style.display = 'none';
        }
    });
})();
</script>
