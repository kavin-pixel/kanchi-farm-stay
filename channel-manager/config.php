<?php
// ============================================================
// KANCHI FARM STAY — CHANNEL MANAGER CONFIGURATION
// ============================================================

// Admin password to access /channel-manager/admin.php
define('ADMIN_PASSWORD', 'KanchiFarm2025!');

// Path to the SQLite database (auto-created on first run)
define('DB_PATH', __DIR__ . '/calendar.db');

// Your website URL — no trailing slash
define('SITE_URL', 'https://kanchifarmstay.com');

// Secret token for iCal export URLs
define('ICAL_TOKEN', 'ksf-ical-secret-2025');

// Secret token for cron/sync triggers
define('CRON_SECRET', 'kanchi-cron-2025');

// Secret token for Google Hotel Ads price feed
define('PRICE_FEED_TOKEN', 'ksf-pricefeed-2025');

// ============================================================
// RAZORPAY PAYMENT GATEWAY
// ============================================================
define('RAZORPAY_KEY_ID',     'rzp_live_SImDeaehZI93nG');
define('RAZORPAY_KEY_SECRET', '');  // ← paste your Razorpay secret here

// ============================================================
// WHATSAPP NOTIFICATIONS
// ============================================================
// Provider: 'callmebot' | 'meta' | 'none'
define('WHATSAPP_PROVIDER', 'callmebot');
define('WHATSAPP_PHONE',    '91XXXXXXXXXX');   // Admin phone (country code + number, no +)
define('CALLMEBOT_API_KEY', 'YOUR_API_KEY_HERE');

// Meta WhatsApp Business API (optional — leave blank to use CallMeBot)
define('META_WA_TOKEN',        '');
define('META_WA_PHONE_ID',     '');
define('META_WA_VERIFY_TOKEN', 'kfs-webhook-2025');

// ============================================================
// OTA COMMISSION RATES (percentage deducted from gross revenue)
// ============================================================
define('OTA_COMMISSIONS', [
    'airbnb'            => 3,    // Airbnb host-side fee
    'booking.com'       => 15,
    'booking'           => 15,
    'agoda'             => 15,
    'makemytrip'        => 10,
    'goibibo'           => 10,
    'yatra'             => 10,
    'expedia'           => 15,
    'hotels.com'        => 15,
    'tripadvisor'       => 0,
    'direct'            => 0,
    'razorpay'          => 2,
    'manual'            => 0,
    'blocked'           => 0,
    'google_hotel_ads'  => 0,
]);

// ============================================================
// ROOMS — must match the IDs in script.js
// ============================================================
define('ROOM_IDS', [
    'wooden-villa'           => 'Wooden Villa',
    'white-villa'            => 'White Villa — Room 1',
    'white-villa-room-2'     => 'White Villa — Room 2',
    'white-villa-full-floor' => 'White Villa — Full 1st Floor',
    'natures-nest'           => "Nature's Nest",
    'tranquil-retreat'       => 'Tranquil Retreat',
    'wooden-cottage'         => 'Wooden Cottage',
    'kanchi-farm-stay'       => 'KanchiFarmStay (Group Booking)',
    'tent'                   => 'Tent',
    'tree-house'             => 'Tree House',
]);

// Default base prices (used as fallback if not set in room_rates table)
define('ROOM_BASE_PRICES', [
    'wooden-villa'           => 3000,
    'white-villa'            => 2000,
    'white-villa-room-2'     => 1000,
    'white-villa-full-floor' => 2500,
    'natures-nest'           => 2500,
    'tranquil-retreat'       => 2500,
    'wooden-cottage'         => 3000,
    'kanchi-farm-stay'       => 8000,
    'tent'                   => 500,
    'tree-house'             => 2000,
]);

// ============================================================
// BANK / PAYMENT DETAILS (shown on booking confirmation)
// ============================================================
define('BANK_NAME',         'Kotak Mahindra Bank');
define('BANK_ACCOUNT_NAME', 'Kanchi Farm Stay');
define('BANK_ACCOUNT_NO',   '000000123456');
define('BANK_IFSC',         'KKBK00001234');
define('UPI_ID',            '6383726094@Kanchi');
