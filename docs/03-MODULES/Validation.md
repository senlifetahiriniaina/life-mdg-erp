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

Des routes courtes de compatibilité existent aussi (`workflows`, `requests`, `requests/{approval_request}/approve`...). Le module expose en plus deux pages web Inertia (`routes/web.php`, `auth`) : `GET /approval-requests` et `GET /approval-requests/{request}`.

## Services

- **`ApprovalWorkflowService`** — CRUD des workflows et de leurs règles, résolution du workflow applicable à un module+type d'entité (`getApplicableWorkflow`), clonage d'un workflow avec ses règles (`cloneWorkflow`).
- **`ApprovalRequestService`** — crée une `ApprovalRequest` pour n'importe quel modèle passé en argument (`createApprovalRequest($approvable, $workflow, $requestedBy)`), gère l'approbation/rejet/délégation et déclenche les événements (`ApprovalRequestCreated`, `ApprovalApproved`, `ApprovalRejected`, `ApprovalCompleted`), calcule les demandes en attente pour un utilisateur.
- **`ApprovalHierarchyService`** — CRUD des hiérarchies, ajout de niveaux et d'approbateurs, résolution de la chaîne d'approbateurs pour un niveau donné (`getApproverChain`).

## Permissions RBAC

Aucune entrée dédiée `validation.*` dans `RolesAndPermissionsSeeder::MODULES` — le module n'a pas de permissions Spatie granulaires. L'autorisation passe par `ApprovalRequestPolicy`, basée sur les **rôles** plutôt que sur des permissions nommées : `viewAny` exige `admin`, `manager` ou `approver` ; `approve`/`reject`/`delegate` exigent que l'utilisateur soit l'approbateur assigné (`approver_id`) ou ait le rôle `admin`/`manager`, et uniquement si la requête est encore `pending`.

## Dépendances avec d'autres modules

- **`Achats` → `Validation`** (dépendance dure, confirmée dans le code) : `Modules/Achats/app/Models/PurchaseOrder.php`, `Modules/Achats/app/Services/PurchaseOrderService.php`, `Modules/Achats/app/Services/ApprovalRoutingService.php` et `AchatsServiceProvider` importent `Modules\Validation\Models\ApprovalRule` et `ApprovalWorkflow`. `ApprovalRoutingService::getApplicableWorkflow()` interroge les `ApprovalWorkflow` où `module_name = 'Achats'` et évalue chaque `ApprovalRule` (condition sur montant, fournisseur, catégorie fournisseur) pour router une commande vers le bon workflow.
- Aucun autre module du périmètre n'importe `Modules\Validation` directement — le moteur reste générique et n'est câblé qu'à Achats dans ce dépôt, bien qu'il soit conçu pour être réutilisable par tout autre module (relation polymorphe `approvable`).
- Utilise `Modules\Core\Traits\RecordsActivity` pour l'audit.
