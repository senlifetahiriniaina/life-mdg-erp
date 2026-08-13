# Shared

## Rôle

`Shared` regroupe les référentiels transverses (pays, devises, langues, tags) et une poignée de classes de base réutilisées par d'autres modules : exceptions typées, job asynchrone de base, service de base avec isolation par société (`company_id`), et un trait de scope multi-tenant. Il porte concrètement l'aspect « Africa First / Asia First » au niveau données : c'est ici que vivent les référentiels pays/devises OHADA/UEMOA/CEMAC.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Country` | `shared_countries` | Référentiel pays : codes ISO alpha-2/3, devise, préfixe téléphonique, indicateurs `is_ohada`/`is_uemoa`/`is_cemac`, taux de TVA par défaut, fuseau horaire. |
| `Currency` | `shared_currencies` | Référentiel devises : code, symbole, décimales, indicateur `is_cfa`, taux de change vers USD. |
| `Language` | `shared_languages` | Référentiel langues (code, nom natif, sens d'écriture RTL, région). |
| `Tag` | `shared_tags` | Système de tags génériques, scopé par `tenant_id` et par `module` d'origine, avec compteur d'usage. |

Une migration additionnelle crée `shared_preferences` (préférences utilisateur/tenant) sans modèle Eloquent dédié dans le code actuel.

## Endpoints principaux

```
GET  /api/v1/shared/countries                    — liste des pays
GET  /api/v1/shared/countries/{code}              — détail d'un pays
GET  /api/v1/shared/currencies                    — liste des devises
GET  /api/v1/shared/countries/{code}/tax-rates    — taux de taxe actifs pour un pays

POST /api/v1/shared/ai/assist  (auth:sanctum)     — guidance IA contextuelle
```

Les 4 premières routes ne sont pas protégées par `auth:sanctum` (référentiels publics). `CountryController` interroge directement les tables via `DB::table()` plutôt que via les modèles Eloquent — les filtres utilisés (`active`, `code`, `ohada_member` dans `index()`/`show()`/`taxRates()`) ne correspondent pas aux colonnes réellement définies par la migration `shared_countries` (`is_ohada`, `iso_alpha2`, pas de colonne `active` ni `code`), à surveiller si ces endpoints sont exercés en pratique.

## Services

- **`BaseService`** (abstrait) — socle des services scopés par société : construit avec un `companyId` obligatoire (`TenantException::invalidCompanyId` sinon), fournit `verifyCompanyOwnership()`/`verifyCompanyOwnershipMany()` pour vérifier qu'un modèle appartient bien à la société courante, et `scopeQuery()` pour filtrer une requête par `company_id`.
- **`PersonalizationFramework`** (abstrait, étend `BaseService`) — cadre de personnalisation de contenu par segment utilisateur (`personalizeContent`, seuil de confiance, taille minimale de segment) ; sert de base à personnaliser mais n'a pas d'implémentation métier concrète dans ce périmètre (classe abstraite).
- **`SentimentAnalysisService`** (étend `BaseService`) — analyse de sentiment (positif/négatif/neutre/mixte) et détection d'émotions, avec appel HTTP optionnel à une API externe configurable (`sentiment.api_endpoint`) et repli si désactivée.
- **`UnifiedForecastingService`** (étend `BaseService`) — prévisions ARIMA génériques réutilisables par d'autres modules (cache 24h, seuil de confiance, historique minimum 30 jours).
- **`MultiTenantScope`** (trait, pas un service) — ajoute un scope global Eloquent filtrant automatiquement par `company_id` (depuis l'utilisateur authentifié ou l'en-tête `X-Company-ID`), et pré-remplit `company_id` à la création.
- **`BaseAsyncJob`** (`app/Jobs/`, abstrait) — classe de base pour tout job en file d'attente (3 tentatives, timeout 300s) ; largement réutilisée : `Core\Jobs\ExecuteImportJob`, `Core\Jobs\AnonymizeUserJob`, `Core\Jobs\SarExportJob`, `Accounting\Jobs\*` (consolidation, fiscalité), `BI\Jobs\*` (alertes) en héritent toutes.

## Permissions RBAC

Aucune entrée `shared.*` dans `RolesAndPermissionsSeeder::MODULES` — pas de permissions Spatie dédiées. L'autorisation passe par `SharedResourcePolicy` : lecture ouverte à tout utilisateur authentifié (`viewAny`/`view` retournent toujours `true`), création/modification réservées aux rôles `admin`/`super-admin`/`tenant-admin`, suppression réservée à `super-admin`.

## Dépendances avec d'autres modules

`Shared` est le module le plus largement consommé du socle : `Modules\Shared\Jobs\BaseAsyncJob` est étendu par des jobs dans `Core`, `Accounting` (dont `MultiEntityConsolidationService`, `AdvancedTaxComplianceService`), `BI`, `CRM` (`TerritoryManagementService`, `CampaignOrchestrationService`, `CRMForecastingService`) et `Helpdesk` (`SatisfactionPredictionService`). Il ne dépend lui-même d'aucun autre module métier — c'est une brique de fondation pure, cohérente avec sa position dans le socle CORE.

## Particularités du périmètre life-mdg-erp

Certains jobs qui étendent `BaseAsyncJob` (ex. dans `Accounting\Jobs\ConsolidateFinancialsJob`, `ProcessConsolidationAdjustmentsJob`) appartiennent aux fonctionnalités de consolidation multi-société déjà incomplètes dans WideHalo-ERP source (voir `CLAUDE.md`, section « Known gaps ») — `Shared` lui-même est complet, mais certains de ses consommateurs ne le sont pas.
