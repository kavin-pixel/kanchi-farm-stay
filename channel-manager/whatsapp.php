<?php
require_once __DIR__ . '/config.php';

function sendWhatsAppNotification(string $message): void {
    if (WHATSAPP_PROVIDER === 'none') return;
    if (WHATSAPP_PROVIDER === 'callmebot') {
        _sendViaCallMeBot($message);
    }
}

function _sendViaCallMeBot(string $message): void {
    $url = 'https://api.callmebot.com/whatsapp.php?' . http_build_query([
        'phone'  => WHATSAPP_PHONE,
        'text'   => $message,
        'apikey' => CALLMEBOT_API_KEY,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'KanchiFarmStay-ChannelManager/1.0',
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function buildBookingMessage(array $b): string {
    $source = strtoupper($b['source'] ?? 'DIRECT');
    $nights = (int)ceil((strtotime($b['check_out']) - strtotime($b['check_in'])) / 86400);
    $msg    = "New Booking - Kanchi Farm Stay\n\n";
    $msg   .= "Room: {$b['room_name']}\n";
    $msg   .= "Check-in:  {$b['check_in']}\n";
    $msg   .= "Check-out: {$b['check_out']} ({$nights} night" . ($nights !== 1 ? 's' : '') . ")\n";
    $msg   .= "Guest: " . ($b['guest_name'] ?: 'Guest') . "\n";
    if (!empty($b['guest_phone'])) $msg .= "Phone: {$b['guest_phone']}\n";
    if (!empty($b['guest_email'])) $msg .= "Email: {$b['guest_email']}\n";
    if (!empty($b['amount']) && $b['amount'] > 0) {
        $msg .= "Amount: Rs." . number_format($b['amount']) . "\n";
    }
    $msg .= "Source: {$source}\n";
    if (!empty($b['booking_ref'])) $msg .= "Ref: {$b['booking_ref']}\n";
    return $msg;
}
