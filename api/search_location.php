<?php
// ============================================================
// api/search_location.php  — GET: Search structures
// ============================================================
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'GET only']); exit; }

require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../models/Structure.php';

$q = trim(strip_tags($_GET['q'] ?? ''));
if (strlen($q)<2) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Query must be 2+ chars']); exit; }

$results = Structure::search($q, 20);
echo json_encode([
    'success' => true,
    'count'   => count($results),
    'query'   => $q,
    'results' => array_map(fn($r) => [
        'address_code'    => $r['address_code'],
        'latitude'        => (float)$r['latitude'],
        'longitude'       => (float)$r['longitude'],
        'resident_name'   => $r['resident_name'],
        'phone'           => $r['phone'],
        'description'     => $r['description'],
        'zone'            => $r['zone'],
        'status'          => $r['status'],
        'confidence_score'=> (int)$r['confidence_score'],
        'share_url'       => $r['share_token']
                            ? appUrl().'/s/'.$r['share_token']
                            : appUrl().'/views/view.php?id='.$r['address_code'],
    ], $results),
]);
