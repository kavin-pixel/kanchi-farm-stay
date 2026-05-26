<?php
/**
 * Public Review Request Landing Page
 *
 * URL: /channel-manager/review-request.php?bid=123&token=HASH
 *
 * Token = sha256(booking_id . booking_ref . CRON_SECRET)
 * Marks the booking as review_requested=1 on first visit, then shows
 * three buttons: Google, Airbnb, Booking.com
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$bookingId = (int)($_GET['bid'] ?? 0);
$token     = trim($_GET['token'] ?? '');

$booking = null;
$error   = '';

if (!$bookingId || !$token) {
    $error = 'Invalid review link.';
} else {
    // Fetch the booking
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $error = 'Booking not found.';
    } else {
        // Verify token
        $expected = hash('sha256', $bookingId . ($booking['booking_ref'] ?? '') . CRON_SECRET);
        if (!hash_equals($expected, $token)) {
            $error = 'Invalid or expired review link.';
            $booking = null;
        } else {
            // Mark as review requested (idempotent)
            $db->prepare('UPDATE bookings SET review_requested = 1 WHERE id = ?')->execute([$bookingId]);
        }
    }
}

// Get review URLs from settings
$propertyId    = $booking['property_id'] ?? 1;
$googleUrl     = getSetting('google_review_url',  $propertyId) ?: 'https://search.google.com/local/writereview';
$airbnbUrl     = getSetting('airbnb_review_url',  $propertyId) ?: 'https://www.airbnb.co.in';
$bookingUrl    = getSetting('booking_review_url', $propertyId) ?: 'https://www.booking.com';

$guestName = $booking ? htmlspecialchars($booking['guest_name'] ?? 'Valued Guest') : 'Guest';
$roomName  = $booking ? htmlspecialchars($booking['room_name']  ?? 'your room')     : 'your room';
$checkOut  = $booking ? $booking['check_out'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Leave a Review | Kanchi Farm Stay</title>
  <meta name="robots" content="noindex,nofollow">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 50%, #e0f7fa 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }
    .card {
      background: #fff;
      border-radius: 20px;
      padding: 2.5rem 2rem;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 12px 48px rgba(0,0,0,.12);
      text-align: center;
    }
    .logo {
      font-size: 2.5rem;
      margin-bottom: 1rem;
    }
    h1 {
      font-size: 1.5rem;
      color: #2e7d32;
      margin-bottom: .5rem;
    }
    .subtitle {
      color: #546e7a;
      font-size: .92rem;
      margin-bottom: 2rem;
      line-height: 1.55;
    }
    .stars {
      font-size: 2rem;
      color: #f59e0b;
      margin-bottom: 1.5rem;
      letter-spacing: 3px;
    }
    .review-buttons {
      display: flex;
      flex-direction: column;
      gap: .85rem;
    }
    .review-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .75rem;
      padding: .95rem 1.5rem;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 600;
      text-decoration: none;
      color: #fff;
      transition: transform .15s, box-shadow .15s;
    }
    .review-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,.2);
    }
    .review-btn .badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: rgba(255,255,255,.25);
      font-size: .75rem;
      font-weight: 800;
      flex-shrink: 0;
    }
    .btn-google   { background: #4285F4; }
    .btn-airbnb   { background: #FF5A5F; }
    .btn-booking  { background: #003580; }
    .divider { margin: 1.5rem 0; border: none; border-top: 1px solid #e0e0e0; }
    .footer-note {
      font-size: .77rem;
      color: #90a4ae;
      margin-top: 1.5rem;
      line-height: 1.5;
    }
    .error-box {
      background: #fff3e0;
      border: 1.5px solid #ffb74d;
      border-radius: 12px;
      padding: 1.5rem;
      color: #e65100;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">🌿</div>

    <?php if ($error): ?>
      <h1>Review Link Issue</h1>
      <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <p class="footer-note" style="margin-top:1rem;">
        If you'd like to leave a review, please visit us on Google or Airbnb directly.<br>
        Questions? Call <a href="tel:+916383726094">+91 63837 26094</a>
      </p>

    <?php else: ?>
      <h1>Thank You, <?= $guestName ?>!</h1>
      <div class="subtitle">
        We hope you enjoyed your stay in <?= $roomName ?>.<?= $checkOut ? ' It was a pleasure hosting you on ' . date('d M Y', strtotime($checkOut)) . '.' : '' ?><br><br>
        Your feedback means a lot to us and helps other travellers discover Kanchi Farm Stay. It only takes a minute!
      </div>

      <div class="stars">★★★★★</div>

      <div class="review-buttons">
        <a href="<?= htmlspecialchars($googleUrl) ?>" class="review-btn btn-google" target="_blank" rel="noopener">
          <span class="badge">G</span>
          Leave a Google Review
        </a>
        <a href="<?= htmlspecialchars($airbnbUrl) ?>" class="review-btn btn-airbnb" target="_blank" rel="noopener">
          <span class="badge">AB</span>
          Review on Airbnb
        </a>
        <a href="<?= htmlspecialchars($bookingUrl) ?>" class="review-btn btn-booking" target="_blank" rel="noopener">
          <span class="badge">B.</span>
          Review on Booking.com
        </a>
      </div>

      <hr class="divider">

      <p class="footer-note">
        Can't find your booking? Call us on <a href="tel:+916383726094" style="color:#2e7d32;">+91 63837 26094</a><br>
        or email <a href="mailto:ops@kanchifarmstay.com" style="color:#2e7d32;">ops@kanchifarmstay.com</a>
      </p>
    <?php endif; ?>
  </div>
</body>
</html>
