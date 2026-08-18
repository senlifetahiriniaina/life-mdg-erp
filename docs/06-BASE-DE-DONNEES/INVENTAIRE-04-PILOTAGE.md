# Inventaire des tables — Pilotage et Reporting (4 modules)

BI, Analytics, Reporting, Strategy. Voir `SCHEMA-GENERAL.md` pour la méthode et la légende **réelle / stub patché / stub pur**.

## BI (45 tables : 32 réelles, 13 stub dont 11 patchées / 2 pures)

Module reconstruit en profondeur au Chantier 8.2 : les tables `bi_dashboards`/`bi_reports` et 10 des 13 autres tables `bi_*` d'origine stub plantaient silencieusement 5 pages réellement routées avant d'être patchées.

| Table | Rôle |
|---|---|
| `bi_kpis` | Définition de KPI — valeur courante, cible, seuils warning/critique, tendance |
| `bi_embed_tokens` | Jeton d'intégration pour dashboards en marque blanche (embed public, `EmbedTokenService`) |
| `bi_alert_rules` / `bi_alert_conditions` / `bi_alert_deduplication` / `bi_alert_escalations` / `bi_alert_history` / `bi_alert_recipients` / `bi_dnd_schedules` | Moteur d'alerte configurable avec déduplication et escalade (`AlertRule`, 7 tables — construit en entier au Chantier 8.2, policy `AlertPolicy` au nom non conventionnel donc jamais auto-découverte) |
| `bi_data_stories` / `bi_story_slides` / `bi_story_views` / `bi_story_analytics` / `bi_narrative_flows` | Data storytelling — narration automatique de données (`DataStory`, 5 tables, construit au Chantier 8.2) |
| `bi_external_data_sources` / `bi_sync_configurations` / `bi_sync_history` / `bi_field_mappings` / `bi_transformation_rules` / `bi_external_credentials` | Intégration de sources externes — CSV, Google Sheets, MySQL/Postgres, REST API (`ExternalDataSource`, 6 tables) |
| `bi_forecast_models` / `bi_forecast_predictions` / `bi_forecast_scenarios` / `bi_seasonality_patterns` / `bi_model_retraining_logs` / `bi_trend_analysis` / `bi_scenario_predictions` | Prévision et modèles prédictifs internes au module BI (`ForecastModel`, 7 tables) — distinct des tables `forecast_*` du module **Analytics** |
| `bi_custom_visualizations` / `bi_visualization_templates` / `bi_visualization_performance` | Visualisations personnalisées (`CustomVisualization`, 3 tables) — un bug de cohérence de migration a été corrigé avant commit : `company_id` était `NOT NULL` ici alors que les 4 migrations sœurs du même lot le laissaient `nullable()` (convention établie du dépôt, les utilisateurs API/démo n'ont souvent pas de `company_id`) |
| `bi_widget_forecasts` / `bi_widget_objectives` | Liens widget ↔ prévision/objectif |

**Tables stub, patchées depuis (11)** : `bi_dashboards` (2), `bi_data_sources` (3 — la plus retouchée du module), `bi_predictive_models` (2), `bi_reports` (2), `bi_alert_events`, `bi_alerts`, `bi_anomalies`, `bi_forecasts`, `bi_kpi_alerts`, `bi_queries`, `bi_scheduled_reports`, `bi_widgets`. **Stub pures** : *(aucune restante après le Chantier 8.2 — les 13 tables stub d'origine ont toutes reçu au moins un patch correctif)*. Les 5 sous-systèmes ci-dessus (`AlertRule`, `DataStory`, `ExternalDataSource`, `ForecastModel`, `CustomVisualization`) ont chacun reçu, au Chantier 8.2, un `Gate::policy()` explicite dans `BIServiceProvider::registerPolicies()` (aucun n'était auto-découvrable — nom de policy ne correspondant pas au nom du modèle) et un bloc `BI_EXTRA_PERMISSIONS` (45 permissions `bi.<resource>.*`).

`BIController` (scaffold mort, zéro route, vues Blade jamais réelles dans cette app Inertia) a été supprimé au même chantier.

**Gap non documenté ailleurs** : trois modèles listés dans `docs/03-MODULES/BI.md` déclarent une table qui n'existe dans **aucune** migration du dépôt — `AudienceSegment` (`bi_audience_segments`), `DataRefreshSchedule` (`bi_data_refresh_schedules`), `TimeseriesData` (`bi_timeseries_data`). Contrairement aux 13 tables stub ci-dessus (qui existent au moins au schéma générique), celles-ci n'ont même pas de scaffold — premier appel réel garanti en « table not found ».

## Analytics (15 tables, toutes réelles — aucun stub, module le plus propre du groupe)

Confirmé par le Chantier 8.5ars comme la référence « déjà correcte » du groupe — scoping tenant par `company_id` partout dès l'origine, contrairement à Reporting/Strategy.

| Table | Rôle |
|---|---|
| `forecast_models` / `forecast_predictions` / `forecast_alerts` / `forecast_scenarios` | Modèle de prévision, prédictions, alertes, scénarios what-if — `ForecastModel::forTenant()`/`ForecastAlert::forTenant()` confirmés correctement scopés |
| `ml_models` / `ml_model_versions` | Modèle ML générique avec versionnement, déploiement/rollback |
| `model_metrics` / `model_accuracy_metrics` | Métriques de modèle |
| `prediction_models` / `prediction_inputs` / `prediction_results` | Modèle de prédiction générique, entrées, résultats |
| `recommendations` / `recommendation_models` / `user_interactions` | Moteur de recommandation et interactions utilisateur — `Route::apiResource('recommendations', ...)` enregistrait `update`/`destroy` que le contrôleur n'implémente pas (erreur fatale garantie si atteint), restreint à `->only(['index','store','show'])` au Chantier 8.5ars |
| `ab_test_runs` | Test A/B entre versions de modèle ML |

Les modèles/tables de « prévision de capacité de production » (`work_centers`, `manufacturing_orders`, `bom_components`) documentés comme exclus dans `CLAUDE.md` Known Gaps n'apparaissent nulle part dans ce schéma — ils dépendent entièrement du module Manufacturing absent.

## Reporting (7 tables, toutes réelles — aucun stub)

| Table | Rôle |
|---|---|
| `report_definitions` | Définition de rapport réutilisable — template de requête, schéma de paramètres, format de sortie, portée tenant/global. Scopes `visibleTo()`/`active()`/`forModule()`/`forTenant()` |
| `report_executions` | Historique d'exécution — paramètres utilisés, résultat, fichier exporté |
| `report_schedules` | Planification récurrente avec livraison automatique — `ReportGenerationService`/ses jobs (`RunReportJob`, `DeliverScheduledReportJob`) gardent un fallback tenant `?? 1` non corrigé, confirmé jamais dispatchés nulle part dans l'app (pas de contexte de requête réel pour en dériver un vrai tenant) |
| `report_shares` | Partage avec utilisateurs ou rôles spécifiques |
| `report_widgets` / `dashboards` | Widget de tableau de bord et sa configuration |
| `saved_queries` | Requête réutilisable enregistrée, notamment issue du NL→SQL |

Fuite cross-tenant réelle corrigée au Chantier 8.5ars, avec checkpoint utilisateur préalable (large surface) : `ReportingController` (30 sites) et `ReportingService::execute()/schedule()`/`runQuery()` utilisaient `$user->tenant_id ?? 1` — toutes les entreprises partageaient silencieusement le même « bucket » tenant 1. Un second bug, plus sévère, a été trouvé dans `DashboardService` : chaque widget de dashboard ne renseignait jamais `params.tenant_id`, donc le fallback `?? 1` du resolver était systématiquement atteint — **tous les dashboards de tous les tenants affichaient silencieusement les données du tenant 1**, pas seulement les requêtes sans tenant.

## Strategy (18 tables, toutes réelles — aucun stub)

Le 7ᵉ pilier fondateur. Toutes les tables sont créées par une seule migration module (`Modules/Strategy/database/migrations/2026_08_16_000005_create_strategy_tables.php`) — le seul module de ce fichier dont *aucune* table ne vit dans les migrations racine.

| Table | Rôle |
|---|---|
| `strategy_plans` | Plan stratégique (contient des objectifs) |
| `strategy_objectives` / `strategy_key_results` | Arbre OKR (Objectives → Key Results) |
| `strategy_kpis` / `strategy_kpi_values` | Définition de KPI et historique de valeurs |
| `strategy_ratios` / `strategy_ratio_snapshots` | Ratio (numérateur/dénominateur) et instantanés historiques — voir `Modules/Strategy/app/Services/KPIRegistryService.php` pour les 32 ratios cross-module définis (Accounting, CRM, Inventory, Sales, Helpdesk, HR basique) |
| `strategy_industry_benchmarks` | Benchmarks P25/Médiane/P75 par pays/secteur |
| `strategy_correlations` | Corrélations de Pearson entre séries de KPI, base de connaissance pré-seedée |
| `strategy_alerts` | Alertes déclenchées par déviation de ratio |
| `strategy_kros` | Key Result Objectives (liaison objectifs ↔ KPI) |
| `strategy_scenarios` / `strategy_scenario_assumptions` | Scénarios de simulation what-if |
| `strategy_rituals` / `strategy_ritual_sessions` | Rituels de gouvernance (comité stratégique) |
| `strategy_signals` | Signal d'alerte généré par `SignalEngineService` |
| `strategy_objective_links` / `strategy_pillars` | Liaison polymorphe objectif ↔ ressource d'un autre module, piliers stratégiques |

Fuite cross-tenant réelle corrigée au Chantier 8.5ars (checkpoint utilisateur préalable) : les 9 contrôleurs API + `Web/StrategyPageController` acceptaient un header/paramètre `X-Tenant-Id`/`tenant_id` **contrôlé par le client** — remplacé par un helper privé `tenantId()` dérivant uniquement `$user->company_id` (casté en string, ces colonnes `tenant_id` étant `string` ici contrairement à celles de Reporting). Bug d'active-breakage trouvé au même chantier : `StrategyServiceProvider` n'enregistrait aucune policy auprès du Gate — `StrategyObjectiveLinkController` (feature complète de liaison OKR↔Cascade) retournait un 403 inconditionnel à tout le monde, y compris les admins.

Les ratios `training_roi`/`time_to_fill` (HR) retournent des valeurs de repli statiques (145.0 / 28 jours) — dépendent des fonctionnalités Training/ATS hors périmètre, documenté dans `CLAUDE.md` Known Gaps comme le même motif de fallback que le reste de `KPIRegistryService`.
