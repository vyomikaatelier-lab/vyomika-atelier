#!/bin/bash
# One-command Hostinger setup for vyomikaatelier.com
# Usage: bash setup-hostinger.sh

set -e

echo "=== VYOMIKA ATELIER — Hostinger Setup ==="

cd "$(dirname "$0")"

if [ ! -f .env ]; then
  cp .env.example .env
  echo ""
  echo "Created .env — edit DB and mail settings, then run this script again."
  echo "  nano .env"
  exit 1
fi

if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
  if [ -f composer.phar ]; then
    php composer.phar install --no-dev --optimize-autoloader --no-interaction
  else
    curl -sS https://getcomposer.org/installer | php
    php composer.phar install --no-dev --optimize-autoloader --no-interaction
  fi
  php artisan key:generate --force
  php artisan migrate --force
  php artisan db:seed --force
  php artisan storage:link 2>/dev/null || true
  chmod -R 775 storage bootstrap/cache
fi

DOMAIN_LINK="$HOME/domains/vyomikaatelier.com/public_html"
if [ -d "$(dirname "$DOMAIN_LINK")" ]; then
  rm -rf "$DOMAIN_LINK"
  ln -sf "$(pwd)/public" "$DOMAIN_LINK"
  echo "Linked public -> $DOMAIN_LINK"
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache 2>/dev/null || true

echo ""
echo "=== Done ==="
echo "Site:  https://vyomikaatelier.com"
echo "Admin: https://vyomikaatelier.com/admin"
echo "Login: admin@vyomikaatelier.com / changeme123"
