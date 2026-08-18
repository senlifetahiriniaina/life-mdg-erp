# BI

## Rôle

Le module BI (Business Intelligence) fournit la couche de reporting et de pilotage transverse de l'ERP : tableaux de bord, KPI, requêtes ad-hoc, alertes en temps réel, connecteurs de sources de données externes, modèles prédictifs, requêtes en langage naturel (NL → SQL) et partage de dashboards en marque blanche (embed). Il forme, avec Analytics, Reporting et Strategy, le pôle **Pilotage et Reporting** de life-mdg-erp.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Kpi` | `bi_kpis` | Définition d'un KPI (valeur courante, cible, seuils warning/critique, tendance) |
| `KpiHistory`, `KpiAlert` | — | Historique de valeurs et alerte de dépassement de seuil sur un KPI |
| `Dashboard`, `Widget` | — | Tableau de bord et ses widgets |
| `BiQuery` | — | Requête BI sauvegardée (SQL ou construite visuellement) |
| `Report`, `ScheduledReport` | — | Rapport et sa planification d'envoi |
| `BiDataSource` | — | Source de données connectée (type, config de connexion chiffrée) |
| `ExternalDataSource`, `SyncConfiguration`, `SyncHistory`, `FieldMapping`, `TransformationRule` | — | Intégration de sources externes (CSV, Google Sheets, MySQL/Postgres, REST API) |
| `AlertRule`, `AlertCondition`, `AlertEvent`, `AlertEscalation`, `AlertRecipient`, `AlertDeduplication`, `AlertHistory` | — | Moteur d'alerte configurable avec déduplication et escalade |
| `BiAlert`, `BiAlertEvent` | — | Alertes BI legacy/simplifiées |
| `BiAnomaly` | — | Anomalie détectée sur une série temporelle |
| `Forecast`, `ForecastModel`, `ForecastPrediction`, `ForecastScenario`, `SeasonalityPattern`, `ModelRetrainingLog` | — | Prévision et modèles prédictifs internes au module BI |
| `PredictiveModel` | — | Modèle prédictif générique (entraînement, génération) |
| `DataStory`, `StorySlide`, `StoryView`, `StoryAnalytics`, `NarrativeFlow` | — | Data storytelling (narration automatique de données) |
| `CustomVisualization`, `VisualizationTemplate`, `VisualizationPerformance` | — | Visualisations personnalisées |
| `EmbedToken` | — | Jeton d'intégration pour dashboards en marque blanche (embed public) |
| `AudienceSegment`, `DndSchedule` | — | Segmentation d'audience et plages "ne pas déranger" pour les alertes |
| `TimeseriesData`, `TrendAnalysis` | — | Données de séries temporelles et analyse de tendance |
| `ExternalCredential` | — | Identifiants chiffrés pour connecteurs externes |
| `DataRefreshSchedule` | — | Planification de rafraîchissement des sources de données |

## Endpoints principaux

Toutes les routes sont sous `v1`, `auth:sanctum`, `module:BI`, garde de rôle `role:manager,admin` (sauf les endpoints d'embed public).

| Méthode | Route | Description |
|---|---|---|
| GET | `bi/queries`, `bi/alerts`, `bi/data-sources`, `bi/dashboards`, `bi/reports`, `bi/kpis`, `bi/kpi-alerts`, `bi/scheduled-reports`, `bi/insights` | Lectures des ressources BI |
| POST | `bi/queries/{id}/run`, `bi/reports/{id}/run`, `bi/queries/run-raw` (réservé `admin,manager`) | Exécution de requêtes |
| GET | `bi/export/{dataset}`, `bi/dashboards/{id}/export`, `bi/widgets/{id}/export` | Exports (dashboard, widget, dataset) |
| POST | `bi/widgets/{id}/drill` | Analyse en drill-down |
| CRUD | `bi/queries`, `bi/alerts`, `bi/data-sources`, `bi/dashboards`, `bi/reports`, `bi/kpis`, `bi/kpi-alerts`, `bi/scheduled-reports` | Mutations standard |
| POST | `bi/data-sources/{id}/test`, `.../sync`, GET `.../schema`, GET `bi/data-sources/types` | Gestion des connecteurs de sources externes |
| POST | `bi/kpi-alerts/check`, `.../evaluate`, `bi/alert-events/{id}/acknowledge` | Évaluation et accusé de réception d'alerte KPI |
| POST | `bi/scheduled-reports/{id}/send`, `.../process-due` | Envoi de rapports planifiés |
| POST | `bi/ai/narrative`, `bi/ai/analyze-objectives`, `bi/ai/forecast-compare`, `bi/ai/suggest-alignment` | Analyse IA avancée (narration, objectifs, comparaison de prévision) |
| POST | `bi/ai/detect-deviations`, GET `bi/forecast-sources`, `bi/objective-catalog`, `bi/widgets/{id}/objectives` | Alignement widget ↔ objectifs stratégiques |
| POST | `bi/nl-query` | Requête en langage naturel → SQL |
| POST | `bi/ai/insights`, `bi/ai/detect-trends`, `bi/ai/suggest-kpis`, `bi/ai/recommend-dashboard` | Suggestions IA (KPI, tendances, dashboard) |
| POST/GET | `bi/predictive-models`, `.../{id}/train`, `.../{id}/generate`, `.../{id}/forecasts` | Modèles prédictifs |
| POST/GET | `bi/anomalies/detect`, `bi/anomalies`, `bi/anomalies/{id}/acknowledge` | Détection d'anomalies |
| POST | `v1/bi/ai/assist` | Guidance IA contextuelle (AI Assisted First) |
| POST | `v1/bi/embed/tokens`, DELETE `.../{jti}` | Gestion des jetons d'intégration (authentifié) |
| GET | `v1/bi/embed/validate`, `v1/bi/embed/dashboard/{id}` | Endpoints publics d'intégration en marque blanche (vérifiés par jeton, sans `auth:sanctum`) |

## Contrôleurs

22 contrôleurs Api (`Modules/BI/app/Http/Controllers/Api/`) + 5 contrôleurs Web.

Api : `DashboardController`, `KpiController`/`KpiAlertController`, `QueryController`/`BiNlQueryController`, `AlertController`/`AlertRuleController`, `DataSourceController`/`ExternalDataSourceController`, `ReportController`, `ExportController`, `DrillDownController`, `EmbedController`, `AnalyticsController`/`BiInsightsController`, `DataStoryController`, `VisualizationController`, `PredictiveAnalyticsController`/`ForecastingController`, `BiAIController`/`AiBiController`/`BIAiAssistController`.

Web : `BiWebController` (11 pages historiques + dashboards), et les 4 issus du Chantier 8.2bi : `AlertRuleWebController`, `DataStoryWebController`, `ExternalDataSourceWebController`, `ForecastingWebController`.

**Casse active corrigée (Chantier 8.2bi)** : la migration fourre-tout de scaffold avait laissé `bi_dashboards`/`bi_reports` et 10 autres tables `bi_*` en stub `id/tenant_id/data/timestamps`, faisant planter 5 pages réelles déjà routées (`/bi`, `/bi/sql-editor`, `/bi/alerts`, `/bi/data-sources`, `/bi/dashboards/builder`) — patchées additivement (`2026_08_24_000001_patch_remaining_bi_stub_tables.php`).

**5 sous-systèmes entiers construits pour de vrai après checkpoint utilisateur** : `AlertRule`, `DataStory`, `ExternalDataSource`, `ForecastModel`, `CustomVisualization` étaient chacun entièrement écrits (Policy + Controller appelant déjà `authorize()`) mais sans table, sans route, sans permission seedée. Chacun a reçu ses tables manquantes, un contrôleur Web léger + page `Index.vue` (découvrabilité par URL directe, sauf Visualization où `BiWebController::visualizations()` existait déjà), un enregistrement `Gate::policy()` dans `BIServiceProvider::registerPolicies()` (aucune des 5 policies n'était auto-découvrable — le nom de classe ne correspond pas au modèle, ex. `AlertRule` → `AlertPolicy`), et un bloc `BI_EXTRA_PERMISSIONS` (45 permissions).

`BIController` (scaffold mort, zéro route) + ses vues blade ont été supprimés, même précédent que `HelpdeskController`.

## Vues (Vue/Inertia)

`Modules/BI/resources/js/Pages/` : `Visualizations/`, `DataStories/`, `Forecasting/`, `AlertRules/`, `AINarratives/`, `PredictiveAnalytics/`, `ExternalDataSources/` — les 4 dernières venant du Chantier 8.2bi (`AlertRules`, `DataStories`, `ExternalDataSources`, `Forecasting` sont accessibles via `/bi/alert-rules`, `/bi/data-stories`, `/bi/external-data-sources`, `/bi/forecasting`), plus les pages historiques (`/bi`, `/bi/analytics`, `/bi/kpis`, `/bi/nl-query`, `/bi/sql-editor`, `/bi/alerts`, `/bi/data-sources`, `/bi/reports`, `/bi/dashboards/builder`, `/bi/dashboards/{dashboard}`) servies par `BiWebController`.

## Services

- **`AlertService` / `RealTimeAlertService`** — création, test et déclenchement des règles d'alerte, déduplication et escalade.
- **`DataSourceService`** — CRUD des sources de données, config de connexion chiffrée (AES-256), test de connexion, orchestration des connecteurs (`Connectors\MysqlConnector`, `PostgresConnector`, `RestApiConnector`, `CsvConnector`, `GoogleSheetsConnector`).
- **`ExternalDataIntegrationService`** — synchronisation périodique des sources externes.
- **`QueryRunnerService`** — exécution sécurisée des requêtes BI sauvegardées.
- **`PredictiveAnalyticsService` / `EnhancedPredictiveAnalyticsService`** — entraînement et génération de prévisions, détection d'anomalies.
- **`AdvancedAnalyticsService`** — calculs analytiques transverses.
- **`AdvancedReportBuilderService` / `ReportSchedulingService`** — construction et planification de rapports.
- **`AdvancedVisualizationService`** — rendu de visualisations personnalisées.
- **`DataStorytellingService`** — génération automatique de "data stories" (narration de données).
- **`DrillDownService`** — navigation en profondeur sur un widget.
- **`EmbedTokenService`** — émission/révocation des jetons d'intégration en marque blanche.
- **`ExportService`** — export dataset/dashboard/widget.
- **`AI\BiAIService`** — appels IA (narration, suggestions KPI/dashboard, détection de tendance) via `Modules\Core\Services\AI\AIService`.

## Permissions RBAC

Préfixe `bi.` (`database/seeders/RolesAndPermissionsSeeder.php`), ressources `dashboard, kpi, report` × actions `view-any, view, create, update, delete`. Rôles concernés :
- `finance-manager` — accès complet `bi.*` (avec `accounting.*`)
- `sales-manager` — accès `bi.*` (avec `crm.*`)
- `inventory-analyst` — accès complet `bi.*` + `analytics.*`, en lecture seule sur `inventory.*`
- `manager` — sous-ensemble en lecture (`bi.dashboard.view-any/view`, `bi.report.view-any/view`, `bi.kpi.view-any/view`)

Ces permissions Spatie s'ajoutent au contrôle direct de rôle imposé par le middleware `role:manager,admin` sur les routes API, et aux policies dédiées (`BiAlertPolicy`, `BiAnomalyPolicy`, `BiDataSourcePolicy`, `DataStoryPolicy`, `ExternalDataPolicy`, `ForecastingPolicy`, `VisualizationPolicy`, `AlertPolicy`). Le bloc `BI_EXTRA_PERMISSIONS` (Chantier 8.2bi, 45 chaînes réparties sur 5 préfixes `bi.<ressource>.*`) couvre les 5 sous-systèmes nouvellement construits (`alertrule`, `datastory`, `externaldatasource`, `forecastmodel`, `customvisualization`) — `finance-manager`/`inventory-analyst` les couvrent déjà via leur wildcard `bi.*`.

## Dépendances avec d'autres modules

- **AI** : `BIAiAssistController` utilise `AiContextualAssistantService` ; `AI\BiAIService` utilise `Modules\Core\Services\AI\AIService`.
- **Accounting, CRM, HR, Inventory** (lecture) : `Modules\BI\Http\Controllers\Api\ExportController` importe directement `Modules\Accounting\Models\Invoice`, `Modules\CRM\Models\Lead`, `Modules\CRM\Models\Opportunity`, `Modules\HR\Models\Employee`, `Modules\Inventory\Models\Product`, `Modules\Inventory\Models\StockMovement` pour construire ses exports analytiques.
- **Shared** : la plupart des jobs asynchrones (`app/Jobs/*.php`) étendent `Modules\Shared\Jobs\BaseAsyncJob`, et plusieurs services étendent `Modules\Shared\Services\BaseService`.
- BI est un **consommateur transverse** : contrairement à Achats/Inventory/Logistics qui échangent des données métier entre eux, BI lit les données des autres modules sans logique métier propre à y écrire en retour.

## Particularités du périmètre life-mdg-erp

Le module conserve un très large socle applicatif (data storytelling, visualisations personnalisées, embed en marque blanche, connecteurs Google Sheets/REST/MySQL/Postgres) hérité intégralement de WideHalo — rien dans le code lu ne référence de module hors périmètre (Manufacturing, POS, Ecommerce), à l'exception des exports qui restent strictement scopés aux modules retenus (Accounting, CRM, HR, Inventory).
