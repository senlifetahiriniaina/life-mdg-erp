# Life MDG ERP - Claude Code Configuration

## Project Overview

Life MDG ERP is a Laravel 12 + Vue 3 modular ERP, extracted and trimmed from the WideHalo ERP platform for Life MDG's own launch. It keeps WideHalo's seven founding principles, scoped to the modules Life MDG actually needs to start:

| Principle | Definition |
|---|---|
| **Africa First** | OHADA/SYSCOHADA, mobile money (Orange Money, MTN MoMo), CFA Franc, i18n |
| **Asia First** | Multi-currency/timezone, i18n (10 languages shipped in `lang/`) |
| **API First** | All features exposed as REST API before any UI |
| **Compliance First** | GDPR, PDPL, OHADA, OWASP by design |
| **Simplicity First** | Onboarding wizard (Setup module), smart defaults, AI-assisted import |
| **AI Assisted First** | Claude embedded at every friction point via `AiContextualAssistantService` |
| **Strategy First** | KPI/ratio cockpit, benchmarks, correlations, AI strategic recommendations |

## Scope: 27 modules

Extracted from the WideHalo ERP monorepo (`Widehalo-ERP`, 49 modules). Everything not listed below (Manufacturing, POS, Ecommerce, Email, WhatsApp, Documents, Planning, Quality, PLM, Discussion, Assets, Contracts, SMS, SmartTable, Notes, CustomerService, MarketingAutomation, RealTime, MobileSync, Messaging, and HR's advanced ATS/performance/training/succession features) was intentionally left out.

**Socle CORE / système (12)**: Core, AI, Security, AuditLog, API, Integration, Validation, Shared, Settings, Setup, Workflow, Calendar
**Compta et Finance**: Accounting
**Commercial et CRM**: CRM, Sales
**Stock et Logistique**: Inventory, Logistics, Achats
**Pilotage et Reporting**: BI, Analytics, Reporting, Strategy
**RH basique**: HR (trimmed — see below), Payroll, Timesheets, Projects
**Support**: Helpdesk (full, coupled to the other modules — see below)

### HR: "basique" scope only

`Modules/HR` kept only: `Employee, Department, Position/JobPosition, Attendance*, ShiftSchedule, BiometricDevice, LeaveRequest, LeaveBalance, LeaveType, LeaveApprovalLog, TimeOffRequest, EmployeeDocument, EmployeeSkill/Skill, CompensationHistory, SalaryBand, EmployeeCompensation, Deduction` plus their controllers/services/policies/tests. ATS/recruitment, 360° performance reviews, training catalogue, succession/org chart, advanced benefits, and engagement surveys were removed (not needed for launch) — including a duplicate legacy Payroll implementation that lived inside HR (the dedicated `Payroll` module is the single source of truth for payroll).

### Helpdesk: coupled to every other module

Any record in `Accounting\Invoice`, `CRM\Contact`, `Inventory\Product`, `Sales\SalesOrder`, `Achats\PurchaseOrder`, `Projects\Project`, `Logistics\Shipment`, or `HR\Employee` can raise and list its own tickets via the `Modules\Helpdesk\Traits\HelpdeskLinkable` trait:

```php
$invoice->raiseTicket(['subject' => 'Facture erronée', 'priority' => 'high']);
$invoice->tickets; // tickets linked to this invoice
```

`POST /api/v1/helpdesk/tickets` accepts an optional `source_module`/`source_id` pair (validated against an allowlist morph-map registered in `HelpdeskServiceProvider` — never a raw class name from client input) to link a new ticket to any record. A global "Signaler un incident" button (`resources/js/Components/Helpdesk/QuickTicketButton.vue`) is embedded in `AppLayout.vue` so any authenticated user can raise a ticket from anywhere in the app. To make a new module's model linkable, add the `HelpdeskLinkable` trait and register its morph-map alias.

## Repository Structure

```
/
├── app/                    # Root Laravel app (real entry point — NOT apps/api or apps/webapp, which don't exist here)
├── Modules/                # 27 ERP modules (laravel-modules / nwidart)
├── resources/js/           # Vue 3 + Inertia frontend (real entry: resources/js/app.js)
├── database/
│   ├── migrations/         # Root migrations (schema shared across modules)
│   └── seeders/            # RolesAndPermissionsSeeder is the active RBAC seeder
├── lang/                   # i18n dictionaries: ar, en, es, fr, ha, hi, mg, pt, sw, zh
├── tests/                  # Root Unit/Feature/Integration tests
└── config/, routes/, bootstrap/   # Standard Laravel
```

Each module self-registers its own routes via its `RouteServiceProvider` — `routes/api.php` at the root only holds global routes (health, auth, dashboard, AI chat, admin). Removing a module is `rm -rf Modules/<Name>` plus an entry removed from `config/modules_statuses.json`; no manual route wiring needed elsewhere.

## Development Workflow

**Backend (Laravel API):**
```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed

vendor/bin/pest                  # Run all tests
vendor/bin/pest --filter=TestName
vendor/bin/pint --test           # Code style
```

**Frontend (Vue 3):**
```bash
npm install
npm run dev                      # Start dev server
npm run type-check
npm run lint
npm run build
npm run test                     # Vitest unit tests
```

## Known gaps (not blocking launch, tracked here rather than silently left)

- **`composer.json`** intentionally drops the `widehalo/core`/`widehalo/modules` requires and their `.widehalo-core` path-repositories that Widehalo-ERP carried — nothing in the app code actually imports from that namespace (confirmed by grep across the whole tree before removing it), so it was dead weight.
- **`Dockerfile` cannot fully build**: it copies `docker/supervisor.conf`, `docker/php.ini`, `docker/php-fpm.conf`, and `docker/health-check.php`, none of which exist — confirmed this isn't an extraction gap, the source Widehalo-ERP repo doesn't have a `docker/` directory either (byte-identical `Dockerfile`, never actually exercised for real: `docker-build.yml` never ran until this repo's CI was validated, since it only triggers on paths this task didn't otherwise touch). The gitignored-lockfile and dead-`.widehalo-core` COPY lines were fixed (matching the same fixes applied to `.github/workflows/`), but the build stage will still fail at `COPY docker/supervisor.conf ...` until that directory and its 4 files are actually written — real infrastructure content (PHP-FPM tuning, supervisor process list, a health-check script), not something to fabricate without a decision on the serving model. Tracked here as a backlog item, not attempted in this pass.
- **`composer.lock` / `package-lock.json` are gitignored** (matching Widehalo-ERP's own convention) — run `composer install` / `npm install` fresh (not `npm ci`, which requires a lockfile to already exist rather than generating one — this bit CI too, see the GitHub Actions workflows' commit history); `nunomaduro/larastan` (dev-only, static analysis) may need a working GitHub API connection to install depending on your network — it is not required to run the app or the test suite.
- **A batch of features were already incomplete in the source Widehalo-ERP repo**, not something this extraction broke — multi-company Consolidation (`ConsolidationGroup`), ASC606 Revenue Recognition (`RevenueRecognitionEvent`), some Depreciation/Budget-variance advanced models (`GLJournal`, `BudgetActual`, `BudgetAlert`, `BudgetForecast`, `GLEntry`), a chunk of Security's deeper compliance-audit models (`ComplianceAudit`, `ComplianceViolation`, `EncryptedField`, `EncryptionKey`, `ServiceIdentity`, `TrustZone` — all reference a never-created root `App\Models\Company`), and a handful of Analytics ML-model classes reference model/service classes that were never actually created anywhere in the codebase, source included. These surface as individual test failures, not crashes — `vendor/bin/pest` runs to completion without fatal errors. They're enterprise-scope, not needed for Life MDG's initial launch; treat them as a backlog, not a regression.
- **Strategy module's `training_roi`/`time_to_fill` HR ratios** return static fallback values (145.0 / 28 days) since they depend on the Training/ATS features that are out of scope — this was already the design pattern used throughout `KPIRegistryService` for any data source that might not exist (every KPI query is wrapped in try/catch with a fallback), so it degrades the same way the rest of the app does when a data source is thin.
- **`Modules/Projects`' wiki feature was removed** (depended on the excluded `Notes` module) rather than decoupled with a stub — it was a self-contained side feature (`ProjectWikiService`/`ProjectWikiController`), not core project management.

## Testing Requirements

- `php artisan migrate:fresh --seed` — clean run required before merging
- `vendor/bin/pest` — backend tests
- `npm run build && npm run type-check` — frontend compiles
- `php -l` across changed files — this codebase has a history of PSR-4 namespace/filename mismatches that silently drop classes from autoload (see git history for examples fixed during extraction); a broad `php -l` sweep is cheap insurance

## Security & Compliance

Carried over unchanged from WideHalo: GDPR/PDPL/OHADA/OWASP-by-design, RBAC via `spatie/laravel-permission` (22 roles across the 27-module scope — see `database/seeders/RolesAndPermissionsSeeder.php`), audit logging, HMAC-signed webhooks, AES-256-GCM encryption at rest.

## AI Assisted First

`Modules\AI\Services\AiContextualAssistantService::getGuidance()` — same contract as WideHalo (see WideHalo's docs for the full call signature). Static fallback guidance already covers CRM, Accounting, HR, Inventory, Sales for this scope. When `ANTHROPIC_API_KEY` is absent, `enabled: false` is returned with static fallback text — never an error.

## Africa First / Asia First

- **Accounting Standards**: OHADA/SYSCOHADA (Accounting module)
- **Mobile Money**: Orange Money, MTN MoMo connectors (`Modules/Integration/app/Services/Connectors/`)
- **Currencies**: XOF/XAF/MGA and others via `Modules/Core/app/Services/SmartDefaultsService`
- **i18n**: `lang/{ar,en,es,fr,ha,hi,mg,pt,sw,zh}.json` — `resources/js/app.js` currently wires `en, fr, pt, es` into vue-i18n; the other six are present and ready to be added to that import list when needed

## Strategy First

Ratio definitions kept for Accounting, CRM, Inventory, Sales, Helpdesk, and (basic) HR — see `Modules/Strategy/app/Services/KPIRegistryService.php`. Manufacturing-only ratios were dropped along with the module.

---

**Extracted from WideHalo ERP:** August 2026
**Maintainer:** Life MDG Development Team
