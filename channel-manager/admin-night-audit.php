<?php
/**
 * Night Audit — 5-step end-of-day operational review
 * Steps: 1=Pending Check-Ins  2=Pending Check-Outs  3=Stay-Overs  4=Cashiering  5=Close
 */
require_once __DIR__ . '/admin-shell.php';
requireAdminAuth();

$propId   = getCurrentPropertyId();
$today    = date('Y-m-d');
$auditDate = $_GET['date'] ?? $today;
$act       = $_POST['action'] ?? '';
$flash     = '';

// ── POST handlers ─────────────────────────────────────────────────
if ($act === 'complete_step') {
    $step  = (int)($_POST['step'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');
    logNightAudit($auditDate, $step, $notes, $propId);
    $flash = "Step $step completed.";
    header("Location: admin-night-audit.php?date={$auditDate}&flash=" . urlencode($flash));
    exit;
}

if ($act === 'reset_audit') {
    getDB()->prepare("DELETE FROM night_audit_log WHERE property_id=? AND audit_date=?")->execute([$propId, $auditDate]);
    header("Location: admin-night-audit.php?date={$auditDate}&flash=" . urlencode("Audit reset."));
    exit;
}

// ── Data ──────────────────────────────────────────────────────────
$auditLog = getAuditForDate($auditDate, $propId);
$currentStep = (int)($auditLog['step'] ?? 0);
$auditHistory = getNightAuditLog($propId, 14);

// Bookings data for each step
$db = getDB();

// Step 1: Expected check-ins today not yet marked
$expectedCheckIns = $db->prepare("SELECT * FROM bookings WHERE property_id=? AND check_in=? AND status='confirmed' ORDER BY room_name");
$expectedCheckIns->execute([$propId, $auditDate]);
$checkIns = $expectedCheckIns->fetchAll();

// Step 2: Expected check-outs today
$expectedCheckOuts = $db->prepare("SELECT * FROM bookings WHERE property_id=? AND check_out=? AND status='confirmed' ORDER BY room_name");
$expectedCheckOuts->execute([$propId, $auditDate]);
$checkOuts = $expectedCheckOuts->fetchAll();

// Step 3: Stay-overs (in-house guests staying past today)
$stayOvers = $db->prepare("SELECT * FROM bookings WHERE property_id=? AND check_in < ? AND check_out > ? AND status='confirmed' ORDER BY room_name");
$stayOvers->execute([$propId, $auditDate, $auditDate]);
$stayOversList = $stayOvers->fetchAll();

// Step 4: Revenue summary for the day
$revStmt = $db->prepare("SELECT SUM(amount) as total, COUNT(*) as count FROM bookings WHERE property_id=? AND check_in=? AND status='confirmed'");
$revStmt->execute([$propId, $auditDate]);
$revSummary = $revStmt->fetch();

$steps = [
    1 => ['Pending Check-Ins',  'Verify all expected arrivals and update guest status.'],
    2 => ['Pending Check-Outs', 'Ensure all departing guests have checked out and rooms are cleared.'],
    3 => ['Stay-Overs',         'Confirm all in-house guests and room statuses are accurate.'],
    4 => ['Cashiering',         'Reconcile revenue, verify payments and outstanding balances.'],
    5 => ['Close Night Audit',  'Mark the day as fully audited and archive the record.'],
];

$isClosed = $currentStep >= 5;

// ── Render ────────────────────────────────────────────────────────
renderAdminHead('Night Audit');
?>
<div class="layout">
<?php renderSidebar('night-audit'); ?>
<?php renderTopbar('Night Audit — ' . date('D, d M Y', strtotime($auditDate))); ?>
<?php renderFlash(); ?>

<!-- Date selector -->
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
  <form method="GET" style="display:flex;align-items:center;gap:.5rem;">
    <label class="form-label" style="margin:0;">Audit Date:</label>
    <input type="date" name="date" class="form-input" style="width:auto;" value="<?= $auditDate ?>" onchange="this.form.submit()">
  </form>
  <?php if ($isClosed): ?>
    <span class="badge badge-active" style="font-size:.9rem;padding:.3rem .8rem;">✓ Night Audit Closed</span>
  <?php elseif ($currentStep === 0): ?>
    <span class="badge badge-inactive" style="font-size:.9rem;padding:.3rem .8rem;">Not Started</span>
  <?php else: ?>
    <span class="badge" style="background:#fef3c7;color:#92400e;font-size:.9rem;padding:.3rem .8rem;">In Progress — Step <?= $currentStep ?>/5</span>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">

  <!-- Steps Panel -->
  <div>
    <?php foreach ($steps as $num => [$title, $desc]):
        $done    = $currentStep >= $num;
        $active  = $currentStep === $num - 1 && !$isClosed;
        $locked  = $currentStep < $num - 1;
    ?>
    <div class="card" style="margin-bottom:1rem;<?= $locked ? 'opacity:.55;' : '' ?>border-left:4px solid <?= $done ? 'var(--green)' : ($active ? 'var(--primary)' : 'var(--border)') ?>;">
      <div style="display:flex;align-items:center;gap:.75rem;padding:.75rem 1.25rem;">
        <div style="width:2rem;height:2rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;background:<?= $done ? 'var(--green)' : ($active ? 'var(--primary)' : '#e5e7eb') ?>;color:<?= ($done || $active) ? '#fff' : '#6b7280' ?>;">
          <?= $done ? '✓' : $num ?>
        </div>
        <div>
          <strong><?= $title ?></strong>
          <div style="font-size:.8rem;color:#6b7280;"><?= $desc ?></div>
        </div>
        <?php if ($done && !$isClosed): ?>
          <span class="badge badge-active" style="margin-left:auto;">Done</span>
        <?php elseif ($isClosed && $num === 5): ?>
          <span class="badge badge-active" style="margin-left:auto;">Closed</span>
        <?php endif; ?>
      </div>

      <?php if (!$locked): ?>
      <!-- Step content -->
      <div style="padding:0 1.25rem 1.25rem;">

        <?php if ($num === 1): // Check-Ins ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead><tr><th>Room</th><th>Guest</th><th>Phone</th><th>Nights</th><th>Amount</th></tr></thead>
            <tbody>
              <?php if (empty($checkIns)): ?>
              <tr><td colspan="5" style="text-align:center;color:#888;">No check-ins scheduled today.</td></tr>
              <?php else: foreach ($checkIns as $b): ?>
              <tr>
                <td><?= htmlspecialchars($b['room_name']) ?></td>
                <td><?= htmlspecialchars($b['guest_name']) ?></td>
                <td><?= htmlspecialchars($b['guest_phone']) ?></td>
                <td><?= (int)((strtotime($b['check_out'])-strtotime($b['check_in']))/86400) ?></td>
                <td>₹<?= number_format($b['amount']) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <?php elseif ($num === 2): // Check-Outs ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead><tr><th>Room</th><th>Guest</th><th>Stayed</th><th>Amount</th><th>Payment</th></tr></thead>
            <tbody>
              <?php if (empty($checkOuts)): ?>
              <tr><td colspan="5" style="text-align:center;color:#888;">No check-outs scheduled today.</td></tr>
              <?php else: foreach ($checkOuts as $b): ?>
              <tr>
                <td><?= htmlspecialchars($b['room_name']) ?></td>
                <td><?= htmlspecialchars($b['guest_name']) ?></td>
                <td><?= date('d M', strtotime($b['check_in'])) ?> → <?= date('d M', strtotime($b['check_out'])) ?></td>
                <td>₹<?= number_format($b['amount']) ?></td>
                <td><span class="badge badge-<?= $b['payment_status'] === 'paid' ? 'active' : 'inactive' ?>"><?= ucfirst($b['payment_status']) ?></span></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <?php elseif ($num === 3): // Stay-Overs ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead><tr><th>Room</th><th>Guest</th><th>Check-Out</th><th>Nights Left</th></tr></thead>
            <tbody>
              <?php if (empty($stayOversList)): ?>
              <tr><td colspan="4" style="text-align:center;color:#888;">No in-house guests.</td></tr>
              <?php else: foreach ($stayOversList as $b):
                $nightsLeft = (int)((strtotime($b['check_out']) - strtotime($auditDate)) / 86400);
              ?>
              <tr>
                <td><?= htmlspecialchars($b['room_name']) ?></td>
                <td><?= htmlspecialchars($b['guest_name']) ?></td>
                <td><?= $b['check_out'] ?></td>
                <td><?= $nightsLeft ?> night<?= $nightsLeft != 1 ? 's' : '' ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <?php elseif ($num === 4): // Cashiering ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1rem;">
          <div class="stat-card">
            <div class="stat-value">₹<?= number_format($revSummary['total'] ?? 0) ?></div>
            <div class="stat-label">Revenue Today</div>
          </div>
          <div class="stat-card">
            <div class="stat-value"><?= $revSummary['count'] ?? 0 ?></div>
            <div class="stat-label">Check-Ins Today</div>
          </div>
          <div class="stat-card">
            <div class="stat-value"><?= count($stayOversList) ?></div>
            <div class="stat-label">Rooms Occupied</div>
          </div>
        </div>

        <?php elseif ($num === 5): // Close ?>
        <p style="color:#6b7280;">Clicking Close will finalize the night audit for <strong><?= $auditDate ?></strong>. This records all steps as complete for the day.</p>
        <?php endif; ?>

        <?php if ($active && !$isClosed): ?>
        <form method="POST" style="margin-top:1rem;">
          <input type="hidden" name="action" value="complete_step">
          <input type="hidden" name="step" value="<?= $num ?>">
          <div style="display:flex;align-items:center;gap:.75rem;">
            <input name="notes" class="form-input" placeholder="Notes (optional)" style="flex:1;">
            <button type="submit" class="btn btn-sm"><?= $num === 5 ? '🔒 Close Night Audit' : "Complete Step $num →" ?></button>
          </div>
        </form>
        <?php elseif ($done && $num < 5 && !$isClosed): ?>
        <div style="color:var(--green);font-size:.85rem;margin-top:.5rem;">✓ Completed<?= $auditLog['notes'] ? ' — ' . htmlspecialchars($auditLog['notes']) : '' ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($isClosed && !($auditDate === $today)): ?>
    <form method="POST" onsubmit="return confirm('Reset this audit? All steps will be cleared.');">
      <input type="hidden" name="action" value="reset_audit">
      <button class="btn btn-sm btn-secondary">Reset Audit for <?= $auditDate ?></button>
    </form>
    <?php endif; ?>
  </div>

  <!-- History Panel -->
  <div>
    <div class="card">
      <div class="card-header"><h3 style="margin:0;">Recent Audits</h3></div>
      <div style="padding:.5rem 0;">
        <?php if (empty($auditHistory)): ?>
        <p style="padding:1rem;color:#888;font-size:.85rem;">No audit history yet.</p>
        <?php else: foreach ($auditHistory as $log): ?>
        <a href="admin-night-audit.php?date=<?= $log['audit_date'] ?>"
           style="display:flex;justify-content:space-between;padding:.6rem 1rem;text-decoration:none;color:inherit;border-bottom:1px solid var(--border);<?= $log['audit_date'] === $auditDate ? 'background:#f0fdf4;' : '' ?>">
          <span><?= date('d M Y', strtotime($log['audit_date'])) ?></span>
          <?php if ($log['step'] >= 5): ?>
            <span class="badge badge-active">Closed</span>
          <?php else: ?>
            <span class="badge" style="background:#fef3c7;color:#92400e;">Step <?= $log['step'] ?>/5</span>
          <?php endif; ?>
        </a>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

</div><!-- /grid -->

<?php renderAdminFoot(); ?>
