<?php
/**
 * Booking Confirmation Endpoint
 *
 * Called from the frontend (script.js) after a successful Razorpay payment.
 * Saves the booking to the channel manager database and sends a WhatsApp notification.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

require_once __DIR__ . '/channel-manager/config.php';
require_once __DIR__ . '/channel-manager/db.php';
require_once __DIR__ . '/channel-manager/whatsapp.php';

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input) { http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit; }

// Required fields
$requiredFields = ['paymentId', 'orderId', 'roomId', 'roomName', 'checkIn', 'checkOut', 'guestName', 'amount'];
foreach ($requiredFields as $f) {
    if (empty($input[$f])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: {$f}"]);
        exit;
    }
}

$booking = [
    'room_id'     => $input['roomId'],
    'room_name'   => $input['roomName'],
    'check_in'    => $input['checkIn'],
    'check_out'   => $input['checkOut'],
    'guest_name'  => $input['guestName'],
    'guest_email' => $input['guestEmail'] ?? '',
    'guest_phone' => $input['guestPhone'] ?? '',
    'source'      => 'direct',
    'booking_ref' => $input['paymentId'],  // Razorpay payment ID as reference
    'amount'      => (float)$input['amount'],
    'status'      => 'confirmed',
    'uid'         => 'rzp-' . $input['paymentId'] . '@kanchifarmstay.com',
    'notes'       => 'Razorpay Order: ' . $input['orderId'],
];

$id = addBooking($booking);

if ($id === false) {
    // Booking with this payment ID might already exist (duplicate call), that's OK
    echo json_encode(['success' => true, 'message' => 'Booking already recorded']);
    exit;
}

// Send WhatsApp notification
sendWhatsAppNotification(buildBookingMessage(array_merge($booking, ['id' => $id])));

// Also send email notification
$to      = 'ops@kanchifarmstay.com';
$subject = "✅ Payment Confirmed: {$booking['guest_name']} — {$booking['room_name']}";
$nights  = max(1, (int)ceil((strtotime($booking['check_out']) - strtotime($booking['check_in'])) / 86400));
$body    = "A payment has been confirmed via Razorpay.\n\n";
$body   .= "Guest:      {$booking['guest_name']}\n";
$body   .= "Phone:      {$booking['guest_phone']}\n";
$body   .= "Email:      {$booking['guest_email']}\n";
$body   .= "Room:       {$booking['room_name']}\n";
$body   .= "Check-in:   {$booking['check_in']}\n";
$body   .= "Check-out:  {$booking['check_out']} ({$nights} night" . ($nights !== 1 ? 's' : '') . ")\n";
$body   .= "Amount:     ₹" . number_format($booking['amount']) . "\n";
$body   .= "Payment ID: {$booking['booking_ref']}\n";
$body   .= "Order ID:   {$input['orderId']}\n";
$headers = "From: noreply@kanchifarmstay.com\r\nReply-To: {$booking['guest_email']}\r\n";
@mail($to, $subject, $body, $headers);

echo json_encode(['success' => true, 'bookingId' => $id]);
