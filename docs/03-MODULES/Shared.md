# Shared

## Rôle

`Shared` regroupe les référentiels transverses (pays, devises, langues, tags) et une poignée de classes de base réutilisées par d'autres modules : exceptions typées, job asynchrone de base, service de base avec isolation par société (`company_id`), et un trait de scope multi-tenant. Il porte concrètement l'aspect « Africa First / Asia First » au niveau données : c'est ici que vivent les référentiels pays/devises OHADA/UEMOA/CEMAC.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Country` | `shared_countries` | Référentiel pays : codes ISO alpha-2/3, devise, préfixe téléphonique, indicateurs `is_ohada`/`is_uemoa`/`is_cemac`, taux de TVA par défaut, fuseau horaire. |
| `Currency` | `shared_currencies` | Référentiel devises : code, symbole, décimales, indicateur `is_cfa`, taux de change vers USD. |

Une migration additionnelle crée `shared_preferences` (préférences utilisateur/tenant) sans modèle Eloquent dédié dans le code actuel. `Tag`/`Language` (modèles + `SharedResourcePolicy`) ont été **supprimés** cette session : confirmés zéro consommateur nulle part dans le dépôt.

## Endpoints principaux

```
GET  /api/v1/shared/countries                    — liste des pays
GET  /api/v1/shared/countries/{code}              — détail d'un pays
GET  /api/v1/shared/countries/{code}/tax-rates    — taux de taxe actifs pour un pays

GET  /api/v1/shared/currencies                    — liste des devises (CurrencyController, filtres région/CFA/actif)
GET  /api/v1/shared/currencies/{code}             — détail d'une devise
POST /api/v1/shared/currencies/convert            — conversion réelle entre deux devises

POST /api/v1/shared/ai/assist  (auth:sanctum)     — guidance IA contextuelle
```

Les routes pays/devises ne sont pas protégées par `auth:sanctum` (référentiels publics, design volontaire — Africa First/Asia First). `CountryController` interroge directement les tables via `DB::table()` plutôt que via les modèles Eloquent — les filtres utilisés (`active`, `code`, `ohada_member` dans `index()`/`show()`/`taxRates()`) ne correspondent pas aux colonnes réellement définies par la migration `shared_countries` (`is_ohada`, `iso_alpha2`, pas de colonne `active` ni `code`), à surveiller si ces endpoints sont exercés en pratique — non touché cette session (hors scope du correctif ciblé).

## Contrôleurs

`Modules/Shared/app/Http/Controllers/Api/` (3 fichiers) :

| Contrôleur | Rôle |
|---|---|
| `CountryController` | Référentiel pays + taux de taxe (voir réserve ci-dessus) |
| `CurrencyController` | Référentiel devises + conversion — **wiré pour la première fois cette session** (voir Particularités) |
| `SharedAiAssistController` | Guidance IA contextuelle |

## Vues (Vue/Inertia)

`Modules/Shared/resources/js/Pages/Index.vue` existe dans le dépôt mais **n'est rendue par aucune route web** — ni `Modules/Shared/routes/web.php` (absent) ni aucune route racine ne la référencent. Contrairement aux pages « mock, laissées de côté volontairement » documentées ailleurs dans ce dépôt, ce cas n'a pas été explicitement statué par un chantier : à considérer comme une page orpheline plutôt que comme une décision produit actée.

## Services

- **`BaseService`** (abstrait) — socle des services scopés par société : construit avec un `companyId` obligatoire (`TenantException::invalidCompanyId` sinon), fournit `verifyCompanyOwnership()`/`verifyCompanyOwnershipMany()` pour vérifier qu'un modèle appartient bien à la société courante, et `scopeQuery()` pour filtrer une requête par `company_id`.
- **`PersonalizationFramework`** (abstrait, étend `BaseService`) — cadre de personnalisation de contenu par segment utilisateur (`personalizeContent`, seuil de confiance, taille minimale de segment) ; sert de base à personnaliser mais n'a pas d'implémentation métier concrète dans ce périmètre (classe abstraite).
- **`SentimentAnalysisService`** (étend `BaseService`) — analyse de sentiment (positif/négatif/neutre/mixte) et détection d'émotions, avec appel HTTP optionnel à une API externe configurable (`sentiment.api_endpoint`) et repli si désactivée.
- **`UnifiedForecastingService`** (étend `BaseService`) — prévisions ARIMA génériques réutilisables par d'autres modules (cache 24h, seuil de confiance, historique minimum 30 jours).
- **`MultiTenantScope`** (trait, pas un service) — ajoute un scope global Eloquent filtrant automatiquement par `company_id` (depuis l'utilisateur authentifié ou l'en-tête `X-Company-ID`), et pré-remplit `company_id` à la création.
- **`BaseAsyncJob`** (`app/Jobs/`, abstrait) — classe de base pour tout job en file d'attente (3 tentatives, timeout 300s) ; largement réutilisée : `Core\Jobs\ExecuteImportJob`, `Core\Jobs\AnonymizeUserJob`, `Core\Jobs\SarExportJob`, `Accounting\Jobs\*` (consolidation, fiscalité), `BI\Jobs\*` (alertes) en héritent toutes.

## Permissions RBAC

Aucune entrée `shared.*` dans `RolesAndPermissionsSeeder::MODULES` — pas de permissions Spatie dédiées. `SharedResourcePolicy` a été **supprimée** cette session (zéro consommateur confirmé) : il n'y a donc plus de Policy du tout sur ce module — les référentiels pays/devises restent en lecture publique par design (voir Endpoints principaux), sans écriture exposée par API dans ce périmètre.

## Dépendances avec d'autres modules

`Shared` est le module le plus largement consommé du socle : `Modules\Shared\Jobs\BaseAsyncJob` est étendu par des jobs dans `Core`, `Accounting` (dont `MultiEntityConsolidationService`, `AdvancedTaxComplianceService`), `BI`, `CRM` (`TerritoryManagementService`, `CampaignOrchestrationService`, `CRMForecastingService`) et `Helpdesk` (`SatisfactionPredictionService`). Il ne dépend lui-même d'aucun autre module métier — c'est une brique de fondation pure, cohérente avec sa position dans le socle CORE.

## Particularités du périmètre life-mdg-erp

- **`CurrencyController` était réel mais entièrement non routé avant cette session** : seul un alias plus mince, `CountryController::currencies()` (une simple liste `DB::table()` non filtrée), était enregistré sous `GET currencies`. `CurrencyController` (filtrage région/CFA/actif, lookup d'une devise unique, et la seule implémentation réelle de *conversion* de devise du module) a remplacé cet alias comme la vraie route `currencies*`, plutôt que d'être enregistré en doublon sur la même URI — cela aurait recréé le même piège d'écrasement silencieux (dernière déclaration gagnante dans la table de routes de Laravel) déjà documenté et corrigé pour les routes de base de connaissances de Helpdesk. Le multi-devises étant l'un des sept principes fondateurs de l'application, cette absence de route était considérée comme un vrai gap plutôt qu'un détail mineur.
- Certains jobs qui étendent `BaseAsyncJob` (ex. dans `Accounting\Jobs\ConsolidateFinancialsJob`, `ProcessConsolidationAdjustmentsJob`) appartiennent aux fonctionnalités de consolidation multi-société déjà incomplètes dans WideHalo-ERP source (voir `CLAUDE.md`, section « Known gaps ») — `Shared` lui-même est complet, mais certains de ses consommateurs ne le sont pas.
