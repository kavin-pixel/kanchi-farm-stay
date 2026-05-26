<?php
require_once __DIR__ . '/admin-shell.php';
requireAdminAuth();
$propertyId = getCurrentPropertyId();

$act = $_POST['action'] ?? '';
if ($act === 'add_calendar') {
    addExternalCalendar($_POST['room_id'], strtolower(trim($_POST['platform'])), trim($_POST['ical_url']), $propertyId, (float)($_POST['commission_pct'] ?? 0));
    header('Location: admin-channels.php?flash=Channel+added'); exit;
}
if ($act === 'delete_calendar') {
    deleteExternalCalendar((int)$_POST['id']);
    header('Location: admin-channels.php?flash=Channel+removed'); exit;
}
if ($act === 'sync_now') {
    require_once __DIR__ . '/sync.php';
    $calId = (int)$_POST['cal_id'];
    $cals  = getExternalCalendars($propertyId);
    foreach ($cals as $cal) {
        if ($cal['id'] === $calId) {
            $res = syncOneCalendar($cal);
            updateCalendarSyncStatus($cal['id'], $res['success'], $res['imported'], $res['error'] ?? '');
            break;
        }
    }
    header('Location: admin-channels.php?flash=Sync+complete'); exit;
}

$extCals  = getExternalCalendars($propertyId);
$stats    = getChannelStats($propertyId, 6);
$icalUrls = [];
foreach (ROOM_IDS as $rid => $_) {
    $icalUrls[$rid] = SITE_URL . '/channel-manager/ical-export.php?room=' . urlencode($rid) . '&token=' . urlencode(ICAL_TOKEN);
}

// OTA Library
$otaLibrary = [
    'Major OTAs' => [
        ['airbnb', 'Airbnb', '#FF5A5F', 'https://www.airbnb.co.in/hosting'],
        ['booking.com', 'Booking.com', '#003580', 'https://extranet.booking.com'],
        ['agoda', 'Agoda', '#EB1A23', 'https://ycs.agoda.com'],
        ['makemytrip', 'MakeMyTrip', '#E8262D', 'https://property.makemytrip.com'],
        ['expedia', 'Expedia', '#FFC72C', 'https://expediapartnercentral.com'],
        ['hotels.com', 'Hotels.com', '#D42B2B', 'https://expediapartnercentral.com'],
        ['tripadvisor', 'TripAdvisor', '#34E0A1', 'https://www.tripadvisor.in/hotel'],
        ['hostelworld', 'Hostelworld', '#FE6D1E', 'https://partner.hostelworld.com'],
    ],
    'Indian OTAs' => [
        ['goibibo', 'Goibibo', '#E03A3E', 'https://partner.goibibo.com'],
        ['yatra', 'Yatra', '#0073CF', 'https://hotelpartners.yatra.com'],
        ['ixigo', 'ixigo', '#FF7700', 'https://hotels.ixigo.com'],
        ['oyo', 'OYO', '#EE2222', 'https://partner.oyorooms.com'],
        ['treebo', 'Treebo', '#2196F3', 'https://treebo.com/partner'],
        ['cleartrip', 'Cleartrip', '#E31837', 'https://cleartrip.com'],
        ['holidayiq', 'HolidayIQ', '#FF6600', 'https://hoteliers.holidayiq.com'],
        ['zostel', 'Zostel', '#F26522', 'https://zostel.com'],
        ['stayuncle', 'StayUncle', '#6600CC', 'https://stayuncle.com'],
        ['fabhotels', 'FabHotels', '#FF8C00', 'https://fabhotels.com/partner'],
        ['tripoto', 'Tripoto', '#00B0FF', 'https://tripoto.com'],
        ['nestaway', 'NestAway', '#2A9D8F', 'https://nestaway.com'],
    ],
    'Direct & Meta' => [
        ['google_hotel_ads', 'Google Hotels', '#4285F4', 'https://hotel-ads.google.com'],
        ['vrbo', 'VRBO', '#175CB2', 'https://vrbo.com/partner'],
        ['homeaway', 'HomeAway', '#5A7DC8', 'https://homeaway.com'],
    ],
];

// Which platforms are connected?
$connectedPlatforms = array_map(fn($c) => strtolower($c['platform']), $extCals);

renderAdminHead('Channel Manager');
?>
<div class="layout">
<?php renderSidebar('channels'); renderTopbar('Channel Manager', '
  <button class="sync-btn" onclick="syncAll(this)">↻ Sync All Now</button>
'); ?>
<?php renderFlash(); ?>

<!-- Stats -->
<div class="stats-row">
  <div class="stat-card"><div class="stat-icon">🔗</div><div class="stat-val"><?= count($extCals) ?></div><div class="stat-lbl">Connected Channels</div></div>
  <div class="stat-card"><div class="stat-icon">📥</div><div class="stat-val"><?= array_sum(array_column($extCals,'last_sync_imported')) ?></div><div class="stat-lbl">Blocks Imported (Last Sync)</div></div>
  <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-val"><?= count(array_filter($extCals, fn($c) => str_starts_with($c['last_sync_status'], 'OK'))) ?></div><div class="stat-lbl">Healthy Channels</div></div>
  <div class="stat-card"><div class="stat-icon">📤</div><div class="stat-val"><?= count(ROOM_IDS) ?></div><div class="stat-lbl">iCal Feeds Available</div></div>
</div>

<!-- Connected Channels -->
<div class="panel">
  <div class="panel-hd"><h3>Connected Channels</h3></div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Room</th><th>Platform</th><th>Commission</th><th>Last Synced</th><th>Status</th><th>Imported</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($extCals)): ?>
          <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted);">No channels connected yet. Add one from the OTA Library below.</td></tr>
        <?php else: ?>
          <?php foreach ($extCals as $ec): ?>
          <tr>
            <td class="room-name"><?= htmlspecialchars(ROOM_IDS[$ec['room_id']] ?? $ec['room_id']) ?></td>
            <td><?php
              $color = match(strtolower($ec['platform'])) {
                'airbnb'=>'#FF5A5F','booking.com','booking'=>'#003580','agoda'=>'#EB1A23','makemytrip'=>'#E8262D',
                default => '#546e7a'
              };
              echo "<span class='badge' style='background:{$color}'>" . htmlspecialchars(ucfirst($ec['platform'])) . "</span>";
            ?></td>
            <td class="muted"><?= $ec['commission_pct'] ?>%</td>
            <td class="muted" style="font-size:.76rem;"><?= $ec['last_synced'] ? substr($ec['last_synced'], 0, 16) : 'Never' ?></td>
            <td>
              <?php if (!$ec['last_synced']): ?>
                <span class="status-never">Never synced</span>
              <?php elseif (str_starts_with($ec['last_sync_status'], 'OK')): ?>
                <span class="status-synced">✓ <?= htmlspecialchars($ec['last_sync_status']) ?></span>
              <?php else: ?>
                <span class="status-error">✗ <?= htmlspecialchars(substr($ec['last_sync_status'],0,30)) ?></span>
              <?php endif; ?>
            </td>
            <td class="muted"><?= $ec['last_sync_imported'] ?></td>
            <td style="white-space:nowrap;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="sync_now">
                <input type="hidden" name="cal_id" value="<?= $ec['id'] ?>">
                <button class="btn btn-info btn-sm">Sync</button>
              </form>
              <form method="POST" style="display:inline;margin-left:3px;" onsubmit="return confirm('Remove this channel?')">
                <input type="hidden" name="action" value="delete_calendar">
                <input type="hidden" name="id" value="<?= $ec['id'] ?>">
                <button class="btn btn-danger btn-sm">Remove</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Channel Form -->
<div class="panel">
  <div class="panel-hd"><h3>Add Channel Feed</h3></div>
  <div class="panel-bd">
    <form method="POST">
      <input type="hidden" name="action" value="add_calendar">
      <div class="form-grid">
        <div class="fld"><label>Room *</label>
          <select name="room_id" required>
            <?php foreach (ROOM_IDS as $rid => $rn): ?><option value="<?= $rid ?>"><?= htmlspecialchars($rn) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fld"><label>Platform *</label>
          <input type="text" name="platform" id="platform-input" placeholder="e.g. airbnb" required>
        </div>
        <div class="fld"><label>Commission %</label>
          <input type="number" name="commission_pct" value="0" min="0" max="50" step="0.5">
        </div>
        <div class="fld" style="grid-column:span 3">
          <label>iCal URL (from the OTA's calendar export) *</label>
          <input type="url" name="ical_url" placeholder="https://www.airbnb.com/calendar/ical/..." required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:.85rem;">+ Add Channel</button>
    </form>
  </div>
</div>

<!-- OTA Library -->
<div class="panel">
  <div class="panel-hd"><h3>OTA Library</h3><div class="sub">Click any platform to pre-fill the form above</div></div>
  <div class="panel-bd">
    <?php foreach ($otaLibrary as $groupName => $platforms): ?>
    <div style="margin-bottom:1.1rem;">
      <div style="font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.6rem;"><?= $groupName ?></div>
      <div class="ota-grid">
        <?php foreach ($platforms as [$slug, $name, $color, $url]):
          $connected = in_array($slug, $connectedPlatforms);
        ?>
        <div class="ota-card <?= $connected ? 'connected' : '' ?>" onclick="selectOta('<?= htmlspecialchars($slug) ?>', '<?= htmlspecialchars($url) ?>')">
          <div class="ota-badge" style="background:<?= $color ?>"><?= strtoupper(substr($name,0,2)) ?></div>
          <div class="ota-name"><?= $name ?></div>
          <div class="ota-status"><?= $connected ? '✓ Connected' : 'Not connected' ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Channel Analytics -->
<?php if (!empty($stats)): ?>
<div class="panel">
  <div class="panel-hd"><h3>Channel Analytics (Last 6 Months)</h3></div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Month</th><th>Platform</th><th>Room</th><th>Bookings</th><th>Nights</th><th>Gross</th><th>Commission</th><th>Net</th></tr></thead>
      <tbody>
        <?php foreach ($stats as $s): ?>
        <tr>
          <td class="muted"><?= $s['month_year'] ?></td>
          <td><?= htmlspecialchars(ucfirst($s['platform'])) ?></td>
          <td class="muted"><?= htmlspecialchars(ROOM_IDS[$s['room_id']] ?? $s['room_id']) ?></td>
          <td><?= $s['bookings_count'] ?></td>
          <td><?= $s['total_nights'] ?></td>
          <td>₹<?= number_format($s['gross_revenue']) ?></td>
          <td><?= $s['commission_pct'] ?>%</td>
          <td>₹<?= number_format($s['net_revenue']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- iCal Export -->
<div class="panel">
  <div class="panel-hd"><h3>Your iCal Export URLs</h3><div class="sub">Give these to OTAs so they can pull your availability</div></div>
  <div class="panel-bd">
    <div class="ical-list">
      <?php foreach (ROOM_IDS as $rid => $rn): ?>
      <div class="ical-row">
        <span class="ical-room-lbl"><?= htmlspecialchars($rn) ?></span>
        <span class="ical-url-box" id="url-<?= $rid ?>"><?= htmlspecialchars($icalUrls[$rid]) ?></span>
        <button class="btn btn-copy" onclick="copyUrl('url-<?= $rid ?>', this)">Copy</button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Cron Setup -->
<div class="panel">
  <div class="panel-hd"><h3>Automatic Sync Setup</h3></div>
  <div class="panel-bd">
    <div class="howto">
      <h4>Set up auto-sync on Hostinger cPanel (every 30 min)</h4>
      1. Log into <strong>hPanel → Advanced → Cron Jobs</strong><br>
      2. Set frequency: <code>*/30 * * * *</code><br>
      3. Command: <code>php <?= __DIR__ ?>/cron.php</code><br><br>
      Or trigger via URL: <code><?= SITE_URL ?>/channel-manager/cron.php?token=<?= CRON_SECRET ?></code>
    </div>
  </div>
</div>

<script>
function selectOta(slug, url) {
    document.getElementById('platform-input').value = slug;
    document.getElementById('platform-input').focus();
    window.scrollTo({top: document.getElementById('platform-input').getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth'});
}
function copyUrl(id, btn) {
    navigator.clipboard.writeText(document.getElementById(id).textContent.trim()).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = orig, 2000);
    });
}
function syncAll(btn) {
    btn.disabled = true; btn.textContent = '↻ Syncing…';
    fetch('sync.php?run=1', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(r => r.json())
        .then(data => { alert('Sync complete. ' + (data.results?.length || 0) + ' calendar(s) checked.'); location.reload(); })
        .catch(() => alert('Sync failed. Check PHP error log.'))
        .finally(() => { btn.disabled = false; btn.textContent = '↻ Sync All Now'; });
}
</script>

<?php renderAdminFoot(); ?>
