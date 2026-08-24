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
| POST | `bi/ai/narrative`, `bi/ai/analyze-objectives`, `bi/ai/forecast-compare`, `bi/ai/suggest-alignment` | Analyse IA avancée (narration, objectifs, comparaison de prévision) — seul `narrative` délègue réellement à `BiAIService`, les 3 autres renvoient une réponse statique en conserve (voir note Chantier 10 ci-dessous) |
| POST | `bi/ai/detect-deviations`, GET `bi/forecast-sources`, `bi/objective-catalog`, `bi/widgets/{id}/objectives` | Alignement widget ↔ objectifs stratégiques — réponses statiques en conserve, zéro consommateur Vue/test (Chantier 10) |
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

**Chantier 10 — re-vérification transverse** : `bi_kpi_history` (consommée par `Kpi::history()`, eager-loadée par `KpiController::show()`) était restée en stub `id/tenant_id/name/config/timestamps` malgré un modèle réel (`KpiHistory`, `$fillable = ['kpi_id','value','recorded_at']`) — patchée additivement (`2026_09_04_000001_patch_bi_kpi_history_table.php`). Deux routes mortes supprimées : `bi/widgets/{id}/link-objective`/`link-forecast` pointaient vers `AiBiController::linkObjective()`/`linkForecast()`, qui n'ont jamais existé (zéro appelant Vue/test) ; le reste du contrôleur (`analyzeObjectives`, `forecastCompare`, `suggestAlignment`, `detectDeviations`, `forecastSources`, `objectiveCatalog`, `widgetObjectives`) reste en place mais est documenté comme réponses statiques en conserve, zéro consommateur réel — construire l'alignement widget↔objectif pour de vrai supposerait d'inventer un schéma de liaison et une logique de scoring, laissé comme lacune documentée. `resources/js/Pages/BI/SqlEditor.vue` appelait `POST /api/v1/bi/queries/run` (jamais une route réelle) au lieu de `POST /api/v1/bi/queries/run-raw` — corrigé.

## Vues (Vue/Inertia)

`Modules/BI/resources/js/Pages/` : `Visualizations/`, `DataStories/`, `Forecasting/`, `AlertRules/`, `AINarratives/`, `PredictiveAnalytics/`, `ExternalDataSources/` — les 4 dernières venant du Chantier 8.2bi (`AlertRules`, `DataStories`, `ExternalDataSources`, `Forecasting` sont accessibles via `/bi/alert-rules`, `/bi/data-stories`, `/bi/external-data-sources`, `/bi/forecasting`), plus les pages historiques (`/bi`, `/bi/analytics`, `/bi/kpis`, `/bi/nl-query`, `/bi/sql-editor`, `/bi/alerts`, `/bi/data-sources`, `/bi/reports`, `/bi/dashboards/builder`, `/bi/dashboards/{dashboard}`) servies par `BiWebController`.

**Chantier 10** : `resources/js/Pages/BI/Visualizations/Index.vue` (racine) masquait `Modules/BI/resources/js/Pages/Visualizations/Index.vue` — même mécanisme prioritaire `resolve()` déjà documenté pour Calendar, mais avec la direction inversée : le fichier racine servi (287 lignes) était une galerie de types de graphiques 100 % statique (zéro `axios`/`fetch`), tandis que le fichier module masqué (396 lignes) est le vrai gestionnaire CRUD (liste, création, édition, partage, export, temps réel) déjà branché sur `/api/v1/bi/visualizations*`. Le fichier racine mock a été supprimé ; `BiWebController::visualizations()` sert maintenant le vrai gestionnaire via le repli module de `resolve()`.

**Chantier 32.24 (audit approfondi 14 couches)** : `BiAlert::biQuery()` utilisait la convention Laravel implicite (`bi_query_id`) alors que la vraie colonne, `query_id`, ne correspond pas à ce nom de méthode camelCase — la relation retournait toujours `null` pour toute alerte réellement liée à une requête, confirmé empiriquement via tinker avant correction (clé étrangère explicite ajoutée). `AlertService::checkAlert()`/nouvelle `refreshValue()` résolvent désormais une vraie valeur courante depuis la `BiQuery` liée avant d'évaluer la condition — avant ce correctif, `bi_alerts.last_value` n'était jamais écrit par aucun code réellement atteignable (son seul écrivain était `EvaluateAlertRulesJob::fetchMetricValue()`, un job confirmé mort/factice — `rand(10,1000)/10` — et supprimé dans ce même chantier), donc `POST bi/alerts/{alert}/test` répondait toujours `triggered: false` quelle que soit la donnée réelle. `CheckBiAlertsJob` (le seul job asynchrone réel du module — voir section « Jobs asynchrones ») est désormais réellement planifié (`bi:check-alerts`, toutes les 15 minutes) ; les 14 autres jobs du module, tous confirmés morts/factices (théâtre `rand()`/données codées en dur, zéro appelant réel), ont été supprimés, avec 6 services orphelins dupliquant les mêmes concepts.

## Services

**Chantier 32.24 (audit approfondi 14 couches)** a supprimé 6 services orphelins jamais utilisés hors de ce fichier (zéro appelant réel confirmé par grep exhaustif, zéro référence de test) — `RealTimeAlertService`, `ExternalDataIntegrationService`, `DataConnectorService`, `EnhancedPredictiveAnalyticsService`, `DataStorytellingService`, `AdvancedVisualizationService` — chacun un doublon confirmé, en pire, du contrôleur réel/routé équivalent (`AlertController`/`AlertRuleController` pour les alertes, `DataSourceController`+`Connectors\*` pour l'intégration externe, `PredictiveAnalyticsController` pour les prévisions, `DataStoryController` pour les stories, `VisualizationController` pour les visualisations). Voir la section « Jobs asynchrones » ci-dessous pour le détail du même constat sur 14 jobs.

- **`AlertService`** — création, test et déclenchement des règles d'alerte. Depuis Chantier 32.24, `checkAlert()`/`refreshValue()` résolvent une vraie valeur courante via `QueryRunnerService::runQuery()` pour une alerte liée à une `BiQuery` (`query_id`) — avant ce correctif, `bi_alerts.last_value` n'était jamais écrit par aucun chemin de code réel, donc `POST bi/alerts/{alert}/test` renvoyait toujours `triggered: false`. Une alerte liée à un widget (`widget_id`, sans `query_id`) reste un gap documenté : aucun mécanisme réel de résolution de la valeur courante d'un widget n'existe ailleurs dans ce module (`DrillDownService::getWidgetBaseData()` est lui-même un stub retournant `[]`, Chantier 29) — construire cette résolution serait inventer une nouvelle logique métier, pas une correction de câblage.
- **`DataSourceService`** — CRUD des sources de données, config de connexion chiffrée (AES-256), test de connexion, orchestration des connecteurs (`Connectors\MysqlConnector`, `PostgresConnector`, `RestApiConnector`, `CsvConnector`, `GoogleSheetsConnector`).
- **`QueryRunnerService`** — exécution sécurisée des requêtes BI sauvegardées ; désormais aussi consommée par `AlertService::refreshValue()`.
- **`PredictiveAnalyticsService`** — entraînement et génération de prévisions (régression linéaire/moyenne mobile), détection d'anomalies — le seul moteur prédictif réel du module, déjà routé via `PredictiveAnalyticsController`.
- **`DrillDownService`** — navigation en profondeur sur un widget (stub `getWidgetBaseData()`, gap documenté depuis Chantier 29).
- **`EmbedTokenService`** — émission/révocation des jetons d'intégration en marque blanche.
- **`ExportService`** — export dataset/dashboard/widget.
- **`AI\BiAIService`** — appels IA (narration, suggestions KPI/dashboard, détection de tendance) via `Modules\Core\Services\AI\AIService`.

## Jobs asynchrones (Chantier 32.24)

`Modules/BI/app/Jobs/` ne contient plus qu'un seul job : **`CheckBiAlertsJob`** — réel, correctement écrit (requête les `BiAlert` actives, délègue à `AlertService::checkAlert()`), mais jamais planifié nulle part avant ce chantier. Désormais planifié pour de vrai (`BIServiceProvider::registerCommandSchedules()`, motif `callAfterResolving(Schedule::class, ...)` déjà établi pour Analytics/Helpdesk/Sales — `bi:check-alerts`, toutes les 15 minutes).

**14 autres jobs supprimés** (`AnalyzeStoryEngagementJob`, `CreateDataStoryJob`, `EscalateUnacknowledgedAlertsJob`, `EvaluateAlertRulesJob`, `EvaluateForecastAccuracyJob`, `ExportVisualizationJob`, `GenerateAlertDigestJob`, `GenerateForecastJob`, `GenerateVisualizationDataJob`, `PublishDataStoryJob`, `RenderCustomChartJob`, `SendAlertNotificationJob`, `SyncExternalDataSourceJob`, `TrainForecastModelJob`, `TransformAndValidateDataJob`) — confirmés à la fois **morts** (zéro appelant réel nulle part dans l'app, uniquement quelques références internes croisées entre eux et depuis les 2 services orphelins listés ci-dessus) et **factices** : chacun contenait un ou plusieurs marqueurs de théâtre littéral (`rand()`, données codées en dur, commentaires "In a real implementation..."/"Simulate..."/"For now, create a basic..."). Deux constats notables : `TrainForecastModelJob`/`GenerateForecastJob`/`EvaluateForecastAccuracyJob` opéraient en réalité sur `PredictiveModel` (pas `ForecastModel`, malgré leur nom) via des données d'entraînement 100% simulées (`rand(100,1000)`) — un doublon cassé et inférieur du vrai `PredictiveAnalyticsService::trainLinearRegression()`/`trainMovingAverage()`/`generateForecasts()`, déjà réel et routé ; `ExportVisualizationJob` (PDF+PPTX d'un dashboard) était le « gap déjà documenté » que ce chantier avait pour mandat explicite de trancher (voir CLAUDE.md Chantier 29) — confirmé mort : sa partie PDF duplique l'`ExportService::exportDashboardPdf()` déjà réel et fonctionnel (Chantier 29), et sa partie PPTX construisait à la main une archive ZIP minimale sans jamais utiliser `PhpOffice\PhpPresentation` (non installé dans ce projet), un fichier PPTX invalide en pratique.

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
- **Shared** : plusieurs services étendaient `Modules\Shared\Services\BaseService` avant Chantier 32.24 (`RealTimeAlertService`, `ExternalDataIntegrationService`, tous deux supprimés — confirmés morts) ; le module n'a plus aucun consommateur réel de `Modules\Shared\Jobs\BaseAsyncJob` depuis que les 14 jobs qui en héritaient (tous confirmés morts/factices) ont été supprimés — seul `CheckBiAlertsJob` subsiste, un `ShouldQueue` simple sans base commune.
- BI est un **consommateur transverse** : contrairement à Achats/Inventory/Logistics qui échangent des données métier entre eux, BI lit les données des autres modules sans logique métier propre à y écrire en retour.

## Particularités du périmètre life-mdg-erp

Le module conserve un très large socle applicatif (data storytelling, visualisations personnalisées, embed en marque blanche, connecteurs Google Sheets/REST/MySQL/Postgres) hérité intégralement de WideHalo — rien dans le code lu ne référence de module hors périmètre (Manufacturing, POS, Ecommerce), à l'exception des exports qui restent strictement scopés aux modules retenus (Accounting, CRM, HR, Inventory).
