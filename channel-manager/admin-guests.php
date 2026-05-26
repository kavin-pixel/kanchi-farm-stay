<?php
/**
 * Guest CRM — Client Profiles, Stay History, Spend Analytics
 */
require_once __DIR__ . '/admin-shell.php';
requireAdminAuth();

$propId = getCurrentPropertyId();
$search = trim($_GET['search'] ?? '');
$viewEmail = $_GET['email'] ?? '';

// ── Data ──────────────────────────────────────────────────────────
$stats  = getGuestStats($propId);
$guests = getGuests($propId, $search);

$profile = null;
$history = [];
if ($viewEmail) {
    $profile = null;
    foreach ($guests as $g) {
        if ($g['guest_email'] === $viewEmail) { $profile = $g; break; }
    }
    if (!$profile) {
        // If search filtered them out, re-fetch without search
        $allGuests = getGuests($propId, '');
        foreach ($allGuests as $g) {
            if ($g['guest_email'] === $viewEmail) { $profile = $g; break; }
        }
    }
    $history = getGuestBookingHistory($viewEmail, $propId);
}

// ── Render ────────────────────────────────────────────────────────
renderAdminHead('Guest CRM');
?>
<div class="layout">
<?php renderSidebar('guests'); ?>
<?php renderTopbar('Guest CRM'); ?>
<?php renderFlash(); ?>

<!-- KPI strip -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-value"><?= number_format($stats['unique_guests']) ?></div>
    <div class="stat-label">Unique Guests</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= number_format($stats['repeat_guests']) ?></div>
    <div class="stat-label">Repeat Guests</div>
  </div>
  <div class="stat-card">
    <div class="stat-value">₹<?= number_format($stats['avg_spend_per_guest']) ?></div>
    <div class="stat-label">Avg Spend / Guest</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:<?= $profile ? '1fr 1fr' : '1fr' ?>;gap:1.5rem;align-items:start;">

  <!-- Guest List -->
  <div class="card">
    <div class="card-header" style="display:flex;align-items:center;gap:.75rem;">
      <h2 style="margin:0;flex:1;">Guests</h2>
      <form method="GET" style="display:flex;gap:.5rem;">
        <input name="search" class="form-input" placeholder="Search name, email, phone…" value="<?= htmlspecialchars($search) ?>" style="width:200px;">
        <?php if ($viewEmail): ?><input type="hidden" name="email" value="<?= htmlspecialchars($viewEmail) ?>"><?php endif; ?>
        <button class="btn btn-sm">Search</button>
        <?php if ($search): ?><a href="admin-guests.php" class="btn btn-sm btn-secondary">Clear</a><?php endif; ?>
      </form>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr><th>Guest</th><th>Phone</th><th>Stays</th><th>Nights</th><th>Total Spent</th><th>Last Stay</th><th></th></tr>
        </thead>
        <tbody>
          <?php if (empty($guests)): ?>
          <tr><td colspan="7" style="text-align:center;color:#888;padding:2rem;">
            <?= $search ? "No guests match '$search'." : 'No guest data yet. Direct bookings will appear here.' ?>
          </td></tr>
          <?php else: foreach ($guests as $g): ?>
          <tr class="<?= $g['guest_email'] === $viewEmail ? 'row-selected' : '' ?>">
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($g['guest_name']) ?></div>
              <div style="font-size:.78rem;color:#6b7280;"><?= htmlspecialchars($g['guest_email']) ?></div>
            </td>
            <td><?= htmlspecialchars($g['guest_phone'] ?: '—') ?></td>
            <td style="text-align:center;">
              <?= $g['total_stays'] ?>
              <?php if ($g['total_stays'] > 1): ?>
                <span title="Repeat guest" style="color:var(--primary);font-size:.7rem;">★</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;"><?= (int)($g['total_nights'] ?? 0) ?></td>
            <td>₹<?= number_format($g['total_spent']) ?></td>
            <td><?= $g['last_stay'] ? date('d M Y', strtotime($g['last_stay'])) : '—' ?></td>
            <td>
              <a href="?email=<?= urlencode($g['guest_email']) ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-xs">View</a>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Guest Profile Panel -->
  <?php if ($profile): ?>
  <div>
    <div class="card" style="margin-bottom:1rem;">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h2 style="margin:0;"><?= htmlspecialchars($profile['guest_name']) ?></h2>
        <a href="admin-guests.php<?= $search ? '?search=' . urlencode($search) : '' ?>" class="btn btn-xs btn-secondary">✕ Close</a>
      </div>
      <div style="padding:1.25rem;display:grid;gap:.5rem;">
        <div><span style="color:#6b7280;">Email:</span> <?= htmlspecialchars($profile['guest_email']) ?></div>
        <div><span style="color:#6b7280;">Phone:</span> <?= htmlspecialchars($profile['guest_phone'] ?: '—') ?></div>
        <?php if ($profile['whatsapp_number']): ?>
        <div><span style="color:#6b7280;">WhatsApp:</span> <?= htmlspecialchars($profile['whatsapp_number']) ?></div>
        <?php endif; ?>
        <div><span style="color:#6b7280;">First Stay:</span> <?= $profile['first_stay'] ? date('d M Y', strtotime($profile['first_stay'])) : '—' ?></div>
        <div><span style="color:#6b7280;">Last Stay:</span> <?= $profile['last_stay'] ? date('d M Y', strtotime($profile['last_stay'])) : '—' ?></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);border-top:1px solid var(--border);">
        <div style="text-align:center;padding:1rem;border-right:1px solid var(--border);">
          <div style="font-size:1.4rem;font-weight:700;color:var(--primary);"><?= $profile['total_stays'] ?></div>
          <div style="font-size:.75rem;color:#6b7280;">Stays</div>
        </div>
        <div style="text-align:center;padding:1rem;border-right:1px solid var(--border);">
          <div style="font-size:1.4rem;font-weight:700;color:var(--primary);"><?= (int)($profile['total_nights'] ?? 0) ?></div>
          <div style="font-size:.75rem;color:#6b7280;">Nights</div>
        </div>
        <div style="text-align:center;padding:1rem;">
          <div style="font-size:1.4rem;font-weight:700;color:var(--green);">₹<?= number_format($profile['total_spent']) ?></div>
          <div style="font-size:.75rem;color:#6b7280;">Total Spent</div>
        </div>
      </div>
      <!-- Quick contact links -->
      <div style="padding:1rem 1.25rem;border-top:1px solid var(--border);display:flex;gap:.5rem;flex-wrap:wrap;">
        <?php if ($profile['whatsapp_number'] || $profile['guest_phone']): ?>
        <a href="https://wa.me/<?= preg_replace('/\D/','',$profile['whatsapp_number'] ?: $profile['guest_phone']) ?>" target="_blank" class="btn btn-sm" style="background:#25D366;color:#fff;">WhatsApp</a>
        <?php endif; ?>
        <?php if ($profile['guest_phone']): ?>
        <a href="tel:<?= htmlspecialchars($profile['guest_phone']) ?>" class="btn btn-sm btn-secondary">Call</a>
        <?php endif; ?>
        <?php if ($profile['guest_email']): ?>
        <a href="mailto:<?= htmlspecialchars($profile['guest_email']) ?>" class="btn btn-sm btn-secondary">Email</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stay History -->
    <div class="card">
      <div class="card-header"><h3 style="margin:0;">Stay History</h3></div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr><th>Room</th><th>Check-In</th><th>Check-Out</th><th>Nights</th><th>Amount</th><th>Source</th></tr>
          </thead>
          <tbody>
            <?php foreach ($history as $b):
              $nights = (int)((strtotime($b['check_out']) - strtotime($b['check_in'])) / 86400);
            ?>
            <tr>
              <td><?= htmlspecialchars($b['room_name']) ?></td>
              <td><?= date('d M Y', strtotime($b['check_in'])) ?></td>
              <td><?= date('d M Y', strtotime($b['check_out'])) ?></td>
              <td><?= $nights ?></td>
              <td>₹<?= number_format($b['amount']) ?></td>
              <td><span class="badge" style="font-size:.7rem;"><?= htmlspecialchars($b['source']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /grid -->

<style>
.row-selected { background:#f0fdf4; }
</style>

<?php renderAdminFoot(); ?>
