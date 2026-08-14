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
- **`Dockerfile` now builds cleanly** — the `docker/{supervisor.conf,php.ini,php-fpm.conf,health-check.php}` gap tracked here previously has been closed: `docker/` now contains real infrastructure content (PHP-FPM pool tuned for the container's non-root `appuser`, OPcache/limits tuning, a supervisord fragment launching `php-fpm` in the foreground, and a TCP-connect health check against the exposed port 9000). Confirmed via a real `docker-build.yml` CI run, not just a local lint. The gitignored-lockfile and dead-`.widehalo-core` COPY lines were fixed earlier (matching the same fixes applied to `.github/workflows/`).
- **`composer.lock` / `package-lock.json` are gitignored** (matching Widehalo-ERP's own convention) — run `composer install` / `npm install` fresh (not `npm ci`, which requires a lockfile to already exist rather than generating one — this bit CI too, see the GitHub Actions workflows' commit history); `nunomaduro/larastan` (dev-only, static analysis) may need a working GitHub API connection to install depending on your network — it is not required to run the app or the test suite.
- **A batch of features were already incomplete in the source Widehalo-ERP repo**, not something this extraction broke — multi-company Consolidation (`ConsolidationGroup`), ASC606 Revenue Recognition (`RevenueRecognitionEvent`), some Depreciation/Budget-variance advanced models (`GLJournal`, `BudgetActual`, `BudgetAlert`, `BudgetForecast`, `GLEntry`), a chunk of Security's deeper compliance-audit models (`ComplianceAudit`, `ComplianceViolation`, `EncryptedField`, `EncryptionKey`, `ServiceIdentity`, `TrustZone` — all reference a never-created root `App\Models\Company`), and a handful of Analytics ML-model classes reference model/service classes that were never actually created anywhere in the codebase, source included. These surface as individual test failures, not crashes — `vendor/bin/pest` runs to completion without fatal errors. They're enterprise-scope, not needed for Life MDG's initial launch; treat them as a backlog, not a regression.
- **Strategy module's `training_roi`/`time_to_fill` HR ratios** return static fallback values (145.0 / 28 days) since they depend on the Training/ATS features that are out of scope — this was already the design pattern used throughout `KPIRegistryService` for any data source that might not exist (every KPI query is wrapped in try/catch with a fallback), so it degrades the same way the rest of the app does when a data source is thin.
- **`Modules/Projects`' wiki feature was removed** (depended on the excluded `Notes` module) rather than decoupled with a stub — it was a self-contained side feature (`ProjectWikiService`/`ProjectWikiController`), not core project management.
- **A security audit surfaced several gaps, most now fixed** (see `docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md` for the full state and traceability) — remaining backlog, not fixed in that pass: `Modules\Core\Services\SecretsService`'s claimed AES-256-GCM secrets vault depends on a `Modules\Core\Services\EncryptionService` class that was never created (same "references a never-created class" pattern as the Consolidation/ASC606/Security models noted above) — either implement it for real or formally deprecate the vault; ~~`JWT_SECRET` in `.env.example` reuses `APP_KEY`~~ — fixed by removing the whole `JWT_*` block from `.env.example`: it had no real consumer anywhere in the code (no `config/jwt.php`, no JWT library dependency), so "fixing" the key reuse would have meant building a feature nobody uses just to configure it correctly — dead config removed instead; the federation endpoints (`/api/v1/federation/*`) reference a `VerifyFederationSignature` middleware that doesn't exist and currently 500 on every request rather than being protected — decide if federation ships before implementing it; mandatory TOTP 2FA currently only covers admin/super-admin, not every role; 124 controllers extend `Illuminate\Routing\Controller` instead of `App\Http\Controllers\Controller` and would fatal if they ever called `$this->authorize()` (only the one in the HR PII path, `EmployeeController`, was fixed); `session.security` middleware (device fingerprint/hijack detection) is wired for Sanctum auth and applied to the HR module as the reference implementation, not yet rolled out to the other 26 modules' route groups.

## Testing Requirements

- `php artisan migrate:fresh --seed` — clean run required before merging
- `vendor/bin/pest` — backend tests
- `npm run build && npm run type-check` — frontend compiles
- `php -l` across changed files — this codebase has a history of PSR-4 namespace/filename mismatches that silently drop classes from autoload (see git history for examples fixed during extraction); a broad `php -l` sweep is cheap insurance

## Security & Compliance

RBAC via `spatie/laravel-permission` (22 roles across the 27-module scope — see `database/seeders/RolesAndPermissionsSeeder.php`). **`docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md` is the source of truth** for the actual state of every security control (encryption, audit logging, webhooks, RBAC enforcement, session/auth hardening) and for Madagascar's legal/compliance framework (Loi 2014-038, Décret 2023-1541, CMIL, Convention de Malabo/Loi 2024-004, ANSSI-Madagascar) — do not restate "AES-256-GCM" (the real cipher is AES-256-CBC) or an unqualified "GDPR/PDPL/OHADA/OWASP" compliance line here or anywhere else; link to that document instead.

## AI Assisted First

`Modules\AI\Services\AiContextualAssistantService::getGuidance()` — same contract as WideHalo (see WideHalo's docs for the full call signature). Static fallback guidance already covers CRM, Accounting, HR, Inventory, Sales for this scope. `getGuidance()` delegates to `Modules\Core\Services\AI\AIService::forModule('AI')`, which resolves whichever provider is active (`AI_DEFAULT_PROVIDER`, default `anthropic`); when that provider isn't configured or the call throws, `enabled: false` is returned with static fallback text — never an error.

**Self-hosted DeepSeek provider**: in addition to Anthropic/OpenAI, a self-hosted DeepSeek provider (served via Ollama, `docker-compose.deepseek.yml`) is available and selectable via `AI_DEFAULT_PROVIDER=deepseek` or a targeted `module_providers` override in `config/ai.php` — additive only, zero behavior change when unset. Covers both `Modules\Core\Services\AI\AIService` (used by the `<Module>AIService` business services) and `AiContextualAssistantService`. See `docs/07-DEPLOIEMENT/IA-AUTOHEBERGEE.md`.

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
