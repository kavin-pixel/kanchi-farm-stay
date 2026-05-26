<?php
/**
 * Google Hotel Center ARI (Availability, Rates & Inventory) Price Feed
 *
 * URL: /channel-manager/price-feed.php?token=PRICE_FEED_TOKEN
 *
 * Returns XML in Google Transaction Message format.
 * Generates rates for the next 90 days per room using calculateDynamicPrice().
 *
 * Submit this URL to Google Hotel Center:
 *   https://hotel-ads.google.com → Property → ARI Settings → Price Feed URL
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pricing-engine.php';

// Token auth
$token = $_GET['token'] ?? '';
if (!defined('PRICE_FEED_TOKEN') || !hash_equals(PRICE_FEED_TOKEN, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden — invalid token';
    exit;
}

// Cache: serve same output for 1 hour to reduce DB load from Google's crawlers
$cacheFile = sys_get_temp_dir() . '/ksf_price_feed_' . md5(PRICE_FEED_TOKEN) . '.xml';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Cache: HIT');
    readfile($cacheFile);
    exit;
}

$propertyId  = 1;
$lookAhead   = 90; // days
$today       = new DateTime('today');
$blockedByRoom = [];

// Pre-load all blocked ranges per room for efficiency
foreach (ROOM_IDS as $roomId => $_) {
    $ranges = getBlockedRanges($roomId);
    $blockedByRoom[$roomId] = array_map(fn($r) => [$r['check_in'], $r['check_out']], $ranges);
}

function isDateBlocked(string $date, array $blockedRanges): bool {
    foreach ($blockedRanges as [$ci, $co]) {
        if ($date >= $ci && $date < $co) return true;
    }
    return false;
}

// Build XML
$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent(true);
$xml->setIndentString('  ');
$xml->startDocument('1.0', 'UTF-8');

// Google Transaction Message root
$xml->startElement('transaction');
$xml->writeAttribute('timestamp', date('Y-m-d') . 'T' . date('H:i:s') . 'Z');
$xml->writeAttribute('id', 'ksf-' . date('Ymd-His'));

// Property ID — must match what you register in Google Hotel Center
$xml->startElement('property_data_set');
$xml->writeElement('property', SITE_URL); // Use your Hotel Center property ID here

// Loop: each room × each night in next 90 days
foreach (ROOM_IDS as $roomId => $roomName) {
    for ($i = 0; $i < $lookAhead; $i++) {
        $checkInDt  = clone $today;
        $checkInDt->modify("+{$i} days");
        $checkOutDt = clone $checkInDt;
        $checkOutDt->modify('+1 day');

        $checkIn  = $checkInDt->format('Y-m-d');
        $checkOut = $checkOutDt->format('Y-m-d');

        // Determine availability
        $available = !isDateBlocked($checkIn, $blockedByRoom[$roomId] ?? []);

        // Get dynamic price for 1-night stay
        $pricing = calculateDynamicPrice($roomId, $checkIn, $checkOut, $propertyId);
        $price   = round($pricing['final_price']);

        // RoomBundle element (one per room per night)
        $xml->startElement('Result');

        $xml->startElement('Property');
        $xml->text(SITE_URL);
        $xml->endElement(); // Property

        $xml->startElement('Checkin');
        $xml->text($checkIn);
        $xml->endElement(); // Checkin

        $xml->startElement('Nights');
        $xml->text('1');
        $xml->endElement(); // Nights

        $xml->startElement('RoomID');
        $xml->text($roomId);
        $xml->endElement(); // RoomID

        // Baserate: the price for this room for 1 night
        $xml->startElement('Baserate');
        $xml->writeAttribute('currency', 'INR');
        $xml->writeAttribute('all_inclusive', 'true');
        if (!$available) $xml->writeAttribute('unavailable', '1');
        $xml->text($available ? (string)$price : '0');
        $xml->endElement(); // Baserate

        // Tax (0 for room stays in India — adjust if GST applies to your property)
        $xml->startElement('Tax');
        $xml->writeAttribute('currency', 'INR');
        $xml->text('0');
        $xml->endElement(); // Tax

        // OtherFees (0 — no resort/cleaning fee by default)
        $xml->startElement('OtherFees');
        $xml->writeAttribute('currency', 'INR');
        $xml->text('0');
        $xml->endElement(); // OtherFees

        // AllowablePointsOfSale: direct booking only
        $xml->startElement('AllowablePointsOfSale');
        $xml->startElement('PointOfSale');
        $xml->writeAttribute('id', 'direct');
        $xml->endElement(); // PointOfSale
        $xml->endElement(); // AllowablePointsOfSale

        $xml->endElement(); // Result
    }
}

$xml->endElement(); // property_data_set
$xml->endElement(); // transaction
$xml->endDocument();

$output = $xml->outputMemory();

// Cache to file
file_put_contents($cacheFile, $output);

header('Content-Type: application/xml; charset=utf-8');
header('X-Cache: MISS');
echo $output;
