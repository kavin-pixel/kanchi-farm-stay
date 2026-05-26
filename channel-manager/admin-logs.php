<?php
/**
 * System Logs — Availability Actions & Price Change History
 */
require_once __DIR__ . '/admin-shell.php';
requireAdminAuth();

$propId = getCurrentPropertyId();
$tab    = $_GET['tab'] ?? 'availability';

$availLogs = getAvailabilityLogs($propId, 200);
$priceLogs = getPriceLogs($propId, 200);

// ── Render ────────────────────────────────────────────────────────
renderAdminHead('System Logs');
?>
<div class="layout">
<?php renderSidebar('logs'); ?>
<?php renderTopbar('System Logs'); ?>

<!-- Tabs -->
<div style="display:flex;gap:0;margin-bottom:1.5rem;border-bottom:2px solid var(--border);">
  <a href="?tab=availability" class="tab-btn <?= $tab === 'availability' ? 'active' : '' ?>">Availability Log (<?= count($availLogs) ?>)</a>
  <a href="?tab=price"        class="tab-btn <?= $tab === 'price'        ? 'active' : '' ?>">Price Change Log (<?= count($priceLogs) ?>)</a>
</div>

<!-- ──────────── AVAILABILITY LOG ──────────────────────────── -->
<?php if ($tab === 'availability'): ?>

<div class="card">
  <div class="card-header">
    <h2 style="margin:0;">Availability Log</h2>
    <p style="margin:.25rem 0 0;color:#6b7280;font-size:.85rem;">Records of room blocks, releases, and booking actions affecting availability.</p>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Time</th><th>Room</th><th>Action</th><th>Check-In</th><th>Check-Out</th><th>Source</th><th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($availLogs)): ?>
        <tr><td colspan="7" style="text-align:center;color:#888;padding:2rem;">
          No availability log entries yet. Entries appear when bookings are created, cancelled, or rooms are blocked manually.
        </td></tr>
        <?php else: foreach ($availLogs as $log):
            $actionColors = [
                'booked'      => '#dcfce7:#15803d',
                'cancelled'   => '#fee2e2:#b91c1c',
                'blocked'     => '#fef3c7:#92400e',
                'released'    => '#dbeafe:#1d4ed8',
                'sync_import' => '#f3e8ff:#7c3aed',
            ];
            [$bg, $fc] = isset($actionColors[$log['action']])
                ? explode(':', $actionColors[$log['action']])
                : ['#f3f4f6', '#374151'];
        ?>
        <tr>
          <td style="white-space:nowrap;font-size:.8rem;color:#6b7280;">
            <?= date('d M Y', strtotime($log['created_at'])) ?><br>
            <?= date('H:i', strtotime($log['created_at'])) ?>
          </td>
          <td><strong><?= htmlspecialchars($log['room_id']) ?></strong></td>
          <td>
            <span style="padding:.15rem .5rem;border-radius:4px;font-size:.78rem;font-weight:600;background:<?= $bg ?>;color:<?= $fc ?>;">
              <?= ucfirst(str_replace('_', ' ', $log['action'])) ?>
            </span>
          </td>
          <td><?= $log['check_in'] ?: '—' ?></td>
          <td><?= $log['check_out'] ?: '—' ?></td>
          <td style="font-size:.8rem;"><?= htmlspecialchars($log['source'] ?: '—') ?></td>
          <td style="font-size:.82rem;color:#6b7280;max-width:200px;"><?= htmlspecialchars($log['notes'] ?: '—') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ──────────── PRICE CHANGE LOG ──────────────────────────── -->
<?php elseif ($tab === 'price'): ?>

<div class="card">
  <div class="card-header">
    <h2 style="margin:0;">Price Change Log</h2>
    <p style="margin:.25rem 0 0;color:#6b7280;font-size:.85rem;">Audit trail of all rate changes — from AI pricing suggestions, manual admin edits, or cron updates.</p>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Time</th><th>Room</th><th>Old Rate</th><th>New Rate</th><th>Change</th><th>Changed By</th><th>Reason</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($priceLogs)): ?>
        <tr><td colspan="7" style="text-align:center;color:#888;padding:2rem;">
          No price changes logged yet. Rate changes will be recorded here automatically.
        </td></tr>
        <?php else: foreach ($priceLogs as $log):
            $diff = $log['new_price'] - $log['old_price'];
            $pct  = $log['old_price'] > 0 ? round(($diff / $log['old_price']) * 100, 1) : 0;
            $up   = $diff >= 0;
        ?>
        <tr>
          <td style="white-space:nowrap;font-size:.8rem;color:#6b7280;">
            <?= date('d M Y', strtotime($log['created_at'])) ?><br>
            <?= date('H:i', strtotime($log['created_at'])) ?>
          </td>
          <td><strong><?= htmlspecialchars($log['room_id']) ?></strong></td>
          <td style="color:#6b7280;">₹<?= number_format($log['old_price']) ?></td>
          <td style="font-weight:600;">₹<?= number_format($log['new_price']) ?></td>
          <td>
            <span style="color:<?= $up ? 'var(--green)' : '#dc2626' ?>;font-weight:600;font-size:.9rem;">
              <?= $up ? '+' : '' ?><?= $pct ?>%
              (<?= $up ? '+' : '' ?>₹<?= number_format(abs($diff)) ?>)
            </span>
          </td>
          <td style="font-size:.8rem;"><?= htmlspecialchars($log['changed_by'] ?: 'system') ?></td>
          <td style="font-size:.82rem;color:#6b7280;max-width:200px;"><?= htmlspecialchars($log['reason'] ?: '—') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<style>
.tab-btn {
    padding:.6rem 1.25rem;font-size:.875rem;font-weight:600;text-decoration:none;
    color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-2px;
    transition:color .15s,border-color .15s;
}
.tab-btn.active, .tab-btn:hover { color:var(--primary);border-bottom-color:var(--primary); }
</style>

<?php renderAdminFoot(); ?>
