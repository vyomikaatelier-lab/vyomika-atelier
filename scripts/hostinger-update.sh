#!/bin/bash
# Run on Hostinger SSH after code is pushed to GitHub.
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/vyomika-atelier}"
cd "$APP_DIR"

echo "=== Vyomika Atelier — update ==="
echo "Directory: $(pwd)"

if [ ! -f artisan ]; then
  echo "ERROR: artisan not found. cd to your Laravel project first."
  exit 1
fi

echo "=== Git pull ==="
git fetch origin main
git pull origin main
echo "Commit on server: $(git log -1 --oneline)"

if grep -q "storeOverride" app/Support/LandingPageContent.php 2>/dev/null; then
  echo "Landing page save fix: INSTALLED"
else
  echo "Landing page save fix: MISSING — git pull did not get the latest code"
  exit 1
fi

echo "=== Laravel ==="
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== Deploy check ==="
php artisan vyomika:deploy-check

echo "=== Done ==="
echo "Hard-refresh admin in browser (Ctrl+Shift+R), then save Corten again."
