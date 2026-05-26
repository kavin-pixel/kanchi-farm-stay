<?php
/**
 * Agents & Corporate Companies management
 */
require_once __DIR__ . '/admin-shell.php';
requireAdminAuth();

$propId = getCurrentPropertyId();
$tab    = $_GET['tab'] ?? 'agents';
$act    = $_POST['action'] ?? '';
$flash  = ''; $err = '';

// ── POST handlers ─────────────────────────────────────────────────
if ($act === 'add_agent') {
    try {
        addAgent([
            'name'           => trim($_POST['name'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? ''),
            'commission_pct' => (float)($_POST['commission_pct'] ?? 10),
            'address'        => trim($_POST['address'] ?? ''),
            'is_active'      => 1,
        ], $propId);
        $flash = 'Agent added.';
    } catch (Exception $e) { $err = $e->getMessage(); }
}

if ($act === 'toggle_agent') {
    $agent = getAgentById((int)$_POST['id']);
    if ($agent) {
        updateAgent($agent['id'], array_merge($agent, ['is_active' => $agent['is_active'] ? 0 : 1]));
        $flash = 'Agent updated.';
    }
}

if ($act === 'delete_agent') {
    deleteAgent((int)$_POST['id']);
    $flash = 'Agent deleted.';
}

if ($act === 'add_company') {
    try {
        addCompany([
            'name'         => trim($_POST['name'] ?? ''),
            'gst_number'   => strtoupper(trim($_POST['gst_number'] ?? '')),
            'email'        => trim($_POST['email'] ?? ''),
            'phone'        => trim($_POST['phone'] ?? ''),
            'address'      => trim($_POST['address'] ?? ''),
            'discount_pct' => (float)($_POST['discount_pct'] ?? 0),
            'credit_limit' => (float)($_POST['credit_limit'] ?? 0),
            'is_active'    => 1,
        ], $propId);
        $flash = 'Company added.';
    } catch (Exception $e) { $err = $e->getMessage(); }
}

if ($act === 'toggle_company') {
    $company = getCompanyById((int)$_POST['id']);
    if ($company) {
        updateCompany($company['id'], array_merge($company, ['is_active' => $company['is_active'] ? 0 : 1]));
        $flash = 'Company updated.';
    }
}

if ($act === 'delete_company') {
    deleteCompany((int)$_POST['id']);
    $flash = 'Company deleted.';
}

if ($flash && !$err) {
    header("Location: admin-agents.php?tab={$tab}&flash=" . urlencode($flash));
    exit;
}

// ── Data ──────────────────────────────────────────────────────────
$agents    = getAgents($propId);
$companies = getCompanies($propId);

// Bookings from agents/companies (by source tag)
$agentBookings   = array_filter(getAllBookings([], $propId), fn($b) => $b['source'] === 'agent');
$companyBookings = array_filter(getAllBookings([], $propId), fn($b) => $b['source'] === 'corporate');

$agentRevenue   = array_sum(array_column(iterator_to_array((function() use ($agentBookings) { yield from $agentBookings; })()), 'amount'));
$companyRevenue = array_sum(array_column(iterator_to_array((function() use ($companyBookings) { yield from $companyBookings; })()), 'amount'));

// ── Render ────────────────────────────────────────────────────────
renderAdminHead('Agents & Companies');
?>
<div class="layout">
<?php renderSidebar('agents'); ?>
<?php renderTopbar('Agents & Corporate'); ?>
<?php renderFlash(); ?>

<?php if ($err): ?>
<div class="flash" style="background:#fee2e2;color:#b91c1c;"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<!-- Tabs -->
<div style="display:flex;gap:0;margin-bottom:1.5rem;border-bottom:2px solid var(--border);">
  <a href="?tab=agents"    class="tab-btn <?= $tab === 'agents'    ? 'active' : '' ?>">Travel Agents (<?= count($agents) ?>)</a>
  <a href="?tab=companies" class="tab-btn <?= $tab === 'companies' ? 'active' : '' ?>">Corporate Companies (<?= count($companies) ?>)</a>
</div>

<!-- ────────────────── AGENTS TAB ────────────────────────────── -->
<?php if ($tab === 'agents'): ?>

<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-value"><?= count($agents) ?></div>
    <div class="stat-label">Total Agents</div>
  </div>
  <div class="stat-card">
    <div class="stat-value">₹<?= number_format($agentRevenue) ?></div>
    <div class="stat-label">Agent Booking Revenue</div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h2 style="margin:0;">Travel Agents</h2>
    <button class="btn btn-sm" onclick="togglePanel('agent-form')">+ Add Agent</button>
  </div>

  <div id="agent-form" style="display:none;padding:1.25rem;border-top:1px solid var(--border);background:#f9fafb;">
    <form method="POST">
      <input type="hidden" name="action" value="add_agent">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;">
        <div>
          <label class="form-label">Agent Name *</label>
          <input name="name" class="form-input" placeholder="Sunshine Travels" required>
        </div>
        <div>
          <label class="form-label">Email</label>
          <input name="email" type="email" class="form-input" placeholder="agent@travels.com">
        </div>
        <div>
          <label class="form-label">Phone</label>
          <input name="phone" type="tel" class="form-input" placeholder="+91 9876543210">
        </div>
        <div>
          <label class="form-label">Commission %</label>
          <input name="commission_pct" type="number" min="0" max="50" step="0.5" class="form-input" value="10">
        </div>
        <div style="grid-column:span 2;">
          <label class="form-label">Address</label>
          <input name="address" class="form-input" placeholder="City, State">
        </div>
      </div>
      <div style="margin-top:.75rem;">
        <button type="submit" class="btn btn-sm">Add Agent</button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="togglePanel('agent-form')" style="margin-left:.5rem;">Cancel</button>
      </div>
    </form>
  </div>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Agent</th><th>Email</th><th>Phone</th><th>Commission</th><th>Address</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($agents)): ?>
        <tr><td colspan="7" style="text-align:center;color:#888;padding:2rem;">No agents yet. Add your first travel agent partner.</td></tr>
        <?php else: foreach ($agents as $a): ?>
        <tr>
          <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
          <td><?= htmlspecialchars($a['email'] ?: '—') ?></td>
          <td><?= htmlspecialchars($a['phone'] ?: '—') ?></td>
          <td style="font-weight:600;color:var(--primary);"><?= $a['commission_pct'] ?>%</td>
          <td><?= htmlspecialchars($a['address'] ?: '—') ?></td>
          <td><span class="badge badge-<?= $a['is_active'] ? 'active' : 'inactive' ?>"><?= $a['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="toggle_agent">
              <input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button class="btn btn-xs btn-secondary"><?= $a['is_active'] ? 'Deactivate' : 'Activate' ?></button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this agent?');">
              <input type="hidden" name="action" value="delete_agent">
              <input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button class="btn btn-xs btn-danger">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Agent bookings -->
<?php if (!empty($agentBookings)): ?>
<div class="card" style="margin-top:1.5rem;">
  <div class="card-header"><h3 style="margin:0;">Agent Bookings</h3></div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Guest</th><th>Room</th><th>Check-In</th><th>Check-Out</th><th>Amount</th><th>Ref</th></tr></thead>
      <tbody>
        <?php foreach (array_slice($agentBookings, 0, 20) as $b): ?>
        <tr>
          <td><?= htmlspecialchars($b['guest_name']) ?></td>
          <td><?= htmlspecialchars($b['room_name']) ?></td>
          <td><?= $b['check_in'] ?></td>
          <td><?= $b['check_out'] ?></td>
          <td>₹<?= number_format($b['amount']) ?></td>
          <td style="font-size:.78rem;color:#6b7280;"><?= htmlspecialchars($b['booking_ref'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ────────────────── COMPANIES TAB ─────────────────────────── -->
<?php elseif ($tab === 'companies'): ?>

<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-value"><?= count($companies) ?></div>
    <div class="stat-label">Corporate Accounts</div>
  </div>
  <div class="stat-card">
    <div class="stat-value">₹<?= number_format($companyRevenue) ?></div>
    <div class="stat-label">Corporate Revenue</div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h2 style="margin:0;">Corporate Companies</h2>
    <button class="btn btn-sm" onclick="togglePanel('company-form')">+ Add Company</button>
  </div>

  <div id="company-form" style="display:none;padding:1.25rem;border-top:1px solid var(--border);background:#f9fafb;">
    <form method="POST">
      <input type="hidden" name="action" value="add_company">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;">
        <div>
          <label class="form-label">Company Name *</label>
          <input name="name" class="form-input" placeholder="Acme Corp Pvt Ltd" required>
        </div>
        <div>
          <label class="form-label">GST Number</label>
          <input name="gst_number" class="form-input" placeholder="29AABCT1332L1ZM" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div>
          <label class="form-label">Email</label>
          <input name="email" type="email" class="form-input" placeholder="hr@company.com">
        </div>
        <div>
          <label class="form-label">Phone</label>
          <input name="phone" type="tel" class="form-input" placeholder="+91 80 1234 5678">
        </div>
        <div>
          <label class="form-label">Discount %</label>
          <input name="discount_pct" type="number" min="0" max="50" step="0.5" class="form-input" value="0">
        </div>
        <div>
          <label class="form-label">Credit Limit (₹)</label>
          <input name="credit_limit" type="number" min="0" class="form-input" value="0" placeholder="50000">
        </div>
        <div style="grid-column:span 2;">
          <label class="form-label">Address</label>
          <input name="address" class="form-input" placeholder="123 Corporate Park, Bangalore">
        </div>
      </div>
      <div style="margin-top:.75rem;">
        <button type="submit" class="btn btn-sm">Add Company</button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="togglePanel('company-form')" style="margin-left:.5rem;">Cancel</button>
      </div>
    </form>
  </div>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Company</th><th>GST</th><th>Email</th><th>Discount</th><th>Credit Limit</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($companies)): ?>
        <tr><td colspan="7" style="text-align:center;color:#888;padding:2rem;">No corporate accounts yet.</td></tr>
        <?php else: foreach ($companies as $c): ?>
        <tr>
          <td>
            <div style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></div>
            <div style="font-size:.78rem;color:#6b7280;"><?= htmlspecialchars($c['phone'] ?: '') ?></div>
          </td>
          <td style="font-family:monospace;font-size:.82rem;"><?= htmlspecialchars($c['gst_number'] ?: '—') ?></td>
          <td><?= htmlspecialchars($c['email'] ?: '—') ?></td>
          <td><?= $c['discount_pct'] > 0 ? $c['discount_pct'] . '%' : '—' ?></td>
          <td><?= $c['credit_limit'] > 0 ? '₹' . number_format($c['credit_limit']) : '—' ?></td>
          <td><span class="badge badge-<?= $c['is_active'] ? 'active' : 'inactive' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="toggle_company">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-xs btn-secondary"><?= $c['is_active'] ? 'Deactivate' : 'Activate' ?></button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this company?');">
              <input type="hidden" name="action" value="delete_company">
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

<?php endif; ?>

<style>
.tab-btn {
    padding:.6rem 1.25rem;font-size:.875rem;font-weight:600;text-decoration:none;
    color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-2px;
    transition:color .15s,border-color .15s;
}
.tab-btn.active, .tab-btn:hover { color:var(--primary);border-bottom-color:var(--primary); }
</style>

<script>
function togglePanel(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php renderAdminFoot(); ?>
