<?php
// ============================================================
// views/admin/users.php  — User management
// ============================================================
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../models/User.php';
requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['create_user'])) {
        if (($_POST['password']??'') !== ($_POST['confirm_password']??'')) {
            $_SESSION['flash']=['type'=>'danger','message'=>'Passwords do not match.'];
        } else {
            $r = User::register($_POST);
            $_SESSION['flash'] = $r['success']
                ? ['type'=>'success','message'=>'User created successfully.']
                : ['type'=>'danger','message'=>$r['message']];
        }
        header('Location: users.php'); exit;
    }
    if (isset($_POST['edit_user_id'])) {
        $editId = (int)$_POST['edit_user_id'];
        $fields = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'phone'     => trim($_POST['phone']     ?? ''),
            'role'      => $_POST['role']            ?? 'viewer',
            'is_active' => (int)($_POST['is_active'] ?? 1),
        ];
        // Super admin can also update email
        if (isSuperAdmin() && !empty($_POST['email'])) {
            $newEmail = strtolower(trim($_POST['email']));
            if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                // Check not already taken by another user
                $chk = getDB()->prepare('SELECT COUNT(*) FROM users WHERE email=? AND id!=?');
                $chk->execute([$newEmail, $editId]);
                if ((int)$chk->fetchColumn() === 0) {
                    $fields['email'] = $newEmail;
                } else {
                    $_SESSION['flash'] = ['type'=>'danger','message'=>'That email is already in use by another account.'];
                    header('Location: users.php'); exit;
                }
            }
        }
        User::update($editId, $fields);
        if (!empty($_POST['new_password']) && strlen($_POST['new_password']) >= 8)
            User::updatePassword($editId, $_POST['new_password']);
        $_SESSION['flash'] = ['type'=>'success','message'=>'User updated.'];
        header('Location: users.php'); exit;
    }
}
if (isset($_GET['toggle'])) {
    User::toggleActive((int)$_GET['toggle']);
    header('Location: users.php'); exit;
}

$filters = ['q'=>$_GET['q']??'','role'=>$_GET['role']??''];
$users   = User::all($filters);
$pageTitle='Manage Users';
require_once __DIR__.'/../partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/../partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/../partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
    <div class="page-header-left">
        <h1>Manage Users</h1>
        <p><?= count($users) ?> registered accounts</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-accent" onclick="openModal('modal-create-user')">
            <i class="fas fa-user-plus"></i> Add User
        </button>
    </div>
</div>

<div id="flash-zone"></div>

<!-- Filter bar -->
<div class="card mb-3">
    <div class="card-body" style="padding:12px 16px">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="q" class="form-control" style="max-width:280px"
                   placeholder="Search name, email, phone..." value="<?= htmlspecialchars($_GET['q']??'') ?>">
            <select name="role" class="form-control" style="max-width:160px">
                <option value="">All Roles</option>
                <option value="super_admin"  <?= ($_GET['role']??'')==='super_admin'?'selected':'' ?>>Super Admin</option>
                <option value="admin"        <?= ($_GET['role']??'')==='admin'?'selected':'' ?>>Admin</option>
                <option value="mapper"       <?= ($_GET['role']??'')==='mapper'?'selected':'' ?>>Mapper</option>
                <option value="viewer"       <?= ($_GET['role']??'')==='viewer'?'selected':'' ?>>Viewer</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="users.php" class="btn btn-ghost">Clear</a>
        </form>
    </div>
</div>

<!-- Users table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th><th>Role</th><th>Phone</th>
                    <th>Structures</th><th>Last Login</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u):
                    // Count their structures
                    $uStructures = 0;
                    try { $uStructures=(int)getDB()->prepare('SELECT COUNT(*) FROM structures WHERE registered_by=?')->execute([$u['id']])?
                          (int)getDB()->query('SELECT COUNT(*) FROM structures WHERE registered_by='.(int)$u['id'])->fetchColumn():0;
                    } catch(Throwable $e){}
                    $initials = strtoupper(substr($u['full_name'],0,1).(strpos($u['full_name'],' ')!==false?substr(strstr($u['full_name'],' '),1,1):''));
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-center gap-2">
                            <div class="user-avatar" style="width:34px;height:34px;font-size:12px;background:var(--c-mid)">
                                <?= $initials ?>
                            </div>
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($u['full_name']) ?></div>
                                <div class="text-muted text-xs"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-<?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars(str_replace('_',' ',$u['role'])) ?></span></td>
                    <td class="text-sm"><?= htmlspecialchars($u['phone']??'—') ?></td>
                    <td><?= $uStructures ?></td>
                    <td class="text-xs text-muted">
                        <?= $u['last_login'] ? date('d M Y H:i',strtotime($u['last_login'])) : 'Never' ?>
                        <?php if ($u['login_count']>0): ?>
                        <div>(<?= $u['login_count'] ?> logins)</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $u['is_active']?'badge-active':'badge-inactive' ?>">
                            <?= $u['is_active']?'Active':'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-ghost btn-sm btn-icon"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)"
                                    title="Edit">
                                <i class="fas fa-pencil"></i>
                            </button>
                            <a href="?toggle=<?= $u['id'] ?>" title="<?= $u['is_active']?'Deactivate':'Activate' ?>"
                               class="btn btn-<?= $u['is_active']?'warning':'success' ?> btn-sm btn-icon">
                                <i class="fas fa-<?= $u['is_active']?'user-slash':'user-check' ?>"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal-overlay" id="modal-create-user">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New User</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="viewer">Viewer</option>
                        <option value="mapper">Mapper</option>
                        <option value="developer">Developer</option>
                        <option value="admin">Admin</option>
                        <?php if (isSuperAdmin()): ?>
                        <option value="super_admin">Super Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                <button type="submit" name="create_user" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Create User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="modal-edit-user">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-pencil"></i> Edit User</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="edit_user_id" id="edit-user-id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="edit-full-name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" id="edit-phone" class="form-control">
                    </div>
                </div>

                <?php if (isSuperAdmin()): ?>
                <!-- Email — super admin only -->
                <div class="form-group">
                    <label class="form-label">
                        Email Address
                        <span class="badge badge-super_admin" style="font-size:.6rem;margin-left:6px">
                            Super Admin Only
                        </span>
                    </label>
                    <div style="position:relative">
                        <input type="email" name="email" id="edit-email"
                               class="form-control" autocomplete="off"
                               oninput="checkEditEmail(this.value)">
                        <span id="edit-email-icon"
                              style="position:absolute;right:10px;top:50%;
                                     transform:translateY(-50%);font-size:13px;display:none"></span>
                    </div>
                    <div id="edit-email-msg" class="form-hint"></div>
                </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit-role" class="form-control">
                            <option value="viewer">Viewer</option>
                            <option value="mapper">Mapper</option>
                            <option value="developer">Developer</option>
                            <option value="admin">Admin</option>
                            <?php if (isSuperAdmin()): ?>
                            <option value="super_admin">Super Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" id="edit-active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        New Password
                        <span class="text-muted text-xs">(leave blank to keep existing)</span>
                    </label>
                    <input type="password" name="new_password" class="form-control"
                           minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__.'/../partials/footer.php'; ?>
<script>
// Populate edit modal
function openEditModal(u) {
    document.getElementById('edit-user-id').value   = u.id;
    document.getElementById('edit-full-name').value  = u.full_name;
    document.getElementById('edit-phone').value      = u.phone || '';
    document.getElementById('edit-role').value       = u.role;
    document.getElementById('edit-active').value     = u.is_active;

    const emailInp = document.getElementById('edit-email');
    if (emailInp) {
        emailInp.value = u.email || '';
        emailInp.dataset.userId = u.id;
        // Reset checker state
        document.getElementById('edit-email-icon').style.display = 'none';
        document.getElementById('edit-email-msg').textContent    = '';
        emailInp.style.borderColor = '';
    }
    openModal('modal-edit-user');
}

// Live email checker for edit modal (excludes current user)
let editEmailTimer = null;

function checkEditEmail(value) {
    const icon   = document.getElementById('edit-email-icon');
    const msg    = document.getElementById('edit-email-msg');
    const inp    = document.getElementById('edit-email');
    const userId = inp.dataset.userId || 0;

    if (!value || !value.includes('@')) {
        icon.style.display = 'none';
        msg.textContent    = '';
        inp.style.borderColor = '';
        return;
    }

    icon.style.display = 'inline';
    icon.innerHTML     = '<i class="fas fa-spinner fa-spin" style="color:var(--text-muted)"></i>';
    msg.textContent    = '';

    clearTimeout(editEmailTimer);
    editEmailTimer = setTimeout(() => {
        fetch(`<?= appUrl() ?>/api/check_email.php?email=${encodeURIComponent(value)}&exclude_id=${userId}`)
            .then(r => r.json())
            .then(d => {
                if (d.available) {
                    icon.innerHTML        = '<i class="fas fa-check-circle" style="color:var(--success)"></i>';
                    msg.style.color       = 'var(--success)';
                    inp.style.borderColor = 'var(--success)';
                } else {
                    icon.innerHTML        = '<i class="fas fa-times-circle" style="color:var(--danger)"></i>';
                    msg.style.color       = 'var(--danger)';
                    inp.style.borderColor = 'var(--danger)';
                }
                msg.textContent = d.message;
            })
            .catch(() => { icon.style.display = 'none'; });
    }, 500);
}
</script>
