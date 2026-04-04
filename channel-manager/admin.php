<?php
/**
 * Kanchi Farm Stay — Channel Manager
 * Professional property management dashboard
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/whatsapp.php';

session_start();

// ── Auth ────────────────────────────────────────────────────
$loginError = '';
if (($_POST['action'] ?? '') === 'login') {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
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

    if ($act === 'add_booking') {
        $rid  = $_POST['room_id'];
        $data = [
            'room_id'    => $rid,
            'room_name'  => ROOM_IDS[$rid] ?? $rid,
            'check_in'   => $_POST['check_in'],
            'check_out'  => $_POST['check_out'],
            'guest_name' => trim($_POST['guest_name'] ?: 'Blocked'),
            'guest_email'=> trim($_POST['guest_email'] ?? ''),
            'guest_phone'=> trim($_POST['guest_phone'] ?? ''),
            'source'     => $_POST['source'] ?? 'manual',
            'amount'     => (float)($_POST['amount'] ?? 0),
            'notes'      => trim($_POST['notes'] ?? ''),
            'status'     => 'confirmed',
        ];
        $id = addBooking($data);
        if ($id) sendWhatsAppNotification(buildBookingMessage(array_merge($data, ['id' => $id])));
        header('Location: admin.php?section=bookings&flash=Booking+added'); exit;
    }
    if ($act === 'delete_booking')  { deleteBooking((int)$_POST['id']);  header('Location: admin.php?section=bookings&flash=Deleted');   exit; }
    if ($act === 'cancel_booking')  { cancelBooking((int)$_POST['id']);  header('Location: admin.php?section=bookings&flash=Cancelled'); exit; }
    if ($act === 'add_calendar') {
        addExternalCalendar($_POST['room_id'], strtolower(trim($_POST['platform'])), trim($_POST['ical_url']));
        header('Location: admin.php?section=channels&flash=Calendar+added'); exit;
    }
    if ($act === 'delete_calendar') { deleteExternalCalendar((int)$_POST['id']); header('Location: admin.php?section=channels&flash=Removed'); exit; }
}

// ── Data ─────────────────────────────────────────────────────
$section = $_GET['section'] ?? 'dashboard';
$flash   = htmlspecialchars($_GET['flash'] ?? '');
$rooms   = ROOM_IDS;

if (!empty($_SESSION['admin_logged_in'])) {
    $allBookings = getAllBookings();
    $extCals     = getExternalCalendars();

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
<title>Channel Manager — Kanchi Farm Stay</title>
<style>
:root {
    --sidebar-bg: #1a2e1a;
    --sidebar-text: #c8dfc8;
    --sidebar-active: #4a7c59;
    --primary: #4a7c59;
    --primary-dark: #2e5c3a;
    --bg: #f0f4f0;
    --card: #ffffff;
    --border: #e2e8e2;
    --text: #1a2e1a;
    --text-muted: #5a7060;
    --danger: #c62828;
    --warn: #e65100;
    --info: #01579b;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
a { color: inherit; text-decoration: none; }

/* ── Login ───────────────────────────────────── */
.login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #1a2e1a 0%, #2e5c3a 100%); }
.login-card { background: #fff; border-radius: 16px; padding: 2.5rem 2.25rem; width: 360px; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
.login-logo { text-align: center; margin-bottom: 1.75rem; }
.login-logo .icon { font-size: 2.5rem; display: block; }
.login-logo h1 { font-size: 1.3rem; font-weight: 700; color: var(--text); margin-top: .4rem; }
.login-logo p { font-size: .82rem; color: var(--text-muted); }
.login-card label { font-size: .8rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: .35rem; }
.login-card input[type=password] { width: 100%; padding: .75rem 1rem; border: 1.5px solid var(--border); border-radius: 8px; font-size: 1rem; margin-bottom: 1rem; transition: border-color .2s; }
.login-card input[type=password]:focus { outline: none; border-color: var(--primary); }
.login-err { background: #fdecea; color: #c62828; border-radius: 7px; padding: .5rem .85rem; font-size: .83rem; margin-bottom: .85rem; }
.btn-login { width: 100%; padding: .8rem; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background .2s; }
.btn-login:hover { background: var(--primary-dark); }

/* ── Layout ──────────────────────────────────── */
.layout { display: flex; min-height: 100vh; }

/* ── Sidebar ─────────────────────────────────── */
.sidebar { width: 240px; background: var(--sidebar-bg); color: var(--sidebar-text); display: flex; flex-direction: column; flex-shrink: 0; }
.sidebar-brand { padding: 1.5rem 1.25rem 1rem; border-bottom: 1px solid rgba(255,255,255,.08); }
.sidebar-brand .name { font-size: 1rem; font-weight: 700; color: #fff; }
.sidebar-brand .sub  { font-size: .72rem; color: #8ab898; margin-top: .15rem; }
.sidebar-nav { padding: 1rem 0; flex: 1; }
.nav-item { display: flex; align-items: center; gap: .75rem; padding: .7rem 1.25rem; font-size: .88rem; font-weight: 500; color: var(--sidebar-text); cursor: pointer; border-left: 3px solid transparent; transition: all .15s; }
.nav-item:hover { background: rgba(255,255,255,.06); color: #fff; }
.nav-item.active { background: rgba(74,124,89,.25); border-left-color: #6abf85; color: #fff; }
.nav-icon { font-size: 1.1rem; width: 22px; text-align: center; }
.sidebar-bottom { padding: 1rem 1.25rem; border-top: 1px solid rgba(255,255,255,.08); font-size: .8rem; }
.sidebar-bottom a { color: #8ab898; }
.sidebar-bottom a:hover { color: #fff; }

/* ── Main ────────────────────────────────────── */
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.topbar { background: var(--card); border-bottom: 1px solid var(--border); padding: .85rem 1.75rem; display: flex; align-items: center; justify-content: space-between; }
.topbar-title { font-size: 1.05rem; font-weight: 700; color: var(--text); }
.topbar-right { display: flex; align-items: center; gap: 1rem; font-size: .83rem; color: var(--text-muted); }
.sync-btn { background: var(--info); color: #fff; border: none; border-radius: 7px; padding: .4rem 1rem; font-size: .82rem; font-weight: 600; cursor: pointer; transition: opacity .2s; }
.sync-btn:hover { opacity: .85; }
.sync-btn:disabled { opacity: .5; cursor: default; }

.content { flex: 1; overflow-y: auto; padding: 1.5rem 1.75rem; }

.flash { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; border-radius: 8px; padding: .65rem 1rem; margin-bottom: 1.25rem; font-size: .88rem; font-weight: 500; }

/* ── Stat cards ─────────────────────────────── */
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
.stat-card { background: var(--card); border-radius: 12px; padding: 1.25rem 1.35rem; box-shadow: 0 1px 4px rgba(0,0,0,.06); border: 1px solid var(--border); }
.stat-card .stat-icon { font-size: 1.6rem; margin-bottom: .6rem; }
.stat-card .stat-val  { font-size: 2rem; font-weight: 800; color: var(--primary-dark); line-height: 1; }
.stat-card .stat-lbl  { font-size: .75rem; color: var(--text-muted); margin-top: .35rem; font-weight: 500; }

/* ── Cards / Panels ──────────────────────────── */
.panel { background: var(--card); border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 1.25rem; overflow: hidden; }
.panel-hd { padding: .9rem 1.35rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.panel-hd h3 { font-size: .92rem; font-weight: 700; }
.panel-hd .sub { font-size: .78rem; color: var(--text-muted); margin-top: .1rem; }
.panel-bd { padding: 1.25rem 1.35rem; }

/* ── Tables ──────────────────────────────────── */
.tbl-wrap { overflow-x: auto; }
table.tbl { width: 100%; border-collapse: collapse; font-size: .85rem; }
.tbl th { background: #f7faf7; font-size: .75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; padding: .6rem 1rem; border-bottom: 1px solid var(--border); text-align: left; white-space: nowrap; }
.tbl td { padding: .65rem 1rem; border-bottom: 1px solid #f3f6f3; vertical-align: middle; }
.tbl tr:last-child td { border-bottom: none; }
.tbl tr:hover td { background: #f7faf7; }
.tbl .room-name { font-weight: 600; }
.tbl .guest { font-weight: 500; }
.tbl .muted { color: var(--text-muted); font-size: .8rem; }

/* ── Badges ──────────────────────────────────── */
.badge { display: inline-block; padding: .2rem .65rem; border-radius: 20px; font-size: .73rem; font-weight: 600; color: #fff; white-space: nowrap; }
.badge-green { background: var(--primary); }
.badge-grey  { background: #78909c; }
.status-confirmed { color: #1b5e20; background: #e8f5e9; padding: .2rem .6rem; border-radius: 12px; font-size: .75rem; font-weight: 600; }
.status-cancelled { color: #b71c1c; background: #fdecea; padding: .2rem .6rem; border-radius: 12px; font-size: .75rem; font-weight: 600; }

/* ── Buttons ─────────────────────────────────── */
.btn { display: inline-flex; align-items: center; gap: .35rem; padding: .45rem 1rem; border-radius: 7px; border: none; font-size: .83rem; font-weight: 600; cursor: pointer; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn-primary { background: var(--primary); color: #fff; }
.btn-danger  { background: #c62828; color: #fff; padding: .3rem .75rem; font-size: .78rem; }
.btn-warn    { background: #e65100; color: #fff; padding: .3rem .75rem; font-size: .78rem; }
.btn-copy    { background: var(--primary); color: #fff; padding: .35rem .9rem; }

/* ── Forms ───────────────────────────────────── */
.form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(185px, 1fr)); gap: .8rem; }
.form-grid.wide-last > :last-child { grid-column: 1 / -1; }
.fld label { font-size: .76rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: .3rem; }
.fld input, .fld select, .fld textarea { width: 100%; padding: .55rem .85rem; border: 1.5px solid var(--border); border-radius: 7px; font-size: .86rem; color: var(--text); background: #fff; transition: border-color .15s; }
.fld input:focus, .fld select:focus, .fld textarea:focus { outline: none; border-color: var(--primary); }
.fld textarea { resize: vertical; min-height: 56px; }

/* ── Platform breakdown ──────────────────────── */
.platform-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: .75rem; }
.platform-card { border-radius: 10px; padding: .9rem 1rem; color: #fff; }
.platform-card .pc-count { font-size: 1.8rem; font-weight: 800; line-height: 1; }
.platform-card .pc-name  { font-size: .75rem; font-weight: 500; margin-top: .3rem; opacity: .9; }

/* ── Arrivals list ───────────────────────────── */
.arrival-list { display: flex; flex-direction: column; gap: .6rem; }
.arrival-item { display: flex; align-items: center; gap: .85rem; padding: .65rem .9rem; background: #f7faf7; border-radius: 8px; border: 1px solid var(--border); }
.arrival-date { font-size: .72rem; font-weight: 700; color: var(--primary-dark); background: #d7eedd; padding: .25rem .5rem; border-radius: 5px; white-space: nowrap; }
.arrival-info { flex: 1; min-width: 0; }
.arrival-name { font-size: .88rem; font-weight: 600; }
.arrival-sub  { font-size: .76rem; color: var(--text-muted); }

/* ── Gantt calendar ──────────────────────────── */
.gantt-wrap { overflow-x: auto; overflow-y: visible; border-radius: 10px; border: 1px solid var(--border); }
.gantt-table { border-collapse: collapse; min-width: 100%; font-size: .8rem; }
.gantt-room-col { width: 150px; min-width: 150px; }
.gantt-day-col  { min-width: 36px; width: 36px; }
.gantt-table thead th { background: #f7faf7; border-bottom: 1px solid var(--border); border-right: 1px solid #eef2ee; padding: 0; }
.gantt-hdr-room { padding: .6rem .9rem; text-align: left; font-size: .76rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
.gantt-hdr-day { padding: .35rem .1rem; text-align: center; }
.gantt-hdr-day .d-num { font-size: .78rem; font-weight: 700; color: var(--text); display: block; }
.gantt-hdr-day .d-dow { font-size: .65rem; color: var(--text-muted); display: block; }
.gantt-hdr-day.today-col { background: #e8f5e9; }
.gantt-hdr-day.today-col .d-num { color: var(--primary-dark); }
.gantt-hdr-day.weekend-col { background: #fafbfa; }

.gantt-table tbody td { border-right: 1px solid #eef2ee; border-bottom: 1px solid #eef2ee; padding: 0; height: 44px; }
.gantt-room-label { padding: .5rem .9rem; font-size: .82rem; font-weight: 700; white-space: nowrap; background: #fafbfa; color: var(--text); position: sticky; left: 0; z-index: 2; border-right: 2px solid var(--border); }
.gantt-day-free { background: #fff; }
.gantt-day-free.today-day { background: #f0faf3; }
.gantt-day-free.weekend-day { background: #fafbfa; }
.gantt-booking { padding: 0 .5rem; vertical-align: middle; position: relative; }
.gantt-bk-inner { height: 28px; border-radius: 4px; display: flex; align-items: center; padding: 0 .5rem; overflow: hidden; white-space: nowrap; }
.gantt-bk-inner .bk-guest { font-size: .72rem; font-weight: 600; color: rgba(255,255,255,.95); overflow: hidden; text-overflow: ellipsis; }
.gantt-bk-inner .bk-src   { font-size: .62rem; color: rgba(255,255,255,.75); margin-left: .35rem; }
.gantt-day-past { background: #f8f8f8; }
.gantt-day-past.booked-past { background: #f3f3f3; }

/* ── Gantt legend ────────────────────────────── */
.gantt-legend { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .85rem; }
.gl-item { display: flex; align-items: center; gap: .35rem; font-size: .76rem; color: var(--text-muted); }
.gl-dot { width: 12px; height: 12px; border-radius: 3px; }

/* ── iCal URLs ───────────────────────────────── */
.ical-list { display: flex; flex-direction: column; gap: .85rem; }
.ical-row { display: flex; align-items: center; gap: .85rem; flex-wrap: wrap; }
.ical-room-lbl { font-size: .83rem; font-weight: 700; min-width: 155px; }
.ical-url-box { flex: 1; font-size: .76rem; background: #f7faf7; border: 1px solid var(--border); border-radius: 7px; padding: .45rem .8rem; color: var(--text-muted); word-break: break-all; font-family: monospace; }

/* ── Search bar ──────────────────────────────── */
.search-bar { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.search-bar input, .search-bar select { padding: .5rem .85rem; border: 1.5px solid var(--border); border-radius: 7px; font-size: .85rem; }
.search-bar input { min-width: 220px; }
.search-bar input:focus, .search-bar select:focus { outline: none; border-color: var(--primary); }

/* ── Dashboard grid ──────────────────────────── */
.dash-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.25rem; }
@media(max-width:900px) { .dash-grid { grid-template-columns: 1fr; } .stats-row { grid-template-columns: repeat(2,1fr); } .sidebar { display: none; } }

/* ── Channels table ──────────────────────────── */
.status-synced { color: #1b5e20; font-size: .78rem; }
.status-never  { color: var(--warn); font-size: .78rem; }

/* ── How-to box ──────────────────────────────── */
.howto { background: #fffde7; border: 1px solid #ffe082; border-radius: 10px; padding: 1.1rem 1.25rem; font-size: .85rem; line-height: 1.7; }
.howto h4 { font-size: .9rem; margin-bottom: .5rem; color: var(--text); }
.howto code { background: #fff8e1; border: 1px solid #ffe082; border-radius: 4px; padding: .1rem .4rem; font-size: .82rem; }
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
      'dashboard' => ['📊', 'Dashboard'],
      'calendar'  => ['📅', 'Calendar'],
      'bookings'  => ['📋', 'Bookings'],
      'channels'  => ['🔗', 'Channels'],
      'export'    => ['📤', 'iCal Export'],
    ];
    foreach ($navItems as $key => [$icon, $label]):
    ?>
      <div class="nav-item <?= $section===$key?'active':'' ?>" onclick="goTo('<?= $key ?>')">
        <span class="nav-icon"><?= $icon ?></span>
        <?= $label ?>
      </div>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-bottom">
    <a href="/">← View website</a><br>
    <a href="admin.php?action=logout" style="margin-top:.35rem;display:block;">Sign out</a>
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
                        <button class="btn btn-warn">Cancel</button>
                      </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;margin-left:4px;" onsubmit="return confirm('Permanently delete this record?')">
                      <input type="hidden" name="action" value="delete_booking">
                      <input type="hidden" name="id" value="<?= $b['id'] ?>">
                      <button class="btn btn-danger">Delete</button>
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

<style>
.section-pane { display: none; }
.section-pane.active { display: block; }
</style>

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
