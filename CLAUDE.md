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
- **Most of what was originally flagged here as "incomplete in the source Widehalo-ERP repo" has since been built for real**, not just documented: multi-company Consolidation now lives behind a single unified `Modules\Accounting\Models\ConsolidationGroup` (the parallel `ConsolidationHierarchy` stack was kept too — it's a genuinely different concept, an ownership tree with periods/eliminations — and wired up rather than deleted); the Budget-variance models (`BudgetActual`, `BudgetAlert`, `BudgetForecast`, `GLEntry`) and Security's compliance-audit models (`ComplianceAudit`, `ComplianceViolation`, `EncryptedField`, `EncryptionKey`, `ServiceIdentity`, `TrustZone`) all reconnected cleanly once the root `App\Models\Company` foundation landed. **ASC606 Revenue Recognition (`RevenueRecognitionEvent`/`RevenueContract`) is the one exception, explicitly excluded rather than built**: it's the US-GAAP five-step revenue-recognition standard (performance-obligation allocation, variable-consideration constraint, contract-liability schedules) — Life MDG keeps its books under OHADA/SYSCOHADA, which has no equivalent concept, so there is nothing to port. `Modules/Accounting/tests/Feature/RevenueContractTest.php`'s 5 failures are this gap, not a regression.
- **Strategy module's `training_roi`/`time_to_fill` HR ratios** return static fallback values (145.0 / 28 days) since they depend on the Training/ATS features that are out of scope — this was already the design pattern used throughout `KPIRegistryService` for any data source that might not exist (every KPI query is wrapped in try/catch with a fallback), so it degrades the same way the rest of the app does when a data source is thin.
- **`Modules/Projects`' wiki feature was removed** (depended on the excluded `Notes` module) rather than decoupled with a stub — it was a self-contained side feature (`ProjectWikiService`/`ProjectWikiController`), not core project management.
- **A security audit surfaced several gaps, now fixed** (see `docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md` for the full state and traceability): `Modules\Core\Services\SecretsService`'s AES-256-CBC secrets vault is backed by a real `Modules\Core\Services\EncryptionService`/`KeyManagementService` pair; the federation endpoints (`/api/v1/federation/*`) are protected by a real `VerifyFederationSignature` middleware (HMAC-SHA256 + anti-replay); mandatory TOTP 2FA roles are config-driven (`config('security.mandatory_2fa_roles')`) rather than hard-coded to admin/super-admin; all controllers extend `App\Http\Controllers\Controller`; `session.security` middleware (device fingerprint/hijack detection) is rolled out across all 27 modules' route groups, not just HR.
- **GraphQL (Lighthouse) is now wired up as a single real schema** — `project/graphql/schema.graphql` (CRM contacts/accounts to start, with `@paginate`/`@guard`/explicit resolver classes) is what `config/lighthouse.php:76`'s `schema_path` actually points at. This replaced a second, parallel "GraphQL v2" stack (`Modules\API\Services\GraphQLSchemaBuilderService`/`GraphQLQueryOptimizerService`/`GraphQLSubscriptionManagerService`) that stored hand-rolled schema fragments in `Cache` and never served a real GraphQL endpoint — those services were kept (74 tests outside the GraphQL-specific file depend on them for non-GraphQL query-optimization/subscription bookkeeping) but no longer duplicate schema-building responsibility.
- **10 items (66 test failures) were deliberately left unbuilt after a full test-suite triage**, rather than constructed, because none of them are needed to operate the ERP for Life MDG:
  - **Mobile device auth** (`Modules/Core`, 11) — device registry/biometric-enrollment/refresh-token endpoints for the excluded Mobile/MobileSync modules; there is no React Native/Expo app anywhere in this repo to call them.
  - **FMLA eligibility checking** (`Modules/HR`, 1) — US federal employment law (12 months tenure + 1250 hours worked); has no legal meaning for a Malagasy employer, whose leave rules already come from the Code du Travail malgache/CNaPS via the kept `LeaveType`/`LeaveRequest`/`LeaveBalance` models.
  - **Offer-letter generation** (`Modules/HR`, 1) — a recruitment artefact issued before someone is an employee; ATS/recruitment was already removed from HR's "basique" scope (see above).
  - **ASC606 Revenue Recognition** (`Modules/Accounting`, 5) — see the entry above; OHADA/SYSCOHADA has no equivalent concept to port.
  - **Production-capacity forecasting** (`Modules/Analytics`, 1) — built entirely on the excluded Manufacturing module's schema (`work_centers`, `manufacturing_orders`, `bom_components`), which doesn't exist in this repo.
  - **Skill matrix / competency grid** (`Modules/HR`, 6) — the HR "basique" scope note above already documents that the training catalogue and skill-matrix visualization were removed on purpose; the routed HR skills endpoint's own code comment says so too.
  - **Agent talent management** (`Modules/Helpdesk`, 19) — coaching recommendations, SMART goals, and skill-proficiency scoring for support agents is 360°-performance-review territory wearing a Helpdesk badge; excluded for the same reason HR's own 360° reviews were.
  - **Onboarding funnel telemetry** (`Modules/Setup`, 15) — `OnboardingSession`/`OnboardingStepEvent`/`FunnelSnapshot` measure WideHalo's own product-marketing KPI (wizard completion rate, median setup minutes, AI-mapping adoption) about the onboarding wizard itself, not something a Malagasy SME needs in order to run its own onboarding.
  - **`MinioService` (Deepnest/Blender render pipeline)** (`Modules/Integration`, 2) — object storage for 3D-nesting/CAD renders, a Manufacturing/PLM-domain feature, out of scope.
  - **Pessimistic task locking for real-time collaborative editing** (`Modules/Projects`, 5) — concurrent-edit lock arbitration depends on the excluded RealTime/Discussion modules.
- **Chantier 8.1 (Accounting cross-layer audit) rewiring/cleanup**: a full API/Controller/View/Data audit of `Modules/Accounting` (the largest module, 76+ models) found real end-to-end gaps and fixed the reachable ones — `BankAccount::reconciliationSessions()` relation was missing (would crash `ReconciliationSessionController`), `BankReconciliationService::autoMatchAccount()`/`completeReconciliation()` were missing (the Reconcile.vue "Auto-Match"/"Terminer" buttons had nowhere real to call), `acc_bank_accounts` was missing `gl_account_id`/`iban`/`bic` columns despite `StoreBankAccountRequest` validating them (silently dropped on every create/update, and `BankAccount::glAccount()` crashed `GET bank/{bank}` outright since the column didn't exist at all) — all now routed under `bank-accounts/{bankAccount}/...` and `bank/...`, with `Reconcile.vue`/`BankReconciliation/Index.vue` pointed at the real endpoints/field names (`entry_id` not `journal_entry_id`, `POST bank` not `POST bank-accounts`). Five genuinely redundant controllers (`BalanceSheetController`, `IncomeStatementController`, `CashFlowController`, `LedgerMatchingController`, `OpenBankingController` — all superseded by already-routed `ReportController`/`AccOpenBankingController` methods) and two duplicate dead Vue pages (`Modules/Accounting/resources/js/Pages/{Invoices,Expenses}/Index.vue`, masked by the real root-level copies) were deleted, plus `TransactionAIController`/`BalanceSheetAIController` (functionally duplicated already-routed `AccountingAIController::categorizeTransactions`/`summarizeBalanceSheet`).
- **Chantier 8.1b — the 7 previously-unrouted Accounting controllers were built out into full features, not just routed**: `AssetImpairmentController`, `BudgetVarianceController`, `CostEngineController`, `DepreciationPolicyController`, `DepreciationScheduleController`, `IntercompanyClearanceController`, `ScenarioPlanningController` (the 3 that live directly under `Http/Controllers/`, not `Http/Controllers/Api/`, also got a critical fix: none of them extended `App\Http\Controllers\Controller`, so every `$this->authorize()` call inside them was a fatal `Error: Call to undefined method` waiting to happen the moment they were ever hit). Also fixed: `AssetImpairmentController`/`DepreciationScheduleController` validated against bare table names (`fixed_assets`, `gl_accounts`, `journal_entries`) instead of the real `acc_`-prefixed ones; `IntercompanyClearanceController::index()` called `$q->user()` on a query `Builder` instead of `$request->user()` (fatal error); `DepreciationScheduleController::store()` seeded `book_value` from the annual depreciation amount instead of the asset's `acquisition_cost`; `BudgetVarianceController`'s 6 methods all called `BudgetVarianceService` methods that didn't exist (`analyzeVariance`, `drilldownVariance`, etc.) — rewired onto the service's real, already-tested methods (`calculateVariance`, `varianceReport`, `monthlyTrend`, `topVariances`), plus 2 new small aggregation methods (`varianceByDepartment`/`varianceByCategory`) on the real `Budget::department`/`BudgetLine::category` columns; `ScenarioPlanningService` was an explicit stub (every method returned `implemented:false`) — replaced with real delegation to `BudgetVarianceService::projectScenario()` (already real, used elsewhere), plus a `listScenarios()`/`index()` endpoint the controller was missing entirely. `BudgetPolicy`/`BudgetScenarioPolicy` (new) and the `accounting.asset_impairment.*`/`accounting.depreciation_policy.*`/`accounting.budget.*`/`accounting.budget_scenario.*` permissions (new, `RolesAndPermissionsSeeder`) close the gap where `AssetImpairmentPolicy`/`DepreciationPolicyPolicy` already existed but their permissions were never seeded, so every non-super-admin check silently failed. 7 new Web controllers + Vue pages (`Modules/Accounting/resources/js/Pages/{AssetImpairments,BudgetVariance,CostEngine,DepreciationPolicies,DepreciationSchedules,IntercompanyClearances,ScenarioPlanning}/Index.vue`) — reachable only by direct URL/route name for now (`/accounting/asset-impairments` etc.), matching the existing `consolidation-hierarchies` precedent: this repo has no per-page Accounting sub-navigation yet, and designing one is a separate UX chantier, not a side effect of a routing pass.
- **36 of the 42 `acc_` tables created by the catch-all scaffold migration (`database/migrations/2026_05_29_000003_create_all_missing_module_tables.php`) have no Eloquent model** (stub schema `id/tenant_id/data/timestamps`, never queried by any code) — confirmed by a full audit, correcting the earlier estimate above of "41 of 94". Left as-is: nothing references them, so they're inert scaffold debt rather than a functional gap, and building 36 speculative models with no driving requirement is out of scope.
- **Chantier 8.2 (CRM) rewiring/cleanup**: a full API/Controller/View/Data audit found and closed a real RBAC hole — `CampaignController`/`WorkflowBuilderController`/`RevenueIntelligenceController` lived directly under `Http/Controllers/` (not `Http/Controllers/Api/`), so none `extends App\Http\Controllers\Controller`; `CampaignController`/`WorkflowBuilderController` never called `authorize()` despite `CampaignPolicy`/`WorkflowPolicy` already existing and being correctly written — any authenticated user could create/launch/pause campaigns or workflows regardless of role. Fixed by moving both into `Http/Controllers/Api/`, adding `extends Controller` + the missing `authorize()` calls, and — since the policies checked permission strings (`crm.campaigns.*`/`crm.workflows.*`, non-standard `.edit` verb) that were never seeded by the real `RolesAndPermissionsSeeder` chain — adding a `CRM_EXTRA_PERMISSIONS` block there (mirroring `ACCOUNTING_EXTRA_PERMISSIONS`) so the fix is fail-closed-then-correctly-open rather than fail-closed-for-everyone. A duplicate `crm/opportunity-scores` route registration was removed (kept the one route, dropped the redundant alias). 4 real, already-built Vue pages with no route (`Quotes/Index+Show`, `Territories/Index`, `Forecast/Index`, `Opportunities/Scoring`) got thin Web controllers + routes, matching the Accounting `consolidation-hierarchies` URL-only-discoverability precedent. 5 dead duplicate Vue pages under `Modules/CRM/resources/js/Pages/` (masked by larger, real root-level copies — same pattern as the Chantier 6 cleanup) were deleted. A deeper read (beyond what the initial audit flagged) found `TerritoryManagementController`/`TerritoryManagementService`/`TerritoryQuota`/`TerritoryAlert` were an entire broken, fully redundant parallel subsystem: 6 of the controller's methods called service methods that didn't exist at all, 2 more wrote field names that didn't match the real models' `$fillable`, and one called a nonexistent `Territory::alerts()` relation — while the *real*, tested, already-routed `TerritoryController`+`TerritoryService`+`TerritoryForecastService` already cover every concept it was attempting (rebalance, forecast, at-risk, quotas) via `Territory`'s own native `ytdRevenue()`/`quotaAttainment()`/`quotaForecast()` methods. Deleted the entire broken subtree (controller, service, both models, both factories) rather than repairing it, and added the one genuinely new, non-duplicate concept — a `coverage()` gap-analysis endpoint — as a plain method on the real `TerritoryService`/`TerritoryController` instead (no `authorize()` call, matching the rest of that controller: this module gates by route-level `role:` middleware, not per-method policies — no `TerritoryPolicy` exists).

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
