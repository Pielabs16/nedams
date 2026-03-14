<?php
// ============================================================
// views/reset_password.php  — Step 2: Enter OTP + new password
// ============================================================
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../models/User.php';
startSession();
if (!empty($_SESSION['user_id'])) { header('Location: '.appUrl().'/views/dashboard.php'); exit; }

$email  = $_SESSION['otp_reset_email']   ?? null;
$userId = $_SESSION['otp_reset_user_id'] ?? null;
$error  = null;
$step   = 'otp'; // otp | newpwd

if (!$email) { header('Location: '.appUrl().'/views/forgot_password.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp'])) {
        $code = trim($_POST['otp_code'] ?? '');
        if (!$userId || !verifyOtpClean((int)$userId, $code, 'password_reset')) {
            $error = 'Invalid or expired OTP. Please try again or request a new one.';
        } else {
            $_SESSION['otp_reset_verified'] = true;
            $step = 'newpwd';
        }
    } elseif (isset($_POST['save_password']) && !empty($_SESSION['otp_reset_verified'])) {
        $newPwd  = $_POST['new_password']     ?? '';
        $confPwd = $_POST['confirm_password'] ?? '';
        $errs    = validatePassword($newPwd);
        if ($newPwd !== $confPwd) $errs[] = 'Passwords do not match.';
        if ($errs) {
            $error = implode(' ', $errs);
            $step  = 'newpwd';
        } else {
            User::updatePassword((int)$userId, $newPwd);
            unset($_SESSION['otp_reset_email'], $_SESSION['otp_reset_user_id'],
                  $_SESSION['otp_reset_verified']);
            $_SESSION['flash'] = ['type'=>'success','message'=>'Password reset successfully. You can now sign in.'];
            header('Location: '.appUrl().'/views/login.php'); exit;
        }
    }
}
if (!empty($_SESSION['otp_reset_verified'])) $step = 'newpwd';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — <?= htmlspecialchars(appName()) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= appUrl() ?>/assets/css/style.css">
</head>
<body style="background:var(--body-bg);height:100vh;display:flex;align-items:center;justify-content:center">
<div style="width:100%;max-width:420px;padding:20px">

    <div style="text-align:center;margin-bottom:24px">
        <div style="width:50px;height:50px;background:var(--c-dark);border-radius:10px;
                    display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
            <i class="fas fa-key" style="color:#fff;font-size:19px"></i>
        </div>
        <h1 style="font-size:1.25rem;font-weight:700;color:var(--c-darkest);margin-bottom:4px">
            <?= $step==='otp' ? 'Enter OTP Code' : 'Set New Password' ?>
        </h1>
        <p class="text-muted text-sm">
            <?= $step==='otp'
                ? 'A 6-digit code was sent to <strong>'.htmlspecialchars($email).'</strong>'
                : 'Choose a strong new password for your account.' ?>
        </p>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if ($error): ?>
            <div class="alert alert-danger mb-2">
                <i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($step === 'otp'): ?>
            <!-- Step 1: OTP Entry -->
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">6-Digit OTP Code</label>
                    <input type="text" name="otp_code" class="form-control"
                           style="font-family:var(--font-mono);font-size:1.4rem;text-align:center;
                                  letter-spacing:.25em"
                           placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                           required autofocus autocomplete="one-time-code">
                    <div class="form-hint">Code expires in 15 minutes.</div>
                </div>
                <button type="submit" name="verify_otp" class="btn btn-primary btn-block">
                    <i class="fas fa-check"></i> Verify Code
                </button>
            </form>
            <div style="text-align:center;margin-top:14px">
                <a href="<?= appUrl() ?>/views/forgot_password.php"
                   style="font-size:.8rem;color:var(--text-muted)">
                    <i class="fas fa-rotate"></i> Request new OTP
                </a>
            </div>

            <?php else: ?>
            <!-- Step 2: New Password -->
            <form method="POST">
                <?php
                $minLen = (int)setting('security.password_min_length', 8);
                $reqNum = settingBool('security.password_require_number', true);
                $reqSpc = settingBool('security.password_require_special', true);
                ?>
                <div class="alert alert-info mb-2">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        Password must be at least <?= $minLen ?> characters
                        <?= $reqNum ? ', include a number' : '' ?>
                        <?= $reqSpc ? ', and a special character' : '' ?>.
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control"
                           minlength="<?= $minLen ?>" required autofocus autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                           required autocomplete="new-password">
                </div>
                <button type="submit" name="save_password" class="btn btn-primary btn-block">
                    <i class="fas fa-floppy-disk"></i> Save New Password
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div style="text-align:center;margin-top:14px">
        <a href="<?= appUrl() ?>/views/login.php" style="font-size:.8rem;color:var(--text-muted)">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </div>
</div>
</body>
</html>
