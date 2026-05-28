<?php
/**
 * Kanchi Farm Stay — Shared Admin Shell
 * require_once this file at the top of every admin page.
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
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — Kanchi Farm Stay PMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="admin-styles.css">
</head>
<body>
    <?php
}

function renderSidebar(string $activeSection = ''): void {
    $prop = getProperty(getCurrentPropertyId());
    $propName = $prop['name'] ?? 'Kanchi Farm Stay';
    $allProps = getAllProperties();

    $navGroups = [
        'Operations' => [
            'dashboard'   => ['fa-solid fa-gauge',           'Dashboard',      'admin.php'],
            'calendar'    => ['fa-solid fa-calendar-days',   'Calendar',       'admin.php?section=calendar'],
            'bookings'    => ['fa-solid fa-calendar-check',  'Bookings',       'admin.php?section=bookings'],
            'guests'      => ['fa-solid fa-users',           'Guests',         'admin-guests.php'],
            'night-audit' => ['fa-solid fa-moon',            'Night Audit',    'admin-night-audit.php'],
        ],
        'Revenue' => [
            'channels'    => ['fa-solid fa-link',             'Channels',       'admin-channels.php'],
            'pricing'     => ['fa-solid fa-bolt',             'AI Pricing',     'admin-pricing.php'],
            'campaigns'   => ['fa-solid fa-tag',              'Campaigns',      'admin-campaigns.php'],
            'revenue'     => ['fa-solid fa-chart-line',       'Revenue',        'admin-revenue.php'],
            'agents'      => ['fa-solid fa-handshake',        'Agents & Corp.', 'admin-agents.php'],
        ],
        'Engagement' => [
            'whatsapp'    => ['fa-brands fa-whatsapp',        'WhatsApp',       'admin-whatsapp.php'],
            'reputation'  => ['fa-solid fa-star',             'Reputation',     'admin-reputation.php'],
        ],
        'System' => [
            'logs'        => ['fa-solid fa-scroll',           'Logs',           'admin-logs.php'],
            'settings'    => ['fa-solid fa-gear',             'Settings',       'admin-settings.php'],
        ],
    ];
    ?>
<div class="layout" id="layout">
<aside class="sidebar" id="sidebar">
  <button class="sidebar-toggle-btn" id="sidebar-toggle-btn" data-tip="Collapse sidebar" data-testid="btn-sidebar-toggle">
    <i class="fa-solid fa-chevron-left"></i>
  </button>
  <div class="sidebar-brand">
    <div class="brand-row">
      <div class="brand-icon"><i class="fa-solid fa-seedling"></i></div>
      <div>
        <div class="name"><?= htmlspecialchars($propName) ?></div>
        <div class="sub">PMS Dashboard</div>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($navGroups as $groupLabel => $navItems): ?>
      <div class="sidebar-section-label"><?= $groupLabel ?></div>
      <?php foreach ($navItems as $key => [$iconClass, $label, $href]): ?>
        <a href="<?= $href ?>" class="nav-item <?= $activeSection === $key ? 'active' : '' ?>" data-testid="nav-<?= $key ?>">
          <span class="nav-icon"><i class="<?= $iconClass ?>"></i></span>
          <span class="nav-label"><?= $label ?></span>
        </a>
      <?php endforeach; ?>
      <div class="sidebar-divider"></div>
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
    <a href="/" data-testid="nav-view-website"><i class="fa-solid fa-arrow-up-right-from-square"></i> <span>View website</span></a>
    <a href="admin.php?action=logout" class="signout" data-testid="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> <span>Sign out</span></a>
  </div>
</aside>
<div id="sidebar-overlay" class="sidebar-overlay"></div>
    <?php
}

function renderTopbar(string $pageTitle = '', string $extra = ''): void {
    ?>
<div class="main" id="main-area">
  <div class="topbar">
    <div class="topbar-left">
      <button class="topbar-hamburger" id="sidebar-hamburger" data-testid="btn-hamburger">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
    </div>
    <div class="topbar-right">
      <span class="topbar-date"><i class="fa-regular fa-calendar"></i> <?= date('D, d M Y') ?></span>
      <?= $extra ?>
      <button class="btn btn-icon" onclick="document.getElementById('kb-modal').classList.toggle('open')" data-tip="Keyboard shortcuts (?)" data-testid="btn-shortcuts">
        <i class="fa-solid fa-keyboard"></i>
      </button>
    </div>
  </div>
  <div class="content">
    <?php
}

function renderFlash(): void {
    $flash = htmlspecialchars($_GET['flash'] ?? '');
    if ($flash) echo "<div class='flash' data-testid='flash-message'><i class='fa-solid fa-circle-check'></i> {$flash}</div>";
}

function renderAdminFoot(): void {
    ?>
  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->

<!-- Keyboard Shortcuts Modal -->
<div class="kb-modal-overlay" id="kb-modal">
  <div class="kb-modal">
    <div class="kb-modal-head">
      <h3><i class="fa-solid fa-keyboard" style="margin-right:.5rem;color:var(--accent);"></i>Keyboard Shortcuts</h3>
      <button class="btn-icon btn" onclick="document.getElementById('kb-modal').classList.remove('open')" data-testid="btn-close-shortcuts"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="kb-row"><span>Close modal / drawer</span><div class="kb-keys"><span class="kb-key">Esc</span></div></div>
    <div class="kb-row"><span>Show shortcuts</span><div class="kb-keys"><span class="kb-key">?</span></div></div>
  </div>
</div>

<div id="ui-tooltip"></div>

<script>
(function() {
  // ── Sidebar collapse (desktop) ───────────────────────────────────
  const layoutEl   = document.getElementById('layout');
  const sidebarEl  = document.getElementById('sidebar');
  const toggleBtn  = document.getElementById('sidebar-toggle-btn');
  const mainEl     = document.getElementById('main-area');

  function applySidebarState(collapsed) {
    if (collapsed) {
      layoutEl && layoutEl.classList.add('sidebar-collapsed');
      sidebarEl && sidebarEl.classList.add('collapsed');
    } else {
      layoutEl && layoutEl.classList.remove('sidebar-collapsed');
      sidebarEl && sidebarEl.classList.remove('collapsed');
    }
  }

  if (toggleBtn) {
    // Restore saved state
    applySidebarState(localStorage.getItem('sb-collapsed') === '1');
    toggleBtn.addEventListener('click', () => {
      const isNowCollapsed = !sidebarEl.classList.contains('collapsed');
      applySidebarState(isNowCollapsed);
      localStorage.setItem('sb-collapsed', isNowCollapsed ? '1' : '0');
    });
  }

  // ── Mobile hamburger ─────────────────────────────────────────────
  const hamburger = document.getElementById('sidebar-hamburger');
  const overlay   = document.getElementById('sidebar-overlay');
  if (hamburger && overlay && sidebarEl) {
    hamburger.addEventListener('click', () => {
      sidebarEl.classList.toggle('mobile-open');
      overlay.classList.toggle('open');
    });
    overlay.addEventListener('click', () => {
      sidebarEl.classList.remove('mobile-open');
      overlay.classList.remove('open');
    });
  }

  // ── Tooltip ──────────────────────────────────────────────────────
  const tip = document.getElementById('ui-tooltip');
  if (tip) {
    document.addEventListener('mouseover', (e) => {
      const el = e.target.closest('[data-tip]');
      if (el) { tip.textContent = el.dataset.tip; tip.style.opacity = '1'; }
    });
    document.addEventListener('mousemove', (e) => {
      if (tip.style.opacity === '1') {
        const x = e.clientX + 14;
        const y = e.clientY - 30;
        const maxX = window.innerWidth - tip.offsetWidth - 8;
        tip.style.left = Math.min(x, maxX) + 'px';
        tip.style.top  = y + 'px';
      }
    });
    document.addEventListener('mouseout', (e) => {
      if (!e.target.closest('[data-tip]')) tip.style.opacity = '0';
    });
  }

  // ── Keyboard shortcuts ───────────────────────────────────────────
  document.addEventListener('keydown', (e) => {
    const active = document.activeElement;
    if (active && (active.tagName==='INPUT'||active.tagName==='TEXTAREA'||active.tagName==='SELECT')) return;
    const kbModal = document.getElementById('kb-modal');
    if (e.key === '?' || e.key === 'F1') { e.preventDefault(); kbModal && kbModal.classList.toggle('open'); }
    if (e.key === 'Escape') {
      kbModal && kbModal.classList.remove('open');
      if (typeof closeDrawer === 'function') closeDrawer();
    }
  });
})();
</script>
</body>
</html>
    <?php
}
