# Workflow

## Rôle

Le module Workflow est le moteur d'automatisation métier de Life MDG ERP : il permet de définir des chaînes « déclencheur → conditions → actions » qui réagissent aux événements des autres modules (ex. `crm.opportunity.won`) et exécutent des actions dans Accounting, HR, Inventory, Helpdesk, etc. Il coexiste avec un second sous-système plus récent orienté « flow builder » façon n8n (`AutomationFlow`, `FlowExecutionEngine`), ainsi qu'un ancien moteur de tâches/approbations (builder, `WorkflowController@create`, `TaskController`, `ApprovalController`). Les trois cohabitent dans le même module et sont exposés par des groupes de routes distincts.

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

Toutes les routes sont sous `auth:sanctum`, préfixe `v1`, définies dans `Modules/Workflow/routes/api.php`.

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
| * | `v1/workflow/workflows/*`, `/tasks/*`, `/approvals/*` (middleware `role:manager,admin`) | Ancien moteur builder/tâches/approbations (legacy) |
| POST | `v1/workflow/ai/assist` | Guidance IA contextuelle (AI Assisted First) |

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
- les routes legacy (`/workflow/workflows/*`, `/tasks/*`, `/approvals/*`) sont protégées par le middleware `role:manager,admin` ;
- les policies (`WorkflowDefinitionPolicy`, `WorkflowExecutionPolicy`) vérifient `$user->hasAnyRole(['workflow-manager', 'admin', 'super-admin'])`, avec un fallback `hasPermissionTo('workflow.definition.create')` etc. — ce fallback référence des permissions qui n'existent pas dans le seeder actuel, donc en pratique seul le contrôle par rôle est opérant, et le rôle `workflow-manager` lui-même n'est pas défini dans `RolesAndPermissionsSeeder` (les 22 rôles seedés n'incluent pas ce nom) : sans intervention manuelle, seuls `admin`/`super-admin` peuvent créer/exécuter/supprimer une définition de workflow.

## Dépendances avec d'autres modules

Workflow est un **hub d'intégration** : ses handlers d'action importent directement les modèles/services d'Accounting, Helpdesk, CRM (via `HelpdeskActionHandler` qui utilise `Modules\Helpdesk\Models\Ticket`, `TicketService`, `EscalationService`, `TicketAssignmentService`), et d'autres domaines métier. Aucun autre module de life-mdg-erp n'importe `Modules\Workflow` directement dans son code PHP (`Strategy\StrategyObjectiveLink` et `Helpdesk\HelpdeskServiceProvider` sont les deux seules références trouvées) — les autres modules déclenchent des chaînes indirectement via `FlowExecutionEngine::triggerByKey()` appelé depuis leurs propres services/écouteurs d'événements plutôt que par un import statique.

## Particularités du périmètre life-mdg-erp

`Modules/Workflow/app/Services/Actions/HelpdeskActionHandler.php` porte, dans son docblock de classe, la trace explicite d'une correction faite lors de l'extraction : l'implémentation d'origine écrivait directement en SQL brut (`DB::table('helpdesk_tickets', ...)`) avec un jeu de colonnes (`tenant_id`, `client_id`, `body`, `support_level`, `assigned_to`, `resolution_note`) qui ne correspondait à aucune colonne réelle du schéma Helpdesk — la vraie table est `hd_tickets` avec des colonnes `ticket_number`, `reporter_id`, `assignee_id`, `description`, etc. Résultat : tout appel tombait silencieusement dans la branche « simulée » du catch-all sans jamais toucher de vraies données. Le handler actuel a été réécrit pour utiliser le modèle Eloquent `Ticket` et les vrais services Helpdesk (`TicketService::createFromSource()`, `EscalationService::checkAndEscalate()`, `TicketAssignmentService::assignRoundRobin()`), ce qui corrige le bug à la racine.
