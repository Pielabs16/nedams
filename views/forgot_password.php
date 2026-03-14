<?php
// ============================================================
// views/forgot_password.php  — Step 1: Enter email → send OTP
// ============================================================
require_once __DIR__.'/../config/app.php';
startSession();
if (!empty($_SESSION['user_id'])) { header('Location: '.appUrl().'/views/dashboard.php'); exit; }

$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT id, full_name, is_active FROM users WHERE email=? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && $user['is_active']) {
                sendOtp($user['id'], $email, 'password_reset');
                $_SESSION['otp_reset_email']   = $email;
                $_SESSION['otp_reset_user_id'] = $user['id'];
                header('Location: '.appUrl().'/views/reset_password.php'); exit;
            }
        } catch (Throwable $e) {}
        // Generic message to prevent email enumeration
        $success = 'If that email is registered, a 6-digit OTP has been sent to it.';
        $_SESSION['otp_reset_email']   = $email; // Still redirect so real users go through
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — <?= htmlspecialchars(appName()) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= appUrl() ?>/assets/css/style.css">
</head>
<body style="background:var(--body-bg);height:100vh;display:flex;align-items:center;justify-content:center">
<div style="width:100%;max-width:420px;padding:20px">

    <div style="text-align:center;margin-bottom:28px">
        <a href="<?= appUrl() ?>/views/login.php" style="display:inline-flex;align-items:center;gap:8px;
           text-decoration:none;color:var(--c-dark);font-size:.82rem;margin-bottom:20px">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
        <div style="width:50px;height:50px;background:var(--c-dark);border-radius:10px;
                    display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
            <i class="fas fa-lock-open" style="color:#fff;font-size:20px"></i>
        </div>
        <h1 style="font-size:1.3rem;font-weight:700;color:var(--c-darkest);margin-bottom:6px">
            Reset Password
        </h1>
        <p class="text-muted text-sm">Enter your registered email to receive a one-time password.</p>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if ($error): ?>
            <div class="alert alert-danger mb-2"><i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success mb-2"><i class="fas fa-check-circle"></i><div><?= htmlspecialchars($success) ?></div></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Registered Email Address</label>
                    <input type="email" name="email" class="form-control"
                           placeholder="your@email.com" required autofocus>
                </div>
                <button type="submit" name="send_otp" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i> Send OTP Code
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
