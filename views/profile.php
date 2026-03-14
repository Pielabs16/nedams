<?php
// ============================================================
// views/profile.php  — User profile & settings
// ============================================================
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../models/User.php';
requireLogin();

$uid  = (int)$_SESSION['user_id'];
$user = User::findById($uid);

// Handle updates
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['update_profile'])) {
        User::update($uid, [
            'full_name' => trim($_POST['full_name']??''),
            'phone'     => trim($_POST['phone']??''),
        ]);
        // Refresh session name
        $_SESSION['user_name'] = trim($_POST['full_name']??$user['full_name']);
        $_SESSION['flash']=['type'=>'success','message'=>'Profile updated.'];
        header('Location: profile.php'); exit;
    }
    if (isset($_POST['change_password'])) {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        $res  = User::login($user['email'], $cur);
        if (!$res['success']) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>'Current password is incorrect.'];
        } elseif ($new !== $conf) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>'Passwords do not match.'];
        } else {
            $errs = validatePassword($new);
            if ($errs) {
                $_SESSION['flash'] = ['type'=>'danger','message'=>implode(' ', $errs)];
            } else {
                User::updatePassword($uid, $new);
                $_SESSION['flash'] = ['type'=>'success','message'=>'Password changed successfully.'];
            }
        }
        header('Location: profile.php'); exit;
    }
}

// User's own structures
$myStructures = getDB()->prepare(
    'SELECT address_code,resident_name,zone,status,created_at,view_count,share_token
     FROM structures WHERE registered_by=? ORDER BY created_at DESC LIMIT 20');
$myStructures->execute([$uid]);
$myStructures = $myStructures->fetchAll();

$pageTitle='My Profile';
require_once __DIR__.'/partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>My Profile</h1>
        <p>Account details and personal settings</p>
    </div>
</div>

<div class="grid-65">

    <!-- Left column -->
    <div>

        <!-- Profile info -->
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon"><i class="fas fa-circle-user"></i></div>
                    <div class="card-title">Profile Information</div>
                </div>
            </div>
            <div class="card-body">
                <!-- Avatar -->
                <?php
                $initials = strtoupper(substr($user['full_name'],0,1).
                    (strpos($user['full_name'],' ')!==false?substr(strstr($user['full_name'],' '),1,1):''));
                ?>
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;
                            padding-bottom:16px;border-bottom:1px solid var(--border)">
                    <div class="user-avatar" style="width:60px;height:60px;font-size:22px;
                                                    background:var(--c-mid)">
                        <?= $initials ?>
                    </div>
                    <div>
                        <div style="font-size:1.1rem;font-weight:700"><?= htmlspecialchars($user['full_name']) ?></div>
                        <div class="text-muted text-sm"><?= htmlspecialchars($user['email']) ?></div>
                        <span class="badge badge-<?= htmlspecialchars($user['role']) ?> mt-1">
                            <?= htmlspecialchars(str_replace('_',' ',$user['role'])) ?>
                        </span>
                    </div>
                </div>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control"
                                   value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($user['phone']??'') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        <div class="form-hint">Email cannot be changed here. Contact an admin.</div>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-floppy-disk"></i> Save Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon"><i class="fas fa-lock"></i></div>
                    <div class="card-title">Change Password</div>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" id="change-pwd-form">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" id="current-pwd"
                               class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                            <label class="form-label" style="margin-bottom:0">New Password</label>
                            <button type="button" onclick="genProfilePwd()"
                                    style="font-size:.73rem;color:var(--c-mid);background:none;border:none;
                                           cursor:pointer;padding:0;font-family:var(--font);font-weight:600;
                                           display:flex;align-items:center;gap:4px">
                                <i class="fas fa-wand-magic-sparkles"></i> Generate
                            </button>
                        </div>
                        <input type="password" name="new_password" id="profile-new-pwd"
                               class="form-control" required minlength="12" autocomplete="new-password">
                        <!-- Generated suggestion -->
                        <div id="profile-gen-box" style="display:none;margin-top:6px;
                             background:var(--info-bg);border:1px solid rgba(49,93,119,.2);
                             border-radius:5px;padding:9px 12px">
                            <div style="font-size:.7rem;font-weight:700;color:var(--c-dark);margin-bottom:5px">
                                <i class="fas fa-wand-magic-sparkles"></i> Suggested Password
                            </div>
                            <div style="display:flex;align-items:center;gap:8px">
                                <code id="profile-gen-pwd"
                                      style="font-family:var(--font-mono);font-size:.84rem;
                                             background:#fff;padding:5px 8px;border-radius:4px;
                                             border:1px solid var(--border);flex:1;word-break:break-all"></code>
                                <button type="button" onclick="useProfilePwd()" class="btn btn-primary btn-sm">
                                    <i class="fas fa-check"></i> Use
                                </button>
                                <button type="button" onclick="genProfilePwd()" class="btn btn-ghost btn-sm">
                                    <i class="fas fa-rotate"></i>
                                </button>
                            </div>
                            <div style="font-size:.7rem;color:var(--text-muted);margin-top:5px">
                                <i class="fas fa-triangle-exclamation"></i> Save this password before proceeding.
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="profile-conf-pwd"
                               class="form-control" required autocomplete="new-password">
                        <div id="profile-pwd-match" class="form-hint"></div>
                    </div>
                    <!-- Requirements -->
                    <div style="background:var(--body-bg);border-radius:5px;padding:10px 12px;
                                font-size:.76rem;margin-bottom:12px;border:1px solid var(--border)">
                        <div style="font-weight:700;color:var(--text-secondary);
                                    text-transform:uppercase;letter-spacing:.05em;
                                    font-size:.68rem;margin-bottom:6px">Requirements</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:3px 10px">
                            <div id="p-req-len"   class="pwd-req"><i class="fas fa-circle" style="font-size:6px"></i> 12+ characters</div>
                            <div id="p-req-upper" class="pwd-req"><i class="fas fa-circle" style="font-size:6px"></i> Uppercase (A-Z)</div>
                            <div id="p-req-lower" class="pwd-req"><i class="fas fa-circle" style="font-size:6px"></i> Lowercase (a-z)</div>
                            <div id="p-req-num"   class="pwd-req"><i class="fas fa-circle" style="font-size:6px"></i> Number (0-9)</div>
                            <div id="p-req-spc"   class="pwd-req" style="grid-column:span 2">
                                <i class="fas fa-circle" style="font-size:6px"></i> Symbol (!@#$%^&amp;*)
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Update Password
                    </button>
                </form>
            </div>
        </div>

        <!-- Account stats -->
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon"><i class="fas fa-chart-simple"></i></div>
                    <div class="card-title">Account Activity</div>
                </div>
            </div>
            <div class="card-body">
                <div class="grid-2" style="gap:12px">
                    <div style="background:var(--body-bg);border-radius:var(--radius-sm);padding:14px;text-align:center">
                        <div style="font-size:1.6rem;font-weight:700;color:var(--c-dark)"><?= count($myStructures) ?></div>
                        <div class="text-xs text-muted">My Structures</div>
                    </div>
                    <div style="background:var(--body-bg);border-radius:var(--radius-sm);padding:14px;text-align:center">
                        <div style="font-size:1.6rem;font-weight:700;color:var(--c-dark)"><?= $user['login_count'] ?></div>
                        <div class="text-xs text-muted">Total Logins</div>
                    </div>
                </div>
                <div class="divider"></div>
                <div class="text-sm text-muted">
                    <i class="fas fa-clock"></i>
                    Last login: <?= $user['last_login'] ? date('d M Y, H:i', strtotime($user['last_login'])) : 'First login' ?><br>
                    <i class="fas fa-calendar"></i>
                    Member since: <?= date('d M Y', strtotime($user['created_at'])) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: My structures -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon"><i class="fas fa-building"></i></div>
                    <div class="card-title">My Registered Structures</div>
                </div>
                <a href="<?= appUrl() ?>/views/register.php" class="btn btn-accent btn-sm">
                    <i class="fas fa-plus"></i> Add
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr><th>Code</th><th>Resident</th><th>Zone</th><th>Status</th><th>Views</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myStructures as $s): ?>
                        <tr>
                            <td>
                                <a href="<?= shareUrl($s) ?>"
                                   target="_blank"
                                   class="addr-code" style="text-decoration:none">
                                    <?= htmlspecialchars($s['address_code']) ?>
                                </a>
                            </td>
                            <td class="text-sm"><?= htmlspecialchars($s['resident_name']) ?></td>
                            <td class="text-xs text-muted"><?= htmlspecialchars($s['zone']??'—') ?></td>
                            <td><span class="badge badge-<?= htmlspecialchars($s['status']) ?>"><?= htmlspecialchars($s['status']) ?></span></td>
                            <td class="text-xs text-muted"><?= $s['view_count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$myStructures): ?>
                        <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">
                            No structures registered yet.
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__.'/partials/footer.php'; ?>
<style>
.pwd-req { display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-muted);transition:color .2s; }
.pwd-req.met { color:var(--success); }
.pwd-req.met i { color:var(--success); }
</style>
<script>
// Shared password generator (same algorithm as registration)
function _buildPassword() {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ', lower = 'abcdefghjkmnpqrstuvwxyz';
    const digits = '23456789', symbols = '!@#$%^&*-_=+?';
    const all = upper + lower + digits + symbols;
    let p = [
        upper[Math.floor(Math.random()*upper.length)],
        upper[Math.floor(Math.random()*upper.length)],
        lower[Math.floor(Math.random()*lower.length)],
        lower[Math.floor(Math.random()*lower.length)],
        digits[Math.floor(Math.random()*digits.length)],
        digits[Math.floor(Math.random()*digits.length)],
        symbols[Math.floor(Math.random()*symbols.length)],
        symbols[Math.floor(Math.random()*symbols.length)],
    ];
    while (p.length < 16) p.push(all[Math.floor(Math.random()*all.length)]);
    for (let i=p.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[p[i],p[j]]=[p[j],p[i]];}
    return p.join('');
}

function genProfilePwd() {
    const pwd = _buildPassword();
    document.getElementById('profile-gen-pwd').textContent = pwd;
    document.getElementById('profile-gen-box').style.display = 'block';
}

function useProfilePwd() {
    const pwd = document.getElementById('profile-gen-pwd').textContent;
    document.getElementById('profile-new-pwd').value  = pwd;
    document.getElementById('profile-conf-pwd').value = pwd;
    document.getElementById('profile-gen-box').style.display = 'none';
    document.getElementById('profile-new-pwd').dispatchEvent(new Event('input'));
    document.getElementById('profile-conf-pwd').dispatchEvent(new Event('input'));
}

// Requirement checklist for profile new password
document.getElementById('profile-new-pwd')?.addEventListener('input', function() {
    const v = this.value;
    const set = (id, ok) => document.getElementById(id)?.classList.toggle('met', ok);
    set('p-req-len',   v.length >= 12);
    set('p-req-upper', /[A-Z]/.test(v));
    set('p-req-lower', /[a-z]/.test(v));
    set('p-req-num',   /[0-9]/.test(v));
    set('p-req-spc',   /[^A-Za-z0-9]/.test(v));
});

// Confirm match
document.getElementById('profile-conf-pwd')?.addEventListener('input', function() {
    const el = document.getElementById('profile-pwd-match');
    const p1 = document.getElementById('profile-new-pwd').value;
    if (!this.value) { el.textContent = ''; return; }
    el.textContent  = this.value === p1 ? 'Passwords match' : 'Passwords do not match';
    el.style.color  = this.value === p1 ? 'var(--success)' : 'var(--danger)';
});
</script>
