# Projects

## Rôle

Le module Projects fournit la gestion de projet complète de l'ERP : projets, tâches, épics, sprints, jalons, dépendances et diagramme de Gantt, équipe et capacité de ressources, automatisations, budget (EVM) et facturation. Il fait partie de la ligne **RH basique** aux côtés de HR, Payroll et Timesheets, et sert de référentiel « projet » central : c'est notamment le module dont dépend Timesheets pour rattacher chaque saisie de temps à un projet/tâche réels.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Project` | `prj_projects` | Projet (propriétaire, statut, dates, budget, devise) ; utilise le trait `HelpdeskLinkable` (voir Dépendances) |
| `Task` | `prj_tasks` | Tâche (hiérarchie parent/sous-tâches, épic/sprint/jalon, priorité, points d'histoire) — modèle unique désormais (voir Particularités : `ProjectTask`, un doublon sur la même table, a été supprimé cette session) |
| `Milestone` | `prj_milestones` | Jalon de projet, utilisé aussi pour la facturation par jalon (Timesheets) |
| `Epic` | `prj_epics` | Regroupement de tâches au niveau épopée (méthodologie agile) |
| `Sprint` | `prj_sprints` | Sprint agile (burndown, vélocité) |
| `TaskDependency` | `project_task_dependencies` | Dépendances entre tâches, avec détection de cycles |
| `ProjectTeamMember` | `prj_team_members` | Membre d'équipe affecté à un projet |
| `ResourceAllocation` / `ResourceCapacity` | `prj_resource_allocations` / `prj_resource_capacity` | Planification de capacité des ressources humaines sur les projets |
| `AutomationRule` | `prj_automation_rules` | Règle « SI/QUAND/ALORS » déclenchée par les évènements projet |
| `BudgetLine` | `prj_budget_lines` | Ligne budgétaire (CAPEX/OPEX) pour le suivi EVM — table migrée cette session (voir Particularités, elle n'existait pas du tout avant) |
| `ProjectRisk` | `prj_risks` | Risque identifié sur un projet — table migrée cette session |
| `TimeEntry` / `ProjectTimeLog` | `prj_time_entries` / `prj_time_logs` | Suivi de temps propre à Projects (distinct des modèles du module Timesheets) — `ProjectTimeLog` est désormais le seul modèle réel sur `prj_time_logs` (voir Particularités : `TimeLog`, cassé et jamais aligné avec cette table, a été supprimé cette session) |
| `ProjectBilling` (Projects) | `prj_project_billing` | Facturation côté Projects — homonyme du modèle `ProjectBilling` de Timesheets mais table et namespace différents |
| `CustomField` / `CustomFieldValue` | `projects_custom_fields` / `projects_custom_field_values` | Champs personnalisés par projet |
| `SavedView` | `projects_saved_views` | Vue sauvegardée (filtre Kanban/Gantt/Calendrier) |

## Endpoints principaux

Tous sous `auth:sanctum` + `module:Projects` + `role:employee,manager,admin`, montés sous `api/v1` (`Modules/Projects/routes/api.php`) :

| Méthode | Route | Description |
|---|---|---|
| GET/POST/PUT/DELETE | `projects`, `projects/{project}` | CRUD projet |
| GET/POST/PUT/DELETE | `projects/{project}/tasks`, `tasks/{task}` | CRUD tâches scopées au projet |
| GET/POST/PUT/DELETE | `projects/{project}/milestones`, `projects/{project}/epics`, `projects/{project}/sprints` | CRUD jalons / épics / sprints |
| GET | `projects/{project}/sprints/{sprint}/burndown`, `projects/{project}/velocity`, `projects/{project}/backlog` | Rapports agile |
| POST | `projects/{project}/sprints/{sprint}/start\|complete` | Cycle de vie du sprint |
| GET | `projects/{project}/views/kanban\|calendar\|gantt` | Vues alternatives du projet |
| GET | `projects/{project}/gantt` | Gantt avec dépendances et chemin critique |
| POST/DELETE/PATCH | `projects/tasks/{task}/dependencies`, `projects/tasks/{task}/gantt` | Gestion des dépendances et replanification |
| GET | `projects/{project}/report`, `projects/{project}/report/pdf` | Rapport projet (JSON/PDF) |
| GET/POST/DELETE | `projects/{project}/team`, `projects/{project}/time-logs` | Équipe et journal de temps du projet |
| GET/POST/PUT/DELETE | `projects/{project}/members` | Membres du projet |
| GET/POST/PUT/DELETE | `projects/{project}/automations` | Règles d'automatisation |
| GET/POST/PUT/DELETE | `projects/time-entries`, `projects/{project}/tasks/{task}/time-entries` | Suivi de temps (démarrage/arrêt/facturation) |
| GET/POST | `projects/{project}/billing`, `projects/{project}/billing/setup\|mark-billed` | Facturation projet |
| GET/POST/PUT/DELETE | `allocations`, `allocations/{allocation}` | Allocations de ressources |
| GET | `capacity/utilization`, `capacity/users/{user}/availability`, `capacity/check-overallocation`, `projects/{project}/demand` | Rapports de capacité |
| POST | `capacity/suggest`, `capacity/users/{user}/leave` | Suggestion d'allocation, saisie de congé |
| POST | `projects/ai/estimate-task`, `identify-risks`, `status-report`, `generate-tasks`, `suggest-prioritization` | IA projet (`throttle:expensive`) |
| POST | `v1/projects/ai/assist` | Guidance IA contextuelle (AI Assisted First) |
| GET | `v1/projects/portfolio/kpis\|timeline\|resources`, `v1/projects/{id}/budget\|kpis\|risks` | Budget/KPI/risque avancés (`ProjectAdvancedController`, Phase 49) — désormais sous `module:Projects, role:employee,manager,admin` (voir Particularités, trou RBAC corrigé cette session) |
| GET/POST/PUT/DELETE | `projects/time-entries`, `projects/{project}/tasks/{task}/time-entries` | Suivi de temps par tâche, réécrit cette session sur `ProjectTimeLog` (voir Particularités) |

Routes web (`Modules/Projects/routes/web.php`, `auth`+`module:Projects`) : `projects`, `projects/time-report`, `projects/roadmap`, `projects/{project}`, `projects/{project}/{calendar,gantt,kanban,automation,epics,sprints}` — 7 pages réelles ajoutées cette session (voir Particularités, elles n'avaient auparavant aucune route).

## Contrôleurs

API (`Modules/Projects/app/Http/Controllers/Api/`) : `ProjectController`, `TaskController`, `MilestoneController`, `EpicController`, `SprintController`, `GanttController`, `ProjectViewsController`, `ProjectReportController`, `ProjectTeamController`, `ProjectMemberController`, `AutomationController`, `ResourceCapacityController`, `TimeEntryController` (réécrit cette session sur `ProjectTimeLog` — voir Particularités), `TimeTrackingController`, `ProjectAdvancedController` (Phase 49 : budget/KPI/risques/portefeuille — `index`/`store`/`show`/`gantt`/`storeTask`/`updateTask` sont délibérément non routés, car ils entreraient en collision avec les routes déjà actives des contrôleurs dédiés), `ProjectsAIController`, `ProjectsAiAssistController`.

Web (`Modules/Projects/app/Http/Controllers/Web/`) : **`ProjectWebController`** (index/show/calendar/gantt/kanban/automation/epics/sprints/roadmap), **`ProjectTimeReportController`** (rapport de temps global, réécrit cette session sur `TimeTrackingController`). Un `ProjectsController` scaffold mort (zéro route + vue Blade) et 3 policies inatteignables (`Modules\Projects\Policies\{Project,Task,Milestone}Policy` — les vraies policies actives sont `App\Policies\{Project,Task}Policy`) ont été supprimés.

## Vues (Vue/Inertia)

Toutes les pages Projects actives vivent désormais à la **racine** (`resources/js/Pages/Projects/`) — le module `Modules/Projects/resources/js/Pages/` ne contient plus aucune page après suppression du doublon `TimeReport/Index.vue` masqué cette session : `Index.vue`, `Show.vue`, `Calendar.vue`, `Gantt.vue`, `Kanban.vue`, `Roadmap.vue`, `Automation/Index.vue`, `Epics/Index.vue`, `Sprints/Index.vue`, `TimeReport/Index.vue`. Les 7 premières (`Automation`/`Calendar`/`Epics`/`Gantt`/`Kanban`/`Roadmap`/`Sprints`) étaient réelles et entièrement construites mais **n'avaient aucune route web** avant cette session ; `TimeReport/Index.vue` appelait un endpoint `time-report-global` qui n'existait pas — bâti sur `TimeTrackingController`, avec agrégation réelle par membre/projet/tâche et montant facturable.

## Services

- **`AutomationService`** — évalue les règles d'automatisation actives (`AutomationRule`) correspondant à un déclencheur donné.
- **`CustomFieldService`** — définitions de champs personnalisés et leurs valeurs par projet.
- **`DependencyCycleDetectionService`** — détection de cycles dans le graphe de dépendances entre tâches (parcours en profondeur, profondeur max 10 niveaux) avant d'autoriser l'ajout d'une dépendance.
- **`GanttService`** — construction des données du diagramme de Gantt (dates, dépendances, chemin critique).
- **`ProjectBudgetService`** — suivi budgétaire EVM (SPI/CPI/EAC), mapping des comptes OHADA (CAPEX Cl.2, OPEX Cl.6, revenus Cl.7061), courbe en S, alertes budgétaires ; montants exprimés en XOF.
- **`ProjectKpiService`** — KPI projet et portefeuille, intégration Strategy First, tendances de vélocité, score de santé projet.
- **`ProjectReportService`** — génération des rapports projet (JSON/PDF).
- **`ProjectTeamService`** — ajout/retrait de membres d'équipe et leur rôle.
- **`ResourceCapacityService`** — allocation de ressources, calcul de disponibilité et de sur-allocation.
- **`SprintService`** — cycle de vie du sprint (démarrage, complétion).
- **`TaskConflictResolutionService`** — verrouillage pessimiste des tâches en édition concurrente et résolution « dernier écrit gagne ».
- **`TimeTrackingDeduplicationService`** — déduplique les saisies de temps d'un utilisateur/date et documente explicitement le module **Timesheets** comme source de vérité unique pour le suivi de temps.
- **`TimeTrackingService`** — démarrage/arrêt de minuteur et création de `TimeEntry` côté Projects.
- **`Services\AI\ProjectsAIService`** — appelle `Modules\Core\Services\AI\AIService` pour l'estimation de tâches, l'identification de risques, la génération de rapports de statut, la génération de tâches et la suggestion de priorisation.

## Permissions RBAC

Permissions dédiées sous le préfixe `projects.*` dans `database/seeders/RolesAndPermissionsSeeder.php` : ressources `project` et `task`, actions `view-any|view|create|update|delete` (plus `approve`, `export`, `archive` référencées par `ProjectPolicy` mais non déclarées dans la liste `MODULES` du seeder — inchangé cette session). Le rôle `project-manager` reçoit l'ensemble des permissions `projects.*` ainsi que `timesheets.*` et un accès en lecture aux employés HR.

Correction RBAC de cette session : le groupe de routes Phase 49 (`portfolio/*`, `{id}/budget\|kpis\|risks`) n'avait **aucune** gating `module:`/`role:` — n'importe quel utilisateur authentifié de n'importe quel tenant pouvait lire le budget/KPI/risque de n'importe quel projet. Corrigé avec `module:Projects, role:employee,manager,admin`, identique au reste du module. Ses tables (`prj_budget_lines`/`prj_expense_ledger`/`prj_risks`) n'avaient elles-mêmes aucune migration — chaque appel réel échouait de toute façon (« table not found ») avant que le trou RBAC ne soit même exploitable ; corrigé par la même migration.

## Dépendances avec d'autres modules

- **Utilise `Modules\Helpdesk`** : `Project` utilise le trait `Modules\Helpdesk\Traits\HelpdeskLinkable`, ce qui permet à tout projet de créer (`raiseTicket()`) et lister (`tickets`) ses propres tickets Helpdesk.
- **Utilise `Modules\Core`** : trait `RecordsActivity` sur `Project`, et `Modules\Core\Services\AI\AIService` dans `ProjectsAIService`.
- **Utilise `Modules\AI`** : `ProjectsAiAssistController` appelle `Modules\AI\Services\AiContextualAssistantService`.
- **Dépendance côté Timesheets** : Projects ne dépend d'aucune classe du module Timesheets (aucun `use Modules\Timesheets\...` trouvé dans le code de Projects), mais c'est l'inverse qui est vrai — voir `docs/03-MODULES/Timesheets.md`. `TimeTrackingDeduplicationService` documente ce lien logique sans introduire de dépendance de code.

## Particularités du périmètre life-mdg-erp

- **La fonctionnalité wiki de projet a été retirée**, comme documenté dans `CLAUDE.md` : aucune trace de `ProjectWikiService`, `ProjectWikiController`, ni d'aucune référence au module `Notes` (`Modules\Notes\Models\Note`) n'existe dans le code actuel de `Modules/Projects` — confirmé par une recherche exhaustive des chaînes `Notes`, `Wiki`/`wiki` dans le module, qui ne retourne aucun résultat. C'était une fonctionnalité annexe autonome, retirée plutôt que transformée en stub, car elle dépendait du module Notes exclu du périmètre life-mdg-erp.
- **Trois piles de suivi de temps qui se chevauchaient ont été réduites cette session** : `Task`/`ProjectTask` partageaient `prj_tasks` (le second n'ajoutait que des accesseurs EVM/Gantt) et `TimeLog`/`ProjectTimeLog` partageaient `prj_time_logs`, mais `TimeLog` avait un `$fillable` qui ne correspondait jamais réellement à cette table. `ProjectTask` et `TimeLog` (+ leurs factories) ont été supprimés ; `TimeEntryController` (endpoint `projects/{project}/tasks/{task}/time-entries`, déjà routé) a été réécrit sur `ProjectTimeLog`, le modèle qui correspond réellement au schéma, avec un adaptateur convertissant la saisie manuelle simple (heures + date) vers la forme « minuteur » réellement stockée par ce modèle.
- **Référence orpheline non déclenchée trouvée pendant cette vérification** (non corrigée — hors du périmètre « documentation » de cette passe, signalée ici plutôt que masquée) : `Task::timeLogs()` (`Modules/Projects/app/Models/Task.php`) référence toujours `TimeLog::class` — la classe supprimée ci-dessus — sans qu'aucune classe de ce nom n'existe plus dans `Modules\Projects\Models`. Aucun appelant de cette relation n'a été trouvé dans le dépôt (`$task->timeLogs`/`timeLogs()` : zéro résultat hors de sa propre définition), donc ce n'est pas une régression active, mais le premier appel réel lèverait une erreur de classe introuvable.
