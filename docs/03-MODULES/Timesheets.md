# Timesheets

## Rôle

Le module Timesheets gère la saisie, la soumission et l'approbation des heures travaillées par les employés, ainsi que leur allocation à des projets/tâches et leur facturation. Il fait partie de la ligne **RH basique** aux côtés de HR, Payroll et Projects, et dépend structurellement de **Projects** : toute saisie de temps peut être rattachée à un `Project`/`Task` du module Projects (relation `belongsTo`), il n'existe pas de notion de projet autonome dans Timesheets en dehors du suivi de temps « brut » (`TimeTrackingProject`).

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `TimesheetEntry` | `timesheet_entries` | Saisie de temps journalière (heures, description, statut draft/submitted/approved/rejected), rattachée à `HR\Employee` et optionnellement à `Projects\Project`/`Projects\Task` |
| `TimeEntry` | `timesheets_entries` | Second modèle de saisie de temps (avec taux horaire, facturabilité) — coexiste avec `TimesheetEntry`, voir Particularités |
| `Timesheet` | `timesheets_sheets` | Feuille de temps groupée par période (`period_start`/`period_end`) pour un employé, avec heures totales/facturables et cycle de soumission/approbation |
| `TimeAllocation` | `time_allocations` | Répartition des heures d'une `TimesheetEntry` entre projet/tâche/centre de coût, avec taux horaire et coût calculé |
| `TimeTrackingProject` | `time_tracking_projects` | Projet de suivi de temps « léger » propre à Timesheets (budget d'heures, département), distinct des `Project` du module Projects |
| `TimesheetPeriod` | `ts_timesheet_periods` | Période hebdomadaire/bi-hebdomadaire de soumission groupée, avec calcul des heures supplémentaires (règle Afrique First : >40h/semaine à 125%) — **aucune migration ne crée cette table dans le dépôt** (voir Particularités) |
| `ProjectBilling` (Timesheets) | `ts_project_billing` | Facturation de projet par jalon/pourcentage/régie/forfait avec compte OHADA 7061 — **aucune migration ne crée cette table** (voir Particularités) |

## Endpoints principaux

Tous sous `auth:sanctum` + `module:Timesheets` + `role:employee,manager,admin`, avec `throttle:simple_get` en lecture et `throttle:create_post` en écriture (`Modules/Timesheets/routes/api.php`, monté sous `api/v1/timesheets`) :

| Méthode | Route | Description |
|---|---|---|
| GET | `entries`, `entries/{entry}`, `entries/pending/approvals` | Liste/détail des saisies, file d'attente d'approbation |
| POST/PUT/PATCH/DELETE | `entries`, `entries/{entry}` | CRUD saisie de temps |
| POST | `entries/{entry}/submit`, `entries/{entry}/approve`, `entries/{entry}/reject` | Cycle de validation d'une saisie |
| POST | `entries/{entry}/allocate` | Répartir les heures d'une saisie sur plusieurs projets/tâches |
| GET/POST/PUT/PATCH/DELETE | `allocations`, `allocations/{allocation}`, `allocations/project/{project}` | CRUD allocations de temps |
| GET/POST/PUT/PATCH/DELETE | `projects`, `projects/{project}` | CRUD des `TimeTrackingProject` |
| GET | `projects/{project}/timesheets`, `projects/{project}/metrics` | Feuilles de temps et métriques d'un projet de suivi |
| GET | `metrics/summary`, `metrics/employee/{user}/month/{month}`, `metrics/project/{project}` | Rapports d'utilisation |
| GET | `v1/timesheets/timer/current` | Minuteur actif de l'utilisateur courant |
| POST/DELETE | `v1/timesheets/timer/start\|stop\|discard` | Démarrer/arrêter/annuler un minuteur (crée une `TimesheetEntry` à l'arrêt) |
| POST | `v1/timesheets/ai/assist` | Guidance IA contextuelle (AI Assisted First) |

Le contrôleur `TimesheetAdvancedController` (routes documentées dans son propre docblock : `GET/POST /api/v1/timesheets`, `periods/{weekStart}/submit`, `periods/{id}/approve|reject`, `weekly/{employeeId}/{weekStart}`, `team/{managerId}`, `utilization`, `revenue-recognition`, `projects/{id}/billing/*`) **n'est enregistré dans aucun fichier de routes du dépôt** — ni `routes/api.php` du module, ni `routes/web.php`, ni les routes racine. Seul son test dédié (`tests/Feature/TimesheetAdvancedControllerTest.php`) l'exerce ; ces endpoints ne sont donc pas exposés dans l'API telle qu'elle tourne aujourd'hui.

## Services

- **`TimesheetService`** — cœur métier des `TimesheetEntry` : création, mise à jour (bloquée hors statut `draft`), soumission, approbation/rejet, allocation multi-projets avec vérification que la somme des heures allouées correspond aux heures saisies, calcul de métriques employé/projet.
- **`TimerService`** — minuteur live (start/stop/discard) stocké en cache (clé `timesheet_timer:{userId}`, TTL 24h) ; à l'arrêt, délègue à `TimesheetService::createEntry()` pour persister une `TimesheetEntry`.
- **`ProjectBillingService`** — facturation de projet par jalon, pourcentage d'avancement, régie (temps & matériel à partir des saisies approuvées et facturables), ou montant forfaitaire ; calcule la TVA à 18% (UEMOA) et le compte OHADA 7061 ; génère les références `BILL-YYYY-NNNN`/`INV-YYYY-NNNN`. Persiste dans `ts_project_billing` — table absente des migrations du dépôt (repli en mode « démo » avec valeurs générées si la table n'existe pas, via `try/catch` autour de chaque requête `DB::table`).

## Permissions RBAC

Permissions dédiées sous le préfixe `timesheets.*` dans `database/seeders/RolesAndPermissionsSeeder.php` : ressources `timesheet` et `entry`, actions `view-any|view|create|update|delete` (ex. `timesheets.entry.approve` n'existe pas — l'approbation passe par les rôles `admin`/`manager`/`hr-manager` au niveau de `TimesheetEntryPolicy`, pas par une permission Spatie dédiée). Les rôles `hr-manager` et `project-manager` reçoivent automatiquement toutes les permissions `timesheets.*` (voir les blocs dédiés du seeder).

## Dépendances avec d'autres modules

- **Dépend de `Modules\HR`** : `TimesheetEntry`, `TimeEntry` et `Timesheet` référencent `Modules\HR\Models\Employee`.
- **Dépend fortement de `Modules\Projects`** : `TimesheetEntry`, `TimeAllocation`, `TimeEntry` et `ProjectBilling` référencent `Modules\Projects\Models\Project`/`Task` — chaque saisie de temps peut être rattachée à un projet réel du module Projects. Aucun import réciproque (`Modules\Timesheets`) n'a été trouvé côté Projects : la dépendance est à sens unique.
- **Utilisé par `Modules\Projects`** : le service `TimeTrackingDeduplicationService` de Projects documente explicitement Timesheets comme « source of truth » pour le suivi de temps, en dédupliquant les entrées entre les deux modules.
- Utilise `Modules\AuditLog\Traits\HasAuditLog` (sur `TimesheetPeriod` et `ProjectBilling`) pour la traçabilité des modifications.

## Particularités du périmètre life-mdg-erp

- **Deux tables de saisie de temps coexistent** (`TimesheetEntry` → `timesheet_entries`, `TimeEntry` → `timesheets_entries`) avec des schémas voisins mais non identiques (l'une gère un cycle d'approbation, l'autre un taux horaire/facturabilité). Ce n'est pas une régression de l'extraction : les deux modèles, leurs contrôleurs et leurs routes coexistent tels quels dans le code actuel.
- **`TimesheetPeriod` (`ts_timesheet_periods`) et `ProjectBilling` (`ts_project_billing`) n'ont aucune migration créant leur table** dans `database/migrations/` (racine ou module) — seule une table `project_billings` (nom différent) est créée par `2026_05_30_000002_fix_timesheets_table_columns.php`. En l'état, les fonctionnalités qui en dépendent (soumission/approbation de période, facturation persistée en base) ne sont opérationnelles qu'en mode de repli « démo » codé en dur dans `ProjectBillingService`, tant que la table réelle n'est pas migrée.
- **`TimesheetAdvancedController` n'est routé nulle part** dans le dépôt (voir section Endpoints) — c'est un contrôleur orphelin, exercé uniquement par son test dédié qui appelle des URLs non déclarées ailleurs.
