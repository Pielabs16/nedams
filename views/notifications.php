<?php
// ============================================================
// views/notifications.php
// ============================================================
require_once __DIR__.'/../config/app.php';
requireLogin();
$uid = (int)$_SESSION['user_id'];
$pdo = getDB();

// Mark all read
if (isset($_GET['mark_all'])) {
    $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);
    header('Location: notifications.php'); exit;
}
// Mark single read
if (isset($_GET['read'])) {
    $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')
        ->execute([(int)$_GET['read'], $uid]);
    $link = $_GET['link'] ?? '';
    if ($link) { header('Location: '.urldecode($link)); exit; }
    header('Location: notifications.php'); exit;
}

$notifs = $pdo->prepare(
    'SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100'
);
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();
$unread = count(array_filter($notifs, fn($n)=>!$n['is_read']));

$pageTitle = 'Notifications';
require_once __DIR__.'/partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Notifications</h1>
        <p><?= $unread ?> unread of <?= count($notifs) ?> total</p>
    </div>
    <?php if ($unread > 0): ?>
    <div class="page-header-actions">
        <a href="?mark_all=1" class="btn btn-ghost">
            <i class="fas fa-check-double"></i> Mark all read
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (empty($notifs)): ?>
    <div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted)">
        <i class="fas fa-bell-slash fa-2x" style="display:block;margin-bottom:12px"></i>
        No notifications yet.
    </div>
    <?php else: ?>
    <?php
    $typeIcons = ['info'=>'fa-info-circle','success'=>'fa-check-circle',
                  'warning'=>'fa-triangle-exclamation','danger'=>'fa-exclamation-circle'];
    $typeBadge = ['info'=>'badge-info','success'=>'badge-success',
                  'warning'=>'badge-warning','danger'=>'badge-danger'];
    foreach ($notifs as $n):
        $icon  = $typeIcons[$n['type']]  ?? 'fa-info-circle';
        $badge = $typeBadge[$n['type']]  ?? 'badge-info';
    ?>
    <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 20px;
                border-bottom:1px solid #f0f4f7;
                background:<?= $n['is_read'] ? '#fff' : 'var(--info-bg)' ?>">
        <div style="width:34px;height:34px;background:var(--info-bg);border-radius:5px;
                    display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas <?= $icon ?>" style="color:var(--c-mid)"></i>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:.88rem;font-weight:<?= $n['is_read']?'500':'700' ?>;
                        color:var(--text-primary)">
                <?= htmlspecialchars($n['title']) ?>
            </div>
            <?php if ($n['message']): ?>
            <div class="text-sm text-muted mt-1"><?= htmlspecialchars($n['message']) ?></div>
            <?php endif; ?>
            <div class="text-xs text-muted mt-1">
                <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
            </div>
        </div>
        <div style="flex-shrink:0;display:flex;gap:6px;align-items:center">
            <?php if (!$n['is_read']): ?>
            <span style="width:8px;height:8px;border-radius:50%;background:var(--c-mid);display:inline-block"></span>
            <?php endif; ?>
            <?php if ($n['link']): ?>
            <a href="?read=<?= $n['id'] ?>&link=<?= urlencode($n['link']) ?>"
               class="btn btn-ghost btn-sm btn-icon" title="Go to">
                <i class="fas fa-arrow-right"></i>
            </a>
            <?php elseif (!$n['is_read']): ?>
            <a href="?read=<?= $n['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Mark read">
                <i class="fas fa-check"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__.'/partials/footer.php'; ?>
