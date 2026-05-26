<?php
require_once __DIR__ . '/admin-shell.php';
require_once __DIR__ . '/wa-queue.php';
requireAdminAuth();
$propertyId = getCurrentPropertyId();

// POST handlers
$act = $_POST['action'] ?? '';
if ($act === 'save_template') {
    saveWaTemplate($_POST['trigger_type'], trim($_POST['message_body']), (int)($_POST['is_active'] ?? 1), $propertyId);
    header('Location: admin-whatsapp.php?flash=Template+saved'); exit;
}
if ($act === 'retry_message') {
    retryWaMessage((int)$_POST['msg_id']);
    header('Location: admin-whatsapp.php?flash=Message+queued+for+retry'); exit;
}
if ($act === 'send_test') {
    $msg = trim($_POST['test_message'] ?? 'Test message from Kanchi Farm Stay PMS. ✅');
    $ok  = sendWhatsAppToGuest(WHATSAPP_PHONE, $msg);
    header('Location: admin-whatsapp.php?flash=' . urlencode($ok ? 'Test sent!' : 'Send failed — check credentials')); exit;
}
if ($act === 'process_queue') {
    $r = processWaQueue($propertyId);
    header('Location: admin-whatsapp.php?flash=' . urlencode("Processed: sent {$r['sent']}, failed {$r['failed']}")); exit;
}

$templates = getWaTemplates($propertyId);
$history   = getWaQueueHistory($propertyId, 100);

$templateLabels = [
    'booking_confirmed' => ['✅', 'Booking Confirmed (Immediate)'],
    'pre_stay_t3'       => ['📅', 'Pre-Stay: 3 Days Before'],
    'pre_stay_t1'       => ['🏡', 'Pre-Stay: 1 Day Before'],
    'post_checkout_t1'  => ['⭐', 'Post-Checkout: Review Request'],
    'abandonment'       => ['🔔', 'Abandoned Booking Recovery'],
];

renderAdminHead('WhatsApp Workflows');
?>
<div class="layout">
<?php renderSidebar('whatsapp'); renderTopbar('WhatsApp Workflows', '
  <form method="POST" style="display:inline;">
    <input type="hidden" name="action" value="process_queue">
    <button class="sync-btn" type="submit">▶ Run Queue Now</button>
  </form>
'); ?>

<?php renderFlash(); ?>

<!-- Queue Stats -->
<?php
$pending = array_filter($history, fn($m) => $m['status'] === 'pending');
$sent    = array_filter($history, fn($m) => $m['status'] === 'sent');
$failed  = array_filter($history, fn($m) => $m['status'] === 'failed');
?>
<div class="stats-row">
  <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-val"><?= count($pending) ?></div><div class="stat-lbl">Pending Messages</div></div>
  <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-val"><?= count($sent) ?></div><div class="stat-lbl">Sent (Last 100)</div></div>
  <div class="stat-card"><div class="stat-icon">❌</div><div class="stat-val"><?= count($failed) ?></div><div class="stat-lbl">Failed</div></div>
  <div class="stat-card"><div class="stat-icon">💬</div><div class="stat-val"><?= count($history) ?></div><div class="stat-lbl">Total in Log</div></div>
</div>

<!-- Test Message -->
<div class="panel">
  <div class="panel-hd"><h3>Send Test Message</h3><div class="sub">Sends to your admin WhatsApp number</div></div>
  <div class="panel-bd">
    <form method="POST" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="send_test">
      <div class="fld" style="flex:1;min-width:240px;">
        <label>Message</label>
        <input type="text" name="test_message" value="Test from Kanchi Farm Stay PMS ✅" required>
      </div>
      <button type="submit" class="btn btn-primary">Send Test</button>
    </form>
  </div>
</div>

<!-- Message Templates -->
<div class="panel">
  <div class="panel-hd"><h3>Workflow Message Templates</h3><div class="sub">Customise what gets sent at each stage of the guest journey. Use {{guest_name}}, {{room_name}}, {{check_in}}, {{check_out}}, {{amount}}, {{room_id}}, {{google_review_url}}, {{airbnb_review_url}}</div></div>
  <div class="panel-bd">
    <?php
    $tmplMap = [];
    foreach ($templates as $t) $tmplMap[$t['trigger_type']] = $t;

    foreach ($templateLabels as $type => [$icon, $label]):
      $tmpl = $tmplMap[$type] ?? ['message_body' => '', 'is_active' => 1, 'trigger_type' => $type];
    ?>
    <div class="panel" style="margin-bottom:.85rem;">
      <div class="panel-hd" style="background:#f7faf7;">
        <div>
          <h3><?= $icon ?> <?= $label ?></h3>
          <div class="sub" style="font-family:monospace;font-size:.72rem;">trigger: <?= $type ?></div>
        </div>
        <label class="toggle-wrap" style="cursor:pointer;">
          <span style="font-size:.78rem;color:var(--text-muted);margin-right:.35rem;">Active</span>
          <label class="toggle">
            <input type="checkbox" id="active-<?= $type ?>" <?= $tmpl['is_active'] ? 'checked' : '' ?> onchange="saveToggle('<?= $type ?>', this.checked)">
            <span class="slider"></span>
          </label>
        </label>
      </div>
      <div class="panel-bd">
        <form method="POST">
          <input type="hidden" name="action" value="save_template">
          <input type="hidden" name="trigger_type" value="<?= $type ?>">
          <input type="hidden" name="is_active" id="hidden-active-<?= $type ?>" value="<?= $tmpl['is_active'] ? 1 : 0 ?>">
          <div class="fld" style="margin-bottom:.75rem;">
            <textarea name="message_body" rows="5" style="font-size:.84rem;line-height:1.55;"><?= htmlspecialchars($tmpl['message_body']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Save Template</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Message Queue Log -->
<div class="panel">
  <div class="panel-hd"><h3>Message Queue Log</h3><div class="sub">Last 100 messages</div></div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Trigger</th><th>Guest</th><th>Phone</th><th>Scheduled</th><th>Status</th><th>Sent At</th><th>Error</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--text-muted);">No messages yet. Messages appear here after bookings are confirmed.</td></tr>
        <?php else: ?>
          <?php foreach ($history as $m): ?>
          <tr>
            <td class="muted"><?= $m['id'] ?></td>
            <td><span class="badge badge-grey" style="font-size:.7rem;"><?= htmlspecialchars($m['trigger_type']) ?></span></td>
            <td><?= htmlspecialchars($m['guest_name'] ?? '—') ?></td>
            <td class="muted"><?= htmlspecialchars($m['phone']) ?></td>
            <td class="muted"><?= $m['scheduled_at'] ?></td>
            <td>
              <?php if ($m['status'] === 'sent'): ?>
                <span class="wa-status-sent">✅ Sent</span>
              <?php elseif ($m['status'] === 'failed'): ?>
                <span class="wa-status-failed">❌ Failed (<?= $m['attempts'] ?>x)</span>
              <?php else: ?>
                <span class="wa-status-pending">⏳ Pending</span>
              <?php endif; ?>
            </td>
            <td class="muted"><?= $m['sent_at'] ?? '—' ?></td>
            <td class="muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($m['last_error']) ?>"><?= htmlspecialchars(substr($m['last_error'], 0, 40)) ?></td>
            <td>
              <?php if ($m['status'] === 'failed'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="retry_message">
                  <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                  <button class="btn btn-warn btn-sm">Retry</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function saveToggle(type, checked) {
    document.getElementById('hidden-active-' + type).value = checked ? 1 : 0;
}
</script>

<?php renderAdminFoot(); ?>
