# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Deployment

There is no build step. All files are deployed directly via FTP to Hostinger shared hosting. The FTP root **is** the web root — do not use a `public_html/` prefix.

**Upload changed files:**
```python
# /tmp/upload.py — edit FILES list as needed
import ftplib, os
HOST = '82.112.239.19'
USER = 'u997938990.kanchifarmstay.com'
PASS = 'gyWzet9pirmedowweg'
ftp = ftplib.FTP(); ftp.connect(HOST, 21, timeout=60); ftp.login(USER, PASS); ftp.set_pasv(True)
# ftp.storbinary('STOR path/on/server.php', open('local/file.php', 'rb'))
ftp.quit()
```

**Trigger cron manually (iCal sync + WA queue + pricing):**
```
https://kanchifarmstay.com/channel-manager/cron.php?token=kanchi-cron-2025
```

**Test availability API:**
```
https://kanchifarmstay.com/channel-manager/availability-api.php?room=wooden-villa
```

## Architecture

### Two separate repositories
- `kanchi-farm-stay copy/` — **this repo** — plain HTML/CSS/JS frontend + PHP backend (production)
- `kanchi-farm-stay/` — a separate React/Vite project (not production)

### Frontend (static HTML + vanilla JS)
- All pages are plain `.html` files. `script.js` is the single JS file for all page logic.
- Room data (names, prices, images, OTA URLs) lives in the `rooms` array at the top of `script.js`.
- The navbar and footer are rendered by JS (`renderNavbar()`, `renderFooter()`) and injected into `#navbar-placeholder` / `#footer-placeholder` on every page.
- `room-details.html?id=<room-id>` is the booking page. It calls `availability-api.php`, `check-availability.php`, `create_order.php`, and `confirm_booking.php` in sequence.

### Backend (PHP 8.2 + SQLite on Hostinger)
All backend files live in `channel-manager/`.

**Entry points:**
| File | Purpose |
|---|---|
| `availability-api.php` | Public GET — returns blocked date ranges for a room |
| `check-availability.php` | Pre-payment double-check before Razorpay order |
| `create_order.php` | Creates Razorpay order, records booking attempt |
| `confirm_booking.php` | Called after payment success — writes booking, schedules WA messages, sends emails |
| `cron.php` | iCal sync + WA queue + abandonment + pricing + revenue snapshot |
| `sync.php` | iCal parser and sync logic (used by cron and admin) |
| `admin.php` | Main admin dashboard (calendar, bookings, blocking) |
| `admin-shell.php` | Shared auth + sidebar + topbar required by all admin-*.php pages |

**Data layer — `db.php`:**
- Single file, no ORM. All DB access goes through functions in `db.php`.
- `getDB()` returns a PDO singleton. It runs `_migrateSchema()` **then** `_initSchema()` on first call — migration must run first so `INSERT` statements in `_initSchema` don't fail on old schemas that are missing columns.
- Database file: `channel-manager/calendar.db` (SQLite, auto-created).

**Configuration — `config.php`:**
- Defines `ROOM_IDS` and `ROOM_BASE_PRICES` — these must match the `id` values in `script.js`.
- Razorpay secret is intentionally blank (`RAZORPAY_KEY_SECRET => ''`) — must be set manually on the server.
- All tokens/secrets are hardcoded constants (no `.env`).

### White Villa mutual-blocking
`white-villa`, `white-villa-room-2`, and `white-villa-full-floor` are mutually exclusive. The logic is in two places:
1. `availability-api.php` — merges blocked ranges from linked rooms at query time so the date picker shows correct unavailability.
2. `confirm_booking.php` — writes zero-amount shadow bookings on linked rooms after a payment is confirmed.

### iCal sync flow
OTAs (Airbnb, Booking.com) expose iCal feeds. `cron.php` fetches them every 30 min, parses VEVENT blocks, and upserts into `bookings` (with `is_sync_imported=1`). Revenue from OTA bookings is estimated (base price × nights) — OTAs do not provide actual payout data.

### WhatsApp notifications
- Staff alerts go via `sendWhatsAppNotification()` in `whatsapp.php` (CallMeBot or Meta API).
- Guest workflow messages (confirmation, pre-stay ×2, post-checkout) are queued in `wa_message_queue` by `scheduleWorkflowMessages()` and processed by `processWaQueue()` in `wa-queue.php` during cron.

### Admin panel
- Login: `https://kanchifarmstay.com/channel-manager/admin.php` — password in `config.php` as `ADMIN_PASSWORD`.
- All `admin-*.php` pages `require_once 'admin-shell.php'` which handles session auth, renders the sidebar, and outputs `<head>`.
- Sidebar links and active state are driven by the `$navItems` array in `admin-shell.php`.

### SEO / Schema
`index.html` contains three `application/ld+json` blocks: `LodgingBusiness` (with `containsPlace` listing all 9 rooms), `BreadcrumbList`, and `FAQPage`. The `LodgingBusiness` block also has `sameAs` links to Airbnb, Booking.com, Instagram, and Facebook — this is how Google groups all OTA listings under one "Kanchi Farm Stay" result.
