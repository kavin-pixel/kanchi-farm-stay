<?php
/**
 * iCal Export — generates an iCalendar (.ics) feed for one room.
 *
 * URL: /channel-manager/ical-export.php?room=wooden-villa&token=ksf-ical-secret-2025
 *
 * Subscribe this URL in Airbnb / Booking.com so they see your blocked dates.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$roomId = trim($_GET['room'] ?? '');
$token  = trim($_GET['token'] ?? '');
$rooms  = ROOM_IDS;

// Basic token check — keeps feed URLs from being trivially guessable
if ($token !== ICAL_TOKEN) {
    http_response_code(403);
    exit('Invalid token.');
}

if (!$roomId || !isset($rooms[$roomId])) {
    http_response_code(404);
    exit('Room not found.');
}

$roomName = $rooms[$roomId];

// Fetch all confirmed bookings for this room (past year onward — Airbnb likes history)
$db   = getDB();
$stmt = $db->prepare("
    SELECT * FROM bookings
    WHERE room_id = ? AND status = 'confirmed'
      AND check_out >= date('now', '-1 year')
    ORDER BY check_in
");
$stmt->execute([$roomId]);
$bookings = $stmt->fetchAll();

// Output iCal
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="' . $roomId . '.ics"');
header('Cache-Control: no-cache, must-revalidate');

$now = gmdate('Ymd\THis\Z');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Kanchi Farm Stay//Channel Manager//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:Kanchi Farm Stay - {$roomName}\r\n";
echo "X-WR-CALDESC:Availability calendar for {$roomName}\r\n";
echo "X-WR-TIMEZONE:Asia/Kolkata\r\n";
echo "REFRESH-INTERVAL;VALUE=DURATION:PT1H\r\n";
echo "X-PUBLISHED-TTL:PT1H\r\n";

foreach ($bookings as $b) {
    $uid        = $b['uid'] ?: ('bk-' . $b['id'] . '@kanchifarmstay.com');
    $dtStart    = date('Ymd', strtotime($b['check_in']));
    $dtEnd      = date('Ymd', strtotime($b['check_out']));
    $sourceUC   = strtoupper($b['source'] ?? 'DIRECT');
    $summary    = "Booked - {$sourceUC}";
    $guestLine  = $b['guest_name'] !== 'Blocked' && $b['guest_name'] ? $b['guest_name'] : 'Guest';
    $desc       = "Guest: {$guestLine} | Source: {$sourceUC}";
    if (!empty($b['booking_ref'])) $desc .= " | Ref: {$b['booking_ref']}";

    echo "BEGIN:VEVENT\r\n";
    echo "UID:{$uid}\r\n";
    echo "DTSTAMP:{$now}\r\n";
    echo "DTSTART;VALUE=DATE:{$dtStart}\r\n";
    echo "DTEND;VALUE=DATE:{$dtEnd}\r\n";
    echo "SUMMARY:{$summary}\r\n";
    echo "DESCRIPTION:{$desc}\r\n";
    echo "STATUS:CONFIRMED\r\n";
    echo "TRANSP:OPAQUE\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";
