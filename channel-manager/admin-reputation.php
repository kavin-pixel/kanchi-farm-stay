<?php
require_once __DIR__ . '/admin-shell.php';
requireAdminAuth();
$propertyId = getCurrentPropertyId();

$act = $_POST['action'] ?? '';
if ($act === 'add_review') {
    addReview(['platform'=>$_POST['platform'],'reviewer_name'=>trim($_POST['reviewer_name']),'rating'=>(int)$_POST['rating'],'review_text'=>trim($_POST['review_text']),'review_url'=>trim($_POST['review_url']??''),'review_date'=>$_POST['review_date']??date('Y-m-d')], $propertyId);
    header('Location: admin-reputation.php?flash=Review+added'); exit;
}
if ($act === 'update_response') {
    updateReviewResponse((int)$_POST['review_id'], trim($_POST['response_text']));
    header('Location: admin-reputation.php?flash=Response+saved'); exit;
}
if ($act === 'delete_review') {
    deleteReview((int)$_POST['review_id']);
    header('Location: admin-reputation.php?flash=Review+removed'); exit;
}
if ($act === 'save_template') {
    saveReviewTemplate(['id'=>$_POST['template_id']??null,'name'=>trim($_POST['name']),'rating_min'=>(int)$_POST['rating_min'],'rating_max'=>(int)$_POST['rating_max'],'template_text'=>trim($_POST['template_text'])], $propertyId);
    header('Location: admin-reputation.php?flash=Template+saved'); exit;
}
if ($act === 'delete_template') {
    deleteReviewTemplate((int)$_POST['template_id']);
    header('Location: admin-reputation.php?flash=Template+removed'); exit;
}
if ($act === 'save_review_links') {
    setSetting('google_review_url', trim($_POST['google_review_url']), $propertyId);
    setSetting('airbnb_review_url', trim($_POST['airbnb_review_url']), $propertyId);
    setSetting('booking_review_url',trim($_POST['booking_review_url']),$propertyId);
    header('Location: admin-reputation.php?flash=Review+links+saved'); exit;
}

$reviews   = getReviews($propertyId);
$stats     = getReputationStats($propertyId);
$templates = getReviewTemplates($propertyId);
$gUrl      = getSetting('google_review_url',  $propertyId);
$aUrl      = getSetting('airbnb_review_url',  $propertyId);
$bUrl      = getSetting('booking_review_url', $propertyId);

function stars(int $n): string {
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}

renderAdminHead('Reputation Management');
?>
<div class="layout">
<?php renderSidebar('reputation'); renderTopbar('Reputation Management'); ?>
<?php renderFlash(); ?>

<!-- Score Dashboard -->
<div class="stats-row">
  <div class="stat-card"><div class="stat-icon">⭐</div><div class="stat-val"><?= $stats['overall_avg'] ?></div><div class="stat-lbl">Overall Rating / 5</div></div>
  <div class="stat-card"><div class="stat-icon">📝</div><div class="stat-val"><?= $stats['total'] ?></div><div class="stat-lbl">Total Reviews</div></div>
  <div class="stat-card"><div class="stat-icon">💬</div><div class="stat-val"><?= count(array_filter($reviews, fn($r) => $r['response_text'])) ?></div><div class="stat-lbl">Responded</div></div>
  <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-val"><?= count(array_filter($reviews, fn($r) => !$r['response_text'])) ?></div><div class="stat-lbl">Awaiting Response</div></div>
</div>

<!-- Platform Breakdown -->
<?php if (!empty($stats['by_platform'])): ?>
<div class="panel">
  <div class="panel-hd"><h3>By Platform</h3></div>
  <div class="panel-bd">
    <?php foreach ($stats['by_platform'] as $p):
      $fill = match(strtolower($p['platform'])) {'google'=>'#4285F4','airbnb'=>'#FF5A5F','booking.com','booking'=>'#003580','agoda'=>'#EB1A23',default=>'#546e7a'};
    ?>
    <div class="rep-platform-row">
      <div class="ota-badge" style="background:<?= $fill ?>;width:32px;height:32px;font-size:.85rem;"><?= strtoupper(substr($p['platform'],0,2)) ?></div>
      <div style="flex:1;font-size:.88rem;font-weight:600;"><?= htmlspecialchars(ucfirst($p['platform'])) ?></div>
      <div class="stars"><?= str_repeat('★', (int)round($p['avg_rating'])) ?></div>
      <div style="font-size:.85rem;font-weight:700;margin-left:.5rem;"><?= round($p['avg_rating'], 1) ?>/5</div>
      <div style="font-size:.78rem;color:var(--text-muted);margin-left:.75rem;"><?= $p['count'] ?> reviews</div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Review Links -->
<div class="panel">
  <div class="panel-hd"><h3>Review Request Links</h3><div class="sub">These links are used in the post-checkout WhatsApp message</div></div>
  <div class="panel-bd">
    <form method="POST">
      <input type="hidden" name="action" value="save_review_links">
      <div class="form-grid">
        <div class="fld"><label>Google Review URL</label><input type="url" name="google_review_url" value="<?= htmlspecialchars($gUrl) ?>" placeholder="https://search.google.com/local/writereview?placeid=..."></div>
        <div class="fld"><label>Airbnb Profile URL</label><input type="url" name="airbnb_review_url" value="<?= htmlspecialchars($aUrl) ?>" placeholder="https://www.airbnb.co.in/users/show/..."></div>
        <div class="fld"><label>Booking.com Review URL</label><input type="url" name="booking_review_url" value="<?= htmlspecialchars($bUrl) ?>" placeholder="https://www.booking.com/hotel/..."></div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:.85rem;">Save Links</button>
    </form>
    <div class="info-box" style="margin-top:1rem;">
      <h4>How to get your Google Review link</h4>
      1. Go to <a href="https://business.google.com" target="_blank" style="color:#01579b;">business.google.com</a><br>
      2. Select your property → Get more reviews → Copy the link<br>
      3. Paste it above. This link goes directly to the review form.
    </div>
  </div>
</div>

<!-- Response Templates -->
<div class="panel">
  <div class="panel-hd"><h3>Response Templates</h3><div class="sub">Use {{name}} to insert reviewer's name</div></div>
  <div class="panel-bd">
    <form method="POST" style="margin-bottom:1.25rem;padding-bottom:1.25rem;border-bottom:1px solid var(--border);">
      <input type="hidden" name="action" value="save_template">
      <input type="hidden" name="template_id" value="">
      <div class="form-grid">
        <div class="fld"><label>Template Name *</label><input type="text" name="name" required placeholder="e.g. 5-Star Thank You"></div>
        <div class="fld"><label>Min Rating</label><input type="number" name="rating_min" min="1" max="5" value="5"></div>
        <div class="fld"><label>Max Rating</label><input type="number" name="rating_max" min="1" max="5" value="5"></div>
        <div class="fld" style="grid-column:span 3"><label>Template Text *</label><textarea name="template_text" rows="3" required placeholder="Thank you {{name}} for your kind review!"></textarea></div>
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.6rem;">+ Add Template</button>
    </form>
    <?php foreach ($templates as $tmpl): ?>
    <div class="panel" style="margin-bottom:.65rem;">
      <div class="panel-hd" style="background:#f7faf7;padding:.6rem 1rem;">
        <div>
          <span style="font-weight:700;font-size:.88rem;"><?= htmlspecialchars($tmpl['name']) ?></span>
          <span class="badge badge-grey" style="margin-left:.5rem;font-size:.68rem;">Rating <?= $tmpl['rating_min'] ?>–<?= $tmpl['rating_max'] ?></span>
        </div>
        <form method="POST" onsubmit="return confirm('Delete template?')">
          <input type="hidden" name="action" value="delete_template">
          <input type="hidden" name="template_id" value="<?= $tmpl['id'] ?>">
          <button class="btn btn-danger btn-sm">Delete</button>
        </form>
      </div>
      <div class="panel-bd" style="padding:.75rem 1rem;font-size:.84rem;color:var(--text-muted);line-height:1.55;"><?= nl2br(htmlspecialchars($tmpl['template_text'])) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Add Review -->
<div class="panel">
  <div class="panel-hd"><h3>Log a Review</h3><div class="sub">Manually add reviews from any platform</div></div>
  <div class="panel-bd">
    <form method="POST">
      <input type="hidden" name="action" value="add_review">
      <div class="form-grid">
        <div class="fld"><label>Platform *</label>
          <select name="platform">
            <option value="google">Google</option>
            <option value="airbnb">Airbnb</option>
            <option value="booking.com">Booking.com</option>
            <option value="agoda">Agoda</option>
            <option value="tripadvisor">TripAdvisor</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="fld"><label>Reviewer Name</label><input type="text" name="reviewer_name" placeholder="Guest Name"></div>
        <div class="fld"><label>Rating (1–5) *</label><input type="number" name="rating" min="1" max="5" value="5" required></div>
        <div class="fld"><label>Review Date</label><input type="date" name="review_date" value="<?= date('Y-m-d') ?>"></div>
        <div class="fld"><label>Review URL</label><input type="url" name="review_url" placeholder="Link to the review"></div>
        <div class="fld" style="grid-column:span 3"><label>Review Text</label><textarea name="review_text" rows="3" placeholder="Paste the review text here..."></textarea></div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:.85rem;">Add Review</button>
    </form>
  </div>
</div>

<!-- Reviews Log -->
<div class="panel">
  <div class="panel-hd"><h3>All Reviews</h3><span class="badge badge-grey"><?= count($reviews) ?> total</span></div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Date</th><th>Platform</th><th>Reviewer</th><th>Rating</th><th>Review</th><th>Response</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($reviews)): ?>
          <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted);">No reviews logged yet.</td></tr>
        <?php else: ?>
          <?php foreach ($reviews as $rv): ?>
          <tr>
            <td class="muted"><?= $rv['review_date'] ?></td>
            <td><?php
              $fill = match(strtolower($rv['platform'])) {'google'=>'#4285F4','airbnb'=>'#FF5A5F','booking.com'=>'#003580','agoda'=>'#EB1A23',default=>'#546e7a'};
              echo "<span class='badge' style='background:{$fill}'>" . htmlspecialchars(ucfirst($rv['platform'])) . "</span>";
            ?></td>
            <td><?= htmlspecialchars($rv['reviewer_name'] ?: '—') ?></td>
            <td><span class="stars" style="font-size:.85rem;"><?= str_repeat('★',$rv['rating']) ?><?= str_repeat('☆',5-$rv['rating']) ?></span></td>
            <td style="max-width:200px;font-size:.78rem;"><?= htmlspecialchars(substr($rv['review_text'],0,100)) ?><?= strlen($rv['review_text'])>100?'…':'' ?></td>
            <td style="max-width:180px;font-size:.76rem;color:var(--text-muted);">
              <?= $rv['response_text'] ? htmlspecialchars(substr($rv['response_text'],0,80)).'…' : '<span style="color:var(--warn);">No response</span>' ?>
            </td>
            <td style="white-space:nowrap;">
              <button class="btn btn-info btn-sm" onclick="openResponse(<?= $rv['id'] ?>, this)">Respond</button>
              <form method="POST" style="display:inline;margin-left:3px;" onsubmit="return confirm('Remove?')">
                <input type="hidden" name="action" value="delete_review">
                <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                <button class="btn btn-danger btn-sm">Del</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Response Modal -->
<div id="respModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;padding:2rem;width:500px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);">
    <h3 style="margin-bottom:1rem;">Write Response</h3>
    <form method="POST" id="resp-form">
      <input type="hidden" name="action" value="update_response">
      <input type="hidden" name="review_id" id="resp-id">
      <div class="fld" style="margin-bottom:.75rem;">
        <label>Response</label>
        <textarea name="response_text" id="resp-text" rows="5" style="width:100%;"></textarea>
      </div>
      <div style="margin-bottom:.75rem;">
        <label style="font-size:.76rem;font-weight:600;color:var(--text-muted);">Quick templates:</label>
        <div style="display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.3rem;">
          <?php foreach ($templates as $t): ?>
          <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('resp-text').value = '<?= htmlspecialchars(addslashes($t['template_text'])) ?>'">
            <?= htmlspecialchars($t['name']) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="display:flex;gap:.75rem;">
        <button type="submit" class="btn btn-primary">Save Response</button>
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('respModal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openResponse(id, btn) {
    document.getElementById('resp-id').value = id;
    document.getElementById('resp-text').value = '';
    document.getElementById('respModal').style.display = 'flex';
}
</script>

<?php renderAdminFoot(); ?>
