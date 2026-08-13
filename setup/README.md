# WideHalo ERP — Web App Setup

This folder provides everything needed to run `Widehalo-ERP` as a standalone web application.

## Prerequisites

- PHP 8.3+, Composer 2.x
- Node.js 22+
- Redis 7.4+
- A running `widehalo-erp-api` instance (the API backend)

## Quick Start (Docker)

```bash
# 1. Configure environment
cp setup/.env.example .env
# Edit .env: set API_URL to your widehalo-erp-api endpoint

# 2. Start services (Nginx + PHP + Node + Redis)
docker compose -f setup/docker-compose.yml up -d

# 3. Install and build
bash setup/install.sh
```

The web app will be available at **http://localhost:3000**.

## Quick Start (bare metal)

```bash
cp setup/.env.example .env
# Edit .env: set API_URL=http://<api-host>:8000

composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan optimize

cd webapp
npm ci --legacy-peer-deps
npm run build
cd ../..

php artisan serve
```

## Configuration

| Variable      | Description                        | Default               |
|---------------|------------------------------------|-----------------------|
| `API_URL`     | URL to widehalo-erp-api            | `http://localhost:8000` |
| `VITE_API_URL`| API URL exposed to Vue frontend    | `http://localhost:8000/api/v1` |

## Architecture

This web app is a **thin Laravel/Inertia.js frontend** that delegates all business logic to `widehalo-erp-api`.

```
widehalo-erp-api  ←── REST JSON API (all business logic, all modules)
Widehalo-ERP      ←── Inertia/Vue 3 web frontend (this repo)
```
