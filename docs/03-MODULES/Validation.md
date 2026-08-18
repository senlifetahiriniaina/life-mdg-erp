# Validation

## Rôle

`Validation` est le moteur d'approbation générique de life-mdg-erp : il ne connaît aucune entité métier directement, mais expose un workflow d'approbation réutilisable (règles, hiérarchies, historique) que n'importe quel autre module peut brancher sur ses propres modèles via une relation polymorphe (`approvable_type` / `approvable_id`). Dans ce périmètre, il est concrètement utilisé par `Achats` pour le routage d'approbation des commandes fournisseur (`PurchaseOrder`).

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `ApprovalWorkflow` | `validation_approval_workflows` | Un workflow nommé, rattaché à un `module_name` (ex. `Achats`), actif ou non. |
| `ApprovalRule` | `validation_approval_rules` | Règle conditionnelle d'un workflow (ex. condition sur montant, fournisseur, catégorie fournisseur). |
| `ApprovalRequest` | `validation_approval_requests` | Demande d'approbation concrète : relation polymorphe `approvable()` vers l'entité métier (ex. un `PurchaseOrder`), statut, demandeur, approbateur. |
| `ApprovalAction` | `validation_approval_actions` | Action effectuée sur une requête (approuver, rejeter, déléguer) avec commentaire. |
| `ApprovalHistory` | `validation_approval_histories` | Historique chronologique des changements d'état d'une requête. |
| `ApprovalHierarchy` | `validation_approval_hierarchies` | Hiérarchie d'approbation nommée, éventuellement rattachée à une `company_id`. |
| `HierarchyLevel` | `validation_hierarchy_levels` | Niveau ordonné (`level_order`) au sein d'une hiérarchie, avec titre et nombre d'approbateurs requis. |
| `LevelApprover` | `validation_level_approvers` | Utilisateur assigné à un niveau, avec ordre et approbateur de secours (`backup_user_id`). |

Tous les modèles principaux utilisent `SoftDeletes` et le trait `Modules\Core\Traits\RecordsActivity` (`$auditModule = 'Validation'`).

## Endpoints principaux

Routes API sans préfixe de module dans `routes/api.php` (`throttle:simple_get` en lecture, `throttle:create_post` en écriture) :

| Méthode | Route | Description |
|---|---|---|
| GET | `approval-workflows` / `{workflow}` | Liste / détail des workflows |
| GET | `approval-workflows/{workflow}/rules` / `{rule}` | Règles d'un workflow |
| POST/PUT/DELETE | `approval-workflows`, `approval-workflows/{workflow}` | CRUD workflow |
| POST/PUT/DELETE | `approval-workflows/{workflow}/rules...` | CRUD règles |
| GET | `approval-requests` / `{request}` / `{request}/history` | Liste, détail et historique des demandes |
| POST | `approval-requests` | Créer une demande d'approbation |
| POST | `approval-requests/{approval_request}/approve` \| `/reject` | Approuver / rejeter |
| POST | `approval-requests/{request}/delegate` | Déléguer une approbation |
| GET | `approval-hierarchies` / `{hierarchy}` | Liste / détail des hiérarchies |
| POST/PUT/DELETE | `approval-hierarchies...` | CRUD hiérarchie |
| POST | `approval-hierarchies/{hierarchy}/levels` | Ajouter un niveau |
| POST | `approval-hierarchies/{hierarchy}/levels/{level}/approvers` | Ajouter des approbateurs à un niveau |
| POST | `ai/assist` (`prefix: v1/validation`) | Guidance IA contextuelle |
| GET/POST | `validation-rules`, `validation-rule-sets`, `validation-rule-sets/{ruleSet}`, `validation-rule-sets/{ruleSet}/validate` | Moteur de règles de données génériques — endpoint sœur, distinct du moteur d'approbation ci-dessus (`Modules/Validation/routes/validation-rules.php`) ; `store`/`update`/`destroy`/`addDependency`/la création de rule-set sont sous `role:admin,super-admin` (trou RBAC corrigé cette session, voir RBAC) |
| POST | `validation-rules/{rule}/dependencies`, `validation-rule-sets/{ruleSet}/rules` | Ajouter une dépendance entre règles (rejet 422 si cycle détecté) / ajouter une règle à un ensemble — nouveaux endpoints cette session, câblant `ValidationEngine::createRuleSet()`/`hasCircularDependency()`, réels et testés depuis longtemps mais jamais exposés |
| POST | `approval-workflows/{workflow}/clone` | Cloner un workflow avec ses règles — nouvellement routé cette session (méthode réelle, corrigée, jamais routée avant) |

Des routes courtes de compatibilité existent aussi (`workflows`, `requests`, `requests/{approval_request}/approve`...). Le module expose en plus 6 pages web Inertia (`routes/web.php`, `auth`) : `GET /approval-requests`, `/approval-requests/{request}`, `/workflows`, `/workflows/builder`, `/workflows/{workflow}/builder`, `/validation-rules`.

## Contrôleurs

API (`Modules/Validation/app/Http/Controllers/Api/`) : `ApprovalWorkflowController`, `ApprovalRuleController`, `ApprovalRequestController` (désormais avec `authorize()` sur `index()`/`show()` — voir RBAC), `ApprovalHierarchyController`, `ValidationRuleController`, **`ValidationRuleSetController`** (nouveau cette session), `ValidationAiAssistController`.

Web (`Modules/Validation/app/Http/Controllers/Web/`) : `ValidationRuleWebController`, `WorkflowWebController`, `ApprovalRequestController` (Web, même nom que son homonyme API mais namespace différent).

## Vues (Vue/Inertia)

Toutes sous `Modules/Validation/resources/js/Pages/` : `ApprovalRequests/{Index,Show}.vue`, `Workflows/{Index,Builder}.vue`, `ValidationRules/Index.vue`. Deux composables JS confirmés morts (`useApprovals.js`, `useWorkflows.js`, zéro import trouvé nulle part dans le dépôt) ont été supprimés cette session.

## Services

- **`ApprovalWorkflowService`** — CRUD des workflows et de leurs règles, résolution du workflow applicable à un module+type d'entité (`getApplicableWorkflow`), clonage d'un workflow avec ses règles (`cloneWorkflow`, **bug corrigé cette session** — voir Particularités).
- **`ApprovalRequestService`** — crée une `ApprovalRequest` pour n'importe quel modèle passé en argument (`createApprovalRequest($approvable, $workflow, $requestedBy)`), gère l'approbation/rejet/délégation et déclenche les événements (`ApprovalRequestCreated`, `ApprovalApproved`, `ApprovalRejected`, `ApprovalCompleted`). `getPendingApprovalsForUser()` **corrigé cette session** — voir Particularités (fuite de visibilité).
- **`ApprovalHierarchyService`** — CRUD des hiérarchies, ajout de niveaux et d'approbateurs, résolution de la chaîne d'approbateurs pour un niveau donné (`getApproverChain`).
- **`ValidationEngine`** — moteur de règles de validation de données génériques ; ses méthodes de regroupement en ensembles (`createRuleSet()`, `validateWithRuleSet()`, `hasCircularDependency()`) étaient réelles et testées mais sans contrôleur/route jusqu'à cette session — désormais exposées par `ValidationRuleSetController`.

## Permissions RBAC

Aucune entrée dédiée `validation.*` dans `RolesAndPermissionsSeeder::MODULES` — le module n'a pas de permissions Spatie granulaires. L'autorisation du moteur d'approbation passe par `ApprovalRequestPolicy`, basée sur les **rôles** plutôt que sur des permissions nommées : `viewAny` exige `admin`, `manager` ou `approver` ; `approve`/`reject`/`delegate` exigent que l'utilisateur soit l'approbateur assigné (`approver_id`) ou ait le rôle `admin`/`manager`, et uniquement si la requête est encore `pending`. Le moteur de règles de données génériques (`validation-rules*`) est gaté séparément par middleware de route.

Corrections RBAC de cette session :
- `validation-rules.php` : `store`/`update`/`destroy` (règles) et `store`/`addRule` (ensembles de règles) n'avaient auparavant **aucun** gate de rôle — n'importe quel utilisateur authentifié pouvait créer/modifier/supprimer des règles de validation. Corrigé avec `role:admin,super-admin`, alignant sur le gate déjà appliqué aux routes sœurs `approval-workflows`/`approval-hierarchies`.
- `ApprovalRequestController::index()`/`show()` n'avaient **aucun** appel `authorize()` malgré `ApprovalRequestPolicy::viewAny()`/`view()` déjà correctement écrites et enregistrées — n'importe quel utilisateur authentifié pouvait lister l'intégralité des demandes d'approbation de l'entreprise et voir le détail de n'importe laquelle par id. Corrigé.

## Dépendances avec d'autres modules

- **`Achats` → `Validation`** (dépendance dure, confirmée dans le code) : `Modules/Achats/app/Models/PurchaseOrder.php`, `Modules/Achats/app/Services/PurchaseOrderService.php`, `Modules/Achats/app/Services/ApprovalRoutingService.php` et `AchatsServiceProvider` importent `Modules\Validation\Models\ApprovalRule` et `ApprovalWorkflow`. `ApprovalRoutingService::getApplicableWorkflow()` interroge les `ApprovalWorkflow` où `module_name = 'Achats'` et évalue chaque `ApprovalRule` (condition sur montant, fournisseur, catégorie fournisseur) pour router une commande vers le bon workflow. Cette session a par ailleurs supprimé un sous-système parallèle et redondant côté Achats (`PurchaseApprovalChainService`/`PurchaseOrderApproval`/`PurchaseApprovalController`, avec ses seuils codés en dur) qui dupliquait ce flux d'approbation déjà réel — le vrai chemin d'approbation Achats reste exclusivement celui basé sur `Modules\Validation`/`ApprovalRoutingResolver`.
- Aucun autre module du périmètre n'importe `Modules\Validation` directement — le moteur reste générique et n'est câblé qu'à Achats dans ce dépôt, bien qu'il soit conçu pour être réutilisable par tout autre module (relation polymorphe `approvable`).
- Utilise `Modules\Core\Traits\RecordsActivity` pour l'audit.

## Particularités du périmètre life-mdg-erp

- **`ApprovalWorkflowService::cloneWorkflow()` avait un bug réel, corrigé cette session** : l'appel `$rule->replicate(['workflow_id' => $newWorkflow->id])` traite `replicate(array $except)` comme une liste de *noms* d'attributs à exclure, pas une map de substitution — c'était donc un no-op pour l'exclusion et le nouveau `workflow_id` n'était jamais réellement affecté, si bien que chaque règle clonée continuait silencieusement de pointer vers le workflow d'origine. Corrigé en `$rule->replicate()` suivi d'une affectation explicite `$newRule->workflow_id = $newWorkflow->id`. La méthode elle-même n'avait par ailleurs jamais eu de route (`POST approval-workflows/{workflow}/clone`, ajoutée cette session).
- **`ApprovalRequestService::getPendingApprovalsForUser()` avait une fuite de visibilité, corrigée cette session** : la méthode filtrait sur les demandes que l'utilisateur **n'avait pas encore traitées** (`doesntExist()` sur ses propres actions) au lieu des demandes où il **est l'approbateur assigné** — comme presque personne n'a encore agi sur une demande donnée en attente, cela renvoyait en pratique toutes les demandes en attente de l'entreprise à tout appelant, pas seulement les siennes. Corrigé en filtrant directement sur `approver_id === $user->id`.
