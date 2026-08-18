# Timesheets

## Rôle

Le module Timesheets gère la saisie, la soumission et l'approbation des heures travaillées par les employés, ainsi que leur allocation à des projets/tâches et leur facturation. Il fait partie de la ligne **RH basique** aux côtés de HR, Payroll et Projects, et dépend structurellement de **Projects** : toute saisie de temps peut être rattachée à un `Project`/`Task` du module Projects (relation `belongsTo`).

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `TimesheetEntry` | `timesheet_entries` | Saisie de temps journalière (heures, description, statut draft/submitted/approved/rejected), rattachée à `HR\Employee` et optionnellement à `Projects\Project`/`Projects\Task` — c'est le **seul** modèle de saisie de temps du module |
| `TimesheetPeriod` | `ts_timesheet_periods` | Période hebdomadaire/bi-hebdomadaire de soumission groupée (« feuille de temps »/« sheet »), statut par défaut/soumissible `draft` (aligné cette session avec `TimesheetEntry` — voir Particularités), calcul des heures supplémentaires (règle Afrique First : >40h/semaine à 125%) |
| `TimeAllocation` | `time_allocations` | Répartition des heures d'une `TimesheetEntry` entre projet/tâche/centre de coût, avec taux horaire et coût calculé |
| `TimeTrackingProject` | `time_tracking_projects` | Projet de suivi de temps « léger » propre à Timesheets (budget d'heures, département), distinct des `Project` du module Projects |
| `ProjectBilling` (Timesheets) | `ts_project_billing` | Facturation de projet par jalon/pourcentage/régie/forfait avec compte OHADA 7061 |

## Endpoints principaux

Tous sous `auth:sanctum` + `module:Timesheets` + `role:employee,manager,admin`, avec `throttle:simple_get` en lecture et `throttle:create_post` en écriture (`Modules/Timesheets/routes/api.php`, monté sous `api/v1/timesheets`) :

| Méthode | Route | Description |
|---|---|---|
| GET | `entries`, `entries/{entry}`, `entries/pending/approvals`, `entries/by-employee` | Liste/détail des saisies, file d'attente d'approbation, filtrage par employé |
| POST/PUT/PATCH/DELETE | `entries`, `entries/{entry}` | CRUD saisie de temps |
| POST | `entries/{entry}/submit`, `entries/{entry}/approve`, `entries/{entry}/reject` | Cycle de validation d'une saisie |
| POST | `entries/{entry}/allocate` | Répartir les heures d'une saisie sur plusieurs projets/tâches |
| GET/POST/PUT/PATCH/DELETE | `allocations`, `allocations/{allocation}`, `allocations/project/{project}` | CRUD allocations de temps |
| GET/POST/PUT/PATCH/DELETE | `projects`, `projects/{project}` | CRUD des `TimeTrackingProject` |
| GET | `projects/{project}/timesheets`, `projects/{project}/metrics` | Feuilles de temps et métriques d'un projet de suivi |
| GET | `metrics/summary`, `metrics/employee/{user}/month/{month}`, `metrics/project/{project}` | Rapports d'utilisation |
| GET | `sheets/my-sheets`, `sheets` | Mes feuilles de temps / liste des feuilles (`TimesheetAdvancedController`, adossé à `TimesheetPeriod`/`TimesheetEntry`) |
| GET | `reports/project-billing`, `reports/employee-hours`, `reports/utilization` | Rapports (`TimesheetAdvancedController`) |
| POST/PUT | `sheets`, `sheets/{id}`, `sheets/{id}/submit`, `sheets/{id}/approve`, `sheets/{id}/reject` | Cycle de vie d'une feuille de temps groupée |
| GET | `timer/current` | Minuteur actif de l'utilisateur courant |
| POST/DELETE | `timer/start\|stop\|discard` | Démarrer/arrêter/annuler un minuteur (crée une `TimesheetEntry` à l'arrêt) |
| POST | `ai/assist` | Guidance IA contextuelle (AI Assisted First) |

Routes web (`Modules/Timesheets/routes/web.php`, préfixe `timesheets/`) : désormais protégées par `auth` + `module:Timesheets` (voir Particularités — c'était entièrement ouvert avant cette session) : `dashboard`, `entries`, `entries/create`, `entries/{entry}/edit`, `sheets`, `sheets/create`, `sheets/{sheet}`, `sheets/{sheet}/edit`, `my-sheets`, `reports/hours`, `reports/billing`, `reports/utilization`.

## Contrôleurs

- **`Api\TimesheetEntryController`** — CRUD complet des `TimesheetEntry`, cycle d'approbation, allocation multi-projets, filtrage par employé.
- **`Api\TimeAllocationController`** — CRUD des `TimeAllocation`.
- **`Api\TrackingProjectController`** — CRUD des `TimeTrackingProject` et leurs métriques.
- **`Api\MetricsController`** — rapports d'utilisation employé/projet.
- **`Api\TimerController`** — minuteur temps réel (start/stop/discard).
- **`Api\TimesheetAdvancedController`** — feuilles de temps groupées (`sheets`), soumission/approbation/rejet, 3 rapports (`project-billing`, `employee-hours`, `utilization`). Réécrit cette session sur les vrais modèles `TimesheetEntry`/`TimesheetPeriod` (voir Particularités) ; ses anciennes méthodes CRUD brutes (`index`/`store`/`update`/`destroy`, 100% redondantes avec `TimesheetEntryController`) ont été supprimées.
- **`Api\TimesheetsAiAssistController`** — guidance IA contextuelle.
- **`Web\SheetWebController`** (nouveau cette session) — props réelles (`sheet`/`entries`) pour `Sheets/Show.vue`/`Sheets/Form.vue`, remplaçant des closures vides qui ne fournissaient aucune prop malgré des composants qui les exigeaient.

L'ancien `Api\TimeEntryController`/modèle `TimeEntry` (`timesheets_entries`) — un doublon cassé et jamais routé de `TimesheetEntryController`/`TimesheetEntry` — a été **supprimé** cette session, ainsi que son factory/resource.

## Vues (Vue/Inertia)

Toutes sous `Modules/Timesheets/resources/js/Pages/` (aucun doublon racine) :

- **`Dashboard.vue`** — tableau de bord principal.
- **`TimeEntries/{Index,Form}.vue`**, **`Entries/Index.vue`**, **`Projects/Index.vue`** — saisie et suivi.
- **`Sheets/{Index,Show,Form,MySheets}.vue`** — feuilles de temps groupées ; `Show.vue`/`Form.vue` reçoivent désormais de vraies props via `SheetWebController` (avant cette session, les routes `sheets/{sheet}` et `sheets/{sheet}/edit` étaient des closures sans aucune prop alors que ces pages en déclarent des obligatoires, et `sheets/create` (lié depuis deux pages) n'existait pas du tout).
- **`Reports/{ProjectBilling,EmployeeHours,Utilization}.vue`** — rapports, alimentés par `TimesheetAdvancedController::*Report()`.

Correction de sécurité notable : **tout ce groupe de pages était accessible sans authentification** avant cette session (`routes/web.php` n'avait aucun middleware `auth`/`module:Timesheets`), y compris les données personnelles de temps de travail — corrigé en ajoutant `['auth', 'module:Timesheets']` au groupe de routes.

## Services

- **`TimesheetService`** — cœur métier des `TimesheetEntry` : création, mise à jour (bloquée hors statut `draft`), soumission, approbation/rejet, allocation multi-projets avec vérification que la somme des heures allouées correspond aux heures saisies, calcul de métriques employé/projet.
- **`TimerService`** — minuteur live (start/stop/discard) stocké en cache (clé `timesheet_timer:{userId}`, TTL 24h) ; à l'arrêt, délègue à `TimesheetService::createEntry()` pour persister une `TimesheetEntry`.
- **`ProjectBillingService`** — facturation de projet par jalon, pourcentage d'avancement, régie (temps & matériel), ou montant forfaitaire ; calcule la TVA à 18% (UEMOA) et le compte OHADA 7061 ; génère les références `BILL-YYYY-NNNN`/`INV-YYYY-NNNN`. Persiste dans `ts_project_billing` (table réellement migrée cette session — voir Particularités). `billTimeAndMaterial()` interroge désormais la vraie table `timesheet_entries` (elle lisait auparavant une quatrième table fictive, `ts_timesheets`, dont ni le nom ni les colonnes ne correspondaient à aucun modèle réel).

## Permissions RBAC

Permissions dédiées sous le préfixe `timesheets.*` dans `database/seeders/RolesAndPermissionsSeeder.php` : ressources `timesheet` et `entry`, actions `view-any|view|create|update|delete` (l'approbation passe par les rôles `admin`/`manager`/`hr-manager` au niveau de `TimesheetEntryPolicy`, pas par une permission Spatie dédiée). Les rôles `hr-manager` et `project-manager` reçoivent automatiquement toutes les permissions `timesheets.*`.

Correction de sécurité de cette session (bug d'espace d'identifiants, même motif que `LeaveRequestPolicy`/`PayrollPolicy` ailleurs dans l'app) : `TimesheetEntryPolicy::view/update/delete/submit` comparaient `$user->id` (un `users.id`) directement à `$entry->employee_id` (un `hr_employees.id`) — un employé ne pouvait jamais passer ces vérifications sur ses propres saisies. Corrigé en comparant `$user->employee?->id`. Le même bug affectait le filtrage non-manager de `TimesheetEntryController::index()` et les deux `FormRequest` (`employee_id` validé contre `exists:users,id` au lieu de `hr_employees,id`, et `task_id` contre `exists:tasks,id` — une table qui n'existe pas dans ce dépôt, la vraie étant `prj_tasks`) — tous corrigés.

## Dépendances avec d'autres modules

- **Dépend de `Modules\HR`** : `TimesheetEntry` référence `Modules\HR\Models\Employee`.
- **Dépend fortement de `Modules\Projects`** : `TimesheetEntry`, `TimeAllocation` et `ProjectBilling` référencent `Modules\Projects\Models\Project`/`Task` — chaque saisie de temps peut être rattachée à un projet réel du module Projects. Aucun import réciproque n'existe côté Projects : la dépendance est à sens unique.
- **Utilisé par `Modules\Projects`** : le service `TimeTrackingDeduplicationService` de Projects documente Timesheets comme « source of truth » pour le suivi de temps.
- **Utilisé par `Modules\Payroll`** : `PayrollIntegrationService::calculateOvertime()` lit `TimesheetEntry` (heures approuvées au-delà de 160h/mois) pour dériver les heures supplémentaires d'un bulletin.
- Utilise `Modules\AuditLog\Traits\HasAuditLog` (sur `TimesheetPeriod` et `ProjectBilling`) pour la traçabilité des modifications.

## Particularités du périmètre life-mdg-erp

- **Trois piles de suivi de temps qui se chevauchaient ont été réduites à une seule** : avant cette session, `TimesheetEntry` (`timesheet_entries`, la table réellement gérée par `TimesheetService`) coexistait avec un second modèle `TimeEntry` (`timesheets_entries`) entièrement mort et non routé, et avec `Timesheet`/`timesheets_sheets` dont le `$fillable` ne correspondait jamais à sa propre table stub. Le doublon `TimeEntry` a été supprimé ; `TimesheetAdvancedController` (qui ciblait le modèle `Timesheet` cassé) a été réécrit sur `TimesheetPeriod`/`TimesheetEntry`, les deux modèles réels et désormais correctement migrés.
- **`TimesheetPeriod` (`ts_timesheet_periods`) et `ProjectBilling` (`ts_project_billing`) n'avaient aucune migration créant leur table** avant cette session — corrigé par une migration additive dédiée. Avant ce correctif, la soumission de période échouait franchement (« table not found ») tandis que la facturation dégradait gracieusement en mode « démo » (repli `try/catch` codé en dur dans `ProjectBillingService`) ; les deux fonctionnent désormais sur de vraies tables.
- **`TimesheetAdvancedController` n'était routé nulle part** avant cette session (seul son test dédié l'exerçait). Il est désormais monté sous `api/v1/timesheets/sheets*` et `reports/*`, distinct de la CRUD brute déjà couverte par `TimesheetEntryController`.
- **`routes/web.php` du module était accessible sans authentification** — voir la section Vues ci-dessus.
