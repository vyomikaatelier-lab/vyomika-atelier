#!/bin/bash
# Run after every Hostinger Git redeploy (or via SSH)
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/vyomika-atelier}"
DOMAIN_LINK="${DOMAIN_LINK:-$HOME/domains/vyomikaatelier.com/public_html}"

echo "=== VYOMIKA post-deploy ==="
cd "$APP_DIR"

# Re-link domain → Laravel public (Git redeploy often breaks this → 403)
if [ -d "$(dirname "$DOMAIN_LINK")" ]; then
  if [ -L "$DOMAIN_LINK" ]; then
    rm -f "$DOMAIN_LINK"
  elif [ -d "$DOMAIN_LINK" ]; then
    echo "WARNING: public_html is a folder (not symlink). Removing it..."
    rm -rf "$DOMAIN_LINK"
  fi
  ln -sf "$APP_DIR/public" "$DOMAIN_LINK"
  echo "Linked: $DOMAIN_LINK -> $APP_DIR/public"
fi

mkdir -p storage/app/public database/backups
rm -f public/storage
ln -sf "$APP_DIR/storage/app/public" "$APP_DIR/public/storage"
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "=== Database backup before classification ==="
BACKUP_FILE="database/backups/pre-deploy-$(date +%Y%m%d-%H%M%S).sql"
if command -v mysqldump >/dev/null 2>&1 && [ -f .env ]; then
  DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')
  DB_USER=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
  DB_PASS=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')
  DB_HOST=$(grep -E '^DB_HOST=' .env | cut -d= -f2- | tr -d '"' || echo "127.0.0.1")
  if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
    MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$BACKUP_FILE" \
      && echo "Backup saved to $BACKUP_FILE" \
      || echo "WARNING: mysqldump failed — create a manual backup before classification."
  fi
else
  echo "WARNING: mysqldump unavailable — create a manual backup before classification."
fi

echo "=== Pull latest code ==="
git pull origin main

echo "=== Composer ==="
if [ -f composer.phar ]; then
  php composer.phar install --no-dev --optimize-autoloader
elif command -v composer >/dev/null 2>&1; then
  composer install --no-dev --optimize-autoloader
else
  echo "ERROR: composer not found — run: curl -sS https://getcomposer.org/installer | php"
  exit 1
fi

echo "=== Clear caches ==="
php artisan optimize:clear

echo "=== Migrate ==="
php artisan migrate --force

echo "=== Catalog seeders ==="
php artisan db:seed --class=CatalogSyncSeeder --force
php artisan db:seed --class=CorrectCatalogClassificationSeeder --force

echo "=== Export site JSON ==="
php database/scripts/export-site-json.php
php database/scripts/export-pricing-json.php
php database/scripts/export-finishes-json.php

echo "=== Rebuild caches ==="
php artisan storefront:diagnose || {
  echo "ERROR: storefront:diagnose failed — fix errors before caching routes/views."
  exit 1
}
php artisan config:cache
php artisan route:cache
php artisan view:cache || echo "WARNING: view:cache failed — check storage/logs/laravel.log"

echo "=== Done — site should load without 403 ==="
