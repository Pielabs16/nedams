<?php
// ============================================================
// api/get_markers.php  — GET: Map markers for viewport
// ============================================================
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../models/Structure.php';

$swLat = filter_input(INPUT_GET,'swLat',FILTER_VALIDATE_FLOAT);
$swLng = filter_input(INPUT_GET,'swLng',FILTER_VALIDATE_FLOAT);
$neLat = filter_input(INPUT_GET,'neLat',FILTER_VALIDATE_FLOAT);
$neLng = filter_input(INPUT_GET,'neLng',FILTER_VALIDATE_FLOAT);

// If no bounds, return all (limited)
if ($swLat===false||$swLng===false||$neLat===false||$neLng===false) {
    $swLat=-1.5; $swLng=29.5; $neLat=4.5; $neLng=35.1;
}

$limit  = min(500,(int)setting('maps.max_markers',500));
$rows   = Structure::findInBounds($swLat,$swLng,$neLat,$neLng,$limit);

echo json_encode([
    'success' => true,
    'count'   => count($rows),
    'markers' => array_map(fn($r) => [
        'code'        => $r['address_code'],
        'lat'         => (float)$r['latitude'],
        'lng'         => (float)$r['longitude'],
        'name'        => $r['resident_name'],
        'description' => $r['description'],
        'type'        => $r['structure_type'],
        'status'      => $r['status'],
        'confidence'  => (int)$r['confidence_score'],
        'share_url'   => $r['share_token']
                         ? appUrl().'/s/'.$r['share_token']
                         : appUrl().'/views/view.php?id='.$r['address_code'],
    ], $rows),
], JSON_UNESCAPED_UNICODE);
