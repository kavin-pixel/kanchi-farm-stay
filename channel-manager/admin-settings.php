<?php
require_once __DIR__ . '/admin-shell.php';
requireAdminAuth();
$propertyId = getCurrentPropertyId();

$act = $_POST['action'] ?? '';
if ($act === 'save_property') {
    updateProperty($propertyId, ['name'=>trim($_POST['name']),'address'=>trim($_POST['address']),'city'=>trim($_POST['city']),'state'=>trim($_POST['state']),'phone'=>trim($_POST['phone']),'email'=>trim($_POST['email']),'website_url'=>trim($_POST['website_url']),'description'=>trim($_POST['description'])]);
    header('Location: admin-settings.php?flash=Property+details+saved'); exit;
}
if ($act === 'add_property') {
    $name = trim($_POST['new_property_name']);
    if ($name) addProperty(['name'=>$name,'city'=>trim($_POST['new_property_city']??'')]);
    header('Location: admin-settings.php?flash=Property+added'); exit;
}

$prop = getProperty($propertyId);
$allProps = getAllProperties();

renderAdminHead('Settings');
?>
<div class="layout">
<?php renderSidebar('settings'); renderTopbar('Settings'); ?>
<?php renderFlash(); ?>

<!-- Property Details -->
<div class="panel">
  <div class="panel-hd"><h3>Property Details</h3><div class="sub">This information is used in emails, WhatsApp messages, and SEO</div></div>
  <div class="panel-bd">
    <form method="POST">
      <input type="hidden" name="action" value="save_property">
      <div class="form-grid">
        <div class="fld"><label>Property Name *</label><input type="text" name="name" value="<?= htmlspecialchars($prop['name'] ?? '') ?>" required></div>
        <div class="fld"><label>City</label><input type="text" name="city" value="<?= htmlspecialchars($prop['city'] ?? '') ?>"></div>
        <div class="fld"><label>State</label><input type="text" name="state" value="<?= htmlspecialchars($prop['state'] ?? '') ?>"></div>
        <div class="fld"><label>Phone</label><input type="tel" name="phone" value="<?= htmlspecialchars($prop['phone'] ?? '') ?>"></div>
        <div class="fld"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($prop['email'] ?? '') ?>"></div>
        <div class="fld"><label>Website URL</label><input type="url" name="website_url" value="<?= htmlspecialchars($prop['website_url'] ?? SITE_URL) ?>"></div>
        <div class="fld" style="grid-column:span 3"><label>Address</label><input type="text" name="address" value="<?= htmlspecialchars($prop['address'] ?? '') ?>"></div>
        <div class="fld" style="grid-column:span 3"><label>Description</label><textarea name="description" rows="3"><?= htmlspecialchars($prop['description'] ?? '') ?></textarea></div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:.85rem;">Save Property Details</button>
    </form>
  </div>
</div>

<!-- WhatsApp & API Config -->
<div class="panel">
  <div class="panel-hd"><h3>WhatsApp & Notifications</h3><div class="sub">Edit credentials in <code>config.php</code> on the server</div></div>
  <div class="panel-bd">
    <div class="info-box">
      <h4>Current Configuration</h4>
      Provider: <strong><?= WHATSAPP_PROVIDER ?></strong><br>
      Admin Phone: <strong><?= WHATSAPP_PHONE ?></strong><br>
      CallMeBot Key: <strong><?= CALLMEBOT_API_KEY === 'YOUR_API_KEY_HERE' ? '⚠️ Not configured' : '✓ Set' ?></strong><br>
      Meta Token: <strong><?= META_WA_TOKEN ? '✓ Set' : 'Not configured' ?></strong><br><br>
      To change: edit <code><?= __DIR__ ?>/config.php</code>
    </div>
  </div>
</div>

<!-- Razorpay -->
<div class="panel">
  <div class="panel-hd"><h3>Payment Gateway (Razorpay)</h3></div>
  <div class="panel-bd">
    <div class="info-box">
      <h4>Current Configuration</h4>
      Key ID: <strong><?= RAZORPAY_KEY_ID ? htmlspecialchars(substr(RAZORPAY_KEY_ID,0,12).'…') : '⚠️ Not set' ?></strong><br>
      Key Secret: <strong><?= RAZORPAY_KEY_SECRET ? '✓ Set' : '⚠️ Not set — Refunds will fail' ?></strong><br><br>
      To change: edit <code><?= __DIR__ ?>/config.php</code>
    </div>
  </div>
</div>

<!-- Google Hotel Ads -->
<div class="panel">
  <div class="panel-hd"><h3>Google Hotel Ads Setup</h3></div>
  <div class="panel-bd">
    <div class="howto">
      <h4>Step 1 — Free Booking Links (Available Now)</h4>
      Your website has <strong>LodgingBusiness schema markup</strong> which allows Google to surface your direct booking link in search results for free.<br><br>
      ✅ Schema markup: Added to index.html<br>
      ✅ Sitemap: <code><?= SITE_URL ?>/sitemap.xml</code><br><br>
      <strong>Action required:</strong> Submit your sitemap to <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a>.<br><br>
      <h4>Step 2 — Paid Hotel Ads (When Ready)</h4>
      1. Create a Google Hotel Center account at <a href="https://hotel-ads.google.com" target="_blank">hotel-ads.google.com</a><br>
      2. Link it to your Google Business Profile<br>
      3. Submit this price feed URL:<br>
      <code><?= SITE_URL ?>/channel-manager/price-feed.php?token=<?= PRICE_FEED_TOKEN ?></code><br><br>
      The price feed automatically includes dynamic pricing for all 10 rooms for the next 90 days.
    </div>
  </div>
</div>

<!-- Multi-Property -->
<div class="panel">
  <div class="panel-hd"><h3>Multi-Property Management</h3></div>
  <div class="panel-bd">
    <div style="margin-bottom:1rem;">
      <strong>Current Properties:</strong>
      <table class="tbl" style="margin-top:.6rem;">
        <thead><tr><th>#</th><th>Name</th><th>City</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($allProps as $p): ?>
          <tr>
            <td class="muted"><?= $p['id'] ?></td>
            <td class="room-name"><?= htmlspecialchars($p['name']) ?> <?= $p['id'] == $propertyId ? '<span class="badge badge-green" style="font-size:.68rem;">Active</span>' : '' ?></td>
            <td class="muted"><?= htmlspecialchars($p['city']) ?></td>
            <td><span class="<?= $p['is_active'] ? 'status-confirmed' : 'status-cancelled' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <form method="POST" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="add_property">
      <div class="fld"><label>New Property Name</label><input type="text" name="new_property_name" placeholder="e.g. Kanchi Beach Stay"></div>
      <div class="fld"><label>City</label><input type="text" name="new_property_city" placeholder="Chennai"></div>
      <button type="submit" class="btn btn-primary">+ Add Property</button>
    </form>
  </div>
</div>

<!-- Cron Status -->
<div class="panel">
  <div class="panel-hd"><h3>Automation & Cron</h3></div>
  <div class="panel-bd">
    <div class="howto">
      <h4>Cron Job Command (run every 30 minutes)</h4>
      <code>php <?= __DIR__ ?>/cron.php</code><br><br>
      <h4>What the cron does:</h4>
      — Syncs all iCal calendars from OTAs<br>
      — Sends queued WhatsApp messages (pre-stay, post-checkout, abandonment)<br>
      — Checks for abandoned bookings (order created but no payment in 2hrs)<br>
      — Generates AI pricing suggestions (once daily at 2am)<br>
      — Takes revenue snapshot for analytics (once daily at midnight)
    </div>
    <div style="margin-top:1rem;">
      <a href="cron.php?token=<?= CRON_SECRET ?>" class="btn btn-info" target="_blank">Run Cron Manually</a>
    </div>
  </div>
</div>

<?php renderAdminFoot(); ?>
