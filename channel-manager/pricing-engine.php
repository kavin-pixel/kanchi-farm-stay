<?php
/**
 * AI Smart Pricing Engine
 * Calculates dynamic prices based on active rules:
 * occupancy, last-minute, weekend premium, length-of-stay discounts, demand events.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function calculateDynamicPrice(string $roomId, string $checkIn, string $checkOut, int $propertyId = 1): array {
    $nights    = max(1, (int)ceil((strtotime($checkOut) - strtotime($checkIn)) / 86400));
    $basePrice = getRoomBasePrice($roomId, $propertyId, ROOM_BASE_PRICES[$roomId] ?? 2000);
    $rules     = getPricingRules($propertyId);
    $active    = array_filter($rules, fn($r) => (int)$r['is_active'] === 1);

    $price        = $basePrice;
    $rulesApplied = [];
    $totalPct     = 0;

    // Sort by priority (lowest number = highest priority)
    usort($active, fn($a, $b) => (int)$a['priority'] - (int)$b['priority']);

    foreach ($active as $rule) {
        $adj = 0;
        switch ($rule['rule_type']) {
            case 'occupancy_high':
                $occ = getCurrentOccupancyPct($propertyId);
                if ($occ >= (float)$rule['condition_value']) {
                    $adj = (float)$rule['adjustment_pct'];
                }
                break;
            case 'occupancy_low':
                $occ = getCurrentOccupancyPct($propertyId);
                if ($occ <= (float)$rule['condition_value']) {
                    $adj = (float)$rule['adjustment_pct'];
                }
                break;
            case 'last_minute':
                $daysAhead = max(0, (int)ceil((strtotime($checkIn) - time()) / 86400));
                if ($daysAhead <= (int)$rule['condition_value']) {
                    $adj = (float)$rule['adjustment_pct'];
                }
                break;
            case 'weekend':
                if (_isWeekendStay($checkIn, $checkOut)) {
                    $adj = (float)$rule['adjustment_pct'];
                }
                break;
            case 'los_discount':
                if ($nights >= (int)$rule['condition_value']) {
                    // Use the largest applicable discount
                    if ((float)$rule['adjustment_pct'] < $adj || $adj === 0) {
                        $adj = (float)$rule['adjustment_pct'];
                    }
                }
                break;
            case 'demand_event':
                $demandAdj = _getDemandEventAdjustment($checkIn, $checkOut, $propertyId);
                if ($demandAdj !== 0) $adj = $demandAdj;
                break;
        }
        if ($adj !== 0) {
            $rulesApplied[] = ['rule' => $rule['rule_name'], 'adj_pct' => $adj];
            $totalPct      += $adj;
        }
    }

    // Also check demand events (independent of rule table)
    $demandAdj = _getDemandEventAdjustment($checkIn, $checkOut, $propertyId);
    if ($demandAdj !== 0) {
        $rulesApplied[] = ['rule' => 'Demand Event', 'adj_pct' => $demandAdj];
        $totalPct      += $demandAdj;
    }

    $finalPrice = $basePrice * (1 + $totalPct / 100);
    // Round to nearest ₹100
    $finalPrice = round($finalPrice / 100) * 100;
    $finalPrice = max($basePrice * 0.5, $finalPrice); // never below 50% of base

    return [
        'base_price'    => $basePrice,
        'final_price'   => (int)$finalPrice,
        'adjustment_pct'=> $totalPct,
        'rules_applied' => $rulesApplied,
        'nights'        => $nights,
    ];
}

function getCurrentOccupancyPct(int $propertyId = 1, int $lookAheadDays = 30): float {
    $db   = getDB();
    $from = date('Y-m-d');
    $to   = date('Y-m-d', strtotime("+{$lookAheadDays} days"));
    $stmt = $db->prepare("SELECT check_in, check_out FROM bookings WHERE property_id=? AND status='confirmed' AND check_out >= ? AND check_in <= ?");
    $stmt->execute([$propertyId, $from, $to]);
    $bookings = $stmt->fetchAll();

    $totalRooms = count(ROOM_IDS);
    $totalAvailable = $totalRooms * $lookAheadDays;
    if ($totalAvailable === 0) return 0.0;

    $bookedNights = 0;
    foreach ($bookings as $b) {
        $start = max(strtotime($from), strtotime($b['check_in']));
        $end   = min(strtotime($to),   strtotime($b['check_out']));
        $n = max(0, (int)ceil(($end - $start) / 86400));
        $bookedNights += $n;
    }

    return round($bookedNights / $totalAvailable * 100, 1);
}

function _isWeekendStay(string $checkIn, string $checkOut): bool {
    $ts  = strtotime($checkIn);
    $end = strtotime($checkOut);
    while ($ts < $end) {
        $dow = (int)date('N', $ts); // 1=Mon … 7=Sun
        if ($dow >= 5) return true;  // Fri, Sat, Sun
        $ts += 86400;
    }
    return false;
}

function _getDemandEventAdjustment(string $checkIn, string $checkOut, int $propertyId): float {
    $events = getDemandEvents($propertyId, $checkIn);
    $maxAdj = 0;
    foreach ($events as $ev) {
        if ($ev['event_date'] >= $checkIn && $ev['event_date'] < $checkOut) {
            $adj = match($ev['demand_level']) {
                'gold'   => 40,
                'high'   => 25,
                'medium' => 15,
                default  => 10,
            };
            $maxAdj = max($maxAdj, $adj);
        }
    }
    return $maxAdj;
}

function generatePricingSuggestions(int $propertyId = 1): void {
    // Throttle to once per day
    $lastRun = getSetting('last_suggestions_generated', $propertyId);
    if ($lastRun === date('Y-m-d')) return;

    $rooms = ROOM_IDS;
    $today = date('Y-m-d');
    $end   = date('Y-m-d', strtotime('+60 days'));

    $db = getDB();
    // Clear old pending suggestions
    $db->prepare("DELETE FROM pricing_suggestions WHERE property_id=? AND status='pending' AND date_from >= ?")->execute([$propertyId, $today]);

    foreach ($rooms as $roomId => $_) {
        $basePrice = getRoomBasePrice($roomId, $propertyId, ROOM_BASE_PRICES[$roomId] ?? 2000);
        // Check in weekly blocks
        $ts = strtotime($today);
        while (($date = date('Y-m-d', $ts)) < $end) {
            $dateTo = date('Y-m-d', $ts + 86400);
            $result = calculateDynamicPrice($roomId, $date, $dateTo, $propertyId);

            if ($result['final_price'] !== $result['base_price'] && $result['adjustment_pct'] !== 0) {
                addPricingSuggestion([
                    'property_id'    => $propertyId,
                    'room_id'        => $roomId,
                    'date_from'      => $date,
                    'date_to'        => $dateTo,
                    'current_price'  => $basePrice,
                    'suggested_price'=> $result['final_price'],
                    'suggestion_pct' => $result['adjustment_pct'],
                    'reason'         => implode(', ', array_column($result['rules_applied'], 'rule')),
                    'demand_level'   => $result['adjustment_pct'] >= 25 ? 'high' : ($result['adjustment_pct'] >= 10 ? 'medium' : 'low'),
                ]);
            }
            $ts += 86400;
        }
    }

    setSetting('last_suggestions_generated', date('Y-m-d'), $propertyId);
}
