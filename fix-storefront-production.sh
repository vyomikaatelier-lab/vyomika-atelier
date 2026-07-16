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
echo "    HEAD: $(git log -1 --oneline)"
if ! grep -q 'usesCheckoutFlow' app/Models/Product.php 2>/dev/null; then
  echo "ERROR: Product.php missing usesCheckoutFlow — git pull did not get latest main (need d9b8655+)."
  exit 1
fi
GALLERY_BLADE="resources/views/partials/am-service-product-gallery.blade.php"
if grep -q 'am-design-gallery__count' "$GALLERY_BLADE" 2>/dev/null; then
  echo "ERROR: $GALLERY_BLADE still shows design count text — need latest main."
  exit 1
fi
if ! grep -q 'am-btn--card-view' "$GALLERY_BLADE" 2>/dev/null; then
  echo "ERROR: $GALLERY_BLADE missing gallery card action row — need latest main."
  exit 1
fi

echo "2) Verify storefront files exist..."
git checkout -- public/css/amerce.css public/css/amerce-themes.css public/css/responsive.css public/js/amerce.js public/js/responsive.js 2>/dev/null || true

for f in \
  config/site.php \
  app/Support/SiteContent.php \
  app/Support/StorefrontUrl.php \
  resources/views/layouts/store.blade.php \
  resources/views/home.blade.php \
  public/css/amerce.css \
  public/css/responsive.css \
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

echo "4) Clear ALL caches (fixes stale route/view cache — required after gallery CTA blade changes)..."
php artisan optimize:clear
php artisan view:clear

echo "5) Migrations..."
php artisan migrate --force

echo "5b) Sync studio catalog (partitions, doors, furniture gallery products)..."
php artisan db:seed --class=CatalogSyncSeeder --force

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

echo "8) Diagnose storefront (must pass before caching routes/views)..."
if php artisan storefront:diagnose; then
  php artisan route:cache
  php artisan view:cache
else
  echo ""
  echo "WARNING: Diagnose failed — skipping route:cache and view:cache so errors surface in logs."
  echo "Fix the error above, then re-run: bash fix-storefront-production.sh"
  exit 1
fi

echo "9) Smoke check..."
php artisan route:list --name=home --columns=method,uri,name 2>/dev/null | head -5 || true
php artisan route:list --name=legal.privacy --columns=method,uri,name 2>/dev/null | head -5 || true

if grep -q 'Buy Now' resources/views/collections/mirror-frames/index.blade.php 2>/dev/null; then
  echo "    OK: mirror-frames blade has Buy Now buttons"
else
  echo "WARNING: mirror-frames blade missing Buy Now — wrong branch or stale files"
fi

if grep -q 'am-btn--card-view' "$GALLERY_BLADE" 2>/dev/null \
  && ! grep -q 'am-design-gallery__count' "$GALLERY_BLADE" 2>/dev/null; then
  echo "    OK: studio gallery card action row (View Details + CTA)"
else
  echo "WARNING: studio gallery blade may be outdated"
fi

echo ""
echo "=== Done ==="
echo "Test: https://vyomikaatelier.com/"
echo "Test: https://vyomikaatelier.com/shop"
echo ""
echo "If still 500, temporarily enable debug:"
echo "  sed -i 's/^APP_DEBUG=.*/APP_DEBUG=true/' .env && php artisan config:clear"
echo "  tail -50 storage/logs/laravel.log"
