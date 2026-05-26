<?php
/**
 * Kanchi Farm Stay — Cron Job
 * Run every 30 minutes via Hostinger cPanel:
 *   php /path/to/channel-manager/cron.php
 *
 * Or trigger via URL (with secret token):
 *   https://kanchifarmstay.com/channel-manager/cron.php?token=kanchi-cron-2025
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/sync.php';
require_once __DIR__ . '/wa-queue.php';
require_once __DIR__ . '/pricing-engine.php';
require_once __DIR__ . '/revenue-functions.php';

$isCli = php_sapi_name() === 'cli';
$isWeb = !$isCli;

// Web access requires secret token
if ($isWeb) {
    $token = $_GET['token'] ?? '';
    if ($token !== CRON_SECRET) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    header('Content-Type: application/json');
}

$log = [];

// ── 1. Sync iCal calendars from OTAs ─────────────────────────────
$calendars = getExternalCalendars(0); // 0 = all properties
$syncResults = [];
foreach ($calendars as $cal) {
    $res = syncOneCalendar($cal);
    updateCalendarSyncStatus($cal['id'], $res['success'], $res['imported'], $res['error'] ?? '');
    if ($res['success'] && !empty($res['new_bookings'])) {
        foreach ($res['new_bookings'] as $b) {
            sendWhatsAppNotification(buildBookingMessage($b));
        }
    }
    $syncResults[] = ['platform' => $cal['platform'], 'room_id' => $cal['room_id'], 'success' => $res['success'], 'imported' => $res['imported']];
}
$log['sync'] = $syncResults;

// ── 2. Process WhatsApp message queue ─────────────────────────────
$waResult = processWaQueue(0);
$log['whatsapp_queue'] = $waResult;

// ── 3. Check for abandoned bookings ───────────────────────────────
scheduleAbandonmentCheck();
$log['abandonment_check'] = 'done';

// ── 4. Generate AI pricing suggestions (once daily at 02:xx) ─────
if ((int)date('H') === 2) {
    generatePricingSuggestions(1);
    $log['pricing_suggestions'] = 'generated';
}

// ── 5. Take revenue snapshot (once daily at midnight 00:xx) ──────
if ((int)date('H') === 0) {
    takeRevenueSnapshot(1);
    $log['revenue_snapshot'] = 'taken';
}

$log['timestamp'] = date('Y-m-d H:i:s');

if ($isCli) {
    echo "=== Kanchi Farm Stay Cron: " . $log['timestamp'] . " ===\n";
    echo "iCal Sync: " . count($syncResults) . " calendars processed\n";
    echo "WA Queue: sent={$waResult['sent']}, failed={$waResult['failed']}\n";
    echo "Done.\n";
} else {
    echo json_encode(['success' => true, 'log' => $log]);
}
