<?php
// ============================================================
// api/get_coordinates.php  — GET: Resolve code → GPS
// Used by delivery companies, emergency services, visitors
// ============================================================
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../models/Structure.php';
require_once __DIR__.'/../models/User.php';
require_once __DIR__.'/../models/AddressGenerator.php';

$t0 = microtime(true);

// Validate API key (optional but logged)
$keyRecord = null;
$apiKeyHeader = $_SERVER['HTTP_X_NEDAMS_KEY'] ?? '';
if ($apiKeyHeader) {
    $keyRecord = ApiKey::validate($apiKeyHeader);
    if (!$keyRecord) {
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Invalid or expired API key.']);
        exit;
    }
}

// Validate code
$code = strtoupper(trim($_GET['code'] ?? ''));
if (!$code) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Address code required. Use ?code=NE4K7X']);
    exit;
}
if (!preg_match('/^[A-Z0-9]{4,16}$/', $code)) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Invalid address code format.','code'=>$code]);
    exit;
}

$s = Structure::findByCode($code);
if (!$s) {
    http_response_code(404);
    echo json_encode(['success'=>false,'message'=>'Address code not found.','code'=>$code]);
    exit;
}

// Log service request
$ms = round((microtime(true)-$t0)*1000);
$purpose = in_array($_GET['purpose']??'',['delivery','emergency','visit','verification','survey','other'])
           ? $_GET['purpose'] : 'delivery';
Structure::logServiceRequest([
    'address_code'   => $code,
    'api_key_id'     => $keyRecord['id'] ?? null,
    'requester_name' => strip_tags($_GET['requester'] ?? $_SERVER['HTTP_X_REQUESTER'] ?? ''),
    'requester_org'  => strip_tags($_GET['org']       ?? ''),
    'requester_phone'=> strip_tags($_GET['phone']     ?? ''),
    'purpose'        => $purpose,
    'response_code'  => 200,
    'response_ms'    => $ms,
]);

$lat = (float)$s['latitude'];
$lng = (float)$s['longitude'];

echo json_encode([
    'success'       => true,
    'address_code'  => $s['address_code'],
    'coordinates'   => ['latitude' => $lat, 'longitude' => $lng],
    'resident'      => ['name' => $s['resident_name'], 'phone' => $s['phone']],
    'location'      => [
        'description'    => $s['description'],
        'landmarks'      => $s['landmarks'] ?? null,
        'zone'           => $s['zone'],
        'parish'         => $s['parish'],
        'division'       => $s['division'],
        'structure_type' => $s['structure_type'],
    ],
    'confidence_score' => (int)$s['confidence_score'],
    'status'           => $s['status'],
    'navigation'       => [
        'google_maps' => "https://www.google.com/maps?q={$lat},{$lng}",
        'directions'  => "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}",
    ],
    'share_url'  => $s['share_token']
                   ? appUrl().'/s/'.$s['share_token']
                   : appUrl().'/views/view.php?id='.$code,
    'meta'       => ['response_ms' => $ms],
], JSON_UNESCAPED_UNICODE);
