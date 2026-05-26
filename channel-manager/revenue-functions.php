<?php
/**
 * Revenue Management Functions
 * ADR, RevPAR, occupancy, pacing, channel mix, pickup report.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function calculateADR(int $propertyId = 1, ?string $dateFrom = null, ?string $dateTo = null): float {
    $from = $dateFrom ?? date('Y-m-01');
    $to   = $dateTo   ?? date('Y-m-t');
    $stmt = getDB()->prepare("SELECT SUM(amount) as rev, SUM(CAST(ceil((julianday(check_out)-julianday(check_in))) AS INT)) as nights FROM bookings WHERE property_id=? AND status='confirmed' AND check_in >= ? AND check_in <= ? AND amount > 0");
    $stmt->execute([$propertyId, $from, $to]);
    $row = $stmt->fetch();
    $nights = (int)($row['nights'] ?? 0);
    return $nights > 0 ? round((float)$row['rev'] / $nights, 2) : 0.0;
}

function calculateRevPAR(int $propertyId = 1, ?string $dateFrom = null, ?string $dateTo = null): float {
    $from      = $dateFrom ?? date('Y-m-01');
    $to        = $dateTo   ?? date('Y-m-t');
    $totalRooms= count(ROOM_IDS);
    $days      = max(1, (int)ceil((strtotime($to) - strtotime($from)) / 86400) + 1);
    $adr       = calculateADR($propertyId, $from, $to);
    $occ       = getOccupancyPct($propertyId, $from, $to);
    return round($adr * $occ / 100, 2);
}

function getOccupancyPct(int $propertyId = 1, ?string $dateFrom = null, ?string $dateTo = null): float {
    $from       = $dateFrom ?? date('Y-m-01');
    $to         = $dateTo   ?? date('Y-m-t');
    $totalRooms = count(ROOM_IDS);
    $days       = max(1, (int)ceil((strtotime($to) - strtotime($from)) / 86400) + 1);
    $available  = $totalRooms * $days;

    $stmt = getDB()->prepare("SELECT check_in, check_out FROM bookings WHERE property_id=? AND status='confirmed' AND check_out >= ? AND check_in <= ?");
    $stmt->execute([$propertyId, $from, $to]);
    $bookings = $stmt->fetchAll();

    $bookedNights = 0;
    foreach ($bookings as $b) {
        $start = max(strtotime($from), strtotime($b['check_in']));
        $end   = min(strtotime($to),   strtotime($b['check_out']));
        $n = max(0, (int)ceil(($end - $start) / 86400));
        $bookedNights += $n;
    }
    return $available > 0 ? round($bookedNights / $available * 100, 1) : 0.0;
}

function getPacingReport(int $propertyId = 1, int $lookAheadDays = 30): array {
    $from     = date('Y-m-d');
    $to       = date('Y-m-d', strtotime("+{$lookAheadDays} days"));
    $fromLY   = date('Y-m-d', strtotime("-1 year"));
    $toLY     = date('Y-m-d', strtotime("-1 year +{$lookAheadDays} days"));

    // This year: bookings for the next N days
    $stmt = getDB()->prepare("SELECT COUNT(*) as cnt, SUM(amount) as rev FROM bookings WHERE property_id=? AND status='confirmed' AND check_in >= ? AND check_in <= ?");
    $stmt->execute([$propertyId, $from, $to]);
    $ty = $stmt->fetch();

    // Last year: same window
    $stmt->execute([$propertyId, $fromLY, $toLY]);
    $ly = $stmt->fetch();

    return [
        'this_year' => ['bookings' => (int)$ty['cnt'], 'revenue' => (float)$ty['rev']],
        'last_year' => ['bookings' => (int)$ly['cnt'], 'revenue' => (float)$ly['rev']],
        'days'      => $lookAheadDays,
    ];
}

function getChannelMixRevenue(int $propertyId = 1, ?string $dateFrom = null, ?string $dateTo = null): array {
    $from = $dateFrom ?? date('Y-m-01');
    $to   = $dateTo   ?? date('Y-m-t');
    $stmt = getDB()->prepare("SELECT source, COUNT(*) as bookings, SUM(amount) as gross_revenue FROM bookings WHERE property_id=? AND status='confirmed' AND check_in >= ? AND check_in <= ? AND amount > 0 GROUP BY source ORDER BY gross_revenue DESC");
    $stmt->execute([$propertyId, $from, $to]);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $r) {
        $commPct = OTA_COMMISSIONS[strtolower($r['source'])] ?? 0;
        $result[] = [
            'source'        => $r['source'],
            'bookings'      => (int)$r['bookings'],
            'gross_revenue' => (float)$r['gross_revenue'],
            'commission_pct'=> $commPct,
            'net_revenue'   => round((float)$r['gross_revenue'] * (1 - $commPct / 100), 2),
        ];
    }
    return $result;
}

function getPickupReport(int $propertyId = 1, int $lookbackDays = 30): array {
    $from = date('Y-m-d', strtotime("-{$lookbackDays} days"));
    $stmt = getDB()->prepare("SELECT date(created_at) as day, COUNT(*) as bookings, SUM(amount) as revenue FROM bookings WHERE property_id=? AND status='confirmed' AND date(created_at) >= ? GROUP BY date(created_at) ORDER BY day ASC");
    $stmt->execute([$propertyId, $from]);
    return $stmt->fetchAll();
}

function getRoomPerformance(int $propertyId = 1, ?string $dateFrom = null, ?string $dateTo = null): array {
    $from = $dateFrom ?? date('Y-m-01');
    $to   = $dateTo   ?? date('Y-m-t');
    $stmt = getDB()->prepare("SELECT room_id, room_name, COUNT(*) as bookings, SUM(amount) as revenue, SUM(CAST(ceil((julianday(check_out)-julianday(check_in))) AS INT)) as nights FROM bookings WHERE property_id=? AND status='confirmed' AND check_in >= ? AND check_in <= ? GROUP BY room_id ORDER BY revenue DESC");
    $stmt->execute([$propertyId, $from, $to]);
    return $stmt->fetchAll();
}

function takeRevenueSnapshot(int $propertyId = 1): void {
    $today      = date('Y-m-d');
    $from       = date('Y-m-01');
    $to         = $today;
    $totalRooms = count(ROOM_IDS);

    $adr    = calculateADR($propertyId, $from, $to);
    $occ    = getOccupancyPct($propertyId, $from, $to);
    $revpar = $adr * $occ / 100;

    $stmt = getDB()->prepare("SELECT SUM(amount) as rev FROM bookings WHERE property_id=? AND status='confirmed' AND check_in >= ? AND check_in <= ?");
    $stmt->execute([$propertyId, $from, $to]);
    $rev = (float)($stmt->fetch()['rev'] ?? 0);

    $days = max(1, (int)ceil((strtotime($to) - strtotime($from)) / 86400) + 1);
    $stmt2 = getDB()->prepare("SELECT check_in, check_out FROM bookings WHERE property_id=? AND status='confirmed' AND check_out >= ? AND check_in <= ?");
    $stmt2->execute([$propertyId, $from, $to]);
    $bookedNights = 0;
    foreach ($stmt2->fetchAll() as $b) {
        $s = max(strtotime($from), strtotime($b['check_in']));
        $e = min(strtotime($to),   strtotime($b['check_out']));
        $bookedNights += max(0, (int)ceil(($e - $s) / 86400));
    }

    upsertRevenueSnapshot($propertyId, $today, [
        'adr'           => $adr,
        'revpar'        => round($revpar, 2),
        'occupancy_pct' => $occ,
        'total_rooms'   => $totalRooms,
        'rooms_occupied'=> $bookedNights,
        'gross_revenue' => $rev,
    ]);
}
