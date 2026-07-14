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

# Fix SSL: Hostinger PHP often has no valid CA bundle for Composer
setup_ca_bundle() {
  CAFILE="$(pwd)/cacert.pem"
  if [ ! -f "$CAFILE" ]; then
    echo "Downloading CA certificate bundle..."
    curl -sS https://curl.se/ca/cacert.pem -o "$CAFILE" || wget -q -O "$CAFILE" https://curl.se/ca/cacert.pem
  fi
  if [ -f "$CAFILE" ]; then
    export SSL_CERT_FILE="$CAFILE"
    export CURL_CA_BUNDLE="$CAFILE"
    php composer.phar config --global --unset cafile 2>/dev/null || true
    php composer.phar config cafile "$CAFILE"
    echo "Using CA bundle: $CAFILE"
  else
    echo "WARNING: Could not download CA bundle — Composer may fail on SSL."
  fi
}

# Install Composer if needed
if [ ! -f composer.phar ]; then
  echo "Installing Composer..."
  curl -sS https://getcomposer.org/installer | php
fi

setup_ca_bundle

# Composer 2.10 blocks packages with open security advisories on fresh installs without composer.lock
php composer.phar config audit.block-insecure false 2>/dev/null || true

# Install Laravel dependencies
if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
  echo "Installing PHP packages (2-3 min)..."
  php -d openssl.cafile="$(pwd)/cacert.pem" -d curl.cainfo="$(pwd)/cacert.pem" \
    composer.phar install --no-dev --optimize-autoloader --no-interaction
fi

# Generate app key if missing
if ! grep -q "APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

# Database
echo "Running migrations..."
php artisan migrate --force
php artisan db:seed --force

# Storage link (skip artisan — Hostinger disables exec(); use ln directly)
echo "Linking storage..."
mkdir -p storage/app/public
rm -f public/storage
ln -sf "$(pwd)/storage/app/public" "$(pwd)/public/storage"

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
