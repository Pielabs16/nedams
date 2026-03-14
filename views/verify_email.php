<?php
// ============================================================
// views/verify_email.php  — OTP email verification
// ============================================================
require_once __DIR__.'/../config/app.php';
startSession();

$userId = $_SESSION['pending_verify_id']    ?? null;
$email  = $_SESSION['pending_verify_email'] ?? null;
if (!$userId || !$email) {
    header('Location: '.appUrl().'/views/login.php'); exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['otp_code'] ?? '');
    if (verifyOtpClean((int)$userId, $code, 'email_verify')) {
        getDB()->prepare('UPDATE users SET email_verified=1 WHERE id=?')->execute([$userId]);
        unset($_SESSION['pending_verify_id'], $_SESSION['pending_verify_email']);
        $_SESSION['flash'] = ['type'=>'success','message'=>'Email verified. You can now sign in.'];
        header('Location: '.appUrl().'/views/login.php'); exit;
    } else {
        $error = 'Invalid or expired code. Please try again or request a new one.';
    }
}

// Resend
if (isset($_GET['resend'])) {
    sendOtp((int)$userId, $email, 'email_verify');
    $notice = 'A new code has been sent to '.$email;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — <?= htmlspecialchars(appName()) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= appUrl() ?>/assets/css/style.css">
</head>
<body style="background:var(--body-bg);height:100vh;display:flex;align-items:center;justify-content:center">
<div style="width:100%;max-width:400px;padding:20px">

    <div style="text-align:center;margin-bottom:24px">
        <div style="width:52px;height:52px;background:var(--c-dark);border-radius:10px;
                    display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
            <i class="fas fa-envelope-open-text" style="color:#fff;font-size:20px"></i>
        </div>
        <h1 style="font-size:1.25rem;font-weight:700;color:var(--c-darkest);margin-bottom:4px">
            Verify Your Email
        </h1>
        <p class="text-muted text-sm">
            A 6-digit code was sent to<br>
            <strong><?= htmlspecialchars($email) ?></strong>
        </p>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger mb-2">
                <i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($notice)): ?>
            <div class="alert alert-info mb-2">
                <i class="fas fa-info-circle"></i><div><?= htmlspecialchars($notice) ?></div>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Enter 6-Digit Code</label>
                    <input type="text" name="otp_code" class="form-control"
                           style="font-family:var(--font-mono);font-size:1.5rem;text-align:center;
                                  letter-spacing:.3em"
                           placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                           required autofocus autocomplete="one-time-code">
                    <div class="form-hint">Code expires in 15 minutes.</div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-check"></i> Verify Email
                </button>
            </form>
        </div>
    </div>

    <div style="text-align:center;margin-top:14px;font-size:.82rem;color:var(--text-muted)">
        Didn't receive it?
        <a href="?resend=1" style="color:var(--c-mid)">Resend code</a>
        &nbsp;&middot;&nbsp;
        <a href="<?= appUrl() ?>/views/login.php" style="color:var(--text-muted)">
            Back to login
        </a>
    </div>
</div>
</body>
</html>
