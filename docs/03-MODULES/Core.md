# Core

## Rôle

`Modules/Core` est le socle technique de life-mdg-erp : authentification (Sanctum), gestion multi-tenant, gestion des modules activés/désactivés, moteur de workflow générique et d'approbations, import de données assisté par IA (onboarding), conformité RGPD (SAR, consentement, anonymisation), synchronisation offline, sandbox de démonstration, gestion des secrets applicatifs et une large partie du hardening sécurité (CSRF, XSS, DDoS, headers, sessions). C'est le module le plus volumineux du périmètre et celui dont dépendent le plus grand nombre de fonctionnalités transverses (dashboard multi-module, export RGPD cross-module).

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Tenant` | `tenants` | Entreprise cliente (multi-tenant) |
| `TenantUser` / `TenantModule` / `TenantInvitation` | — | Rattachement utilisateur ↔ tenant, modules activés par tenant, invitations |
| `ApiKey` | `api_keys` | Clés API internes au Core (distinctes du modèle `Modules\API\Models\ApiKey`, voir ci-dessous) |
| `AuditLog` | `core_audit_logs` | Journal d'audit **propre au Core**, alimenté par le trait `RecordsActivity` |
| `ApprovalWorkflow` / `ApprovalInstance` / `ApprovalDecision` | — | Moteur d'approbation générique multi-niveaux, réutilisable par n'importe quel module |
| `WorkflowDefinition` / `WorkflowState` | — | Moteur de workflow à états génériques |
| `CustomField` / `CustomFieldValue` | — | Champs personnalisés attachables à n'importe quelle entité (`HasCustomFields` trait) |
| `GdprConsent` / `DataRequest` | — | Consentements RGPD et demandes SAR (Subject Access Request) |
| `ImportJob` / `ImportRow` | — | Jobs d'import de données (onboarding, mapping assisté par IA) |
| `Sandbox` | `sandboxes` | Environnements de démonstration à expiration automatique |
| `Secret` / `SecretAccessGrant` / `SecretAccessLog` / `SecretRotationPolicy` | — | Coffre-fort de secrets applicatifs avec rotation et contrôle d'accès |
| `SessionEnhanced` / `SessionSecurityEvent` | — | Sessions enrichies et événements de sécurité liés aux sessions |
| `CsrfToken` / `CspViolation` / `DDoSIncident` / `RateLimitMetrics` | — | Télémétrie sécurité (CSRF, CSP, DDoS, rate limiting) |
| `TenantExchange` / `TenantExchangeHistory` | — | Échange de données inter-tenants |

## Endpoints principaux

Tous préfixés `/api/v1/` (voir `Modules/Core/routes/api.php` et `routes/secrets.php`) :

- **Tenants** : `POST tenants/register` (public), `GET tenants/check-slug/{slug}`, `GET tenants/me` ; gestion complète (`GET/PUT/DELETE tenants/{id}`, suspend/activate/reprovision) réservée au rôle `super-admin`
- **Auth** : `POST auth/register`, `POST auth/login`, `POST auth/logout`, `GET auth/me`, `POST auth/refresh`
- **Modules** : `GET modules`, `POST modules/{module}/enable|disable`, `PUT modules/{module}/settings`
- **Sync offline** : `POST sync/push`, `GET sync/pull`
- **AI Assistant (implémentation Core, distincte du module AI)** : `POST ai/ask`, `POST ai/analyze`, `POST ai/generate-document`
- **Notifications / Push tokens / Recherche globale** : `GET notifications`, `POST push-tokens`, `GET search`
- **RGPD** : `GET account/export`, `DELETE account`, `GET/POST core/gdpr/*` (consentements, SAR, dashboard réservé `role:admin,manager`)
- **Audit** (implémentation Core) : `GET core/audit-log`, `GET core/audit-logs`, `core/audit-logs/stats`, réservés `role:admin,manager`
- **Workflow / Approbations** : `core/workflows/*`, `core/approvals/*`
- **Échanges inter-tenants** : `core/exchanges/*`
- **Import de données** : `POST import/upload`, `PUT import/jobs/{job}/mapping`, `POST import/jobs/{job}/execute|rollback`
- **Realtime** : `GET realtime/subscribe`, `GET realtime/health`
- **Secrets** (`routes/secrets.php`, throttle dédié `secrets`) : `GET/POST v1/secrets`, `PUT {name}/rotate`, `DELETE {name}`, gestion des accès

## Services

- `TenantManagerService` / `TenantProvisioningService` / `TenantRegistrationService` / `TenantExchangeService` — cycle de vie tenant (provisioning, suspension, purge RGPD, échange de données)
- `AuditService` — écrit dans `core_audit_logs` (voir section Particularités)
- `GdprService` — export SAR, anonymisation, suivi des demandes
- `ImportExecutorService` / `DataExtractionService` / `AiMappingService` — pipeline d'import assisté (Setup) : extraction, mapping IA des colonnes, exécution
- `WorkflowService` / `ApprovalService` — moteurs génériques de workflow et d'approbation multi-niveaux
- `ModuleManager` — active/désactive les 27 modules par tenant (alias conteneur `erp.modules`)
- `SandboxService` — création et expiration automatique des environnements de démo (`ExpireSandboxesCommand`)
- `SecretsService` / `SecretAccessControl` / `SecretRotationManager` — coffre-fort de secrets, contrôle d'accès et rotation planifiée (`RotateDueSecretsCommand`)
- `SmartDefaultsService` — pré-remplissage devise/TVA/fuseau horaire par pays (Africa First / Asia First)
- `SimpleModeService` — bascule « mode expert » (Simplicity First)
- Sécurité applicative : `CsrfTokenService`, `XssPreventionService`, `HtmlPurifierService`, `OutputEncodingService`, `SecurityHeadersService`, `DDoSDetectionService`, `RateLimitService`, `CspViolationLogger`, `SessionFingerprint`, `SessionSecurityService`
- `Services\AI\AIService` (+ `AnthropicProvider`, `OpenAIProvider`) — service IA **propre au Core**, distinct du module AI (voir Particularités)
- `Dashboard\RoleBasedDashboardService` — agrège des données CRM, Inventory, Accounting, HR pour le tableau de bord (voir Dépendances)

## Permissions RBAC

Core n'a **pas** de bloc de permissions granulaires dédié dans `RolesAndPermissionsSeeder::MODULES` (pas de préfixe `core.*.*`). Le contrôle d'accès Core repose sur :
- des permissions d'administration globales (`ADMIN_PERMISSIONS`) : `admin.modules.view`, `admin.modules.toggle`, `admin.security.manage`, `admin.audit.view`, `admin.users.*`, `admin.roles.*`, attribuées au rôle `admin` (toutes) et partiellement au rôle `tenant-admin` (modules/utilisateurs/rôles) ;
- des middlewares de rôle directs sur les routes (`role:super-admin` pour la gestion des tenants, `role:admin,manager` pour l'audit et les demandes RGPD) plutôt que sur des permissions Spatie nommées.

## Dépendances avec d'autres modules

- **Utilisé par** : tous les modules dépendent implicitement de Core pour l'authentification (Sanctum), le multi-tenant, et le moteur d'approbation/workflow générique.
- **Utilise** : `Modules\Shared\Jobs\BaseAsyncJob` (classe de base des jobs d'import/anonymisation) ; `RoleBasedDashboardService` lit directement `Modules\Crm\Models\{Contact,Lead,Opportunity}`, `Modules\Inventory\Models\Product`, `Modules\Accounting\Models\Invoice`, `Modules\Hr\Models\Employee` pour construire le tableau de bord par rôle ; `SarExportJob`/`AnonymizeUserJob` lisent `Modules\CRM\Models\{Contact,Lead}`, `Modules\HR\Models\Employee` et `Modules\Helpdesk\Models\Ticket` pour les exports/purges RGPD cross-module.
- Core **n'importe pas** `Modules\AI\Services\AiContextualAssistantService` : sa route `ai/ask` s'appuie sur sa propre pile IA interne (voir ci-dessous).

## Particularités du périmètre life-mdg-erp

- **Deux journaux d'audit parallèles** : Core possède son propre modèle `Modules\Core\Models\AuditLog` (table `core_audit_logs`), alimenté par le trait `Modules\Core\Traits\RecordsActivity` (utilisé sur des dizaines de modèles métier, ex. `CRM\Account`, `Achats\PurchaseOrder`, `Logistics\Shipment`) et par `Modules\Core\Services\AuditService`. Le module dédié `AuditLog` a son propre modèle `audit_logs`, mais son contrôleur API (`AuditLogApiController`) interroge en réalité le modèle **Core** `core_audit_logs`, pas le sien — voir `docs/03-MODULES/AuditLog.md` pour le détail.
- **Deux implémentations IA distinctes** : Core embarque sa propre pile (`Services\AI\AIService`, `AnthropicProvider`, `OpenAIProvider`, `AIProviderContract`) exposée via `POST /api/v1/ai/ask|analyze|generate-document`, complètement indépendante du module `AI` et de son `AiContextualAssistantService::getGuidance()` (utilisé par 33 autres fichiers du codebase pour le pattern « AI Assisted First »). Les deux ne partagent ni code ni configuration.
- **`CoreServiceProvider::register()` référence des services de sécurité qui n'existent pas dans ce périmètre trimmé** : `KeyManagementService`, `EncryptionService`, `SearchableEncryption`, `MFAService`, `PasswordlessService`, `RiskAssessmentService`, `IPWhitelistService`, `GeolocationService`, `SuspiciousActivityService`, `IntrusionDetectionService`, `SecurityAutomationService`, `ComplianceFrameworkService`, `IncidentResponseService`, `SecurityTestingService` sont enregistrés comme singletons (`$this->app->singleton(...)`) mais aucun fichier correspondant n'existe sous `Modules/Core/app/Services/`. Ces bindings ne provoquent pas d'erreur au démarrage (Laravel ne charge la classe qu'à la résolution), mais deux tests existants (`tests/Unit/MFAServiceTest.php`, `tests/Unit/RiskAssessmentServiceTest.php`) référencent ces classes absentes et échoueront à l'exécution. Un doublon partiel de `RateLimitService` existe aussi dans `Modules\Security\Services\RateLimitService`, avec une implémentation entièrement différente de celle du Core.
- **`ApiKey`** : Core possède son propre modèle `Modules\Core\Models\ApiKey`, séparé du modèle `Modules\API\Models\ApiKey` géré par le module API — deux tables/gestion de clés API coexistent dans le périmètre.
