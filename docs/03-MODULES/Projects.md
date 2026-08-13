# Projects

## Rôle

Le module Projects fournit la gestion de projet complète de l'ERP : projets, tâches, épics, sprints, jalons, dépendances et diagramme de Gantt, équipe et capacité de ressources, automatisations, budget (EVM) et facturation. Il fait partie de la ligne **RH basique** aux côtés de HR, Payroll et Timesheets, et sert de référentiel « projet » central : c'est notamment le module dont dépend Timesheets pour rattacher chaque saisie de temps à un projet/tâche réels.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Project` | `prj_projects` | Projet (propriétaire, statut, dates, budget, devise) ; utilise le trait `HelpdeskLinkable` (voir Dépendances) |
| `Task` | `prj_tasks` | Tâche (hiérarchie parent/sous-tâches, épic/sprint/jalon, priorité, points d'histoire) |
| `ProjectTask` | `prj_tasks` | Modèle « alias » de `Task` sur la même table, ajoutant les accesseurs EVM/Gantt (Phase 49) sans modifier `Task` — voir Particularités |
| `Milestone` | `prj_milestones` | Jalon de projet, utilisé aussi pour la facturation par jalon (Timesheets) |
| `Epic` | `prj_epics` | Regroupement de tâches au niveau épopée (méthodologie agile) |
| `Sprint` | `prj_sprints` | Sprint agile (burndown, vélocité) |
| `TaskDependency` | `project_task_dependencies` | Dépendances entre tâches, avec détection de cycles |
| `ProjectTeamMember` | `prj_team_members` | Membre d'équipe affecté à un projet |
| `ResourceAllocation` / `ResourceCapacity` | `prj_resource_allocations` / `prj_resource_capacity` | Planification de capacité des ressources humaines sur les projets |
| `AutomationRule` | `prj_automation_rules` | Règle « SI/QUAND/ALORS » déclenchée par les évènements projet |
| `BudgetLine` | `prj_budget_lines` | Ligne budgétaire (CAPEX/OPEX) pour le suivi EVM |
| `ProjectRisk` | `prj_risks` | Risque identifié sur un projet |
| `TimeEntry` / `TimeLog` / `ProjectTimeLog` | `prj_time_entries` / `prj_time_logs` (partagée par `TimeLog` et `ProjectTimeLog`) | Suivi de temps propre à Projects (distinct des modèles du module Timesheets) |
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

Permissions dédiées sous le préfixe `projects.*` dans `database/seeders/RolesAndPermissionsSeeder.php` : ressources `project` et `task`, actions `view-any|view|create|update|delete` (plus `approve`, `export`, `archive` référencées par `ProjectPolicy` mais non déclarées dans la liste `MODULES` du seeder — à vérifier/compléter si ces actions doivent réellement être assignables). Le rôle `project-manager` reçoit l'ensemble des permissions `projects.*` ainsi que `timesheets.*` et un accès en lecture aux employés HR.

## Dépendances avec d'autres modules

- **Utilise `Modules\Helpdesk`** : `Project` utilise le trait `Modules\Helpdesk\Traits\HelpdeskLinkable`, ce qui permet à tout projet de créer (`raiseTicket()`) et lister (`tickets`) ses propres tickets Helpdesk.
- **Utilise `Modules\Core`** : trait `RecordsActivity` sur `Project`, et `Modules\Core\Services\AI\AIService` dans `ProjectsAIService`.
- **Utilise `Modules\AI`** : `ProjectsAiAssistController` appelle `Modules\AI\Services\AiContextualAssistantService`.
- **Dépendance côté Timesheets** : Projects ne dépend d'aucune classe du module Timesheets (aucun `use Modules\Timesheets\...` trouvé dans le code de Projects), mais c'est l'inverse qui est vrai — voir `docs/03-MODULES/Timesheets.md`. `TimeTrackingDeduplicationService` documente ce lien logique sans introduire de dépendance de code.

## Particularités du périmètre life-mdg-erp

- **La fonctionnalité wiki de projet a été retirée**, comme documenté dans `CLAUDE.md` : aucune trace de `ProjectWikiService`, `ProjectWikiController`, ni d'aucune référence au module `Notes` (`Modules\Notes\Models\Note`) n'existe dans le code actuel de `Modules/Projects` — confirmé par une recherche exhaustive des chaînes `Notes`, `Wiki`/`wiki` dans le module, qui ne retourne aucun résultat. C'était une fonctionnalité annexe autonome, retirée plutôt que transformée en stub, car elle dépendait du module Notes exclu du périmètre life-mdg-erp.
- **Duplication délibérée de modèles sur les mêmes tables** : `Task`/`ProjectTask` partagent tous deux la table `prj_tasks` (le second est explicitement documenté dans son commentaire de classe comme un « alias model » ajoutant des accesseurs EVM/Gantt sans toucher au modèle `Task` existant), et `TimeLog`/`ProjectTimeLog` partagent `prj_time_logs`. Ce n'est pas une erreur d'extraction mais un choix architectural déjà présent tel quel dans le code.
