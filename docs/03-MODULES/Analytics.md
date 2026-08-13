# Analytics

## Rôle

Le module Analytics porte le moteur de prévision IA transverse (demande, trésorerie, RH, production), la détection d'anomalies, les modèles ML génériques (avec versions et A/B tests) et un moteur de recommandation. Il complète BI dans le pôle **Pilotage et Reporting**, avec une orientation plus "modèles/algorithmes" que "tableaux de bord".

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `ForecastModel`, `ForecastPrediction`, `ForecastAlert`, `ForecastScenario` | `create_forecast_models_table` et suivantes | Modèle de prévision, ses prédictions, ses alertes et ses scénarios what-if |
| `AnomalyDetectionModel`, `DetectedAnomaly`, `AnomalyAlert` | `create_anomaly_detection_models_table` et suivantes | Modèle de détection d'anomalies et anomalies détectées |
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

**Analytics (`v1/analytics`, `auth:api`)**

| Méthode | Route | Description |
|---|---|---|
| CRUD | `predictions` | Modèles de prédiction (`PredictionController`) |
| POST | `predictions/{id}/train`, GET `.../results` | Entraînement et résultats |
| CRUD | `recommendations` | Recommandations |
| GET | `recommendations/for-user`, POST `.../act`, `.../dismiss` | Cycle de vie d'une recommandation |
| CRUD | `anomalies` (nommé `anomaly`) | Modèles de détection d'anomalies |
| GET | `anomalies/{id}/anomalies`, POST `.../investigate`, `.../resolve`, `.../dismiss` | Cycle de vie d'une anomalie détectée |
| CRUD | `ml-models` (nommé `ml_model`) | Modèles ML |
| GET | `ml-models/{id}/versions`, `.../ab-tests`, POST `.../deploy`, `.../rollback` | Versionnement et déploiement de modèles |

**IA Assisted First** : `POST v1/analytics/ai/assist` (`auth:sanctum`).

## Services

- **`ForecastingEngineService`** — moteur central : `collectHistoricalData()` (agrège les données selon le module cible : `demand`, `cashflow`, `hr`, `production`, `revenue`, `inventory`), puis applique un algorithme (`movingAverage`, `exponentialSmoothing` façon Holt-Winters triple niveau/tendance/saisonnalité, `linear_regression`, ou narration `ai_claude` via `claude-sonnet-4-6`). Gère la saisonnalité Afrique (Ramadan, rentrée scolaire) et le multi-devises XOF/XAF.
- **`AiForecastNarrativeService`** — génère l'explication narrative d'une prévision (facteurs clés, risques, actions recommandées) via l'API Claude.
- **`Forecasting\DemandForecastService`** — prévision de demande produit/catégorie à partir de `sales_order_lines`, suggestions de point de réapprovisionnement.
- **`Forecasting\CashflowForecastService`** — projection de trésorerie à 90 jours à partir de `chart_of_accounts`, `invoices`, `accounting_transactions`, `employees` (masse salariale), détection de déficit.
- **`Forecasting\HrForecastService`** — prévision d'effectifs, score de risque de turnover, projection de coût de paie, à partir de `employees`, `leave_requests`, `leave_balances`, `timesheets`, `job_postings`, `sales_orders`.
- **`Forecasting\ProductionForecastService`** — utilisation de capacité, détection de goulots, consommation matière, à partir de `bom_components`, `work_centers`, `manufacturing_orders`, `stock_movements`, `stock_levels` (voir Particularités : ces trois premières tables appartiennent au module Manufacturing, hors périmètre).

## Permissions RBAC

Préfixe `analytics.` (`database/seeders/RolesAndPermissionsSeeder.php`), ressources `forecast, anomaly` × actions `view-any, view, create, update, delete`. Rôle concerné : `inventory-analyst` (accès complet `analytics.*`, avec `bi.*` et lecture seule `inventory.*`). Aucune policy Laravel additionnelle n'a été trouvée dans `Modules/Analytics/app/Policies` liée à `forecast`/`anomaly` au sens des permissions Spatie ci-dessus — les policies présentes (`ABTestRunPolicy`, `AnomalyDetectionModelPolicy`, `DetectedAnomalyPolicy`, `MLModelPolicy`, `PredictionModelPolicy`, `RecommendationModelPolicy`, `RecommendationPolicy`) couvrent le sous-système ML/recommandation, distinct du sous-système de prévision (`v1/forecasting/*`).

## Dépendances avec d'autres modules

- **AI** : `AnalyticsAiAssistController` utilise `AiContextualAssistantService`.
- **Lecture cross-module via requêtes SQL directes** (et non via les modèles Eloquent des modules cibles) : `ForecastingEngineService` et les services `Forecasting\*` interrogent directement, par `DB::table()`, des tables appartenant à Sales (`sales_orders`, `sales_order_lines`), Accounting (`chart_of_accounts`, `invoices`, `accounting_transactions`), HR (`employees`, `leave_requests`, `leave_balances`, `timesheets`, `job_postings`), Inventory (`stock_movements`, `stock_levels`, `products`) et Core (`companies`). Ce couplage se fait donc au niveau du schéma de base de données, pas au niveau du code PHP des modules (aucun `use Modules\X\Models\...` n'est présent dans ces services).

## Particularités du périmètre life-mdg-erp

- **`Forecasting\ProductionForecastService`** (docblock : "Intégré avec Modules/Manufacturing, Modules/Inventory, Modules/Achats") interroge par `DB::table()` les tables `bom_components`, `work_centers` et `manufacturing_orders`, qui appartiennent au module Manufacturing — **absent du périmètre des 27 modules de life-mdg-erp** et sans migration `Schema::create('manufacturing_orders'...)` dans ce dépôt (confirmé par recherche sur l'ensemble des migrations). Cette prévision de production échouera donc à l'exécution (table inexistante) tant que ces requêtes n'auront pas été adaptées ou que la fonctionnalité n'aura pas été retirée — c'est le même type de dette déjà documenté dans le CLAUDE.md racine sous "Known gaps" (fonctionnalités incomplètes héritées de WideHalo-ERP, pas une régression introduite par l'extraction).
- Les routes `v1/analytics/*` (contrôleurs `PredictionController`, `RecommendationController`, `AnomalyDetectionController`, `MLModelController`) utilisent le guard `auth:api`, alors que la quasi-totalité du reste de l'ERP (y compris les routes `v1/forecasting/*` du même module) utilise `auth:sanctum` — à vérifier si un guard `api` est bien configuré dans `config/auth.php` avant d'exposer ces routes en production.
