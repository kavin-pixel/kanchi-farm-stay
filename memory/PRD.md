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
- **Preview server**: `php -S 0.0.0.0:3000 -t /app` (background process — not supervisor-managed).

## Auth
- Login at `/channel-manager/admin.php` with `KanchiFarm2025!` (from `config.php` → `ADMIN_PASSWORD`).

## Implemented (May 28, 2026)
1. **Sort bookings by date** — 8 sort options: check-in/check-out asc/desc, recently added, amount asc/desc. Persisted to localStorage.
2. **Mobile-first redesign of Bookings list** — table converts to card layout below 768px; each card shows property as header, then label/value rows.
3. **Bottom-sheet drawer on mobile** — slides up from bottom with handle bar; form grids stack vertically; 1rem font inputs for touch.
4. **Easy mobile direct booking** — extended "Direct Booking" FAB stacked above a "+" secondary FAB. Opens drawer pre-filled with `source=Direct`, today → tomorrow dates, focuses Guest Name field.
5. **Quick Direct button on desktop bookings toolbar** (parity with mobile FAB).
6. **Removed duplicate `#sidebar-overlay`** DOM ID.
7. **Mobile filter bar full-width stacking** — search becomes full row, filter/sort dropdowns 2-up.

## Existing Features (from previous fork)
- Collapsible sidebar (left-rail with sections: Operations, Revenue, Engagement, System).
- Mobile sidebar overlay with hamburger toggle.
- Keyboard shortcuts: `D` dashboard, `C` calendar, `B` bookings, `N` new booking, `/` search, `?` shortcuts modal, `Esc` close.
- Tooltips via `data-tip` attribute + global `#ui-tooltip` element.
- Gantt: 30/60 day toggle, today-scroll, color-coded source dots, click empty cell to add booking.
- Light theme variables defined in `admin-styles.css`.

## Known Issues / Backlog
- 🟡 Topbar title always says "Dashboard" — needs to update dynamically when navigating between sections via initial URL load (works via JS goTo() but not on page reload).
- 🟡 Login page background is dark navy — doesn't match the new light theme.
- 🟡 Secondary pages (Channels, Guests, Pricing, Revenue, Settings, Logs, WhatsApp, Reputation, Night Audit, Agents, Campaigns) still use the OLD UI (separate PHP files not yet rebuilt with new shell).
- 🟢 PHP dev server is a transient process — not in supervisor.

## File Map
- `/app/channel-manager/admin.php` — main SPA-style admin (Dashboard / Calendar / Bookings).
- `/app/channel-manager/admin-shell.php` — alternative shell used by secondary pages.
- `/app/channel-manager/admin-styles.css` — global styles, design tokens, responsive rules.
- `/app/channel-manager/db.php` + `/app/channel-manager/calendar.db` — SQLite store.
- `/app/design_guidelines.json` — design system reference from design agent.

## Test Credentials
See `/app/memory/test_credentials.md`.
