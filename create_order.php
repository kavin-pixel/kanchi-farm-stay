<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/channel-manager/config.php';
require_once __DIR__ . '/channel-manager/db.php';

$inputJSON = file_get_contents('php://input');
$input     = json_decode($inputJSON, true);

if (!isset($input['amount'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Amount is required']);
    exit;
}

$amount    = (int)$input['amount'];   // Amount in paise
$guestName = $input['guestName']  ?? '';
$guestEmail= $input['guestEmail'] ?? '';
$guestPhone= $input['guestPhone'] ?? '';
$checkin   = $input['checkin']    ?? '';
$checkout  = $input['checkout']   ?? '';
$roomId    = $input['roomId']     ?? '';
$roomName  = $input['roomName']   ?? '';
$days      = $input['days']       ?? 1;

// Create Razorpay order
$postData = ['amount' => $amount, 'currency' => 'INR', 'payment_capture' => 1];
$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_POSTFIELDS     => json_encode($postData),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Track booking attempt for abandonment recovery
if ($httpCode >= 200 && $httpCode < 300) {
    $orderData = json_decode($response, true);
    if (!empty($orderData['id'])) {
        insertBookingAttempt([
            'property_id'      => 1,
            'room_id'          => $roomId,
            'room_name'        => $roomName,
            'check_in'         => $checkin,
            'check_out'        => $checkout,
            'guest_name'       => $guestName,
            'guest_email'      => $guestEmail,
            'guest_phone'      => $guestPhone,
            'razorpay_order_id'=> $orderData['id'],
            'amount'           => $amount / 100,
        ]);
    }
}

// Staff email notification
$emailMessage = "A new booking was initiated (Razorpay order created).\n\n"
    . "Guest: {$guestName}\nEmail: {$guestEmail}\nPhone: {$guestPhone}\n"
    . "Room: {$roomName}\nCheck-in: {$checkin}\nCheck-out: {$checkout}\n"
    . "Duration: {$days} night(s)\nAmount: ₹" . ($amount / 100) . "\n\n"
    . "Note: Payment not yet confirmed. Check Razorpay dashboard.";
@mail('ops@kanchifarmstay.com', "Booking Started: {$guestName} ({$roomName})", $emailMessage, "From: noreply@kanchifarmstay.com\r\n");

http_response_code($httpCode);
echo $response;
