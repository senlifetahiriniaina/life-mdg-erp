# Analytics

## Rôle

Le module Analytics porte le moteur de prévision IA transverse (demande, trésorerie, RH, production), la détection d'anomalies, les modèles ML génériques (avec versions et A/B tests) et un moteur de recommandation. Il complète BI dans le pôle **Pilotage et Reporting**, avec une orientation plus "modèles/algorithmes" que "tableaux de bord".

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `ForecastModel`, `ForecastPrediction`, `ForecastAlert`, `ForecastScenario` | `create_forecast_models_table` et suivantes | Modèle de prévision, ses prédictions, ses alertes et ses scénarios what-if |
| `MLModel`, `MLModelVersion`, `ModelMetric`, `ModelAccuracyMetric` | `create_ml_models_table`, `create_ml_model_versions_table` | Modèle ML générique avec versionnement, déploiement/rollback |
| `PredictionModel`, `PredictionInput`, `PredictionResult` | — | Modèle de prédiction générique, ses entrées et résultats |
| `Recommendation`, `RecommendationModel`, `UserInteraction` | — | Moteur de recommandation et interactions utilisateur associées |
| `ABTestRun` | — | Test A/B entre versions de modèle ML |

## Endpoints principaux

Deux ensembles de routes, avec des guards d'authentification différents (à noter, voir Particularités) :

**Prévision (`v1/forecasting`, `auth:sanctum`)**

| Méthode | Route | Description |
|---|---|---|
| GET/POST/PUT | `models`, `models/{id}` | Modèles de prévision |
| POST | `models/{id}/train` | Entraînement d'un modèle |
| GET | `models/{id}/predictions` | Prédictions d'un modèle |
| GET | `alerts`, POST `alerts/{id}/acknowledge` | Alertes de prévision |
| GET/POST | `scenarios`, `scenarios/compare` | Scénarios what-if et comparaison |
| GET | `demand/{productId}`, `demand` | Prévision de demande produit |
| GET | `cashflow` | Prévision de trésorerie à 90 jours |
| GET | `hr/headcount`, `hr/turnover-risk`, `hr` | Prévision RH (effectifs, risque de turnover) |
| GET | `production` | Prévision de production |
| POST | `ai/analyze`, `ai/narrative` | Analyse IA libre et narration de prévision (Claude) |
| GET | `hub` | Résumé consolidé (demande + trésorerie + RH + production + alertes) |

**Analytics (`v1/analytics`, `auth:sanctum` + `module:Analytics` + `role:employee,inventory-analyst,manager,admin`)**

| Méthode | Route | Description |
|---|---|---|
| GET/POST/GET-1 | `predictions` (`->only(['index','store','show'])`) | Modèles de prédiction (`PredictionController`) |
| POST | `predictions/{id}/train`, GET `.../results` | Entraînement et résultats |
| GET/POST/GET-1 | `recommendations` (`->only(['index','store','show'])`) | Recommandations — `update`/`destroy` volontairement exclues (corrigé au Chantier 8.5ars : l'`apiResource` complet enregistrait des routes vers des méthodes que le contrôleur n'implémente pas, un `Call to undefined method` garanti) |
| GET | `recommendations/for-user`, POST `.../act`, `.../dismiss` | Cycle de vie d'une recommandation (mutation réelle) |
| CRUD | `ml-models` (nommé `ml_model`) | Modèles ML |
| GET | `ml-models/{id}/versions`, `.../ab-tests`, POST `.../deploy`, `.../rollback` | Versionnement et déploiement de modèles |

**IA Assisted First** : `POST v1/analytics/ai/assist` (`auth:sanctum`).

**Note** : le sous-registre de détection d'anomalies propre à Analytics (`AnomalyDetectionController`, modèles `AnomalyDetectionModel`/`DetectedAnomaly`/`AnomalyAlert`) a été supprimé — c'était un doublon orphelin (zéro appelant réel, schéma jamais aligné avec `$fillable`). La détection d'anomalies réellement utilisée dans l'ERP vit dans `Modules\AI\Http\Controllers\Api\AiAnomalyController` (`/api/v1/ai/anomalies*`, adossé à `AiAnomalyDetectionService`, qui vérifie réellement Inventory/Accounting/HR) — voir le module AI.

## Contrôleurs

6 contrôleurs : `Api/ForecastingController` (moteur de prévision, `v1/forecasting/*`), `Api/AnalyticsAiAssistController` (guidance IA), `PredictionController`/`RecommendationController`/`MLModelController` (directement sous `Http/Controllers/`, pas `Api/`), `Web/AnalyticsWebController`.

Analytics s'est révélé le module le plus propre des trois du pôle Pilotage et Reporting (avec BI et Reporting) lors de l'audit Chantier 8.5ars : le scoping tenant par `company_id` était déjà correct partout, contrairement à Reporting/Strategy qui souffraient d'une fuite cross-tenant. Corrections apportées malgré tout : `module:Analytics`+`role:employee,inventory-analyst,manager,admin` ajouté aux deux groupes de routes (confirmé dans le code) ; ajout d'une capacité `create` à `RecommendationPolicy` + l'appel `authorize()` manquant dans `RecommendationController::store()` ; ajout de `authorize('view', ...)` + scoping tenant implicite à `PredictionController::results()` et `MLModelController::versions()`/`abTests()`. `PredictionController::train()` a le même trou d'autorisation que les 3 méthodes corrigées — signalé mais volontairement laissé pour un futur passage plutôt que d'élargir le périmètre du chantier en cours.

## Vues (Vue/Inertia)

Une seule page : `Modules/Analytics/resources/js/Pages/Index.vue`, servie par `AnalyticsWebController` sous `/analytics`. L'ancienne page racine `resources/js/Pages/Analytics/Dashboard.vue` (445 lignes de données 100 % simulées, sous des onglets Procurement/Approvals/Quality qui ne correspondent même pas au domaine Analytics, jamais rendue par aucun contrôleur) a été supprimée au Chantier 8.5ars — `resources/js/Pages/Analytics/` ne contient donc plus aucun fichier.

## Services

- **`ForecastingEngineService`** — moteur central : `collectHistoricalData()` (agrège les données selon le module cible : `demand`, `cashflow`, `hr`, `production`, `revenue`, `inventory`), puis applique un algorithme (`movingAverage`, `exponentialSmoothing` façon Holt-Winters triple niveau/tendance/saisonnalité, `linear_regression`, ou narration `ai_claude` via `claude-sonnet-4-6`). Gère la saisonnalité Afrique (Ramadan, rentrée scolaire) et le multi-devises XOF/XAF.
- **`AiForecastNarrativeService`** — génère l'explication narrative d'une prévision (facteurs clés, risques, actions recommandées) via l'API Claude.
- **`Forecasting\DemandForecastService`** — prévision de demande produit/catégorie à partir de `sales_order_lines`, suggestions de point de réapprovisionnement.
- **`Forecasting\CashflowForecastService`** — projection de trésorerie à 90 jours à partir de `chart_of_accounts`, `invoices`, `accounting_transactions`, `employees` (masse salariale), détection de déficit.
- **`Forecasting\HrForecastService`** — prévision d'effectifs, score de risque de turnover, projection de coût de paie, à partir de `employees`, `leave_requests`, `leave_balances`, `timesheets`, `job_postings`, `sales_orders`.
- **`Forecasting\ProductionForecastService`** — utilisation de capacité, détection de goulots, consommation matière, à partir de `bom_components`, `work_centers`, `manufacturing_orders`, `stock_movements`, `stock_levels` (voir Particularités : ces trois premières tables appartiennent au module Manufacturing, hors périmètre).

## Permissions RBAC

Préfixe `analytics.` (`database/seeders/RolesAndPermissionsSeeder.php`), ressources `forecast, anomaly` × actions `view-any, view, create, update, delete`. Rôle concerné : `inventory-analyst` (accès complet `analytics.*`, avec `bi.*` et lecture seule `inventory.*`). Le sous-registre d'anomalies propre à Analytics ayant été supprimé (voir Contrôleurs), les policies présentes (`ABTestRunPolicy`, `MLModelPolicy`, `PredictionModelPolicy`, `RecommendationModelPolicy`, `RecommendationPolicy`) couvrent uniquement le sous-système ML/recommandation, distinct du sous-système de prévision (`v1/forecasting/*`) qui n'a pas de Policy dédiée — sa protection vient exclusivement du scoping `company_id` en base et du verrou `role:` de route ajouté au Chantier 8.5ars (même précédent que `RateLimitController`/`AuthenticationEventController` côté Security : pas de modèle naturel où accrocher une Policy).

## Dépendances avec d'autres modules

- **AI** : `AnalyticsAiAssistController` utilise `AiContextualAssistantService`.
- **Lecture cross-module via requêtes SQL directes** (et non via les modèles Eloquent des modules cibles) : `ForecastingEngineService` et les services `Forecasting\*` interrogent directement, par `DB::table()`, des tables appartenant à Sales (`sales_orders`, `sales_order_lines`), Accounting (`chart_of_accounts`, `invoices`, `accounting_transactions`), HR (`employees`, `leave_requests`, `leave_balances`, `timesheets`, `job_postings`), Inventory (`stock_movements`, `stock_levels`, `products`) et Core (`companies`). Ce couplage se fait donc au niveau du schéma de base de données, pas au niveau du code PHP des modules (aucun `use Modules\X\Models\...` n'est présent dans ces services).

## Particularités du périmètre life-mdg-erp

- **`Forecasting\ProductionForecastService`** (docblock : "Intégré avec Modules/Manufacturing, Modules/Inventory, Modules/Achats") interroge par `DB::table()` les tables `bom_components`, `work_centers` et `manufacturing_orders`, qui appartiennent au module Manufacturing — **absent du périmètre des 27 modules de life-mdg-erp** et sans migration `Schema::create('manufacturing_orders'...)` dans ce dépôt (confirmé par recherche sur l'ensemble des migrations). Cette prévision de production échouera donc à l'exécution (table inexistante) tant que ces requêtes n'auront pas été adaptées ou que la fonctionnalité n'aura pas été retirée — c'est le même type de dette déjà documenté dans le CLAUDE.md racine sous "Known gaps" (fonctionnalités incomplètes héritées de WideHalo-ERP, pas une régression introduite par l'extraction).
- L'incohérence de guard précédemment documentée ici (`v1/analytics/*` sur `auth:api` contre `auth:sanctum` partout ailleurs) a été corrigée au Chantier 8.5ars en même temps que l'ajout du verrou `module:`/`role:` — les deux groupes de routes (`v1/forecasting/*` et `v1/analytics/*`) utilisent désormais `auth:sanctum` de façon cohérente (confirmé dans le code).
