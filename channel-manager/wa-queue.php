<?php
/**
 * WhatsApp Workflow Queue Processor
 * Called by cron.php every 30 minutes.
 * Also called directly by confirm_booking.php to schedule messages.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/whatsapp.php';

// ── Process pending messages in the queue ─────────────────────────
function processWaQueue(int $propertyId = 0): array {
    $pending = getPendingWaMessages($propertyId);
    $sent = 0; $failed = 0;

    foreach ($pending as $msg) {
        $ok = sendWhatsAppToGuest($msg['phone'], $msg['message']);
        if ($ok) {
            markWaMessageSent($msg['id']);
            $sent++;
        } else {
            markWaMessageFailed($msg['id'], 'Send failed');
            $failed++;
        }
        // Rate-limit: don't hammer the API
        if (count($pending) > 1) usleep(500000); // 0.5s between messages
    }

    return ['processed' => count($pending), 'sent' => $sent, 'failed' => $failed];
}

// ── Schedule all workflow messages for a new booking ──────────────
function scheduleWorkflowMessages(int $bookingId): void {
    $booking = getBookingById($bookingId);
    if (!$booking) return;

    $phone      = $booking['whatsapp_number'] ?: $booking['guest_phone'];
    $propId     = (int)($booking['property_id'] ?? 1);
    $checkIn    = $booking['check_in'];
    $checkOut   = $booking['check_out'];

    $triggers = [
        'booking_confirmed' => date('Y-m-d H:i:s'),   // immediate
        'pre_stay_t3'       => _scheduleAt($checkIn, -3, '09:00:00'),
        'pre_stay_t1'       => _scheduleAt($checkIn, -1, '09:00:00'),
        'post_checkout_t1'  => _scheduleAt($checkOut,  1, '10:00:00'),
    ];

    foreach ($triggers as $triggerType => $scheduledAt) {
        // Don't schedule pre-stay if the date is already in the past
        if ($scheduledAt < date('Y-m-d H:i:s') && $triggerType !== 'booking_confirmed') continue;

        $tmpl = getWaTemplate($triggerType, $propId);
        if (!$tmpl || !$tmpl['is_active']) continue;

        $message = buildTemplateMessage($tmpl['message_body'], $booking, $propId);
        enqueueWaMessage($bookingId, $phone, $message, $triggerType, $scheduledAt, $propId);
    }
}

// ── Check for abandoned bookings (order created, no confirmation in 2hr) ──
function scheduleAbandonmentCheck(): void {
    $abandoned = getPendingAbandonments(2);
    foreach ($abandoned as $attempt) {
        if (!$attempt['guest_phone']) { markAbandonmentRecoverySent($attempt['id']); continue; }

        $propId = (int)($attempt['property_id'] ?? 1);
        $tmpl   = getWaTemplate('abandonment', $propId);
        if (!$tmpl || !$tmpl['is_active']) { markAbandonmentRecoverySent($attempt['id']); continue; }

        $message = buildTemplateMessage($tmpl['message_body'], [
            'guest_name'   => $attempt['guest_name'] ?: 'there',
            'room_name'    => $attempt['room_name'],
            'room_id'      => $attempt['room_id'],
            'check_in'     => $attempt['check_in'],
            'check_out'    => $attempt['check_out'],
            'amount'       => $attempt['amount'],
            'guest_phone'  => $attempt['guest_phone'],
        ], $propId);

        enqueueWaMessage(0, $attempt['guest_phone'], $message, 'abandonment', date('Y-m-d H:i:s'), $propId);
        markAbandonmentRecoverySent($attempt['id']);
    }
}

// ── Build a message from a template ──────────────────────────────
function buildTemplateMessage(string $template, array $data, int $propertyId = 1): string {
    $googleUrl  = getSetting('google_review_url',  $propertyId, 'https://g.page/r/');
    $airbnbUrl  = getSetting('airbnb_review_url',  $propertyId, 'https://www.airbnb.co.in');
    $bookingUrl = getSetting('booking_review_url', $propertyId, 'https://www.booking.com');

    $checkIn  = $data['check_in']  ?? '';
    $checkOut = $data['check_out'] ?? '';

    // Build personalised review request link
    $bookingId  = $data['id'] ?? 0;
    $bookingRef = $data['booking_ref'] ?? '';
    $reviewToken = $bookingId ? hash('sha256', $bookingId . $bookingRef . CRON_SECRET) : '';
    $reviewLink  = $bookingId ? SITE_URL . "/channel-manager/review-request.php?bid={$bookingId}&token={$reviewToken}" : $googleUrl;

    $vars = [
        '{{guest_name}}'        => $data['guest_name']   ?? 'Guest',
        '{{room_name}}'         => $data['room_name']    ?? '',
        '{{room_id}}'           => $data['room_id']      ?? '',
        '{{check_in}}'          => $checkIn  ? date('d M Y', strtotime($checkIn))  : '',
        '{{check_out}}'         => $checkOut ? date('d M Y', strtotime($checkOut)) : '',
        '{{amount}}'            => isset($data['amount']) ? number_format((float)$data['amount']) : '0',
        '{{booking_ref}}'       => $bookingRef,
        '{{google_review_url}}' => $googleUrl,
        '{{airbnb_review_url}}' => $airbnbUrl,
        '{{booking_review_url}}'=> $bookingUrl,
        '{{review_link}}'       => $reviewLink,
        '{{site_url}}'          => SITE_URL,
    ];

    return str_replace(array_keys($vars), array_values($vars), $template);
}

// ── Helper: compute a datetime string relative to a date ─────────
function _scheduleAt(string $baseDate, int $dayOffset, string $time): string {
    $ts = strtotime("{$baseDate} {$time}") + ($dayOffset * 86400);
    return date('Y-m-d H:i:s', $ts);
}

// ── CLI / direct call entry point ─────────────────────────────────
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Processing WhatsApp queue...\n";
    $r = processWaQueue();
    echo "Sent: {$r['sent']}, Failed: {$r['failed']}, Total: {$r['processed']}\n";
    scheduleAbandonmentCheck();
    echo "Abandonment check done.\n";
}
