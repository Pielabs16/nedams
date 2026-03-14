<?php
// ============================================================
// views/view.php  — Legacy redirect handler
//
// Old URLs (?id=CODE or ?token=TOKEN) are permanently redirected
// to the clean /s/{token} URL so no directory paths are exposed.
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Structure.php';

$code  = strtoupper(trim($_GET['id']    ?? ''));
$token = trim($_GET['token'] ?? '');

// Resolve token → code
if ($token && preg_match('/^[a-f0-9]{32}$/', $token)) {
    $stmt = getDB()->prepare('SELECT address_code FROM structures WHERE share_token=? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row) $code = $row['address_code'];
}

// Validate code
if (!$code || !preg_match('/^[A-Z0-9]{4,16}$/', $code)) {
    http_response_code(404);
    die('<div style="font-family:Arial,sans-serif;padding:40px"><h2>Address not found</h2></div>');
}

$s = Structure::findByCode($code);
if (!$s) {
    http_response_code(404);
    die('<div style="font-family:Arial,sans-serif;padding:40px"><h2>Address code '.htmlspecialchars($code).' not found.</h2></div>');
}

// Ensure share token exists
if (empty($s['share_token'])) {
    $newToken = bin2hex(random_bytes(16));
    getDB()->prepare('UPDATE structures SET share_token=? WHERE address_code=?')
           ->execute([$newToken, $code]);
    $s['share_token'] = $newToken;
}

// 301 redirect to clean token URL — this permanently replaces old links
header('Location: ' . appUrl() . '/s/' . $s['share_token'], true, 301);
exit;
