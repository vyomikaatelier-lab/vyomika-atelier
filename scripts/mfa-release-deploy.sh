#!/bin/bash
# MFA release deploy (PR #1) — run on Hostinger SSH or hPanel browser terminal.
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/vyomika-atelier}"
cd "$APP_DIR"

TS="$(date +%Y%m%d-%H%M%S)"
mkdir -p database/backups storage/backups

echo "=== 1) Backups ==="
cp .env "storage/backups/.env.${TS}.bak"
echo "Saved .env -> storage/backups/.env.${TS}.bak"

if command -v mysqldump >/dev/null 2>&1; then
  DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')
  DB_USER=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
  DB_PASS=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')
  DB_HOST=$(grep -E '^DB_HOST=' .env | cut -d= -f2- | tr -d '"' || echo "127.0.0.1")
  BACKUP_FILE="database/backups/pre-mfa-${TS}.sql"
  MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$BACKUP_FILE"
  echo "Saved MySQL -> ${BACKUP_FILE}"
else
  echo "WARNING: mysqldump not found — take an hPanel MySQL backup before continuing."
fi

if [ -d storage/app/public ]; then
  tar -czf "storage/backups/uploads-${TS}.tar.gz" -C storage/app public 2>/dev/null \
    && echo "Saved uploads -> storage/backups/uploads-${TS}.tar.gz" \
    || echo "WARNING: could not archive storage/app/public"
fi

echo "=== 2) Production .env checks ==="
for key in SESSION_DRIVER SESSION_SECURE_COOKIE ADMIN_MFA_GRACE_DAYS ADMIN_MFA_ISSUER; do
  if grep -q "^${key}=" .env; then
    echo "OK ${key}=$(grep "^${key}=" .env | cut -d= -f2-)"
  else
    echo "MISSING ${key} — add before MFA goes live"
  fi
done

if grep -q '^SESSION_DRIVER=database' .env; then
  echo "WARNING: SESSION_DRIVER=database on shared hosting can 500 if misconfigured; HOSTINGER.md recommends file."
fi

echo "=== 3) Deploy merged main ==="
git fetch origin main
git pull origin main
echo "Commit: $(git log -1 --oneline)"

if [ -f composer.phar ]; then
  php composer.phar install --no-dev --optimize-autoloader
else
  composer install --no-dev --optimize-autoloader
fi

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status | grep -E 'admin_mfa|2026_08_06_180000' || true

echo "=== 4) Rebuild caches ==="
php artisan storefront:diagnose
php artisan config:cache
php artisan route:cache
php artisan view:cache || echo "WARNING: view:cache failed"
php artisan vyomika:deploy-check

echo "=== 5) Done — manual steps ==="
echo "1. Open https://vyomikaatelier.com/admin/login"
echo "2. Password login -> enroll TOTP -> save recovery codes"
echo "3. Smoke test storefront + admin settings"
echo "Grace window: ADMIN_MFA_GRACE_DAYS (default 7) for existing admins."
