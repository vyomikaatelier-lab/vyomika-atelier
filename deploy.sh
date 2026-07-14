#!/bin/bash
# VYOMIKA ATELIER — Hostinger deployment script
# Run this on your Hostinger server via SSH after cloning the repo.

set -e

APP_DIR="${APP_DIR:-$HOME/vyomika-atelier}"
DOMAIN_DIR="${DOMAIN_DIR:-$HOME/domains/vyomikaatelier.com/public_html}"

echo "==> Deploying VYOMIKA ATELIER to $APP_DIR"

cd "$APP_DIR"

echo "==> Installing dependencies..."
php composer.phar install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || composer install --no-dev --optimize-autoloader --no-interaction

if [ ! -f .env ]; then
    cp .env.example .env
    echo "!!> Created .env — edit it with your database and mail settings before continuing."
    exit 1
fi

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Linking storage..."
php artisan storage:link 2>/dev/null || ln -sf "$APP_DIR/storage/app/public" "$APP_DIR/public/storage"

echo "==> Caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Linking public folder to domain..."
if [ -d "$DOMAIN_DIR" ]; then
    rm -f "$DOMAIN_DIR"
    ln -sf "$APP_DIR/public" "$DOMAIN_DIR"
    echo "    Linked $APP_DIR/public -> $DOMAIN_DIR"
else
    echo "!!> Domain dir not found: $DOMAIN_DIR"
    echo "    Manually point your domain to: $APP_DIR/public"
fi

echo ""
echo "==> Deployment complete!"
echo "    Site:  check your domain"
echo "    Admin: https://yourdomain.com/admin"
echo ""
echo "Next steps:"
echo "  1. Enable SSL in Hostinger hPanel"
echo "  2. Set APP_DEBUG=false in .env"
echo "  3. Run: php artisan db:seed --force  (first time only)"
echo "  4. Change admin password after login"
