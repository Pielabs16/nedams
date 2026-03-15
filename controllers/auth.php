<?php
// ============================================================
// controllers/auth.php  — v2.1 Hardened
// Handles: login (with RBA), register, logout, rba_verify
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/User.php';
startSession();

$action = $_REQUEST['action'] ?? '';
$ip     = $_SERVER['REMOTE_ADDR']     ?? '127.0.0.1';
$ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';

// ============================================================
// LOGIN
// ============================================================
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Email and password are required.'];
        header('Location: ' . appUrl() . '/views/login.php'); exit;
    }

    // ---- CSRF check -----------------------------------------
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['_csrf'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid request. Please try again.'];
        header('Location: ' . appUrl() . '/views/login.php'); exit;
    }

    // ---- Attempt login via model ----------------------------
    $result = User::login($email, $password);

    // ---- Log the attempt ------------------------------------
    _logLoginAttempt($email, $ip, $ua, $result['success'] ? 'success' : 'fail');

    if (!$result['success']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => $result['message']];
        header('Location: ' . appUrl() . '/views/login.php'); exit;
    }

    $user = $result['user'];

    // ---- Email verification gate ----------------------------
    if (settingBool('security.require_email_verify', false) && !$user['email_verified']) {
        sendOtp($user['id'], $user['email'], 'email_verify');
        $_SESSION['pending_verify_id']    = $user['id'];
        $_SESSION['pending_verify_email'] = $user['email'];
        header('Location: ' . appUrl() . '/views/verify_email.php'); exit;
    }

    // ---- Risk-Based Authentication (RBA) -------------------
    if (settingBool('security.enable_rba', true)) {
        $risk = assessLoginRisk((int)$user['id'], $ip, $ua);

        if ($risk['challenge']) {
            // Issue challenge — store pending user in session, redirect to RBA page
            $challengeId = issueRbaChallenge(
                (int)$user['id'], $user['email'], $ip, $ua, $risk['reasons']
            );
            _logLoginAttempt($email, $ip, $ua, 'rba_challenge');
            auditLog('rba_challenge_issued', 'security', 'user',
                (string)$user['id'],
                'Risk score ' . $risk['score'] . ' — reasons: ' . implode(', ', $risk['reasons']));

            // Store minimal pending state — not logged in yet
            $_SESSION['rba_pending'] = [
                'user_id'      => (int)$user['id'],
                'challenge_id' => $challengeId,
                'email'        => $user['email'],
                'risk_reasons' => $risk['reasons'],
                'expires'      => time() + 900,
            ];
            header('Location: ' . appUrl() . '/views/rba_challenge.php'); exit;
        }
    }

    // ---- All checks passed — establish session --------------
    _establishSession($user, $ip, $ua);
    header('Location: ' . appUrl() . '/views/dashboard.php'); exit;
}

// ============================================================
// RBA CHALLENGE VERIFICATION
// ============================================================
if ($action === 'rba_verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $pending = $_SESSION['rba_pending'] ?? null;
    if (!$pending || time() > ($pending['expires'] ?? 0)) {
        unset($_SESSION['rba_pending']);
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Verification session expired. Please sign in again.'];
        header('Location: ' . appUrl() . '/views/login.php'); exit;
    }

    $code        = trim($_POST['rba_code'] ?? '');
    $challengeId = (int)$pending['challenge_id'];
    $userId      = (int)$pending['user_id'];

    if (!$code) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please enter the verification code.'];
        header('Location: ' . appUrl() . '/views/rba_challenge.php'); exit;
    }

    $ok = verifyRbaChallenge($challengeId, $code);

    if (!$ok) {
        auditLog('rba_challenge_failed', 'security', 'user', (string)$userId,
            'Incorrect RBA code entered from ' . $ip);
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid or expired verification code. Please try again.'];
        header('Location: ' . appUrl() . '/views/rba_challenge.php'); exit;
    }

    // ---- Challenge passed — load user and establish session -
    require_once __DIR__ . '/../models/User.php';
    $user = User::findById($userId);
    if (!$user || !$user['is_active']) {
        unset($_SESSION['rba_pending']);
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Account not found or disabled.'];
        header('Location: ' . appUrl() . '/views/login.php'); exit;
    }

    auditLog('rba_challenge_passed', 'security', 'user', (string)$userId,
        'Extra verification passed from ' . $ip);

    unset($_SESSION['rba_pending']);
    _establishSession($user, $ip, $ua);
    header('Location: ' . appUrl() . '/views/dashboard.php'); exit;
}

// ============================================================
// REGISTER
// ============================================================
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!settingBool('workflow.allow_self_register', true)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Self-registration is disabled.'];
        header('Location: ' . appUrl() . '/views/login.php'); exit;
    }

    $pwd  = $_POST['password']         ?? '';
    $pwd2 = $_POST['confirm_password'] ?? '';

    if ($pwd !== $pwd2) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Passwords do not match.'];
        header('Location: ' . appUrl() . '/views/register_user.php'); exit;
    }

    $pwdErrors = validatePassword($pwd);
    if ($pwdErrors) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => implode(' ', $pwdErrors)];
        header('Location: ' . appUrl() . '/views/register_user.php'); exit;
    }

    $result = User::register([
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email'     => strtolower(trim($_POST['email'] ?? '')),
        'phone'     => normalisePhone($_POST['phone'] ?? null),
        'password'  => $pwd,
        'role'      => setting('workflow.default_role', 'viewer'),
    ]);

    if (!$result['success']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => $result['message']];
        header('Location: ' . appUrl() . '/views/register_user.php'); exit;
    }

    if (settingBool('security.require_email_verify', false)) {
        sendOtp($result['id'], strtolower(trim($_POST['email'] ?? '')), 'email_verify');
        $_SESSION['pending_verify_id']    = $result['id'];
        $_SESSION['pending_verify_email'] = strtolower(trim($_POST['email'] ?? ''));
        header('Location: ' . appUrl() . '/views/verify_email.php'); exit;
    }

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account created. You can now sign in.'];
    header('Location: ' . appUrl() . '/views/login.php'); exit;
}

// ============================================================
// LOGOUT
// ============================================================
if ($action === 'logout') {

    $uid          = (int)($_SESSION['user_id']       ?? 0);
    $sessionToken = $_SESSION['_session_token'] ?? '';

    // Audit before destroying session
    if ($uid) {
        auditLog('logout', 'auth', 'user', (string)$uid, 'User logged out from ' . $ip);
    }

    // Revoke session in registry
    if ($sessionToken) {
        try {
            getDB()->prepare(
                "UPDATE session_registry SET is_active=0, revoked_reason='logout'
                 WHERE session_token=? AND user_id=?"
            )->execute([$sessionToken, $uid]);
        } catch (Throwable $e) {}
    }

    // Destroy server-side session
    $_SESSION = [];

    // Delete the session cookie on the client
    $cookieName = session_name();
    if (isset($_COOKIE[$cookieName])) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        setcookie(
            $cookieName, '',
            [
                'expires'  => time() - 86400,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );
    }

    session_destroy();

    header('Location: ' . appUrl() . '/views/login.php'); exit;
}

// ---- Fallback ----------------------------------------------
header('Location: ' . appUrl() . '/views/login.php'); exit;


// ============================================================
// PRIVATE HELPERS
// ============================================================

/**
 * Establish authenticated session after all checks passed.
 * - Regenerates session ID (prevents session fixation)
 * - Stores 256-bit session token in registry
 * - Binds session to IP + UA fingerprint
 * - Sets idle + absolute expiry markers
 */
function _establishSession(array $user, string $ip, string $ua): void {

    // 1. Regenerate the session ID immediately — session fixation prevention
    session_regenerate_id(true);

    // 2. Register session and get high-entropy token
    $sessionToken = registerSession((int)$user['id']);

    // 3. Set all session data
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['user_name']     = $user['full_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];

    // Security markers
    $_SESSION['_session_token']  = $sessionToken;
    $_SESSION['_fingerprint']    = _sessionFingerprint();
    $_SESSION['_last_active']    = time();
    $_SESSION['_abs_expiry']     = time() + max(3600, (int)setting('security.absolute_timeout', 18000));
    $_SESSION['_login_ip']       = $ip;

    // 4. Audit log
    auditLog('login', 'auth', 'user', (string)$user['id'],
        'Successful login from ' . $ip . ' — role: ' . $user['role']);

    $_SESSION['flash'] = [
        'type'    => 'success',
        'message' => 'Welcome back, ' . htmlspecialchars(explode(' ', $user['full_name'])[0]) . '!',
    ];
}

/** Write a login attempt row for analytics and RBA history */
function _logLoginAttempt(string $email, string $ip, string $ua, string $status): void {
    try {
        getDB()->prepare(
            'INSERT INTO login_attempts (email, ip_address, user_agent, status)
             VALUES (?, ?, ?, ?)'
        )->execute([$email, $ip, substr($ua, 0, 512), $status]);
    } catch (Throwable $e) {}
}
