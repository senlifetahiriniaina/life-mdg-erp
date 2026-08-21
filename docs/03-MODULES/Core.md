# Core

## Rôle

`Modules/Core` est le socle technique de life-mdg-erp : authentification (Sanctum), gestion multi-tenant (portail superadmin + provisioning), gestion des modules activés/désactivés, import de données assisté par IA (pipeline CSV/XLSX, mapping IA, exécution), conformité RGPD (SAR, consentement, anonymisation), synchronisation offline, sandbox de démonstration, gestion des secrets applicatifs et une large partie du hardening sécurité (CSRF, XSS, DDoS, headers, sessions). C'est le module le plus volumineux du périmètre et celui dont dépendent le plus grand nombre de fonctionnalités transverses (dashboard multi-module, export RGPD cross-module).

**Chantier 32.1 (audit approfondi 14 couches)** a supprimé deux moteurs génériques confirmés morts (voir Particularités) et corrigé une dizaine de bugs réels — voir `CLAUDE.md` pour le détail complet ; cette page reflète l'état du module **après** ce chantier.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Tenant` | `tenants` | Entreprise cliente provisionnée (portail superadmin, distinct du `company_id` utilisé partout ailleurs dans l'app pour le cloisonnement des données métier) |
| `TenantUser` / `TenantModule` / `TenantInvitation` | `tenant_users` / `tenant_modules` / `tenant_invitations` | Rattachement utilisateur ↔ tenant (seule source légitime de résolution du tenant courant — jamais un header/paramètre client, voir Particularités), modules activés par tenant, invitations |
| `TenantAuditLog` | `tenant_audit_log` | Journal d'audit du portail superadmin (provisionnement/suspension/upgrade/purge) — distinct du journal d'audit applicatif général (voir `AuditLog` ci-dessous) |
| `ApiKey` | `core_api_keys` | Clés API internes au Core (distinctes du modèle `Modules\API\Models\ApiKey`, voir Particularités) |
| `AuditLog` | `core_audit_logs` | Journal d'audit **propre au Core**, alimenté par le trait `RecordsActivity` |
| `CustomField` / `CustomFieldValue` | — | Champs personnalisés attachables à n'importe quelle entité (`HasCustomFields` trait) — fonctionnalité réelle et testée, mais **non adoptée par aucun modèle métier à ce jour** (voir Particularités) |
| `GdprConsent` / `DataRequest` | — | Consentements RGPD et demandes SAR (Subject Access Request) |
| `ImportJob` / `ImportRow` | `core_import_jobs` / `core_import_rows` | Jobs d'import de données (pipeline CSV/XLSX assisté par IA — voir Particularités pour les bugs réels trouvés et corrigés au Chantier 32.1) |
| `Sandbox` | `sandboxes` | Environnements de démonstration à expiration automatique |
| `Secret` / `SecretAccessGrant` / `SecretAccessLog` / `SecretRotationPolicy` | — | Coffre-fort de secrets applicatifs avec rotation et contrôle d'accès |
| `SessionEnhanced` / `SessionSecurityEvent` | — | Sessions enrichies et événements de sécurité liés aux sessions |
| `CsrfToken` / `CspViolation` / `DDoSIncident` / `RateLimitMetrics` | — | Télémétrie sécurité (CSRF, CSP, DDoS, rate limiting) |
| `TenantExchange` / `TenantExchangeHistory` | `core_tenant_exchanges` / `core_tenant_exchange_history` | Échange de données inter-tenants |

## Endpoints principaux

Tous préfixés `/api/v1/` (voir `Modules/Core/routes/api.php` et `routes/secrets.php`) :

- **Tenants** : `POST tenants/register` (public), `GET tenants/check-slug/{slug}`, `GET tenants/me` ; gestion complète (`GET/PUT/DELETE tenants/{id}`, suspend/activate/reprovision) réservée au rôle `super-admin`
- **Auth** : `POST auth/register`, `POST auth/login`, `POST auth/logout`, `GET auth/me`, `POST auth/refresh`
- **Modules** : `GET modules`, `POST modules/{module}/enable|disable`, `PUT modules/{module}/settings`
- **Sync offline** : `POST sync/push`, `GET sync/pull` — vrai consommateur front confirmé au Chantier 32.1 : `resources/js/Components/UI/{OfflineIndicator,OfflineStatusPill}.vue` (montés dans `AppLayout.vue`) via `resources/js/stores/sync.js`
- **AI Assistant (implémentation Core, distincte du module AI)** : `POST ai/ask`, `POST ai/analyze`, `POST ai/generate-document`
- **Notifications / Push tokens / Recherche globale** : `GET notifications`, `POST push-tokens`, `GET search`
- **RGPD** : `GET account/export`, `DELETE account`, `GET/POST core/gdpr/*` (consentements, SAR, dashboard réservé `role:admin,manager`). **Chantier 32.1** : un ancien groupe `gdpr/*` (7 routes, hérité d'un refactor de `GdprController` jamais nettoyé) appelait des méthodes qui n'ont jamais existé sur le contrôleur (`getSARStatus`/`exportPersonalData`/`complianceStatus`/`listRequests`/`requestSAR`/`deleteAccount`/`deletePersonalData`) — un 500 garanti sur 6 de ses 7 routes, zéro appelant réel ; supprimé au profit du seul groupe réel `core/gdpr/*`.
- **Audit** (implémentation Core) : `GET core/audit-log`, `GET core/audit-logs`, `core/audit-logs/stats`, réservés `role:admin,manager`
- **Échanges inter-tenants** : `core/exchanges/*`
- **Import de données** : `POST import/upload`, `PUT import/jobs/{job}/mapping`, `POST import/jobs/{job}/execute|rollback`, `GET import/jobs`, `GET import/jobs/{job}`, `GET import/jobs/{job}/rows`. Vrai consommateur front confirmé au Chantier 32.1 : `resources/js/Pages/Import/Index.vue` (une documentation antérieure de ce fichier affirmait à tort qu'aucun contrôleur ne servait ce schéma d'URL — c'est faux, `ImportController` le sert intégralement).
- **Realtime** : `GET realtime/health` (le `GET realtime/subscribe` — un endpoint SSE explicitement documenté comme « demo » dans son propre code, zéro appelant réel, cassé même en théorie via la colonne fantôme `users.tenant_id` — a été supprimé au Chantier 32.1 ; le temps réel réel de l'app passe par Reverb + Echo)
- **Secrets** (`routes/secrets.php`, throttle dédié `secrets`) : `GET/POST v1/secrets`, `PUT {name}/rotate`, `DELETE {name}`, gestion des accès
- **Superadmin** (`core/superadmin/*`, `role:super-admin`) : portail multi-tenant complet — CRUD tenant, suspend/reactivate/upgrade-plan/purge/export RGPD, stats globales, audit log. Vérifié empiriquement de bout en bout au Chantier 32.1 (provision→suspend→reactivate→upgrade-plan→export→purge, toutes via la vraie route HTTP).
- **Onboarding** (`core/onboarding/*`) : wizard tenant-scopé (distinct du portail superadmin). **Chantier 32.1** : `resolveTenant()` acceptait un `?tenant_id=`/`X-Tenant-Id` client AVANT même de consulter le vrai pivot `TenantUser` — une IDOR cross-tenant réelle (n'importe quel utilisateur authentifié pouvait lire, et surtout **écrire** — `onboarding/step`, `onboarding/skip` — la progression d'onboarding d'un autre tenant) ; corrigé en supprimant entièrement le repli client.
- **Smart Defaults / mode expert** (`core/smart-defaults`, `core/defaults`, `PUT core/simple-mode`) : pré-remplissage devise/TVA/fuseau par pays, bascule Simplicity First.
- **CSP** (`core/csp/report` public/non-authentifié, throttle `webhook`, plus `index`/`show`/`resolve`/`stats` authentifiés) : réception des violations `report-uri` du navigateur.
- **Sandboxes** (`core/sandboxes/*`, `role:super-admin`) : `resources/js/Pages/Admin/Sandboxes/Index.vue` est un vrai appelant. **Chantier 32.1** : `index()` résolvait implicitement le tenant parent via la colonne fantôme `users.tenant_id` (toujours nulle) — la page réelle, qui n'envoie jamais de filtre `parent_tenant_id`, a donc **toujours affiché une liste vide** malgré des sandboxes réellement existants ; corrigé en listant globalement (route déjà `role:super-admin`-only) quand aucun filtre explicite n'est fourni.

## Contrôleurs

`Modules/Core/app/Http/Controllers/Api/` (24 fichiers) :

| Contrôleur | Rôle |
|---|---|
| `AuthController` / `AccountController` | Authentification Sanctum, cycle de vie du compte (export RGPD + suppression/anonymisation — voir Particularités pour les bugs de dispatch de job corrigés au Chantier 32.1) |
| `TenantController` / `TenantRegistrationController` / `TenantExchangeController` | Cycle de vie tenant, inscription publique, échanges inter-tenants |
| `ModuleController` | Activation/désactivation des modules par tenant |
| `SyncController` | Synchronisation offline (push/pull) — vrai consommateur confirmé au Chantier 32.1 |
| `AIAssistantController` | `POST ai/ask\|analyze\|generate-document` — implémentation Core de l'IA générique |
| `NotificationController` / `PushTokenController` / `GlobalSearchController` / `HelpController` | Notifications, tokens push, recherche globale, aide contextuelle |
| `GdprController` / `ConsentController` / `ConsentWithdrawalController` | RGPD : export SAR, consentements |
| `AuditLogController` | `core/audit-log(s)*` — lit `Modules\Core\Models\AuditLog` (voir Particularités) |
| `ImportController` | Pipeline d'import de données — réel et consommé, voir Endpoints |
| `RealtimeController` | `health()` uniquement depuis Chantier 32.1 (`subscribe()`, demo mort, supprimé) |
| `SandboxController` | Environnements de démo (`role:super-admin`) |
| `SecretsController` | Coffre-fort de secrets (`routes/secrets.php`) |
| `SmartDefaultsController` | Pré-remplissage pays/devise, mode expert |
| `SuperadminController` | Portail multi-tenant complet — vérifié empiriquement de bout en bout au Chantier 32.1, y compris la faille `resolveTenant()` corrigée |
| `CustomFieldController` | Champs personnalisés attachables (`HasCustomFields`) — API fonctionnelle, vérifiée empiriquement au Chantier 32.1, mais aucun modèle métier n'utilise le trait à ce jour (voir Particularités) |
| `CspViolationController` | Réception des rapports CSP du navigateur |

**Chantier 32.1** : `WorkflowController`/`ApprovalController` (et les services/modèles génériques qu'ils exposaient) ont été supprimés — voir Particularités pour le détail complet de cette suppression. `MobileAuthController` n'existe plus dans ce dépôt depuis le Chantier 9 (déjà documenté dans `CLAUDE.md`) — une version antérieure de cette page le listait encore par erreur.

## Vues (Vue/Inertia)

Core est **délibérément sans interface propre** (principe « API First » — Core expose ses fonctionnalités en API pure, l'UI vit dans les modules consommateurs ou au niveau racine de `resources/js/Pages/`) : `Modules/Core/routes/web.php` est un fichier intentionnellement vide. Aucune page Vue ne vit sous `Modules/Core/resources/js/Pages/` — les vraies pages consommatrices des API Core (import, sandboxes, échanges inter-tenants, offline sync) vivent toutes à la racine `resources/js/Pages/`.

`Modules/Core/resources/js/Components/` contient 5 composants (`Approval/ApprovalCard.vue`, `Auth/AuthForm.vue`, `CustomField/CustomFieldEditor.vue`, `GDPR/GdprConsent.vue`, `Workflow/WorkflowVisualizer.vue`) dont **aucun n'est monté par une page réelle** (confirmé par grep au Chantier 32.1) — `ApprovalCard.vue`/`WorkflowVisualizer.vue` ont été supprimés avec le moteur mort qu'ils appelaient ; les 3 autres (`AuthForm`, `CustomFieldEditor`, `GdprConsent`) restent, puisque leurs API sous-jacentes sont réelles et fonctionnelles (juste jamais montées dans une page — un gap de découvrabilité, pas du code mort au sens where rien ne marche).

## Services

- `TenantManagerService` / `TenantProvisioningService` / `TenantRegistrationService` / `TenantExchangeService` — cycle de vie tenant (provisioning, suspension, purge RGPD, échange de données)
- `AuditService` — écrit dans `core_audit_logs` (voir section Particularités)
- `GdprService` — export SAR, anonymisation, suivi des demandes (implémentation synchrone, réellement branchée sur `processRequest()`)
- `ImportExecutorService` / `DataExtractionService` / `AiMappingService` — pipeline d'import assisté : exécution des lignes mappées, extraction CSV/XLSX/PDF/PNG/JPG, mapping IA des colonnes. **Chantier 32.1** : `AiMappingService`/`DataExtractionService` avaient zéro appelant malgré leur propre docblock/nom de classe promettant le contraire — branchés dans `ExtractAndMapImportJob` pour de vrai (CSV/XLSX ; PDF/PNG/JPG restent un gap honnêtement documenté, voir Particularités).
- `ModuleManager` — active/désactive les 27 modules par tenant (alias conteneur `erp.modules`)
- `SandboxService` — création et expiration automatique des environnements de démo (`ExpireSandboxesCommand`) ; `listAll()` ajouté au Chantier 32.1 (voir Endpoints)
- `SecretsService` / `SecretAccessControl` / `SecretRotationManager` — coffre-fort de secrets, contrôle d'accès et rotation planifiée (`RotateDueSecretsCommand`)
- `SmartDefaultsService` — pré-remplissage devise/TVA/fuseau horaire par pays (Africa First / Asia First)
- `SimpleModeService` — bascule « mode expert » (Simplicity First)
- Sécurité applicative : `CsrfTokenService`, `XssPreventionService`, `HtmlPurifierService`, `OutputEncodingService`, `DDoSDetectionService`, `RateLimitService`, `CspViolationLogger`, `SessionFingerprint`, `SessionSecurityService`, `KeyManagementService`, `EncryptionService`, `MFAService`, `RiskAssessmentService` — tous réellement présents sous `Modules/Core/app/Services/` (voir Particularités pour la correction d'une confusion documentaire antérieure sur ce point)
- `Services\AI\AIService` (+ `AnthropicProvider`, `OpenAIProvider`, `DeepSeekProvider`) — service IA central, provider-agnostic via `AIProviderContract` (`chat()`, `embed()`, `analyze()`, `getProviderName()`, `isConfigured()`). Le provider actif est résolu par `AI_DEFAULT_PROVIDER` (`config('ai.default_provider')`, `anthropic` par défaut) ou par un override `module_providers` ciblé par module. Exposé via `POST /api/v1/ai/ask|analyze|generate-document` et consommé par `Modules\AI\Services\AiContextualAssistantService`.
- `Dashboard\RoleBasedDashboardService` — agrège des données CRM, Inventory, Accounting, HR pour le tableau de bord (voir Dépendances)
- `ParticipantNotificationService` — voir `CLAUDE.md` Chantier 20/31 pour le détail complet (notification cross-module réelle, déjà auditée en profondeur)

## Permissions RBAC

Core n'a pas d'entrée dans le tableau générique `RolesAndPermissionsSeeder::MODULES` (pas de `core.customfield.*` produit par la boucle standard `MODULES`×`ACTIONS`), mais possède depuis Chantier 8.3 un bloc dédié **`CORE_EXTRA_PERMISSIONS`** (8 permissions : `core.customfield.{view-any,view,create,update,delete,approve,export,archive}`) — nécessaire car `CustomFieldPolicy` était déjà écrite et déjà appelée via `$this->authorize()` dans `CustomFieldController`, mais **jamais enregistrée auprès du Gate** avant Chantier 8.3 (`CoreServiceProvider::registerPolicies()` — les policies namespacées `Modules\*` ne s'auto-découvrent pas comme celles d'`App\Policies`). **Chantier 32.1** : le bloc sœur `core.approvalworkflow.*` (8 permissions) a été retiré avec la suppression du moteur d'approbation Core confirmé mort — voir Particularités.

Le reste du contrôle d'accès Core repose sur :
- des permissions d'administration globales (`ADMIN_PERMISSIONS`) : `admin.modules.view`, `admin.modules.toggle`, `admin.security.manage`, `admin.audit.view`, `admin.users.*`, `admin.roles.*`, attribuées au rôle `admin` (toutes) et partiellement au rôle `tenant-admin` (modules/utilisateurs/rôles) ;
- des middlewares de rôle directs sur les routes (`role:super-admin` pour la gestion des tenants, le portail `core/superadmin/*` et `core/sandboxes/*`, `role:admin,manager` pour l'audit et les demandes RGPD) plutôt que sur des permissions Spatie nommées.

## Dépendances avec d'autres modules

- **Utilisé par** : tous les modules dépendent implicitement de Core pour l'authentification (Sanctum) et le multi-tenant.
- **Utilise** : `Modules\Shared\Jobs\BaseAsyncJob` (classe de base des jobs d'import/anonymisation/export SAR — voir Particularités pour un gap sévère trouvé au Chantier 32.1) ; `RoleBasedDashboardService` lit directement `Modules\Crm\Models\{Contact,Lead,Opportunity}`, `Modules\Inventory\Models\Product`, `Modules\Accounting\Models\Invoice`, `Modules\Hr\Models\Employee` pour construire le tableau de bord par rôle ; `SarExportJob`/`AnonymizeUserJob`/`ImportExecutorService` lisent/écrivent `Modules\CRM\Models\{Contact,Lead}`, `Modules\HR\Models\Employee`, `Modules\Helpdesk\Models\Ticket`, `Modules\Inventory\Models\{Product,Supplier}`, `Modules\Accounting\Models\Invoice` pour les exports/purges RGPD et l'import de données cross-module.
- Core **n'importe pas** `Modules\AI\Services\AiContextualAssistantService` — la dépendance va dans l'autre sens : le module `AI` importe `Modules\Core\Services\AI\AIService`.

## Particularités du périmètre life-mdg-erp

- **Chantier 32.1 — deux moteurs génériques confirmés morts, supprimés (pas juste documentés)** : `WorkflowController`/`Services\WorkflowService`/`Models\{WorkflowDefinition,WorkflowState}` (une machine à états générique, `core/workflows/*`) et `ApprovalController`/`Services\ApprovalService`/`Models\{ApprovalWorkflow,ApprovalInstance,ApprovalDecision}`/`ApprovalWorkflowPolicy` (un moteur d'approbation multi-niveaux générique, `core/approvals/*`, déjà signalé sans producteur au Chantier 31) ont tous deux été supprimés (contrôleurs, services, modèles, factories, routes, policy, permissions, tables via une migration dédiée, plus le seeder `WorkflowDefinitionsSeeder` et les 2 composants Vue orphelins `ApprovalCard.vue`/`WorkflowVisualizer.vue`). Confirmé par exécution empirique, pas seulement par grep : zéro appelant réel nulle part dans l'app hors de leur propre code, zéro page Vue montée les consommant, zéro test au-delà d'assertions RBAC superficielles. Les vraies chaînes d'approbation de cette app passent par `Modules\Validation` (Achats/Accounting/HR — voir Chantier 31) ; ces deux moteurs Core étaient des doublons morts du même schéma déjà trouvé et supprimé plusieurs fois cette session (`TerritoryManagementController`, `wh_*`/`lgx_*`, le bloc « Legacy Workflow Engine » de `Modules/Workflow`).
- **Chantier 32.1 — un vrai gap système trouvé en corrigeant le pipeline d'import : `BaseAsyncJob` (`Modules\Shared\Jobs`) n'a de méthode `handle()` nulle part dans sa chaîne d'héritage**, et 30 classes de jobs à travers l'app (Core, Accounting, et probablement d'autres modules) en héritent sans jamais définir leur propre `handle()`. Confirmé empiriquement (`php artisan tinker` avec le vrai driver `QUEUE_CONNECTION=sync` de cette app) qu'un dispatch réel de n'importe lequel de ces jobs échoue avec `Call to undefined method ...::__invoke()`. Corrigé **localement** sur les 5 jobs de Core (`ExtractAndMapImportJob`, `ExecuteImportJob`, `SarExportJob`, `AnonymizeUserJob` — ce dernier avait en plus un second bug fatal indépendant, un `parent::__construct(0)` invalide) plutôt que sur la classe de base partagée, pour ne pas modifier silencieusement le comportement de 28 autres classes de jobs hors du périmètre de ce chantier (Accounting notamment) sans les avoir auditées. **Reste un gap système réel pour un futur chantier dédié**, potentiellement le bug le plus sévère (en surface d'impact) trouvé cette session : toute fonctionnalité GDPR/export/anonymisation de ce module a probablement toujours échoué silencieusement en production avant ce correctif, masquée par les tests existants qui utilisaient tous `Queue::fake()` (jamais une exécution réelle).
- **Chantier 32.1 — `ImportJob::$fillable` déclarait `processed_rows`/`failed_rows`/`error_message`, colonnes qui n'ont jamais existé sur `core_import_jobs`** (les vraies colonnes sont `processed`/`failed`/`errors`) — `ImportExecutorService::executeImport()` échouait donc de façon garantie sur la toute première ligne traitée. Corrigé en remappant sur les vraies colonnes ; les clés JSON exposées par `ImportJobResource` (`processed_rows`/`failed_rows`/`error_message`) restent inchangées côté contrat frontend, seule la lecture côté modèle a changé. Un nouveau champ réel `ai_suggestions` (migration additive) a été ajouté pour porter la sortie d'`AiMappingService`, que le frontend (`resources/js/Pages/Import/Index.vue`) attendait déjà.
- **Chantier 32.1 — les factories `Tenant*` (hors `TenantFactory` lui-même) étaient toutes du scaffold cassé** : `TenantUserFactory`, `TenantAuditLogFactory`, `TenantInvitationFactory`, `TenantExchangeFactory`, `TenantExchangeHistoryFactory`, `TenantModuleFactory` généraient des `fake()->word()` sur des colonnes FK/date/booléennes (ex. `joined_at` recevait un mot latin aléatoire, fatal sur le cast `datetime` dès la première utilisation réelle) — toutes réécrites pour correspondre aux vrais `$fillable`/`$casts` de leurs modèles.
- **`CoreServiceProvider::register()` référençait 10 services de sécurité qui n'existent réellement nulle part dans ce périmètre trimmé** (corrigé au Chantier 32.1, une précision par rapport à une version antérieure de cette page qui en comptait 14 par erreur — `KeyManagementService`, `EncryptionService`, `MFAService`, `RiskAssessmentService`, `CsrfTokenService` existent bel et bien et ont été conservés) : `SearchableEncryption`, `PasswordlessService`, `IPWhitelistService`, `GeolocationService`, `SuspiciousActivityService`, `IntrusionDetectionService`, `SecurityAutomationService`, `ComplianceFrameworkService`, `IncidentResponseService`, `SecurityTestingService`. Ces 10 bindings ont été supprimés (jamais construits avant résolution, donc inertes mais une vraie mine — un `app('passwordless')` ou une injection par type aurait fatalement échoué) plutôt que les 10 classes correspondantes inventées sans aucune spécification réelle nulle part dans l'app.
- **Deux journaux d'audit parallèles** : Core possède son propre modèle `Modules\Core\Models\AuditLog` (table `core_audit_logs`), alimenté par le trait `Modules\Core\Traits\RecordsActivity` (utilisé sur des dizaines de modèles métier, ex. `CRM\Account`, `Achats\PurchaseOrder`, `Logistics\Shipment`) et par `Modules\Core\Services\AuditService`. Le module dédié `AuditLog` a son propre modèle `audit_logs`, mais son contrôleur API (`AuditLogApiController`) interroge en réalité le modèle **Core** `core_audit_logs`, pas le sien — voir `docs/03-MODULES/AuditLog.md` pour le détail.
- **Une seule pile IA, deux points d'entrée** : Core expose `Services\AI\AIService` (`AIProviderContract` : `AnthropicProvider`/`OpenAIProvider`/`DeepSeekProvider`) via `POST /api/v1/ai/ask|analyze|generate-document`. Le module `AI` (`AiContextualAssistantService::getGuidance()`) délègue à cette même instance (`AIService::forModule('AI')`) plutôt que d'avoir sa propre logique d'appel HTTP.
- **`CustomField`/`HasCustomFields` : fonctionnalité réelle, testée, RBAC-correcte, mais adoptée par zéro modèle métier à ce jour** — confirmé au Chantier 32.1 (`grep` du trait `HasCustomFields` dans tout le dépôt : zéro résultat en dehors de sa propre définition) et vérifié empiriquement que le cycle de vie (créer un champ → sauvegarder une valeur sur une entité arbitraire → la relire) fonctionne réellement de bout en bout. Un gap de découvrabilité/adoption, pas du code mort à supprimer — contrairement au moteur d'approbation/workflow ci-dessus, celui-ci ne fait doublon avec rien d'existant et reste une extension générique légitime en attente d'un premier consommateur réel.
- **`ApiKey`** : Core possède son propre modèle `Modules\Core\Models\ApiKey` (`core_api_keys`), séparé du modèle `Modules\API\Models\ApiKey` géré par le module API — deux tables/gestion de clés API coexistent dans le périmètre.
- **Pipeline d'import — PDF/PNG/JPG restent un gap honnêtement documenté** : `DataExtractionService::extract()` sait dispatcher vers `extractFromPdf()`/`extractFromImage()`, mais ces deux méthodes retournent du texte brut/base64, pas des lignes structurées — brancher ceci sur `ImportRow` demanderait un vrai pipeline IA-vision-vers-lignes jamais spécifié nulle part, hors périmètre d'une correction de bug. CSV/XLSX (le besoin réel majoritaire) sont pleinement fonctionnels depuis Chantier 32.1.
