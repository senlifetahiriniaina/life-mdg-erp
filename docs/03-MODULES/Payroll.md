# Payroll

## Rôle

Le module Payroll est la source unique de vérité pour la paie dans life-mdg-erp (voir `CLAUDE.md`) : cycles de paie (« runs »), bulletins de salaire, composants salariaux configurables et barèmes fiscaux/sociaux par pays africain (Africa First). Il fournit aussi un service d'intégration comptable OHADA et un jeu de données statutaires réel (impôt progressif + régimes de cotisations sociales pour 8 pays) désormais réellement branché sur le calcul de paie.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `PayrollRun` | `payroll_runs` | Cycle de paie pour un tenant/période (`draft → processing → validated → paid`), totaux agrégés (brut, déductions, net) |
| `Payslip` | `payslips` | Bulletin de salaire individuel rattaché à un `PayrollRun`, `salary_components` stocké en JSON |
| `SalaryComponent` | `salary_components` | Composant de rémunération configurable (fixe ou pourcentage), taxable/statutaire, portée (`applies_to`) |

`PayrollRun` expose la relation `payslips()` (hasMany). Ce trio (`PayrollRun`/`Payslip`/`SalaryComponent`) est le schéma migré et **désormais le seul chemin réellement exposé par l'API** — le bug historique documenté ici jusqu'à cette refonte (`PayrollController`/`PayrollIntegrationService` important `Modules\HR\Models\PayrollRecord`/`PayrollPeriod`, deux classes qui n'existaient nulle part dans le dépôt) a été corrigé : `PayrollIntegrationService` a été réécrit pour opérer directement sur `PayrollRun`/`Payslip` et sur la vraie source de salaire (voir Services ci-dessous).

## Endpoints principaux

Préfixe `/api/v1/payroll/` (`Modules/Payroll/routes/api.php`) :

| Middleware | Méthode | Route | Description |
|---|---|---|---|
| `auth:sanctum, session.security, tenancy.user, module:Payroll, role:hr-manager,payroll-officer,accountant,finance-manager,manager,admin` | GET | `payslips` | Liste des bulletins pour une période (`?period=YYYY-MM`) |
| — | POST | `generate` | Génère les bulletins pour tous les employés actifs d'une période |
| — | POST | `payslips/approve-batch` | Approuve en lot les bulletins brouillon d'une période |
| — | POST | `process-payment` | Traite le paiement des bulletins approuvés + intégration comptable |
| — | GET | `statistics` | Statistiques de paie pour une période |
| — | GET | `taxes/by-country` | Répartition des taxes/cotisations par pays |
| `auth:sanctum, session.security, tenancy.user` (tout utilisateur) | GET | `me/payslips` | Bulletins de l'utilisateur connecté (self-service) |
| — | GET | `payslips/{payslip}` | Détail d'un bulletin — `authorize('view', $payslip)` via `PayrollPolicy` (tout employé peut voir son propre bulletin, pas seulement le staff paie) |
| `auth:sanctum, session.security, tenancy.user` | POST | `ai/assist` | Guidance IA contextuelle (`PayrollAiAssistController`) |

Correction Chantier 10 : ce groupe (ainsi que le groupe self-service et le groupe IA) n'avait pas de middleware `module:Payroll` dédié, contrairement à tous les modules frères (HR/Timesheets/Projects/Helpdesk) — ajouté pour cohérence (permet notamment de désactiver le module par tenant via `ModuleManager`, ce qu'un simple `role:` ne permettait pas).

Route web (`Modules/Payroll/routes/web.php`, ajoutée cette session — le module n'en avait aucune) : `GET /payroll` → `Inertia::render('Payroll/Dashboard/Index')`.

## Contrôleurs

- **`Api\PayrollController`** — bulletins, génération, approbation par lot, paiement, statistiques, répartition fiscale par pays, self-service (`myPayslips`/`show`). Chaque méthode staff vérifie `$request->user()->can('payroll.payslip.<verb>')` (`abort_unless`) ; `show()` passe par `$this->authorize('view', $payslip)`.
- **`Api\PayrollAiAssistController`** — guidance IA contextuelle, passe désormais `module: 'Payroll'` (et non `'HR'`) à `AiContextualAssistantService::getGuidance()`, atteignant enfin la vraie table de repli statique du module Payroll (`generate_payslips`/`approve_payroll`/`export_payroll`/`view_dashboard`).

Aucun contrôleur Web dédié : la route `/payroll` rend directement `Payroll/Dashboard/Index` via une closure Inertia.

## Vues (Vue/Inertia)

- **`Dashboard/Index.vue`** (`Modules/Payroll/resources/js/Pages/Dashboard/Index.vue`) — page réelle et auto-alimentée (liste des bulletins, statistiques, répartition fiscale, approbation par lot, traitement du paiement), appelant l'API Payroll réelle. Elle existait déjà mais n'avait **aucune route** pour l'atteindre avant l'ajout de `routes/web.php` cette session.
- Pas de page dédiée « mes bulletins » côté self-service pour l'instant — `GET me/payslips`/`GET payslips/{payslip}` sont consommés côté HR (`resources/js/Pages/HR/Payroll/Index.vue`, `HR/Portal.vue`) plutôt que par une vue propre au module Payroll.

## Services

- **`PayrollService`** — opère sur le schéma actuel (`PayrollRun`/`Payslip`/`SalaryComponent`) : `createRun()`, `processRun()`, `validateRun()`, `markAsPaid()`, `getActiveComponents()`, `computeSalary()`. Toujours **appelé uniquement par `Modules\Workflow\Services\Actions\Phase52ActionHandler::generatePayrollRun()`** (le moteur d'automatisation), pas par un contrôleur HTTP direct de ce module.
- **`PayrollIntegrationService`** — service branché sur `PayrollController`, réécrit cette session pour supprimer sa dépendance envers les classes `Modules\HR\Models\PayrollRecord`/`PayrollPeriod` (absentes du dépôt) : génère les bulletins (`generatePayslips`/`generatePayslip`) directement sur `PayrollRun`/`Payslip`, calcule les composants de salaire, les heures supplémentaires, les allocations et les déductions, et passe les écritures comptables OHADA (`postPayslipsToAccounting()` → `Modules\Accounting\Models\JournalEntry`, imputation Cl.6161/Cl.4210).
  - **Source de salaire réelle** : `base_salary`/`currency` viennent désormais de `Modules\HR\Models\EmployeeCompensation` via un helper `getCurrentCompensation(Employee, Carbon $asOf)` (résolu à la date de début de la période de paie, pas `now()`) — corrige le bug historique où `base_salary`/`monthly_salary` étaient lus directement sur `Employee`, des colonnes qui existent en base mais jamais dans son `$fillable`, ce qui faisait silencieusement calculer un salaire quasi nul sur tout bulletin généré via l'UI/API réelle (seuls les tests passaient, en contournant `$fillable` via `Model::unguarded()`).
  - **Tenant** : `tenant_id` vient de l'appelant (ou, à défaut, de `Employee::user->company_id`) plutôt que du champ fantôme `hr_employees.tenant_id` (jamais alimenté). Correction Chantier 10 : `PayrollController`/`PayrollIntegrationService` lisaient auparavant `Employee::user->tenant_id` — une confusion documentée dans une session précédente comme « la vraie colonne de délimitation multi-tenant », mais `users.tenant_id` est en réalité le même champ fantôme (réel, migré, jamais dans `User::$fillable`, jamais alimenté par le flux d'inscription réel) déjà corrigé ailleurs dans l'app (Reporting, Strategy, AI, Sales, Achats, Integration, Workflow) — chaque tenant voyait donc silencieusement les bulletins de tous les autres tenants s'agréger dans le même panier `tenant_id=0`. Corrigé vers `users.company_id`, la vraie colonne de délimitation. Verrouillé par `Modules/Payroll/tests/Feature/Chantier10PayrollTenantIsolationTest.php` (isolation testée sur le vrai chemin HTTP, deux entreprises distinctes).
  - **Heures supplémentaires** : dérivées des saisies réelles et approuvées de `Modules\Timesheets\Models\TimesheetEntry` (au-delà de 160h/mois), plutôt que de la table `hr_timesheets` (jamais alimentée) lue auparavant.
  - **Allocations granulaires** (`housing_allowance`, `transport_allowance`, `family_allowance`, `monthly_bonus`) restent lues sur des champs `Employee` qui n'ont pas de source de données réelle par employé dans cette version — laissées à `?? 0` (repli documenté, pas un bug corrigé silencieusement).
  - **Remboursement de prêt** (`calculateLoanRepayment()`) reste à 0 — aucune fonctionnalité de gestion de prêts salariés n'existe nulle part dans le dépôt.
- **`Data\StatutorySchemes`** (`app/Data/`) — table de données statiques des régimes de cotisations sociales et de l'impôt sur le revenu pour 8 pays africains (SN, CI, CM, MG, BJ, TG, BF, ML) : tranches d'IR progressives, abattements, surtaxes, minimums statutaires, régimes de cotisations sociales avec taux et plafonds, catégorisés (`pension`/`social_security`/`health`/`combined`). **Désormais réellement câblée** : `calculateIncomeTax()` exécute un vrai calcul par tranches progressives pour ces 8 pays (`calculateProgressiveIncomeTax()`), avec repli sur l'ancienne approximation à taux forfaitaire pour les pays hors de ce jeu de données (dont NG) ; `calculateSocialSecurity()`/`calculatePensionContribution()` sommes les régimes réels par catégorie sans double comptage.
- **`PayrollPolicy`** — corrigée : la vérification « bulletin personnel » comparait `$model->employee_id` (un `hr_employees.id`) directement à `$user->id` (un `users.id`), empêchant tout employé de passer le contrôle sur son propre bulletin ; compare désormais `$user->employee->id`. Le rôle inexistant `payroll-manager` a été remplacé par le vrai rôle `payroll-officer`. Désormais enregistrée auprès du Gate via `PayrollServiceProvider::registerPolicies()` (`Gate::policy(Payslip::class, PayrollPolicy::class)`) — les policies namespacées sous `Modules\*` ne s'auto-découvrent pas.

## Permissions RBAC

Préfixe `payroll.*` dans `RolesAndPermissionsSeeder::MODULES` : ressources `payslip`, `run`, `tax-config`, actions standard `view-any|view|create|update|delete`. Le rôle `hr-manager` reçoit toutes les permissions `payroll.*` ; le rôle `payroll-officer` aussi.

`PayrollController` vérifie en réalité des chaînes ad hoc (`payroll.payslip.view`, `.generate`, `.approve`) plutôt que le schéma générique `payroll.<resource>.<action>` du seeder — ces chaînes sont bien réelles/seedées (`payslip` est une ressource déclarée), donc les vérifications fonctionnent pour les rôles porteurs de `payroll.*`. Correction RBAC de cette session : le groupe de routes n'incluait pas `payroll-officer` — le rôle nommé pour ce module était bloqué à la porte d'entrée avant même la vérification de permission — et `statistics()`/`taxesByCountry()` n'avaient aucun `authorize()`/`abort_unless()` du tout, permettant à `accountant`/`finance-manager` (qui n'ont pas `payroll.*`) de lire ces agrégats en ne passant que la grille de rôles au niveau route ; les deux ont été corrigés (rôle ajouté au groupe, `abort_unless` ajouté aux deux méthodes).

## Dépendances avec d'autres modules

- **HR** : `PayrollIntegrationService` importe désormais `Modules\HR\Models\Employee` et `Modules\HR\Models\EmployeeCompensation` (la vraie source de salaire, construite par `Modules\HR\Services\CompensationService`) — la dépendance envers les classes inexistantes `PayrollRecord`/`PayrollPeriod` a été supprimée. Inversement, HR consomme `Modules\Payroll\Models\Payslip` en lecture pour son self-service (voir `docs/03-MODULES/HR.md`).
- **Timesheets** : `PayrollIntegrationService::calculateOvertime()` lit `Modules\Timesheets\Models\TimesheetEntry` (heures approuvées) pour dériver les heures supplémentaires.
- **Accounting** : `PayrollIntegrationService::postPayslipsToAccounting()` crée des `Modules\Accounting\Models\JournalEntry` (comptabilisation OHADA du salaire brut et du net à payer).
- **AI** : `PayrollAiAssistController` utilise `AiContextualAssistantService`.
- **Workflow** : `Phase52ActionHandler` (module Workflow) déclenche `PayrollService::createRun()` via l'automatisation cross-module.

## Particularités du périmètre life-mdg-erp

- **Les deux implémentations parallèles documentées avant cette refonte n'existent plus en tant que bug** : le schéma migré (`PayrollRun`/`Payslip`/`SalaryComponent`, piloté par `PayrollService`) reste utilisé uniquement par le moteur d'automatisation Workflow, mais le chemin exposé par l'API REST (`PayrollController` → `PayrollIntegrationService`) a été réécrit pour opérer sur ce même schéma au lieu de référencer des classes `Modules\HR\Models\PayrollRecord`/`PayrollPeriod` inexistantes. Les deux chemins convergent désormais sur `PayrollRun`/`Payslip` ; `PayrollService` reste néanmoins un second point d'entrée non exposé par HTTP.
- **Répartition fine des primes/allocations non modélisée** : seule une valeur agrégée (`base_salary`, `bonus_amount`, `benefits_annual_value`) existe par employé sur `EmployeeCompensation` — les sous-catégories (logement, transport, famille, prime mensuelle) restent à 0 par défaut, faute de source de données dédiée, plutôt que d'inventer une répartition arbitraire.
- **Gestion de prêts salariés absente** : `calculateLoanRepayment()` retourne toujours 0, faute de tout modèle/contrôleur/UI de prêt employé dans ce dépôt — documenté comme gap plutôt que masqué.
