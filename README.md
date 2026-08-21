# Life MDG ERP

Life MDG's own ERP, built on the WideHalo ERP platform (Laravel 12 + Vue 3 + Inertia). This repository keeps the core system modules plus four business domains — Compta/Finance, Commercial/CRM, Stock/Logistique, and Pilotage/Reporting — enriched with a basic HR module (attendance, leave, payroll, project timesheets), a Helpdesk module coupled to every other module for incident/request management, and a real-time internal team Messaging module (added post-launch, outside the original 27-module extraction).

See [CLAUDE.md](CLAUDE.md) for the full module scope, architecture notes, and known gaps, [docs/CATALOGUE-FONCTIONNALITES.md](docs/CATALOGUE-FONCTIONNALITES.md) for a feature-level catalogue of what the app actually does, and [docs/INDEX.md](docs/INDEX.md) for the full technical documentation (architecture, per-module deep-dives, API conventions, database, deployment, RBAC/security, testing).

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

## License

MIT — see [LICENSE](LICENSE).
