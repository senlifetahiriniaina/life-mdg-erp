# Workflow

## Rôle

Le module Workflow est le moteur d'automatisation métier de Life MDG ERP : il permet de définir des chaînes « déclencheur → conditions → actions » qui réagissent aux événements des autres modules (ex. `crm.opportunity.won`) et exécutent des actions dans Accounting, HR, Inventory, Helpdesk, etc. Il coexiste avec un second sous-système plus récent orienté « flow builder » façon n8n (`AutomationFlow`, `FlowExecutionEngine`). Un troisième bloc de routes — un ancien moteur de builder/tâches/approbations (`WorkflowController@create`/`getCanvas`/etc., `ExecutionController`, `TaskController`, `ApprovalController`) — a été **supprimé** cette session (voir Particularités) : ces classes n'existaient nulle part dans le module, le bloc de ~34 routes était fatal au premier appel.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `WorkflowDefinition` | `wfd_definitions` | Définition d'un workflow legacy (déclencheur, conditions, module cible) |
| `WorkflowAction` | `wfd_actions` | Étapes/actions ordonnées d'un `WorkflowDefinition` |
| `WorkflowExecution` | `wfd_executions` | Historique d'exécution d'un `WorkflowDefinition` |
| `WorkflowExecutionLog` | `wfd_execution_logs` | Log par action exécutée dans une `WorkflowExecution` |
| `WorkflowChainDefinition` | `workflow_chain_definitions` | Définition Phase-39 : `trigger_key` + `conditions` + `actions` déclenchée par clé (ex. `crm.opportunity.won`) |
| `WorkflowChainExecution` | `workflow_chain_executions` | Exécution d'une `WorkflowChainDefinition` |
| `WorkflowExecutionStep` | `workflow_execution_steps` | Détail pas-à-pas d'une `WorkflowChainExecution` |
| `Automation\AutomationFlow` | `automation_flows` | Flow visuel type n8n (nœuds + connexions), versionné |
| `Automation\AutomationNode` | `automation_nodes` | Nœud individuel d'un `AutomationFlow` |
| `Automation\AutomationConnection` | `automation_connections` | Connexion entre deux nœuds |
| `Automation\AutomationExecution` | `automation_executions` | Exécution d'un `AutomationFlow` |
| `Automation\AutomationVariable` | `automation_variables` | Variables réutilisables dans un flow |
| `Automation\AutomationFlowTemplate` | `automation_templates` | Modèles de flow prêts à l'emploi |
| `Automation\FlowVersion` | `flow_versions` | Historique de versions d'un `AutomationFlow` (rollback) |

Les tables sont créées par les migrations racine (`database/migrations/2026_05_17_*workflow*`, `*automation*`, patches `wave15`–`wave26`), pas par des migrations propres au module.

## Endpoints principaux

Toutes les routes sont sous `auth:sanctum`, préfixe `v1`, définies dans `Modules/Workflow/routes/api.php`. **Tout le groupe (hors AI-assist) est désormais gaté `module:Workflow`+`role:manager,admin`** — corrigé cette session : ce groupe n'avait auparavant aucun gate `module:`/`role:` du tout, ce qui incluait `CodeNodeController::execute()`, un endpoint d'exécution de code/expression sandboxé, atteignable par n'importe quel utilisateur authentifié de n'importe quel rôle.

| Méthode | Route | Description |
|---|---|---|
| POST | `v1/workflow/dsl/parse` \| `/validate` \| `/create` | Parseur DSL (règles SI/QUAND/ALORS) |
| GET | `v1/workflow/schema` \| `/actions` \| `/triggers` | Catalogue des nœuds disponibles (`NodeTypeRegistry`) |
| GET/POST/PUT/DELETE | `v1/workflows[/{id}]` | CRUD sur les workflows (API REST moderne) |
| POST | `v1/workflows/trigger` | Déclenchement manuel |
| POST | `v1/workflows/{id}/toggle` | Activer/désactiver |
| GET | `v1/workflows/{id}/executions` | Historique d'exécution |
| GET/POST/PUT/DELETE | `v1/workflow/definitions[/{id}]` | CRUD chaînes Phase-39 (`WorkflowChainDefinition`) |
| GET | `v1/workflow/executions[/{id}]`, `v1/workflow/stats` | Historique + statistiques des chaînes |
| POST | `v1/workflow/trigger` | Déclenchement manuel d'une chaîne (test) |
| GET/POST | `v1/flows/{id}/versions`, POST `/versions/{versionId}/restore` | Versioning + rollback d'un flow |
| POST | `v1/workflow/code-node/validate` \| `/execute` | Nœud de code/expression sandboxé |
| GET/POST/PUT/DELETE | `v1/workflow-chain/definitions[/{definition}]` | CRUD chaînes HR→Payroll (`WorkflowDefinitionController`) |
| POST | `v1/workflow-chain/definitions/{definition}/test` | Test à blanc |
| GET/POST | `v1/workflow-chain/executions[/{execution}]`, `/{execution}/retry` | Exécutions + relance |
| POST | `v1/workflow/ai/assist` | Guidance IA contextuelle (AI Assisted First) |

Le bloc legacy `v1/workflow/workflows/*`, `/tasks/*`, `/approvals/*` (« Legacy Workflow Engine / Builder / Task / Approval Routes », ~34 routes) a été **supprimé** cette session, pas seulement re-gaté (voir Particularités).

## Contrôleurs

`Modules/Workflow/app/Http/Controllers/Api/` (14 fichiers — bien plus que l'estimation initiale « ≤ 5 contrôleurs » sur laquelle ce chantier avait été scopé) :

| Contrôleur | Rôle |
|---|---|
| `WorkflowController` | DSL, schéma/catalogue, CRUD `workflows` (API REST moderne) |
| `WorkflowChainController` | CRUD + exécutions des chaînes Phase-39 |
| `WorkflowDefinitionController` / `WorkflowExecutionController` | CRUD + relance des chaînes HR→Payroll |
| `AutomationFlowController` | Flows visuels façon n8n |
| `FlowVersionController` | Versioning/rollback d'un flow |
| `CodeNodeController` | Nœud de code/expression sandboxé |
| `WorkflowDslController` / `WorkflowNodeController` / `WorkflowScheduleController` / `WorkflowTemplateController` / `WorkflowTriggerController` | Support DSL, nœuds, planification, modèles, déclencheurs — `WorkflowNodeController` en particulier n'est routé nulle part (voir Particularités) |
| `AiWorkflowController` / `WorkflowAiAssistController` | Suggestion/génération IA de règles, guidance contextuelle |

Aucun contrôleur `Web/` — voir Vues.

## Vues (Vue/Inertia)

Aucune route web (`Modules/Workflow/routes/web.php` n'existe pas). Deux pages existent dans le dépôt sans être routées : `Modules/Workflow/resources/js/Pages/AIWorkflowBuilder/Index.vue` et `RPA/Index.vue` — toutes deux 100 % données mock, sans modèle réel derrière, examinées et **délibérément laissées non routées** cette session (même traitement que les autres pages mock identifiées ailleurs dans le dépôt, ex. Logistics `AIRiskMonitor.vue`).

## Services

- **`WorkflowEngineService`** — moteur legacy : `executeWorkflow()`, évaluation de conditions/déclencheurs, `dispatchAction()`, `dispatchWithRetry()`/`dispatchWithTimeout()`, `processChain()`.
- **`Automation\FlowExecutionEngine`** — moteur de flow façon n8n : `execute()` traverse le graphe de nœuds d'un `AutomationFlow`, `evaluateConditionExpr()`, `pause()`/`resume()`/`retry()`, et `triggerByKey()` (point d'entrée documenté pour déclencher un flow depuis n'importe quel autre module, ex. `crm.opportunity.won`).
- **`Automation\NodeTypeRegistry`** — registre central de tous les triggers/actions (`getAll()`, `getByModule()`, `getActionsForModule()`), avec libellés en français.
- **`Automation\FlowSchedulerService`** — planification cron des flows.
- **`Automation\AiWorkflowAssistantService`** — suggestion/génération/validation de règles via Claude.
- **`WorkflowDslParser`** / **`WorkflowJsonSchema`** — parseur du DSL français (SI/QUAND/ALORS) et validation JSON Schema associée.
- **`CodeNodeService`** — exécution sandboxée d'un nœud de code/expression.
- **`FlowVersionService`** — snapshot/restore de versions de flow.
- **`TaskManagementService`** / **`ApprovalWorkflowService`** — gestion legacy de tâches et de circuits d'approbation.
- **`WorkflowActionRegistry`** / **`WorkflowBuilderService`** — registre et builder du moteur legacy.
- **Handlers d'action** (`app/Services/Actions/`, un par domaine, chacun implémentant `dispatch(string $action, array $params, array $context): array`, enregistrés en singleton dans `WorkflowServiceProvider`) : `AchatsInventoryActionHandler`, `CrmSalesActionHandler`, `HrPayrollActionHandler`, `InventoryAccountingActionHandler`, `SalesManufacturingActionHandler`, `NotificationActionHandler`, `AiActionHandler`, `CalendarActionHandler`, `DataTransformHandler`, `DelayActionHandler`, `DocumentsActionHandler`, `EcommerceActionHandler`, `HelpdeskActionHandler`, `HttpActionHandler`, `LogisticsActionHandler`, `ProjectsActionHandler`, `QualityActionHandler`, `StrategyActionHandler`.

## Permissions RBAC

Workflow n'a **pas** d'entrée dans `RolesAndPermissionsSeeder::MODULES` (contrairement à `crm`, `sales`, `accounting`, etc.) — aucune permission granulaire `workflow.*.*` n'est réellement seedée. L'accès est contrôlé autrement :
- **tout le fichier `routes/api.php`** (hors AI-assist) est désormais protégé par `module:Workflow`+`role:manager,admin`, ajouté cette session au niveau du groupe englobant plutôt que route par route — avant ce correctif, aucune route de ce fichier n'avait de gate `module:`/`role:` du tout ;
- les policies (`WorkflowDefinitionPolicy`, `WorkflowExecutionPolicy`) vérifient `$user->hasAnyRole(['workflow-manager', 'admin', 'super-admin'])`, avec un fallback `hasPermissionTo('workflow.definition.create')` etc. — ce fallback référence des permissions qui n'existent pas dans le seeder actuel, donc en pratique seul le contrôle par rôle est opérant, et le rôle `workflow-manager` lui-même n'est pas défini dans `RolesAndPermissionsSeeder` (les 22 rôles seedés n'incluent pas ce nom) : sans intervention manuelle, seuls `admin`/`super-admin` peuvent créer/exécuter/supprimer une définition de workflow — non touché cette session, le correctif RBAC a porté sur le gate de route, pas sur ces policies.

## Dépendances avec d'autres modules

Workflow est un **hub d'intégration** : ses handlers d'action importent directement les modèles/services d'Accounting, Helpdesk, CRM (via `HelpdeskActionHandler` qui utilise `Modules\Helpdesk\Models\Ticket`, `TicketService`, `EscalationService`, `TicketAssignmentService`), et d'autres domaines métier. Aucun autre module de life-mdg-erp n'importe `Modules\Workflow` directement dans son code PHP (`Strategy\StrategyObjectiveLink` et `Helpdesk\HelpdeskServiceProvider` sont les deux seules références trouvées) — les autres modules déclenchent des chaînes indirectement via `FlowExecutionEngine::triggerByKey()` appelé depuis leurs propres services/écouteurs d'événements plutôt que par un import statique.

## Particularités du périmètre life-mdg-erp

- **Bloc de routes legacy supprimé, pas seulement re-gaté** : l'ancien bloc « Legacy Workflow Engine / Builder / Task / Approval Routes » (~34 routes) référençait 14 méthodes `WorkflowController@*` (`create`, `getCanvas`, `delete`, `duplicate`, `publish`, `execute`, `addStep`, `updateStep`, `removeStep`, `addTrigger`, `removeTrigger`, `validate`, `export`, `import`) qui n'existent pas sur le vrai `WorkflowController` (ses vraies méthodes sont `index`/`store`/`show`/`update`/`destroy`/`toggle`/`executions`/`trigger`/`dslParse`/`dslValidate`/`dslCreate`/`schema`/`actions`/`triggers` — seul `show` coïncidait par hasard) et vers 3 classes (`ExecutionController`, `TaskController`, `ApprovalController`) inexistantes dans `Modules\Workflow` (`TaskController`/`ApprovalController` n'existent que dans les espaces de noms sans rapport `Projects`/`Core`). Chaque route de ce bloc était une erreur fatale « action does not exist » garantie au premier appel — même famille de sous-système mort et redondant que `TerritoryManagementController` (CRM), `wh_*`/`lgx_*` (Logistics), `PurchaseApprovalChainService` (Achats), déjà trouvés et supprimés ailleurs cette session. La gestion de tâches et les circuits d'approbation sont déjà des fonctionnalités réelles et actives dans `Modules/Projects` et `Modules/Validation` respectivement.
- **Services legacy désormais orphelins, laissés en place** : `TaskManagementService`/`ApprovalWorkflowService`/`WorkflowBuilderService` (qui alimentaient l'ancien bloc de routes supprimé) n'ont plus aucun consommateur dans `Http/Controllers/` — confirmé par recherche. `WorkflowActionRegistry` a un seul consommateur restant, `WorkflowNodeController`, lui-même non routé nulle part. Ces fichiers de service n'ont pas été supprimés cette session (le nettoyage a porté sur les routes/contrôleurs fatals, pas sur cette dette de code mort adjacente) — à traiter dans un futur passage.

`Modules/Workflow/app/Services/Actions/HelpdeskActionHandler.php` porte, dans son docblock de classe, la trace explicite d'une correction faite lors de l'extraction : l'implémentation d'origine écrivait directement en SQL brut (`DB::table('helpdesk_tickets', ...)`) avec un jeu de colonnes (`tenant_id`, `client_id`, `body`, `support_level`, `assigned_to`, `resolution_note`) qui ne correspondait à aucune colonne réelle du schéma Helpdesk — la vraie table est `hd_tickets` avec des colonnes `ticket_number`, `reporter_id`, `assignee_id`, `description`, etc. Résultat : tout appel tombait silencieusement dans la branche « simulée » du catch-all sans jamais toucher de vraies données. Le handler actuel a été réécrit pour utiliser le modèle Eloquent `Ticket` et les vrais services Helpdesk (`TicketService::createFromSource()`, `EscalationService::checkAndEscalate()`, `TicketAssignmentService::assignRoundRobin()`), ce qui corrige le bug à la racine.
