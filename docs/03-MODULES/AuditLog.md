# AuditLog

## Rôle

`Modules/AuditLog` expose la consultation, les statistiques et l'export du journal d'audit de l'ERP : qui a fait quoi, sur quelle entité, à quel moment. Il fournit une page web (Inertia) et une API REST de lecture, ainsi qu'un trait (`HasAuditLog`) massivement adopté par les modèles métier du reste de l'application. C'est un module de la « socle CORE / système », mais son fonctionnement réel s'appuie entièrement sur les données produites par le module Core plutôt que sur une table qui lui serait propre — voir « Particularités » et le Chantier 32.4 ci-dessous.

## Modèles clés

Le module **n'a plus de modèle Eloquent propre** depuis le Chantier 32.4 (voir ci-dessous). Il dépend entièrement du modèle `Modules\Core\Models\AuditLog` (table `core_audit_logs`) pour toutes ses lectures (API et page web) et de son propre trait `HasAuditLog` pour l'écriture massivement adoptée décrite plus bas.

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

## Contrôleurs

`Modules/AuditLog/app/Http/Controllers/` (3 fichiers) :

| Contrôleur | Rôle |
|---|---|
| `Api\AuditLogApiController` | `index`/`stats`/`export`/`show` — lit `Modules\Core\Models\AuditLog` (`core_audit_logs`), scopé `company_id` |
| `Api\AuditLogAiAssistController` | Guidance IA contextuelle (AI Assisted First), 3 actions réelles enregistrées côté `Modules\AI` : `view_audit_log`, `export_audit`, `filter_events` |
| `Web\AuditLogWebController` | Rend `AuditLog/Index` (voir Vues), même scope `company_id` |

Une page Vue dupliquée, masquée par la vraie page racine `resources/js/Pages/AuditLog/Index.vue`, avait été **supprimée** au Chantier 8.x (voir Vues).

## Vues (Vue/Inertia)

Une seule page réellement servie : `resources/js/Pages/AuditLog/Index.vue` (racine du dépôt, pas sous `Modules/AuditLog/`) — la résolution Inertia (`resources/js/app.js`) essaie d'abord `./Pages/AuditLog/Index.vue` avant de retomber sur une éventuelle copie module, donc la page racine gagne systématiquement. Rendue par `AuditLogWebController::index()` (`GET /audit/logs`, `middleware(['auth', 'module:AuditLog'])` + `can:auditlog.logs.view-any`). **Chantier 32.4** : cette page n'appelait jamais l'assistant IA contextuel malgré `AuditLogAiAssistController` déjà réel, correctement autorisé et testé en direct via HTTP — câblée pour de vrai (`useAiAssistant('AuditLog', 'view_audit_log')` + `<AIAssistantPanel>`).

## Services

- `Modules\AuditLog\Http\Controllers\Api\AuditLogAiAssistController` — expose la guidance IA contextuelle pour ce module (délègue à `Modules\AI\Services\AiContextualAssistantService`).
- ~~`Modules\AuditLog\Services\AuditService`~~ — **supprimé au Chantier 32.4** (voir ci-dessous). Le service du même nom réellement utilisé par le reste de l'application est `Modules\Core\Services\AuditService`, propriété du module Core.

## Permissions RBAC

Le module possède bien un préfixe de permission dédié : `auditlog.logs.*` (`view-any`, `view`, `create`, `update`, `delete`), seedé dans `RolesAndPermissionsSeeder::MODULES['auditlog'] = ['logs']`, plus `auditlog.logs.export` (verbe non-standard, bloc `AUDITLOG_EXTRA_PERMISSIONS`). Le rôle `security-admin` reçoit explicitement `auditlog.logs.view-any` et `auditlog.logs.view`, en plus de `admin.audit.view` et `admin.security.manage`. Le rôle `admin` reçoit l'ensemble des permissions du module via `syncPermissions(array_merge($allPermissions, $adminPermissions))`.

Les incohérences de nommage documentées auparavant sont résolues : `AuditLogApiController` (celui réellement utilisé par l'API) vérifie `$request->user()->can('auditlog.logs.view')` / `'auditlog.logs.export'` — le format réellement seedé, aligné sur celui déjà utilisé par la page Web (`auditlog.logs.view-any`). L'ancienne `AuditLogPolicy` (qui vérifiait un troisième format jamais seedé, `auditlog.auditlog.*`, et n'était de toute façon jamais enregistrée ni appelée) avait déjà été supprimée avant ce chantier — les deux contrôleurs gatent directement via `abort_unless($user->can(...))`, sans Policy Eloquent. **Chantier 32.4** : le module n'avait jamais eu de test confirmant le refus (403) pour un utilisateur sans `auditlog.logs.view`/`.export`/`.view-any` — seul le refus « non authentifié » (401) était couvert — désormais verrouillé empiriquement pour les 3 routes (API index/export + web index).

## Dépendances avec d'autres modules

- **Utilise** : `Modules\Core\Models\AuditLog` — à la fois `AuditLogApiController` et `AuditLogWebController` importent et interrogent directement ce modèle Core, pas un modèle propre au module AuditLog (qui n'en a plus).
- **Utilisé par** : le trait `Modules\AuditLog\Traits\HasAuditLog` est adopté par plus de 200 modèles à travers la quasi-totalité des modules du périmètre (Security, AI, Accounting, CRM, HR, Achats, Logistics, Strategy, Reporting, Analytics, BI, Settings, Setup, Integration, Calendar, Validation, Core lui-même…) — c'est de loin le point d'intégration le plus large du module.

## Particularités du périmètre life-mdg-erp

- **Fuite cross-tenant corrigée en amont de ce chantier (Chantier 8.5-light)** : `Modules\Core\Models\AuditLog` (table `core_audit_logs`), le journal d'audit réel et alimenté en continu (54+ modèles via `RecordsActivity`, 23 via `AuditableActions`, `AuditAuthListener` sur chaque événement d'authentification), n'avait **aucune colonne de tenant/société** — et ni `AuditLogApiController` ni `AuditLogWebController` ne filtraient par tenant. Corrigé par une migration ajoutant `company_id` (nullable, rétro-rempli), tous les écrivains mis à jour, filtre ajouté aux deux contrôleurs. Re-confirmé empiriquement (login réel de 2 sociétés différentes, lecture via la vraie route HTTP) à la fois par le Chantier 19 et par le Chantier 32.4.
- **Le canal de log `audit` (consommé par `HasAuditLog::bootHasAuditLog()`) est bien défini et fonctionnel** — une note précédente de cette documentation affirmait le contraire (« `config/logging.php` ne définit aucun canal nommé `audit`... l'appel lève une `InvalidArgumentException` »), ce qui était vrai à un moment antérieur de la session mais a depuis été corrigé par un chantier de sécurité (`config/logging.php`, commit `bc6ad03`/`996190e`, hors périmètre AuditLog) sans que cette page ne soit jamais mise à jour en conséquence — une des raisons d'être du Chantier 32.4. **Reconfirmé empiriquement** (création réelle d'un `Modules\Settings\Models\Setting`, vérification du contenu de `storage/logs/audit-<date>.log`) : `HasAuditLog` écrit bien une entrée `[created]`/`[updated]`/`[deleted]` réelle pour chacun des 200+ modèles qui l'utilisent — dans un **fichier** (`storage/logs/audit.log`), pas dans une table interrogeable par Eloquent. C'est donc un journal complémentaire à `core_audit_logs`, pas un doublon : un modèle qui n'utilise que `HasAuditLog` (ex. `Modules\Calendar\Models\Calendar`) n'a **aucune** trace dans `core_audit_logs`, seulement dans le fichier.
- **Chantier 32.4 — audit approfondi en 14 couches, résolution définitive de la question centrale « deux systèmes d'audit »** : ce module portait un troisième système, `Modules\AuditLog\Models\AuditLog` (table `audit_logs`, colonne `tenant_id` réelle) — confirmé empiriquement, pas seulement par grep, **réellement mort et supprimé** dans ce chantier (modèle, service, table, et son unique écrivain réel).
  - **Aucun lecteur réel, confirmé** : ni l'API ni la page Web du module (qui interrogent toutes deux `Modules\Core\Models\AuditLog`) ne l'ont jamais lu.
  - **Un seul écrivain réel en production, et un vrai bug dedans** : `App\Listeners\AuthEventSubscriber` (racine `app/`, hors du module), abonné à `Login`/`Logout`/`Failed`/`Lockout` via `Event::subscribe()` dans `AppServiceProvider::boot()`. Confirmé empiriquement (déclenchement réel d'un événement `Login`) qu'il écrivait bien une ligne à chaque authentification — mais avec `tenant_id` **codé en dur à `0`**, jamais la vraie société de l'utilisateur, dupliquant exactement ce que `Modules\Core\Listeners\AuditAuthListener` fait déjà correctement (vrai `company_id`) pour les 4 mêmes événements. Un écrit en base gaspillé à chaque connexion/déconnexion/échec de toute l'application, alimentant une table que rien ne lit, avec un bug de cloisonnement intégré. **Supprimé** avec sa table (nouvelle migration `2026_10_11_000001_drop_dead_audit_logs_table.php`) et sa désinscription (`AppServiceProvider.php`).
  - **8 autres sites d'écriture confirmés inatteignables** dans 5 services de `Modules/Helpdesk` (`SentimentAnalysisService`, `AiResponseService`, `PredictiveEscalationService`, `SatisfactionPredictionService`, `AgentPerformanceAnalyticsService`) — zéro contrôleur/route/job n'atteint jamais les méthodes qui les contiennent (confirmé par grep exhaustif des appelants). Laissés tels quels (hors périmètre de ce module) et signalés dans `CLAUDE.md` pour un futur chantier ciblé Helpdesk — ils utilisaient de toute façon des noms de champs (`model_type`/`model_id`/`changes`) jamais présents dans `$fillable`, un second bug indépendant de leur inaccessibilité.
  - **Le trait `HasAuditLog::auditLogs()`** (relation `MorphMany` vers le modèle supprimé, `auditable_type`/`auditable_id` — des colonnes qui n'ont d'ailleurs jamais existé sur la vraie table) a été retiré du trait plutôt que repointé vers `Modules\Core\Models\AuditLog` : ce repointage a été étudié et rejeté, puisque `RecordsActivity`/`AuditableActions` (les vrais écrivains de Core) ne sont pas utilisés par tous les modèles consommant `HasAuditLog` — un repointage aurait silencieusement renvoyé un résultat vide pour ces modèles-là, un correctif trompeur plutôt qu'un vrai. `bootHasAuditLog()` (l'écriture réelle vers le fichier) n'a pas été touché.
  - **Verrouillé empiriquement** par `Modules/AuditLog/tests/Feature/Chantier32AuditLogDeepAuditTest.php` (16 tests HTTP réels, sans mock) : disparition confirmée de la table/des classes, non-régression du vrai écrivain Core, non-régression du trait `HasAuditLog`, RBAC 403 (jusqu'ici non couvert), page web réellement servie avec l'assistant IA câblé, guidance IA de repli réellement non vide (fr+en) pour les 3 actions réelles du module, colonnes réelles de `core_audit_logs`, et un test de performance/N+1 sous 500 lignes réalistes.
