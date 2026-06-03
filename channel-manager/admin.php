<?php
/**
 * Kanchi Farm Stay — Channel Manager
 * Professional property management dashboard
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/admin-shell.php';

if (!session_id()) session_start();

// ── Auth ────────────────────────────────────────────────────────────
$loginError = '';
if (($_POST['action'] ?? '') === 'login') {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['current_property_id'] = 1;
        header('Location: admin.php'); exit;
    }
    $loginError = 'Incorrect password.';
}
if (($_GET['action'] ?? '') === 'logout') {
    session_destroy(); header('Location: admin.php'); exit;
}

// ── POST handlers ────────────────────────────────────────────────────
if (!empty($_SESSION['admin_logged_in'])) {
    $act = $_POST['action'] ?? '';
    $propertyId = getCurrentPropertyId();

    if ($act === 'add_booking') {
        $rid  = $_POST['room_id'];
        $data = [
            'property_id' => $propertyId,
            'room_id'     => $rid,
            'room_name'   => ROOM_IDS[$rid] ?? $rid,
            'check_in'    => $_POST['check_in'],
            'check_out'   => $_POST['check_out'],
            'guest_name'  => trim($_POST['guest_name'] ?: 'Blocked'),
            'guest_email' => trim($_POST['guest_email'] ?? ''),
            'guest_phone' => trim($_POST['guest_phone'] ?? ''),
            'source'      => $_POST['source'] ?? 'manual',
            'amount'      => (float)($_POST['amount'] ?? 0),
            'notes'       => trim($_POST['notes'] ?? ''),
            'status'      => 'confirmed',
        ];
        $id = addBooking($data);
        if ($id && function_exists('buildBookingMessage')) {
            sendWhatsAppNotification(buildBookingMessage(array_merge($data, ['id' => $id])));
        }
        header('Location: admin.php?section=bookings&flash=Booking+added'); exit;
    }
    if ($act === 'delete_booking')  { deleteBooking((int)$_POST['id']);  header('Location: admin.php?section=bookings&flash=Deleted');   exit; }
    if ($act === 'cancel_booking')  { cancelBooking((int)$_POST['id']);  header('Location: admin.php?section=bookings&flash=Cancelled'); exit; }
    if ($act === 'update_booking') {
        $bid = (int)($_POST['id'] ?? 0);
        $rid = $_POST['room_id'] ?? '';
        $data = [
            'room_id'     => $rid,
            'room_name'   => ROOM_IDS[$rid] ?? $rid,
            'check_in'    => $_POST['check_in'],
            'check_out'   => $_POST['check_out'],
            'guest_name'  => trim($_POST['guest_name'] ?: 'Blocked'),
            'guest_email' => trim($_POST['guest_email'] ?? ''),
            'guest_phone' => trim($_POST['guest_phone'] ?? ''),
            'source'      => $_POST['source'] ?? 'manual',
            'amount'      => (float)($_POST['amount'] ?? 0),
            'notes'       => trim($_POST['notes'] ?? ''),
        ];
        if ($bid) { updateBooking($bid, $data); }
        $back = $_POST['return_to'] ?? 'bookings';
        header('Location: admin.php?section=' . urlencode($back) . '&flash=Booking+updated'); exit;
    }
    if ($act === 'add_calendar') {
        addExternalCalendar($_POST['room_id'], strtolower(trim($_POST['platform'])), trim($_POST['ical_url']), $propertyId, (float)($_POST['commission_pct'] ?? 0));
        header('Location: admin.php?section=channels&flash=Calendar+added'); exit;
    }
    if ($act === 'delete_calendar') { deleteExternalCalendar((int)$_POST['id']); header('Location: admin.php?section=channels&flash=Removed'); exit; }
    if ($act === 'issue_refund') {
        $paymentId = trim($_POST['payment_id'] ?? '');
        $amount    = (float)($_POST['refund_amount'] ?? 0);
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        if ($paymentId && $amount > 0 && defined('RAZORPAY_KEY_ID') && RAZORPAY_KEY_SECRET) {
            $ch = curl_init("https://api.razorpay.com/v1/payments/{$paymentId}/refund");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
                CURLOPT_POSTFIELDS     => json_encode(['amount' => (int)($amount * 100), 'notes' => ['reason' => 'Cancellation by admin']]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $r = json_decode($resp, true);
            if (!empty($r['id'])) {
                if ($bookingId) {
                    updateBookingField($bookingId, 'status', 'cancelled');
                    updateBookingField($bookingId, 'notes', 'Refunded ₹'.number_format($amount).' on '.date('d M Y').' (Refund ID: '.$r['id'].')');
                }
                header('Location: admin.php?section=bookings&flash=Refund+issued+successfully'); exit;
            }
        }
        header('Location: admin.php?section=bookings&flash=Refund+failed+-+check+Razorpay+credentials'); exit;
    }
}

// ── Data ─────────────────────────────────────────────────────────────
$section    = $_GET['section'] ?? 'dashboard';
$flash      = htmlspecialchars($_GET['flash'] ?? '');
$rooms      = ROOM_IDS;
$propertyId = getCurrentPropertyId();

if (!empty($_SESSION['admin_logged_in'])) {
    $allBookings = getAllBookings([], $propertyId);
    $extCals     = getExternalCalendars($propertyId);

    $confirmed   = array_filter($allBookings, fn($b) => $b['status'] === 'confirmed');
    $upcoming    = array_filter($confirmed,   fn($b) => $b['check_out'] >= date('Y-m-d'));
    $thisMonth   = array_filter($confirmed,   fn($b) => substr($b['check_in'],0,7) === date('Y-m'));

    $totalRev    = array_sum(array_column(array_filter($thisMonth, fn($b) => $b['source']==='direct' || $b['source']==='razorpay'), 'amount'));

    $totalNights = 0;
    foreach ($confirmed as $b) {
        $n = (int)ceil((strtotime($b['check_out']) - strtotime($b['check_in'])) / 86400);
        $totalNights += max(0, $n);
    }
    $occupancy = count($rooms) > 0 ? min(100, round($totalNights / (count($rooms) * 30) * 100)) : 0;

    $byPlatform = [];
    foreach ($confirmed as $b) {
        $src = $b['source'] ?? 'direct';
        $byPlatform[$src] = ($byPlatform[$src] ?? 0) + 1;
    }
    arsort($byPlatform);

    $nextWeek  = date('Y-m-d', strtotime('+7 days'));
    $arrivals  = array_filter($upcoming, fn($b) => $b['check_in'] <= $nextWeek && $b['check_in'] >= date('Y-m-d'));
    usort($arrivals, fn($a,$b) => strcmp($a['check_in'], $b['check_in']));

    $departures = array_filter($upcoming, fn($b) => $b['check_out'] <= $nextWeek && $b['check_out'] >= date('Y-m-d'));

    $ganttDays   = 60;
    $ganttOffset = (int)($_GET['gantt_offset'] ?? 0);
    // Clamp to a reasonable range so query doesn't get silly
    $ganttOffset = max(-3650, min(3650, $ganttOffset));
    $ganttStart  = new DateTime('today');
    if ($ganttOffset !== 0) $ganttStart->modify(($ganttOffset >= 0 ? '+' : '') . $ganttOffset . ' days');
    $ganttDates  = [];
    for ($i = 0; $i < $ganttDays; $i++) {
        $d = clone $ganttStart;
        $d->modify("+{$i} days");
        $ganttDates[] = $d->format('Y-m-d');
    }
    // For Gantt past-date browsing: fetch ALL bookings (not just upcoming) so old rows still paint
    $bookingsByRoom = [];
    foreach ($rooms as $rid => $_) $bookingsByRoom[$rid] = [];
    foreach ($confirmed as $b) {
        if (isset($bookingsByRoom[$b['room_id']])) {
            $bookingsByRoom[$b['room_id']][] = $b;
        }
    }

    $icalUrls = [];
    foreach ($rooms as $rid => $_) {
        $icalUrls[$rid] = SITE_URL . '/channel-manager/ical-export.php?room=' . urlencode($rid) . '&token=' . urlencode(ICAL_TOKEN);
    }
}

// ── Helpers ──────────────────────────────────────────────────────────
function sourceColor(string $s): string {
    return match(strtolower(trim($s))) {
        'airbnb'                    => '#FF5A5F',
        'booking.com','booking'     => '#003580',
        'agoda'                     => '#EB1A23',
        'makemytrip'                => '#E8262D',
        'direct','razorpay'         => '#2e7d32',
        'manual'                    => '#6d4c41',
        'blocked'                   => '#78909c',
        default                     => '#546e7a',
    };
}
function sourceName(string $s): string {
    return match(strtolower(trim($s))) {
        'booking.com','booking' => 'Booking.com',
        'airbnb'        => 'Airbnb',
        'agoda'         => 'Agoda',
        'makemytrip'    => 'MakeMyTrip',
        'direct'        => 'Direct',
        'razorpay'      => 'Direct (Razorpay)',
        'manual'        => 'Manual / Phone',
        'blocked'       => 'Blocked',
        default         => ucfirst($s),
    };
}
function badge(string $s): string {
    $c = sourceColor($s);
    return "<span class='badge' style='background:{$c}'>".htmlspecialchars(sourceName($s))."</span>";
}
function nights(string $ci, string $co): int {
    return max(0, (int)ceil((strtotime($co) - strtotime($ci)) / 86400));
}
function bookingOnDay(array $bookings, string $date): ?array {
    foreach ($bookings as $b) {
        if ($date >= $b['check_in'] && $date < $b['check_out']) return $b;
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PMS — Kanchi Farm Stay</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="admin-styles.css">
</head>
<body>

<?php if (empty($_SESSION['admin_logged_in'])): /* ═══ LOGIN ═══ */ ?>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div class="icon"><i class="fa-solid fa-seedling"></i></div>
      <h1>Channel Manager</h1>
      <p>Kanchi Farm Stay</p>
    </div>
    <?php if ($loginError): ?>
      <div class="login-err"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="login">
      <label>Admin Password</label>
      <input type="password" name="password" autofocus autocomplete="current-password" placeholder="Enter password" data-testid="input-password">
      <button type="submit" class="btn-login" data-testid="btn-login">Sign In <i class="fa-solid fa-arrow-right-to-bracket" style="margin-left:.35rem;"></i></button>
    </form>
  </div>
</div>

<?php else: /* ═══ DASHBOARD ═══ */ ?>
<div class="layout" id="layout">

<!-- ── Sidebar ── -->
<aside class="sidebar" id="sidebar">
  <button class="sidebar-toggle-btn" id="sidebar-toggle-btn" data-tip="Collapse sidebar" data-testid="btn-sidebar-toggle">
    <i class="fa-solid fa-chevron-left"></i>
  </button>
  <div class="sidebar-brand">
    <div class="brand-row">
      <div class="brand-icon"><i class="fa-solid fa-seedling"></i></div>
      <div>
        <div class="name">Kanchi Farm Stay</div>
        <div class="sub">Channel Manager</div>
      </div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section-label">Operations</div>
    <div class="nav-item <?= $section==='dashboard'?'active':'' ?>" onclick="goTo('dashboard')" data-testid="nav-dashboard">
      <span class="nav-icon"><i class="fa-solid fa-gauge"></i></span>
      <span class="nav-label">Dashboard</span>
    </div>
    <div class="nav-item <?= $section==='calendar'?'active':'' ?>" onclick="goTo('calendar')" data-testid="nav-calendar">
      <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
      <span class="nav-label">Calendar</span>
    </div>
    <div class="nav-item <?= $section==='bookings'?'active':'' ?>" onclick="goTo('bookings')" data-testid="nav-bookings">
      <span class="nav-icon"><i class="fa-solid fa-calendar-check"></i></span>
      <span class="nav-label">Bookings</span>
    </div>
    <a href="admin-guests.php" class="nav-item" data-testid="nav-guests">
      <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
      <span class="nav-label">Guests</span>
    </a>
    <a href="admin-night-audit.php" class="nav-item" data-testid="nav-night-audit">
      <span class="nav-icon"><i class="fa-solid fa-moon"></i></span>
      <span class="nav-label">Night Audit</span>
    </a>
    <div class="sidebar-divider"></div>
    <div class="sidebar-section-label">Revenue</div>
    <a href="admin-channels.php" class="nav-item" data-testid="nav-channels">
      <span class="nav-icon"><i class="fa-solid fa-link"></i></span>
      <span class="nav-label">Channels</span>
    </a>
    <a href="admin-pricing.php" class="nav-item" data-testid="nav-pricing">
      <span class="nav-icon"><i class="fa-solid fa-bolt"></i></span>
      <span class="nav-label">AI Pricing</span>
    </a>
    <a href="admin-campaigns.php" class="nav-item" data-testid="nav-campaigns">
      <span class="nav-icon"><i class="fa-solid fa-tag"></i></span>
      <span class="nav-label">Campaigns</span>
    </a>
    <a href="admin-revenue.php" class="nav-item" data-testid="nav-revenue">
      <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
      <span class="nav-label">Revenue</span>
    </a>
    <a href="admin-agents.php" class="nav-item" data-testid="nav-agents">
      <span class="nav-icon"><i class="fa-solid fa-handshake"></i></span>
      <span class="nav-label">Agents & Corp.</span>
    </a>
    <div class="sidebar-divider"></div>
    <div class="sidebar-section-label">Engagement</div>
    <a href="admin-whatsapp.php" class="nav-item" data-testid="nav-whatsapp">
      <span class="nav-icon"><i class="fa-brands fa-whatsapp"></i></span>
      <span class="nav-label">WhatsApp</span>
    </a>
    <a href="admin-reputation.php" class="nav-item" data-testid="nav-reputation">
      <span class="nav-icon"><i class="fa-solid fa-star"></i></span>
      <span class="nav-label">Reputation</span>
    </a>
    <div class="sidebar-divider"></div>
    <div class="sidebar-section-label">System</div>
    <div class="nav-item <?= $section==='export'?'active':'' ?>" onclick="goTo('export')" data-testid="nav-export">
      <span class="nav-icon"><i class="fa-solid fa-file-export"></i></span>
      <span class="nav-label">iCal Export</span>
    </div>
    <a href="admin-logs.php" class="nav-item" data-testid="nav-logs">
      <span class="nav-icon"><i class="fa-solid fa-scroll"></i></span>
      <span class="nav-label">Logs</span>
    </a>
    <a href="admin-settings.php" class="nav-item" data-testid="nav-settings">
      <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
      <span class="nav-label">Settings</span>
    </a>
  </nav>
  <div class="sidebar-bottom">
    <a href="/" data-testid="nav-view-website"><i class="fa-solid fa-arrow-up-right-from-square"></i> <span>View website</span></a>
    <a href="admin.php?action=logout" class="signout" data-testid="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> <span>Sign out</span></a>
  </div>
</aside>

<!-- Mobile overlay -->
<div id="sidebar-overlay" class="sidebar-overlay"></div>

<!-- ── Main ── -->
<div class="main" id="main-area">
  <div class="topbar">
    <div class="topbar-left">
      <button class="topbar-hamburger" id="sidebar-hamburger" data-testid="btn-hamburger">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="topbar-title" id="topbar-title">Dashboard</div>
    </div>
    <div class="topbar-right">
      <span class="topbar-date"><i class="fa-regular fa-calendar" style="margin-right:.3rem;"></i><?= date('D, d M Y') ?></span>
      <button class="sync-btn" id="sync-btn" onclick="syncAll(this)" data-testid="btn-sync-all">
        <i class="fa-solid fa-rotate"></i> Sync All
      </button>
      <button class="btn btn-icon" onclick="document.getElementById('kb-modal').classList.toggle('open')" data-tip="Keyboard shortcuts (?)" data-testid="btn-shortcuts">
        <i class="fa-solid fa-keyboard"></i>
      </button>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?>
    <div class="flash" data-testid="flash-message"><i class="fa-solid fa-circle-check"></i> <?= $flash ?></div>
    <?php endif; ?>

    <!-- ════════════ DASHBOARD ════════════ -->
    <div id="sec-dashboard" class="section-pane <?= $section==='dashboard'?'active':'' ?>">

      <!-- KPI Cards -->
      <div class="stats-row">
        <div class="stat-card" data-testid="stat-confirmed-bookings">
          <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-val"><?= count($confirmed) ?></div>
          <div class="stat-lbl">Confirmed Bookings</div>
        </div>
        <div class="stat-card" data-testid="stat-upcoming-stays">
          <div class="stat-icon"><i class="fa-solid fa-bed"></i></div>
          <div class="stat-val"><?= count($upcoming) ?></div>
          <div class="stat-lbl">Upcoming Stays</div>
        </div>
        <div class="stat-card" data-testid="stat-direct-revenue">
          <div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
          <div class="stat-val">₹<?= number_format($totalRev) ?></div>
          <div class="stat-lbl">Direct Revenue This Month</div>
        </div>
        <div class="stat-card" data-testid="stat-occupancy">
          <div class="stat-icon"><i class="fa-solid fa-chart-pie"></i></div>
          <div class="stat-val"><?= $occupancy ?>%</div>
          <div class="stat-lbl">Avg Occupancy (30 days)</div>
        </div>
      </div>

      <div class="dash-grid">
        <div>
          <!-- Arrivals -->
          <div class="panel">
            <div class="panel-hd">
              <div>
                <h3><i class="fa-solid fa-plane-arrival" style="color:var(--accent);margin-right:.4rem;"></i>Upcoming Arrivals</h3>
                <div class="sub">Next 7 days</div>
              </div>
              <span class="badge badge-green" data-testid="arrivals-count"><?= count($arrivals) ?> guests</span>
            </div>
            <div class="panel-bd">
              <?php if (empty($arrivals)): ?>
                <p style="color:var(--text-xlo);font-size:.83rem;text-align:center;padding:.5rem 0;">No arrivals in the next 7 days.</p>
              <?php else: ?>
                <div class="arrival-list">
                  <?php foreach ($arrivals as $a): ?>
                  <div class="arrival-item" data-testid="arrival-item">
                    <div class="arrival-date"><?= date('d M', strtotime($a['check_in'])) ?></div>
                    <div class="arrival-info">
                      <div class="arrival-name"><?= htmlspecialchars($a['guest_name']) ?></div>
                      <div class="arrival-sub"><?= htmlspecialchars($a['room_name']) ?> &middot; <?= nights($a['check_in'],$a['check_out']) ?>n &middot; <?= sourceName($a['source']) ?></div>
                    </div>
                    <?= badge($a['source']) ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Recent bookings -->
          <div class="panel">
            <div class="panel-hd">
              <h3><i class="fa-solid fa-clock-rotate-left" style="color:var(--accent);margin-right:.4rem;"></i>Recent Bookings</h3>
              <button class="cal-add-btn" onclick="goTo('bookings')" data-testid="btn-view-all-bookings">
                View All <i class="fa-solid fa-arrow-right" style="font-size:.7rem;"></i>
              </button>
            </div>
            <div class="tbl-wrap">
              <table class="tbl" data-testid="recent-bookings-table">
                <thead>
                  <tr>
                    <th>Property</th>
                    <th>Guest</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Source</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($allBookings, 0, 8) as $b): ?>
                  <tr>
                    <td class="room-name"><?= htmlspecialchars($b['room_name']) ?></td>
                    <td class="guest"><?= htmlspecialchars($b['guest_name']) ?></td>
                    <td><?= date('d M', strtotime($b['check_in'])) ?></td>
                    <td><?= date('d M', strtotime($b['check_out'])) ?></td>
                    <td><?= badge($b['source']) ?></td>
                    <td><?= $b['amount'] > 0 ? '₹'.number_format($b['amount']) : '<span class="muted">—</span>' ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($allBookings)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text-xlo);padding:2rem;">No bookings yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Right column -->
        <div>
          <!-- Platform breakdown -->
          <div class="panel">
            <div class="panel-hd"><h3>Bookings by Platform</h3></div>
            <div class="panel-bd">
              <?php if (empty($byPlatform)): ?>
                <p style="color:var(--text-xlo);font-size:.83rem;">No bookings yet.</p>
              <?php else: ?>
                <div class="platform-grid">
                  <?php foreach ($byPlatform as $src => $cnt): ?>
                    <div class="platform-card" style="background:<?= sourceColor($src) ?>" data-testid="platform-card-<?= $src ?>">
                      <div class="pc-count"><?= $cnt ?></div>
                      <div class="pc-name"><?= htmlspecialchars(sourceName($src)) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div style="margin-top:1.1rem;">
                  <?php
                  $total = array_sum($byPlatform);
                  foreach ($byPlatform as $src => $cnt):
                    $pct = $total > 0 ? round($cnt / $total * 100) : 0;
                  ?>
                  <div style="margin-bottom:.6rem;">
                    <div style="display:flex;justify-content:space-between;font-size:.76rem;margin-bottom:.22rem;">
                      <span style="font-weight:600;"><?= htmlspecialchars(sourceName($src)) ?></span>
                      <span style="color:var(--text-xlo);"><?= $cnt ?> (<?= $pct ?>%)</span>
                    </div>
                    <div style="height:6px;background:var(--bg-hover);border-radius:4px;overflow:hidden;">
                      <div style="height:100%;width:<?= $pct ?>%;background:<?= sourceColor($src) ?>;border-radius:4px;"></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Room occupancy -->
          <div class="panel">
            <div class="panel-hd"><h3>Room Occupancy (30 days)</h3></div>
            <div class="panel-bd">
              <?php foreach ($rooms as $rid => $rname):
                $roomNights = 0;
                foreach ($bookingsByRoom[$rid] ?? [] as $b) {
                    if ($b['status']==='confirmed') $roomNights += nights($b['check_in'],$b['check_out']);
                }
                $rOcc = min(100, round($roomNights / 30 * 100));
              ?>
              <div style="margin-bottom:.6rem;">
                <div style="display:flex;justify-content:space-between;font-size:.77rem;margin-bottom:.22rem;">
                  <span style="font-weight:600;"><?= htmlspecialchars($rname) ?></span>
                  <span style="color:var(--text-xlo);"><?= $rOcc ?>%</span>
                </div>
                <div style="height:6px;background:var(--bg-hover);border-radius:4px;overflow:hidden;">
                  <div style="height:100%;width:<?= $rOcc ?>%;background:var(--accent);border-radius:4px;transition:width .5s ease;"></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ════════════ CALENDAR (GANTT) ════════════ -->
    <div id="sec-calendar" class="section-pane <?= $section==='calendar'?'active':'' ?>">
      <div class="panel">
        <div class="panel-hd" style="flex-wrap:wrap;gap:.75rem;">
          <div class="cal-toolbar" style="margin:0;flex:1;">
            <h3>Availability Calendar</h3>
            <?php
              $prevOff = $ganttOffset - 30;
              $nextOff = $ganttOffset + 30;
              $rangeStart = date('d M Y', strtotime($ganttDates[0] ?? 'today'));
              $rangeEnd   = date('d M Y', strtotime(end($ganttDates) ?: 'today'));
            ?>
            <button class="cal-nav-btn" onclick="ganttShift(<?= $prevOff ?>)" data-tip="Previous 30 days" data-testid="btn-cal-prev">
              <i class="fa-solid fa-chevron-left" style="font-size:.72rem;"></i> Prev
            </button>
            <button class="cal-nav-btn" onclick="ganttShift(0)" data-tip="Jump to today" data-testid="btn-cal-jump-today">
              <i class="fa-solid fa-crosshairs" style="font-size:.72rem;"></i> Today
            </button>
            <button class="cal-nav-btn" onclick="ganttShift(<?= $nextOff ?>)" data-tip="Next 30 days" data-testid="btn-cal-next">
              Next <i class="fa-solid fa-chevron-right" style="font-size:.72rem;"></i>
            </button>
            <span class="cal-range-label" data-testid="cal-range-label"><?= $rangeStart ?> → <?= $rangeEnd ?></span>
            <button class="cal-nav-btn active" id="cal-btn-30" onclick="setCalRange(30)" data-testid="btn-cal-30">
              <i class="fa-solid fa-calendar" style="font-size:.72rem;"></i> 30 days
            </button>
            <button class="cal-nav-btn" id="cal-btn-60" onclick="setCalRange(60)" data-testid="btn-cal-60">
              <i class="fa-solid fa-calendar-week" style="font-size:.72rem;"></i> 60 days
            </button>
          </div>
          <button class="cal-add-btn" onclick="openDrawer()" data-testid="btn-add-booking-calendar">
            <i class="fa-solid fa-plus"></i> New Booking
          </button>
        </div>
        <div class="panel-bd" style="padding-top:.85rem;padding-bottom:.85rem;">

          <!-- Legend -->
          <div class="gantt-legend">
            <?php foreach (['direct'=>'Direct','airbnb'=>'Airbnb','booking.com'=>'Booking.com','agoda'=>'Agoda','makemytrip'=>'MakeMyTrip','manual'=>'Walk-in / Phone','blocked'=>'Blocked'] as $src => $lbl): ?>
              <div class="gl-item"><div class="gl-dot" style="background:<?= sourceColor($src) ?>"></div><?= $lbl ?></div>
            <?php endforeach; ?>
            <div class="gl-item"><div class="gl-dot" style="background:var(--accent-light);border:2px solid var(--accent);"></div>Today</div>
            <div class="gl-item" style="margin-left:auto;font-size:.7rem;color:var(--text-xlo);">
              <i class="fa-regular fa-hand-pointer" style="margin-right:.3rem;"></i>Click empty cell to add booking
            </div>
          </div>

          <div class="gantt-scroll-hint"><i class="fa-solid fa-arrows-left-right"></i> Scroll horizontally to see more dates</div>

          <div class="gantt-wrap" id="gantt-wrap">
            <table class="gantt-table" id="gantt-table">
              <thead>
                <!-- Month label row -->
                <tr id="gantt-month-row">
                  <th class="gantt-room-col" style="border-right:2px solid var(--border-strong);"></th>
                  <?php
                  $prevMonth = '';
                  $monthSpan = 0;
                  $monthCells = [];
                  foreach ($ganttDates as $gd) {
                    $m = date('M Y', strtotime($gd));
                    if ($m !== $prevMonth) {
                      if ($prevMonth) $monthCells[] = ['month'=>$prevMonth,'span'=>$monthSpan];
                      $prevMonth = $m; $monthSpan = 1;
                    } else { $monthSpan++; }
                  }
                  if ($prevMonth) $monthCells[] = ['month'=>$prevMonth,'span'=>$monthSpan];
                  foreach ($monthCells as $mc):
                  ?>
                    <th colspan="<?= $mc['span'] ?>" style="padding:0;border-right:1px solid rgba(0,0,0,.06);">
                      <div class="gantt-month-hdr"><?= $mc['month'] ?></div>
                    </th>
                  <?php endforeach; ?>
                </tr>
                <!-- Day header row -->
                <tr>
                  <th class="gantt-room-col" style="border-right:2px solid var(--border-strong);">
                    <div class="gantt-hdr-room">Property</div>
                  </th>
                  <?php foreach ($ganttDates as $gd):
                    $dow = date('D', strtotime($gd));
                    $isToday = $gd === date('Y-m-d');
                    $isWkend = in_array($dow, ['Sat','Sun']);
                    $isMonthStart = date('j', strtotime($gd)) === '1';
                  ?>
                    <th class="gantt-day-col <?= $isToday?'today-col':($isWkend?'weekend-col':'') ?>"
                        id="<?= $isToday?'gantt-today':'' ?>"
                        style="<?= $isMonthStart?'border-left:2px solid var(--border-strong);':'' ?>">
                      <div class="gantt-hdr-day">
                        <span class="d-num"><?= (int)date('d', strtotime($gd)) ?></span>
                        <span class="d-dow"><?= substr($dow,0,1) ?></span>
                      </div>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php
                $roomColors = ['#6366F1','#F97316','#10B981','#3B82F6','#EC4899','#8B5CF6','#14B8A6','#F59E0B','#EF4444','#06B6D4'];
                $roomIdx = 0;
                foreach ($rooms as $rid => $rname):
                  $roomBks   = $bookingsByRoom[$rid] ?? [];
                  $roomColor = $roomColors[$roomIdx % count($roomColors)];
                  $roomIdx++;
                  $i = 0;
                ?>
                <tr>
                  <td class="gantt-room-label" style="border-right:2px solid var(--border-strong);">
                    <div class="room-dot" style="background:<?= $roomColor ?>"></div>
                    <span style="font-size:.77rem;"><?= htmlspecialchars($rname) ?></span>
                  </td>
                  <?php
                  while ($i < count($ganttDates)):
                    $gd       = $ganttDates[$i];
                    $bk       = bookingOnDay($roomBks, $gd);
                    $dow      = date('D', strtotime($gd));
                    $isToday  = $gd === date('Y-m-d');
                    $isPast   = $gd < date('Y-m-d');
                    $isWkend  = in_array($dow, ['Sat','Sun']);
                    $isMonthStart = date('j', strtotime($gd)) === '1';

                    if ($bk):
                      $span = 1;
                      for ($j = $i+1; $j < count($ganttDates); $j++) {
                          if ($ganttDates[$j] >= $bk['check_out']) break;
                          $span++;
                      }
                      $isFirst  = ($gd === $bk['check_in'] || $i === 0);
                      $clr      = sourceColor($bk['source']);
                      $isBlock  = $bk['source'] === 'blocked';
                      $nightCnt = nights($bk['check_in'], $bk['check_out']);
                      $tipText  = $bk['guest_name'] . "\n" . sourceName($bk['source']) . " · " . $bk['check_in'] . " → " . $bk['check_out'] . " (" . $nightCnt . "n)";
                      if ($bk['amount'] > 0) $tipText .= "\n₹" . number_format($bk['amount']);
                  ?>
                      <td colspan="<?= $span ?>" class="gantt-booking gantt-booking-clickable"
                          style="<?= $isMonthStart?'border-left:2px solid var(--border-strong);':'' ?>"
                          data-tip="<?= htmlspecialchars($tipText) ?> · click to edit"
                          onclick="editBooking(<?= (int)$bk['id'] ?>)"
                          data-testid="gantt-booking-<?= $bk['id'] ?>">
                        <?php if ($isFirst): ?>
                          <div class="gantt-bk-inner <?= $isBlock?'is-block':'' ?>"
                               style="background:<?= $clr ?>;opacity:<?= $isPast?.45:1 ?>;">
                            <?php if (!$isBlock): ?>
                              <span class="bk-guest"><?= htmlspecialchars(substr($bk['guest_name'],0,16)) ?></span>
                              <span class="bk-src"><?= strtoupper(substr($bk['source'],0,3)) ?></span>
                            <?php endif; ?>
                          </div>
                        <?php else: ?>
                          <div class="gantt-bk-inner <?= $isBlock?'is-block':'' ?>"
                               style="background:<?= $clr ?>;opacity:<?= $isPast?.45:1 ?>;"></div>
                        <?php endif; ?>
                      </td>
                  <?php
                      $i += $span;
                    else:
                      $cls = 'gantt-day-free';
                      if ($isToday) $cls .= ' today-day';
                      if ($isWkend) $cls .= ' weekend-day';
                      if ($isPast)  $cls  = 'gantt-day-past';
                  ?>
                      <td class="<?= $cls ?>"
                          style="<?= $isMonthStart?'border-left:2px solid var(--border-strong);':'' ?>"
                          <?= !$isPast ? "onclick=\"openDrawer('".htmlspecialchars($rid)."','".htmlspecialchars($gd)."')\"" : '' ?>
                          <?= !$isPast ? "data-tip=\"Add booking: ".htmlspecialchars($rname)." on ".date('d M Y',strtotime($gd))."\"" : '' ?>>
                      </td>
                  <?php
                      $i++;
                    endif;
                  endwhile;
                  ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>

    <!-- ════════════ BOOKINGS ════════════ -->
    <div id="sec-bookings" class="section-pane <?= $section==='bookings'?'active':'' ?>">

      <!-- Toolbar -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.75rem;">
        <div>
          <div style="font-size:1rem;font-weight:700;color:var(--text-hi);">All Bookings</div>
          <div style="font-size:.77rem;color:var(--text-lo);margin-top:.1rem;" data-testid="bookings-total-count"><?= count($allBookings) ?> total reservations</div>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
          <button class="btn btn-secondary btn-sm" onclick="exportBookings()" data-testid="btn-export-bookings">
            <i class="fa-solid fa-download"></i> Export
          </button>
          <button class="btn btn-secondary btn-sm" onclick="openQuickDirect()" data-tip="Quick direct booking (today→tomorrow, source: Direct)" data-testid="btn-quick-direct-bookings">
            <i class="fa-solid fa-bolt" style="color:var(--accent);"></i> Quick Direct
          </button>
          <button class="cal-add-btn" onclick="openDrawer()" data-testid="btn-add-booking-bookings">
            <i class="fa-solid fa-plus"></i> New Booking
          </button>
        </div>
      </div>

      <div class="panel">
        <!-- Search & Filter bar -->
        <div class="search-bar">
          <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--text-xlo);font-size:.78rem;pointer-events:none;"></i>
            <input type="text" id="bk-search" placeholder="Search guest, room, ref…" oninput="filterBookings()"
              style="padding-left:2.2rem;min-width:220px;" data-testid="input-booking-search">
          </div>
          <select id="bk-filter-room" onchange="filterBookings()" data-testid="select-filter-room">
            <option value="">All Properties</option>
            <?php foreach ($rooms as $rid => $rn): ?>
              <option value="<?= $rid ?>"><?= htmlspecialchars($rn) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="bk-filter-src" onchange="filterBookings()" data-testid="select-filter-source">
            <option value="">All Sources</option>
            <?php foreach (['airbnb','booking.com','agoda','makemytrip','direct','manual','blocked'] as $s): ?>
              <option value="<?= $s ?>"><?= sourceName($s) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="bk-filter-status" onchange="filterBookings()" data-testid="select-filter-status">
            <option value="">All Statuses</option>
            <option value="confirmed">Confirmed</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <select id="bk-sort" onchange="sortBookings()" data-testid="select-sort-bookings" data-tip="Sort bookings">
            <option value="checkin-desc">Check-in: newest first</option>
            <option value="checkin-asc">Check-in: oldest first</option>
            <option value="created-desc" selected>Recently added</option>
            <option value="created-asc">Oldest added</option>
            <option value="checkout-asc">Check-out: soonest</option>
            <option value="checkout-desc">Check-out: latest</option>
            <option value="amount-desc">Amount: high → low</option>
            <option value="amount-asc">Amount: low → high</option>
          </select>
          <span id="bk-result-count" style="font-size:.77rem;color:var(--text-xlo);margin-left:auto;"></span>
        </div>

        <!-- Date-range filter row -->
        <div class="search-bar date-range-bar" style="border-top:1px dashed var(--border);">
          <span class="dr-lbl"><i class="fa-regular fa-calendar" style="margin-right:.3rem;color:var(--accent);"></i>Date range</span>
          <div class="fld dr-fld">
            <label for="bk-date-from">From</label>
            <input type="date" id="bk-date-from" oninput="filterBookings()" data-testid="input-date-from">
          </div>
          <div class="fld dr-fld">
            <label for="bk-date-to">To</label>
            <input type="date" id="bk-date-to" oninput="filterBookings()" data-testid="input-date-to">
          </div>
          <select id="bk-date-field" onchange="filterBookings()" data-testid="select-date-field" data-tip="Apply range to which date">
            <option value="checkin">on check-in</option>
            <option value="checkout">on check-out</option>
            <option value="stay">overlapping stay</option>
          </select>
          <div class="dr-presets">
            <button type="button" class="cal-nav-btn cal-nav-sm" onclick="setDateRangePreset('today')" data-testid="btn-preset-today">Today</button>
            <button type="button" class="cal-nav-btn cal-nav-sm" onclick="setDateRangePreset('week')" data-testid="btn-preset-week">This week</button>
            <button type="button" class="cal-nav-btn cal-nav-sm" onclick="setDateRangePreset('month')" data-testid="btn-preset-month">This month</button>
            <button type="button" class="cal-nav-btn cal-nav-sm" onclick="setDateRangePreset('next30')" data-testid="btn-preset-next30">Next 30d</button>
            <button type="button" class="cal-nav-btn cal-nav-sm" onclick="setDateRangePreset('clear')" data-testid="btn-preset-clear">Clear</button>
          </div>
        </div>

        <div class="tbl-wrap bookings-cards">
          <table class="tbl" id="bk-table" data-testid="bookings-table">
            <thead>
              <tr>
                <th class="hide-mobile">#</th>
                <th>Property</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th class="hide-mobile">Nights</th>
                <th>Guest</th>
                <th class="hide-mobile">Phone</th>
                <th>Source</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($allBookings)): ?>
                <tr class="row-empty"><td colspan="11" style="text-align:center;padding:2.5rem;color:var(--text-xlo);">
                  <i class="fa-regular fa-calendar" style="font-size:1.5rem;display:block;margin-bottom:.5rem;opacity:.4;"></i>
                  No bookings yet.
                </td></tr>
              <?php else: ?>
                <?php foreach ($allBookings as $b):
                  $n = nights($b['check_in'], $b['check_out']);
                ?>
                <tr data-room="<?= $b['room_id'] ?>" data-src="<?= htmlspecialchars($b['source']) ?>" data-status="<?= $b['status'] ?>"
                    data-checkin="<?= htmlspecialchars($b['check_in']) ?>"
                    data-checkout="<?= htmlspecialchars($b['check_out']) ?>"
                    data-amount="<?= (float)($b['amount'] ?? 0) ?>"
                    data-id="<?= (int)$b['id'] ?>"
                    data-search="<?= strtolower(htmlspecialchars($b['guest_name'].' '.$b['room_name'].' '.$b['booking_ref'])) ?>"
                    data-testid="booking-row-<?= $b['id'] ?>">
                  <td class="muted hide-mobile" data-label="#"><?= $b['id'] ?></td>
                  <td class="room-name cell-property" data-label="Property"><?= htmlspecialchars($b['room_name']) ?></td>
                  <td data-label="Check-in"><?= date('d M Y', strtotime($b['check_in'])) ?></td>
                  <td data-label="Check-out"><?= date('d M Y', strtotime($b['check_out'])) ?></td>
                  <td class="hide-mobile" data-label="Nights"><?= $n ?></td>
                  <td class="guest" data-label="Guest">
                    <span><?= htmlspecialchars($b['guest_name']) ?><?php if ($b['guest_email']): ?><div class="muted"><?= htmlspecialchars($b['guest_email']) ?></div><?php endif; ?></span>
                  </td>
                  <td class="muted hide-mobile" data-label="Phone"><?= htmlspecialchars($b['guest_phone']) ?></td>
                  <td data-label="Source"><?= badge($b['source']) ?></td>
                  <td data-label="Amount"><?= $b['amount'] > 0 ? '₹'.number_format($b['amount']) : '<span class="muted">—</span>' ?></td>
                  <td data-label="Status">
                    <?php if ($b['status']==='confirmed'): ?>
                      <span class="status-confirmed"><i class="fa-solid fa-circle-check"></i> Confirmed</span>
                    <?php else: ?>
                      <span class="status-cancelled"><i class="fa-solid fa-ban"></i> Cancelled</span>
                    <?php endif; ?>
                  </td>
                  <td class="cell-actions" style="white-space:nowrap;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="editBooking(<?= (int)$b['id'] ?>)" data-tip="Edit booking" data-testid="btn-edit-<?= $b['id'] ?>"><i class="fa-solid fa-pen-to-square"></i></button>
                    <?php if ($b['status']==='confirmed'): ?>
                      <form method="POST" style="display:inline;margin-left:3px;" onsubmit="return confirm('Cancel this booking?')">
                        <input type="hidden" name="action" value="cancel_booking">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <button class="btn btn-warn btn-sm" data-testid="btn-cancel-<?= $b['id'] ?>"><i class="fa-solid fa-ban"></i></button>
                      </form>
                    <?php endif; ?>
                    <?php if (!empty($b['booking_ref']) && in_array($b['source'] ?? '', ['direct','razorpay'])): ?>
                      <button class="btn btn-info btn-sm" style="margin-left:3px;" onclick="openRefund(<?= $b['id'] ?>,'<?= htmlspecialchars($b['booking_ref']) ?>',<?= $b['amount_paid'] ?? $b['amount'] ?>)" data-testid="btn-refund-<?= $b['id'] ?>"><i class="fa-solid fa-rotate-left"></i></button>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;margin-left:3px;" onsubmit="return confirm('Permanently delete this record?')">
                      <input type="hidden" name="action" value="delete_booking">
                      <input type="hidden" name="id" value="<?= $b['id'] ?>">
                      <button class="btn btn-danger btn-sm" data-testid="btn-delete-<?= $b['id'] ?>"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ════════════ CHANNELS ════════════ -->
    <div id="sec-channels" class="section-pane <?= $section==='channels'?'active':'' ?>">

      <div class="panel">
        <div class="panel-hd">
          <div>
            <h3><i class="fa-solid fa-link" style="color:var(--accent);margin-right:.4rem;"></i>Add External Calendar Feed</h3>
          </div>
        </div>
        <div class="panel-bd">
          <p style="font-size:.83rem;color:var(--text-lo);margin-bottom:1rem;">
            Paste the iCal export URL from Airbnb, Booking.com, Agoda etc. and click <strong>Sync All</strong> to import their blocked dates.
          </p>
          <form method="POST">
            <input type="hidden" name="action" value="add_calendar">
            <div class="form-grid">
              <div class="fld">
                <label>Property *</label>
                <select name="room_id" required data-testid="select-channel-room">
                  <?php foreach ($rooms as $rid => $rn): ?><option value="<?= $rid ?>"><?= htmlspecialchars($rn) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="fld">
                <label>Platform *</label>
                <select name="platform" data-testid="select-channel-platform">
                  <option value="airbnb">Airbnb</option>
                  <option value="booking.com">Booking.com</option>
                  <option value="agoda">Agoda</option>
                  <option value="makemytrip">MakeMyTrip</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="fld" style="grid-column:span 2">
                <label>iCal URL *</label>
                <input type="url" name="ical_url" placeholder="https://www.airbnb.com/calendar/ical/…" required data-testid="input-ical-url">
              </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:.9rem;" data-testid="btn-add-channel">
              <i class="fa-solid fa-plus"></i> Add Calendar Feed
            </button>
          </form>
        </div>
      </div>

      <div class="panel">
        <div class="panel-hd">
          <h3>Connected Channels</h3>
          <button class="sync-btn" onclick="syncAll(this)" data-testid="btn-sync-channels">
            <i class="fa-solid fa-rotate"></i> Sync All Now
          </button>
        </div>
        <div class="tbl-wrap">
          <table class="tbl" data-testid="channels-table">
            <thead><tr><th>Property</th><th>Platform</th><th>iCal URL</th><th>Last Synced</th><th></th></tr></thead>
            <tbody>
              <?php if (empty($extCals)): ?>
                <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-xlo);">
                  <i class="fa-solid fa-link" style="font-size:1.5rem;display:block;margin-bottom:.5rem;opacity:.3;"></i>
                  No channels connected yet.
                </td></tr>
              <?php else: ?>
                <?php foreach ($extCals as $ec): ?>
                <tr>
                  <td class="room-name"><?= htmlspecialchars($rooms[$ec['room_id']] ?? $ec['room_id']) ?></td>
                  <td><?= badge($ec['platform']) ?></td>
                  <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.77rem;">
                    <a href="<?= htmlspecialchars($ec['ical_url']) ?>" target="_blank" style="color:var(--text-lo);"
                       data-tip="<?= htmlspecialchars($ec['ical_url']) ?>"><?= htmlspecialchars(substr($ec['ical_url'],0,50)).'…' ?></a>
                  </td>
                  <td>
                    <?php if ($ec['last_synced']): ?>
                      <span class="status-synced"><i class="fa-solid fa-circle-check"></i> <?= substr($ec['last_synced'],0,16) ?></span>
                    <?php else: ?>
                      <span class="status-never">Never synced</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" onsubmit="return confirm('Remove this channel?')">
                      <input type="hidden" name="action" value="delete_calendar">
                      <input type="hidden" name="id" value="<?= $ec['id'] ?>">
                      <button class="btn btn-danger btn-sm" data-testid="btn-remove-channel-<?= $ec['id'] ?>"><i class="fa-solid fa-trash"></i> Remove</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <div class="panel-hd"><h3><i class="fa-solid fa-clock" style="color:var(--accent);margin-right:.4rem;"></i>Automatic Sync (Cron Job)</h3></div>
        <div class="panel-bd">
          <div class="howto">
            <h4>Set up hourly auto-sync on Hostinger</h4>
            1. Log into <strong>hPanel &rarr; Advanced &rarr; Cron Jobs</strong><br>
            2. Set frequency: <code>Every Hour</code><br>
            3. Command: <code>php <?= __DIR__ ?>/sync.php</code><br><br>
            This imports new bookings from all connected platforms every hour automatically.
          </div>
        </div>
      </div>
    </div>

    <!-- ════════════ iCAL EXPORT ════════════ -->
    <div id="sec-export" class="section-pane <?= $section==='export'?'active':'' ?>">
      <div class="panel">
        <div class="panel-hd"><h3><i class="fa-solid fa-file-export" style="color:var(--accent);margin-right:.4rem;"></i>Your iCal Export URLs</h3></div>
        <div class="panel-bd">
          <p style="font-size:.83rem;color:var(--text-lo);margin-bottom:1.25rem;">
            Copy each URL and paste it into the corresponding platform as an <strong>imported calendar</strong>.
            The platform will regularly fetch this feed and block already-booked dates.
          </p>
          <div class="ical-list">
            <?php foreach ($rooms as $rid => $rn): ?>
            <div class="ical-row">
              <span class="ical-room-lbl"><?= htmlspecialchars($rn) ?></span>
              <span class="ical-url-box" id="url-<?= $rid ?>" data-testid="ical-url-<?= $rid ?>"><?= htmlspecialchars($icalUrls[$rid]) ?></span>
              <button class="btn btn-copy" onclick="copyUrl('url-<?= $rid ?>', this)" data-testid="btn-copy-<?= $rid ?>">
                <i class="fa-solid fa-copy"></i> Copy
              </button>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-hd"><h3>How to Connect Each Platform</h3></div>
        <div class="panel-bd">
          <div class="howto">
            <h4>Airbnb</h4>
            Hosting &rarr; Your Listings &rarr; Select listing &rarr; <strong>Availability &rarr; Sync calendars &rarr; Import calendar</strong> &rarr; paste the URL above &rarr; Save<br><br>
            <h4>Booking.com</h4>
            Extranet &rarr; <strong>Calendar &rarr; Sync calendars &rarr; Add a source</strong> &rarr; paste the URL above &rarr; Save<br><br>
            <h4>Agoda / MakeMyTrip</h4>
            Look for <strong>Calendar Sync</strong> or <strong>iCal Import</strong> in their property extranet and paste the matching URL.<br><br>
            <strong>Then add their export URL</strong> under the Channels tab so their bookings also block your calendar.
          </div>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->

<!-- ═══ BOOKING DRAWER ═══════════════════════════════════════════ -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="bookingDrawer">
  <div class="drawer-head">
    <div>
      <h2 id="drawerTitle">New Booking</h2>
      <p id="drawerSub">Fill in the details below to confirm a reservation</p>
    </div>
    <button class="drawer-close" onclick="closeDrawer()" data-testid="btn-close-drawer"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="drawer-body">
    <form method="POST" id="drawerForm">
      <input type="hidden" name="action" value="add_booking" id="drawerAction">
      <input type="hidden" name="id" value="" id="drawer-id">
      <input type="hidden" name="return_to" value="bookings" id="drawer-return-to">

      <!-- Type toggle -->
      <div class="bk-type-toggle" id="bkTypeToggle">
        <button type="button" class="bk-type-btn active" onclick="setBkType('guest')" id="btnGuest" data-testid="btn-type-guest">
          <i class="fa-solid fa-user"></i> Guest Booking
        </button>
        <button type="button" class="bk-type-btn" onclick="setBkType('block')" id="btnBlock" data-testid="btn-type-block">
          <i class="fa-solid fa-lock"></i> Block Dates
        </button>
      </div>

      <!-- Property & Dates -->
      <div class="drawer-section">
        <div class="drawer-section-label"><i class="fa-solid fa-door-open" style="color:var(--accent);"></i> Property &amp; Dates</div>
        <div class="fld">
          <label>Property *</label>
          <select name="room_id" id="drawer-room" required onchange="updateAmountHint()" data-testid="select-drawer-room">
            <?php foreach ($rooms as $rid => $rn): ?><option value="<?= $rid ?>"><?= htmlspecialchars($rn) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
          <div class="fld">
            <label>Check-in *</label>
            <input type="date" name="check_in" id="drawer-checkin" required onchange="updateNights();updateAmountHint()" data-testid="input-checkin">
          </div>
          <div class="fld">
            <label>Check-out *</label>
            <input type="date" name="check_out" id="drawer-checkout" required onchange="updateNights();updateAmountHint()" data-testid="input-checkout">
          </div>
        </div>
        <div id="nights-display" style="display:none;">
          <span class="nights-pill" id="nights-pill"><i class="fa-solid fa-moon" style="font-size:.7rem;"></i> 1 night</span>
        </div>
      </div>

      <!-- Guest Details -->
      <div class="drawer-section" id="guestSection">
        <div class="drawer-section-label"><i class="fa-solid fa-user" style="color:var(--accent);"></i> Guest Details</div>
        <div class="fld">
          <label>Guest Name *</label>
          <input type="text" name="guest_name" id="drawer-name" placeholder="Ravi Kumar" required data-testid="input-guest-name">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
          <div class="fld">
            <label>Phone</label>
            <input type="tel" name="guest_phone" placeholder="+91 98765 43210" data-testid="input-guest-phone">
          </div>
          <div class="fld">
            <label>Email</label>
            <input type="email" name="guest_email" placeholder="guest@email.com" data-testid="input-guest-email">
          </div>
        </div>
      </div>

      <!-- Payment & Source -->
      <div class="drawer-section" id="paymentSection">
        <div class="drawer-section-label"><i class="fa-solid fa-indian-rupee-sign" style="color:var(--accent);"></i> Payment &amp; Source</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
          <div class="fld">
            <label>Amount (₹)</label>
            <input type="number" name="amount" id="drawer-amount" min="0" step="1" placeholder="0" data-testid="input-amount">
            <div class="amount-hint" id="amount-hint"></div>
          </div>
          <div class="fld">
            <label>Source</label>
            <select name="source" id="drawer-source" data-testid="select-source">
              <option value="manual">Walk-in / Phone</option>
              <option value="direct">Direct (Website)</option>
              <option value="airbnb">Airbnb</option>
              <option value="booking.com">Booking.com</option>
              <option value="agoda">Agoda</option>
              <option value="makemytrip">MakeMyTrip</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="drawer-section">
        <div class="drawer-section-label"><i class="fa-solid fa-note-sticky" style="color:var(--accent);"></i> Notes</div>
        <div class="fld">
          <textarea name="notes" rows="3" placeholder="Special requests, dietary needs, room preferences…"></textarea>
        </div>
      </div>
    </form>
  </div>
  <div class="drawer-foot">
    <button type="button" class="btn btn-secondary" onclick="closeDrawer()" data-testid="btn-drawer-cancel">Cancel</button>
    <button type="submit" form="drawerForm" class="btn btn-primary" id="drawerSubmitBtn" data-testid="btn-drawer-save">
      <i class="fa-solid fa-floppy-disk"></i> Save &amp; Notify
    </button>
  </div>
</div>

<!-- Mobile FAB stack: quick-direct + full new booking -->
<div class="fab-stack" data-testid="fab-stack">
  <button class="fab fab-secondary" onclick="openDrawer()" data-tip="New booking (N)" data-testid="btn-fab-new-booking" aria-label="New booking">
    <i class="fa-solid fa-plus"></i>
  </button>
  <button class="fab fab-primary" onclick="openQuickDirect()" data-tip="Quick direct booking" data-testid="btn-fab-quick-direct" aria-label="Quick direct booking">
    <i class="fa-solid fa-bolt"></i>
    <span class="fab-label">Direct&nbsp;Booking</span>
  </button>
</div>

<!-- Keyboard Shortcuts Modal -->
<div class="kb-modal-overlay" id="kb-modal">
  <div class="kb-modal">
    <div class="kb-modal-head">
      <h3><i class="fa-solid fa-keyboard" style="color:var(--accent);margin-right:.5rem;"></i>Keyboard Shortcuts</h3>
      <button class="btn btn-icon" onclick="document.getElementById('kb-modal').classList.remove('open')" data-testid="btn-close-shortcuts"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="kb-row"><span>Go to Dashboard</span><div class="kb-keys"><span class="kb-key">D</span></div></div>
    <div class="kb-row"><span>Go to Calendar</span><div class="kb-keys"><span class="kb-key">C</span></div></div>
    <div class="kb-row"><span>Go to Bookings</span><div class="kb-keys"><span class="kb-key">B</span></div></div>
    <div class="kb-row"><span>New Booking</span><div class="kb-keys"><span class="kb-key">N</span></div></div>
    <div class="kb-row"><span>Search Bookings</span><div class="kb-keys"><span class="kb-key">/</span></div></div>
    <div class="kb-row"><span>Close modal / drawer</span><div class="kb-keys"><span class="kb-key">Esc</span></div></div>
    <div class="kb-row"><span>Show shortcuts</span><div class="kb-keys"><span class="kb-key">?</span></div></div>
  </div>
</div>

<div id="ui-tooltip"></div>

<script>
const sectionTitles = {
  dashboard: 'Dashboard',
  calendar:  'Availability Calendar',
  bookings:  'Bookings',
  channels:  'Channels',
  export:    'iCal Export'
};

// ── Room base prices ─────────────────────────────────────────────
const roomPriceMap = <?= json_encode(
  array_combine(array_keys(ROOM_IDS), array_map(fn($rid) => ROOM_BASE_PRICES[$rid] ?? 0, array_keys(ROOM_IDS)))
) ?>;

// ── All bookings (for edit drawer prefill) ───────────────────────
const bookingMap = <?= json_encode(array_reduce($allBookings ?? [], function($acc, $b) {
  $acc[(int)$b['id']] = [
    'id'          => (int)$b['id'],
    'room_id'     => $b['room_id'],
    'check_in'    => $b['check_in'],
    'check_out'   => $b['check_out'],
    'guest_name'  => $b['guest_name'],
    'guest_email' => $b['guest_email'],
    'guest_phone' => $b['guest_phone'],
    'source'      => $b['source'],
    'amount'      => (float)$b['amount'],
    'notes'       => $b['notes'] ?? '',
    'status'      => $b['status'],
  ];
  return $acc;
}, [])) ?: '{}' ?>;

// ── Current gantt offset (for prev/next nav) ─────────────────────
const ganttOffset = <?= (int)($ganttOffset ?? 0) ?>;

// ── Sidebar collapse ─────────────────────────────────────────────
const layoutEl  = document.getElementById('layout');
const sidebarEl = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebar-toggle-btn');
const overlayEl = document.getElementById('sidebar-overlay');

function applySidebarState(collapsed) {
  layoutEl.classList.toggle('sidebar-collapsed', collapsed);
  sidebarEl.classList.toggle('collapsed', collapsed);
}
if (toggleBtn) {
  applySidebarState(localStorage.getItem('sb-collapsed') === '1');
  toggleBtn.addEventListener('click', () => {
    const now = !sidebarEl.classList.contains('collapsed');
    applySidebarState(now);
    localStorage.setItem('sb-collapsed', now ? '1' : '0');
  });
}

// ── Mobile hamburger ─────────────────────────────────────────────
const hamburger = document.getElementById('sidebar-hamburger');
if (hamburger && overlayEl && sidebarEl) {
  hamburger.addEventListener('click', () => {
    sidebarEl.classList.toggle('mobile-open');
    overlayEl.classList.toggle('open');
  });
  overlayEl.addEventListener('click', () => {
    sidebarEl.classList.remove('mobile-open');
    overlayEl.classList.remove('open');
  });
}

// ── Tooltip ──────────────────────────────────────────────────────
const tip = document.getElementById('ui-tooltip');
if (tip) {
  document.addEventListener('mouseover', e => {
    const el = e.target.closest('[data-tip]');
    if (el) { tip.textContent = el.dataset.tip; tip.style.opacity = '1'; }
    else     { tip.style.opacity = '0'; }
  });
  document.addEventListener('mousemove', e => {
    if (tip.style.opacity === '1') {
      const maxX = window.innerWidth - tip.offsetWidth - 8;
      tip.style.left = Math.min(e.clientX + 14, maxX) + 'px';
      tip.style.top  = (e.clientY - 32) + 'px';
    }
  });
}

// ── Navigation ───────────────────────────────────────────────────
let currentBkType = 'guest';

function goTo(sec) {
  document.querySelectorAll('.section-pane').forEach(p => p.classList.remove('active'));
  const pane = document.getElementById('sec-' + sec);
  if (pane) pane.classList.add('active');
  document.getElementById('topbar-title').textContent = sectionTitles[sec] || sec;
  history.replaceState(null, '', 'admin.php?section=' + sec);
  // Update active nav
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const navKeys = Object.keys(sectionTitles);
  const idx = navKeys.indexOf(sec);
  if (idx >= 0) document.querySelectorAll('.nav-item')[idx]?.classList.add('active');
  // Scroll calendar to today on switch
  if (sec === 'calendar') setTimeout(scrollToToday, 150);
}

// ── Drawer ───────────────────────────────────────────────────────
function openDrawer(roomId = '', date = '', opts = {}) {
  document.getElementById('drawerOverlay').classList.add('open');
  document.getElementById('bookingDrawer').classList.add('open');
  document.body.style.overflow = 'hidden';
  setBkType('guest');
  // Reset to fresh "add" state by default
  document.getElementById('drawerAction').value = 'add_booking';
  document.getElementById('drawer-id').value    = '';
  document.getElementById('drawerSubmitBtn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save & Notify';

  // Quick-direct mode: prefill source=direct, today + tomorrow, scroll-friendly
  if (opts.quick) {
    const today = new Date();
    const tmrw  = new Date(today); tmrw.setDate(tmrw.getDate() + 1);
    const fmt   = d => d.toISOString().split('T')[0];
    document.getElementById('drawer-checkin').value  = fmt(today);
    document.getElementById('drawer-checkout').value = fmt(tmrw);
    const srcEl = document.getElementById('drawer-source');
    if (srcEl) srcEl.value = 'direct';
    document.getElementById('drawerTitle').textContent = 'Quick Direct Booking';
    document.getElementById('drawerSub').textContent   = 'Tonight → tomorrow. Adjust dates if needed.';
    updateNights(); updateAmountHint();
    setTimeout(() => document.getElementById('drawer-name')?.focus(), 220);
    return;
  }
  if (roomId) document.getElementById('drawer-room').value = roomId;
  if (date) {
    document.getElementById('drawer-checkin').value = date;
    const next = new Date(date);
    next.setDate(next.getDate() + 1);
    document.getElementById('drawer-checkout').value = next.toISOString().split('T')[0];
    updateNights(); updateAmountHint();
  }
  setTimeout(() => document.getElementById('drawer-room').focus(), 200);
}

function openQuickDirect() { openDrawer('', '', { quick: true }); }

function editBooking(id) {
  const b = bookingMap[id];
  if (!b) { alert('Booking not found.'); return; }
  // Open drawer in edit mode
  document.getElementById('drawerOverlay').classList.add('open');
  document.getElementById('bookingDrawer').classList.add('open');
  document.body.style.overflow = 'hidden';
  const isBlock = b.source === 'blocked';
  setBkType(isBlock ? 'block' : 'guest');
  document.getElementById('drawerAction').value = 'update_booking';
  document.getElementById('drawer-id').value    = b.id;
  // Return to whichever section we came from (calendar or bookings)
  const currentSec = document.querySelector('.section-pane.active')?.id?.replace('sec-','') || 'bookings';
  document.getElementById('drawer-return-to').value = currentSec;

  document.getElementById('drawerTitle').textContent = (isBlock ? 'Edit Block' : 'Edit Booking') + ' #' + b.id;
  document.getElementById('drawerSub').textContent   = 'Update details, then save.';
  document.getElementById('drawerSubmitBtn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes';

  document.getElementById('drawer-room').value      = b.room_id || '';
  document.getElementById('drawer-checkin').value   = b.check_in || '';
  document.getElementById('drawer-checkout').value  = b.check_out || '';
  document.getElementById('drawer-name').value      = b.guest_name || '';
  const formEl = document.getElementById('drawerForm');
  if (formEl.guest_phone) formEl.guest_phone.value = b.guest_phone || '';
  if (formEl.guest_email) formEl.guest_email.value = b.guest_email || '';
  if (formEl.notes) formEl.notes.value = b.notes || '';
  document.getElementById('drawer-amount').value    = b.amount || '';
  const srcEl = document.getElementById('drawer-source');
  if (srcEl) srcEl.value = b.source || 'manual';
  updateNights(); updateAmountHint();
  setTimeout(() => document.getElementById('drawer-name')?.focus(), 220);
}

function closeDrawer() {
  document.getElementById('drawerOverlay').classList.remove('open');
  document.getElementById('bookingDrawer').classList.remove('open');
  document.body.style.overflow = '';
  document.getElementById('drawerForm').reset();
  document.getElementById('drawerAction').value = 'add_booking';
  document.getElementById('drawer-id').value    = '';
  document.getElementById('drawerTitle').textContent = 'New Booking';
  document.getElementById('drawerSub').textContent   = 'Fill in the details below to confirm a reservation';
  document.getElementById('drawerSubmitBtn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save & Notify';
  document.getElementById('nights-display').style.display = 'none';
  document.getElementById('amount-hint').textContent = '';
}

function setBkType(type) {
  currentBkType = type;
  const isBlock = type === 'block';
  document.getElementById('btnGuest').classList.toggle('active', !isBlock);
  document.getElementById('btnBlock').classList.toggle('active', isBlock);
  document.getElementById('guestSection').style.display = isBlock ? 'none' : '';
  document.getElementById('paymentSection').style.display = isBlock ? 'none' : '';
  document.getElementById('drawerTitle').textContent = isBlock ? 'Block Dates' : 'New Booking';
  document.getElementById('drawerSub').textContent = isBlock
    ? 'Block these dates for maintenance or personal use'
    : 'Fill in the details below to confirm a reservation';
  document.getElementById('drawerSubmitBtn').innerHTML = isBlock
    ? '<i class="fa-solid fa-lock"></i> Block Dates'
    : '<i class="fa-solid fa-floppy-disk"></i> Save & Notify';

  const nameInput = document.getElementById('drawer-name');
  if (isBlock) {
    nameInput.removeAttribute('required');
    nameInput.value = 'Blocked';
    document.getElementById('drawer-source') && (document.getElementById('drawer-source').value = 'blocked');
  } else {
    nameInput.setAttribute('required', '');
    if (nameInput.value === 'Blocked') nameInput.value = '';
  }
}

function updateNights() {
  const ci = document.getElementById('drawer-checkin').value;
  const co = document.getElementById('drawer-checkout').value;
  if (!ci || !co) { document.getElementById('nights-display').style.display = 'none'; return; }
  const n = Math.round((new Date(co) - new Date(ci)) / 86400000);
  if (n <= 0) { document.getElementById('nights-display').style.display = 'none'; return; }
  document.getElementById('nights-display').style.display = 'block';
  document.getElementById('nights-pill').innerHTML = '<i class="fa-solid fa-moon" style="font-size:.7rem;"></i> ' + (n === 1 ? '1 night' : n + ' nights');
}

function updateAmountHint() {
  const rid   = document.getElementById('drawer-room').value;
  const ci    = document.getElementById('drawer-checkin').value;
  const co    = document.getElementById('drawer-checkout').value;
  const hint  = document.getElementById('amount-hint');
  if (!ci || !co || !rid) { hint.textContent = ''; return; }
  const n = Math.round((new Date(co) - new Date(ci)) / 86400000);
  if (n <= 0) { hint.textContent = ''; return; }
  const base = roomPriceMap[rid] ?? 0;
  if (!base) { hint.textContent = ''; return; }
  const suggested = base * n;
  hint.textContent = `Suggested: ₹${base.toLocaleString('en-IN')} × ${n} = ₹${suggested.toLocaleString('en-IN')}`;
  if (!document.getElementById('drawer-amount').value) {
    document.getElementById('drawer-amount').value = suggested;
  }
}

// ── Calendar range toggle ────────────────────────────────────────
function setCalRange(days) {
  document.getElementById('cal-btn-30').classList.toggle('active', days === 30);
  document.getElementById('cal-btn-60').classList.toggle('active', days !== 30);
  const tbl = document.getElementById('gantt-table');
  if (tbl) {
    tbl.classList.toggle('range-30', days === 30);
    tbl.classList.toggle('range-60', days !== 30);
  }
}

function scrollToToday() {
  const el = document.getElementById('gantt-today');
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
}

document.addEventListener('DOMContentLoaded', () => {
  setTimeout(scrollToToday, 180);
});

// ── Bookings search / filter ─────────────────────────────────────
function filterBookings() {
  const q       = document.getElementById('bk-search').value.toLowerCase();
  const room    = document.getElementById('bk-filter-room').value;
  const src     = document.getElementById('bk-filter-src').value;
  const status  = document.getElementById('bk-filter-status').value;
  const dFrom   = document.getElementById('bk-date-from')?.value || '';
  const dTo     = document.getElementById('bk-date-to')?.value   || '';
  const dField  = document.getElementById('bk-date-field')?.value || 'checkin';
  let visible   = 0;
  document.querySelectorAll('#bk-table tbody tr').forEach(row => {
    if (!row.dataset.id) return; // skip empty placeholder row
    const ci = row.dataset.checkin || '';
    const co = row.dataset.checkout || '';
    let dateMatch = true;
    if (dFrom || dTo) {
      const lo = dFrom || '0000-01-01';
      const hi = dTo   || '9999-12-31';
      if (dField === 'checkin')      dateMatch = (ci >= lo && ci <= hi);
      else if (dField === 'checkout') dateMatch = (co >= lo && co <= hi);
      else /* stay overlap */         dateMatch = (ci <= hi && co >= lo);
    }
    const match = (!q      || (row.dataset.search||'').includes(q))
               && (!room   || row.dataset.room === room)
               && (!src    || row.dataset.src === src)
               && (!status || row.dataset.status === status)
               && dateMatch;
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  const cntEl = document.getElementById('bk-result-count');
  const anyFilter = q||room||src||status||dFrom||dTo;
  if (cntEl) cntEl.textContent = anyFilter ? `${visible} result${visible!==1?'s':''}` : '';
}

// Date range presets
function setDateRangePreset(kind) {
  const fromEl = document.getElementById('bk-date-from');
  const toEl   = document.getElementById('bk-date-to');
  if (!fromEl || !toEl) return;
  const today = new Date();
  const fmt   = d => d.toISOString().split('T')[0];
  if (kind === 'clear') {
    fromEl.value = ''; toEl.value = '';
  } else if (kind === 'today') {
    fromEl.value = fmt(today); toEl.value = fmt(today);
  } else if (kind === 'week') {
    const day = today.getDay(); // 0 = Sun
    const monday = new Date(today); monday.setDate(today.getDate() - ((day + 6) % 7));
    const sunday = new Date(monday); sunday.setDate(monday.getDate() + 6);
    fromEl.value = fmt(monday); toEl.value = fmt(sunday);
  } else if (kind === 'month') {
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    const last  = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    fromEl.value = fmt(first); toEl.value = fmt(last);
  } else if (kind === 'next30') {
    const next = new Date(today); next.setDate(today.getDate() + 30);
    fromEl.value = fmt(today); toEl.value = fmt(next);
  }
  filterBookings();
}

// ── Gantt nav: shift past / future ────────────────────────────────
function ganttShift(offsetDays) {
  const url = new URL(window.location.href);
  url.searchParams.set('section', 'calendar');
  if (offsetDays === 0) url.searchParams.delete('gantt_offset');
  else                  url.searchParams.set('gantt_offset', offsetDays);
  window.location.href = url.toString();
}

// ── Bookings sort ────────────────────────────────────────────────
function sortBookings() {
  const sel = document.getElementById('bk-sort');
  if (!sel) return;
  const [field, dir] = sel.value.split('-');
  const mult = dir === 'desc' ? -1 : 1;
  const tbody = document.querySelector('#bk-table tbody');
  if (!tbody) return;
  const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.dataset.id);
  if (!rows.length) return;
  rows.sort((a, b) => {
    let av, bv;
    if (field === 'amount') {
      av = parseFloat(a.dataset.amount) || 0;
      bv = parseFloat(b.dataset.amount) || 0;
      return (av - bv) * mult;
    }
    if (field === 'created') {
      av = parseInt(a.dataset.id) || 0;
      bv = parseInt(b.dataset.id) || 0;
      return (av - bv) * mult;
    }
    av = a.dataset[field] || '';
    bv = b.dataset[field] || '';
    return av.localeCompare(bv) * mult;
  });
  rows.forEach(r => tbody.appendChild(r));
  try { localStorage.setItem('bk-sort', sel.value); } catch (e) {}
}
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('bk-sort');
  if (sel) {
    try { const v = localStorage.getItem('bk-sort'); if (v) sel.value = v; } catch (e) {}
    sortBookings();
  }
});

// ── Copy iCal URL ─────────────────────────────────────────────────
function copyUrl(id, btn) {
  navigator.clipboard.writeText(document.getElementById(id).textContent.trim()).then(() => {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
    setTimeout(() => btn.innerHTML = orig, 2000);
  });
}

// ── Export bookings (CSV) ─────────────────────────────────────────
function exportBookings() {
  const rows = Array.from(document.querySelectorAll('#bk-table tbody tr'))
    .filter(r => r.style.display !== 'none');
  const headers = ['ID','Property','Check-in','Check-out','Guest','Source','Amount','Status'];
  const data = rows.map(r => {
    const cells = r.querySelectorAll('td');
    return [
      cells[0]?.textContent.trim(),
      cells[1]?.textContent.trim(),
      cells[2]?.textContent.trim(),
      cells[3]?.textContent.trim(),
      cells[5]?.textContent.trim(),
      cells[7]?.textContent.trim(),
      cells[8]?.textContent.trim(),
      cells[9]?.textContent.trim(),
    ].map(v => '"' + (v||'').replace(/"/g,'""') + '"').join(',');
  });
  const csv = [headers.join(','), ...data].join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'bookings-' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
}

// ── Refund modal ──────────────────────────────────────────────────
function openRefund(bookingId, paymentId, amount) {
  const existing = document.getElementById('rfModal');
  if (existing) existing.remove();
  const html = `<div id="rfModal" style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(2px);">
    <div style="background:#fff;border-radius:14px;padding:2rem;width:380px;max-width:95vw;box-shadow:0 16px 48px rgba(0,0,0,.2);">
      <h3 style="margin-bottom:1rem;font-family:var(--font-heading);">Issue Refund</h3>
      <form method="POST">
        <input type="hidden" name="action" value="issue_refund">
        <input type="hidden" name="booking_id" value="${bookingId}">
        <input type="hidden" name="payment_id" value="${paymentId}">
        <div class="fld" style="margin-bottom:.75rem;">
          <label>Razorpay Payment ID</label>
          <input type="text" value="${paymentId}" readonly style="background:var(--bg);">
        </div>
        <div class="fld" style="margin-bottom:1rem;">
          <label>Refund Amount (₹)</label>
          <input type="number" name="refund_amount" value="${amount}" min="1" max="${amount}" step="1" required>
        </div>
        <div style="display:flex;gap:.75rem;">
          <button type="submit" class="btn btn-danger" onclick="return confirm('Issue this refund via Razorpay?')"><i class="fa-solid fa-rotate-left"></i> Issue Refund</button>
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('rfModal').remove()">Cancel</button>
        </div>
      </form>
    </div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
}

// ── Sync all external calendars ───────────────────────────────────
function syncAll(btn) {
  btn.disabled = true;
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="fa-solid fa-rotate fa-spin"></i> Syncing…';
  fetch('sync.php?run=1')
    .then(r => r.json())
    .then(data => {
      const lines = (data.results||[]).map(r =>
        `[${(r.platform||'').toUpperCase()}] ${r.room_id}: ` +
        (r.success ? `✓ ${r.imported} new block(s)` : `✗ ${r.error}`)
      ).join('\n');
      alert(lines || 'Sync complete — no changes.');
      location.reload();
    })
    .catch(() => alert('Sync failed. Check the PHP error log.'))
    .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

// ── Keyboard shortcuts ────────────────────────────────────────────
document.addEventListener('keydown', e => {
  const active = document.activeElement;
  if (active && (active.tagName==='INPUT'||active.tagName==='TEXTAREA'||active.tagName==='SELECT')) return;

  const kbModal = document.getElementById('kb-modal');
  if (e.key==='?') { e.preventDefault(); kbModal && kbModal.classList.toggle('open'); return; }
  if (e.key==='Escape') {
    kbModal && kbModal.classList.remove('open');
    closeDrawer();
    return;
  }
  if (e.key==='d'||e.key==='D') goTo('dashboard');
  if (e.key==='c'||e.key==='C') goTo('calendar');
  if (e.key==='b'||e.key==='B') goTo('bookings');
  if (e.key==='n'||e.key==='N') openDrawer();
  if (e.key==='/') {
    e.preventDefault();
    goTo('bookings');
    setTimeout(() => document.getElementById('bk-search')?.focus(), 120);
  }
});
</script>

<?php endif; ?>
</body>
</html>
