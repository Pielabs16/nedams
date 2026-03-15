<?php
// ============================================================
// config/app.php  — NEDAMS v2.1
// Credentials loaded from config/env.php (never committed).
// ============================================================

define('NEDAMS_VERSION', '2.1.0');
define('BCRYPT_COST',    12);
define('SESSION_NAME',   'nedams_sess');

// ---- Load credentials from env.php -------------------------
// env.php is in .gitignore — never committed to version control.
$_envPath = __DIR__ . '/env.php';
if (file_exists($_envPath)) {
    require_once $_envPath;
} else {
    // Fallback for first-run / CI — all blank, system prompts setup
    define('DB_HOST',    'localhost');
    define('DB_PORT',    '3306');
    define('DB_NAME',    'nedams');
    define('DB_USER',    'root');
    define('DB_PASS',    '');
    define('DB_CHARSET', 'utf8mb4');
    define('APP_KEY',    'INSECURE_DEFAULT_CHANGE_IN_env.php');
    define('SMTP_HOST',  '');
    define('SMTP_PORT',  587);
    define('SMTP_USER',  '');
    define('SMTP_PASS',  '');
    define('SMTP_FROM',  '');
    define('SMTP_FROM_NAME', 'NEDAMS');
}

// ---- PDO singleton ------------------------------------------
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.
               ";dbname=".DB_NAME.";charset=".DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'Database unavailable.']);
            exit;
        }
    }
    return $pdo;
}

// ---- Settings cache -----------------------------------------
function loadSettings(): void {
    if (isset($GLOBALS['_settings'])) return;
    $GLOBALS['_settings'] = [];
    try {
        $rows = getDB()->query('SELECT `group`,`key`,`value` FROM settings')->fetchAll();
        foreach ($rows as $r) {
            $GLOBALS['_settings'][$r['group']][$r['key']] = $r['value'];
        }
    } catch (Throwable $e) {}
}

function setting(string $key, mixed $default = null): mixed {
    loadSettings();
    if (str_contains($key, '.')) {
        [$group, $k] = explode('.', $key, 2);
        return $GLOBALS['_settings'][$group][$k] ?? $default;
    }
    foreach ($GLOBALS['_settings'] as $grp) {
        if (isset($grp[$key])) return $grp[$key];
    }
    return $default;
}

function settingBool(string $key, bool $default = false): bool {
    $v = setting($key);
    return $v === null ? $default : (bool)(int)$v;
}

function saveSetting(string $group, string $key, mixed $value): void {
    getDB()->prepare(
        'INSERT INTO settings (`group`,`key`,`value`) VALUES(?,?,?)
         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_at=NOW()'
    )->execute([$group, $key, $value]);
    unset($GLOBALS['_settings']);
}

function appUrl(): string    { return rtrim(setting('general.app_url','http://localhost/nedams'),'/'); }
function appName(): string   { return setting('general.app_name','NEDAMS'); }
function gmapsKey(): string  { return setting('maps.gmaps_api_key',''); }

// ---- Session -----------------------------------------------
function startSession(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    // Determine if request is over HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Force HTTPS redirect if enabled in settings (skip for CLI)
    if (PHP_SAPI !== 'cli' && !$isHttps) {
        try {
            if (settingBool('security.force_https', false)) {
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $uri  = $_SERVER['REQUEST_URI'] ?? '/';
                header('Location: https://' . $host . $uri, true, 301);
                exit;
            }
        } catch (Throwable $e) {} // settings table may not exist yet on first install
    }

    session_name(SESSION_NAME);

    // ---- Hardened cookie parameters ----
    // Idle timeout from settings (default 15 minutes = 900 sec)
    $idleTimeout = max(300, (int)(function_exists('setting') ? setting('security.idle_timeout', 900) : 900));

    session_set_cookie_params([
        'lifetime' => 0,             // Session cookie — expires on browser close
        'path'     => '/',
        'domain'   => '',            // Current domain only
        'secure'   => $isHttps,      // HTTPS only when on HTTPS
        'httponly' => true,          // No JS access — blocks XSS cookie theft
        'samesite' => 'Strict',      // Blocks CSRF via cross-site requests
    ]);

    // Use high-entropy session IDs (PHP default is 26 chars; force 48 hex = 192 bits)
    ini_set('session.entropy_length',      48);
    ini_set('session.hash_function',       'sha256');
    ini_set('session.use_strict_mode',     '1'); // Reject unrecognised session IDs
    ini_set('session.use_only_cookies',    '1'); // Never accept SID in URL
    ini_set('session.use_trans_sid',       '0');
    ini_set('session.cookie_httponly',     '1');
    ini_set('session.cookie_samesite',     'Strict');
    ini_set('session.gc_maxlifetime',      (string)$idleTimeout);

    session_start();

    // ---- Add HSTS header if enabled ----
    try {
        if ($isHttps && settingBool('security.enable_hsts', false)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    } catch (Throwable $e) {}

    // ---- Idle timeout enforcement ----
    if (isset($_SESSION['user_id'])) {
        $now       = time();
        $lastSeen  = (int)($_SESSION['_last_active'] ?? 0);
        $absExpiry = (int)($_SESSION['_abs_expiry']  ?? 0);

        // Idle: no activity for $idleTimeout seconds
        if ($lastSeen && ($now - $lastSeen) > $idleTimeout) {
            _destroySession('idle_timeout');
            return;
        }

        // Absolute: session older than absolute_timeout regardless of activity
        $absTimeout = max(3600, (int)(function_exists('setting') ? setting('security.absolute_timeout', 18000) : 18000));
        if ($absExpiry && $now > $absExpiry) {
            _destroySession('absolute_timeout');
            return;
        }

        // ---- Session binding: IP + User-Agent ----
        try {
            if (settingBool('security.session_binding', true)) {
                $currentFingerprint = _sessionFingerprint();
                if (isset($_SESSION['_fingerprint']) && !hash_equals($_SESSION['_fingerprint'], $currentFingerprint)) {
                    // Fingerprint changed — possible session hijack
                    auditLog('session_hijack_attempt', 'security', 'session',
                        (string)$_SESSION['user_id'],
                        'Session fingerprint mismatch — session terminated');
                    _destroySession('fingerprint_mismatch');
                    return;
                }
            }
        } catch (Throwable $e) {}

        $_SESSION['_last_active'] = $now;
    }
}

// ---- Compute session fingerprint (IP + UA hash) ------------
function _sessionFingerprint(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash_hmac('sha256', $ip . '|' . substr($ua, 0, 256), APP_KEY);
}

// ---- Destroy session cleanly --------------------------------
function _destroySession(string $reason = ''): void {
    if (isset($_SESSION['user_id']) && $reason) {
        try {
            auditLog('session_expired', 'security', 'session',
                (string)$_SESSION['user_id'], 'Session terminated: ' . $reason);
            // Revoke in registry
            getDB()->prepare(
                "UPDATE session_registry SET is_active=0, revoked_reason=?
                 WHERE session_token=? AND user_id=?"
            )->execute([$reason, $_SESSION['_session_token'] ?? '', $_SESSION['user_id']]);
        } catch (Throwable $e) {}
    }
    $msg = match($reason) {
        'idle_timeout'       => 'Your session expired due to inactivity. Please sign in again.',
        'absolute_timeout'   => 'Your session has exceeded the maximum duration. Please sign in again.',
        'fingerprint_mismatch' => 'Security alert: your session was invalidated. Please sign in again.',
        default              => 'Your session has ended. Please sign in again.',
    };
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    session_name(SESSION_NAME);
    $_SESSION['flash'] = ['type' => 'warning', 'message' => $msg];
}

// ---- Register new session in session_registry --------------
function registerSession(int $userId): string {
    $token    = bin2hex(random_bytes(32)); // 256-bit token
    $absLimit = time() + max(3600, (int)setting('security.absolute_timeout', 18000));
    try {
        getDB()->prepare(
            'INSERT INTO session_registry
             (session_token, user_id, ip_address, user_agent_hash, absolute_expiry)
             VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))'
        )->execute([
            $token,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''),
            $absLimit,
        ]);
        // Enforce concurrent session limit
        $limit = (int)setting('security.concurrent_session_limit', 3);
        if ($limit > 0) {
            getDB()->prepare(
                "UPDATE session_registry
                 SET is_active=0, revoked_reason='concurrent_limit'
                 WHERE user_id=? AND is_active=1 AND session_token != ?
                 ORDER BY last_active ASC
                 LIMIT " . max(0, $limit - 1)
            )->execute([$userId, $token]);
        }
    } catch (Throwable $e) {}
    return $token;
}

// ---- Auth guards -------------------------------------------
function requireLogin(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: '.appUrl().'/views/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!in_array($_SESSION['role']??'', ['admin','super_admin'])) {
        http_response_code(403);
        include __DIR__.'/../views/403.php'; exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['role']??'', $roles)) {
        http_response_code(403);
        include __DIR__.'/../views/403.php'; exit;
    }
}

function isSuperAdmin(): bool { return ($_SESSION['role'] ?? '') === 'super_admin'; }
function isAdmin(): bool      { return in_array($_SESSION['role']??'', ['admin','super_admin']); }
function isDeveloper(): bool  { return in_array($_SESSION['role']??'', ['developer','admin','super_admin']); }

// ---- Nav permission check ----------------------------------
// Cached per request
function canAccessNav(string $navKey): bool {
    static $perms = null;
    if ($perms === null) {
        startSession();
        $role = $_SESSION['role'] ?? 'viewer';
        try {
            $stmt = getDB()->prepare(
                'SELECT nav_key, is_allowed FROM role_permissions WHERE role=?'
            );
            $stmt->execute([$role]);
            $perms = [];
            foreach ($stmt->fetchAll() as $row) {
                $perms[$row['nav_key']] = (bool)$row['is_allowed'];
            }
        } catch (Throwable $e) {
            $perms = [];
        }
    }
    // super_admin always has access
    if (($_SESSION['role']??'') === 'super_admin') return true;
    return $perms[$navKey] ?? false;
}

// ---- Risk-Based Authentication (RBA) -----------------------

/**
 * Assess login risk. Returns array with:
 *   risk_level: 'low' | 'medium' | 'high'
 *   reasons:    array of human-readable risk signals
 *   challenge:  bool — should we issue an extra challenge?
 */
function assessLoginRisk(int $userId, string $ip, string $ua): array {
    if (!settingBool('security.enable_rba', true)) {
        return ['risk_level' => 'low', 'reasons' => [], 'challenge' => false];
    }
    $reasons = [];
    $score   = 0;
    $pdo     = getDB();

    // 1. New IP address?
    if (settingBool('security.rba_on_new_ip', true)) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE email=(SELECT email FROM users WHERE id=? LIMIT 1)
               AND ip_address=? AND status='success'"
        );
        $stmt->execute([$userId, $ip]);
        if ((int)$stmt->fetchColumn() === 0) {
            $reasons[] = 'Login from a new IP address (' . htmlspecialchars($ip) . ')';
            $score += 40;
        }
    }

    // 2. New device / user-agent hash?
    if (settingBool('security.rba_on_new_device', true)) {
        $uaHash = hash('sha256', substr($ua, 0, 512));
        $stmt   = $pdo->prepare(
            "SELECT COUNT(*) FROM session_registry
             WHERE user_id=? AND user_agent_hash=? AND is_active=0
             LIMIT 1"
        );
        $stmt->execute([$userId, $uaHash]);
        // Also check login_attempts for historic successful logins from this UA
        $stmt2 = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE email=(SELECT email FROM users WHERE id=? LIMIT 1)
               AND user_agent=? AND status='success'"
        );
        $stmt2->execute([$userId, substr($ua, 0, 512)]);
        if ((int)$stmt2->fetchColumn() === 0) {
            $reasons[] = 'Login from an unrecognised device or browser';
            $score += 35;
        }
    }

    // 3. Unusual hour? (midnight–5am Kampala time)
    $hour = (int)date('G', time());
    if ($hour >= 0 && $hour < 5) {
        $reasons[] = 'Login at unusual hour (' . date('H:i') . ' local time)';
        $score += 15;
    }

    // 4. High recent failure rate from this IP?
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE ip_address=? AND status='fail'
           AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $stmt->execute([$ip]);
    $recentFails = (int)$stmt->fetchColumn();
    if ($recentFails >= 3) {
        $reasons[] = 'Multiple recent failed attempts from this IP';
        $score += 20;
    }

    $level = $score >= 60 ? 'high' : ($score >= 30 ? 'medium' : 'low');
    return [
        'risk_level' => $level,
        'reasons'    => $reasons,
        'challenge'  => $score >= 30 && !empty($reasons),
        'score'      => $score,
    ];
}

/** Issue an RBA challenge OTP and return the challenge ID */
function issueRbaChallenge(int $userId, string $email, string $ip, string $ua, array $reasons): int {
    $code     = (string)random_int(100000, 999999);
    $hash     = password_hash($code, PASSWORD_BCRYPT, ['cost' => 10]);
    $reason   = implode('; ', array_slice($reasons, 0, 3));
    $expires  = date('Y-m-d H:i:s', time() + 900); // 15 min

    // Invalidate any prior challenges for this user
    getDB()->prepare("UPDATE rba_challenges SET used=1 WHERE user_id=? AND used=0")
           ->execute([$userId]);

    $stmt = getDB()->prepare(
        'INSERT INTO rba_challenges
         (user_id, email, code_hash, ip_address, user_agent, risk_reason, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $email, $hash, $ip, substr($ua, 0, 512), $reason, $expires]);
    $challengeId = (int)getDB()->lastInsertId();

    // Send OTP email
    sendMail($email, $email, 'NEDAMS Security Verification',
        '<p>A login to your NEDAMS account was flagged for security review.</p>
         <p><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>
         <p>Your verification code is: <strong style="font-size:1.4em;letter-spacing:.1em">' . $code . '</strong></p>
         <p>This code expires in 15 minutes. If you did not attempt to login, please contact your administrator immediately.</p>',
        "NEDAMS Security Verification\n\nVerification code: $code\nExpires in 15 minutes.\nReason: $reason"
    );

    return $challengeId;
}

/** Verify an RBA challenge code. Returns true on success. */
function verifyRbaChallenge(int $challengeId, string $code): bool {
    $stmt = getDB()->prepare(
        'SELECT * FROM rba_challenges WHERE id=? AND used=0 AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$challengeId]);
    $row = $stmt->fetch();
    if (!$row) return false;

    // Increment attempt counter
    getDB()->prepare('UPDATE rba_challenges SET attempts=attempts+1 WHERE id=?')
           ->execute([$challengeId]);

    if ($row['attempts'] >= 5) {
        getDB()->prepare('UPDATE rba_challenges SET used=1 WHERE id=?')->execute([$challengeId]);
        return false;
    }

    if (!password_verify($code, $row['code_hash'])) return false;

    getDB()->prepare('UPDATE rba_challenges SET used=1 WHERE id=?')->execute([$challengeId]);
    return true;
}

// ---- Password validation -----------------------------------
function validatePassword(string $password): array {
    $errors = [];
    $min = max(12, (int) setting('security.password_min_length', 12));

    if (strlen($password) < $min)
        $errors[] = "Password must be at least {$min} characters long.";
    if (!preg_match('/[A-Z]/', $password))
        $errors[] = "Password must contain at least one uppercase letter (A-Z).";
    if (!preg_match('/[a-z]/', $password))
        $errors[] = "Password must contain at least one lowercase letter (a-z).";
    if (!preg_match('/[0-9]/', $password))
        $errors[] = "Password must contain at least one number (0-9).";
    if (!preg_match('/[^A-Za-z0-9]/', $password))
        $errors[] = "Password must contain at least one symbol (!@#\$%^&* etc).";
    return $errors;
}

// ---- Phone normalisation (UG) ------------------------------
// Converts 07XXXXXXXX → +2567XXXXXXXX
function normalisePhone(?string $phone): ?string {
    if (!$phone) return null;
    $clean = preg_replace('/[^0-9+]/', '', $phone);
    if (preg_match('/^07\d{8}$/', $clean))
        return '+256' . substr($clean, 1);
    if (preg_match('/^7\d{8}$/', $clean))
        return '+256' . $clean;
    if (preg_match('/^2567\d{8}$/', $clean))
        return '+' . $clean;
    if (preg_match('/^\+2567\d{8}$/', $clean))
        return $clean;
    return $clean ?: null;
}

// ---- Sanitisation ------------------------------------------
function clean(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- CSRF --------------------------------------------------
function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        jsonResponse(['success'=>false,'message'=>'CSRF token mismatch.'], 403);
    }
}

// ---- Audit logger ------------------------------------------
function auditLog(string $action, string $module='', string $targetType='',
                  string $targetId='', string $desc=''): void {
    if (!settingBool('security.enable_audit_log', true)) return;
    startSession();
    try {
        getDB()->prepare(
            'INSERT INTO audit_log
             (user_id,user_email,action,module,target_type,target_id,description,ip_address,user_agent)
             VALUES(?,?,?,?,?,?,?,?,?)'
        )->execute([
            $_SESSION['user_id']   ?? null,
            $_SESSION['email']     ?? null,
            $action, $module, $targetType, $targetId, $desc,
            $_SERVER['REMOTE_ADDR']     ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Throwable $e) {}
}

// ---- Notification helper -----------------------------------
function addNotification(int $userId, string $title, string $msg='',
                         string $type='info', string $link=''): void {
    try {
        getDB()->prepare(
            'INSERT INTO notifications(user_id,title,message,type,link) VALUES(?,?,?,?,?)'
        )->execute([$userId,$title,$msg,$type,$link]);
    } catch (Throwable $e) {}
}

// ---- Unread notification count ----------------------------
function unreadNotifCount(): int {
    startSession();
    if (empty($_SESSION['user_id'])) return 0;
    try {
        $s = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $s->execute([$_SESSION['user_id']]);
        return (int)$s->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

// ---- Unread messages count (admin/super_admin) ------------
function unreadMessageCount(): int {
    try {
        $s = getDB()->query('SELECT COUNT(*) FROM messages WHERE status="unread"');
        return (int)$s->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

// ============================================================
// Mailer  (requires mail settings configured in admin)
// ============================================================
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody=''): bool {
    if (!settingBool('mail.mail_enabled', false)) return false;

    $host  = setting('mail.mail_host',         'smtp.gmail.com');
    $port  = (int) setting('mail.mail_port',    587);
    $enc   = setting('mail.mail_encryption',    'tls');
    $user  = setting('mail.mail_username',      '');
    $pass  = setting('mail.mail_password',      '');
    $from  = setting('mail.mail_from_address',  'noreply@nedams.ug');
    $fname = setting('mail.mail_from_name',     'NEDAMS');

    if (!$user || !$pass || !$host) return false;

    // Use PHPMailer if available, else fallback to php mail()
    $phpmailerPath = __DIR__.'/../lib/phpmailer/PHPMailer.php';
    if (file_exists($phpmailerPath)) {
        require_once $phpmailerPath;
        require_once __DIR__.'/../lib/phpmailer/SMTP.php';
        require_once __DIR__.'/../lib/phpmailer/Exception.php';
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->SMTPSecure = $enc === 'ssl' ? 'ssl' : 'tls';
            $mail->Port       = $port;
            $mail->setFrom($from, $fname);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('NEDAMS mail error: '.$e->getMessage());
            return false;
        }
    }

    // Native mail() fallback
    $headers  = "From: {$fname} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($toEmail, $subject, $htmlBody, $headers);
}

// ---- OTP sender --------------------------------------------
function sendOtp(int $userId, string $email, string $purpose): string {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 900); // 15 min
    $pdo = getDB();
    // Invalidate previous OTPs for this user/purpose
    $pdo->prepare('UPDATE otp_codes SET used=1 WHERE user_id=? AND purpose=? AND used=0')
        ->execute([$userId, $purpose]);
    $pdo->prepare('INSERT INTO otp_codes(user_id,email,code,purpose,expires_at) VALUES(?,?,?,?,?)')
        ->execute([$userId, $email, $code, $purpose, $expires]);

    $isReset  = $purpose === 'password_reset';
    $subject  = $isReset ? 'NEDAMS Password Reset OTP' : 'NEDAMS Email Verification';
    $heading  = $isReset ? 'Password Reset' : 'Verify Your Email';
    $message  = $isReset
        ? 'You requested a password reset for your NEDAMS account. Use the code below:'
        : 'Thank you for registering. Please verify your email address using the code below:';

    $html = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;'>
        <div style='background:#071c2c;padding:20px 24px;border-radius:8px 8px 0 0;'>
            <h2 style='color:#fff;margin:0;font-size:1.1rem;'>NEDAMS — {$heading}</h2>
        </div>
        <div style='background:#fff;border:1px solid #e4eaee;border-top:none;padding:28px 24px;border-radius:0 0 8px 8px;'>
            <p style='color:#4a6072;margin-bottom:20px;'>{$message}</p>
            <div style='background:#f0f4f7;border-radius:6px;padding:20px;text-align:center;margin-bottom:20px;'>
                <span style='font-family:monospace;font-size:2.2rem;font-weight:700;
                             letter-spacing:.3em;color:#071c2c;'>{$code}</span>
            </div>
            <p style='color:#8096a7;font-size:.82rem;'>This code expires in <strong>15 minutes</strong>.
               Do not share it with anyone.</p>
        </div>
    </div>";

    sendMail($email, $email, $subject, $html);
    return $code;
}

// ---- Verify OTP --------------------------------------------
function verifyOtp(int $userId, string $code, string $purpose): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT id,attempts FROM otp_codes
         WHERE user_id=? AND purpose=? AND used=0 AND expires_at>NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId, $purpose]);
    $row = $stmt->fetch();
    if (!$row) return false;

    // Increment attempts
    $pdo->prepare('UPDATE otp_codes SET attempts=attempts+1 WHERE id=?')->execute([$row['id']]);
    if ($row['attempts'] >= 5) return false; // Max 5 attempts

    if (!hash_equals($row['id'] > 0 ? '' : '', '') && !hash_equals(
        $pdo->prepare('SELECT code FROM otp_codes WHERE id=?')->execute([$row['id']])
            ? $pdo->query('SELECT code FROM otp_codes WHERE id='.$row['id'])->fetchColumn()
            : '',
        $code
    )) {
        // Safer approach:
        $codeRow = $pdo->prepare('SELECT code FROM otp_codes WHERE id=? LIMIT 1');
        $codeRow->execute([$row['id']]);
        $stored = $codeRow->fetchColumn();
        if (!hash_equals((string)$stored, (string)$code)) return false;
    }

    $pdo->prepare('UPDATE otp_codes SET used=1 WHERE id=?')->execute([$row['id']]);
    return true;
}

// ---- CSRF-safe OTP verify (cleaner version) ----------------
function verifyOtpClean(int $userId, string $code, string $purpose): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT id, code FROM otp_codes
         WHERE user_id=? AND purpose=? AND used=0 AND expires_at>NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId, $purpose]);
    $row = $stmt->fetch();
    if (!$row) return false;

    $pdo->prepare('UPDATE otp_codes SET attempts=attempts+1 WHERE id=?')->execute([$row['id']]);

    if (!hash_equals((string)$row['code'], (string)trim($code))) return false;

    $pdo->prepare('UPDATE otp_codes SET used=1 WHERE id=?')->execute([$row['id']]);
    return true;
}

// ---- Share URL builder -------------------------------------
// Always returns clean /s/{token} if token available,
// falls back to /views/view.php?id=CODE (which 301-redirects)
function shareUrl(array $structure): string {
    if (!empty($structure['share_token'])) {
        return appUrl() . '/s/' . $structure['share_token'];
    }
    return appUrl() . '/views/view.php?id=' . urlencode($structure['address_code']);
}

function pdfUrl(array $structure): string {
    if (!empty($structure['share_token'])) {
        return appUrl() . '/p/' . $structure['share_token'];
    }
    return appUrl() . '/api/generate_pdf.php?code=' . urlencode($structure['address_code']);
}

// ---- Share token generator ---------------------------------
function generateShareToken(): string {
    return bin2hex(random_bytes(16));
}

// ---- Timezone ----------------------------------------------
date_default_timezone_set(setting('general.timezone', 'Africa/Kampala') ?: 'Africa/Kampala');
