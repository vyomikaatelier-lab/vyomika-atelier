#!/bin/bash
# VYOMIKA ATELIER — Hostinger one-command setup
set -e

cd "$(dirname "$0")"
echo "=== VYOMIKA ATELIER Setup ==="

if [ ! -f .env ]; then
  cp .env.example .env
  echo "ERROR: Edit .env first with your DB password, then run again."
  echo "  nano .env"
  exit 1
fi

# Install Composer if needed
if [ ! -f composer.phar ]; then
  echo "Installing Composer..."
  curl -sS https://getcomposer.org/installer | php
fi

# Install Laravel dependencies
if [ ! -d vendor ]; then
  echo "Installing PHP packages (2-3 min)..."
  php composer.phar install --no-dev --optimize-autoloader --no-interaction
fi

# Generate app key if missing
if ! grep -q "APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

# Database
echo "Running migrations..."
php artisan migrate --force
php artisan db:seed --force

# Storage link
php artisan storage:link 2>/dev/null || ln -sf "$(pwd)/storage/app/public" "$(pwd)/public/storage"

# Permissions
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Link domain to Laravel public folder
DOMAIN_LINK="$HOME/domains/vyomikaatelier.com/public_html"
if [ -d "$(dirname "$DOMAIN_LINK")" ]; then
  rm -rf "$DOMAIN_LINK"
  ln -sf "$(pwd)/public" "$DOMAIN_LINK"
  echo "Linked: $DOMAIN_LINK -> $(pwd)/public"
else
  echo "WARNING: Domain folder not found. Link manually in hPanel."
fi

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache 2>/dev/null || true

echo ""
echo "=== DONE ==="
echo "Site:  https://vyomikaatelier.com"
echo "Admin: https://vyomikaatelier.com/admin"
echo "Login: admin@vyomikaatelier.com / changeme123"
