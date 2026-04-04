<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        _initSchema($db);
    }
    return $db;
}

function _initSchema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS bookings (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            room_id      TEXT    NOT NULL,
            room_name    TEXT    NOT NULL,
            check_in     DATE    NOT NULL,
            check_out    DATE    NOT NULL,
            guest_name   TEXT    DEFAULT 'Blocked',
            guest_email  TEXT    DEFAULT '',
            guest_phone  TEXT    DEFAULT '',
            source       TEXT    DEFAULT 'direct',
            booking_ref  TEXT    DEFAULT '',
            amount       REAL    DEFAULT 0,
            status       TEXT    DEFAULT 'confirmed',
            uid          TEXT    UNIQUE,
            notes        TEXT    DEFAULT '',
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS external_calendars (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            room_id      TEXT NOT NULL,
            platform     TEXT NOT NULL,
            ical_url     TEXT NOT NULL,
            last_synced  DATETIME,
            is_active    INTEGER DEFAULT 1,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
}

// ---- Bookings ------------------------------------------------

function addBooking(array $data): int|false {
    $db  = getDB();
    $uid = $data['uid'] ?? ('bk-' . bin2hex(random_bytes(8)) . '@kanchifarmstay.com');
    $stmt = $db->prepare("
        INSERT OR IGNORE INTO bookings
            (room_id, room_name, check_in, check_out,
             guest_name, guest_email, guest_phone,
             source, booking_ref, amount, status, uid, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $data['room_id'],
        $data['room_name'],
        $data['check_in'],
        $data['check_out'],
        $data['guest_name']  ?? 'Guest',
        $data['guest_email'] ?? '',
        $data['guest_phone'] ?? '',
        $data['source']      ?? 'direct',
        $data['booking_ref'] ?? '',
        $data['amount']      ?? 0,
        $data['status']      ?? 'confirmed',
        $uid,
        $data['notes']       ?? '',
    ]);
    return $stmt->rowCount() ? (int)$db->lastInsertId() : false;
}

function getAllBookings(array $filters = []): array {
    $db     = getDB();
    $where  = ['1=1'];
    $params = [];
    if (!empty($filters['room_id'])) { $where[] = 'room_id = ?'; $params[] = $filters['room_id']; }
    if (!empty($filters['source']))  { $where[] = 'source = ?';  $params[] = $filters['source']; }
    $w    = implode(' AND ', $where);
    $stmt = $db->prepare("SELECT * FROM bookings WHERE $w ORDER BY check_in DESC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getUpcomingBookings(): array {
    $stmt = getDB()->prepare("
        SELECT * FROM bookings
        WHERE status = 'confirmed' AND check_out >= date('now')
        ORDER BY check_in
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getBlockedRanges(string $roomId): array {
    $stmt = getDB()->prepare("
        SELECT check_in, check_out FROM bookings
        WHERE room_id = ? AND status = 'confirmed' AND check_out >= date('now')
        ORDER BY check_in
    ");
    $stmt->execute([$roomId]);
    return $stmt->fetchAll();
}

function deleteBooking(int $id): void {
    $stmt = getDB()->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
}

function cancelBooking(int $id): void {
    $stmt = getDB()->prepare("UPDATE bookings SET status='cancelled', updated_at=datetime('now') WHERE id=?");
    $stmt->execute([$id]);
}

// ---- External Calendars -------------------------------------

function getExternalCalendars(): array {
    return getDB()->query("SELECT * FROM external_calendars ORDER BY room_id, platform")->fetchAll();
}

function addExternalCalendar(string $roomId, string $platform, string $url): int {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO external_calendars (room_id, platform, ical_url) VALUES (?,?,?)");
    $stmt->execute([$roomId, $platform, $url]);
    return (int)$db->lastInsertId();
}

function deleteExternalCalendar(int $id): void {
    $stmt = getDB()->prepare("DELETE FROM external_calendars WHERE id = ?");
    $stmt->execute([$id]);
}

function touchLastSynced(int $calendarId): void {
    $stmt = getDB()->prepare("UPDATE external_calendars SET last_synced = datetime('now') WHERE id = ?");
    $stmt->execute([$calendarId]);
}
