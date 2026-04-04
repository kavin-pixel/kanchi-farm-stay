<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Essential if frontend and backend domain differ, though they should be same on hostinger
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Razorpay Live API Credentials
$keyId = 'rzp_live_SImDeaehZI93nG';
$keySecret = '2HSD20TrjbZSB2PI4l2L6zYk';

// Read incoming JSON payload
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!isset($input['amount'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Amount is required']);
    exit;
}

$amount = $input['amount']; // Amount should come in paise

$guestName = $input['guestName'] ?? 'Not provided';
$guestEmail = $input['guestEmail'] ?? 'Not provided';
$guestPhone = $input['guestPhone'] ?? 'Not provided';
$checkin = $input['checkin'] ?? 'Not provided';
$checkout = $input['checkout'] ?? 'Not provided';
$roomName = $input['roomName'] ?? 'Not provided';
$days = $input['days'] ?? 'Not provided';

// Send Email Notification
$to = "ops@kanchifarmstay.com";
$subject = "New Booking Started: " . $guestName . " (" . $roomName . ")";
$emailMessage = "A new booking process has been initiated (Order created via Razorpay).\n\n";
$emailMessage .= "Guest Name: " . $guestName . "\n";
$emailMessage .= "Email: " . $guestEmail . "\n";
$emailMessage .= "Phone: " . $guestPhone . "\n";
$emailMessage .= "Room: " . $roomName . "\n";
$emailMessage .= "Check-in: " . $checkin . "\n";
$emailMessage .= "Check-out: " . $checkout . "\n";
$emailMessage .= "Duration: " . $days . " night(s)\n";
$emailMessage .= "Amount: ₹" . ($amount / 100) . "\n\n";
$emailMessage .= "Note: This email means the user clicked 'Pay with Razorpay' and an order was generated. Check your Razorpay dashboard for the final successful payment status.";

$headers = "From: noreply@kanchifarmstay.com\r\n" .
    "Reply-To: " . $guestEmail . "\r\n" .
    "X-Mailer: PHP/" . phpversion();

// Suppress errors with @ in case mail() isn't fully configured, to prevent breaking JSON response
@mail($to, $subject, $emailMessage, $headers);

// Razorpay Order creation API payload
$postData = [
    'amount' => $amount,
    'currency' => 'INR',
    'payment_capture' => 1 // Auto capture
];

// Initialize cURL securely
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/orders");
curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Return response to frontend
http_response_code($httpCode);
echo $response;
?>