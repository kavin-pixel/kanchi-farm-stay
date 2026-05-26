<?php
/**
 * Booking Confirmation Endpoint
 * Called from script.js after a successful Razorpay payment.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); exit; }

require_once __DIR__ . '/channel-manager/config.php';
require_once __DIR__ . '/channel-manager/db.php';
require_once __DIR__ . '/channel-manager/whatsapp.php';
require_once __DIR__ . '/channel-manager/wa-queue.php';

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input) { http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit; }

// Required fields
foreach (['paymentId','orderId','roomId','roomName','checkIn','checkOut','guestName','amount'] as $f) {
    if (empty($input[$f])) { http_response_code(400); echo json_encode(['error' => "Missing: {$f}"]); exit; }
}

$booking = [
    'property_id'    => 1,
    'room_id'        => $input['roomId'],
    'room_name'      => $input['roomName'],
    'check_in'       => $input['checkIn'],
    'check_out'      => $input['checkOut'],
    'guest_name'     => $input['guestName'],
    'guest_email'    => $input['guestEmail']    ?? '',
    'guest_phone'    => $input['guestPhone']    ?? '',
    'whatsapp_number'=> $input['guestPhone']    ?? '',
    'source'         => $input['source']        ?? 'direct',
    'booking_ref'    => $input['paymentId'],
    'amount'         => (float)$input['amount'],
    'amount_paid'    => (float)$input['amount'],
    'payment_method' => 'razorpay',
    'payment_status' => 'paid',
    'status'         => 'confirmed',
    'uid'            => 'rzp-' . $input['paymentId'] . '@kanchifarmstay.com',
    'notes'          => 'Razorpay Order: ' . $input['orderId'],
];

$id = addBooking($booking);

if ($id === false) {
    echo json_encode(['success' => true, 'message' => 'Already recorded']);
    exit;
}

// Mark the booking attempt as converted (stops abandonment recovery)
markAttemptConverted($input['orderId'] ?? '');

// Schedule WhatsApp workflow messages (confirmation + pre-stay + post-checkout)
scheduleWorkflowMessages($id);

// Immediate staff WhatsApp notification
sendWhatsAppNotification(buildBookingMessage(array_merge($booking, ['id' => $id])));

// Email to ops
$nights  = max(1, (int)ceil((strtotime($booking['check_out']) - strtotime($booking['check_in'])) / 86400));
$subject = "✅ Confirmed: {$booking['guest_name']} — {$booking['room_name']}";
$body    = "Payment confirmed via Razorpay.\n\n"
         . "Guest:      {$booking['guest_name']}\n"
         . "Phone:      {$booking['guest_phone']}\n"
         . "Email:      {$booking['guest_email']}\n"
         . "Room:       {$booking['room_name']}\n"
         . "Check-in:   {$booking['check_in']}\n"
         . "Check-out:  {$booking['check_out']} ({$nights} night" . ($nights !== 1 ? 's' : '') . ")\n"
         . "Amount:     ₹" . number_format($booking['amount']) . "\n"
         . "Payment ID: {$booking['booking_ref']}\n"
         . "Order ID:   {$input['orderId']}\n";
@mail('ops@kanchifarmstay.com', $subject, $body, "From: noreply@kanchifarmstay.com\r\n");

// Guest confirmation email
if ($booking['guest_email']) {
    $gSubject = "Your Kanchi Farm Stay booking is confirmed! 🏡";
    $gBody    = "Hi {$booking['guest_name']},\n\nYour booking is confirmed.\n\n"
              . "Room:       {$booking['room_name']}\n"
              . "Check-in:   {$booking['check_in']} (2:00 PM onwards)\n"
              . "Check-out:  {$booking['check_out']} (by 11:00 AM)\n"
              . "Amount Paid: ₹" . number_format($booking['amount']) . "\n"
              . "Booking Ref: {$booking['booking_ref']}\n\n"
              . "Address: 704, Sastha Nagar, Kovil Street, Chithathur\n"
              . "Contact: +91 6383726094\n"
              . "Website: https://kanchifarmstay.com\n\n"
              . "We look forward to welcoming you!\n— Team Kanchi Farm Stay";
    @mail($booking['guest_email'], $gSubject, $gBody, "From: ops@kanchifarmstay.com\r\n");
}

echo json_encode(['success' => true, 'bookingId' => $id]);
