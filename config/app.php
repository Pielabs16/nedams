<?php
// ============================================================
// config/app.php  — NEDAMS v2.1
// ============================================================

define('NEDAMS_VERSION', '2.1.0');
define('BCRYPT_COST',    12);
define('SESSION_NAME',   'nedams_sess');
define('DB_HOST',   'localhost');
define('DB_PORT',   '3306');
define('DB_NAME',   'nedams');
define('DB_USER',   'root');
define('DB_PASS',   '');
define('DB_CHARSET','utf8mb4');

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
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        $lifetime = (int) setting('security.session_lifetime', 7200);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
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
