<?php
/**
 * iCal Sync — fetches external calendars (Airbnb, Booking.com, etc.)
 * and imports their blocked dates into our database.
 *
 * Call this:
 *   - From the admin panel (sync button) — session-authenticated
 *   - Via cron:  php /path/to/channel-manager/sync.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/whatsapp.php';

// ---- iCal parser --------------------------------------------

function parseIcalFeed(string $raw): array {
    $events = [];
    // Unfold continuation lines (RFC 5545)
    $raw   = preg_replace("/\r\n[ \t]/", '', $raw);
    $raw   = preg_replace("/\r\n/", "\n", $raw);
    $lines = explode("\n", $raw);

    $inEvent = false;
    $ev      = [];

    foreach ($lines as $line) {
        $line = rtrim($line);
        if ($line === 'BEGIN:VEVENT') {
            $inEvent = true;
            $ev = [];
        } elseif ($line === 'END:VEVENT' && $inEvent) {
            $inEvent = false;
            $events[] = $ev;
        } elseif ($inEvent && strpos($line, ':') !== false) {
            [$rawKey, $value] = explode(':', $line, 2);
            $key = strtoupper(explode(';', $rawKey)[0]); // strip params
            $ev[$key] = $value;
        }
    }

    return $events;
}

function icalDateToYmd(string $val): ?string {
    if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $val, $m)) {
        return "{$m[1]}-{$m[2]}-{$m[3]}";
    }
    return null;
}

// ---- Sync one calendar entry --------------------------------

function syncOneCalendar(array $cal): array {
    $rooms    = ROOM_IDS;
    $roomId   = $cal['room_id'];
    $platform = $cal['platform'];
    $roomName = $rooms[$roomId] ?? $roomId;

    // Fetch the remote iCal
    $ch = curl_init($cal['ical_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'KanchiFarmStay-ChannelManager/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$raw || $httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP {$httpCode}", 'imported' => 0, 'new_bookings' => []];
    }

    $events      = parseIcalFeed($raw);
    $imported    = 0;
    $newBookings = [];

    // Collect UIDs seen in this feed (to remove stale blocks later)
    $feedUids = [];

    foreach ($events as $ev) {
        $checkIn  = icalDateToYmd($ev['DTSTART'] ?? '');
        $checkOut = icalDateToYmd($ev['DTEND']   ?? '');
        if (!$checkIn || !$checkOut) continue;

        // Skip events already in the past
        if ($checkOut < date('Y-m-d')) continue;

        $uid = $ev['UID'] ?? null;
        if ($uid) $feedUids[] = $uid;

        $booking = [
            'room_id'     => $roomId,
            'room_name'   => $roomName,
            'check_in'    => $checkIn,
            'check_out'   => $checkOut,
            'guest_name'  => 'Blocked',
            'guest_email' => '',
            'guest_phone' => '',
            'source'      => $platform,
            'booking_ref' => $uid ?? '',
            'amount'      => 0,
            'status'      => 'confirmed',
            'uid'         => $uid,
            'notes'       => $ev['SUMMARY'] ?? '',
        ];

        $id = addBooking($booking);
        if ($id !== false) {
            $imported++;
            $newBookings[] = $booking;
        }
    }

    // Remove stale future blocks from this platform that are no longer in the feed
    if (!empty($feedUids)) {
        $db     = getDB();
        $ph     = implode(',', array_fill(0, count($feedUids), '?'));
        $params = array_merge([$roomId, $platform, date('Y-m-d')], $feedUids);
        $db->prepare("
            DELETE FROM bookings
            WHERE room_id = ? AND source = ? AND check_out >= ?
              AND uid IS NOT NULL AND uid NOT IN ({$ph})
        ")->execute($params);
    }

    touchLastSynced($cal['id']);

    return ['success' => true, 'imported' => $imported, 'new_bookings' => $newBookings, 'error' => ''];
}

// ---- Main entry point ---------------------------------------

$isCli = php_sapi_name() === 'cli';
$isWeb = isset($_GET['run']);

if ($isCli || $isWeb) {
    // Web calls must be authenticated via session
    if ($isWeb) {
        session_start();
        if (empty($_SESSION['admin_logged_in'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    $calendars   = getExternalCalendars();
    $results     = [];
    $allNewBooks = [];

    foreach ($calendars as $cal) {
        $res       = syncOneCalendar($cal);
        $results[] = array_merge($res, ['platform' => $cal['platform'], 'room_id' => $cal['room_id']]);
        if ($res['success'] && !empty($res['new_bookings'])) {
            $allNewBooks = array_merge($allNewBooks, $res['new_bookings']);
        }
    }

    // WhatsApp notifications for genuinely new bookings
    foreach ($allNewBooks as $b) {
        sendWhatsAppNotification(buildBookingMessage($b));
    }

    if ($isCli) {
        foreach ($results as $r) {
            $status = $r['success']
                ? "OK — {$r['imported']} new block(s) imported"
                : "FAILED — {$r['error']}";
            echo "[{$r['platform']}] {$r['room_id']}: {$status}\n";
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['results' => $results]);
    }
}
