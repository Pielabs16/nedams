<?php
// ============================================================
// api/register_location.php  — POST: Register new structure
// ============================================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-NEDAMS-Key');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST')    { jsonResponse(['success'=>false,'message'=>'POST required.'],405); }

require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../models/Structure.php';
require_once __DIR__.'/../models/User.php';

$t0 = microtime(true);

// Auth: session or API key
startSession();
$registeredBy = null;
if (!empty($_SESSION['user_id'])) {
    $registeredBy = (int)$_SESSION['user_id'];
} else {
    $apiKey = $_SERVER['HTTP_X_NEDAMS_KEY'] ?? '';
    if (!$apiKey) jsonResponse(['success'=>false,'message'=>'Authentication required.'],401);
    $keyRecord = ApiKey::validate($apiKey);
    if (!$keyRecord) jsonResponse(['success'=>false,'message'=>'Invalid or expired API key.'],401);
    if (!str_contains($keyRecord['permissions'],'write'))
        jsonResponse(['success'=>false,'message'=>'This key has read-only permissions.'],403);
}

// Parse body
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
$input = str_contains($ct,'json') ? (json_decode(file_get_contents('php://input'),true)??[]) : $_POST;

$data = [
    'latitude'       => filter_var($input['latitude']  ?? null, FILTER_VALIDATE_FLOAT),
    'longitude'      => filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT),
    'resident_name'  => trim(strip_tags($input['resident_name']  ?? '')),
    'phone'          => trim(strip_tags($input['phone']          ?? '')),
    'email'          => trim(strip_tags($input['email']          ?? '')),
    'description'    => trim(strip_tags($input['description']    ?? '')),
    'landmarks'      => trim(strip_tags($input['landmarks']      ?? '')),
    'zone'           => trim(strip_tags($input['zone']           ?? '')),
    'parish'         => trim(strip_tags($input['parish']         ?? '')),
    'structure_type' => $input['structure_type'] ?? 'residential',
    'floor_count'    => (int)($input['floor_count'] ?? 1),
    'occupant_count' => (int)($input['occupant_count'] ?? 1),
    'accuracy_meters'=> is_numeric($input['accuracy_meters']??null) ? (float)$input['accuracy_meters'] : null,
    'registered_by'  => $registeredBy,
];

if ($data['latitude']===false||$data['longitude']===false)
    jsonResponse(['success'=>false,'message'=>'Valid latitude and longitude required.'],422);

$result = Structure::create($data);
if (!$result['success']) jsonResponse($result, 422);

$struct = Structure::findByCode($result['address_code']);
$ms     = round((microtime(true)-$t0)*1000);

// Build clean share URL using token
$token  = $struct['share_token'] ?? null;
if (!$token) {
    $token = bin2hex(random_bytes(16));
    getDB()->prepare('UPDATE structures SET share_token=? WHERE address_code=?')
           ->execute([$token, $result['address_code']]);
}
$cleanUrl = appUrl() . '/s/' . $token;

jsonResponse([
    'success'      => true,
    'message'      => 'Structure registered successfully.',
    'address_code' => $result['address_code'],
    'id'           => $result['id'],
    'status'       => $result['status'],
    'share_url'    => $cleanUrl,
    'whatsapp_url' => 'https://wa.me/?text=' . urlencode(
        'My NEDAMS address is ' . $result['address_code'] . ': ' . $cleanUrl),
    'structure' => [
        'address_code'    => $struct['address_code'],
        'latitude'        => (float)$struct['latitude'],
        'longitude'       => (float)$struct['longitude'],
        'resident_name'   => $struct['resident_name'],
        'phone'           => $struct['phone'],
        'zone'            => $struct['zone'],
        'structure_type'  => $struct['structure_type'],
        'confidence_score'=> (int)$struct['confidence_score'],
    ],
    'meta' => ['response_ms' => $ms],
], 201);
