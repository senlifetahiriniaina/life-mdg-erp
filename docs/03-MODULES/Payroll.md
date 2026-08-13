# Payroll

## Rôle

Le module Payroll est la source unique de vérité pour la paie dans life-mdg-erp (voir `CLAUDE.md`) : cycles de paie (« runs »), bulletins de salaire, composants salariaux configurables et barèmes fiscaux/sociaux par pays africain (Africa First). Il fournit aussi un service d'intégration comptable OHADA et un jeu de données statutaires (IPRES, CSS, IR…) pour plusieurs pays.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `PayrollRun` | `payroll_runs` | Cycle de paie pour un tenant/période (`draft → processing → validated → paid`), totaux agrégés (brut, déductions, net) |
| `Payslip` | `payslips` | Bulletin de salaire individuel rattaché à un `PayrollRun`, `salary_components` stocké en JSON |
| `SalaryComponent` | `salary_components` | Composant de rémunération configurable (fixe ou pourcentage), taxable/statutaire, portée (`applies_to`) |

`PayrollRun` expose la relation `payslips()` (hasMany). Ce trio (`PayrollRun`/`Payslip`/`SalaryComponent`) est le schéma **actuel et migré** du module — à ne pas confondre avec `Modules\HR\Models\PayrollRecord`/`PayrollPeriod`, référencés ailleurs dans le code mais absents du dépôt (voir Particularités).

## Endpoints principaux

Toutes sous `auth:sanctum` + `role:hr-manager,accountant,finance-manager,manager,admin`, préfixe `/api/v1/payroll/` :

| Méthode | Route | Description |
|---|---|---|
| GET | `payslips` | Liste des bulletins pour une période (`?period=YYYY-MM`) |
| POST | `generate` | Génère les bulletins pour tous les employés actifs d'une période |
| POST | `payslips/approve-batch` | Approuve en lot les bulletins brouillon d'une période |
| POST | `process-payment` | Traite le paiement des bulletins approuvés + intégration comptable |
| GET | `statistics` | Statistiques de paie pour une période |
| GET | `taxes/by-country` | Répartition des taxes/cotisations par pays |
| POST | `ai/assist` | Guidance IA contextuelle (`PayrollAiAssistController`, `auth:sanctum` seul) |

## Services

- **`PayrollService`** — opère sur le schéma actuel (`PayrollRun`/`Payslip`/`SalaryComponent`) : `createRun()`, `processRun()` (agrège les totaux depuis les bulletins), `validateRun()`, `markAsPaid()`, `getActiveComponents()`, `computeSalary()` (calcule brut/déductions/net à partir d'un salaire de base et des composants actifs du tenant). **N'est appelé par aucun contrôleur HTTP de ce module** — son seul consommateur trouvé dans le code est `Modules\Workflow\Services\Actions\Phase52ActionHandler::generatePayrollRun()`, qui l'invoque via `app(PayrollService::class)` pour déclencher un cycle de paie depuis le moteur d'automatisation.
- **`PayrollIntegrationService`** — service historique branché sur le `PayrollController` : génération de bulletins (`generatePayslips`/`generatePayslip`), calcul de composants (allocations, heures sup., primes), calcul des déductions (IR, sécurité sociale, pension) avec des barèmes codés en dur par pays (SN/CI/CM/NG + repli OHADA générique), et **passage des écritures comptables OHADA** (`postPayslipsToAccounting()` → `Modules\Accounting\Models\JournalEntry`, imputation Cl.6161/Cl.4210). Ce service dépend de `Modules\HR\Models\PayrollRecord`/`PayrollPeriod`, deux classes absentes du dépôt (voir Particularités) — il ne peut donc pas fonctionner tel quel.
- **`StatutorySchemes`** (`app/Data/`) — table de données statique des régimes de cotisations sociales et de l'impôt sur le revenu par pays (Sénégal détaillé : IPRES, CSS, IR/TRIMF avec taux, plafonds, périodicité, comptes OHADA). N'est référencée par aucun service ou contrôleur du module — données prêtes mais non câblées.

## Permissions RBAC

Préfixe `payroll.*` dans `RolesAndPermissionsSeeder::MODULES` : ressources `payslip`, `run`, `tax-config`, actions standard `view-any|view|create|update|delete`. Le rôle `hr-manager` reçoit toutes les permissions `payroll.*` (avec `hr.*` et `timesheets.*`) ; le rôle `payroll-officer` (décrit en commentaire comme « full Payroll + HR compensation/leave ») reçoit aussi toutes les permissions `payroll.*`.

Cependant, le contrôle d'accès réellement appliqué diverge de ce schéma à deux endroits :
- **`PayrollController`** n'utilise pas les permissions Spatie `payroll.*.{action}` du seeder mais des chaînes ad hoc non seedées : `payroll.payslips.view`, `payroll.payslips.generate`, `payroll.payslips.approve` (notez le pluriel `payslips` et l'action `generate`/`approve`, absents de la liste `ACTIONS` et de la ressource `payslip` au singulier définie dans le seeder) — ces `abort_unless($user->can(...))` échoueront donc toujours sauf pour un rôle qui bypass les Gates.
- **`PayrollPolicy`** (utilisée ailleurs, ex. Policy Eloquent standard) n'interroge pas non plus les permissions Spatie mais fait du contrôle par rôle direct (`hasAnyRole(['hr-manager', 'payroll-manager', 'admin', 'super-admin'])`). Le rôle `payroll-manager` qu'elle cite **n'existe pas** dans le seeder (seul `payroll-officer` y est créé) — un utilisateur avec seulement le rôle `payroll-officer` ne passera donc pas ces vérifications `hasAnyRole`, malgré son intitulé de commentaire dans le seeder.

## Dépendances avec d'autres modules

- **HR** : `PayrollIntegrationService` et `PayrollController` importent `Modules\HR\Models\Employee` (existe) et `Modules\HR\Models\PayrollRecord`/`PayrollPeriod` (n'existent pas — voir Particularités) ; à l'inverse, HR consomme `Modules\Payroll\Models\Payslip` en lecture pour son self-service (voir `docs/03-MODULES/HR.md`).
- **Accounting** : `PayrollIntegrationService::postPayslipsToAccounting()` crée des `Modules\Accounting\Models\JournalEntry` (comptabilisation OHADA du salaire brut et du net à payer).
- **AI** : `PayrollAiAssistController` utilise `AiContextualAssistantService`.
- **Workflow** : `Phase52ActionHandler` (module Workflow) déclenche `PayrollService::createRun()` via l'automatisation cross-module.

## Particularités du périmètre life-mdg-erp

Le module a en réalité **deux implémentations parallèles et incompatibles** cohabitant dans le code actuel :

1. Le schéma **migré et fonctionnel** — `PayrollRun`/`Payslip`/`SalaryComponent` (avec migrations dans `Modules/Payroll/database/migrations/`) piloté par `PayrollService`, mais qui n'est branché à aucune route HTTP du module (seul le moteur d'automatisation `Workflow` l'utilise).
2. Le chemin **effectivement exposé par l'API REST** (`PayrollController` → `PayrollIntegrationService`), qui repose sur `Modules\HR\Models\PayrollRecord`/`PayrollPeriod` — deux classes qui n'existent nulle part dans ce dépôt trimmé (confirmé par recherche exhaustive sur `Modules/`, `app/` et `tests/`). Cela correspond très probablement à un reliquat de l'ancienne duplication de paie qui vivait dans `Modules/HR` avant l'extraction (voir `CLAUDE.md`, section HR) : les modèles ont été supprimés du module HR, mais ce service/contrôleur du module Payroll qui les référençait encore n'a pas été mis à jour vers le nouveau schéma `PayrollRun`/`Payslip`. En l'état, toutes les routes de `PayrollController` (`payslips`, `generate`, `payslips/approve-batch`, `process-payment`, `taxes/by-country`) échoueront à l'exécution (classe introuvable), de même que `PayrollIntegrationServiceTest`. C'est un gap concret non mentionné dans les « Known gaps » de `CLAUDE.md` et qui mériterait d'y être ajouté : soit réécrire `PayrollController`/`PayrollIntegrationService` pour utiliser `PayrollRun`/`Payslip`/`SalaryComponent`, soit les retirer au profit du chemin `PayrollService` déjà fonctionnel.
