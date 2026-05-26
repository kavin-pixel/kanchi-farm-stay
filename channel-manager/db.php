<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        _initSchema($db);
        _migrateSchema($db);
    }
    return $db;
}

// ──────────────────────────────────────────────────────────────────
// SCHEMA INIT  (tables that must exist from day 1)
// ──────────────────────────────────────────────────────────────────
function _initSchema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS bookings (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id     INTEGER NOT NULL DEFAULT 1,
            room_id         TEXT    NOT NULL,
            room_name       TEXT    NOT NULL,
            check_in        DATE    NOT NULL,
            check_out       DATE    NOT NULL,
            guest_name      TEXT    DEFAULT 'Blocked',
            guest_email     TEXT    DEFAULT '',
            guest_phone     TEXT    DEFAULT '',
            whatsapp_number TEXT    DEFAULT '',
            source          TEXT    DEFAULT 'direct',
            booking_ref     TEXT    DEFAULT '',
            amount          REAL    DEFAULT 0,
            amount_paid     REAL    DEFAULT 0,
            payment_method  TEXT    DEFAULT '',
            payment_status  TEXT    DEFAULT 'pending',
            status          TEXT    DEFAULT 'confirmed',
            uid             TEXT    UNIQUE,
            notes           TEXT    DEFAULT '',
            is_sync_imported INTEGER DEFAULT 0,
            review_requested INTEGER DEFAULT 0,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS external_calendars (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id      INTEGER NOT NULL DEFAULT 1,
            room_id          TEXT    NOT NULL,
            platform         TEXT    NOT NULL,
            ical_url         TEXT    NOT NULL,
            last_synced      DATETIME,
            last_sync_status TEXT    DEFAULT '',
            last_sync_imported INTEGER DEFAULT 0,
            commission_pct   REAL    DEFAULT 0,
            is_active        INTEGER DEFAULT 1,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS properties (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL,
            slug        TEXT    NOT NULL UNIQUE,
            address     TEXT    DEFAULT '',
            city        TEXT    DEFAULT '',
            state       TEXT    DEFAULT '',
            country     TEXT    DEFAULT 'India',
            phone       TEXT    DEFAULT '',
            email       TEXT    DEFAULT '',
            website_url TEXT    DEFAULT '',
            description TEXT    DEFAULT '',
            is_active   INTEGER DEFAULT 1,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        INSERT OR IGNORE INTO properties (id, name, slug, city, state)
        VALUES (1, 'Kanchi Farm Stay', 'kanchi-farm-stay', 'Kanchipuram', 'Tamil Nadu');

        CREATE TABLE IF NOT EXISTS room_rates (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id INTEGER NOT NULL DEFAULT 1,
            room_id     TEXT    NOT NULL,
            base_price  REAL    NOT NULL DEFAULT 0,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(property_id, room_id)
        );

        CREATE TABLE IF NOT EXISTS demand_events (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id  INTEGER NOT NULL DEFAULT 1,
            event_date   DATE    NOT NULL,
            event_name   TEXT    NOT NULL,
            event_type   TEXT    NOT NULL DEFAULT 'festival',
            demand_level TEXT    DEFAULT 'high',
            notes        TEXT    DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS pricing_rules (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id     INTEGER NOT NULL DEFAULT 1,
            rule_name       TEXT    NOT NULL,
            rule_type       TEXT    NOT NULL,
            condition_value REAL    DEFAULT 0,
            adjustment_pct  REAL    NOT NULL DEFAULT 0,
            is_active       INTEGER DEFAULT 1,
            priority        INTEGER DEFAULT 10,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        INSERT OR IGNORE INTO pricing_rules (id, property_id, rule_name, rule_type, condition_value, adjustment_pct, priority)
        VALUES
            (1, 1, 'High Occupancy (>70%)',     'occupancy_high', 70,  20, 10),
            (2, 1, 'Low Occupancy (<30%)',       'occupancy_low',  30, -10, 10),
            (3, 1, 'Last Minute (<7 days)',       'last_minute',     7, -15, 20),
            (4, 1, 'Weekend Premium (Fri-Sun)',   'weekend',         0,  25, 15),
            (5, 1, 'Long Stay 3+ nights',         'los_discount',    3,  -5, 25),
            (6, 1, 'Long Stay 7+ nights',         'los_discount',    7, -10, 25);

        CREATE TABLE IF NOT EXISTS pricing_suggestions (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id     INTEGER NOT NULL DEFAULT 1,
            room_id         TEXT    NOT NULL,
            date_from       DATE    NOT NULL,
            date_to         DATE    NOT NULL,
            current_price   REAL    NOT NULL,
            suggested_price REAL    NOT NULL,
            suggestion_pct  REAL    NOT NULL,
            reason          TEXT    NOT NULL DEFAULT '',
            demand_level    TEXT    DEFAULT 'normal',
            status          TEXT    DEFAULT 'pending',
            approved_price  REAL,
            approved_at     DATETIME,
            notes           TEXT    DEFAULT '',
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS price_history (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id INTEGER NOT NULL DEFAULT 1,
            room_id     TEXT    NOT NULL,
            date_from   DATE    NOT NULL,
            date_to     DATE    NOT NULL,
            old_price   REAL    NOT NULL,
            new_price   REAL    NOT NULL,
            reason      TEXT    NOT NULL DEFAULT '',
            changed_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS wa_templates (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id  INTEGER NOT NULL DEFAULT 1,
            trigger_type TEXT    NOT NULL,
            message_body TEXT    NOT NULL DEFAULT '',
            is_active    INTEGER DEFAULT 1,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(property_id, trigger_type)
        );

        INSERT OR IGNORE INTO wa_templates (property_id, trigger_type, message_body) VALUES
        (1, 'booking_confirmed',
         'Hi {{guest_name}}, your booking at Kanchi Farm Stay is confirmed! 🏡\nRoom: {{room_name}}\nCheck-in: {{check_in}}\nCheck-out: {{check_out}}\nAmount: ₹{{amount}}\nWe look forward to hosting you! Call us: +91 6383726094'),
        (1, 'pre_stay_t3',
         'Hi {{guest_name}}, your Kanchi Farm Stay check-in is in 3 days ({{check_in}})! 🌿\nRoom: {{room_name}}\nNeed directions or have questions? Call: +91 6383726094'),
        (1, 'pre_stay_t1',
         'Hi {{guest_name}}, your check-in at Kanchi Farm Stay is TOMORROW ({{check_in}}) from 2 PM! 🎉\nAddress: 704, Sastha Nagar, Kovil Street, Chithathur\nWe are so excited to welcome you!'),
        (1, 'post_checkout_t1',
         'Hi {{guest_name}}, thank you for staying at Kanchi Farm Stay! 🙏\nWe hope you had a wonderful time in {{room_name}}. If you enjoyed your stay, a quick review would mean the world to us:\n👉 Leave a review: {{review_link}}\nHope to see you again soon!'),
        (1, 'abandonment',
         'Hi {{guest_name}}, we noticed you started booking {{room_name}} at Kanchi Farm Stay for {{check_in}}. 🏡\nYour dates are still available! Complete your booking:\n👉 https://kanchifarmstay.com/room-details.html?id={{room_id}}\nOr call us: +91 6383726094');

        CREATE TABLE IF NOT EXISTS wa_message_queue (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            booking_id   INTEGER REFERENCES bookings(id) ON DELETE SET NULL,
            property_id  INTEGER NOT NULL DEFAULT 1,
            phone        TEXT    NOT NULL,
            message      TEXT    NOT NULL,
            trigger_type TEXT    NOT NULL,
            scheduled_at DATETIME NOT NULL,
            sent_at      DATETIME,
            status       TEXT    DEFAULT 'pending',
            attempts     INTEGER DEFAULT 0,
            last_error   TEXT    DEFAULT '',
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS booking_attempts (
            id                 INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id        INTEGER NOT NULL DEFAULT 1,
            room_id            TEXT    NOT NULL,
            room_name          TEXT    NOT NULL,
            check_in           DATE    NOT NULL,
            check_out          DATE    NOT NULL,
            guest_name         TEXT    DEFAULT '',
            guest_email        TEXT    DEFAULT '',
            guest_phone        TEXT    DEFAULT '',
            razorpay_order_id  TEXT    NOT NULL UNIQUE,
            amount             REAL    DEFAULT 0,
            status             TEXT    DEFAULT 'initiated',
            recovery_sent      INTEGER DEFAULT 0,
            created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
            converted_at       DATETIME
        );

        CREATE TABLE IF NOT EXISTS reviews (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id   INTEGER NOT NULL DEFAULT 1,
            booking_id    INTEGER REFERENCES bookings(id) ON DELETE SET NULL,
            platform      TEXT    NOT NULL DEFAULT 'google',
            reviewer_name TEXT    DEFAULT '',
            rating        INTEGER DEFAULT 5,
            review_text   TEXT    DEFAULT '',
            review_url    TEXT    DEFAULT '',
            review_date   DATE,
            response_text TEXT    DEFAULT '',
            responded_at  DATETIME,
            is_visible    INTEGER DEFAULT 1,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS review_templates (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id   INTEGER NOT NULL DEFAULT 1,
            name          TEXT    NOT NULL,
            rating_min    INTEGER DEFAULT 1,
            rating_max    INTEGER DEFAULT 5,
            template_text TEXT    NOT NULL DEFAULT '',
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        INSERT OR IGNORE INTO review_templates (id, property_id, name, rating_min, rating_max, template_text) VALUES
        (1, 1, '5-Star Response', 5, 5,
         'Thank you so much {{name}}! 🌿 We are absolutely delighted you enjoyed your stay at Kanchi Farm Stay. Your kind words mean the world to our family. We hope to welcome you back very soon!'),
        (2, 1, '4-Star Response', 4, 4,
         'Thank you {{name}} for your lovely review! We are glad you had a great time with us. Your feedback helps us improve and we would love to see you again at Kanchi Farm Stay!'),
        (3, 1, '3-Star or Below', 1, 3,
         'Thank you {{name}} for your honest feedback. We are sorry your stay did not fully meet expectations. We would love to make it right — please reach us at ops@kanchifarmstay.com so we can address your concerns personally.');

        CREATE TABLE IF NOT EXISTS channel_stats (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id    INTEGER NOT NULL DEFAULT 1,
            room_id        TEXT    NOT NULL,
            platform       TEXT    NOT NULL,
            month_year     TEXT    NOT NULL,
            bookings_count INTEGER DEFAULT 0,
            total_nights   INTEGER DEFAULT 0,
            gross_revenue  REAL    DEFAULT 0,
            commission_pct REAL    DEFAULT 0,
            net_revenue    REAL    DEFAULT 0,
            updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(property_id, room_id, platform, month_year)
        );

        CREATE TABLE IF NOT EXISTS revenue_snapshots (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id    INTEGER NOT NULL DEFAULT 1,
            snapshot_date  DATE    NOT NULL,
            adr            REAL    DEFAULT 0,
            revpar         REAL    DEFAULT 0,
            occupancy_pct  REAL    DEFAULT 0,
            total_rooms    INTEGER DEFAULT 0,
            rooms_occupied INTEGER DEFAULT 0,
            gross_revenue  REAL    DEFAULT 0,
            UNIQUE(property_id, snapshot_date)
        );

        CREATE TABLE IF NOT EXISTS settings (
            key         TEXT    NOT NULL,
            value       TEXT    NOT NULL DEFAULT '',
            property_id INTEGER NOT NULL DEFAULT 1,
            PRIMARY KEY (key, property_id)
        );

        INSERT OR IGNORE INTO settings (key, value, property_id) VALUES
            ('google_review_url',  'https://search.google.com/local/writereview?placeid=YOUR_PLACE_ID', 1),
            ('airbnb_review_url',  'https://www.airbnb.co.in/users/show/YOUR_HOST_ID', 1),
            ('booking_review_url', 'https://www.booking.com/hotel/in/kanchi-farm-stay.html#reviews', 1),
            ('google_place_id',    '', 1),
            ('last_suggestions_generated', '', 1);

        -- ── Booking Engine Campaigns (Early Bird, Last Minute, Long Stay) ──
        CREATE TABLE IF NOT EXISTS campaigns (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id    INTEGER NOT NULL DEFAULT 1,
            name           TEXT    NOT NULL,
            campaign_type  TEXT    NOT NULL DEFAULT 'general',
            discount_pct   REAL    NOT NULL DEFAULT 0,
            min_nights     INTEGER NOT NULL DEFAULT 1,
            valid_from     DATE    NOT NULL,
            valid_until    DATE    NOT NULL,
            advance_days   INTEGER DEFAULT 0,
            is_active      INTEGER DEFAULT 1,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- ── Coupon / Promo Codes ──────────────────────────────────────────
        CREATE TABLE IF NOT EXISTS coupons (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id    INTEGER NOT NULL DEFAULT 1,
            code           TEXT    NOT NULL,
            name           TEXT    NOT NULL DEFAULT '',
            discount_pct   REAL    NOT NULL DEFAULT 0,
            min_nights     INTEGER NOT NULL DEFAULT 1,
            max_uses       INTEGER DEFAULT 0,
            uses_count     INTEGER DEFAULT 0,
            valid_from     DATE    NOT NULL,
            valid_until    DATE    NOT NULL,
            is_active      INTEGER DEFAULT 1,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(code, property_id)
        );

        -- ── Agents (Travel Agents with commission) ────────────────────────
        CREATE TABLE IF NOT EXISTS agents (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id    INTEGER NOT NULL DEFAULT 1,
            name           TEXT    NOT NULL,
            email          TEXT    DEFAULT '',
            phone          TEXT    DEFAULT '',
            commission_pct REAL    DEFAULT 10,
            address        TEXT    DEFAULT '',
            is_active      INTEGER DEFAULT 1,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- ── Companies (Corporate Bookings) ───────────────────────────────
        CREATE TABLE IF NOT EXISTS companies (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id    INTEGER NOT NULL DEFAULT 1,
            name           TEXT    NOT NULL,
            gst_number     TEXT    DEFAULT '',
            email          TEXT    DEFAULT '',
            phone          TEXT    DEFAULT '',
            address        TEXT    DEFAULT '',
            discount_pct   REAL    DEFAULT 0,
            credit_limit   REAL    DEFAULT 0,
            is_active      INTEGER DEFAULT 1,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- ── Night Audit Log ───────────────────────────────────────────────
        CREATE TABLE IF NOT EXISTS night_audit_log (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id    INTEGER NOT NULL DEFAULT 1,
            audit_date     DATE    NOT NULL,
            completed_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            step           INTEGER NOT NULL DEFAULT 5,
            notes          TEXT    DEFAULT '',
            UNIQUE(property_id, audit_date)
        );

        -- ── Availability Logs ─────────────────────────────────────────────
        CREATE TABLE IF NOT EXISTS availability_logs (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id    INTEGER NOT NULL DEFAULT 1,
            room_id        TEXT    NOT NULL,
            action         TEXT    NOT NULL,
            check_in       DATE,
            check_out      DATE,
            source         TEXT    DEFAULT '',
            notes          TEXT    DEFAULT '',
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- ── Price Logs ────────────────────────────────────────────────────
        CREATE TABLE IF NOT EXISTS price_logs (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id    INTEGER NOT NULL DEFAULT 1,
            room_id        TEXT    NOT NULL,
            old_price      REAL    DEFAULT 0,
            new_price      REAL    DEFAULT 0,
            changed_by     TEXT    DEFAULT 'admin',
            reason         TEXT    DEFAULT '',
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS wa_conversations (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id       INTEGER NOT NULL DEFAULT 1,
            phone             TEXT    NOT NULL UNIQUE,
            guest_name        TEXT    DEFAULT '',
            status            TEXT    DEFAULT 'open',
            last_message      TEXT    DEFAULT '',
            last_message_time DATETIME,
            unread_count      INTEGER DEFAULT 0,
            booking_id        INTEGER REFERENCES bookings(id) ON DELETE SET NULL,
            created_at        DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS wa_messages (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL REFERENCES wa_conversations(id) ON DELETE CASCADE,
            sender          TEXT    NOT NULL DEFAULT 'guest',
            body            TEXT    NOT NULL DEFAULT '',
            timestamp       DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
}

// ──────────────────────────────────────────────────────────────────
// SCHEMA MIGRATIONS  (add columns to existing tables safely)
// ──────────────────────────────────────────────────────────────────
function _migrateSchema(PDO $db): void {
    $cols = [
        'bookings'           => ['property_id INTEGER NOT NULL DEFAULT 1','whatsapp_number TEXT DEFAULT \'\'','payment_status TEXT DEFAULT \'pending\'','payment_method TEXT DEFAULT \'\'','amount_paid REAL DEFAULT 0','is_sync_imported INTEGER DEFAULT 0','review_requested INTEGER DEFAULT 0'],
        'external_calendars' => ['property_id INTEGER NOT NULL DEFAULT 1','commission_pct REAL DEFAULT 0','last_sync_status TEXT DEFAULT \'\'','last_sync_imported INTEGER DEFAULT 0'],
        'room_rates'         => ['property_id INTEGER NOT NULL DEFAULT 1'],
        'demand_events'      => ['property_id INTEGER NOT NULL DEFAULT 1'],
        'pricing_suggestions'=> ['property_id INTEGER NOT NULL DEFAULT 1'],
        'price_history'      => ['property_id INTEGER NOT NULL DEFAULT 1'],
    ];
    foreach ($cols as $tbl => $additions) {
        foreach ($additions as $colDef) {
            try { $db->exec("ALTER TABLE $tbl ADD COLUMN $colDef"); } catch (Exception $e) { /* already exists */ }
        }
    }
}

// ──────────────────────────────────────────────────────────────────
// PROPERTIES
// ──────────────────────────────────────────────────────────────────
function getAllProperties(): array {
    return getDB()->query("SELECT * FROM properties WHERE is_active=1 ORDER BY id")->fetchAll();
}
function getProperty(int $id): ?array {
    $s = getDB()->prepare("SELECT * FROM properties WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
function addProperty(array $data): int {
    $db = getDB();
    $s  = $db->prepare("INSERT INTO properties (name,slug,address,city,state,country,phone,email,website_url,description) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->execute([$data['name'],$data['slug'] ?? strtolower(str_replace(' ','-',$data['name'])),$data['address'] ?? '',$data['city'] ?? '',$data['state'] ?? '',$data['country'] ?? 'India',$data['phone'] ?? '',$data['email'] ?? '',$data['website_url'] ?? '',$data['description'] ?? '']);
    return (int)$db->lastInsertId();
}
function updateProperty(int $id, array $data): void {
    $s = getDB()->prepare("UPDATE properties SET name=?,address=?,city=?,state=?,phone=?,email=?,website_url=?,description=? WHERE id=?");
    $s->execute([$data['name'],$data['address'],$data['city'],$data['state'],$data['phone'],$data['email'],$data['website_url'],$data['description'],$id]);
}

// ──────────────────────────────────────────────────────────────────
// SETTINGS
// ──────────────────────────────────────────────────────────────────
function getSetting(string $key, int $propertyId = 1, string $default = ''): string {
    $s = getDB()->prepare("SELECT value FROM settings WHERE key=? AND property_id=?");
    $s->execute([$key, $propertyId]);
    $r = $s->fetchColumn();
    return $r !== false ? $r : $default;
}
function setSetting(string $key, string $value, int $propertyId = 1): void {
    $s = getDB()->prepare("INSERT INTO settings (key,value,property_id) VALUES (?,?,?) ON CONFLICT(key,property_id) DO UPDATE SET value=excluded.value");
    $s->execute([$key, $value, $propertyId]);
}

// ──────────────────────────────────────────────────────────────────
// BOOKINGS
// ──────────────────────────────────────────────────────────────────
function addBooking(array $data): int|false {
    $db  = getDB();
    $uid = $data['uid'] ?? ('bk-' . bin2hex(random_bytes(8)) . '@kanchifarmstay.com');
    $s   = $db->prepare("
        INSERT OR IGNORE INTO bookings
            (property_id, room_id, room_name, check_in, check_out,
             guest_name, guest_email, guest_phone, whatsapp_number,
             source, booking_ref, amount, amount_paid, payment_method, payment_status,
             status, uid, notes, is_sync_imported)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $s->execute([
        $data['property_id']     ?? 1,
        $data['room_id'],
        $data['room_name'],
        $data['check_in'],
        $data['check_out'],
        $data['guest_name']      ?? 'Guest',
        $data['guest_email']     ?? '',
        $data['guest_phone']     ?? '',
        $data['whatsapp_number'] ?? $data['guest_phone'] ?? '',
        $data['source']          ?? 'direct',
        $data['booking_ref']     ?? '',
        $data['amount']          ?? 0,
        $data['amount_paid']     ?? $data['amount'] ?? 0,
        $data['payment_method']  ?? '',
        $data['payment_status']  ?? 'pending',
        $data['status']          ?? 'confirmed',
        $uid,
        $data['notes']           ?? '',
        $data['is_sync_imported'] ?? 0,
    ]);
    return $s->rowCount() ? (int)$db->lastInsertId() : false;
}

function getAllBookings(array $filters = [], int $propertyId = 1): array {
    $where  = ['property_id = ?'];
    $params = [$propertyId];
    if (!empty($filters['room_id']))  { $where[] = 'room_id = ?';  $params[] = $filters['room_id']; }
    if (!empty($filters['source']))   { $where[] = 'source = ?';   $params[] = $filters['source']; }
    if (!empty($filters['status']))   { $where[] = 'status = ?';   $params[] = $filters['status']; }
    if (!empty($filters['date_from'])){ $where[] = 'check_in >= ?';$params[] = $filters['date_from']; }
    if (!empty($filters['date_to']))  { $where[] = 'check_in <= ?';$params[] = $filters['date_to']; }
    $w    = implode(' AND ', $where);
    $stmt = getDB()->prepare("SELECT * FROM bookings WHERE $w ORDER BY check_in DESC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getBookingById(int $id): ?array {
    $s = getDB()->prepare("SELECT * FROM bookings WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function getUpcomingBookings(int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT * FROM bookings WHERE property_id=? AND status='confirmed' AND check_out >= date('now') ORDER BY check_in");
    $s->execute([$propertyId]);
    return $s->fetchAll();
}

function getBlockedRanges(string $roomId, int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT check_in, check_out FROM bookings WHERE property_id=? AND room_id=? AND status='confirmed' AND check_out >= date('now') ORDER BY check_in");
    $s->execute([$propertyId, $roomId]);
    return $s->fetchAll();
}

function deleteBooking(int $id): void {
    getDB()->prepare("DELETE FROM bookings WHERE id=?")->execute([$id]);
}

function cancelBooking(int $id): void {
    getDB()->prepare("UPDATE bookings SET status='cancelled', updated_at=datetime('now') WHERE id=?")->execute([$id]);
}

function updateBookingField(int $id, string $field, $value): void {
    $allowed = ['notes','payment_status','amount_paid','review_requested','status'];
    if (!in_array($field, $allowed)) return;
    getDB()->prepare("UPDATE bookings SET $field=?, updated_at=datetime('now') WHERE id=?")->execute([$value, $id]);
}

// ──────────────────────────────────────────────────────────────────
// EXTERNAL CALENDARS
// ──────────────────────────────────────────────────────────────────
function getExternalCalendars(int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT * FROM external_calendars WHERE property_id=? ORDER BY room_id, platform");
    $s->execute([$propertyId]);
    return $s->fetchAll();
}

function addExternalCalendar(string $roomId, string $platform, string $url, int $propertyId = 1, float $commissionPct = 0): int {
    $db = getDB();
    $s  = $db->prepare("INSERT INTO external_calendars (property_id, room_id, platform, ical_url, commission_pct) VALUES (?,?,?,?,?)");
    $s->execute([$propertyId, $roomId, $platform, $url, $commissionPct]);
    return (int)$db->lastInsertId();
}

function deleteExternalCalendar(int $id): void {
    getDB()->prepare("DELETE FROM external_calendars WHERE id=?")->execute([$id]);
}

function touchLastSynced(int $calendarId): void {
    getDB()->prepare("UPDATE external_calendars SET last_synced=datetime('now') WHERE id=?")->execute([$calendarId]);
}

function updateCalendarSyncStatus(int $calId, bool $success, int $imported, string $error = ''): void {
    $status = $success ? "OK ($imported imported)" : "Error: $error";
    $s = getDB()->prepare("UPDATE external_calendars SET last_synced=datetime('now'), last_sync_status=?, last_sync_imported=? WHERE id=?");
    $s->execute([$status, $imported, $calId]);
}

// ──────────────────────────────────────────────────────────────────
// ROOM RATES
// ──────────────────────────────────────────────────────────────────
function getRoomRates(int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT * FROM room_rates WHERE property_id=?");
    $s->execute([$propertyId]);
    $rows = $s->fetchAll();
    $map  = [];
    foreach ($rows as $r) $map[$r['room_id']] = $r['base_price'];
    return $map;
}

function getRoomBasePrice(string $roomId, int $propertyId = 1, float $default = 0): float {
    $s = getDB()->prepare("SELECT base_price FROM room_rates WHERE property_id=? AND room_id=?");
    $s->execute([$propertyId, $roomId]);
    $v = $s->fetchColumn();
    return $v !== false ? (float)$v : $default;
}

function setRoomRate(string $roomId, float $price, int $propertyId = 1): void {
    $s = getDB()->prepare("INSERT INTO room_rates (property_id, room_id, base_price) VALUES (?,?,?) ON CONFLICT(property_id,room_id) DO UPDATE SET base_price=excluded.base_price, updated_at=datetime('now')");
    $s->execute([$propertyId, $roomId, $price]);
}

// ──────────────────────────────────────────────────────────────────
// PRICING RULES
// ──────────────────────────────────────────────────────────────────
function getPricingRules(int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT * FROM pricing_rules WHERE property_id=? ORDER BY priority ASC, id ASC");
    $s->execute([$propertyId]);
    return $s->fetchAll();
}

function updatePricingRule(int $id, float $adjustmentPct, int $isActive, float $conditionValue): void {
    $s = getDB()->prepare("UPDATE pricing_rules SET adjustment_pct=?, is_active=?, condition_value=? WHERE id=?");
    $s->execute([$adjustmentPct, $isActive, $conditionValue, $id]);
}

// ──────────────────────────────────────────────────────────────────
// PRICING SUGGESTIONS
// ──────────────────────────────────────────────────────────────────
function getPricingSuggestions(string $status = 'pending', int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT * FROM pricing_suggestions WHERE property_id=? AND status=? ORDER BY date_from ASC");
    $s->execute([$propertyId, $status]);
    return $s->fetchAll();
}

function approvePricingSuggestion(int $id, float $price): void {
    $s = getDB()->prepare("UPDATE pricing_suggestions SET status='approved', approved_price=?, approved_at=datetime('now') WHERE id=?");
    $s->execute([$price, $id]);
}

function rejectPricingSuggestion(int $id): void {
    getDB()->prepare("UPDATE pricing_suggestions SET status='rejected' WHERE id=?")->execute([$id]);
}

function addPricingSuggestion(array $data): void {
    $s = getDB()->prepare("INSERT OR IGNORE INTO pricing_suggestions (property_id, room_id, date_from, date_to, current_price, suggested_price, suggestion_pct, reason, demand_level) VALUES (?,?,?,?,?,?,?,?,?)");
    $s->execute([$data['property_id'] ?? 1, $data['room_id'], $data['date_from'], $data['date_to'], $data['current_price'], $data['suggested_price'], $data['suggestion_pct'], $data['reason'], $data['demand_level'] ?? 'normal']);
}

// ──────────────────────────────────────────────────────────────────
// DEMAND EVENTS
// ──────────────────────────────────────────────────────────────────
function getDemandEvents(int $propertyId = 1, ?string $from = null): array {
    $from = $from ?? date('Y-m-d');
    $s    = getDB()->prepare("SELECT * FROM demand_events WHERE property_id=? AND event_date >= ? ORDER BY event_date ASC");
    $s->execute([$propertyId, $from]);
    return $s->fetchAll();
}

function addDemandEvent(array $data, int $propertyId = 1): void {
    $s = getDB()->prepare("INSERT INTO demand_events (property_id, event_date, event_name, event_type, demand_level, notes) VALUES (?,?,?,?,?,?)");
    $s->execute([$propertyId, $data['event_date'], $data['event_name'], $data['event_type'] ?? 'festival', $data['demand_level'] ?? 'high', $data['notes'] ?? '']);
}

function deleteDemandEvent(int $id): void {
    getDB()->prepare("DELETE FROM demand_events WHERE id=?")->execute([$id]);
}

// ──────────────────────────────────────────────────────────────────
// WA TEMPLATES
// ──────────────────────────────────────────────────────────────────
function getWaTemplates(int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT * FROM wa_templates WHERE property_id=? ORDER BY id");
    $s->execute([$propertyId]);
    return $s->fetchAll();
}

function getWaTemplate(string $triggerType, int $propertyId = 1): ?array {
    $s = getDB()->prepare("SELECT * FROM wa_templates WHERE property_id=? AND trigger_type=?");
    $s->execute([$propertyId, $triggerType]);
    return $s->fetch() ?: null;
}

function saveWaTemplate(string $triggerType, string $messageBody, int $isActive, int $propertyId = 1): void {
    $s = getDB()->prepare("INSERT INTO wa_templates (property_id, trigger_type, message_body, is_active, updated_at) VALUES (?,?,?,?,datetime('now')) ON CONFLICT(property_id, trigger_type) DO UPDATE SET message_body=excluded.message_body, is_active=excluded.is_active, updated_at=excluded.updated_at");
    $s->execute([$propertyId, $triggerType, $messageBody, $isActive]);
}

// ──────────────────────────────────────────────────────────────────
// WA MESSAGE QUEUE
// ──────────────────────────────────────────────────────────────────
function enqueueWaMessage(int $bookingId, string $phone, string $message, string $triggerType, string $scheduledAt, int $propertyId = 1): void {
    $s = getDB()->prepare("INSERT INTO wa_message_queue (booking_id, property_id, phone, message, trigger_type, scheduled_at) VALUES (?,?,?,?,?,?)");
    $s->execute([$bookingId ?: null, $propertyId, $phone, $message, $triggerType, $scheduledAt]);
}

function getPendingWaMessages(int $propertyId = 0): array {
    if ($propertyId) {
        $s = getDB()->prepare("SELECT * FROM wa_message_queue WHERE property_id=? AND status='pending' AND scheduled_at <= datetime('now') AND attempts < 3 ORDER BY scheduled_at ASC");
        $s->execute([$propertyId]);
    } else {
        $s = getDB()->query("SELECT * FROM wa_message_queue WHERE status='pending' AND scheduled_at <= datetime('now') AND attempts < 3 ORDER BY scheduled_at ASC");
    }
    return $s->fetchAll();
}

function markWaMessageSent(int $id): void {
    getDB()->prepare("UPDATE wa_message_queue SET status='sent', sent_at=datetime('now') WHERE id=?")->execute([$id]);
}

function markWaMessageFailed(int $id, string $error): void {
    getDB()->prepare("UPDATE wa_message_queue SET attempts=attempts+1, last_error=?, status=CASE WHEN attempts+1 >= 3 THEN 'failed' ELSE 'pending' END WHERE id=?")->execute([$error, $id]);
}

function retryWaMessage(int $id): void {
    getDB()->prepare("UPDATE wa_message_queue SET status='pending', attempts=0, last_error='' WHERE id=?")->execute([$id]);
}

function getWaQueueHistory(int $propertyId = 1, int $limit = 50): array {
    $s = getDB()->prepare("SELECT q.*, b.guest_name, b.room_name FROM wa_message_queue q LEFT JOIN bookings b ON q.booking_id=b.id WHERE q.property_id=? ORDER BY q.created_at DESC LIMIT ?");
    $s->execute([$propertyId, $limit]);
    return $s->fetchAll();
}

// ──────────────────────────────────────────────────────────────────
// BOOKING ATTEMPTS (abandonment tracking)
// ──────────────────────────────────────────────────────────────────
function insertBookingAttempt(array $data): void {
    $s = getDB()->prepare("INSERT OR IGNORE INTO booking_attempts (property_id, room_id, room_name, check_in, check_out, guest_name, guest_email, guest_phone, razorpay_order_id, amount) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->execute([$data['property_id'] ?? 1, $data['room_id'], $data['room_name'], $data['check_in'], $data['check_out'], $data['guest_name'] ?? '', $data['guest_email'] ?? '', $data['guest_phone'] ?? '', $data['razorpay_order_id'], $data['amount'] ?? 0]);
}

function markAttemptConverted(string $razorpayOrderId): void {
    if (!$razorpayOrderId) return;
    getDB()->prepare("UPDATE booking_attempts SET status='converted', converted_at=datetime('now') WHERE razorpay_order_id=?")->execute([$razorpayOrderId]);
}

function getPendingAbandonments(int $hoursOld = 2): array {
    $s = getDB()->prepare("SELECT * FROM booking_attempts WHERE status='initiated' AND recovery_sent=0 AND created_at <= datetime('now', '-' || ? || ' hours') AND guest_phone != ''");
    $s->execute([$hoursOld]);
    return $s->fetchAll();
}

function markAbandonmentRecoverySent(int $id): void {
    getDB()->prepare("UPDATE booking_attempts SET recovery_sent=1, status='abandoned' WHERE id=?")->execute([$id]);
}

// ──────────────────────────────────────────────────────────────────
// REVIEWS
// ──────────────────────────────────────────────────────────────────
function getReviews(int $propertyId = 1, ?string $platform = null): array {
    $where  = ['property_id=?', 'is_visible=1'];
    $params = [$propertyId];
    if ($platform) { $where[] = 'platform=?'; $params[] = $platform; }
    $s = getDB()->prepare("SELECT * FROM reviews WHERE " . implode(' AND ', $where) . " ORDER BY review_date DESC");
    $s->execute($params);
    return $s->fetchAll();
}

function addReview(array $data, int $propertyId = 1): int {
    $db = getDB();
    $s  = $db->prepare("INSERT INTO reviews (property_id, booking_id, platform, reviewer_name, rating, review_text, review_url, review_date) VALUES (?,?,?,?,?,?,?,?)");
    $s->execute([$propertyId, $data['booking_id'] ?? null, $data['platform'], $data['reviewer_name'] ?? '', $data['rating'] ?? 5, $data['review_text'] ?? '', $data['review_url'] ?? '', $data['review_date'] ?? date('Y-m-d')]);
    return (int)$db->lastInsertId();
}

function updateReviewResponse(int $id, string $response): void {
    getDB()->prepare("UPDATE reviews SET response_text=?, responded_at=datetime('now') WHERE id=?")->execute([$response, $id]);
}

function deleteReview(int $id): void {
    getDB()->prepare("UPDATE reviews SET is_visible=0 WHERE id=?")->execute([$id]);
}

function getReputationStats(int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT platform, COUNT(*) as count, AVG(rating) as avg_rating FROM reviews WHERE property_id=? AND is_visible=1 GROUP BY platform ORDER BY count DESC");
    $s->execute([$propertyId]);
    $rows = $s->fetchAll();
    $total = 0; $sum = 0;
    foreach ($rows as $r) { $total += $r['count']; $sum += $r['avg_rating'] * $r['count']; }
    return ['by_platform' => $rows, 'total' => $total, 'overall_avg' => $total > 0 ? round($sum / $total, 1) : 0];
}

function getReviewTemplates(int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT * FROM review_templates WHERE property_id=? ORDER BY rating_min DESC");
    $s->execute([$propertyId]);
    return $s->fetchAll();
}

function saveReviewTemplate(array $data, int $propertyId = 1): void {
    if (!empty($data['id'])) {
        $s = getDB()->prepare("UPDATE review_templates SET name=?, rating_min=?, rating_max=?, template_text=? WHERE id=? AND property_id=?");
        $s->execute([$data['name'], $data['rating_min'], $data['rating_max'], $data['template_text'], $data['id'], $propertyId]);
    } else {
        $s = getDB()->prepare("INSERT INTO review_templates (property_id, name, rating_min, rating_max, template_text) VALUES (?,?,?,?,?)");
        $s->execute([$propertyId, $data['name'], $data['rating_min'], $data['rating_max'], $data['template_text']]);
    }
}

function deleteReviewTemplate(int $id): void {
    getDB()->prepare("DELETE FROM review_templates WHERE id=?")->execute([$id]);
}

// ──────────────────────────────────────────────────────────────────
// CHANNEL STATS
// ──────────────────────────────────────────────────────────────────
function upsertChannelStat(string $roomId, string $platform, float $amount, int $nights, int $propertyId = 1): void {
    $monthYear = date('Y-m');
    $commPct   = OTA_COMMISSIONS[strtolower($platform)] ?? 0;
    $net       = $amount * (1 - $commPct / 100);
    $s = getDB()->prepare("INSERT INTO channel_stats (property_id, room_id, platform, month_year, bookings_count, total_nights, gross_revenue, commission_pct, net_revenue) VALUES (?,?,?,?,1,?,?,?,?) ON CONFLICT(property_id, room_id, platform, month_year) DO UPDATE SET bookings_count=bookings_count+1, total_nights=total_nights+excluded.total_nights, gross_revenue=gross_revenue+excluded.gross_revenue, net_revenue=net_revenue+excluded.net_revenue, updated_at=datetime('now')");
    $s->execute([$propertyId, $roomId, $platform, $monthYear, $nights, $amount, $commPct, $net]);
}

function getChannelStats(int $propertyId = 1, int $months = 6): array {
    $from = date('Y-m', strtotime("-{$months} months"));
    $s = getDB()->prepare("SELECT * FROM channel_stats WHERE property_id=? AND month_year >= ? ORDER BY month_year DESC, gross_revenue DESC");
    $s->execute([$propertyId, $from]);
    return $s->fetchAll();
}

// ──────────────────────────────────────────────────────────────────
// REVENUE SNAPSHOTS
// ──────────────────────────────────────────────────────────────────
function upsertRevenueSnapshot(int $propertyId, string $date, array $data): void {
    $s = getDB()->prepare("INSERT INTO revenue_snapshots (property_id, snapshot_date, adr, revpar, occupancy_pct, total_rooms, rooms_occupied, gross_revenue) VALUES (?,?,?,?,?,?,?,?) ON CONFLICT(property_id, snapshot_date) DO UPDATE SET adr=excluded.adr, revpar=excluded.revpar, occupancy_pct=excluded.occupancy_pct, rooms_occupied=excluded.rooms_occupied, gross_revenue=excluded.gross_revenue");
    $s->execute([$propertyId, $date, $data['adr'], $data['revpar'], $data['occupancy_pct'], $data['total_rooms'], $data['rooms_occupied'], $data['gross_revenue']]);
}

function getRevenueSnapshots(int $propertyId = 1, int $days = 30): array {
    $from = date('Y-m-d', strtotime("-{$days} days"));
    $s = getDB()->prepare("SELECT * FROM revenue_snapshots WHERE property_id=? AND snapshot_date >= ? ORDER BY snapshot_date ASC");
    $s->execute([$propertyId, $from]);
    return $s->fetchAll();
}

// ──────────────────────────────────────────────────────────────────
// CAMPAIGNS
// ──────────────────────────────────────────────────────────────────
function getCampaigns(int $propertyId = 1, bool $activeOnly = false): array {
    $where = 'property_id=?';
    $params = [$propertyId];
    if ($activeOnly) { $where .= ' AND is_active=1 AND valid_from <= date(\'now\') AND valid_until >= date(\'now\')'; }
    $s = getDB()->prepare("SELECT * FROM campaigns WHERE $where ORDER BY valid_from DESC");
    $s->execute($params);
    return $s->fetchAll();
}

function getActiveCampaigns(string $checkIn, string $checkOut, int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT * FROM campaigns WHERE property_id=? AND is_active=1 AND valid_from <= ? AND valid_until >= ? ORDER BY discount_pct DESC");
    $s->execute([$propertyId, $checkOut, $checkIn]);
    return $s->fetchAll();
}

function addCampaign(array $data, int $propertyId = 1): int {
    $db = getDB();
    $s  = $db->prepare("INSERT INTO campaigns (property_id,name,campaign_type,discount_pct,min_nights,valid_from,valid_until,advance_days,is_active) VALUES (?,?,?,?,?,?,?,?,?)");
    $s->execute([$propertyId,$data['name'],$data['campaign_type'] ?? 'general',$data['discount_pct'] ?? 0,$data['min_nights'] ?? 1,$data['valid_from'],$data['valid_until'],$data['advance_days'] ?? 0,$data['is_active'] ?? 1]);
    return (int)$db->lastInsertId();
}

function updateCampaign(int $id, array $data): void {
    $s = getDB()->prepare("UPDATE campaigns SET name=?,campaign_type=?,discount_pct=?,min_nights=?,valid_from=?,valid_until=?,advance_days=?,is_active=? WHERE id=?");
    $s->execute([$data['name'],$data['campaign_type'],$data['discount_pct'],$data['min_nights'],$data['valid_from'],$data['valid_until'],$data['advance_days'] ?? 0,$data['is_active'],$id]);
}

function deleteCampaign(int $id): void {
    getDB()->prepare("DELETE FROM campaigns WHERE id=?")->execute([$id]);
}

// ──────────────────────────────────────────────────────────────────
// COUPONS
// ──────────────────────────────────────────────────────────────────
function getCoupons(int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT * FROM coupons WHERE property_id=? ORDER BY created_at DESC");
    $s->execute([$propertyId]);
    return $s->fetchAll();
}

function getCouponByCode(string $code, int $propertyId = 1): ?array {
    $s = getDB()->prepare("SELECT * FROM coupons WHERE code=? AND property_id=? AND is_active=1");
    $s->execute([strtoupper(trim($code)), $propertyId]);
    return $s->fetch() ?: null;
}

function validateCoupon(string $code, int $nights, int $propertyId = 1): array {
    $c = getCouponByCode($code, $propertyId);
    if (!$c) return ['valid' => false, 'error' => 'Invalid or inactive coupon code.'];
    if ($c['valid_from'] > date('Y-m-d'))  return ['valid' => false, 'error' => 'Coupon not yet active.'];
    if ($c['valid_until'] < date('Y-m-d')) return ['valid' => false, 'error' => 'Coupon has expired.'];
    if ($c['max_uses'] > 0 && $c['uses_count'] >= $c['max_uses']) return ['valid' => false, 'error' => 'Coupon usage limit reached.'];
    if ($nights < $c['min_nights']) return ['valid' => false, 'error' => "Minimum stay of {$c['min_nights']} nights required."];
    return ['valid' => true, 'coupon' => $c, 'discount_pct' => (float)$c['discount_pct']];
}

function useCoupon(string $code, int $propertyId = 1): void {
    getDB()->prepare("UPDATE coupons SET uses_count=uses_count+1 WHERE code=? AND property_id=?")->execute([strtoupper(trim($code)), $propertyId]);
}

function addCoupon(array $data, int $propertyId = 1): int {
    $db = getDB();
    $s  = $db->prepare("INSERT INTO coupons (property_id,code,name,discount_pct,min_nights,max_uses,valid_from,valid_until,is_active) VALUES (?,?,?,?,?,?,?,?,?)");
    $s->execute([$propertyId,strtoupper(trim($data['code'])),$data['name'] ?? '',$data['discount_pct'] ?? 0,$data['min_nights'] ?? 1,$data['max_uses'] ?? 0,$data['valid_from'],$data['valid_until'],$data['is_active'] ?? 1]);
    return (int)$db->lastInsertId();
}

function updateCoupon(int $id, array $data): void {
    $s = getDB()->prepare("UPDATE coupons SET name=?,discount_pct=?,min_nights=?,max_uses=?,valid_from=?,valid_until=?,is_active=? WHERE id=?");
    $s->execute([$data['name'],$data['discount_pct'],$data['min_nights'],$data['max_uses'],$data['valid_from'],$data['valid_until'],$data['is_active'],$id]);
}

function deleteCoupon(int $id): void {
    getDB()->prepare("DELETE FROM coupons WHERE id=?")->execute([$id]);
}

// ──────────────────────────────────────────────────────────────────
// AGENTS
// ──────────────────────────────────────────────────────────────────
function getAgents(int $propertyId = 1, bool $activeOnly = false): array {
    $where = 'property_id=?';
    if ($activeOnly) $where .= ' AND is_active=1';
    $s = getDB()->prepare("SELECT * FROM agents WHERE $where ORDER BY name");
    $s->execute([$propertyId]);
    return $s->fetchAll();
}

function getAgentById(int $id): ?array {
    $s = getDB()->prepare("SELECT * FROM agents WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function addAgent(array $data, int $propertyId = 1): int {
    $db = getDB();
    $s  = $db->prepare("INSERT INTO agents (property_id,name,email,phone,commission_pct,address,is_active) VALUES (?,?,?,?,?,?,?)");
    $s->execute([$propertyId,$data['name'],$data['email'] ?? '',$data['phone'] ?? '',$data['commission_pct'] ?? 10,$data['address'] ?? '',$data['is_active'] ?? 1]);
    return (int)$db->lastInsertId();
}

function updateAgent(int $id, array $data): void {
    $s = getDB()->prepare("UPDATE agents SET name=?,email=?,phone=?,commission_pct=?,address=?,is_active=? WHERE id=?");
    $s->execute([$data['name'],$data['email'],$data['phone'],$data['commission_pct'],$data['address'],$data['is_active'],$id]);
}

function deleteAgent(int $id): void {
    getDB()->prepare("DELETE FROM agents WHERE id=?")->execute([$id]);
}

function getAgentStats(int $agentId, int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT COUNT(*) as bookings, SUM(amount) as revenue, SUM(JULIANDAY(check_out)-JULIANDAY(check_in)) as nights FROM bookings WHERE property_id=? AND notes LIKE ? AND status='confirmed'");
    $s->execute([$propertyId, "%agent_id:{$agentId}%"]);
    return $s->fetch() ?: ['bookings' => 0, 'revenue' => 0, 'nights' => 0];
}

// ──────────────────────────────────────────────────────────────────
// COMPANIES
// ──────────────────────────────────────────────────────────────────
function getCompanies(int $propertyId = 1, bool $activeOnly = false): array {
    $where = 'property_id=?';
    if ($activeOnly) $where .= ' AND is_active=1';
    $s = getDB()->prepare("SELECT * FROM companies WHERE $where ORDER BY name");
    $s->execute([$propertyId]);
    return $s->fetchAll();
}

function getCompanyById(int $id): ?array {
    $s = getDB()->prepare("SELECT * FROM companies WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function addCompany(array $data, int $propertyId = 1): int {
    $db = getDB();
    $s  = $db->prepare("INSERT INTO companies (property_id,name,gst_number,email,phone,address,discount_pct,credit_limit,is_active) VALUES (?,?,?,?,?,?,?,?,?)");
    $s->execute([$propertyId,$data['name'],$data['gst_number'] ?? '',$data['email'] ?? '',$data['phone'] ?? '',$data['address'] ?? '',$data['discount_pct'] ?? 0,$data['credit_limit'] ?? 0,$data['is_active'] ?? 1]);
    return (int)$db->lastInsertId();
}

function updateCompany(int $id, array $data): void {
    $s = getDB()->prepare("UPDATE companies SET name=?,gst_number=?,email=?,phone=?,address=?,discount_pct=?,credit_limit=?,is_active=? WHERE id=?");
    $s->execute([$data['name'],$data['gst_number'],$data['email'],$data['phone'],$data['address'],$data['discount_pct'],$data['credit_limit'],$data['is_active'],$id]);
}

function deleteCompany(int $id): void {
    getDB()->prepare("DELETE FROM companies WHERE id=?")->execute([$id]);
}

// ──────────────────────────────────────────────────────────────────
// GUEST CRM (derived from bookings — no separate guests table needed)
// ──────────────────────────────────────────────────────────────────
function getGuests(int $propertyId = 1, string $search = ''): array {
    $where  = ['b.property_id=?', "b.guest_name != 'Blocked'", "b.source != 'airbnb'", "b.source != 'booking'", "b.guest_email != ''"];
    $params = [$propertyId];
    if ($search) { $where[] = "(b.guest_name LIKE ? OR b.guest_email LIKE ? OR b.guest_phone LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
    $w = implode(' AND ', $where);
    $s = getDB()->prepare("
        SELECT b.guest_name, b.guest_email, b.guest_phone, b.whatsapp_number,
               COUNT(*) as total_stays, SUM(b.amount) as total_spent,
               MAX(b.check_in) as last_stay, MIN(b.check_in) as first_stay,
               SUM(CAST(JULIANDAY(b.check_out)-JULIANDAY(b.check_in) AS INTEGER)) as total_nights
        FROM bookings b
        WHERE $w
        GROUP BY b.guest_email
        ORDER BY total_stays DESC, last_stay DESC
    ");
    $s->execute($params);
    return $s->fetchAll();
}

function getGuestBookingHistory(string $email, int $propertyId = 1): array {
    $s = getDB()->prepare("SELECT * FROM bookings WHERE property_id=? AND guest_email=? ORDER BY check_in DESC");
    $s->execute([$propertyId, $email]);
    return $s->fetchAll();
}

function getGuestStats(int $propertyId = 1): array {
    $s = getDB()->prepare("
        SELECT
            COUNT(DISTINCT guest_email) as unique_guests,
            SUM(CASE WHEN cnt > 1 THEN 1 ELSE 0 END) as repeat_guests,
            AVG(total_spent) as avg_spend_per_guest
        FROM (
            SELECT guest_email, COUNT(*) as cnt, SUM(amount) as total_spent
            FROM bookings WHERE property_id=? AND status='confirmed' AND guest_email != '' AND guest_name != 'Blocked'
            GROUP BY guest_email
        )
    ");
    $s->execute([$propertyId]);
    return $s->fetch() ?: ['unique_guests' => 0, 'repeat_guests' => 0, 'avg_spend_per_guest' => 0];
}

// ──────────────────────────────────────────────────────────────────
// NIGHT AUDIT
// ──────────────────────────────────────────────────────────────────
function getNightAuditLog(int $propertyId = 1, int $limit = 30): array {
    $s = getDB()->prepare("SELECT * FROM night_audit_log WHERE property_id=? ORDER BY audit_date DESC LIMIT ?");
    $s->execute([$propertyId, $limit]);
    return $s->fetchAll();
}

function getAuditForDate(string $date, int $propertyId = 1): ?array {
    $s = getDB()->prepare("SELECT * FROM night_audit_log WHERE property_id=? AND audit_date=?");
    $s->execute([$propertyId, $date]);
    return $s->fetch() ?: null;
}

function logNightAudit(string $date, int $step, string $notes = '', int $propertyId = 1): void {
    $s = getDB()->prepare("INSERT INTO night_audit_log (property_id, audit_date, step, notes, completed_at) VALUES (?,?,?,?,datetime('now')) ON CONFLICT(property_id, audit_date) DO UPDATE SET step=excluded.step, notes=excluded.notes, completed_at=excluded.completed_at");
    $s->execute([$propertyId, $date, $step, $notes]);
}

// ──────────────────────────────────────────────────────────────────
// AVAILABILITY & PRICE LOGS
// ──────────────────────────────────────────────────────────────────
function logAvailabilityAction(string $roomId, string $action, string $checkIn = '', string $checkOut = '', string $source = 'admin', string $notes = '', int $propertyId = 1): void {
    $s = getDB()->prepare("INSERT INTO availability_logs (property_id, room_id, action, check_in, check_out, source, notes) VALUES (?,?,?,?,?,?,?)");
    $s->execute([$propertyId, $roomId, $action, $checkIn ?: null, $checkOut ?: null, $source, $notes]);
}

function getAvailabilityLogs(int $propertyId = 1, int $limit = 100): array {
    $s = getDB()->prepare("SELECT * FROM availability_logs WHERE property_id=? ORDER BY created_at DESC LIMIT ?");
    $s->execute([$propertyId, $limit]);
    return $s->fetchAll();
}

function logPriceChange(string $roomId, float $oldPrice, float $newPrice, string $reason = '', string $changedBy = 'admin', int $propertyId = 1): void {
    $s = getDB()->prepare("INSERT INTO price_logs (property_id, room_id, old_price, new_price, changed_by, reason) VALUES (?,?,?,?,?,?)");
    $s->execute([$propertyId, $roomId, $oldPrice, $newPrice, $changedBy, $reason]);
}

function getPriceLogs(int $propertyId = 1, int $limit = 100): array {
    $s = getDB()->prepare("SELECT * FROM price_logs WHERE property_id=? ORDER BY created_at DESC LIMIT ?");
    $s->execute([$propertyId, $limit]);
    return $s->fetchAll();
}

// ──────────────────────────────────────────────────────────────────
// LEGACY HELPERS (unchanged signatures for backward compat)
// ──────────────────────────────────────────────────────────────────
function deleteExternalCalendarById(int $id): void { deleteExternalCalendar($id); }
