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

// ── Auth ────────────────────────────────────────────────────
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

// ── POST handlers (authenticated) ───────────────────────────
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

// ── Data ─────────────────────────────────────────────────────
$section    = $_GET['section'] ?? 'dashboard';
$flash      = htmlspecialchars($_GET['flash'] ?? '');
$rooms      = ROOM_IDS;
$propertyId = getCurrentPropertyId();

if (!empty($_SESSION['admin_logged_in'])) {
    $allBookings = getAllBookings([], $propertyId);
    $extCals     = getExternalCalendars($propertyId);

    // ── Stats
    $confirmed   = array_filter($allBookings, fn($b) => $b['status'] === 'confirmed');
    $upcoming    = array_filter($confirmed,   fn($b) => $b['check_out'] >= date('Y-m-d'));
    $thisMonth   = array_filter($confirmed,   fn($b) => substr($b['check_in'],0,7) === date('Y-m'));

    $totalRev    = array_sum(array_column(array_filter($thisMonth, fn($b) => $b['source']==='direct' || $b['source']==='razorpay'), 'amount'));

    // Occupancy: confirmed nights / (rooms × 30 days) × 100
    $totalNights = 0;
    foreach ($confirmed as $b) {
        $n = (int)ceil((strtotime($b['check_out']) - strtotime($b['check_in'])) / 86400);
        $totalNights += max(0, $n);
    }
    $occupancy = count($rooms) > 0 ? min(100, round($totalNights / (count($rooms) * 30) * 100)) : 0;

    // Platform breakdown
    $byPlatform = [];
    foreach ($confirmed as $b) {
        $src = $b['source'] ?? 'direct';
        $byPlatform[$src] = ($byPlatform[$src] ?? 0) + 1;
    }
    arsort($byPlatform);

    // Arrivals next 7 days
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    $arrivals  = array_filter($upcoming, fn($b) => $b['check_in'] <= $nextWeek && $b['check_in'] >= date('Y-m-d'));
    usort($arrivals, fn($a,$b) => strcmp($a['check_in'], $b['check_in']));

    // Departures next 7 days
    $departures = array_filter($upcoming, fn($b) => $b['check_out'] <= $nextWeek && $b['check_out'] >= date('Y-m-d'));

    // ── Gantt: 60-day rolling view
    $ganttDays = 60;
    $ganttStart = new DateTime('today');
    $ganttDates = [];
    for ($i = 0; $i < $ganttDays; $i++) {
        $d = clone $ganttStart;
        $d->modify("+{$i} days");
        $ganttDates[] = $d->format('Y-m-d');
    }
    $bookingsByRoom = [];
    foreach ($rooms as $rid => $_) $bookingsByRoom[$rid] = [];
    foreach ($confirmed as $b) {
        if (isset($bookingsByRoom[$b['room_id']])) {
            $bookingsByRoom[$b['room_id']][] = $b;
        }
    }

    // iCal export URLs
    $icalUrls = [];
    foreach ($rooms as $rid => $_) {
        $icalUrls[$rid] = SITE_URL . '/channel-manager/ical-export.php?room=' . urlencode($rid) . '&token=' . urlencode(ICAL_TOKEN);
    }
}

// ── Helpers ──────────────────────────────────────────────────
function sourceColor(string $s): string {
    return match(strtolower(trim($s))) {
        'airbnb'            => '#FF5A5F',
        'booking.com','booking' => '#003580',
        'agoda'             => '#EB1A23',
        'makemytrip'        => '#E8262D',
        'direct','razorpay' => '#2e7d32',
        'manual'            => '#6d4c41',
        'blocked'           => '#78909c',
        default             => '#546e7a',
    };
}
function sourceName(string $s): string {
    return match(strtolower(trim($s))) {
        'booking.com','booking' => 'Booking.com',
        'airbnb'            => 'Airbnb',
        'agoda'             => 'Agoda',
        'makemytrip'        => 'MakeMyTrip',
        'direct'            => 'Direct',
        'razorpay'          => 'Direct (Razorpay)',
        'manual'            => 'Manual / Phone',
        'blocked'           => 'Blocked',
        default             => ucfirst($s),
    };
}
function badge(string $s): string {
    $c = sourceColor($s);
    return "<span class='badge' style='background:$c'>".htmlspecialchars(sourceName($s))."</span>";
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin-styles.css">
<style>/* admin.php local overrides */
</style>
</head>
<body>

<?php if (empty($_SESSION['admin_logged_in'])): /* ═══ LOGIN ═══ */ ?>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <span class="icon">🏡</span>
      <h1>Channel Manager</h1>
      <p>Kanchi Farm Stay</p>
    </div>
    <?php if ($loginError): ?>
      <div class="login-err"><?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="login">
      <label>Admin Password</label>
      <input type="password" name="password" autofocus autocomplete="current-password" placeholder="Enter password">
      <button type="submit" class="btn-login">Sign In</button>
    </form>
  </div>
</div>

<?php else: /* ═══ DASHBOARD ═══ */ ?>
<div class="layout">

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="name">Kanchi Farm Stay</div>
    <div class="sub">Channel Manager</div>
  </div>
  <nav class="sidebar-nav">
    <?php
    $navItems = [
      'dashboard' => ['📊', 'Dashboard',   null],
      'calendar'  => ['📅', 'Calendar',    null],
      'bookings'  => ['📋', 'Bookings',    null],
      'channels'  => ['🔗', 'Channels',    'admin-channels.php'],
      'export'    => ['📤', 'iCal Export', null],
      'pricing'   => ['💡', 'AI Pricing',  'admin-pricing.php'],
      'whatsapp'  => ['💬', 'WhatsApp',    'admin-whatsapp.php'],
      'revenue'   => ['📈', 'Revenue',     'admin-revenue.php'],
      'reputation'=> ['⭐', 'Reputation',  'admin-reputation.php'],
      'settings'  => ['⚙️',  'Settings',    'admin-settings.php'],
    ];
    foreach ($navItems as $key => [$icon, $label, $externalHref]):
      if ($externalHref):
    ?>
      <a href="<?= $externalHref ?>" class="nav-item">
        <span class="nav-icon"><?= $icon ?></span>
        <?= $label ?>
      </a>
    <?php else: ?>
      <div class="nav-item <?= $section===$key?'active':'' ?>" onclick="goTo('<?= $key ?>')">
        <span class="nav-icon"><?= $icon ?></span>
        <?= $label ?>
      </div>
    <?php endif; endforeach; ?>
  </nav>
  <div class="sidebar-bottom">
    <a href="/">← View website</a>
    <a href="admin.php?action=logout" style="margin-top:.4rem;display:block;color:#e57373;">Sign out</a>
  </div>
</aside>

<!-- ── Main ── -->
<div class="main">
  <div class="topbar">
    <div>
      <div class="topbar-title" id="topbar-title">Dashboard</div>
    </div>
    <div class="topbar-right">
      <span><?= date('D, d M Y') ?></span>
      <button class="sync-btn" id="sync-btn" onclick="syncAll(this)">↻ Sync All Calendars</button>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?><div class="flash">✓ <?= $flash ?></div><?php endif; ?>

    <!-- ════════════ DASHBOARD ════════════ -->
    <div id="sec-dashboard" class="section-pane <?= $section==='dashboard'?'active':'' ?>">
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon">📋</div>
          <div class="stat-val"><?= count($confirmed) ?></div>
          <div class="stat-lbl">Total Confirmed Bookings</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">🛎</div>
          <div class="stat-val"><?= count($upcoming) ?></div>
          <div class="stat-lbl">Upcoming Stays</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">💰</div>
          <div class="stat-val">₹<?= number_format($totalRev) ?></div>
          <div class="stat-lbl">Direct Revenue This Month</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">📈</div>
          <div class="stat-val"><?= $occupancy ?>%</div>
          <div class="stat-lbl">Avg Occupancy (30 days)</div>
        </div>
      </div>

      <div class="dash-grid">
        <div>
          <!-- Arrivals -->
          <div class="panel">
            <div class="panel-hd">
              <div><h3>Upcoming Arrivals</h3><div class="sub">Next 7 days</div></div>
              <span class="badge badge-green"><?= count($arrivals) ?> guests</span>
            </div>
            <div class="panel-bd">
              <?php if (empty($arrivals)): ?>
                <p style="color:var(--text-muted);font-size:.85rem;">No arrivals in the next 7 days.</p>
              <?php else: ?>
                <div class="arrival-list">
                  <?php foreach ($arrivals as $a): ?>
                  <div class="arrival-item">
                    <div class="arrival-date"><?= date('d M', strtotime($a['check_in'])) ?></div>
                    <div class="arrival-info">
                      <div class="arrival-name"><?= htmlspecialchars($a['guest_name']) ?></div>
                      <div class="arrival-sub"><?= htmlspecialchars($a['room_name']) ?> · <?= nights($a['check_in'],$a['check_out']) ?> nights · <?= sourceName($a['source']) ?></div>
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
            <div class="panel-hd"><h3>Recent Bookings</h3></div>
            <div class="tbl-wrap">
              <table class="tbl">
                <thead><tr><th>Room</th><th>Guest</th><th>Dates</th><th>Source</th><th>Amount</th></tr></thead>
                <tbody>
                  <?php foreach (array_slice($allBookings, 0, 8) as $b): ?>
                  <tr>
                    <td class="room-name"><?= htmlspecialchars($b['room_name']) ?></td>
                    <td class="guest"><?= htmlspecialchars($b['guest_name']) ?></td>
                    <td><span class="muted"><?= $b['check_in'] ?> → <?= $b['check_out'] ?></span></td>
                    <td><?= badge($b['source']) ?></td>
                    <td><?= $b['amount'] > 0 ? '₹'.number_format($b['amount']) : '—' ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($allBookings)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:1.5rem;">No bookings yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Platform breakdown -->
        <div>
          <div class="panel">
            <div class="panel-hd"><h3>Bookings by Platform</h3></div>
            <div class="panel-bd">
              <?php if (empty($byPlatform)): ?>
                <p style="color:var(--text-muted);font-size:.85rem;">No bookings yet.</p>
              <?php else: ?>
                <div class="platform-grid">
                  <?php foreach ($byPlatform as $src => $cnt): ?>
                    <div class="platform-card" style="background:<?= sourceColor($src) ?>">
                      <div class="pc-count"><?= $cnt ?></div>
                      <div class="pc-name"><?= htmlspecialchars(sourceName($src)) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div style="margin-top:1.25rem;">
                  <?php
                  $total = array_sum($byPlatform);
                  foreach ($byPlatform as $src => $cnt):
                    $pct = $total > 0 ? round($cnt / $total * 100) : 0;
                  ?>
                  <div style="margin-bottom:.6rem;">
                    <div style="display:flex;justify-content:space-between;font-size:.78rem;margin-bottom:.25rem;">
                      <span><?= htmlspecialchars(sourceName($src)) ?></span>
                      <span style="color:var(--text-muted);"><?= $cnt ?> (<?= $pct ?>%)</span>
                    </div>
                    <div style="height:7px;background:#eef2ee;border-radius:4px;overflow:hidden;">
                      <div style="height:100%;width:<?= $pct ?>%;background:<?= sourceColor($src) ?>;border-radius:4px;"></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Per-room occupancy -->
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
              <div style="margin-bottom:.65rem;">
                <div style="display:flex;justify-content:space-between;font-size:.78rem;margin-bottom:.25rem;">
                  <span><?= htmlspecialchars($rname) ?></span>
                  <span style="color:var(--text-muted);"><?= $rOcc ?>%</span>
                </div>
                <div style="height:8px;background:#eef2ee;border-radius:4px;overflow:hidden;">
                  <div style="height:100%;width:<?= $rOcc ?>%;background:var(--primary);border-radius:4px;"></div>
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
        <div class="panel-hd">
          <div><h3>Multi-Property Calendar</h3><div class="sub">60-day view — scroll right for more dates</div></div>
        </div>
        <div class="panel-bd">
          <div class="gantt-legend">
            <?php foreach (['direct'=>'Direct','airbnb'=>'Airbnb','booking.com'=>'Booking.com','agoda'=>'Agoda','makemytrip'=>'MakeMyTrip','manual'=>'Manual/Phone','blocked'=>'Blocked'] as $src => $lbl): ?>
              <div class="gl-item"><div class="gl-dot" style="background:<?= sourceColor($src) ?>"></div><?= $lbl ?></div>
            <?php endforeach; ?>
            <div class="gl-item"><div class="gl-dot" style="background:#e8f5e9;border:1px solid #a5d6a7;"></div>Today</div>
          </div>

          <div class="gantt-wrap">
            <table class="gantt-table">
              <thead>
                <tr>
                  <th class="gantt-room-col"><div class="gantt-hdr-room">Property</div></th>
                  <?php foreach ($ganttDates as $gd):
                    $dow = date('D', strtotime($gd));
                    $isToday = $gd === date('Y-m-d');
                    $isWkend = in_array($dow, ['Sat','Sun']);
                  ?>
                    <th class="gantt-day-col <?= $isToday?'today-col':($isWkend?'weekend-col':'') ?>">
                      <div class="gantt-hdr-day">
                        <span class="d-num"><?= (int)date('d', strtotime($gd)) ?></span>
                        <span class="d-dow"><?= substr($dow,0,2) ?></span>
                      </div>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rooms as $rid => $rname):
                  $roomBks = $bookingsByRoom[$rid] ?? [];
                  $i = 0;
                ?>
                <tr>
                  <td class="gantt-room-label"><?= htmlspecialchars($rname) ?></td>
                  <?php
                  while ($i < count($ganttDates)):
                    $gd   = $ganttDates[$i];
                    $bk   = bookingOnDay($roomBks, $gd);
                    $dow  = date('D', strtotime($gd));
                    $isToday = $gd === date('Y-m-d');
                    $isPast  = $gd < date('Y-m-d');
                    $isWkend = in_array($dow, ['Sat','Sun']);

                    if ($bk):
                      // Calculate colspan (how many days this booking spans within our view)
                      $span = 1;
                      for ($j = $i+1; $j < count($ganttDates); $j++) {
                          if ($ganttDates[$j] >= $bk['check_out']) break;
                          $span++;
                      }
                      $isFirst = ($gd === $bk['check_in'] || $i === 0);
                      $clr = sourceColor($bk['source']);
                      $tip = htmlspecialchars("{$bk['guest_name']} | ".sourceName($bk['source'])." | {$bk['check_in']} → {$bk['check_out']}");
                  ?>
                      <td colspan="<?= $span ?>" class="gantt-booking <?= $isPast?'booked-past':'' ?>" title="<?= $tip ?>">
                        <?php if ($isFirst): ?>
                          <div class="gantt-bk-inner" style="background:<?= $clr ?>;opacity:<?= $isPast?.5:1 ?>;">
                            <span class="bk-guest"><?= htmlspecialchars(substr($bk['guest_name'],0,14)) ?></span>
                            <span class="bk-src"><?= htmlspecialchars(strtoupper(substr($bk['source'],0,3))) ?></span>
                          </div>
                        <?php else: ?>
                          <div class="gantt-bk-inner" style="background:<?= $clr ?>;opacity:<?= $isPast?.5:1 ?>;"></div>
                        <?php endif; ?>
                      </td>
                  <?php
                      $i += $span;
                    else:
                      $cls = 'gantt-day-free';
                      if ($isToday)  $cls .= ' today-day';
                      if ($isWkend)  $cls .= ' weekend-day';
                      if ($isPast)   $cls = 'gantt-day-past';
                  ?>
                      <td class="<?= $cls ?>"></td>
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

      <!-- Add Booking -->
      <div class="panel">
        <div class="panel-hd"><h3>Add / Block Dates</h3></div>
        <div class="panel-bd">
          <form method="POST">
            <input type="hidden" name="action" value="add_booking">
            <div class="form-grid wide-last">
              <div class="fld">
                <label>Property *</label>
                <select name="room_id" required>
                  <?php foreach ($rooms as $rid => $rn): ?><option value="<?= $rid ?>"><?= htmlspecialchars($rn) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="fld"><label>Check-in *</label><input type="date" name="check_in" required></div>
              <div class="fld"><label>Check-out *</label><input type="date" name="check_out" required></div>
              <div class="fld"><label>Guest Name</label><input type="text" name="guest_name" placeholder="Ravi Kumar"></div>
              <div class="fld"><label>Phone</label><input type="tel" name="guest_phone" placeholder="+91 98765 43210"></div>
              <div class="fld"><label>Email</label><input type="email" name="guest_email" placeholder="guest@email.com"></div>
              <div class="fld"><label>Amount (₹)</label><input type="number" name="amount" min="0" step="1" placeholder="0"></div>
              <div class="fld">
                <label>Source</label>
                <select name="source">
                  <option value="manual">Manual / Phone</option>
                  <option value="direct">Direct (Website)</option>
                  <option value="airbnb">Airbnb</option>
                  <option value="booking.com">Booking.com</option>
                  <option value="agoda">Agoda</option>
                  <option value="makemytrip">MakeMyTrip</option>
                  <option value="blocked">Block (maintenance)</option>
                </select>
              </div>
              <div class="fld"><label>Notes</label><textarea name="notes" placeholder="Optional notes..."></textarea></div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:1rem;">+ Add Booking &amp; Notify WhatsApp</button>
          </form>
        </div>
      </div>

      <!-- Bookings table -->
      <div class="panel">
        <div class="panel-hd">
          <h3>All Bookings</h3>
          <span style="font-size:.8rem;color:var(--text-muted);"><?= count($allBookings) ?> total</span>
        </div>
        <div class="panel-bd" style="padding-bottom:0;">
          <div class="search-bar">
            <input type="text" id="bk-search" placeholder="Search guest, room, ref…" oninput="filterBookings()">
            <select id="bk-filter-room" onchange="filterBookings()">
              <option value="">All Properties</option>
              <?php foreach ($rooms as $rid => $rn): ?><option value="<?= $rid ?>"><?= htmlspecialchars($rn) ?></option><?php endforeach; ?>
            </select>
            <select id="bk-filter-src" onchange="filterBookings()">
              <option value="">All Sources</option>
              <?php foreach (['airbnb','booking.com','agoda','makemytrip','direct','manual','blocked'] as $s): ?>
                <option value="<?= $s ?>"><?= sourceName($s) ?></option>
              <?php endforeach; ?>
            </select>
            <select id="bk-filter-status" onchange="filterBookings()">
              <option value="">All Statuses</option>
              <option value="confirmed">Confirmed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
        <div class="tbl-wrap">
          <table class="tbl" id="bk-table">
            <thead>
              <tr>
                <th>#</th><th>Property</th><th>Check-in</th><th>Check-out</th><th>Nights</th>
                <th>Guest</th><th>Phone</th><th>Source</th><th>Amount</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($allBookings)): ?>
                <tr><td colspan="11" style="text-align:center;padding:2rem;color:var(--text-muted);">No bookings yet.</td></tr>
              <?php else: ?>
                <?php foreach ($allBookings as $b):
                  $n = nights($b['check_in'], $b['check_out']);
                ?>
                <tr data-room="<?= $b['room_id'] ?>" data-src="<?= htmlspecialchars($b['source']) ?>" data-status="<?= $b['status'] ?>"
                    data-search="<?= strtolower(htmlspecialchars($b['guest_name'].' '.$b['room_name'].' '.$b['booking_ref'])) ?>">
                  <td class="muted"><?= $b['id'] ?></td>
                  <td class="room-name"><?= htmlspecialchars($b['room_name']) ?></td>
                  <td><?= $b['check_in'] ?></td>
                  <td><?= $b['check_out'] ?></td>
                  <td><?= $n ?></td>
                  <td class="guest">
                    <?= htmlspecialchars($b['guest_name']) ?>
                    <?php if ($b['guest_email']): ?><div class="muted"><?= htmlspecialchars($b['guest_email']) ?></div><?php endif; ?>
                  </td>
                  <td class="muted"><?= htmlspecialchars($b['guest_phone']) ?></td>
                  <td><?= badge($b['source']) ?></td>
                  <td><?= $b['amount'] > 0 ? '₹'.number_format($b['amount']) : '—' ?></td>
                  <td>
                    <?php if ($b['status']==='confirmed'): ?>
                      <span class="status-confirmed">Confirmed</span>
                    <?php else: ?>
                      <span class="status-cancelled">Cancelled</span>
                    <?php endif; ?>
                  </td>
                  <td style="white-space:nowrap;">
                    <?php if ($b['status']==='confirmed'): ?>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this booking?')">
                        <input type="hidden" name="action" value="cancel_booking">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <button class="btn btn-warn btn-sm">Cancel</button>
                      </form>
                    <?php endif; ?>
                    <?php if (!empty($b['booking_ref']) && in_array($b['source'] ?? '', ['direct','razorpay'])): ?>
                      <button class="btn btn-info btn-sm" style="margin-left:3px;" onclick="openRefund(<?= $b['id'] ?>,'<?= htmlspecialchars($b['booking_ref']) ?>',<?= $b['amount_paid'] ?? $b['amount'] ?>)">Refund</button>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;margin-left:3px;" onsubmit="return confirm('Permanently delete this record?')">
                      <input type="hidden" name="action" value="delete_booking">
                      <input type="hidden" name="id" value="<?= $b['id'] ?>">
                      <button class="btn btn-danger btn-sm">Delete</button>
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

      <!-- Add channel -->
      <div class="panel">
        <div class="panel-hd"><h3>Add External Calendar Feed</h3></div>
        <div class="panel-bd">
          <p style="font-size:.84rem;color:var(--text-muted);margin-bottom:1rem;">
            Paste the iCal export URL from Airbnb, Booking.com, Agoda etc.
            Click <strong>Sync All</strong> to import their blocked dates into this calendar.
          </p>
          <form method="POST">
            <input type="hidden" name="action" value="add_calendar">
            <div class="form-grid">
              <div class="fld">
                <label>Property *</label>
                <select name="room_id" required>
                  <?php foreach ($rooms as $rid => $rn): ?><option value="<?= $rid ?>"><?= htmlspecialchars($rn) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="fld">
                <label>Platform *</label>
                <select name="platform">
                  <option value="airbnb">Airbnb</option>
                  <option value="booking.com">Booking.com</option>
                  <option value="agoda">Agoda</option>
                  <option value="makemytrip">MakeMyTrip</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="fld" style="grid-column:span 2">
                <label>iCal URL (from the platform's calendar export) *</label>
                <input type="url" name="ical_url" placeholder="https://www.airbnb.com/calendar/ical/..." required>
              </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:.9rem;">Add Calendar Feed</button>
          </form>
        </div>
      </div>

      <!-- Channels table -->
      <div class="panel">
        <div class="panel-hd">
          <h3>Connected Channels</h3>
          <button class="sync-btn" onclick="syncAll(this)">↻ Sync All Now</button>
        </div>
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Property</th><th>Platform</th><th>iCal URL</th><th>Last Synced</th><th></th></tr></thead>
            <tbody>
              <?php if (empty($extCals)): ?>
                <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No channels connected yet.</td></tr>
              <?php else: ?>
                <?php foreach ($extCals as $ec): ?>
                <tr>
                  <td class="room-name"><?= htmlspecialchars($rooms[$ec['room_id']] ?? $ec['room_id']) ?></td>
                  <td><?= badge($ec['platform']) ?></td>
                  <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.78rem;">
                    <a href="<?= htmlspecialchars($ec['ical_url']) ?>" target="_blank" title="<?= htmlspecialchars($ec['ical_url']) ?>" style="color:var(--text-muted);"><?= htmlspecialchars($ec['ical_url']) ?></a>
                  </td>
                  <td>
                    <?php if ($ec['last_synced']): ?>
                      <span class="status-synced">✓ <?= $ec['last_synced'] ?></span>
                    <?php else: ?>
                      <span class="status-never">Never synced</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" onsubmit="return confirm('Remove this channel?')">
                      <input type="hidden" name="action" value="delete_calendar">
                      <input type="hidden" name="id" value="<?= $ec['id'] ?>">
                      <button class="btn btn-danger">Remove</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Cron setup -->
      <div class="panel">
        <div class="panel-hd"><h3>Automatic Sync (Cron Job)</h3></div>
        <div class="panel-bd">
          <div class="howto">
            <h4>Set up hourly auto-sync on Hostinger</h4>
            1. Log into <strong>hPanel → Advanced → Cron Jobs</strong><br>
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
        <div class="panel-hd"><h3>Your iCal Export URLs</h3></div>
        <div class="panel-bd">
          <p style="font-size:.84rem;color:var(--text-muted);margin-bottom:1.25rem;">
            Copy each URL and paste it into the corresponding platform as an <strong>imported calendar</strong>.
            The platform will regularly fetch this feed and block the dates you've already booked directly.
          </p>
          <div class="ical-list">
            <?php foreach ($rooms as $rid => $rn): ?>
            <div class="ical-row">
              <span class="ical-room-lbl"><?= htmlspecialchars($rn) ?></span>
              <span class="ical-url-box" id="url-<?= $rid ?>"><?= htmlspecialchars($icalUrls[$rid]) ?></span>
              <button class="btn btn-copy" onclick="copyUrl('url-<?= $rid ?>', this)">Copy</button>
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
            Hosting → Your Listings → Select listing → <strong>Availability → Sync calendars → Import calendar</strong> → paste the URL above → Save<br><br>
            <h4>Booking.com</h4>
            Extranet → <strong>Calendar → Sync calendars → Add a source</strong> → paste the URL above → Save<br><br>
            <h4>Agoda / MakeMyTrip</h4>
            Look for <strong>Calendar Sync</strong> or <strong>iCal Import</strong> in their property extranet and paste the matching URL.<br><br>
            <strong>Then add their export URL</strong> under the Channels tab so their bookings block your calendar too.
          </div>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->

<script>
const sectionTitles = { dashboard:'Dashboard', calendar:'Multi-Property Calendar', bookings:'Bookings', channels:'Channels', export:'iCal Export' };

function goTo(sec) {
    document.querySelectorAll('.section-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('sec-' + sec).classList.add('active');
    document.querySelectorAll('.nav-item')[Object.keys(sectionTitles).indexOf(sec)].classList.add('active');
    document.getElementById('topbar-title').textContent = sectionTitles[sec];
    history.replaceState(null, '', 'admin.php?section=' + sec);
}

// Bookings search/filter
function filterBookings() {
    const q      = document.getElementById('bk-search').value.toLowerCase();
    const room   = document.getElementById('bk-filter-room').value;
    const src    = document.getElementById('bk-filter-src').value;
    const status = document.getElementById('bk-filter-status').value;
    document.querySelectorAll('#bk-table tbody tr').forEach(row => {
        const matchSearch = !q      || (row.dataset.search || '').includes(q);
        const matchRoom   = !room   || row.dataset.room === room;
        const matchSrc    = !src    || row.dataset.src === src;
        const matchStatus = !status || row.dataset.status === status;
        row.style.display = (matchSearch && matchRoom && matchSrc && matchStatus) ? '' : 'none';
    });
}

// Copy iCal URL
function copyUrl(id, btn) {
    navigator.clipboard.writeText(document.getElementById(id).textContent.trim()).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = orig, 2000);
    });
}

// Refund modal
function openRefund(bookingId, paymentId, amount) {
    const html = `<div id="rfModal" style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;">
      <div style="background:#fff;border-radius:14px;padding:2rem;width:380px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <h3 style="margin-bottom:1rem;">Issue Refund</h3>
        <form method="POST">
          <input type="hidden" name="action" value="issue_refund">
          <input type="hidden" name="booking_id" value="${bookingId}">
          <input type="hidden" name="payment_id" value="${paymentId}">
          <div class="fld" style="margin-bottom:.75rem;">
            <label>Razorpay Payment ID</label>
            <input type="text" value="${paymentId}" readonly style="background:#f5f5f5;">
          </div>
          <div class="fld" style="margin-bottom:1rem;">
            <label>Refund Amount (₹)</label>
            <input type="number" name="refund_amount" value="${amount}" min="1" max="${amount}" step="1" required>
          </div>
          <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Issue this refund via Razorpay?')">Issue Refund</button>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('rfModal').remove()">Cancel</button>
          </div>
        </form>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
}

// Sync all external calendars
function syncAll(btn) {
    btn.disabled = true;
    const orig = btn.textContent;
    btn.textContent = '↻ Syncing…';
    fetch('sync.php?run=1')
        .then(r => r.json())
        .then(data => {
            const lines = data.results.map(r =>
                `[${r.platform.toUpperCase()}] ${r.room_id}: ` +
                (r.success ? `✓ ${r.imported} new block(s) imported` : `✗ ${r.error}`)
            ).join('\n');
            alert(lines || 'Sync complete — no changes.');
            location.reload();
        })
        .catch(() => { alert('Sync failed. Check the PHP error log.'); })
        .finally(() => { btn.disabled = false; btn.textContent = orig; });
}
</script>

<?php endif; ?>
</body>
</html>
