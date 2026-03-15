<?php
// ============================================================
// config/env.example.php  — CREDENTIAL TEMPLATE
//
// SAFE TO COMMIT.  Contains no real values.
//
// Setup:
//   cp config/env.example.php config/env.php
//   Then edit config/env.php with real values.
// ============================================================

define('DB_HOST',   'localhost');
define('DB_PORT',   '3306');
define('DB_NAME',   'nedams');
define('DB_USER',   'your_db_user');
define('DB_PASS',   'your_db_password');
define('DB_CHARSET','utf8mb4');

// Generate with: php -r "echo bin2hex(random_bytes(32));"
define('APP_KEY', 'CHANGE_ME_generate_with_php_random_bytes_32');

define('SMTP_HOST',     '');
define('SMTP_PORT',     587);
define('SMTP_USER',     '');
define('SMTP_PASS',     '');
define('SMTP_FROM',     '');
define('SMTP_FROM_NAME','NEDAMS');
