<?php
require_once __DIR__ . '/admin-shell.php';
require_once __DIR__ . '/revenue-functions.php';
requireAdminAuth();
$propertyId = getCurrentPropertyId();

$period = $_GET['period'] ?? 'month';
[$from, $to] = match($period) {
    'week'    => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))],
    'quarter' => [date('Y-m-01', strtotime('first day of -2 months')), date('Y-m-t')],
    'year'    => [date('Y-01-01'), date('Y-12-31')],
    default   => [date('Y-m-01'), date('Y-m-t')],
};

$adr        = calculateADR($propertyId, $from, $to);
$revpar     = calculateRevPAR($propertyId, $from, $to);
$occ        = getOccupancyPct($propertyId, $from, $to);
$channelMix = getChannelMixRevenue($propertyId, $from, $to);
$pickup30   = getPickupReport($propertyId, 30);
$roomPerf   = getRoomPerformance($propertyId, $from, $to);
$pacing30   = getPacingReport($propertyId, 30);
$pacing60   = getPacingReport($propertyId, 60);
$totalRev   = array_sum(array_column($channelMix, 'gross_revenue'));
$netRev     = array_sum(array_column($channelMix, 'net_revenue'));

renderAdminHead('Revenue Management');
?>
<div class="layout">
<?php renderSidebar('revenue'); ?>
<?php renderTopbar('Revenue Management', '
  <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
    <label style="font-size:.8rem;color:var(--text-muted);">Period:</label>
    <select name="period" onchange="this.form.submit()" style="padding:.35rem .7rem;border:1.5px solid var(--border);border-radius:7px;font-size:.83rem;">
      <option value="week" ' . ($period==='week'?'selected':'') . '>This Week</option>
      <option value="month" ' . ($period==='month'?'selected':'') . '>This Month</option>
      <option value="quarter" ' . ($period==='quarter'?'selected':'') . '>This Quarter</option>
      <option value="year" ' . ($period==='year'?'selected':'') . '>This Year</option>
    </select>
  </form>
'); ?>
<?php renderFlash(); ?>

<!-- KPI Strip -->
<div class="kpi-strip">
  <div class="kpi-card"><div class="kpi-label">ADR</div><div class="kpi-value">₹<?= number_format($adr) ?></div><div class="kpi-delta">Avg Daily Rate</div></div>
  <div class="kpi-card"><div class="kpi-label">RevPAR</div><div class="kpi-value">₹<?= number_format($revpar) ?></div><div class="kpi-delta">Revenue Per Available Room</div></div>
  <div class="kpi-card"><div class="kpi-label">Occupancy</div><div class="kpi-value"><?= $occ ?>%</div><div class="kpi-delta"><?= $from ?> → <?= $to ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Net Revenue</div><div class="kpi-value">₹<?= number_format($netRev) ?></div><div class="kpi-delta">After OTA commissions</div></div>
</div>

<!-- Pacing -->
<div class="panel">
  <div class="panel-hd"><h3>Forward Booking Pace</h3><div class="sub">This year vs same period last year</div></div>
  <div class="panel-bd">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
      <?php foreach ([['30 Days Out', $pacing30], ['60 Days Out', $pacing60]] as [$label, $pac]): ?>
      <div>
        <div style="font-size:.83rem;font-weight:700;margin-bottom:.75rem;"><?= $label ?></div>
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th></th><th>Bookings</th><th>Revenue</th></tr></thead>
            <tbody>
              <tr><td class="room-name">This Year</td><td><?= $pac['this_year']['bookings'] ?></td><td>₹<?= number_format($pac['this_year']['revenue']) ?></td></tr>
              <tr><td class="room-name">Last Year</td><td><?= $pac['last_year']['bookings'] ?></td><td>₹<?= number_format($pac['last_year']['revenue']) ?></td></tr>
              <?php
                $bDelta = $pac['this_year']['bookings'] - $pac['last_year']['bookings'];
                $rDelta = $pac['this_year']['revenue'] - $pac['last_year']['revenue'];
                $bColor = $bDelta >= 0 ? '#1b5e20' : '#c62828';
                $rColor = $rDelta >= 0 ? '#1b5e20' : '#c62828';
              ?>
              <tr style="background:#f7faf7;">
                <td style="font-weight:700;">Difference</td>
                <td style="color:<?= $bColor ?>;font-weight:700;"><?= $bDelta >= 0 ? '+' : '' ?><?= $bDelta ?></td>
                <td style="color:<?= $rColor ?>;font-weight:700;"><?= $rDelta >= 0 ? '+' : '' ?>₹<?= number_format($rDelta) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Channel Mix + Pickup -->
<div class="dash-grid">
  <!-- Channel Mix -->
  <div class="panel">
    <div class="panel-hd"><h3>Revenue by Channel</h3></div>
    <div class="panel-bd">
      <?php if (empty($channelMix)): ?>
        <p style="color:var(--text-muted);font-size:.85rem;">No revenue data for this period.</p>
      <?php else: ?>
        <?php
          $colors = ['airbnb'=>'#FF5A5F','booking.com'=>'#003580','booking'=>'#003580','agoda'=>'#EB1A23','makemytrip'=>'#E8262D','direct'=>'#2e7d32','razorpay'=>'#2e7d32','manual'=>'#6d4c41','blocked'=>'#78909c'];
        ?>
        <!-- Inline SVG pie chart -->
        <?php
          $total = array_sum(array_column($channelMix, 'gross_revenue'));
          $cx = 80; $cy = 80; $r = 70;
          $startAngle = -90;
          $svgSlices = '';
          $idx = 0;
          foreach ($channelMix as $ch) {
              if ($total <= 0) break;
              $pct = $ch['gross_revenue'] / $total;
              $angle = $pct * 360;
              $endAngle = $startAngle + $angle;
              $x1 = $cx + $r * cos(deg2rad($startAngle));
              $y1 = $cy + $r * sin(deg2rad($startAngle));
              $x2 = $cx + $r * cos(deg2rad($endAngle));
              $y2 = $cy + $r * sin(deg2rad($endAngle));
              $large = $angle > 180 ? 1 : 0;
              $fill = $colors[strtolower($ch['source'])] ?? '#546e7a';
              $svgSlices .= "<path d='M{$cx},{$cy} L{$x1},{$y1} A{$r},{$r} 0 {$large},1 {$x2},{$y2} Z' fill='{$fill}' opacity='.9'/>";
              $startAngle = $endAngle;
              $idx++;
          }
        ?>
        <div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;">
          <svg width="160" height="160" viewBox="0 0 160 160"><?= $svgSlices ?></svg>
          <div style="flex:1;">
            <?php foreach ($channelMix as $ch):
              $fill = $colors[strtolower($ch['source'])] ?? '#546e7a';
              $pct  = $total > 0 ? round($ch['gross_revenue'] / $total * 100) : 0;
            ?>
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.55rem;">
              <div style="width:12px;height:12px;border-radius:3px;background:<?= $fill ?>;flex-shrink:0;"></div>
              <div style="flex:1;font-size:.82rem;font-weight:600;"><?= ucfirst($ch['source']) ?></div>
              <div style="font-size:.78rem;color:var(--text-muted);"><?= $pct ?>%</div>
              <div style="font-size:.82rem;font-weight:700;">₹<?= number_format($ch['net_revenue']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pickup Report -->
  <div class="panel">
    <div class="panel-hd"><h3>Booking Pickup (Last 30 Days)</h3><div class="sub">Bookings made by day</div></div>
    <div class="panel-bd">
      <?php if (empty($pickup30)): ?>
        <p style="color:var(--text-muted);font-size:.85rem;">No bookings in the last 30 days.</p>
      <?php else: ?>
        <?php
          $maxBk = max(array_column($pickup30, 'bookings'));
          $barW  = 100 / max(1, count($pickup30));
          $svgH  = 100; $svgW = 400;
          $bars  = '';
          foreach ($pickup30 as $i => $day) {
              $h   = $maxBk > 0 ? ($day['bookings'] / $maxBk) * $svgH : 0;
              $x   = $i * $barW;
              $fill = '#4a7c59';
              $bars .= "<rect x='{$x}%' y='" . ($svgH - $h) . "' width='" . ($barW - 1) . "%' height='{$h}' fill='{$fill}' rx='2'><title>{$day['day']}: {$day['bookings']} booking(s)</title></rect>";
          }
        ?>
        <svg width="100%" height="<?= $svgH + 20 ?>" viewBox="0 0 400 <?= $svgH + 20 ?>" preserveAspectRatio="none">
          <?= $bars ?>
        </svg>
        <div style="font-size:.74rem;color:var(--text-muted);text-align:right;margin-top:.3rem;">Total: <?= array_sum(array_column($pickup30, 'bookings')) ?> bookings</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Room Performance -->
<div class="panel">
  <div class="panel-hd"><h3>Room Performance</h3><div class="sub"><?= $from ?> → <?= $to ?></div></div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Room</th><th>Bookings</th><th>Nights Sold</th><th>Gross Revenue</th><th>ADR</th></tr></thead>
      <tbody>
        <?php if (empty($roomPerf)): ?>
          <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--text-muted);">No data for this period.</td></tr>
        <?php else: ?>
          <?php foreach ($roomPerf as $r):
            $roomAdr = $r['nights'] > 0 ? round($r['revenue'] / $r['nights']) : 0;
          ?>
          <tr>
            <td class="room-name"><?= htmlspecialchars($r['room_name']) ?></td>
            <td><?= $r['bookings'] ?></td>
            <td><?= $r['nights'] ?></td>
            <td>₹<?= number_format($r['revenue']) ?></td>
            <td>₹<?= number_format($roomAdr) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr style="background:#f7faf7;font-weight:700;">
            <td>TOTAL</td>
            <td><?= array_sum(array_column($roomPerf,'bookings')) ?></td>
            <td><?= array_sum(array_column($roomPerf,'nights')) ?></td>
            <td>₹<?= number_format(array_sum(array_column($roomPerf,'revenue'))) ?></td>
            <td>₹<?= number_format($adr) ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php renderAdminFoot(); ?>
