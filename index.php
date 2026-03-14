<?php
// ============================================================
// index.php  — Entry point + clean URL router
//
// Handles two jobs:
//  1. Normal entry: redirect to dashboard or login
//  2. Clean URL routing: /s/{token}, /a/{code}, /p/{token}
//     (rewrites from .htaccess pass _r= parameter)
// ============================================================
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/models/Structure.php';

$route = $_GET['_r'] ?? '';

// ---- Handle clean URL routes --------------------------------
if ($route !== '') {
    _handleRoute($route);
    exit;
}

// ---- Normal entry point ------------------------------------
startSession();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . appUrl() . '/views/dashboard.php');
} else {
    header('Location: ' . appUrl() . '/views/login.php');
}
exit;

// ============================================================
function _handleRoute(string $route): void {
    $token = trim($_GET['token'] ?? '');
    $code  = strtoupper(trim($_GET['code'] ?? ''));

    // Resolve token → code
    if ($token) {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            _notFound('Invalid link.');
        }
        $stmt = getDB()->prepare(
            'SELECT address_code FROM structures WHERE share_token = ? LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) {
            _notFound('This address link is not valid or has been removed.');
        }
        $code = $row['address_code'];
    }

    // Validate code
    if (!$code || !preg_match('/^[A-Z0-9]{4,16}$/', $code)) {
        _notFound('Invalid address code.');
    }

    $s = Structure::findByCode($code);
    if (!$s) {
        _notFound('Address code <strong>' . htmlspecialchars($code) . '</strong> was not found.');
    }

    // Generate share token if missing
    if (empty($s['share_token'])) {
        $newToken = bin2hex(random_bytes(16));
        getDB()->prepare('UPDATE structures SET share_token = ? WHERE address_code = ?')
               ->execute([$newToken, $code]);
        $s['share_token'] = $newToken;
    }

    // Dispatch
    switch ($route) {
        case 'pdf':
            require __DIR__ . '/api/generate_pdf.php';
            break;
        case 'share':
        case 'address':
        default:
            Structure::incrementView($code);
            require __DIR__ . '/views/_card.php';
            break;
    }
}

// ============================================================
function _notFound(string $msg = 'Not found.'): void {
    http_response_code(404);
    $app = function_exists('appName') ? appName() : 'NEDAMS';
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>
        <title>Not Found — {$app}</title>
        <link href='https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;600;700&display=swap' rel='stylesheet'>
        <style>
          body{font-family:'IBM Plex Sans',sans-serif;background:#f0f4f7;display:flex;
               align-items:center;justify-content:center;min-height:100vh;margin:0}
          .box{background:#fff;border:1px solid #e4eaee;border-radius:10px;
               padding:40px 48px;max-width:440px;text-align:center;
               box-shadow:0 4px 16px rgba(7,28,44,.08)}
          h2{color:#071c2c;font-size:1.15rem;margin-bottom:10px}
          p{color:#7a92a3;font-size:.88rem;margin-bottom:24px;line-height:1.55}
          a{display:inline-block;background:#103a54;color:#fff;padding:9px 20px;
            border-radius:5px;text-decoration:none;font-size:.84rem;font-weight:600}
        </style></head><body>
        <div class='box'>
          <div style='width:44px;height:44px;background:#071c2c;border-radius:9px;
                      display:flex;align-items:center;justify-content:center;margin:0 auto 16px'>
            <svg viewBox='0 0 24 24' width='20' height='20' fill='#315d77'>
              <path d='M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z'/>
            </svg>
          </div>
          <h2>{$app}</h2><p>{$msg}</p>
          <a href='javascript:history.back()'>Go Back</a>
        </div></body></html>";
    exit;
}
