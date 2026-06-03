# Channel Manager UI Rework — PRD

## Original Problem Statement
Rework the full UI for the Kanchi Farm Stay channel manager so it is user-friendly and efficient.
- Fix Gantt calendar (was hard to use), dense tables, and cluttered forms.
- Implement a lighter, minimal theme.
- Initial scope: Dashboard, Calendar & Bookings.
- Mobile/tablet responsiveness is critical.
- Requested features: quick-action buttons, keyboard shortcuts, collapsed sidebar, tooltips, better date pickers.

## Tech Stack
- **Pure PHP 8.2 + SQLite** (no React/FastAPI). App lives in `/app/channel-manager/`.
- **Frontend**: Vanilla HTML/CSS/JS with FontAwesome CDN, Outfit + Manrope fonts.
- **Theme**: Swiss-minimal light theme (orange accent #F97316 on white).
- **Preview server**: `php -S 0.0.0.0:3000 -t /app` under supervisor (`/etc/supervisor/conf.d/supervisord_php.conf`).

## Auth
- Login at `/channel-manager/admin.php` with `KanchiFarm2025!` (from `config.php` → `ADMIN_PASSWORD`).

## Implemented

### Jun 3, 2026 — Editing, Date Range, Past Calendar
1. **Edit bookings** — Pencil-icon edit button on every bookings row, AND every Gantt booking block is clickable. Opens drawer in edit mode with full prefill, "Save Changes" CTA, and returns user to whichever section they came from (`return_to=bookings|calendar`).
2. **`updateBooking($id, $data)`** helper in `db.php` (whitelisted column updates).
3. **`update_booking`** POST action handler in `admin.php`.
4. **Date-range filter** on Bookings — From/To inputs + field selector (check-in / check-out / overlapping stay) + presets (Today / This week / This month / Next 30d / Clear).
5. **Calendar past dates** — `?gantt_offset=N` URL param controls start. Prev/Today/Next buttons in toolbar; range label always visible.
6. **Clickable Gantt bookings** — hover state + click-to-edit.

### May 28, 2026 — Sort + Mobile-First Bookings
1. **Sort bookings** — 8 sort options (check-in/check-out asc/desc, recently added, amount). Persisted to localStorage.
2. **Mobile bookings table → card layout** below 768px.
3. **Bottom-sheet drawer on mobile** — slides up from bottom with handle bar; form grids stack vertically.
4. **Easy mobile direct booking** — extended "Direct Booking" FAB + Quick Direct preset (today→tomorrow, source=direct, autofocus guest name).
5. **Quick Direct desktop toolbar button** for parity.
6. **Removed duplicate `#sidebar-overlay`** DOM ID.
7. **Mobile filter-bar full-width stacking**.

### Earlier (previous fork)
- Light theme variables, collapsible sidebar, mobile sidebar overlay, hamburger toggle.
- Keyboard shortcuts: `D` dashboard, `C` calendar, `B` bookings, `N` new booking, `/` search, `?` shortcuts modal, `Esc` close.
- Tooltips via `data-tip` + global `#ui-tooltip`.
- Gantt: 30/60 day toggle, today-scroll, color-coded source dots.

## Known Issues / Backlog
- 🟡 Topbar title always says "Dashboard" — needs to update dynamically on initial page load.
- 🟡 Login page background is dark navy — doesn't match the new light theme.
- 🟡 Secondary pages (Channels, Guests, Pricing, Revenue, Settings, Logs, WhatsApp, Reputation, Night Audit, Agents, Campaigns) still use the OLD UI.
- 🟢 Drag-to-resize a booking in the Gantt would be a nice future polish.

## File Map
- `/app/channel-manager/admin.php` — main SPA-style admin (Dashboard / Calendar / Bookings).
- `/app/channel-manager/admin-shell.php` — alternative shell used by secondary pages.
- `/app/channel-manager/admin-styles.css` — global styles, design tokens, responsive rules.
- `/app/channel-manager/db.php` + `/app/channel-manager/calendar.db` — SQLite store.
- `/app/design_guidelines.json` — design system reference.
- `/etc/supervisor/conf.d/supervisord_php.conf` — supervisor entry for the PHP server.

## Test Credentials
See `/app/memory/test_credentials.md`.
