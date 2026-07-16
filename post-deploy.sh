#!/bin/bash
# Run after every Hostinger Git redeploy (or via SSH)
set -e

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

# Storage
mkdir -p storage/app/public
rm -f public/storage
ln -sf "$APP_DIR/storage/app/public" "$APP_DIR/public/storage"

chmod -R 775 storage bootstrap/cache 2>/dev/null || true

php artisan migrate --force 2>/dev/null || true

# Clear stale caches before rebuild (old route cache breaks new storefront layout)
php artisan optimize:clear

php artisan config:cache

if php artisan storefront:diagnose 2>/dev/null; then
  php artisan route:cache
  php artisan view:clear
  php artisan view:cache || echo "WARNING: view:cache failed — check storage/logs/laravel.log"
else
  echo "WARNING: storefront:diagnose failed — run: bash fix-storefront-production.sh"
  php artisan view:clear
fi

echo "=== Done — site should load without 403 ==="
