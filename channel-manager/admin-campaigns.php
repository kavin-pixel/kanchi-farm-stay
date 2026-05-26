<?php
/**
 * Booking Engine Campaigns & Coupon Codes
 * Manage Early Bird, Last Minute, Long Stay promotions + promo codes.
 */
require_once __DIR__ . '/admin-shell.php';
requireAdminAuth();

$propId = getCurrentPropertyId();
$act    = $_POST['action'] ?? '';
$err    = ''; $flash = '';

// ── POST handlers ─────────────────────────────────────────────────

if ($act === 'add_campaign') {
    try {
        addCampaign([
            'name'          => trim($_POST['name'] ?? ''),
            'campaign_type' => $_POST['campaign_type'] ?? 'general',
            'discount_pct'  => (float)($_POST['discount_pct'] ?? 0),
            'min_nights'    => (int)($_POST['min_nights'] ?? 1),
            'valid_from'    => $_POST['valid_from'],
            'valid_until'   => $_POST['valid_until'],
            'advance_days'  => (int)($_POST['advance_days'] ?? 0),
            'is_active'     => 1,
        ], $propId);
        $flash = 'Campaign created.';
    } catch (Exception $e) { $err = $e->getMessage(); }
}

if ($act === 'toggle_campaign') {
    $campaigns = getCampaigns($propId);
    foreach ($campaigns as $c) {
        if ($c['id'] == (int)$_POST['id']) {
            updateCampaign($c['id'], array_merge($c, ['is_active' => $c['is_active'] ? 0 : 1]));
            $flash = 'Campaign updated.';
            break;
        }
    }
}

if ($act === 'delete_campaign') {
    deleteCampaign((int)$_POST['id']);
    $flash = 'Campaign deleted.';
}

if ($act === 'add_coupon') {
    try {
        addCoupon([
            'code'         => strtoupper(trim($_POST['code'] ?? '')),
            'name'         => trim($_POST['name'] ?? ''),
            'discount_pct' => (float)($_POST['discount_pct'] ?? 0),
            'min_nights'   => (int)($_POST['min_nights'] ?? 1),
            'max_uses'     => (int)($_POST['max_uses'] ?? 0),
            'valid_from'   => $_POST['valid_from'],
            'valid_until'  => $_POST['valid_until'],
            'is_active'    => 1,
        ], $propId);
        $flash = 'Coupon created.';
    } catch (Exception $e) { $err = "Code already exists or error: " . $e->getMessage(); }
}

if ($act === 'toggle_coupon') {
    $coupons = getCoupons($propId);
    foreach ($coupons as $c) {
        if ($c['id'] == (int)$_POST['id']) {
            updateCoupon($c['id'], array_merge($c, ['is_active' => $c['is_active'] ? 0 : 1]));
            $flash = 'Coupon updated.';
            break;
        }
    }
}

if ($act === 'delete_coupon') {
    deleteCoupon((int)$_POST['id']);
    $flash = 'Coupon deleted.';
}

if ($flash && !$err) {
    header("Location: admin-campaigns.php?flash=" . urlencode($flash));
    exit;
}

// ── Data ──────────────────────────────────────────────────────────
$campaigns = getCampaigns($propId);
$coupons   = getCoupons($propId);
$today     = date('Y-m-d');

// ── Render ────────────────────────────────────────────────────────
renderAdminHead('Campaigns & Promotions');
?>
<div class="layout">
<?php renderSidebar('campaigns'); ?>
<?php renderTopbar('Campaigns & Promotions'); ?>
<?php renderFlash(); ?>

<?php if ($err): ?>
<div class="flash" style="background:#fee2e2;color:#b91c1c;"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<!-- ── Booking Engine Campaigns ─────────────────────────────────── -->
<div class="card" style="margin-bottom:1.5rem;">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h2 style="margin:0;">Booking Engine Campaigns</h2>
    <button class="btn btn-sm" onclick="togglePanel('camp-form')">+ New Campaign</button>
  </div>

  <!-- Add Campaign Form -->
  <div id="camp-form" style="display:none;padding:1.25rem;border-top:1px solid var(--border);background:#f9fafb;">
    <form method="POST">
      <input type="hidden" name="action" value="add_campaign">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;">
        <div>
          <label class="form-label">Campaign Name</label>
          <input name="name" class="form-input" placeholder="Early Bird Summer" required>
        </div>
        <div>
          <label class="form-label">Type</label>
          <select name="campaign_type" class="form-input">
            <option value="early_bird">Early Bird</option>
            <option value="last_minute">Last Minute</option>
            <option value="long_stay">Long Stay</option>
            <option value="weekend">Weekend Special</option>
            <option value="general">General</option>
          </select>
        </div>
        <div>
          <label class="form-label">Discount %</label>
          <input name="discount_pct" type="number" min="1" max="70" class="form-input" placeholder="15" required>
        </div>
        <div>
          <label class="form-label">Min Nights</label>
          <input name="min_nights" type="number" min="1" class="form-input" value="1">
        </div>
        <div>
          <label class="form-label">Advance Days (Early Bird)</label>
          <input name="advance_days" type="number" min="0" class="form-input" value="0" placeholder="30">
        </div>
        <div>
          <label class="form-label">Valid From</label>
          <input name="valid_from" type="date" class="form-input" value="<?= $today ?>" required>
        </div>
        <div>
          <label class="form-label">Valid Until</label>
          <input name="valid_until" type="date" class="form-input" value="<?= date('Y-m-d', strtotime('+3 months')) ?>" required>
        </div>
      </div>
      <div style="margin-top:.75rem;">
        <button type="submit" class="btn btn-sm">Save Campaign</button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="togglePanel('camp-form')" style="margin-left:.5rem;">Cancel</button>
      </div>
    </form>
  </div>

  <!-- Campaigns Table -->
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Name</th><th>Type</th><th>Discount</th><th>Min Nights</th>
          <th>Advance Days</th><th>Valid From</th><th>Valid Until</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($campaigns)): ?>
        <tr><td colspan="9" style="text-align:center;color:#888;padding:2rem;">No campaigns yet. Create your first promotion above.</td></tr>
        <?php else: foreach ($campaigns as $c):
            $isExpired = $c['valid_until'] < $today;
            $isActive  = $c['is_active'] && !$isExpired;
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
          <td><span class="badge badge-<?= $c['campaign_type'] ?>"><?= ucwords(str_replace('_',' ',$c['campaign_type'])) ?></span></td>
          <td style="color:var(--green);font-weight:600;"><?= $c['discount_pct'] ?>% off</td>
          <td><?= $c['min_nights'] ?> night<?= $c['min_nights'] > 1 ? 's' : '' ?></td>
          <td><?= $c['advance_days'] > 0 ? $c['advance_days'].' days' : '—' ?></td>
          <td><?= $c['valid_from'] ?></td>
          <td><?= $c['valid_until'] ?></td>
          <td>
            <?php if ($isExpired): ?>
              <span class="badge badge-expired">Expired</span>
            <?php elseif ($isActive): ?>
              <span class="badge badge-active">Active</span>
            <?php else: ?>
              <span class="badge badge-inactive">Paused</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="toggle_campaign">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-xs btn-secondary"><?= $c['is_active'] ? 'Pause' : 'Activate' ?></button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this campaign?');">
              <input type="hidden" name="action" value="delete_campaign">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-xs btn-danger">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Coupon / Promo Codes ──────────────────────────────────────── -->
<div class="card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h2 style="margin:0;">Coupon / Promo Codes</h2>
    <button class="btn btn-sm" onclick="togglePanel('coupon-form')">+ New Coupon</button>
  </div>

  <!-- Add Coupon Form -->
  <div id="coupon-form" style="display:none;padding:1.25rem;border-top:1px solid var(--border);background:#f9fafb;">
    <form method="POST">
      <input type="hidden" name="action" value="add_coupon">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;">
        <div>
          <label class="form-label">Coupon Code</label>
          <input name="code" class="form-input" placeholder="WELCOME20" required style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div>
          <label class="form-label">Display Name</label>
          <input name="name" class="form-input" placeholder="Welcome Discount">
        </div>
        <div>
          <label class="form-label">Discount %</label>
          <input name="discount_pct" type="number" min="1" max="70" class="form-input" placeholder="20" required>
        </div>
        <div>
          <label class="form-label">Min Nights</label>
          <input name="min_nights" type="number" min="1" class="form-input" value="1">
        </div>
        <div>
          <label class="form-label">Max Uses (0 = unlimited)</label>
          <input name="max_uses" type="number" min="0" class="form-input" value="0">
        </div>
        <div>
          <label class="form-label">Valid From</label>
          <input name="valid_from" type="date" class="form-input" value="<?= $today ?>" required>
        </div>
        <div>
          <label class="form-label">Valid Until</label>
          <input name="valid_until" type="date" class="form-input" value="<?= date('Y-m-d', strtotime('+3 months')) ?>" required>
        </div>
      </div>
      <div style="margin-top:.75rem;">
        <button type="submit" class="btn btn-sm">Save Coupon</button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="togglePanel('coupon-form')" style="margin-left:.5rem;">Cancel</button>
      </div>
    </form>
  </div>

  <!-- Coupons Table -->
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th><th>Name</th><th>Discount</th><th>Min Nights</th>
          <th>Uses</th><th>Valid Until</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($coupons)): ?>
        <tr><td colspan="8" style="text-align:center;color:#888;padding:2rem;">No coupons yet.</td></tr>
        <?php else: foreach ($coupons as $c):
            $isExpired = $c['valid_until'] < $today;
            $limitReached = $c['max_uses'] > 0 && $c['uses_count'] >= $c['max_uses'];
            $isActive  = $c['is_active'] && !$isExpired && !$limitReached;
        ?>
        <tr>
          <td><code style="font-size:1rem;font-weight:700;color:var(--primary);background:#f0fdf4;padding:.2rem .6rem;border-radius:4px;"><?= htmlspecialchars($c['code']) ?></code></td>
          <td><?= htmlspecialchars($c['name'] ?: '—') ?></td>
          <td style="color:var(--green);font-weight:600;"><?= $c['discount_pct'] ?>% off</td>
          <td><?= $c['min_nights'] ?></td>
          <td><?= $c['uses_count'] ?><?= $c['max_uses'] > 0 ? ' / ' . $c['max_uses'] : '' ?></td>
          <td><?= $c['valid_until'] ?></td>
          <td>
            <?php if ($isExpired): ?>
              <span class="badge badge-expired">Expired</span>
            <?php elseif ($limitReached): ?>
              <span class="badge badge-expired">Used Up</span>
            <?php elseif ($isActive): ?>
              <span class="badge badge-active">Active</span>
            <?php else: ?>
              <span class="badge badge-inactive">Paused</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="toggle_coupon">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-xs btn-secondary"><?= $c['is_active'] ? 'Pause' : 'Activate' ?></button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this coupon?');">
              <input type="hidden" name="action" value="delete_coupon">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-xs btn-danger">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function togglePanel(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
<?php renderAdminFoot(); ?>
