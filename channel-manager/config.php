<?php
// ============================================================
// KANCHI FARM STAY — CHANNEL MANAGER CONFIGURATION
// Edit the values below before going live.
// ============================================================

// Admin password to access /channel-manager/admin.php
define('ADMIN_PASSWORD', 'KanchiFarm2025!');

// Path to the SQLite database (auto-created on first run)
define('DB_PATH', __DIR__ . '/calendar.db');

// Your website URL — no trailing slash
define('SITE_URL', 'https://kanchifarmstay.com');

// Secret token appended to iCal export URLs.
// Change this to any random string — it prevents random people
// from easily discovering your calendar feed URLs.
define('ICAL_TOKEN', 'ksf-ical-secret-2025');

// ============================================================
// WHATSAPP NOTIFICATIONS  (powered by CallMeBot — free)
// ============================================================
// How to get your CallMeBot API key:
//   1. Save this number in your contacts: +34 644 69 07 99  (name it "CallMeBot")
//   2. Open WhatsApp and send that contact: I allow callmebot to send me messages
//   3. Within seconds you'll receive a WhatsApp reply with your API key.
//
// Set WHATSAPP_PROVIDER to 'none' to disable notifications entirely.
//
define('WHATSAPP_PROVIDER', 'callmebot');         // 'callmebot' | 'none'
define('WHATSAPP_PHONE',    '91XXXXXXXXXX');       // country code + number, no + sign
define('CALLMEBOT_API_KEY', 'YOUR_API_KEY_HERE'); // from step 3 above

// ============================================================
// ROOMS — must match the IDs in script.js
// ============================================================
define('ROOM_IDS', [
    'wooden-villa'     => 'Wooden Villa',
    'white-villa'      => 'White Villa',
    'natures-nest'     => "Nature's Nest",
    'tranquil-retreat' => 'Tranquil Retreat',
    'wooden-cottage'   => 'Wooden Cottage',
]);
