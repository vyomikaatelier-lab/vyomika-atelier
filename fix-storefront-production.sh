#!/bin/bash
# Fix public storefront 500 after Hostinger deploy (admin works, / and /shop/* fail)
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/vyomika-atelier}"
DOMAIN_LINK="${DOMAIN_LINK:-$HOME/domains/vyomikaatelier.com/public_html}"

echo "=== Vyomika storefront recovery ==="
cd "$APP_DIR"

echo "1) Pull latest code..."
git fetch origin
git checkout main
git pull origin main

echo "2) Verify storefront files exist..."
for f in \
  config/site.php \
  app/Support/SiteContent.php \
  app/Support/StorefrontUrl.php \
  resources/views/layouts/store.blade.php \
  resources/views/home.blade.php \
  public/css/amerce.css \
  public/js/amerce.js; do
  if [ ! -f "$f" ]; then
    echo "ERROR: Missing $f — git pull may have failed or wrong branch."
    exit 1
  fi
done

echo "3) Composer (if needed)..."
if [ ! -f vendor/autoload.php ]; then
  php composer.phar install --no-dev --optimize-autoloader --no-interaction
fi

echo "4) Clear ALL caches (fixes stale route/view cache)..."
php artisan optimize:clear

echo "5) Migrations..."
php artisan migrate --force

echo "6) Restore symlinks (never rm -rf public_html when it points at public/)..."
mkdir -p storage/app/public
rm -f public/storage
ln -sf "$APP_DIR/storage/app/public" "$APP_DIR/public/storage"

if [ -d "$(dirname "$DOMAIN_LINK")" ]; then
  if [ -L "$DOMAIN_LINK" ]; then
    rm -f "$DOMAIN_LINK"
  elif [ -d "$DOMAIN_LINK" ]; then
    echo "WARNING: public_html is a real folder — replacing with symlink to Laravel public/"
    rm -rf "$DOMAIN_LINK"
  fi
  ln -sf "$APP_DIR/public" "$DOMAIN_LINK"
  echo "Linked: $DOMAIN_LINK -> $APP_DIR/public"
fi

if [ ! -f public/index.php ]; then
  echo "ERROR: public/index.php missing — restore from git: git checkout -- public/"
  exit 1
fi

chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "7) Rebuild production caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "8) Smoke check..."
php artisan route:list --name=home --columns=method,uri,name 2>/dev/null | head -5 || true
php artisan route:list --name=legal.privacy --columns=method,uri,name 2>/dev/null | head -5 || true

echo ""
echo "=== Done ==="
echo "Test: https://vyomikaatelier.com/"
echo "Test: https://vyomikaatelier.com/shop"
echo ""
echo "If still 500, temporarily enable debug:"
echo "  sed -i 's/^APP_DEBUG=.*/APP_DEBUG=true/' .env && php artisan config:clear"
echo "  tail -50 storage/logs/laravel.log"
