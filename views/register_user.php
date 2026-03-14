<?php
// ============================================================
// views/register_user.php  — Self-registration
// ============================================================
require_once __DIR__.'/../config/app.php';
startSession();
if (!empty($_SESSION['user_id'])) { header('Location: '.appUrl().'/views/dashboard.php'); exit; }
if (!settingBool('workflow.allow_self_register', true)) {
    header('Location: '.appUrl().'/views/login.php'); exit;
}
$error = $_SESSION['flash']['message'] ?? null;
$etype = $_SESSION['flash']['type']    ?? 'danger';
unset($_SESSION['flash']);

$minLen = (int) setting('security.password_min_length', 8);
$reqNum = settingBool('security.password_require_number', true);
$reqSpc = settingBool('security.password_require_special', true);
$regNote= setting('workflow.registration_note','');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — <?= htmlspecialchars(appName()) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= appUrl() ?>/assets/css/style.css">
</head>
<body style="background:var(--body-bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
<div style="width:100%;max-width:480px">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:24px">
        <a href="<?= appUrl() ?>/views/login.php"
           style="display:inline-flex;align-items:center;gap:7px;text-decoration:none;
                  color:var(--text-muted);font-size:.82rem;margin-bottom:16px">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a><br>
        <div style="width:44px;height:44px;background:var(--c-dark);border-radius:9px;
                    display:inline-flex;align-items:center;justify-content:center;margin-bottom:10px">
            <i class="fas fa-map-pin" style="color:#fff;font-size:18px"></i>
        </div>
        <h1 style="font-size:1.25rem;font-weight:700;color:var(--c-darkest);margin-bottom:4px">
            Create Account
        </h1>
        <p class="text-muted text-sm">Join the NEDAMS community mapping programme</p>
    </div>

    <?php if ($regNote): ?>
    <!-- Registration context notice -->
    <div style="background:var(--c-darkest);color:rgba(255,255,255,.7);border-radius:8px;
                padding:14px 16px;font-size:.82rem;line-height:1.6;margin-bottom:16px">
        <div style="display:flex;align-items:flex-start;gap:10px">
            <i class="fas fa-info-circle" style="color:var(--c-light);margin-top:2px;flex-shrink:0"></i>
            <div><?= nl2br(htmlspecialchars($regNote)) ?></div>
        </div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.1)">
            <strong style="color:#fff">Account type: Viewer</strong>
            <span style="color:rgba(255,255,255,.45);font-size:.78rem;margin-left:8px">
                An admin can upgrade your role after review
            </span>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if ($error): ?>
            <div class="alert alert-<?= $etype ?> mb-2">
                <i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div>
            </div>
            <?php endif; ?>

            <form action="<?= appUrl() ?>/controllers/auth.php" method="POST"
                  id="reg-form" novalidate>
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">

                <div class="form-group">
                    <label class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="full_name" class="form-control"
                           placeholder="Your full name" required minlength="3">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email Address <span style="color:var(--danger)">*</span></label>
                        <div style="position:relative">
                            <input type="email" name="email" id="reg-email" class="form-control"
                                   placeholder="you@example.com" required autocomplete="email"
                                   oninput="checkEmail(this.value)">
                            <span id="email-status" style="position:absolute;right:10px;top:50%;
                                  transform:translateY(-50%);font-size:13px;display:none"></span>
                        </div>
                        <div id="email-msg" class="form-hint"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control"
                               id="phone-input" placeholder="07XX XXX XXX">
                        <div class="form-hint" id="phone-preview"></div>
                    </div>
                </div>

                <!-- Password fields -->
                <div class="form-group">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                        <label class="form-label" style="margin-bottom:0">
                            Password <span style="color:var(--danger)">*</span>
                        </label>
                        <button type="button" onclick="generatePassword()"
                                style="font-size:.73rem;color:var(--c-mid);background:none;border:none;
                                       cursor:pointer;padding:0;display:flex;align-items:center;gap:4px;
                                       font-family:var(--font);font-weight:600"
                                title="Generate a strong password">
                            <i class="fas fa-wand-magic-sparkles"></i> Generate
                        </button>
                    </div>
                    <div style="position:relative">
                        <input type="password" name="password" id="reg-pwd"
                               class="form-control" required
                               minlength="12" autocomplete="new-password"
                               style="padding-right:40px">
                        <button type="button" onclick="toggleRegPwd()"
                                style="position:absolute;right:11px;top:50%;transform:translateY(-50%);
                                       background:none;border:none;color:var(--text-muted);
                                       cursor:pointer;padding:0;font-size:13px">
                            <i class="fas fa-eye" id="reg-pwd-eye"></i>
                        </button>
                    </div>
                    <!-- Strength bar -->
                    <div id="pwd-strength" style="margin-top:6px;display:none">
                        <div style="height:5px;border-radius:3px;background:var(--border);overflow:hidden">
                            <div id="pwd-bar" style="height:100%;width:0;
                                 transition:width .35s,background .35s;border-radius:3px"></div>
                        </div>
                        <div id="pwd-msg" style="font-size:.72rem;margin-top:3px;
                                                  font-weight:600;color:var(--text-muted)"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" id="reg-pwd2"
                           class="form-control" required autocomplete="new-password">
                    <div id="pwd-match" class="form-hint"></div>
                </div>

                <!-- Password requirements checklist -->
                <div style="background:var(--body-bg);border-radius:6px;padding:12px 14px;
                            margin-bottom:16px;border:1px solid var(--border)">
                    <div style="font-size:.73rem;font-weight:700;color:var(--text-secondary);
                                text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">
                        Password Requirements
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 12px">
                        <div id="req-len"   class="pwd-req"><i class="fas fa-circle" style="font-size:6px"></i> At least 12 characters</div>
                        <div id="req-upper" class="pwd-req"><i class="fas fa-circle" style="font-size:6px"></i> Uppercase letter (A-Z)</div>
                        <div id="req-lower" class="pwd-req"><i class="fas fa-circle" style="font-size:6px"></i> Lowercase letter (a-z)</div>
                        <div id="req-num"   class="pwd-req"><i class="fas fa-circle" style="font-size:6px"></i> Number (0-9)</div>
                        <div id="req-spc"   class="pwd-req" style="grid-column:span 2">
                            <i class="fas fa-circle" style="font-size:6px"></i> Symbol (!&nbsp;@&nbsp;#&nbsp;$&nbsp;%&nbsp;^&nbsp;&amp;&nbsp;*)
                        </div>
                    </div>
                </div>

                <!-- Generated password suggestion box (hidden by default) -->
                <div id="generated-box" style="display:none;background:var(--info-bg);
                     border:1px solid rgba(49,93,119,.2);border-radius:6px;
                     padding:10px 14px;margin-bottom:14px">
                    <div style="font-size:.72rem;font-weight:700;color:var(--c-dark);
                                text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">
                        <i class="fas fa-wand-magic-sparkles"></i> Suggested Password
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <code id="generated-pwd"
                              style="font-family:var(--font-mono);font-size:.88rem;
                                     color:var(--c-darkest);background:#fff;
                                     padding:6px 10px;border-radius:4px;
                                     border:1px solid var(--border);flex:1;
                                     word-break:break-all;letter-spacing:.04em"></code>
                        <button type="button" onclick="useGeneratedPassword()"
                                class="btn btn-primary btn-sm" style="flex-shrink:0">
                            <i class="fas fa-check"></i> Use
                        </button>
                        <button type="button" onclick="generatePassword()"
                                class="btn btn-ghost btn-sm" style="flex-shrink:0"
                                title="Generate another">
                            <i class="fas fa-rotate"></i>
                        </button>
                    </div>
                    <div style="font-size:.72rem;color:var(--text-muted);margin-top:6px">
                        <i class="fas fa-triangle-exclamation"></i>
                        Save this password securely before proceeding.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
        </div>
        <div class="card-footer" style="text-align:center;font-size:.82rem">
            Already have an account?
            <a href="<?= appUrl() ?>/views/login.php">Sign in</a>
        </div>
    </div>
</div>

<style>
.pwd-req {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .76rem;
    color: var(--text-muted);
    transition: color .2s;
    line-height: 1.4;
}
.pwd-req.met       { color: var(--success); }
.pwd-req.met i     { color: var(--success); }
.pwd-req i         { flex-shrink: 0; }
</style>

<script>
const minLen = 12;
const regBase = '<?= appUrl() ?>';

// ---- Toggle password visibility ----------------------------
function toggleRegPwd() {
    const inp = document.getElementById('reg-pwd');
    const eye = document.getElementById('reg-pwd-eye');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    eye.className = inp.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// ---- Generate strong password ------------------------------
function generatePassword() {
    const upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // no I, O
    const lower   = 'abcdefghjkmnpqrstuvwxyz';    // no i, l, o
    const digits  = '23456789';                   // no 0, 1
    const symbols = '!@#$%^&*-_=+?';
    const all     = upper + lower + digits + symbols;

    // Guarantee at least one of each required type
    let pwd = [
        upper  [Math.floor(Math.random() * upper.length)],
        upper  [Math.floor(Math.random() * upper.length)],
        lower  [Math.floor(Math.random() * lower.length)],
        lower  [Math.floor(Math.random() * lower.length)],
        digits [Math.floor(Math.random() * digits.length)],
        digits [Math.floor(Math.random() * digits.length)],
        symbols[Math.floor(Math.random() * symbols.length)],
        symbols[Math.floor(Math.random() * symbols.length)],
    ];

    // Fill to 16 characters
    while (pwd.length < 16) {
        pwd.push(all[Math.floor(Math.random() * all.length)]);
    }

    // Shuffle using Fisher-Yates
    for (let i = pwd.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [pwd[i], pwd[j]] = [pwd[j], pwd[i]];
    }

    const result = pwd.join('');
    document.getElementById('generated-pwd').textContent = result;
    document.getElementById('generated-box').style.display = 'block';
    document.getElementById('generated-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ---- Apply generated password to inputs --------------------
function useGeneratedPassword() {
    const pwd   = document.getElementById('generated-pwd').textContent;
    const inp1  = document.getElementById('reg-pwd');
    const inp2  = document.getElementById('reg-pwd2');

    // Show password so user can see what's being applied
    inp1.type = 'text';
    document.getElementById('reg-pwd-eye').className = 'fas fa-eye-slash';

    inp1.value = pwd;
    inp2.value = pwd;

    // Trigger validation UI update
    inp1.dispatchEvent(new Event('input'));
    inp2.dispatchEvent(new Event('input'));

    document.getElementById('generated-box').style.display = 'none';
}

// ---- Strength meter & checklist ----------------------------
document.getElementById('reg-pwd')?.addEventListener('input', function() {
    const v     = this.value;
    const wrap  = document.getElementById('pwd-strength');
    const bar   = document.getElementById('pwd-bar');
    const msg   = document.getElementById('pwd-msg');

    wrap.style.display = v.length > 0 ? 'block' : 'none';

    const checks = {
        len:   v.length >= minLen,
        upper: /[A-Z]/.test(v),
        lower: /[a-z]/.test(v),
        num:   /[0-9]/.test(v),
        spc:   /[^A-Za-z0-9]/.test(v),
    };

    // Update requirement items
    document.getElementById('req-len')  ?.classList.toggle('met', checks.len);
    document.getElementById('req-upper')?.classList.toggle('met', checks.upper);
    document.getElementById('req-lower')?.classList.toggle('met', checks.lower);
    document.getElementById('req-num')  ?.classList.toggle('met', checks.num);
    document.getElementById('req-spc')  ?.classList.toggle('met', checks.spc);

    // Score: each rule = 1 pt, extra length = bonus
    let score = Object.values(checks).filter(Boolean).length;
    if (v.length >= 16) score++;
    if (v.length >= 20) score++;

    const pct  = Math.min(100, Math.round((score / 7) * 100));
    const steps = [
        { max: 20, color: '#b91c1c', label: 'Very weak'  },
        { max: 40, color: '#b91c1c', label: 'Weak'       },
        { max: 60, color: '#a05c00', label: 'Fair'       },
        { max: 80, color: '#2563eb', label: 'Strong'     },
        { max: 100,color: '#0d7c4a', label: 'Very strong'},
    ];
    const step = steps.find(s => pct <= s.max) || steps[4];

    bar.style.width      = pct + '%';
    bar.style.background = step.color;
    msg.textContent      = step.label;
    msg.style.color      = step.color;
});

// ---- Password match ----------------------------------------
document.getElementById('reg-pwd2')?.addEventListener('input', function() {
    const p1  = document.getElementById('reg-pwd').value;
    const el  = document.getElementById('pwd-match');
    if (!this.value) { el.textContent = ''; return; }
    if (this.value === p1) {
        el.textContent  = 'Passwords match';
        el.style.color  = 'var(--success)';
    } else {
        el.textContent  = 'Passwords do not match';
        el.style.color  = 'var(--danger)';
    }
});

// Phone preview
document.getElementById('phone-input')?.addEventListener('input', function() {
    const v = this.value.replace(/[^0-9]/g, '');
    const prev = document.getElementById('phone-preview');
    if (v.startsWith('07') && v.length === 10) {
        prev.textContent = 'Will be stored as: +256' + v.substring(1);
        prev.style.color = 'var(--success)';
    } else if (v.startsWith('256') && v.length === 12) {
        prev.textContent = 'Will be stored as: +' + v;
        prev.style.color = 'var(--success)';
    } else {
        prev.textContent = v.length > 0 ? 'Enter Ugandan number: 07XX XXX XXX' : '';
        prev.style.color = 'var(--warning)';
    }
});

// Email availability checker
let emailTimer = null;
let emailAvailable = null;

function checkEmail(value) {
    const icon = document.getElementById('email-status');
    const msg  = document.getElementById('email-msg');
    const inp  = document.getElementById('reg-email');
    if (!value || !value.includes('@')) {
        icon.style.display = 'none'; msg.textContent = ''; emailAvailable = null; return;
    }
    icon.style.display = 'inline';
    icon.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--text-muted)"></i>';
    msg.textContent = ''; emailAvailable = null;
    clearTimeout(emailTimer);
    emailTimer = setTimeout(() => {
        fetch(`<?= appUrl() ?>/api/check_email.php?email=${encodeURIComponent(value)}`)
            .then(r => r.json())
            .then(d => {
                icon.innerHTML = d.available
                    ? '<i class="fas fa-check-circle" style="color:var(--success)"></i>'
                    : '<i class="fas fa-times-circle" style="color:var(--danger)"></i>';
                msg.textContent     = d.message;
                msg.style.color     = d.available ? 'var(--success)' : 'var(--danger)';
                inp.style.borderColor = d.available ? 'var(--success)' : 'var(--danger)';
                emailAvailable = d.available;
            })
            .catch(() => { icon.style.display = 'none'; emailAvailable = null; });
    }, 500);
}

// Form submit validation
document.getElementById('reg-form').addEventListener('submit', function(e) {
    const pwd  = document.getElementById('reg-pwd').value;
    const pwd2 = document.getElementById('reg-pwd2').value;
    const errors = [];

    if (emailAvailable === false)       errors.push('That email is already registered.');
    if (pwd.length < minLen)            errors.push(`Password must be at least ${minLen} characters.`);
    if (!/[A-Z]/.test(pwd))             errors.push('Password must contain an uppercase letter.');
    if (!/[a-z]/.test(pwd))             errors.push('Password must contain a lowercase letter.');
    if (!/[0-9]/.test(pwd))             errors.push('Password must contain a number.');
    if (!/[^A-Za-z0-9]/.test(pwd))      errors.push('Password must contain a symbol.');
    if (pwd !== pwd2)                   errors.push('Passwords do not match.');

    if (errors.length) { e.preventDefault(); alert(errors.join('\n')); }
});
</script>
</body>
</html>
