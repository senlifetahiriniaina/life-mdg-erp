#!/usr/bin/env bash
set -euo pipefail

echo "=== WideHalo ERP — Web App Install ==="

# Copy env
if [ ! -f .env ]; then
  cp setup/.env.example .env
  echo "✓ .env created — edit API_URL to point to your widehalo-erp-api instance"
else
  echo "  .env already exists, skipping"
fi

# PHP dependencies
echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan optimize

# Node dependencies for webapp
echo "Installing frontend dependencies..."
cd webapp
npm ci --legacy-peer-deps
npm run build
cd ../..

echo ""
echo "=== WideHalo ERP Web ready. ==="
echo "Start: php artisan serve  (or use setup/docker-compose.yml)"
echo "API:   Set API_URL in .env to point to widehalo-erp-api"
