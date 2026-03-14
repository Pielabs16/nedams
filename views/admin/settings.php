<?php
// ============================================================
// views/admin/settings.php  — Full system settings panel
// ============================================================
require_once __DIR__.'/../../config/app.php';
requireAdmin();

// Handle save
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['save_group'])) {
    $group = $_POST['save_group'];
    foreach ($_POST['settings'] as $key => $val) {
        // If it's a password field and empty, skip (keep existing)
        $existing = setting("$group.$key");
        if ($val==='' && $existing!=='' &&
            getDB()->prepare('SELECT type FROM settings WHERE `group`=? AND `key`=?')
                   ->execute([$group,$key]) &&
            getDB()->query('SELECT type FROM settings WHERE `group`="'.$group.'" AND `key`="'.$key.'"')
                   ->fetchColumn() === 'password') {
            continue;
        }
        saveSetting($group, $key, $val);
    }
    auditLog('save_settings','settings','group',$group,"Saved $group settings");
    $_SESSION['flash'] = ['type'=>'success','message'=>'Settings saved successfully.'];
    header('Location: ?section='.$group); exit;
}

// Load all settings grouped
$pdo = getDB();
$allSettings = $pdo->query('SELECT * FROM settings ORDER BY `group`,`key`')->fetchAll();
$groups = [];
foreach ($allSettings as $s) {
    $groups[$s['group']][] = $s;
}

// Mailer test
$mailerMsg = null;
if (isset($_POST['test_mail']) && !empty($_POST['test_email'])) {
    // Would use PHPMailer in production; placeholder response
    $mailerMsg = ['type'=>'info','message'=>'Mail test queued. Check your inbox.'];
}

$pageTitle = 'System Settings';
require_once __DIR__.'/../partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/../partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/../partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>System Settings</h1>
        <p>Configure all aspects of NEDAMS — stored securely in the database</p>
    </div>
    <div class="page-header-actions">
        <?php if (isSuperAdmin()): ?>
        <a href="<?= appUrl() ?>/views/admin/role_permissions.php" class="btn btn-ghost">
            <i class="fas fa-shield-halved"></i> Role Permissions
        </a>
        <?php endif; ?>
    </div>
</div>

<div id="flash-zone"></div>

<div class="grid-56">

    <!-- Settings navigation -->
    <div class="card" style="height:fit-content">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-header-icon"><i class="fas fa-gear"></i></div>
                <div class="card-title">Configuration</div>
            </div>
        </div>
        <div class="card-body" style="padding:10px">
            <nav class="settings-nav">
                <?php
                $sectionMeta = [
                    'general'    => ['fa-sliders',        'General'],
                    'maps'       => ['fa-map',             'Google Maps'],
                    'addressing' => ['fa-map-pin',         'Addressing'],
                    'mail'       => ['fa-envelope',        'Mailer / SMTP'],
                    'security'   => ['fa-shield-halved',   'Security'],
                    'workflow'   => ['fa-sitemap',         'Registration Workflow'],
                ];
                foreach ($sectionMeta as $sec => [$icon, $label]):
                    if (!isset($groups[$sec])) continue;
                ?>
                <button class="settings-nav-item" data-section="<?= $sec ?>">
                    <i class="fas <?= $icon ?>"></i> <?= $label ?>
                </button>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>

    <!-- Settings panels -->
    <div>
        <?php if ($mailerMsg): ?>
        <div class="alert alert-<?= $mailerMsg['type'] ?> mb-2">
            <i class="fas fa-info-circle"></i><div><?= htmlspecialchars($mailerMsg['message']) ?></div>
        </div>
        <?php endif; ?>

        <?php foreach ($groups as $groupKey => $settings):
            $meta = $sectionMeta[$groupKey] ?? ['fa-gear', ucfirst($groupKey)];
        ?>
        <div class="settings-section" id="section-<?= $groupKey ?>">
            <div class="card mb-2">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-header-icon"><i class="fas <?= $meta[0] ?>"></i></div>
                        <div class="card-title"><?= $meta[1] ?> Settings</div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="save_group" value="<?= htmlspecialchars($groupKey) ?>">

                        <?php foreach ($settings as $s): ?>
                        <div class="form-group">
                            <label class="form-label">
                                <?= htmlspecialchars($s['label']??$s['key']) ?>
                                <?php if ($s['type']==='password'): ?>
                                <span class="badge badge-dark" style="margin-left:4px">Encrypted</span>
                                <?php endif; ?>
                            </label>

                            <?php if ($s['type']==='boolean'): ?>
                                <div class="toggle-wrap">
                                    <label class="toggle">
                                        <input type="hidden" name="settings[<?= htmlspecialchars($s['key']) ?>]" value="0">
                                        <input type="checkbox" name="settings[<?= htmlspecialchars($s['key']) ?>]"
                                               value="1" <?= $s['value'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">
                                        <?= $s['value'] ? 'Enabled' : 'Disabled' ?>
                                    </span>
                                </div>

                            <?php elseif ($s['type']==='password'): ?>
                                <input type="password"
                                       name="settings[<?= htmlspecialchars($s['key']) ?>]"
                                       class="form-control"
                                       placeholder="<?= $s['value'] ? '••••••••••••' : 'Enter value' ?>"
                                       autocomplete="new-password">
                                <?php if ($s['value']): ?>
                                <div class="form-hint"><i class="fas fa-check-circle" style="color:var(--success)"></i> Value is set. Leave blank to keep existing.</div>
                                <?php endif; ?>

                            <?php elseif ($s['type']==='textarea'): ?>
                                <textarea name="settings[<?= htmlspecialchars($s['key']) ?>]"
                                          class="form-control" rows="3"><?= htmlspecialchars($s['value']??'') ?></textarea>

                            <?php elseif ($s['type']==='integer'): ?>
                                <input type="number"
                                       name="settings[<?= htmlspecialchars($s['key']) ?>]"
                                       class="form-control"
                                       value="<?= htmlspecialchars($s['value']??'') ?>">

                            <?php else: ?>
                                <input type="text"
                                       name="settings[<?= htmlspecialchars($s['key']) ?>]"
                                       class="form-control"
                                       value="<?= htmlspecialchars($s['value']??'') ?>">
                            <?php endif; ?>

                            <?php if ($s['description']): ?>
                            <div class="form-hint"><?= htmlspecialchars($s['description']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-floppy-disk"></i> Save <?= $meta[1] ?> Settings
                            </button>
                        </div>
                    </form>

                    <?php if ($groupKey==='mail'): ?>
                    <hr class="divider">
                    <form method="POST">
                        <div class="card-title mb-2" style="font-size:.85rem">
                            <i class="fas fa-paper-plane"></i> Send Test Email
                        </div>
                        <div class="d-flex gap-2">
                            <input type="email" name="test_email" class="form-control"
                                   placeholder="Send test to...">
                            <button type="submit" name="test_mail" value="1" class="btn btn-accent">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- No section selected message -->
        <div class="card" id="section-none" style="display:none">
            <div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted)">
                <i class="fas fa-gear fa-2x mb-2" style="display:block;margin-bottom:12px"></i>
                Select a settings category from the left panel.
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__.'/../partials/footer.php'; ?>
