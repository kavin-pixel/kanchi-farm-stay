<?php
require_once __DIR__ . '/admin-shell.php';
require_once __DIR__ . '/pricing-engine.php';
requireAdminAuth();
$propertyId = getCurrentPropertyId();

$act = $_POST['action'] ?? '';
if ($act === 'update_rule') {
    updatePricingRule((int)$_POST['rule_id'], (float)$_POST['adjustment_pct'], (int)($_POST['is_active'] ?? 0), (float)($_POST['condition_value'] ?? 0));
    header('Location: admin-pricing.php?flash=Rule+updated'); exit;
}
if ($act === 'update_rate') {
    setRoomRate($_POST['room_id'], (float)$_POST['base_price'], $propertyId);
    header('Location: admin-pricing.php?flash=Base+rate+updated'); exit;
}
if ($act === 'approve_suggestion') {
    approvePricingSuggestion((int)$_POST['suggestion_id'], (float)$_POST['approved_price']);
    header('Location: admin-pricing.php?flash=Suggestion+approved'); exit;
}
if ($act === 'reject_suggestion') {
    rejectPricingSuggestion((int)$_POST['suggestion_id']);
    header('Location: admin-pricing.php?flash=Suggestion+dismissed'); exit;
}
if ($act === 'add_event') {
    addDemandEvent(['event_date' => $_POST['event_date'], 'event_name' => trim($_POST['event_name']), 'event_type' => $_POST['event_type'] ?? 'festival', 'demand_level' => $_POST['demand_level'] ?? 'high', 'notes' => trim($_POST['notes'] ?? '')], $propertyId);
    header('Location: admin-pricing.php?flash=Event+added'); exit;
}
if ($act === 'delete_event') {
    deleteDemandEvent((int)$_POST['event_id']);
    header('Location: admin-pricing.php?flash=Event+removed'); exit;
}
if ($act === 'generate_suggestions') {
    setSetting('last_suggestions_generated', '', $propertyId); // reset throttle
    generatePricingSuggestions($propertyId);
    header('Location: admin-pricing.php?flash=Suggestions+generated'); exit;
}

$rules       = getPricingRules($propertyId);
$suggestions = getPricingSuggestions('pending', $propertyId);
$events      = getDemandEvents($propertyId);
$rates       = getRoomRates($propertyId);

$ruleIcons = ['occupancy_high' => '📈', 'occupancy_low' => '📉', 'last_minute' => '⚡', 'weekend' => '🌙', 'los_discount' => '🗓', 'demand_event' => '🎉'];

renderAdminHead('AI Pricing');
?>
<div class="layout">
<?php renderSidebar('pricing'); renderTopbar('AI Smart Pricing', '
  <form method="POST" style="display:inline;">
    <input type="hidden" name="action" value="generate_suggestions">
    <button class="sync-btn" type="submit">✨ Generate Suggestions</button>
  </form>
'); ?>
<?php renderFlash(); ?>

<!-- Occupancy indicator -->
<?php $occ = getCurrentOccupancyPct($propertyId); ?>
<div class="stats-row">
  <div class="stat-card"><div class="stat-icon">🏨</div><div class="stat-val"><?= $occ ?>%</div><div class="stat-lbl">Current Occupancy (30 days)</div></div>
  <div class="stat-card"><div class="stat-icon">💡</div><div class="stat-val"><?= count($suggestions) ?></div><div class="stat-lbl">Pending Suggestions</div></div>
  <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-val"><?= count($events) ?></div><div class="stat-lbl">Demand Events</div></div>
  <div class="stat-card"><div class="stat-icon">⚙️</div><div class="stat-val"><?= count(array_filter($rules, fn($r) => $r['is_active'])) ?></div><div class="stat-lbl">Active Rules</div></div>
</div>

<!-- Pricing Rules -->
<div class="panel">
  <div class="panel-hd"><h3>Pricing Rules</h3><div class="sub">Adjust multipliers and enable/disable each rule</div></div>
  <div class="panel-bd" style="display:flex;flex-direction:column;gap:.65rem;">
    <?php foreach ($rules as $rule):
      $positive = (float)$rule['adjustment_pct'] > 0;
      $icon = $ruleIcons[$rule['rule_type']] ?? '💰';
    ?>
    <div class="rule-card <?= !$rule['is_active'] ? 'inactive' : '' ?>">
      <div class="rule-icon"><?= $icon ?></div>
      <div class="rule-info">
        <div class="rule-name"><?= htmlspecialchars($rule['rule_name']) ?></div>
        <div class="rule-desc">Type: <?= $rule['rule_type'] ?> | Condition: <?= $rule['condition_value'] ?> | Priority: <?= $rule['priority'] ?></div>
      </div>
      <div class="rule-adj <?= $positive ? 'positive' : 'negative' ?>">
        <?= $positive ? '+' : '' ?><?= $rule['adjustment_pct'] ?>%
      </div>
      <form method="POST" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
        <input type="hidden" name="action" value="update_rule">
        <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
        <input type="hidden" name="condition_value" value="<?= $rule['condition_value'] ?>">
        <div class="fld">
          <label style="font-size:.68rem;">Adjustment %</label>
          <input type="number" name="adjustment_pct" value="<?= $rule['adjustment_pct'] ?>" step="1" style="width:75px;">
        </div>
        <div class="toggle-wrap">
          <label class="toggle">
            <input type="checkbox" name="is_active" value="1" <?= $rule['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()">
            <span class="slider"></span>
          </label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Room Base Rates -->
<div class="panel">
  <div class="panel-hd"><h3>Room Base Rates</h3><div class="sub">These are the starting prices before dynamic adjustments</div></div>
  <div class="panel-bd">
    <div class="form-grid cols-3">
      <?php foreach (ROOM_IDS as $rid => $rname):
        $price = $rates[$rid] ?? (ROOM_BASE_PRICES[$rid] ?? 0);
      ?>
      <div class="fld">
        <label><?= htmlspecialchars($rname) ?></label>
        <form method="POST" style="display:flex;gap:.4rem;">
          <input type="hidden" name="action" value="update_rate">
          <input type="hidden" name="room_id" value="<?= $rid ?>">
          <input type="number" name="base_price" value="<?= (int)$price ?>" min="0" step="100" style="flex:1;">
          <button type="submit" class="btn btn-primary btn-sm">₹</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Demand Events -->
<div class="panel">
  <div class="panel-hd"><h3>Demand Events</h3><div class="sub">Muhurathams, festivals, and holidays that trigger price premiums</div></div>
  <div class="panel-bd">
    <form method="POST" style="margin-bottom:1rem;">
      <input type="hidden" name="action" value="add_event">
      <div class="form-grid">
        <div class="fld"><label>Date *</label><input type="date" name="event_date" required></div>
        <div class="fld"><label>Event Name *</label><input type="text" name="event_name" placeholder="e.g. Pongal" required></div>
        <div class="fld"><label>Type</label>
          <select name="event_type">
            <option value="muhurtham">Muhurtham</option>
            <option value="festival">Festival</option>
            <option value="holiday">Public Holiday</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="fld"><label>Demand Level</label>
          <select name="demand_level">
            <option value="gold">Gold (+40%)</option>
            <option value="high" selected>High (+25%)</option>
            <option value="medium">Medium (+15%)</option>
            <option value="low">Low (+10%)</option>
          </select>
        </div>
        <div class="fld"><label>Notes</label><input type="text" name="notes" placeholder="Optional"></div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:.75rem;">+ Add Event</button>
    </form>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Event</th><th>Type</th><th>Demand</th><th>Notes</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($events)): ?>
            <tr><td colspan="6" style="text-align:center;padding:1.5rem;color:var(--text-muted);">No demand events yet.</td></tr>
          <?php else: ?>
            <?php foreach ($events as $e): ?>
            <tr>
              <td><?= $e['event_date'] ?></td>
              <td><?= htmlspecialchars($e['event_name']) ?></td>
              <td><?= $e['event_type'] ?></td>
              <td><span class="badge" style="background:<?= match($e['demand_level']){'gold'=>'#f59e0b','high'=>'#c62828','medium'=>'#e65100',default=>'#546e7a'} ?>"><?= strtoupper($e['demand_level']) ?></span></td>
              <td class="muted"><?= htmlspecialchars($e['notes']) ?></td>
              <td><form method="POST" onsubmit="return confirm('Remove event?')"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="event_id" value="<?= $e['id'] ?>"><button class="btn btn-danger btn-sm">Remove</button></form></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Pending Suggestions -->
<div class="panel">
  <div class="panel-hd"><h3>AI Pricing Suggestions</h3><span class="badge badge-gold"><?= count($suggestions) ?> pending</span></div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Room</th><th>Date</th><th>Current</th><th>Suggested</th><th>Change</th><th>Reason</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($suggestions)): ?>
          <tr><td colspan="7" style="text-align:center;padding:1.5rem;color:var(--text-muted);">No pending suggestions. Click "Generate Suggestions" to analyse upcoming dates.</td></tr>
        <?php else: ?>
          <?php foreach ($suggestions as $s):
            $pct = $s['suggestion_pct'];
            $color = $pct > 0 ? '#c62828' : '#1b5e20';
          ?>
          <tr>
            <td class="room-name"><?= htmlspecialchars(ROOM_IDS[$s['room_id']] ?? $s['room_id']) ?></td>
            <td><?= $s['date_from'] ?></td>
            <td>₹<?= number_format($s['current_price']) ?></td>
            <td><strong>₹<?= number_format($s['suggested_price']) ?></strong></td>
            <td><span style="color:<?= $color ?>;font-weight:700;"><?= $pct > 0 ? '+' : '' ?><?= $pct ?>%</span></td>
            <td class="muted" style="max-width:200px;"><?= htmlspecialchars($s['reason']) ?></td>
            <td style="white-space:nowrap;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="approve_suggestion">
                <input type="hidden" name="suggestion_id" value="<?= $s['id'] ?>">
                <input type="hidden" name="approved_price" value="<?= $s['suggested_price'] ?>">
                <button class="btn btn-primary btn-sm">Approve</button>
              </form>
              <form method="POST" style="display:inline;margin-left:4px;">
                <input type="hidden" name="action" value="reject_suggestion">
                <input type="hidden" name="suggestion_id" value="<?= $s['id'] ?>">
                <button class="btn btn-danger btn-sm">Dismiss</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php renderAdminFoot(); ?>
