<<<<<<< HEAD
# NEDAMS v2.0 — Nakawa East Digital Addressing & Mapping System

**Enterprise-grade digital addressing system for informal settlements in Kampala, Uganda.**

---

## Features

### Core
- **Advanced Address Generation** — Geohash-inspired spatial encoding with SHA-256 collision fallback
- **Interactive Google Maps** — Live marker loading, GPS pin-drop, drag-to-adjust
- **Public Address Cards** — Shareable URLs with WhatsApp integration and Google Maps navigation
- **GPS Confidence Scoring** — 0–100% score based on browser accuracy metres

### Admin Dashboard
- **Collapsible Sidebar Navigation** — Persistent state, mobile-responsive
- **Analytics** — Chart.js charts: daily registrations, monthly growth, API trends, type/zone breakdowns
- **Structure Management** — Verify/reject/archive pending structures, bulk filters, pagination
- **User Management** — Create/edit/deactivate users, role-based access (super_admin / admin / mapper / viewer)
- **API Key Management** — Generate keys through UI (never hardcoded), SHA-256 hash storage, rate limits, expiry
- **System Settings** — All config stored in DB settings table (general, maps, addressing, mailer, security, workflow)
- **Zone Management** — Admin-managed geographic hierarchy with code prefixes
- **Audit Log** — Every significant action recorded with user, IP, timestamp
- **Service Request Log** — Full log of API lookups by delivery companies and responders

### API
- `GET  /api/get_coordinates.php?code=NE4K7X` — Resolve address → GPS + navigation links
- `GET  /api/search_location.php?q=John`      — Full-text search
- `GET  /api/get_markers.php?swLat=...`        — Viewport markers for maps
- `POST /api/register_location.php`            — Register new structure

---

## Tech Stack

| Layer    | Technology                        |
|----------|-----------------------------------|
| Backend  | PHP 8.2+, Apache/Nginx            |
| Database | MySQL 8.0+ (InnoDB)               |
| Frontend | Vanilla JS, CSS custom properties |
| Maps     | Google Maps JavaScript API        |
| Charts   | Chart.js 4                        |
| Icons    | Font Awesome 6                    |
| Fonts    | IBM Plex Sans + IBM Plex Mono     |

---

## Quick Start (XAMPP / Local)

### 1. Copy Files
```
C:\xampp\htdocs\nedams\   (Windows)
/var/www/html/nedams/      (Linux)
```

### 2. Create Database
Open **phpMyAdmin** → Create database `nedams` → Import `database/schema.sql`

### 3. Configure DB Connection
Edit `config/app.php` — update DB_HOST, DB_USER, DB_PASS if needed:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'nedams');
```

### 4. Set Application URL
Go to **Admin → Settings → General** and set:
```
Application URL: http://localhost/nedams
```

### 5. Add Google Maps API Key
Go to **Admin → Settings → Google Maps** and paste your API key.

To get a key:
1. Visit https://console.cloud.google.com
2. Enable **Maps JavaScript API**
3. Create API key → restrict to your domain

### 6. Log In
```
URL:      http://localhost/nedams
Email:    admin@nedams.ug
Password: Admin@1234  ← CHANGE THIS IMMEDIATELY
```

---

## Production Deployment (Apache + Ubuntu)

### 1. Upload Files
```bash
sudo mkdir -p /var/www/html/nedams
sudo chown www-data:www-data /var/www/html/nedams
# Upload files to /var/www/html/nedams
```

### 2. Import Database
```bash
mysql -u root -p -e "CREATE DATABASE nedams CHARACTER SET utf8mb4;"
mysql -u root -p nedams < /var/www/html/nedams/database/schema.sql
```

### 3. Apache Virtual Host
```apache
<VirtualHost *:443>
    ServerName nedams.yourdomain.ug
    DocumentRoot /var/www/html/nedams
    
    <Directory /var/www/html/nedams>
        AllowOverride All
        Options -Indexes
        Require all granted
    </Directory>
    
    # Protect sensitive dirs
    <Directory /var/www/html/nedams/config>
        Require all denied
    </Directory>
    <Directory /var/www/html/nedams/models>
        Require all denied
    </Directory>
    
    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/nedams.yourdomain.ug/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/nedams.yourdomain.ug/privkey.pem
</VirtualHost>
```

### 4. Enable Apache Modules
```bash
sudo a2enmod rewrite headers deflate expires ssl
sudo systemctl restart apache2
```

### 5. SSL Certificate
```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d nedams.yourdomain.ug
```

### 6. Update Settings in Admin UI
- **General → Application URL**: `https://nedams.yourdomain.ug`
- **Google Maps → API Key**: your production key
- **Security**: Enable `session.cookie_secure` by editing `config/app.php` (set `'secure'=>true`)

---

## Mailer Configuration (SMTP)

Go to **Admin → Settings → Mailer**:

| Setting          | Example Value             |
|------------------|---------------------------|
| Mail Driver      | smtp                      |
| SMTP Host        | smtp.gmail.com            |
| SMTP Port        | 587                       |
| Encryption       | tls                       |
| Username         | your@gmail.com            |
| Password         | App password (not login)  |
| From Address     | noreply@nedams.ug         |
| From Name        | NEDAMS System             |
| Enable Email     | On                        |

For Gmail: Generate an **App Password** at https://myaccount.google.com/apppasswords

---

## API Key Setup

1. Go to **Admin → API Keys → Create API Key**
2. Enter name, organisation, permissions, rate limit
3. **Copy the key immediately** — it is shown only once
4. Provide key to delivery service: `X-NEDAMS-Key: nk_xxxxx`

---

## User Roles

| Role         | Can Do                                                    |
|--------------|-----------------------------------------------------------|
| super_admin  | Everything including promote others to super_admin        |
| admin        | Manage users, verify structures, all settings             |
| mapper       | Register structures, view map, search                     |
| viewer       | View map and search only                                  |

---

## Address Code Algorithm

NEDAMS v2 uses a **Geohash-inspired spatial encoder**:

1. GPS coordinates rounded to 5 decimal places (~1.1m resolution)
2. Coordinates converted to integer grid and **interleaved using Morton (Z-order) curve**
3. 48-bit Morton code encoded with **custom base-32 alphabet** (no 0, O, 1, I — avoids ambiguity)
4. Result prefixed with admin-configured prefix (default `NE`) → 8-character code e.g. `NE4K7X`
5. Collision detection: if same code exists for different coords, falls back to SHA-256 window-shift, then sequential base-36

**Properties:**
- Deterministic: same GPS always gives same code
- Geographically local: nearby structures have similar codes
- Visually unambiguous: no characters that look alike
- Reverse-decodable: approximate coords can be recovered from code alone

---

## File Structure

```
nedams/
├── api/                    REST API endpoints
│   ├── get_coordinates.php
│   ├── get_markers.php
│   ├── register_location.php
│   └── search_location.php
├── assets/
│   ├── css/style.css       Full dashboard CSS (~900 lines)
│   ├── js/app.js           Maps, charts, sidebar, AJAX
│   └── img/favicon.svg
├── config/
│   └── app.php             DB, settings loader, helpers
├── controllers/
│   └── auth.php            Login / register / logout
├── database/
│   └── schema.sql          Full DB schema + seed data
├── docs/
│   └── api.php             Interactive API documentation
├── models/
│   ├── AddressGenerator.php  Spatial encoding algorithms
│   ├── Structure.php         CRUD + analytics queries
│   └── User.php              User + ApiKey models
├── views/
│   ├── admin/
│   │   ├── analytics.php     Full analytics with charts
│   │   ├── api_keys.php      API key management
│   │   ├── audit_log.php     System audit trail
│   │   ├── service_requests.php
│   │   ├── settings.php      DB-driven settings panel
│   │   ├── structures.php    Verify/reject structures
│   │   ├── users.php         User management
│   │   └── zones.php         Geographic zone management
│   ├── partials/
│   │   ├── footer.php
│   │   ├── head.php
│   │   ├── sidebar.php       Collapsible sidebar
│   │   └── topbar.php
│   ├── 403.php
│   ├── dashboard.php         Analytics overview
│   ├── login.php             Static full-page login
│   ├── map.php               Live map view
│   ├── profile.php           User profile + password
│   ├── register.php          Register structure (map + form)
│   ├── register_user.php     Self-registration
│   ├── search.php            Address search
│   └── view.php              Public address card (shareable)
├── .htaccess
├── index.php
└── README.md
```

---

## Security Notes

- **Passwords**: bcrypt cost 12 (`PASSWORD_BCRYPT`)
- **API Keys**: Stored as SHA-256 hashes — plaintext shown only once at creation
- **Sessions**: Regenerated after login, configurable lifetime, httponly cookies
- **Account Lockout**: Configurable max attempts and lockout duration via settings
- **Audit Log**: All admin actions logged with user, IP, timestamp
- **SQL Injection**: All queries use PDO prepared statements
- **XSS**: All output runs through `htmlspecialchars()`
- **Config Protection**: `.htaccess` denies direct access to `/config` and `/models`
- **CSRF**: Add CSRF tokens to forms for production hardening

---

## Requirements

- PHP 8.2+ with extensions: pdo_mysql, json, mbstring, openssl
- MySQL 8.0+ or MariaDB 10.5+
- Apache 2.4+ with mod_rewrite enabled
- Google Maps JavaScript API key (Maps JavaScript API)

---

## Default Credentials

```
Email:    admin@nedams.ug
Password: Admin@1234
```

**Change this immediately after first login via Admin → Users or My Profile.**

---

## License

NEDAMS is developed for Nakawa East Digital Addressing Initiative, Kampala, Uganda.
=======
# NEDAMS
Digital Addressing System (PROJECT IV)
Enhancing Urban Navigation and Service Delivery through Geospatial Identification in Informal Settlements.
>>>>>>> ebccfb860751d6666a5c3783787d2257f8e5557b
