-- ============================================================
-- NEDAMS v2.1 - Enhanced Database Schema
-- MySQL 8.0+ / MariaDB 10.5+  InnoDB
-- Import: mysql -u root -p nedams < schema.sql
-- Roles: super_admin | admin | developer | mapper | viewer
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;
SET sql_mode = 'NO_ENGINE_SUBSTITUTION';

CREATE DATABASE IF NOT EXISTS nedams
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE nedams;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    full_name       VARCHAR(120)      NOT NULL,
    email           VARCHAR(180)      NOT NULL,
    phone           VARCHAR(20)       DEFAULT NULL,
    password        VARCHAR(255)      NOT NULL,
    role            ENUM('super_admin','admin','developer','mapper','viewer') NOT NULL DEFAULT 'viewer',
    avatar          VARCHAR(255)      DEFAULT NULL,
    is_active       TINYINT(1)        NOT NULL DEFAULT 1,
    email_verified  TINYINT(1)        NOT NULL DEFAULT 0,
    last_login      DATETIME          DEFAULT NULL,
    last_login_ip   VARCHAR(45)       DEFAULT NULL,
    login_count     INT UNSIGNED      NOT NULL DEFAULT 0,
    failed_attempts TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    locked_until    DATETIME          DEFAULT NULL,
    reset_token     VARCHAR(64)       DEFAULT NULL,
    reset_expires   DATETIME          DEFAULT NULL,
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_email (email),
    KEY idx_role   (role),
    KEY idx_active (is_active),
    KEY idx_verified (email_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OTP CODES  (email verification + password reset)
-- ============================================================
CREATE TABLE IF NOT EXISTS otp_codes (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED  NOT NULL,
    email       VARCHAR(180)  NOT NULL,
    code        VARCHAR(6)    NOT NULL,
    purpose     ENUM('email_verify','password_reset') NOT NULL,
    used        TINYINT(1)    NOT NULL DEFAULT 0,
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at  DATETIME      NOT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_otp_email   (email),
    KEY idx_otp_user    (user_id),
    KEY idx_otp_purpose (purpose),
    CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ROLE NAV PERMISSIONS  (super_admin controls nav per role)
-- ============================================================
CREATE TABLE IF NOT EXISTS role_permissions (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    role        ENUM('super_admin','admin','developer','mapper','viewer') NOT NULL,
    nav_key     VARCHAR(60)   NOT NULL,
    is_allowed  TINYINT(1)    NOT NULL DEFAULT 1,
    updated_by  INT UNSIGNED  DEFAULT NULL,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_role_nav (role, nav_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STRUCTURES
-- ============================================================
CREATE TABLE IF NOT EXISTS structures (
    id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    address_code     VARCHAR(12)       NOT NULL,
    latitude         DECIMAL(11,8)     NOT NULL,
    longitude        DECIMAL(11,8)     NOT NULL,
    resident_name    VARCHAR(120)      NOT NULL,
    phone            VARCHAR(20)       DEFAULT NULL,
    email            VARCHAR(180)      DEFAULT NULL,
    description      TEXT              DEFAULT NULL,
    landmarks        TEXT              DEFAULT NULL,
    zone             VARCHAR(80)       DEFAULT NULL,
    parish           VARCHAR(80)       DEFAULT NULL,
    division         VARCHAR(80)       NOT NULL DEFAULT 'Nakawa',
    structure_type   ENUM('residential','commercial','school','clinic','worship','government','ngo','other') NOT NULL DEFAULT 'residential',
    floor_count      TINYINT UNSIGNED  NOT NULL DEFAULT 1,
    occupant_count   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    photo_path       VARCHAR(255)      DEFAULT NULL,
    confidence_score TINYINT UNSIGNED  NOT NULL DEFAULT 100,
    accuracy_meters  DECIMAL(8,2)      DEFAULT NULL,
    what3words       VARCHAR(60)       DEFAULT NULL,
    share_token      VARCHAR(32)       DEFAULT NULL,
    registered_by    INT UNSIGNED      DEFAULT NULL,
    verified_by      INT UNSIGNED      DEFAULT NULL,
    verified_at      DATETIME          DEFAULT NULL,
    status           ENUM('pending','verified','rejected','archived') NOT NULL DEFAULT 'pending',
    reject_reason    VARCHAR(255)      DEFAULT NULL,
    view_count       INT UNSIGNED      NOT NULL DEFAULT 0,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_address_code (address_code),
    UNIQUE KEY uk_share_token  (share_token),
    KEY idx_lat_lng       (latitude, longitude),
    KEY idx_zone          (zone),
    KEY idx_status        (status),
    KEY idx_registered_by (registered_by),
    KEY idx_type          (structure_type),
    CONSTRAINT fk_struct_user  FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_struct_verif FOREIGN KEY (verified_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SERVICE REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS service_requests (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    address_code    VARCHAR(12)       NOT NULL,
    api_key_id      INT UNSIGNED      DEFAULT NULL,
    requester_name  VARCHAR(120)      DEFAULT NULL,
    requester_org   VARCHAR(120)      DEFAULT NULL,
    requester_phone VARCHAR(20)       DEFAULT NULL,
    purpose         ENUM('delivery','emergency','visit','verification','survey','other') NOT NULL DEFAULT 'delivery',
    ip_address      VARCHAR(45)       DEFAULT NULL,
    user_agent      VARCHAR(255)      DEFAULT NULL,
    response_code   SMALLINT          NOT NULL DEFAULT 200,
    response_ms     SMALLINT          DEFAULT NULL,
    country         VARCHAR(60)       NOT NULL DEFAULT 'Uganda',
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sr_code    (address_code),
    KEY idx_sr_apikey  (api_key_id),
    KEY idx_sr_date    (created_at),
    KEY idx_sr_purpose (purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API KEYS  (developer + admin roles only)
-- ============================================================
CREATE TABLE IF NOT EXISTS api_keys (
    id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    name         VARCHAR(120)      NOT NULL,
    organisation VARCHAR(120)      DEFAULT NULL,
    key_hash     VARCHAR(64)       NOT NULL,
    key_prefix   VARCHAR(8)        NOT NULL,
    permissions  SET('read','write','admin') NOT NULL DEFAULT 'read',
    rate_limit   SMALLINT UNSIGNED NOT NULL DEFAULT 1000,
    usage_count  INT UNSIGNED      NOT NULL DEFAULT 0,
    last_used    DATETIME          DEFAULT NULL,
    expires_at   DATETIME          DEFAULT NULL,
    is_active    TINYINT(1)        NOT NULL DEFAULT 1,
    created_by   INT UNSIGNED      DEFAULT NULL,
    notes        TEXT              DEFAULT NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_key_hash (key_hash),
    KEY idx_key_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `group`     VARCHAR(60)   NOT NULL DEFAULT 'general',
    `key`       VARCHAR(120)  NOT NULL,
    `value`     TEXT          DEFAULT NULL,
    type        ENUM('string','integer','boolean','json','password','textarea') NOT NULL DEFAULT 'string',
    label       VARCHAR(160)  DEFAULT NULL,
    description TEXT          DEFAULT NULL,
    is_public   TINYINT(1)    NOT NULL DEFAULT 0,
    updated_by  INT UNSIGNED  DEFAULT NULL,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_group_key (`group`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDIT LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED  DEFAULT NULL,
    user_email  VARCHAR(180)  DEFAULT NULL,
    action      VARCHAR(80)   NOT NULL,
    module      VARCHAR(60)   DEFAULT NULL,
    target_type VARCHAR(60)   DEFAULT NULL,
    target_id   VARCHAR(40)   DEFAULT NULL,
    description TEXT          DEFAULT NULL,
    ip_address  VARCHAR(45)   DEFAULT NULL,
    user_agent  VARCHAR(255)  DEFAULT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user   (user_id),
    KEY idx_audit_action (action),
    KEY idx_audit_date   (created_at),
    KEY idx_audit_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED  NOT NULL,
    title      VARCHAR(160)  NOT NULL,
    message    TEXT          DEFAULT NULL,
    type       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    link       VARCHAR(255)  DEFAULT NULL,
    is_read    TINYINT(1)    NOT NULL DEFAULT 0,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user (user_id),
    KEY idx_notif_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MESSAGES  (user inquiries to admins)
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    sender_id     INT UNSIGNED  DEFAULT NULL,
    sender_name   VARCHAR(120)  NOT NULL,
    sender_email  VARCHAR(180)  NOT NULL,
    subject       VARCHAR(200)  NOT NULL,
    body          TEXT          NOT NULL,
    status        ENUM('unread','read','replied','closed') NOT NULL DEFAULT 'unread',
    replied_by    INT UNSIGNED  DEFAULT NULL,
    reply_body    TEXT          DEFAULT NULL,
    replied_at    DATETIME      DEFAULT NULL,
    ip_address    VARCHAR(45)   DEFAULT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_msg_status  (status),
    KEY idx_msg_sender  (sender_id),
    KEY idx_msg_date    (created_at),
    CONSTRAINT fk_msg_sender  FOREIGN KEY (sender_id)  REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_msg_replied FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CSV EXPORT LOG  (super_admin export tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS export_log (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    exported_by  INT UNSIGNED  NOT NULL,
    export_type  ENUM('structures','users','service_requests','audit_log') NOT NULL,
    filters_json TEXT          DEFAULT NULL,
    row_count    INT UNSIGNED  NOT NULL DEFAULT 0,
    filename     VARCHAR(255)  DEFAULT NULL,
    ip_address   VARCHAR(45)   DEFAULT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_export_user (exported_by),
    KEY idx_export_date (created_at),
    CONSTRAINT fk_export_user FOREIGN KEY (exported_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ZONES
-- ============================================================
CREATE TABLE IF NOT EXISTS zones (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80)   NOT NULL,
    parish      VARCHAR(80)   DEFAULT NULL,
    division    VARCHAR(80)   NOT NULL DEFAULT 'Nakawa',
    code_prefix VARCHAR(4)    DEFAULT NULL,
    description TEXT          DEFAULT NULL,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_by  INT UNSIGNED  DEFAULT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_zone_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ============================================================
-- SEED: Default settings
-- ============================================================
INSERT INTO settings (`group`, `key`, `value`, type, label, description, is_public) VALUES
('general',    'app_name',            'NEDAMS',                   'string',  'Application Name',          'System display name',               1),
('general',    'app_tagline',         'Digital Addressing System', 'string',  'Tagline',                   'Shown on login page',               1),
('general',    'app_url',             'http://localhost/nedams',   'string',  'Application URL',           'Base URL with no trailing slash',   0),
('general',    'timezone',            'Africa/Kampala',            'string',  'Timezone',                  'Server timezone',                   0),
('general',    'date_format',         'd M Y',                     'string',  'Date Format',               'PHP date format string',            0),
('general',    'structures_per_page', '25',                        'integer', 'Records Per Page',          'Admin table pagination',            0),
('general',    'map_default_lat',     '0.3476',                    'string',  'Default Map Latitude',      'Map centre latitude',               1),
('general',    'map_default_lng',     '32.6152',                   'string',  'Default Map Longitude',     'Map centre longitude',              1),
('general',    'map_default_zoom',    '15',                        'integer', 'Default Map Zoom',          'Google Maps zoom level 1-20',       1),
('maps',       'gmaps_api_key',       '',                          'password','Google Maps API Key',       'From console.cloud.google.com',     0),
('maps',       'enable_satellite',    '1',                         'boolean', 'Enable Satellite View',     'Allow satellite map type',          1),
('maps',       'enable_clustering',   '1',                         'boolean', 'Enable Marker Clustering',  'Cluster nearby pins on map',        1),
('maps',       'max_markers',         '500',                       'integer', 'Max Markers Per View',      'Limit markers loaded per viewport', 0),
('addressing', 'address_prefix',      'NE',                        'string',  'Address Code Prefix',       '2-4 char prefix for all codes',     1),
('addressing', 'address_length',      '6',                         'integer', 'Code Body Length',          'Characters after prefix',           1),
('addressing', 'auto_verify',         '0',                         'boolean', 'Auto-Verify Structures',    'Skip manual verification step',     0),
('addressing', 'require_photo',       '0',                         'boolean', 'Require Photo Upload',      'Mandatory photo for registration',  0),
('mail',       'mail_driver',         'smtp',                      'string',  'Mail Driver',               'smtp or sendmail',                  0),
('mail',       'mail_host',           'smtp.gmail.com',            'string',  'SMTP Host',                 'Mail server hostname',              0),
('mail',       'mail_port',           '587',                       'integer', 'SMTP Port',                 '587 for TLS, 465 for SSL',          0),
('mail',       'mail_encryption',     'tls',                       'string',  'Encryption',                'tls or ssl',                        0),
('mail',       'mail_username',       '',                          'string',  'SMTP Username',             'Email account username',            0),
('mail',       'mail_password',       '',                          'password','SMTP Password',             'Email account password',            0),
('mail',       'mail_from_address',   'noreply@nedams.ug',         'string',  'From Address',              'Sender email address',              0),
('mail',       'mail_from_name',      'NEDAMS System',             'string',  'From Name',                 'Sender display name',               0),
('mail',       'mail_enabled',        '0',                         'boolean', 'Enable Email',              'Send system emails',                0),
('security',   'session_lifetime',    '7200',                      'integer', 'Session Lifetime (sec)',    'Auto logout after inactivity',      0),
('security',   'max_login_attempts',  '5',                         'integer', 'Max Login Attempts',        'Before account lockout',            0),
('security',   'lockout_duration',    '900',                       'integer', 'Lockout Duration (sec)',    'Account lockout period',            0),
('security',   'enable_audit_log',    '1',                         'boolean', 'Enable Audit Log',          'Record all admin actions',          0),
('security',   'api_rate_limit',      '1000',                      'integer', 'API Rate Limit/day',        'Default requests per API key per day', 0),
('security',   'require_email_verify','0',                         'boolean', 'Require Email Verification','Users must verify email on signup',  0),
('security',   'password_min_length', '12',                        'integer', 'Min Password Length',       'Minimum password length (12 recommended)',  0),
('security',   'password_require_number','1',                      'boolean', 'Require Number in Password','Password must contain a digit',             0),
('security',   'password_require_special','1',                     'boolean', 'Require Special Character', 'Password must contain ! @ # $ etc',         0),
('security',   'password_require_upper', '1',                      'boolean', 'Require Uppercase Letter',  'Password must contain A-Z',                 0),
('security',   'password_require_lower', '1',                      'boolean', 'Require Lowercase Letter',  'Password must contain a-z',                 0),
('workflow',   'allow_self_register', '1',                         'boolean', 'Allow Self-Registration',   'Public registration form enabled',  0),
('workflow',   'default_role',        'viewer',                    'string',  'Default User Role',         'Role auto-assigned to new accounts',0),
('workflow',   'registration_note',   'You are registering as a community mapper for the NEDAMS Digital Addressing System. Your account will be reviewed before activation.', 'textarea', 'Registration Notice', 'Message shown on registration form', 0);

-- ============================================================
-- SEED: Default role nav permissions
-- Format: role | nav_key | is_allowed
-- nav_key matches sidebar data-nav attributes
-- ============================================================
INSERT INTO role_permissions (role, nav_key, is_allowed) VALUES
('super_admin', 'dashboard',         1),
('super_admin', 'map',               1),
('super_admin', 'search',            1),
('super_admin', 'register',          1),
('super_admin', 'structures',        1),
('super_admin', 'pending',           1),
('super_admin', 'zones',             1),
('super_admin', 'analytics',         1),
('super_admin', 'service_requests',  1),
('super_admin', 'audit_log',         1),
('super_admin', 'users',             1),
('super_admin', 'api_keys',          1),
('super_admin', 'settings',          1),
('super_admin', 'messages',          1),
('super_admin', 'api_docs',          1),
('super_admin', 'exports',           1),
('admin',       'dashboard',         1),
('admin',       'map',               1),
('admin',       'search',            1),
('admin',       'register',          1),
('admin',       'structures',        1),
('admin',       'pending',           1),
('admin',       'zones',             1),
('admin',       'analytics',         1),
('admin',       'service_requests',  1),
('admin',       'audit_log',         1),
('admin',       'users',             1),
('admin',       'api_keys',          1),
('admin',       'settings',          0),
('admin',       'messages',          1),
('admin',       'api_docs',          0),
('admin',       'exports',           1),
('developer',   'dashboard',         1),
('developer',   'map',               1),
('developer',   'search',            1),
('developer',   'register',          1),
('developer',   'structures',        1),
('developer',   'pending',           0),
('developer',   'zones',             0),
('developer',   'analytics',         0),
('developer',   'service_requests',  1),
('developer',   'audit_log',         0),
('developer',   'users',             0),
('developer',   'api_keys',          1),
('developer',   'settings',          0),
('developer',   'messages',          1),
('developer',   'api_docs',          1),
('developer',   'exports',           0),
('mapper',      'dashboard',         1),
('mapper',      'map',               1),
('mapper',      'search',            1),
('mapper',      'register',          1),
('mapper',      'structures',        1),
('mapper',      'pending',           0),
('mapper',      'zones',             0),
('mapper',      'analytics',         0),
('mapper',      'service_requests',  0),
('mapper',      'audit_log',         0),
('mapper',      'users',             0),
('mapper',      'api_keys',          0),
('mapper',      'settings',          0),
('mapper',      'messages',          1),
('mapper',      'api_docs',          0),
('mapper',      'exports',           0),
('viewer',      'dashboard',         1),
('viewer',      'map',               1),
('viewer',      'search',            1),
('viewer',      'register',          0),
('viewer',      'structures',        1),
('viewer',      'pending',           0),
('viewer',      'zones',             0),
('viewer',      'analytics',         0),
('viewer',      'service_requests',  0),
('viewer',      'audit_log',         0),
('viewer',      'users',             0),
('viewer',      'api_keys',          0),
('viewer',      'settings',          0),
('viewer',      'messages',          1),
('viewer',      'api_docs',          0),
('viewer',      'exports',           0);

-- ============================================================
-- SEED: Default zones
-- ============================================================
INSERT INTO zones (name, parish, division, code_prefix) VALUES
('Kireka A',       'Kireka',   'Nakawa', 'KA'),
('Kireka B',       'Kireka',   'Nakawa', 'KB'),
('Nakawa Central', 'Nakawa',   'Nakawa', 'NC'),
('Banda',          'Banda',    'Nakawa', 'BD'),
('Ntinda',         'Ntinda',   'Nakawa', 'NT'),
('Luzira',         'Luzira',   'Nakawa', 'LZ'),
('Butabika',       'Butabika', 'Nakawa', 'BT');

-- ============================================================
-- SEED: Admin user
-- Password: Admin@1234  <-- CHANGE THIS IMMEDIATELY IN PRODUCTION
-- ============================================================
INSERT INTO users (full_name, email, phone, password, role, is_active, email_verified) VALUES
(
    'NEDAMS Administrator',
    'admin@nedams.ug',
    '+256700000000',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'super_admin',
    1,
    1
);

-- ============================================================
-- SEED: Sample structures
-- ============================================================
INSERT INTO structures
    (address_code, latitude, longitude, resident_name, phone, description,
     zone, parish, structure_type, registered_by, status, confidence_score, share_token)
VALUES
('NE4K7X', 0.34761230, 32.61524560, 'John Mukasa',    '+256712345678', 'Blue gate near water point',      'Kireka B',       'Kireka', 'residential', 1, 'verified', 95, MD5(CONCAT('NE4K7X', RAND()))),
('NE9R2M', 0.35010000, 32.61800000, 'Grace Nambi',    '+256756789012', 'Yellow house opp primary school', 'Nakawa Central', 'Nakawa', 'residential', 1, 'verified', 88, MD5(CONCAT('NE9R2M', RAND()))),
('NE7T4P', 0.34900000, 32.61650000, 'Kireka Clinic',  '+256778901234', 'Health centre main road',         'Kireka A',       'Kireka', 'clinic',      1, 'verified', 99, MD5(CONCAT('NE7T4P', RAND()))),
('NE2W8Q', 0.34550000, 32.61400000, 'St John School', '+256741234567', 'Primary school blue fence',       'Banda',          'Banda',  'school',      1, 'verified', 97, MD5(CONCAT('NE2W8Q', RAND()))),
('NE5X3N', 0.35200000, 32.61900000, 'Moses Ochieng',  '+256723456789', 'Third house from junction',       'Ntinda',         'Ntinda', 'residential', 1, 'pending',  72, MD5(CONCAT('NE5X3N', RAND()))),
('NE8M6L', 0.34680000, 32.61580000, 'Fatuma Shop',    '+256754321098', 'Corner shop green door',          'Kireka B',       'Kireka', 'commercial',  1, 'verified', 90, MD5(CONCAT('NE8M6L', RAND()))),
('NE3V9K', 0.35050000, 32.61750000, 'David Ssali',    '+256765432109', 'Near borehole red roof',          'Nakawa Central', 'Nakawa', 'residential', 1, 'pending',  65, MD5(CONCAT('NE3V9K', RAND()))),
('NE6H2B', 0.34420000, 32.61320000, 'Masjid Noor',    '+256776543210', 'Mosque white minaret',            'Banda',          'Banda',  'worship',     1, 'verified', 98, MD5(CONCAT('NE6H2B', RAND())));

-- ============================================================
-- SEED: Sample API key (plaintext: nedams-test-key-2024)
-- ============================================================
INSERT INTO api_keys
    (name, organisation, key_hash, key_prefix, permissions, rate_limit, is_active, created_by)
VALUES
(
    'Jumia Delivery Test',
    'Jumia Uganda',
    SHA2('nedams-test-key-2024', 256),
    'nedams-t',
    'read',
    500,
    1,
    1
);

-- ============================================================
-- SEED: Sample service requests
-- ============================================================
INSERT INTO service_requests
    (address_code, requester_name, requester_org, purpose, response_code, response_ms, created_at)
VALUES
('NE4K7X', 'Driver A',  'Jumia',     'delivery',  200, 45, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('NE9R2M', 'Driver B',  'Glovo',     'delivery',  200, 38, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('NE7T4P', 'Ambulance', 'KCCA',      'emergency', 200, 22, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('NE4K7X', 'Driver C',  'Jumia',     'delivery',  200, 51, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('NE2W8Q', 'Inspector', 'MoH',       'visit',     200, 44, DATE_SUB(NOW(), INTERVAL 3 DAY)),
('NE8M6L', 'Driver D',  'Bolt Food', 'delivery',  200, 39, DATE_SUB(NOW(), INTERVAL 3 DAY)),
('NE4K7X', 'Driver E',  'SafeBoda',  'delivery',  200, 42, DATE_SUB(NOW(), INTERVAL 4 DAY)),
('NE6H2B', 'Survey',    'UBOS',      'survey',    200, 55, DATE_SUB(NOW(), INTERVAL 5 DAY)),
('NE9R2M', 'Driver F',  'Jumia',     'delivery',  200, 35, DATE_SUB(NOW(), INTERVAL 6 DAY)),
('NE3V9K', 'Driver G',  'Glovo',     'delivery',  404, 12, DATE_SUB(NOW(), INTERVAL 6 DAY)),
('NE4K7X', 'Driver H',  'Jumia',     'delivery',  200, 47, NOW()),
('NE7T4P', 'Nurse',     'Mulago',    'emergency', 200, 19, NOW());
