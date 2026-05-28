# Test Credentials

## Channel Manager Admin
- **URL**: https://72fd2f13-e8c8-41bd-b110-ea2524952253.preview.emergentagent.com/channel-manager/admin.php
- **Password**: `KanchiFarm2025!`
- Auth method: Single shared password (defined in `config.php` → `ADMIN_PASSWORD`).
- No username field — just password.

## Preview Server
- The app runs via `php -S 0.0.0.0:3000 -t /app` as a background bash process (not supervisor-managed).
- If the preview goes down, restart with: `cd /app && nohup php -S 0.0.0.0:3000 -t /app > /tmp/php-server.log 2>&1 &`
