# Life MDG ERP

Life MDG's own ERP, built on the WideHalo ERP platform (Laravel 12 + Vue 3 + Inertia). This repository keeps the core system modules plus four business domains — Compta/Finance, Commercial/CRM, Stock/Logistique, and Pilotage/Reporting — enriched with a basic HR module (attendance, leave, payroll, project timesheets), a Helpdesk module coupled to every other module for incident/request management, and a real-time internal team Messaging module (added post-launch, outside the original 27-module extraction).

**Documentation:**
- [CLAUDE.md](CLAUDE.md) — full module scope, architecture notes, known gaps
- [docs/CATALOGUE-FONCTIONNALITES.md](docs/CATALOGUE-FONCTIONNALITES.md) — feature-level catalogue of what the app actually does
- [docs/INDEX.md](docs/INDEX.md) — full technical documentation (architecture, per-module deep-dives, API conventions, database, RBAC/security, testing)
- [docs/07-DEPLOIEMENT/GUIDE-DEPLOIEMENT-SIMPLE.md](docs/07-DEPLOIEMENT/GUIDE-DEPLOIEMENT-SIMPLE.md) — production deployment, step by step

## Requirements

- PHP 8.2+ and Composer
- Node.js 18+ and npm
- A database — MySQL 8+ (the `.env.example` default, matches production) or SQLite (fastest local start, see below)

## Local development

`.env.example` defaults to MySQL + Redis + Meilisearch (matching the production stack). If you don't already have those running, use Option A below to get a working app in a couple of minutes with zero external services.

### Option A — fastest start (SQLite, no external services)

```bash
cp .env.example .env
```

Edit `.env` and change these lines:

```bash
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
SCOUT_DRIVER=null
```

Then:

```bash
touch database/database.sqlite
composer install
php artisan key:generate
php artisan migrate --seed

npm install
npm run dev

# in another terminal
php artisan serve
```

This is the quickest way to evaluate the app or contribute — it's not meant to mirror production (no Redis-backed queue/cache, no search indexing).

### Option B — full stack (MySQL + Redis + Meilisearch, matches production)

Have MySQL and Meilisearch reachable (installed locally or via your own tooling), then start Redis with the compose file already provided in this repo:

```bash
docker compose -f docker-compose.redis.yml up -d
```

Then, without editing `.env.example`'s defaults:

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed

npm install
npm run dev

# in another terminal
php artisan serve
```

### First login

`migrate --seed` creates a default admin account: **`admin@life-mdg.com`** / **`admin`** (every role assigned, for a frictionless start). Change this password before exposing any real instance — see the checklist in [docs/07-DEPLOIEMENT/CHECKLIST-GO-LIVE.md](docs/07-DEPLOIEMENT/CHECKLIST-GO-LIVE.md).

## Modules (27 + Messaging)

**Système**: Core, AI, Security, AuditLog, API, Integration, Validation, Shared, Settings, Setup, Workflow, Calendar
**Compta et Finance**: Accounting
**Commercial et CRM**: CRM, Sales
**Stock et Logistique**: Inventory, Logistics, Achats
**Pilotage et Reporting**: BI, Analytics, Reporting, Strategy
**RH et Support**: HR, Payroll, Timesheets, Projects, Helpdesk
**Communication**: Messaging (real-time internal team messaging — added post-launch, not part of the original 27-module extraction; see [CLAUDE.md](CLAUDE.md) § Scope)

## Testing

```bash
vendor/bin/pest              # Backend
npm run test                 # Frontend unit tests
npm run type-check            # TypeScript
```

Tests always run against SQLite with array cache/session and a sync queue (`.env.testing`) — no extra setup needed, even if your local dev environment uses Option B above.

## Production deployment

The stack is **Docker Compose + Caddy** (`docker-compose.prod.yml` + `Caddyfile`, both at the repo root) — Caddy obtains and renews a Let's Encrypt SSL certificate automatically once your domain's DNS points at the server, no manual certbot step. Once you have a server with Docker and a domain pointing at it:

```bash
git clone <this-repo> life-mdg-erp && cd life-mdg-erp
cp .env.example .env   # set APP_DOMAIN, DB_*, MEILISEARCH_KEY, APP_ENV=production, APP_DEBUG=false
./scripts/deploy.sh
```

`scripts/deploy.sh` builds the image, starts the full stack, runs migrations, and verifies the app is healthy over HTTPS. Full walkthrough, prerequisites, and troubleshooting: [docs/07-DEPLOIEMENT/GUIDE-DEPLOIEMENT-SIMPLE.md](docs/07-DEPLOIEMENT/GUIDE-DEPLOIEMENT-SIMPLE.md).

**Don't have a server yet?** Two optional provisioning scripts create the instance/static IP/DNS for you, then hand off to `scripts/deploy.sh` above:
- **AWS Lightsail** — `scripts/lightsail-deploy.sh`, see [docs/07-DEPLOIEMENT/AWS-LIGHTSAIL.md](docs/07-DEPLOIEMENT/AWS-LIGHTSAIL.md)
- **Google Cloud Platform** — `scripts/gcp-deploy.sh`, see [docs/07-DEPLOIEMENT/GCP.md](docs/07-DEPLOIEMENT/GCP.md)

Further reading: [docs/07-DEPLOIEMENT/README.md](docs/07-DEPLOIEMENT/README.md) (CI/CD workflows), [docs/07-DEPLOIEMENT/ENV-PRODUCTION.md](docs/07-DEPLOIEMENT/ENV-PRODUCTION.md) (production environment variables), [docs/07-DEPLOIEMENT/CHECKLIST-GO-LIVE.md](docs/07-DEPLOIEMENT/CHECKLIST-GO-LIVE.md) (go-live checklist).

## License

MIT — see [LICENSE](LICENSE).
