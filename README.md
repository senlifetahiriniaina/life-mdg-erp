# Life MDG ERP

Life MDG's own ERP, built on the WideHalo ERP platform (Laravel 12 + Vue 3 + Inertia). This repository keeps the core system modules plus four business domains — Compta/Finance, Commercial/CRM, Stock/Logistique, and Pilotage/Reporting — enriched with a basic HR module (attendance, leave, payroll, project timesheets) and a Helpdesk module coupled to every other module for incident/request management.

See [CLAUDE.md](CLAUDE.md) for the full module scope, architecture notes, and known gaps, and [docs/INDEX.md](docs/INDEX.md) for the full technical documentation (architecture, per-module deep-dives, API conventions, database, deployment, RBAC/security, testing).

## Quick start

```bash
# Backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Frontend
npm install
npm run dev

# Backend dev server
php artisan serve
```

## Modules (27)

**Système**: Core, AI, Security, AuditLog, API, Integration, Validation, Shared, Settings, Setup, Workflow, Calendar
**Compta et Finance**: Accounting
**Commercial et CRM**: CRM, Sales
**Stock et Logistique**: Inventory, Logistics, Achats
**Pilotage et Reporting**: BI, Analytics, Reporting, Strategy
**RH et Support**: HR, Payroll, Timesheets, Projects, Helpdesk

## Testing

```bash
vendor/bin/pest              # Backend
npm run test                 # Frontend unit tests
npm run type-check            # TypeScript
```

## License

MIT — see [LICENSE](LICENSE).
