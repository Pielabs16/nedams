<?php
// ============================================================
// models/User.php  — v2.1
// Roles: super_admin | admin | developer | mapper | viewer
// ============================================================
require_once __DIR__.'/../config/app.php';

class User {

    const ROLES = ['super_admin','admin','developer','mapper','viewer'];

    public static function register(array $d): array {
        $pdo = getDB();
        foreach (['full_name','email','password'] as $f) {
            if (empty(trim($d[$f]??'')))
                return ['success'=>false,'message'=>"Field '$f' is required."];
        }
        $email = strtolower(trim($d['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            return ['success'=>false,'message'=>'Invalid email address.'];

        // Check duplicate
        $chk = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch())
            return ['success'=>false,'message'=>'That email address is already registered.'];

        $hash = password_hash($d['password'], PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST]);
        $role = in_array($d['role']??'', self::ROLES) ? $d['role'] : 'viewer';
        $phone = normalisePhone($d['phone'] ?? null);

        $stmt = $pdo->prepare(
            'INSERT INTO users(full_name,email,phone,password,role) VALUES(?,?,?,?,?)'
        );
        $stmt->execute([trim($d['full_name']), $email, $phone, $hash, $role]);
        $id = (int)$pdo->lastInsertId();
        auditLog('register','users','user',(string)$id,"New user: $email role=$role");
        return ['success'=>true,'id'=>$id];
    }

    public static function login(string $email, string $password): array {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        $maxAttempts = (int) setting('security.max_login_attempts', 5);
        $lockoutSec  = (int) setting('security.lockout_duration',   900);

        if (!$user) return ['success'=>false,'message'=>'Invalid credentials.'];
        if (!$user['is_active']) return ['success'=>false,'message'=>'Your account has been deactivated. Contact an admin.'];

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['success'=>false,'message'=>"Account locked. Try again in {$mins} minute(s)."];
        }

        if (!password_verify($password, $user['password'])) {
            $attempts = $user['failed_attempts'] + 1;
            if ($attempts >= $maxAttempts) {
                $until = date('Y-m-d H:i:s', time() + $lockoutSec);
                $pdo->prepare('UPDATE users SET failed_attempts=?,locked_until=? WHERE id=?')
                    ->execute([$attempts, $until, $user['id']]);
                return ['success'=>false,'message'=>"Too many failed attempts. Account locked for ".ceil($lockoutSec/60)." minutes."];
            }
            $pdo->prepare('UPDATE users SET failed_attempts=? WHERE id=?')
                ->execute([$attempts, $user['id']]);
            $remaining = $maxAttempts - $attempts;
            return ['success'=>false,'message'=>"Invalid credentials. {$remaining} attempt(s) remaining."];
        }

        // Success
        $pdo->prepare(
            'UPDATE users SET failed_attempts=0,locked_until=NULL,
             last_login=NOW(),last_login_ip=?,login_count=login_count+1 WHERE id=?'
        )->execute([$_SERVER['REMOTE_ADDR']??null, $user['id']]);

        auditLog('login','auth','user',(string)$user['id'],"Login from ".($_SERVER['REMOTE_ADDR']??'?'));
        unset($user['password']);
        return ['success'=>true,'user'=>$user];
    }

    public static function findById(int $id): ?array {
        $s = getDB()->prepare(
            'SELECT id,full_name,email,phone,role,is_active,avatar,email_verified,
                    last_login,login_count,created_at
             FROM users WHERE id=? LIMIT 1'
        );
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public static function all(array $filters = []): array {
        $where = ['1=1']; $params = [];
        if (!empty($filters['role']))   { $where[] = 'role=?';     $params[] = $filters['role']; }
        if (!empty($filters['active'])) { $where[] = 'is_active=?';$params[] = (int)$filters['active']; }
        if (!empty($filters['q'])) {
            $like = '%'.$filters['q'].'%';
            $where[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $wq   = implode(' AND ', $where);
        $stmt = getDB()->prepare(
            "SELECT id,full_name,email,phone,role,is_active,email_verified,
                    last_login,login_count,created_at
             FROM users WHERE $wq ORDER BY created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function update(int $id, array $d): bool {
        $fields = []; $params = [];
        $allowed = ['full_name','phone','role','is_active','avatar','email_verified','email'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $d)) {
                $fields[]  = "$f=?";
                $params[]  = $f === 'phone' ? normalisePhone($d[$f]) : $d[$f];
            }
        }
        if (!$fields) return false;
        $params[] = $id;
        getDB()->prepare('UPDATE users SET '.implode(',',$fields).' WHERE id=?')
               ->execute($params);
        auditLog('update','users','user',(string)$id,'Profile updated');
        return true;
    }

    public static function updatePassword(int $id, string $password): bool {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST]);
        getDB()->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash, $id]);
        auditLog('password_change','users','user',(string)$id,'Password changed');
        return true;
    }

    public static function toggleActive(int $id): bool {
        getDB()->prepare('UPDATE users SET is_active=NOT is_active WHERE id=?')->execute([$id]);
        auditLog('toggle_active','users','user',(string)$id,'Active status toggled');
        return true;
    }
}

// ============================================================
// ApiKey model  (developer + admin roles only)
// ============================================================
class ApiKey {

    public static function create(array $d): array {
        $pdo       = getDB();
        $plaintext = 'nk_'.bin2hex(random_bytes(20));
        $hash      = hash('sha256', $plaintext);
        $prefix    = substr($plaintext, 0, 8);

        $stmt = $pdo->prepare(
            'INSERT INTO api_keys(name,organisation,key_hash,key_prefix,
                                  permissions,rate_limit,expires_at,notes,created_by)
             VALUES(?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $d['name'],
            $d['organisation'] ?? null,
            $hash, $prefix,
            $d['permissions']  ?? 'read',
            (int)($d['rate_limit'] ?? 1000),
            $d['expires_at']   ?? null,
            $d['notes']        ?? null,
            $d['created_by']   ?? null,
        ]);
        $id = (int)$pdo->lastInsertId();
        auditLog('create','api_keys','api_key',(string)$id,"Key created: $prefix...");
        return ['success'=>true,'key'=>$plaintext,'prefix'=>$prefix,'id'=>$id];
    }

    public static function validate(string $plaintext): ?array {
        $hash = hash('sha256', $plaintext);
        $stmt = getDB()->prepare(
            'SELECT * FROM api_keys WHERE key_hash=? AND is_active=1 LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) return null;
        if ($row['expires_at'] && strtotime($row['expires_at']) < time()) return null;
        getDB()->prepare(
            'UPDATE api_keys SET usage_count=usage_count+1,last_used=NOW() WHERE id=?'
        )->execute([$row['id']]);
        return $row;
    }

    public static function all(): array {
        return getDB()->query(
            'SELECT k.*, u.full_name AS creator_name
             FROM api_keys k
             LEFT JOIN users u ON u.id=k.created_by
             ORDER BY k.created_at DESC'
        )->fetchAll();
    }

    public static function revoke(int $id): void {
        getDB()->prepare('UPDATE api_keys SET is_active=0 WHERE id=?')->execute([$id]);
        auditLog('revoke','api_keys','api_key',(string)$id,'Key revoked');
    }
}
