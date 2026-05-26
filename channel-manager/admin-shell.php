<?php
/**
 * Kanchi Farm Stay — Shared Admin Shell
 * require_once this file at the top of every admin page.
 * Handles auth, outputs <head>, sidebar, and topbar.
 */
if (!defined('ADMIN_SHELL')) define('ADMIN_SHELL', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!session_id()) session_start();

// ── Auth ──────────────────────────────────────────────────────────
function requireAdminAuth(): void {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: ' . rtrim(SITE_URL, '/') . '/channel-manager/admin.php');
        exit;
    }
}

function getCurrentPropertyId(): int {
    return (int)($_SESSION['current_property_id'] ?? 1);
}

// ── Handle property switch ────────────────────────────────────────
if (!empty($_SESSION['admin_logged_in']) && ($_POST['action'] ?? '') === 'switch_property') {
    $_SESSION['current_property_id'] = (int)($_POST['property_id'] ?? 1);
    $back = $_SERVER['HTTP_REFERER'] ?? 'admin.php';
    header('Location: ' . $back);
    exit;
}

// ── Output helpers ────────────────────────────────────────────────
function renderAdminHead(string $title = 'Admin'): void {
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — Kanchi Farm Stay PMS</title>
<link rel="stylesheet" href="admin-styles.css">
<style>
/* page-level overrides can go here */
</style>
</head>
<body>
    <?php
}

function renderSidebar(string $activeSection = ''): void {
    $prop = getProperty(getCurrentPropertyId());
    $propName = $prop['name'] ?? 'Kanchi Farm Stay';
    $allProps = getAllProperties();

    $navItems = [
        'dashboard'   => ['📊', 'Dashboard',      'admin.php'],
        'calendar'    => ['📅', 'Calendar',        'admin.php?section=calendar'],
        'bookings'    => ['📋', 'Bookings',        'admin.php?section=bookings'],
        'guests'      => ['👤', 'Guests',          'admin-guests.php'],
        'channels'    => ['🔗', 'Channels',        'admin-channels.php'],
        'pricing'     => ['💡', 'AI Pricing',      'admin-pricing.php'],
        'campaigns'   => ['🎟️',  'Campaigns',       'admin-campaigns.php'],
        'whatsapp'    => ['💬', 'WhatsApp',        'admin-whatsapp.php'],
        'revenue'     => ['📈', 'Revenue',         'admin-revenue.php'],
        'reputation'  => ['⭐', 'Reputation',      'admin-reputation.php'],
        'agents'      => ['🤝', 'Agents & Corp.',  'admin-agents.php'],
        'night-audit' => ['🌙', 'Night Audit',     'admin-night-audit.php'],
        'logs'        => ['📜', 'Logs',            'admin-logs.php'],
        'settings'    => ['⚙️',  'Settings',        'admin-settings.php'],
    ];
    ?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="name"><?= htmlspecialchars($propName) ?></div>
    <div class="sub">Property Management</div>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($navItems as $key => [$icon, $label, $href]): ?>
      <a href="<?= $href ?>" class="nav-item <?= $activeSection === $key ? 'active' : '' ?>">
        <span class="nav-icon"><?= $icon ?></span>
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if (count($allProps) > 1): ?>
  <div class="sidebar-prop-switch">
    <form method="POST">
      <input type="hidden" name="action" value="switch_property">
      <label class="prop-switch-label">Property</label>
      <select name="property_id" onchange="this.form.submit()" class="prop-switch-select">
        <?php foreach ($allProps as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $p['id'] == getCurrentPropertyId() ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <?php endif; ?>

  <div class="sidebar-bottom">
    <a href="/">← View website</a>
    <a href="admin.php?action=logout" style="margin-top:.4rem;display:block;color:#e57373;">Sign out</a>
  </div>
</aside>
    <?php
}

function renderTopbar(string $pageTitle = '', string $extra = ''): void {
    ?>
<div class="main">
  <div class="topbar">
    <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
    <div class="topbar-right">
      <span><?= date('D, d M Y') ?></span>
      <?= $extra ?>
    </div>
  </div>
  <div class="content">
    <?php
}

function renderAdminFoot(): void {
    ?>
  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->
</body>
</html>
    <?php
}

function renderFlash(): void {
    $flash = htmlspecialchars($_GET['flash'] ?? '');
    if ($flash) echo "<div class='flash'>✓ {$flash}</div>";
}
