# Shared

## Rôle

`Shared` regroupe les référentiels transverses réellement utilisés (pays, devises) et une poignée de classes de base réellement adoptées par d'autres modules : exceptions typées, job asynchrone de base, service de base avec isolation par société (`company_id`), et un trait de scope multi-tenant. Il porte concrètement l'aspect « Africa First / Asia First » au niveau données : c'est ici que vivent les référentiels pays/devises OHADA/UEMOA/CEMAC.

**Chantier 32.8** a soumis ce module à l'audit approfondi en 14 couches (voir `CLAUDE.md` § Méthodologie) — plusieurs bugs et du code confirmé mort ont été trouvés et corrigés ; ce fichier reflète l'état réel post-audit, pas l'état pré-Chantier-32.8.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Country` | `shared_countries` | Référentiel pays : codes ISO alpha-2/3, devise, préfixe téléphonique, indicateurs `is_ohada`/`is_uemoa`/`is_cemac`, taux de TVA par défaut, fuseau horaire. Seedé par `database/seeders/DefaultDataSeeder.php` (12 pays, données réelles de `SmartDefaultsService::COUNTRIES`). |
| `Currency` | `shared_currencies` | Référentiel devises : code, symbole, décimales, indicateur `is_cfa`, taux de change vers USD (illustratif, revu périodiquement par un admin — pas un flux FX en direct). Seedé par le même seeder (13 devises). Consommé directement (pas via HTTP) par `Modules\Inventory\Services\{CostingSheetService,SourcingBenchmarkService}`. |

`shared_preferences` (une table réellement migrée mais sans aucun modèle Eloquent, aucun lecteur/écrivain nulle part dans le dépôt) a été **supprimée** au Chantier 32.8 — voir « Particularités » ci-dessous.

## Endpoints principaux

```
GET  /api/v1/shared/countries                    — liste des pays (cache 1h)
GET  /api/v1/shared/countries/{code}              — détail d'un pays (cache 1h)
GET  /api/v1/shared/countries/{code}/tax-rates    — CASSÉ, gap confirmé et documenté, pas corrigé (voir Particularités)

GET  /api/v1/shared/currencies                    — liste des devises (filtres région/CFA/actif, cache 1h)
GET  /api/v1/shared/currencies/{code}             — détail d'une devise (cache 1h)
POST /api/v1/shared/currencies/convert            — conversion réelle entre deux devises (lookups cachés 1h)

POST /api/v1/shared/ai/assist                     — guidance IA contextuelle (action réelle : view_dashboard)

GET  /shared                                      — page web (Chantier 32.8, voir Vues ci-dessous)
```

Toutes les routes API sont derrière `auth:sanctum, session.security, tenancy.user, module:Shared, role:employee,manager,admin,super-admin` (fixé au Chantier 8.5-light/10 — ce groupe n'avait initialement aucun middleware du tout). `CountryController`/`CurrencyController` n'ont pas d'appel `authorize()` (pas de Policy — référentiel global en lecture seule, pas de donnée par tenant), le gate de route est le seul contrôle réel, un choix délibéré documenté dans le code lui-même.

## Contrôleurs

`Modules/Shared/app/Http/Controllers/Api/` (3 fichiers) :

| Contrôleur | Rôle |
|---|---|
| `CountryController` | Référentiel pays (`index`/`show`, cache 1h) + `taxRates()` (confirmé cassé, voir ci-dessous) |
| `CurrencyController` | Référentiel devises + conversion réelle, cache 1h ajouté au Chantier 32.8 |
| `SharedAiAssistController` | Guidance IA contextuelle (`view_dashboard`, réellement enregistrée dans `AiContextualAssistantService::supportedModules()`) |

## Vues (Vue/Inertia)

`Modules/Shared/resources/js/Pages/Index.vue` — une page réelle (appelle `useAiAssistant('Shared', 'view_dashboard')`) qui n'avait **aucune route web nulle part dans l'app** avant le Chantier 32.8 : le module n'avait pas de `routes/web.php` du tout, et `RouteServiceProvider::map()` n'appelait que `mapApiRoutes()`. Corrigé — `routes/web.php` (nouveau) + `mapWebRoutes()`, montée à `GET /shared` derrière le même gate `module:Shared`+`role:employee,manager,admin,super-admin` que l'API.

## Services

- **`BaseService`** (abstrait) — socle des services scopés par société : construit avec un `companyId` obligatoire (`TenantException::invalidCompanyId` sinon), fournit `verifyCompanyOwnership()`/`verifyCompanyOwnershipMany()` pour vérifier qu'un modèle appartient bien à la société courante, et `scopeQuery()` pour filtrer une requête par `company_id`. **Réellement étendu par 13 services** dans `Accounting` (`MultiEntityConsolidationService`, `AdvancedTaxComplianceService`), `Helpdesk` (`KnowledgeBaseService`), `CRM` (`CRMForecastingService`, `EinsteinForecastingService`), `BI` (`AdvancedVisualizationService`, `RealTimeAlertService`, `ExternalDataIntegrationService`, `DataStorytellingService`) — dont 3 (`CRMForecastingService` via `OpportunityController`, `KnowledgeBaseService` via `KnowledgeBaseController`/`PortalWebController`, `EinsteinForecastingService` via `EinsteinForecastingController`) confirmés réellement atteignables depuis un vrai contrôleur routé.
- **`PersonalizationFramework`** (abstrait, étend `BaseService`) — cadre de segmentation/recommandation utilisateur, réel et correct. Réellement étendu par 2 classes (`Modules\Helpdesk\Services\SatisfactionPredictionService`, `Modules\BI\Services\EnhancedPredictiveAnalyticsService`) — mais confirmé au Chantier 32.8 que ces 2 classes elles-mêmes n'ont **aucun consommateur contrôleur/route** dans leur propre module (seuls leurs tests les instancient directement). Le cadre lui-même reste sain et exporté ; ses 2 seuls adoptants sont orphelins dans leur propre module — hors périmètre de correction ici, signalé pour un futur audit Helpdesk/BI.
- **`MultiTenantScope`** (trait, pas un service) — ajoute un scope global Eloquent filtrant automatiquement par `company_id` de l'utilisateur authentifié. Corrigé au Chantier 19 Lot 3 (retirait un fallback IDOR sur en-tête `X-Company-ID` côté client, et un défaut « aucun filtre du tout » quand aucun tenant ne résout). Toujours zéro consommateur réel dans l'app (confirmé à nouveau au Chantier 32.8) — infra exportée saine, gardée en l'état, pas un cas « code mort » puisqu'un futur modèle peut littéralement `use` ce trait directement sans rien réécrire.
- **`BaseAsyncJob`** (`app/Jobs/`, abstrait) — classe de base pour tout job en file d'attente (3 tentatives, timeout 300s), n'implémente délibérément pas `handle()` (contrat Laravel standard, à la charge de chaque sous-classe). **Étendue par 28 jobs réels** dans `Core` (4, tous corrigés au Chantier 32.1), `Accounting` (10) et `BI` (14) — voir « Particularités » pour un constat sévère trouvé au Chantier 32.8 sur ces 24 dernières.

## Permissions RBAC

Aucune entrée `shared.*` dans `RolesAndPermissionsSeeder::MODULES` — pas de permissions Spatie dédiées, et aucune classe Policy dans ce module (confirmé — `SharedResourcePolicy` avait déjà été supprimée au Chantier 8.5-light, zéro consommateur). Les référentiels pays/devises restent en lecture seule par API dans ce périmètre — le gate de route (`role:employee,manager,admin,super-admin`) est le seul contrôle d'accès, cohérent avec l'absence de donnée par tenant sur `Country`/`Currency`.

## Dépendances avec d'autres modules

`Shared` est un module de fondation pur — il ne dépend lui-même d'aucun autre module métier. Il est consommé par :
- `Modules\Shared\Jobs\BaseAsyncJob` — étendu par `Core`, `Accounting`, `BI` (28 jobs).
- `Modules\Shared\Services\BaseService`/`PersonalizationFramework` — étendus par 15 services dans `Accounting`, `CRM`, `Helpdesk`, `BI`.
- `Modules\Shared\Models\Currency` — consommé directement (pas via HTTP) par `Modules\Inventory\Services\{CostingSheetService,SourcingBenchmarkService}`.
- `GET /api/v1/shared/currencies` — appelé par `Modules\Inventory\resources\js\Pages\Benchmark\Index.vue` (seul appelant Vue confirmé de tout ce module).

## Particularités du périmètre life-mdg-erp

- **Chantier 32.8 — code confirmé mort supprimé (layer 9)** :
  - `Modules\Shared\Services\SentimentAnalysisService` + `Modules\Shared\Exceptions\SentimentException` : zéro appelant réel nulle part hors des propres tests du module, doublon fonctionnel confirmé du vrai `Modules\Helpdesk\Services\SentimentAnalysisService` (607 lignes, awareness `Ticket`, réellement consommé par `AiResponseService`/`AgentPerformanceAnalyticsService`/`PredictiveEscalationService`/`SatisfactionPredictionService`, toutes atteignables depuis des contrôleurs réels).
  - `Modules\Shared\Services\UnifiedForecastingService` + `Modules\Shared\Exceptions\ForecastingException` : zéro appelant réel nulle part, doublon fonctionnel confirmé du vrai `Modules\Analytics\Services\ForecastingEngineService` (Phase 41 / Chantier 26A — moving average, exponential smoothing, régression linéaire, decompose, train/predict, scénarios, alertes — un moteur bien plus complet et réellement vivant).
  - `Modules\Shared\Exceptions\ValidationException` : zéro site de `throw` réel nulle part dans l'app — chaque autre occurrence de « ValidationException » du dépôt résout en réalité vers `Illuminate\Validation\ValidationException` (classe Laravel native), une collision de nom sans lien, jamais exploitée mais un vrai risque de confusion pour un futur développeur.
  - Table `shared_preferences` (migration réelle, `tenant_id`/`user_id`/`preference_key`/`preference_value`) : zéro modèle Eloquent, zéro lecteur/écrivain nulle part dans le dépôt (confirmé par grep exhaustif). Construire une CRUD générique dessus aurait été inventer une fonctionnalité que rien ne demande — supprimée plutôt qu'activée, matchant le précédent Chantier 8.5-light (`Tag`/`Language`).
- **Chantier 32.8 — bug réel corrigé (layer 1/3)** : `resources/js/Pages/Index.vue` était une page réelle et bien formée sans aucune route web dans tout le dépôt (le module n'avait pas de `routes/web.php`). Corrigé (voir Vues ci-dessus).
- **Chantier 32.8 — perf (layer 14f)** : `CountryController`/`CurrencyController` re-interrogeaient un référentiel statique à chaque appel sans aucun cache. Ajout d'un `Cache::remember()` (1h, cohérent avec le TTL déjà établi par `PersonalizationFramework` dans ce même module), clé de cache correctement paramétrée par les filtres réels — vérifié empiriquement (compteur de requêtes SQL à zéro sur un second appel identique).
- **Chantier 32.8 — constat sévère trouvé mais délibérément non corrigé ici (hors périmètre `Modules/Shared`)** : sur les 24 jobs (hors `Core`, déjà corrigés au Chantier 32.1) qui étendent `Modules\Shared\Jobs\BaseAsyncJob` — 10 dans `Accounting` (`ValidateComplianceJob`, `EliminateIntercompanyJob`, `GenerateConsolidatedReportsJob`, `UpdateDeferredTaxJob`, `CalculateTaxProvisionsJob`, `CalculateMinorityInterestJob`, `ComputeTransferPricingJob`, `ConsolidateFinancialsJob`, `GenerateTaxReportsJob`, `ProcessConsolidationAdjustmentsJob`) et 14 dans `BI` (`SendAlertNotificationJob`, `EvaluateAlertRulesJob`, `GenerateAlertDigestJob`, `CreateDataStoryJob`, `SyncExternalDataSourceJob`, `TransformAndValidateDataJob`, `EvaluateForecastAccuracyJob`, `TrainForecastModelJob`, `AnalyzeStoryEngagementJob`, `PublishDataStoryJob`, `ExportVisualizationJob`, `GenerateForecastJob`, `GenerateVisualizationDataJob`, `RenderCustomChartJob`, `EscalateUnacknowledgedAlertsJob`) — **aucun des 24 n'a de méthode `handle()`**, la même classe de bug déjà trouvée et corrigée pour 4 jobs `Core` au Chantier 32.1 (un job `ShouldQueue` sans `handle()`/`__invoke()` échoue fatalement à chaque dispatch réel). 21 de ces 24 jobs ont en plus **zéro site de dispatch réel** nulle part dans l'app (confirmé par grep) — du code entièrement mort, pas seulement cassé. Les 3 restants (`SendAlertNotificationJob`, `TransformAndValidateDataJob`, `TrainForecastModelJob`) sont dispatchés uniquement par d'autres jobs de ce même cluster mort (`EvaluateAlertRulesJob`, `EscalateUnacknowledgedAlertsJob`, `SyncExternalDataSourceJob`, `EvaluateForecastAccuracyJob` — eux-mêmes sans `handle()` et sans dispatcher réel) — un sous-système BI entièrement fermé et mort, cohérent avec 4 autres services BI trouvés zéro-consommateur dans la même passe (`AdvancedVisualizationService`, `RealTimeAlertService`, `ExternalDataIntegrationService`, `DataStorytellingService`, tous étendent `BaseService` mais n'ont aucun appelant `Http`). **Non corrigé ici** — corriger `handle()` sur 10 fichiers Accounting + 14 fichiers BI, et statuer activer/supprimer sur les services BI associés, est un travail de la taille d'un chantier dédié à ces 2 modules, hors du périmètre strict `Modules/Shared/**` de cet audit. Signalé ici avec la sévérité qu'il mérite pour un futur « Chantier 32.x — Accounting » et « Chantier 32.x — BI ».
- **`CountryController::taxRates()` reste un gap confirmé, non corrigé (déjà documenté depuis le Chantier 19 Lot 3, re-vérifié empiriquement toujours exact au Chantier 32.8)** : interroge `shared_tax_rates`, une table qui n'a jamais eu de migration nulle part dans le dépôt — erreur SQL "no such table" garantie à chaque appel réel. Construire cette fonctionnalité pour de vrai signifierait concevoir un nouveau concept (historique de taux de TVA daté par pays) plutôt que corriger un simple bug de câblage — `shared_countries.vat_rate` porte déjà un taux plat toujours-actuel par pays, donc la question de savoir si un historique daté est réellement nécessaire est une décision produit, laissée à un futur chantier.
- **`Country`/`Currency` portent chacun leur propre petite méthode `isCfaFranc()` avec une implémentation différente** (`Country` compare `currency_code` à `['XOF','XAF']`, `Currency` lit directement la colonne réelle `is_cfa`) — une redondance mineure, pas un bug (les deux sont corrects et cohérents), non touchée puisque supprimer l'un casserait un appelant potentiel pour un gain de clarté marginal.
- Certains jobs qui étendent `BaseAsyncJob` (ex. dans `Accounting\Jobs\ConsolidateFinancialsJob`, `ProcessConsolidationAdjustmentsJob`) appartiennent aux fonctionnalités de consolidation multi-société déjà incomplètes dans WideHalo-ERP source (voir `CLAUDE.md`, section « Known gaps ») — `Shared` lui-même est complet, mais certains de ses consommateurs ne le sont pas (voir le constat sévère ci-dessus, qui va plus loin que ce que cette note laissait entendre : ces jobs ne sont pas juste "incomplets", ils sont structurellement incapables de s'exécuter du tout).
