# Analytics

## Rôle

Le module Analytics porte le moteur de prévision IA transverse (demande, trésorerie, RH, production), les modèles ML génériques (avec versions et A/B tests), un moteur de recommandation, et l'assistance IA contextuelle par écran. Il complète BI dans le pôle **Pilotage et Reporting**, avec une orientation plus "modèles/algorithmes" que "tableaux de bord".

**Chantier 32.25 (audit approfondi en 14 couches)** a re-vérifié ce module en profondeur — les couches 1-7 avaient déjà été passées en revue à plusieurs reprises (Chantier 8.5ars, 19 Lot 4-5, 26 volet A), donc l'effort a porté principalement sur les couches 8-14 (validation métier, fake/dead, relationnel, CORE, API, IA, le 14ᵉ bloc). Plusieurs bugs réels — dont deux trous de cloisonnement multi-tenant sévères — ont été trouvés par exécution empirique et corrigés ; voir « Chantier 32.25 » ci-dessous pour le détail complet.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `ForecastModel`, `ForecastPrediction`, `ForecastAlert`, `ForecastScenario` | `forecast_models`, `forecast_predictions`, `forecast_alerts`, `forecast_scenarios` | Modèle de prévision, ses prédictions, ses alertes et ses scénarios what-if — toutes les relations `hasMany()`/`belongsTo()` pointent réellement sur la vraie colonne NOT NULL `forecast_model_id` (corrigé au Chantier 26 volet A) |
| `MLModel`, `MLModelVersion`, `ModelMetric`, `ModelAccuracyMetric` | `ml_models`, `ml_model_versions` | Modèle ML générique avec versionnement, déploiement/rollback |
| `PredictionModel`, `PredictionInput`, `PredictionResult` | `prediction_models` et tables liées | Modèle de prédiction générique, ses entrées et résultats |
| `Recommendation`, `RecommendationModel`, `UserInteraction` | `recommendations`, `recommendation_models` | Moteur de recommandation et interactions utilisateur associées |
| `ABTestRun` | `ab_test_runs` | Test A/B entre versions de modèle ML |

## Endpoints principaux

Deux ensembles de routes, tous deux sur `auth:sanctum` + `module:Analytics` + `role:employee,inventory-analyst,manager,admin` (harmonisé au Chantier 8.5ars) :

**Prévision (`v1/forecasting`)**

| Méthode | Route | Description |
|---|---|---|
| GET/POST/PUT | `models`, `models/{id}` | Modèles de prévision |
| POST | `models/{id}/train` | Entraînement d'un modèle |
| GET | `models/{id}/predictions` | Prédictions d'un modèle |
| GET | `alerts`, POST `alerts/{id}/acknowledge` | Alertes de prévision |
| GET/POST | `scenarios`, `scenarios/compare` | Scénarios what-if et comparaison — **cloisonnés par société depuis le Chantier 32.25** (voir plus bas) |
| GET | `demand/{productId}`, `demand` | Prévision de demande produit |
| GET | `cashflow`, `cashflow/export/{pdf,excel}` | Prévision de trésorerie (30/60/90/180j, Chantier 26 volet A) + export |
| GET | `hr/headcount`, `hr/turnover-risk`, `hr` | Prévision RH (effectifs, risque de turnover) |
| GET | `production` | Prévision de production — dégrade proprement (`available:false`) si le schéma Manufacturing est absent, depuis le Chantier 32.25 |
| POST | `ai/analyze`, `ai/narrative` | Analyse IA libre et narration de prévision (Claude) |
| GET | `hub` | Résumé consolidé (demande + trésorerie + RH + production + alertes) |

**Analytics (`v1/analytics`)**

| Méthode | Route | Description |
|---|---|---|
| GET/POST/GET-1 | `predictions` (`->only(['index','store','show'])`) | Modèles de prédiction (`PredictionController`) |
| POST | `predictions/{id}/train`, GET `.../results` | Entraînement (`authorize('train', ...)` ajouté au Chantier 32.25) et résultats |
| GET/POST/GET-1 | `recommendations` (`->only(['index','store','show'])`) | Recommandations — `update`/`destroy` volontairement exclues (Chantier 8.5ars) ; `recipient_type`/`recommended_type` restreints à une liste blanche depuis le Chantier 32.25 (voir plus bas) |
| GET | `recommendations/for-user`, POST `.../act`, `.../dismiss` | Cycle de vie d'une recommandation |
| CRUD | `ml-models` (nommé `ml_model`) | Modèles ML |
| GET | `ml-models/{id}/versions`, `.../ab-tests`, POST `.../deploy`, `.../rollback` | Versionnement et déploiement — `rollback()` vérifie désormais que la version appartient bien au modèle ciblé (Chantier 32.25) |

**IA Assisted First** : `POST v1/analytics/ai/assist` (`auth:sanctum`).

**Note** : le sous-registre de détection d'anomalies propre à Analytics (`AnomalyDetectionController`) a été supprimé — doublon orphelin. La détection d'anomalies réellement utilisée dans l'ERP vit dans `Modules\AI\Http\Controllers\Api\AiAnomalyController`.

## Contrôleurs

6 contrôleurs : `Api/ForecastingController`, `Api/AnalyticsAiAssistController`, `Api/CashflowForecastExportController` (Chantier 26 volet A), `PredictionController`/`RecommendationController`/`MLModelController` (directement sous `Http/Controllers/`, pas `Api/`), `Web/AnalyticsWebController`.

## Vues (Vue/Inertia)

Deux pages : `Modules/Analytics/resources/js/Pages/Index.vue` (hub, `/analytics` — un onglet Vue d'ensemble/Prédictions/Recommandations/Modèles ML, avec assistance IA contextuelle) et `Modules/Analytics/resources/js/Pages/CashflowForecast/Index.vue` (`/analytics/cashflow-forecast`, Chantier 26 volet A — sélecteur d'horizon, sparkline SVG, exports PDF/Excel). Depuis le Chantier 32.25, la page hub porte un lien réel vers la page trésorerie (auparavant atteignable uniquement par URL directe malgré que le hub soit la seule page du module déjà dans la navigation principale de l'app).

## Services

- **`ForecastingEngineService`** — moteur central : `collectHistoricalData()` (agrège selon le module cible : `demand`, `cashflow`, `hr`, `production`, `revenue`, `inventory`), puis applique un algorithme (`movingAverage`, `exponentialSmoothing`, `linear_regression`, ou narration `ai_claude`). `createScenario()`/`compareScenarios()` requièrent désormais un `$tenantId` réel (Chantier 32.25 — voir Sécurité).
- **`AiForecastNarrativeService`** — narrative IA (facteurs clés, risques, actions recommandées) via Claude, repli statique fr/en si l'API est absente/échoue.
- **`Forecasting\DemandForecastService`** — prévision de demande produit/catégorie à partir de `sales_order_lines`/`sales_orders` (réellement cloisonné par `so.tenant_id`, une colonne réellement peuplée par Sales avec le vrai `company_id`). `suggestReorderPoints()` filtre en plus `inventory_products.tenant_id` — **confirmé toujours vacant pour toute donnée réelle** (`ProductController::store()` peuple la colonne fantôme `users.tenant_id`, jamais `company_id` — bug racine hors périmètre de ce module, documenté au Chantier 32.25, non corrigé ici).
- **`Forecasting\CashflowForecastService`** — projection de trésorerie sur horizon réel (30/60/90/180j) à partir du grand livre OHADA réel (`acc_journal_entry_lines`/`acc_journal_entries`/`acc_chart_of_accounts`, classe 5) et `acc_invoices`. `buildNarrative()` citait auparavant "90 jours" quel que soit l'horizon réellement demandé — corrigé au Chantier 32.25.
- **`Forecasting\HrForecastService`** — prévision d'effectifs, risque de turnover, coût de paie. **Deux bugs actifs corrigés au Chantier 32.25** : `getUnusedLeaveDays()` interrogeait `hr_leave_balances`, une table supprimée par l'audit HR (Chantier 32.17) sans que son propre grep de consommateurs ne voie cette requête `DB::table()` brute d'un autre module — `predictTurnoverRisk()` (un vrai endpoint routé) plantait fatalement sur chaque employé actif réel ; recalculé sur la vraie formule déjà utilisée par `EmployeeSelfServiceController::leaveBalance()`. `suggestRoles()` interrogeait `job_postings`, une table qui n'existe dans aucune migration de ce dépôt (ATS/recrutement hors périmètre HR "basique") — `forecastHeadcount()` plantait dès qu'un écart positif était calculé ; dégrade désormais proprement vers le repli déjà écrit.
- **`Forecasting\ProductionForecastService`** — utilisation de capacité, goulots, consommation matière. **Confirmé garanti-fatal sur chaque appel réel avant le Chantier 32.25** (`work_centers`/`manufacturing_orders`/`bom_components` n'existent dans aucune migration — Manufacturing hors périmètre Life MDG). Corrigé pour dégrader proprement (structure vide/zéro + `available:false`) plutôt que de renvoyer une erreur SQL brute à l'appelant — Manufacturing ne reviendra jamais dans ce périmètre, donc plus de valeur à laisser planter qu'à documenter le crash sans le corriger.

## Sécurité approfondie (couche 6) — Chantier 32.25

Trois trous de cloisonnement société réels, tous confirmés empiriquement avant correction :

1. **`ForecastingEngineService::createScenario()`/`compareScenarios()`** — `ForecastModel::findOrFail($modelId)`/`ForecastScenario::whereIn('id', ...)->get()` sans aucun filtre société : n'importe quel utilisateur authentifié pouvait créer un scénario contre le `ForecastModel` d'une autre société (l'historique réel de cette société était alors collecté et renvoyé dans la réponse 201) et comparer/lire les hypothèses/prédictions de n'importe quel scénario d'une autre société par simple devinette d'id. Les deux méthodes exigent désormais un `$tenantId` réel, résolu comme partout ailleurs dans ce contrôleur (`company_id`, jamais la colonne fantôme `users.tenant_id`).
2. **`MLModelPolicy`/`PredictionModelPolicy`/`RecommendationModelPolicy`/`RecommendationPolicy`/`ABTestRunPolicy`** — bug de précédence PHP (`&&` lie plus fort que `||`) dans les 5 fichiers : `$condition && ($companyMatch && $permission) || $user->hasRole('admin')` plaçait le bypass `admin` **en dehors** de toute vérification de société ou de statut métier, à cause de la précédence de l'opérateur. Confirmé empiriquement qu'un `admin` de la société A pouvait consulter/modifier/déployer/rollback le modèle ML d'une société B (`'admin'` est un rôle Spatie global dans cette app, jamais scopé par société). Corrigé selon le même précédent déjà établi au Chantier 31 pour `ApprovalRequestPolicy` : le bypass admin reste réel mais toujours scopé à la même société.
3. **`MLModelController::rollback()`** — acceptait n'importe quel `version_id` existant sans vérifier qu'il appartient au modèle ciblé (contrairement à `deploy()`, juste au-dessus, qui fait déjà cette vérification) — `production_version`/`production_accuracy` pouvaient être écrasés avec les valeurs d'un enregistrement d'une autre société. Corrigé avec la même vérification que `deploy()`.
4. **`Recommendation::MORPH_TYPE_ALIASES`** — `recipient_type`/`recommended_type` (des `morphTo()` génériques) n'étaient validés qu'en `'string'` — un appelant pouvait y placer le FQCN réel d'un modèle sensible (ex. `App\Models\User`) au lieu de l'alias générique (`'Customer'`/`'Product'`) déjà attendu par le frontend/les tests. Confirmé empiriquement qu'un `GET .../recommendations/{id}` avec ce `recipient` ainsi eager-loadé renvoyait les vraies colonnes (email, etc.) de ce modèle arbitraire, y compris d'une autre société. Corrigé avec une liste blanche (`Customer`/`Contact`/`Employee`/`Product`/`Opportunity`) validée côté requête et enregistrée en morph-map dans `AnalyticsServiceProvider::boot()` — même motif déjà documenté pour `HelpdeskLinkable`.

## Permissions RBAC

Préfixe `analytics.` — deux sources dans `database/seeders/RolesAndPermissionsSeeder.php` : la boucle générique `MODULES['analytics'] => ['forecast', 'anomaly']` (verbes standard `view-any/view/create/update/delete`), **et** la constante dédiée `ANALYTICS_PERMISSIONS` (confirmée réellement seedée et fusionnée dans `$allPermissions`) qui couvre les verbes non-standard des 5 policies ML/recommandation (`ml_model.deploy`/`.rollback`, `prediction.train`, `recommendation.act`/`.dismiss`/`.train`, `ab_test.start`/`.complete`/`.deploy`). `inventory-analyst` reçoit `analytics.*` en wildcard complet ; `manager`/`employee`/`admin` héritent aussi de ces permissions via la construction générique de leurs rôles. Le sous-système de prévision (`v1/forecasting/*`) n'a pas de Policy dédiée — sa protection vient du scoping `company_id` en base (désormais complet, y compris scénarios) et du verrou `role:` de route.

## Dépendances avec d'autres modules

- **AI** : `AnalyticsAiAssistController` utilise `AiContextualAssistantService` — le module `'Analytics'` y est enregistré avec l'action `view_dashboard` (Chantier 32.2), consommée réellement par `Index.vue` et `CashflowForecast/Index.vue`, avec un texte de repli fr/en ancré sur ce que ces deux écrans affichent réellement (Chantier 32.2/26A).
- **Lecture cross-module via requêtes SQL directes** (`DB::table()`, jamais les modèles Eloquent des modules cibles) : Sales (`sales_orders`, `sales_order_lines` — réellement cloisonné par tenant), Accounting (`acc_chart_of_accounts`, `acc_invoices`, `acc_journal_entry_lines`/`acc_journal_entries` — grand livre partagé sans colonne tenant, même précédent qu'`OhadaReportService`), HR (`hr_employees`, `hr_employee_compensation`, `hr_leave_requests`, `hr_leave_types` — sans tenant, déploiement mono-tenant HR déjà documenté), Inventory (`inventory_stock_movements`, `inventory_products`, `inventory_stock` — sans cloisonnement société réel, motif déjà documenté au Chantier 19) et Core (`companies`).

## Particularités du périmètre life-mdg-erp

- **`Forecasting\ProductionForecastService`** interroge par `DB::table()` les tables `bom_components`, `work_centers` et `manufacturing_orders`, qui appartiennent au module Manufacturing — absent du périmètre life-mdg-erp et sans migration dans ce dépôt. **Corrigé au Chantier 32.25** : dégrade désormais proprement (`available:false`, structures vides) au lieu de laisser planter l'endpoint réel `GET forecasting/production` avec une erreur SQL brute — Manufacturing ne reviendra jamais dans ce périmètre.
- Le même motif touchait indépendamment `ForecastingEngineService::collectProductionData()`/`collectInventoryData()`/`getCurrentStock()` (chemins `train()`/`predict()`/`checkAlerts()` pour un `ForecastModel` de module `production`/`inventory`) — `collectInventoryData()`/`getCurrentStock()` repointés sur les vraies tables Inventory (`inventory_stock_movements`, `inventory_stock`), `collectProductionData()` dégradé proprement (Manufacturing reste hors périmètre).
- **Gap confirmé, non corrigé (racine hors du module Analytics)** : `DemandForecastService::suggestReorderPoints()` filtre `inventory_products.tenant_id`, une colonne jamais peuplée par le vrai chemin d'écriture d'Inventory (`ProductController::store()` écrit la colonne fantôme `users.tenant_id`, pas `company_id`) — cette fonctionnalité renvoie toujours une liste vide pour tout tenant réel. Corriger nécessiterait de toucher `Modules\Inventory`, hors périmètre de cet audit.
- **Gap mineur, non corrigé** : les 4 modèles `ForecastModel`/`ForecastPrediction`/`ForecastAlert`/`ForecastScenario` n'utilisent aucun trait d'audit (`HasAuditLog`), contrairement au reste du module (ML/prediction/recommendation). Justifiable pour les 3 modèles à fort volume (prédictions/alertes générées en masse), moins évident pour `ForecastModel` lui-même (créé/modifié par de vraies actions utilisateur) — laissé en l'état, une extension ciblée à `ForecastModel` seul serait raisonnable dans un futur chantier.
