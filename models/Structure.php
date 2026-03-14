<?php
// ============================================================
// models/Structure.php  — v2.0
// ============================================================

require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/AddressGenerator.php';

class Structure {

    public static function create(array $d): array {
        $pdo = getDB();
        foreach (['latitude','longitude','resident_name'] as $f) {
            if (empty($d[$f])) return ['success'=>false,'message'=>"Field '$f' required."];
        }
        $lat = (float)$d['latitude'];
        $lng = (float)$d['longitude'];
        if ($lat < -1.5||$lat > 4.5||$lng < 29.5||$lng > 35.1)
            return ['success'=>false,'message'=>'Coordinates outside Uganda bounding box.'];

        $code     = AddressGenerator::generate($lat, $lng, $pdo);
        $conf     = AddressGenerator::confidenceScore($d['accuracy_meters'] ?? null);
        $autoVerify = settingBool('addressing.auto_verify', false);
        $status   = $autoVerify ? 'verified' : 'pending';

        $stmt = $pdo->prepare('
            INSERT INTO structures
              (address_code,latitude,longitude,resident_name,phone,email,description,
               landmarks,zone,parish,division,structure_type,floor_count,occupant_count,
               photo_path,confidence_score,accuracy_meters,registered_by,status)
            VALUES
              (:code,:lat,:lng,:name,:phone,:email,:desc,
               :land,:zone,:parish,:div,:type,:floors,:occ,
               :photo,:conf,:acc,:uid,:status)');
        $stmt->execute([
            ':code'   => $code,      ':lat'   => $lat,         ':lng'  => $lng,
            ':name'   => trim($d['resident_name']),
            ':phone'  => $d['phone']          ?? null,
            ':email'  => $d['email']          ?? null,
            ':desc'   => $d['description']    ?? null,
            ':land'   => $d['landmarks']      ?? null,
            ':zone'   => $d['zone']           ?? null,
            ':parish' => $d['parish']         ?? null,
            ':div'    => $d['division']       ?? 'Nakawa',
            ':type'   => in_array($d['structure_type']??'',
                           ['residential','commercial','school','clinic',
                            'worship','government','ngo','other'])
                         ? $d['structure_type'] : 'residential',
            ':floors' => max(1,(int)($d['floor_count']??1)),
            ':occ'    => max(1,(int)($d['occupant_count']??1)),
            ':photo'  => $d['photo_path']     ?? null,
            ':conf'   => $conf,
            ':acc'    => $d['accuracy_meters']?? null,
            ':uid'    => $d['registered_by']  ?? null,
            ':status' => $status,
        ]);
        $id = (int)$pdo->lastInsertId();
        auditLog('create','structures','structure',$code,"Registered $code by uid ".($d['registered_by']??'anon'));
        return ['success'=>true,'address_code'=>$code,'id'=>$id,'status'=>$status];
    }

    public static function findByCode(string $code): ?array {
        $stmt = getDB()->prepare('
            SELECT s.*, 
                   u.full_name AS mapper_name, u.email AS mapper_email,
                   v.full_name AS verifier_name
            FROM   structures s
            LEFT JOIN users u ON u.id = s.registered_by
            LEFT JOIN users v ON v.id = s.verified_by
            WHERE  s.address_code = ? LIMIT 1');
        $stmt->execute([strtoupper(trim($code))]);
        return $stmt->fetch() ?: null;
    }

    public static function incrementView(string $code): void {
        getDB()->prepare('UPDATE structures SET view_count=view_count+1 WHERE address_code=?')
               ->execute([$code]);
    }

    public static function findInBounds(float $s, float $w, float $n, float $e, int $limit=500): array {
        $stmt = getDB()->prepare('
            SELECT address_code,latitude,longitude,resident_name,
                   description,structure_type,status,confidence_score,share_token
            FROM   structures
            WHERE  latitude BETWEEN :s AND :n
              AND  longitude BETWEEN :w AND :e
              AND  status != "rejected"
            LIMIT  :lim');
        $stmt->bindValue(':s',$s); $stmt->bindValue(':n',$n);
        $stmt->bindValue(':w',$w); $stmt->bindValue(':e',$e);
        $stmt->bindValue(':lim',$limit,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function search(string $q, int $limit=20): array {
        $like = '%'.$q.'%';
        $stmt = getDB()->prepare('
            SELECT address_code,latitude,longitude,resident_name,phone,
                   description,zone,parish,structure_type,status,confidence_score,
                   share_token
            FROM   structures
            WHERE  address_code  LIKE :c
               OR  resident_name LIKE :n
               OR  zone          LIKE :z
               OR  phone         LIKE :p
               OR  description   LIKE :d
            ORDER BY status="verified" DESC, confidence_score DESC
            LIMIT  :lim');
        $stmt->bindValue(':c', strtoupper($q).'%');
        $stmt->bindValue(':n', $like);
        $stmt->bindValue(':z', $like);
        $stmt->bindValue(':p', $like);
        $stmt->bindValue(':d', $like);
        $stmt->bindValue(':lim',$limit,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function all(int $page=1, int $perPage=25, array $filters=[]): array {
        $where = ['1=1']; $params = [];
        if (!empty($filters['status']))  { $where[]='s.status=?';         $params[]=$filters['status']; }
        if (!empty($filters['type']))    { $where[]='s.structure_type=?'; $params[]=$filters['type']; }
        if (!empty($filters['zone']))    { $where[]='s.zone=?';           $params[]=$filters['zone']; }
        if (!empty($filters['q'])) {
            $like = '%'.$filters['q'].'%';
            $where[] = '(s.address_code LIKE ? OR s.resident_name LIKE ? OR s.phone LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $wq     = implode(' AND ', $where);
        $offset = ($page-1)*$perPage;
        $pdo    = getDB();

        $total = $pdo->prepare("SELECT COUNT(*) FROM structures s WHERE $wq");
        $total->execute($params);

        $stmt = $pdo->prepare("
            SELECT s.id,s.address_code,s.latitude,s.longitude,s.resident_name,
                   s.phone,s.zone,s.parish,s.structure_type,s.status,s.confidence_score,
                   s.floor_count,s.created_at,s.view_count,s.share_token,
                   u.full_name AS mapper_name
            FROM   structures s
            LEFT JOIN users u ON u.id=s.registered_by
            WHERE  $wq
            ORDER  BY s.created_at DESC
            LIMIT  ? OFFSET ?");
        $stmt->execute([...$params, $perPage, $offset]);

        $tot = (int)$total->fetchColumn();
        return ['data'=>$stmt->fetchAll(),'total'=>$tot,'page'=>$page,
                'per_page'=>$perPage,'last_page'=>(int)ceil($tot/$perPage)];
    }

    public static function verify(int $id, int $adminId, string $status, string $reason=''): bool {
        getDB()->prepare(
            'UPDATE structures SET status=?,verified_by=?,verified_at=NOW(),reject_reason=? WHERE id=?'
        )->execute([$status,$adminId,$reason,$id]);
        auditLog('verify','structures','structure',(string)$id,"Set status=$status");
        return true;
    }

    public static function dashboardStats(): array {
        $pdo = getDB();
        return [
            'total'      => (int)$pdo->query('SELECT COUNT(*) FROM structures')->fetchColumn(),
            'verified'   => (int)$pdo->query('SELECT COUNT(*) FROM structures WHERE status="verified"')->fetchColumn(),
            'pending'    => (int)$pdo->query('SELECT COUNT(*) FROM structures WHERE status="pending"')->fetchColumn(),
            'today'      => (int)$pdo->query('SELECT COUNT(*) FROM structures WHERE DATE(created_at)=CURDATE()')->fetchColumn(),
            'this_week'  => (int)$pdo->query('SELECT COUNT(*) FROM structures WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)')->fetchColumn(),
            'api_calls'  => (int)$pdo->query('SELECT COUNT(*) FROM service_requests')->fetchColumn(),
            'users'      => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn(),
        ];
    }

    public static function registrationsByDay(int $days=30): array {
        $stmt = getDB()->prepare('
            SELECT DATE(created_at) AS day, COUNT(*) AS count
            FROM   structures
            WHERE  created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP  BY DATE(created_at)
            ORDER  BY day ASC');
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public static function byType(): array {
        return getDB()->query('
            SELECT structure_type AS label, COUNT(*) AS value
            FROM   structures GROUP BY structure_type ORDER BY value DESC'
        )->fetchAll();
    }

    public static function byZone(int $limit=10): array {
        return getDB()->query('
            SELECT COALESCE(zone,"Unknown") AS zone, COUNT(*) AS count
            FROM   structures GROUP BY zone ORDER BY count DESC LIMIT '.$limit
        )->fetchAll();
    }

    public static function logServiceRequest(array $d): void {
        getDB()->prepare('
            INSERT INTO service_requests
              (address_code,api_key_id,requester_name,requester_org,requester_phone,
               purpose,ip_address,user_agent,response_code,response_ms)
            VALUES(?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $d['address_code'],
            $d['api_key_id']       ?? null,
            $d['requester_name']   ?? null,
            $d['requester_org']    ?? null,
            $d['requester_phone']  ?? null,
            $d['purpose']          ?? 'delivery',
            $_SERVER['REMOTE_ADDR']     ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $d['response_code']    ?? 200,
            $d['response_ms']      ?? null,
        ]);
    }
}
