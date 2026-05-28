<?php
/**
 * Public Availability API
 *
 * GET /channel-manager/availability-api.php?room=wooden-villa
 *
 * Returns JSON: { "blocked": [["2025-02-01","2025-02-04"], ...] }
 * Each element is a [check_in, check_out] pair (check_out is the departure day,
 * which IS available for a new check-in).
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$roomId = trim($_GET['room'] ?? '');
$rooms  = ROOM_IDS;

if (!$roomId || !isset($rooms[$roomId])) {
    http_response_code(404);
    echo json_encode(['error' => 'Room not found']);
    exit;
}

// White Villa mutual-block: Room 1, Room 2, and Full Floor are mutually exclusive.
// Booking any one blocks the others.
$whiteVillaLinks = [
    'white-villa'            => ['white-villa-full-floor'],
    'white-villa-room-2'     => ['white-villa-full-floor'],
    'white-villa-full-floor' => ['white-villa', 'white-villa-room-2'],
];

$linkedRooms = $whiteVillaLinks[$roomId] ?? [];

$allRanges = getBlockedRanges($roomId);
foreach ($linkedRooms as $linked) {
    $allRanges = array_merge($allRanges, getBlockedRanges($linked));
}

// Deduplicate by check_in+check_out
$seen    = [];
$blocked = [];
foreach ($allRanges as $r) {
    $key = $r['check_in'] . '|' . $r['check_out'];
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $blocked[]  = [$r['check_in'], $r['check_out']];
    }
}
usort($blocked, fn($a, $b) => strcmp($a[0], $b[0]));

$response = ['room' => $roomId, 'blocked' => $blocked];

// Optional: return dynamic price for the requested dates
if (!empty($_GET['pricing']) && !empty($_GET['check_in']) && !empty($_GET['check_out'])) {
    require_once __DIR__ . '/pricing-engine.php';
    $checkIn  = $_GET['check_in'];
    $checkOut = $_GET['check_out'];
    // Basic date validation
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut) && $checkOut > $checkIn) {
        $pricing = calculateDynamicPrice($roomId, $checkIn, $checkOut);
        $response['price']          = $pricing['final_price'];
        $response['base_price']     = $pricing['base_price'];
        $response['adjustment_pct'] = $pricing['adjustment_pct'];
        $response['rules_applied']  = $pricing['rules_applied'];
    }
}

echo json_encode($response);
