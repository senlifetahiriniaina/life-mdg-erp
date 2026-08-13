# AuditLog

## Rôle

`Modules/AuditLog` expose la consultation, les statistiques et l'export du journal d'audit de l'ERP : qui a fait quoi, sur quelle entité, à quel moment. Il fournit une page web (Inertia) et une API REST de lecture, ainsi qu'un trait (`HasAuditLog`) massivement adopté par les modèles métier du reste de l'application. C'est un module de la « socle CORE / système », mais son fonctionnement réel s'appuie en grande partie sur les données produites par le module Core plutôt que sur sa propre table (voir Particularités).

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `AuditLog` (`Modules\AuditLog\Models\AuditLog`) | `audit_logs` | Modèle propre du module (tenant-scopé, champs `entity_type`/`entity_id`) — **non lu par l'API/Web du module**, voir Particularités |

Le module dépend en réalité du modèle `Modules\Core\Models\AuditLog` (table `core_audit_logs`) pour toutes ses lectures (API et page web).

## Endpoints principaux

**API** (`/api/v1/`, `auth:sanctum`, voir `Modules/AuditLog/routes/api.php`) :

| Méthode | Route | Description |
|---|---|---|
| GET | `audit-logs` | Liste paginée (filtres : `search`, `module`, `event_type`, `user_id`, `date_from`, `date_to`) |
| GET | `audit-logs/stats` | Compteurs today/this_week/this_month, top 10 par module et par type d'événement |
| GET | `audit-logs/export` | Export JSON (max 10 000 lignes) |
| GET | `audit-logs/{id}` | Détail d'une entrée |
| POST | `audit-logs/ai/assist` | Guidance IA contextuelle (AI Assisted First) |

**Web** (Inertia, voir `Modules/AuditLog/routes/web.php`) : `GET /audit/logs` → `AuditLogWebController@index`, protégée par `middleware(['auth', 'module:AuditLog'])` côté route et `can:auditlog.logs.view-any` côté contrôleur.

## Services

- `Modules\AuditLog\Services\AuditService` — service minimal (`log(array $data)`, `getActivity()`, `getStats()`) opérant sur le modèle `Modules\AuditLog\Models\AuditLog` (table `audit_logs`, tenant-scopée). Non appelé par les contrôleurs API/Web du module lui-même (voir Particularités).
- `Modules\AuditLog\Http\Controllers\Api\AuditLogAiAssistController` — expose la guidance IA contextuelle pour ce module.

## Permissions RBAC

Le module possède bien un préfixe de permission dédié : `auditlog.logs.*` (`view-any`, `view`, `create`, `update`, `delete`), seedé dans `RolesAndPermissionsSeeder::MODULES['auditlog'] = ['logs']`. Le rôle `security-admin` reçoit explicitement `auditlog.logs.view-any` et `auditlog.logs.view`, en plus de `admin.audit.view` et `admin.security.manage`. Le rôle `admin` reçoit l'ensemble des permissions du module via `syncPermissions(array_merge($allPermissions, $adminPermissions))`.

Deux incohérences de nommage existent dans le code du module lui-même :
- `AuditLogPolicy` (non branchée sur les contrôleurs actuels) vérifie des permissions au format `auditlog.auditlog.*` (ex. `auditlog.auditlog.view-any`), qui ne sont **jamais seedées** — cette policy est donc inopérante en pratique.
- `AuditLogApiController` (celui réellement utilisé par l'API) vérifie `$request->user()->can('audit-log.view')` / `'audit-log.export'` — un troisième format, avec un tiret, lui aussi absent du seeder. Aucun rôle autre que `super-admin` (qui bypass tous les Gate via `Gate::before`) ne peut donc jamais passer ces contrôles pour l'instant.
- Seule la page Web (`AuditLogWebController`) utilise le format réellement seedé, `auditlog.logs.view-any`.

## Dépendances avec d'autres modules

- **Utilise** : `Modules\Core\Models\AuditLog` — à la fois `AuditLogApiController` et `AuditLogWebController` importent et interrogent directement ce modèle Core, pas le modèle propre du module AuditLog.
- **Utilisé par** : le trait `Modules\AuditLog\Traits\HasAuditLog` est adopté par plus de 200 modèles à travers la quasi-totalité des modules du périmètre (Security, AI, Accounting, CRM, HR, Achats, Logistics, Strategy, Reporting, Analytics, BI, Settings, Setup, Integration, Calendar, Validation, Core lui-même…) — c'est de loin le point d'intégration le plus large du module.

## Particularités du périmètre life-mdg-erp

- **Le trait `HasAuditLog`, bien qu'omniprésent, n'écrit jamais réellement de trace exploitable dans ce périmètre** : `bootHasAuditLog()` appelle `Log::channel('audit')->info(...)`, mais `config/logging.php` ne définit **aucun** canal nommé `audit` (seul un canal `gdpr-audit` existe). L'appel lève donc une `InvalidArgumentException` à chaque create/update/delete d'un modèle utilisant ce trait — exception silencieusement avalée par le `catch (\Throwable)` du trait (« Never let audit logging break normal operations »). En pratique, l'écriture d'audit réellement effective dans l'application vient du trait **Core** `RecordsActivity` (table `core_audit_logs`), pas de `HasAuditLog`.
- **Le modèle et la table propres au module (`Modules\AuditLog\Models\AuditLog`, table `audit_logs`) ne sont lus par aucun contrôleur du module** : l'API et la page Web interrogent toutes deux `Modules\Core\Models\AuditLog` (`core_audit_logs`). Le service `Modules\AuditLog\Services\AuditService` qui sait écrire dans `audit_logs` existe mais n'est appelé nulle part dans les contrôleurs du module — la table `audit_logs` du module AuditLog est donc, dans ce périmètre, vestigiale.
