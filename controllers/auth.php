<?php
// ============================================================
// controllers/auth.php  — v2.1
// ============================================================
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../models/User.php';
startSession();

$action = $_REQUEST['action'] ?? '';

// ---- LOGIN --------------------------------------------------
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $_SESSION['flash'] = ['type'=>'danger','message'=>'Email and password are required.'];
        header('Location: '.appUrl().'/views/login.php'); exit;
    }

    $result = User::login($email, $password);
    if (!$result['success']) {
        $_SESSION['flash'] = ['type'=>'danger','message'=>$result['message']];
        header('Location: '.appUrl().'/views/login.php'); exit;
    }

    $user = $result['user'];

    // Email verification gate
    if (settingBool('security.require_email_verify', false) && !$user['email_verified']) {
        // Send a new verify OTP and redirect
        sendOtp($user['id'], $user['email'], 'email_verify');
        $_SESSION['pending_verify_id']    = $user['id'];
        $_SESSION['pending_verify_email'] = $user['email'];
        header('Location: '.appUrl().'/views/verify_email.php'); exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['flash']     = ['type'=>'success',
        'message'=>'Welcome back, '.explode(' ',$user['full_name'])[0].'!'];
    header('Location: '.appUrl().'/views/dashboard.php'); exit;
}

// ---- REGISTER -----------------------------------------------
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!settingBool('workflow.allow_self_register', true)) {
        $_SESSION['flash'] = ['type'=>'danger','message'=>'Self-registration is disabled.'];
        header('Location: '.appUrl().'/views/login.php'); exit;
    }

    $pwd  = $_POST['password']         ?? '';
    $pwd2 = $_POST['confirm_password'] ?? '';

    if ($pwd !== $pwd2) {
        $_SESSION['flash'] = ['type'=>'danger','message'=>'Passwords do not match.'];
        header('Location: '.appUrl().'/views/register_user.php'); exit;
    }

    $pwdErrors = validatePassword($pwd);
    if ($pwdErrors) {
        $_SESSION['flash'] = ['type'=>'danger','message'=>implode(' ', $pwdErrors)];
        header('Location: '.appUrl().'/views/register_user.php'); exit;
    }

    $phone = normalisePhone($_POST['phone'] ?? null);

    $result = User::register([
        'full_name' => $_POST['full_name'] ?? '',
        'email'     => $_POST['email']     ?? '',
        'phone'     => $phone,
        'password'  => $pwd,
        'role'      => setting('workflow.default_role', 'viewer'),
    ]);

    if (!$result['success']) {
        $_SESSION['flash'] = ['type'=>'danger','message'=>$result['message']];
        header('Location: '.appUrl().'/views/register_user.php'); exit;
    }

    // Send email verification OTP if required
    if (settingBool('security.require_email_verify', false)) {
        sendOtp($result['id'], strtolower(trim($_POST['email']??'')), 'email_verify');
        $_SESSION['pending_verify_id']    = $result['id'];
        $_SESSION['pending_verify_email'] = strtolower(trim($_POST['email']??''));
        header('Location: '.appUrl().'/views/verify_email.php'); exit;
    }

    $_SESSION['flash'] = ['type'=>'success','message'=>'Account created successfully. You can now sign in.'];
    header('Location: '.appUrl().'/views/login.php'); exit;
}

// ---- LOGOUT -------------------------------------------------
if ($action === 'logout') {
    auditLog('logout','auth','user',(string)($_SESSION['user_id']??''),'Logged out');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,
            $p['path'],$p['domain'],$p['secure'],$p['httponly']);
    }
    session_destroy();
    header('Location: '.appUrl().'/views/login.php'); exit;
}

header('Location: '.appUrl().'/views/login.php'); exit;
