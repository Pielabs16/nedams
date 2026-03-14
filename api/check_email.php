<?php
// ============================================================
// api/check_email.php  — GET: Check if email is available
// Returns JSON — used by registration + admin edit forms
// ============================================================
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/app.php';

$email     = strtolower(trim($_GET['email']    ?? ''));
$excludeId = (int)($_GET['exclude_id'] ?? 0); // for edit: exclude current user

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['available' => false, 'message' => 'Invalid email address.']);
    exit;
}

$pdo  = getDB();
$stmt = $excludeId
    ? $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ? LIMIT 1')
    : $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? LIMIT 1');

$excludeId
    ? $stmt->execute([$email, $excludeId])
    : $stmt->execute([$email]);

$exists = (int)$stmt->fetchColumn() > 0;

echo json_encode([
    'available' => !$exists,
    'message'   => $exists
        ? 'This email is already registered to another account.'
        : 'Email address is available.',
]);
