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

## Détail des colonnes (succinct)

Extrait le 2026-08-21 directement du schéma SQLite réellement migré (`Schema::getColumns()`), pas des fichiers de migration — la source de vérité la plus fiable compte tenu du mécanisme « racine crée, module patche » documenté dans `SCHEMA-GENERAL.md`. Format : `colonne:type` — `!` = non nullable (requis). Types SQLite génériques (`integer`/`varchar`/`numeric`/`text`/`datetime`/`date`/`tinyint`) ; en production MySQL les types réels sont plus précis (`bigint unsigned`, `decimal(15,4)`, etc.) mais la structure des colonnes est identique.

### Analytics (16 tables)

**`ab_test_runs`** — `id:integer!,company_id:integer,ml_model_id:integer,control_version_id:integer,variant_version_id:integer,test_name:varchar,hypothesis:text,status:varchar!,sample_size:integer,test_split:numeric,statistical_significance:numeric,confidence_level:numeric,started_at:datetime,ended_at:datetime,results:text,winner:varchar,conclusion:text,created_by:integer,created_at:datetime,updated_at:datetime`

**`forecast_alerts`** — `id:integer!,forecast_model_id:integer!,alert_type:varchar!,severity:varchar!,message:text!,context:text,status:varchar!,triggered_at:datetime!,resolved_at:datetime,created_at:datetime,updated_at:datetime,model_id:integer,tenant_id:integer,title:varchar,predicted_date:date,predicted_value:numeric,threshold_value:numeric,is_acknowledged:tinyint!,acknowledged_by:integer,acknowledged_at:datetime`

**`forecast_models`** — `id:integer!,tenant_id:varchar!,name:varchar!,module:varchar!,entity_type:varchar,entity_id:varchar,algorithm:varchar!,horizon_days:integer!,confidence_level:numeric!,last_trained_at:datetime,next_retrain_at:datetime,is_active:tinyint!,config:text,created_at:datetime,updated_at:datetime`

**`forecast_predictions`** — `id:integer!,forecast_model_id:integer!,forecast_date:date!,predicted_value:numeric!,lower_bound:numeric,upper_bound:numeric,actual_value:numeric,error_rate:numeric,created_at:datetime,updated_at:datetime,model_id:integer,tenant_id:integer,predicted_upper_bound:numeric,error_pct:numeric,confidence:numeric,metadata:text`

**`forecast_scenarios`** — `id:integer!,forecast_model_id:integer!,name:varchar!,description:text,assumptions:text!,results:text,status:varchar!,created_at:datetime,updated_at:datetime,tenant_id:integer,base_model_id:integer,created_by:integer`

**`ml_model_versions`** — `id:integer!,ml_model_id:integer!,version:varchar,accuracy:numeric,metrics:text,status:varchar!,created_at:datetime!,updated_at:datetime,version_number:varchar,change_notes:text,validation_accuracy:numeric,validation_precision:numeric,validation_recall:numeric,validation_f1:numeric,training_samples:integer,validation_samples:integer,trained_at:datetime,model_path:varchar,training_config:text,activated_at:datetime,deactivated_at:datetime,created_by:integer`

**`ml_models`** — `id:integer!,tenant_id:varchar,name:varchar,model_type:varchar,module:varchar,status:varchar!,accuracy_score:numeric,hyperparameters:text,model_path:varchar,trained_at:datetime,created_at:datetime,updated_at:datetime,company_id:integer,model_key:varchar,model_name:varchar,model_category:varchar,framework:varchar,description:text,production_version:varchar,total_versions:integer!,production_accuracy:numeric,deployed_at:datetime,last_retrained_at:datetime,inference_count:integer!,avg_inference_time_ms:numeric,created_by:integer,deployed_by:integer,deleted_at:datetime`

**`model_accuracy_metrics`** — `id:integer!,prediction_model_id:integer!,metric_date:datetime,metric_type:varchar,metric_value:numeric,sample_size:integer,breakdown_by_segment:text,data_period:varchar,created_at:datetime,updated_at:datetime`

**`model_metrics`** — `id:integer!,ml_model_version_id:integer,metric_name:varchar!,metric_value:numeric,dataset_type:varchar,breakdown:text,created_at:datetime,updated_at:datetime`

**`prediction_inputs`** — `id:integer!,prediction_model_id:integer!,feature_name:varchar!,feature_type:varchar,data_source:varchar,field_mapping:varchar,transformation:text,importance_score:numeric,is_required:tinyint!,created_at:datetime,updated_at:datetime`

**`prediction_models`** — `id:integer!,company_id:integer,model_name:varchar!,model_type:varchar,status:varchar!,description:text,configuration:text,training_accuracy:numeric,validation_accuracy:numeric,trained_at:datetime,last_used_at:datetime,prediction_count:integer!,created_by:integer,updated_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`prediction_results`** — `id:integer!,prediction_model_id:integer!,company_id:integer,predictable_type:varchar,predictable_id:integer,prediction_score:numeric,prediction_class:varchar,feature_contributions:text,metadata:text,predicted_at:datetime,actual_outcome_at:datetime,actual_outcome:varchar,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`rate_limit_metrics`** — `id:integer!,user_id:integer,endpoint:varchar!,ip_address:varchar,user_agent:text,status_code:integer!,response_time_ms:integer,timestamp:datetime!,tenant_id:varchar,created_at:datetime`

**`recommendation_models`** — `id:integer!,company_id:integer,model_name:varchar!,recommendation_type:varchar,algorithm:varchar,status:varchar!,description:text,configuration:text,coverage_percentage:numeric,recommendation_count:integer!,click_through_count:integer!,ctr:numeric,last_trained_at:datetime,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`recommendations`** — `id:integer!,recommendation_model_id:integer!,company_id:integer,recipient_type:varchar,recipient_id:integer,recommended_type:varchar,recommended_id:integer,relevance_score:numeric,rank:integer,reason:text,metadata:text,status:varchar!,viewed_at:datetime,clicked_at:datetime,acted_at:datetime,expires_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`user_interactions`** — `id:integer!,company_id:integer,user_type:varchar,user_id:integer,interacted_item_type:varchar,interacted_item_id:integer,interaction_type:varchar,engagement_score:numeric,context:text,interacted_at:datetime,created_at:datetime,updated_at:datetime`

### BI (46 tables)

**`bi_alert_conditions`** — `id:integer!,rule_id:integer!,condition_order:integer!,operator:varchar!,value:varchar!,comparison_type:varchar!,lookback_period:integer,logic_operator:varchar!,created_at:datetime,updated_at:datetime`

**`bi_alert_deduplication`** — `id:integer!,rule_id:integer!,grouping_key:varchar!,grouped_count:integer!,first_triggered_at:datetime,last_triggered_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_alert_escalations`** — `id:integer!,rule_id:integer!,escalation_level:integer!,trigger_condition:varchar!,trigger_value:integer!,escalation_recipients:text,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`bi_alert_events`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,acknowledged_at:datetime,acknowledged_by:integer,alert_id:integer,triggered_value:numeric,threshold:numeric,message:text,severity:varchar,acknowledged:tinyint!`

**`bi_alert_history`** — `id:integer!,rule_id:integer!,status:varchar!,severity:varchar,triggered_value:numeric,condition_results:text,message:text,acknowledged_by:integer,acknowledged_at:datetime,acknowledgment_note:text,resolved_at:datetime,triggered_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_alert_recipients`** — `id:integer!,rule_id:integer!,recipient_type:varchar!,recipient_value:varchar!,notification_channel:varchar!,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`bi_alert_rules`** — `id:integer!,company_id:integer,created_by:integer,name:varchar!,description:text,metric_source:varchar!,metric_source_id:integer!,status:varchar!,is_public:tinyint!,condition_count:integer!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`bi_alerts`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,condition_type:varchar!,metric:varchar,threshold:numeric,is_active:tinyint!,metric_name:varchar,severity:varchar!,check_interval_minutes:integer!,channels:text,recipients:text,status:varchar!,widget_id:integer,query_id:integer,last_checked_at:datetime,last_triggered_at:datetime,last_value:numeric`

**`bi_anomalies`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,entity_type:varchar,entity_id:integer,metric:varchar,expected:numeric,actual:numeric,severity:varchar!,detected_at:datetime,metric_name:varchar,anomaly_date:date,expected_value:numeric,deviation_pct:numeric,actual_value:numeric,deviation_percent:numeric,description:text,status:varchar!,acknowledged_at:datetime,acknowledged_by:integer`

**`bi_custom_visualizations`** — `id:integer!,company_id:integer,created_by:integer!,dashboard_id:integer,template_id:integer,name:varchar!,description:text,type:varchar!,config:text!,data_source:text!,color_scale:text,range_config:text,real_time_enabled:tinyint!,refresh_interval:integer!,performance_score:integer,avg_render_time:numeric,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`bi_dashboards`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,description:varchar,layout:text,is_public:tinyint!,created_by:integer,user_id:integer,is_default:tinyint!`

**`bi_data_sources`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,type:varchar,connection:text,is_active:tinyint!,connection_config:text,last_synced_at:datetime,created_by:integer,status:varchar!,last_tested_at:datetime`

**`bi_data_stories`** — `id:integer!,company_id:integer,created_by:integer,title:varchar!,description:text,summary:text,status:varchar!,slide_count:integer!,is_public:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`bi_dnd_schedules`** — `id:integer!,rule_id:integer!,recipient_value:varchar!,start_time:varchar!,end_time:varchar!,days_of_week:varchar!,start_date:date,end_date:date,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`bi_embed_tokens`** — `id:integer!,dashboard_id:integer!,tenant_id:integer!,token_hash:varchar!,jti:varchar!,allowed_domains:text!,expires_at:datetime!,created_by:integer,revoked_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_external_credentials`** — `id:integer!,source_id:integer!,credential_type:varchar!,encrypted_value:text!,expires_at:datetime,last_used_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_external_data_sources`** — `id:integer!,company_id:integer,created_by:integer,name:varchar!,description:text,source_type:varchar!,status:varchar!,connection_config:text,authentication_type:varchar!,is_test_connection:tinyint!,last_test_at:datetime,last_successful_sync:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`bi_field_mappings`** — `id:integer!,source_id:integer!,source_field:varchar!,target_field:varchar!,data_type:varchar!,transformation_rule:text,is_primary_key:tinyint!,is_mapped:tinyint!,created_at:datetime,updated_at:datetime`

**`bi_forecast_models`** — `id:integer!,company_id:integer,created_by:integer,name:varchar!,description:text,model_type:varchar!,status:varchar!,metric_name:varchar!,metric_source_id:integer!,data_frequency:varchar!,lookback_days:integer!,forecast_horizon:integer!,model_parameters:text,rmse:numeric,mae:numeric,mape:numeric,r_squared:numeric,trained_at:datetime,last_retrained_at:datetime,next_retraining_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`bi_forecast_predictions`** — `id:integer!,model_id:integer!,prediction_date:datetime!,predicted_value:numeric!,lower_bound:numeric,upper_bound:numeric,confidence_level:numeric,actual_value:numeric,error_percent:numeric,is_outlier:tinyint!,feature_importance:text,generated_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_forecast_scenarios`** — `id:integer!,model_id:integer!,created_by:integer,name:varchar!,description:text,scenario_type:varchar!,parameters:text,growth_rate_adjustment:numeric,volatility_adjustment:numeric,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`bi_forecasts`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,predictive_model_id:integer,forecast_date:date,forecast_value:numeric,lower_bound:numeric,upper_bound:numeric,actual_value:numeric,error_percent:numeric`

**`bi_kpi_alerts`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,kpi_id:integer,metric:varchar,threshold:numeric,operator:varchar!,is_active:tinyint!,triggered_at:datetime,metric_name:varchar,condition:varchar!,comparison_value:numeric,severity:varchar!,resolved_at:datetime,notification_channels:text,recipients:text,created_by:integer,trigger_count:integer!,last_triggered_at:datetime`

**`bi_kpi_history`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,kpi_id:integer,value:numeric,recorded_at:datetime`

**`bi_kpis`** — `id:integer!,tenant_id:integer,name:varchar!,code:varchar,description:text,category:varchar,formula:varchar,metric:varchar,source_module:varchar,current_value:numeric,target_value:numeric,threshold_warning:numeric,threshold_critical:numeric,value:numeric,target:numeric,unit:varchar,period:varchar,module:varchar,trend:varchar,is_active:tinyint!,last_calculated_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_model_retraining_logs`** — `id:integer!,model_id:integer!,status:varchar!,rmse:numeric,mae:numeric,mape:numeric,training_duration_seconds:integer,records_processed:integer!,error_message:text,started_at:datetime,completed_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_narrative_flows`** — `id:integer!,story_id:integer!,name:varchar!,description:text,flow_config:text!,is_active:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`bi_predictive_models`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,entity_type:varchar,algorithm:varchar,accuracy:numeric,trained_at:datetime,model_type:varchar,training_data:text,coefficients:text,features:text,last_trained_at:datetime,accuracy_score:numeric,forecast_horizon_days:integer!,is_active:tinyint!`

**`bi_queries`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,sql_query:text,created_by:integer,datasource:varchar!,result_cache_ttl:integer!,is_public:tinyint!,last_run_at:datetime`

**`bi_reports`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,user_id:integer,type:varchar,is_scheduled:tinyint!,description:text,query_config:text,chart_config:text,filters:text,schedule_recipients:text,schedule:varchar,last_run_at:datetime`

**`bi_scenario_predictions`** — `id:integer!,scenario_id:integer!,prediction_date:datetime!,predicted_value:numeric!,lower_bound:numeric,upper_bound:numeric,created_at:datetime,updated_at:datetime`

**`bi_scheduled_reports`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,report_id:integer,schedule:varchar,recipients:text,format:varchar!,is_active:tinyint!,last_run_at:datetime,next_send_at:datetime,send_count:integer!,last_sent_at:datetime,day_of_week:integer,day_of_month:integer,created_by:integer`

**`bi_seasonality_patterns`** — `id:integer!,model_id:integer!,pattern_type:varchar!,seasonal_factors:text,strength:numeric,created_at:datetime,updated_at:datetime`

**`bi_story_analytics`** — `id:integer!,story_id:integer!,total_views:integer!,unique_viewers:integer!,total_slide_views:integer!,avg_time_per_slide:numeric!,completion_rate:numeric!,shares_count:integer!,interactions_count:integer!,last_viewed_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_story_slides`** — `id:integer!,story_id:integer!,slide_number:integer!,title:varchar!,narrative_text:text!,visualization_config:text,interaction_rules:text,transition_type:varchar!,transition_duration:integer!,layout:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`bi_story_views`** — `id:integer!,story_id:integer!,viewer_id:integer,slide_count_viewed:integer!,time_spent_seconds:numeric!,source:varchar,interaction_log:text,viewed_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_sync_configurations`** — `id:integer!,source_id:integer!,sync_type:varchar!,frequency:varchar!,scheduled_time:time,day_of_week:varchar,day_of_month:integer,is_active:tinyint!,batch_size:integer!,max_retries:integer!,retry_delay_minutes:integer!,filter_criteria:text,created_at:datetime,updated_at:datetime`

**`bi_sync_history`** — `id:integer!,source_id:integer!,status:varchar!,sync_type:varchar,records_attempted:integer!,records_synced:integer!,records_failed:integer!,duration_seconds:integer,data_size_mb:numeric,error_message:text,error_log:text,started_at:datetime,completed_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_transformation_rules`** — `id:integer!,source_id:integer!,name:varchar!,description:text,rule_order:integer!,rule_type:varchar!,rule_config:text,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`bi_trend_analysis`** — `id:integer!,model_id:integer!,trend_slope:numeric,trend_direction:varchar,trend_strength:numeric,change_points_count:integer!,change_point_dates:text,created_at:datetime,updated_at:datetime`

**`bi_visualization_performance`** — `id:integer!,visualization_id:integer!,render_time:numeric!,data_points:integer!,memory_usage:numeric,cpu_usage:numeric,status:varchar!,error_message:text,recorded_at:datetime,created_at:datetime,updated_at:datetime`

**`bi_visualization_templates`** — `id:integer!,company_id:integer,created_by:integer!,name:varchar!,description:text,chart_type:varchar!,default_config:text!,color_scheme:text,is_public:tinyint!,usage_count:integer!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`bi_widget_forecasts`** — `id:integer!,widget_id:integer!,forecast_source:varchar!,forecast_column:varchar!,actual_column:varchar!,date_column:varchar!,label:varchar!,horizon:varchar!,config:text,created_at:datetime,updated_at:datetime`

**`bi_widget_objectives`** — `id:integer!,widget_id:integer!,kpi_key:varchar!,objective_label:varchar!,target_value:numeric,target_unit:varchar,horizon:varchar!,horizon_start:date,horizon_end:date,status:varchar!,config:text,created_at:datetime,updated_at:datetime`

**`bi_widgets`** — `id:integer!,tenant_id:integer,name:varchar,config:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,dashboard_id:integer,type:varchar!,position:text,title:varchar,refresh_interval:integer!`

**`dashboards`** — `id:integer!,tenant_id:integer!,name:varchar!,description:text,layout:text,is_default:tinyint!,is_public:tinyint!,created_by:integer,created_at:datetime,updated_at:datetime,shared_with:text`

### Reporting (6 tables)

**`report_definitions`** — `id:integer!,tenant_id:integer,name:varchar!,report_type:varchar!,data_source:varchar,query_config:text,filters:text,columns_config:text,sort_config:text,is_public:tinyint!,is_system:tinyint!,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime,slug:varchar,module:varchar,description:text,query_template:text,parameters_schema:text,output_format:varchar!,is_active:tinyint!`

**`report_executions`** — `id:integer!,tenant_id:integer,report_id:integer,schedule_id:integer,status:varchar!,rows_count:integer,file_path:varchar,format:varchar,error_message:text,executed_by:integer,started_at:datetime,completed_at:datetime,created_at:datetime,updated_at:datetime,report_definition_id:integer,parameters:text,result_count:integer,result_data:text`

**`report_schedules`** — `id:integer!,tenant_id:integer,report_id:integer,name:varchar,cron_expression:varchar,recipients:text,format:varchar!,locale:varchar!,last_run_at:datetime,next_run_at:datetime,is_active:tinyint!,created_by:integer,created_at:datetime,updated_at:datetime,report_definition_id:integer,frequency:varchar`

**`report_shares`** — `id:integer!,tenant_id:integer,report_id:integer,dashboard_id:integer,shared_with_user_id:integer,shared_with_role:varchar,permission:varchar!,share_token:varchar,expires_at:datetime,created_by:integer,created_at:datetime,updated_at:datetime`

**`report_widgets`** — `id:integer!,tenant_id:integer!,dashboard_id:integer,name:varchar,widget_type:varchar!,data_source:text,query_config:text,display_config:text,refresh_interval_seconds:integer!,created_at:datetime,updated_at:datetime,title:varchar,config:text,position_x:integer!,position_y:integer!,width:integer!,height:integer!`

**`saved_queries`** — `id:integer!,tenant_id:integer!,name:varchar!,sql_query:text!,description:text,is_public:tinyint!,last_executed_at:datetime,execution_count:integer!,created_by:integer!,created_at:datetime,updated_at:datetime`

### Strategy (19 tables)

**`mfg_production_orders`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,bom_id:integer,product_id:integer,workcenter_id:integer,reference:varchar,quantity:numeric!,quantity_produced:numeric!,planned_start:datetime,planned_end:datetime,actual_start:datetime,actual_end:datetime,priority:varchar,created_by:integer,warehouse_id:integer,scheduled_date:date,deadline:date,notes:text`

**`strategy_alerts`** — `id:integer!,tenant_id:varchar,type:varchar!,severity:varchar!,message:text!,kpi_id:integer,ratio_id:integer,triggered_at:datetime!,resolved_at:datetime,created_at:datetime,updated_at:datetime`

**`strategy_correlations`** — `id:integer!,kpi_a:varchar!,kpi_b:varchar!,coefficient:numeric,lag_periods:integer!,confidence:numeric,last_computed_at:datetime,created_at:datetime,updated_at:datetime`

**`strategy_industry_benchmarks`** — `id:integer!,ratio_name:varchar!,industry:varchar,country:varchar,p25:numeric,median:numeric,p75:numeric,year:integer,source:varchar,created_at:datetime,updated_at:datetime`

**`strategy_key_results`** — `id:integer!,objective_id:integer!,title:varchar!,description:text,type:varchar,baseline_value:numeric,target_value:numeric,current_value:numeric,unit:varchar,data_source_module:varchar,data_source_key:varchar,progress:numeric!,confidence:numeric,created_at:datetime,updated_at:datetime`

**`strategy_kpi_values`** — `id:integer!,kpi_id:integer!,value:numeric!,recorded_at:datetime,period:varchar,created_at:datetime,updated_at:datetime`

**`strategy_kpis`** — `id:integer!,tenant_id:varchar,name:varchar!,description:text,category:varchar,source_module:varchar,source_key:varchar,source_aggregation:varchar,source_filter:text,unit:varchar,frequency:varchar,target_value:numeric,warning_threshold:numeric,critical_threshold:numeric,higher_is_better:tinyint!,is_public:tinyint!,created_at:datetime,updated_at:datetime`

**`strategy_kros`** — `id:integer!,objective_id:integer!,kpi_id:integer,target:numeric,baseline:numeric,current:numeric,weight:numeric,created_at:datetime,updated_at:datetime`

**`strategy_objective_links`** — `id:integer!,strategy_objective_id:integer!,linkable_type:varchar!,linkable_id:integer!,contribution_value:numeric,unit_type:varchar,created_at:datetime,updated_at:datetime`

**`strategy_objectives`** — `id:integer!,plan_id:integer,pillar_id:integer,parent_id:integer,level:varchar,owner_type:varchar,owner_id:integer,title:varchar!,description:text,framework_type:varchar,bsc_perspective:varchar,weight:numeric,start_date:date,end_date:date,status:varchar!,progress:numeric!,created_at:datetime,updated_at:datetime`

**`strategy_pillars`** — `id:integer!,plan_id:integer!,name:varchar!,description:text,color:varchar,icon:varchar,sort_order:integer!,created_at:datetime,updated_at:datetime`

**`strategy_plans`** — `id:integer!,tenant_id:varchar,name:varchar!,vision:text,mission:text,period_start:integer,period_end:integer,framework:varchar,status:varchar!,health_score:integer,created_by:integer,created_at:datetime,updated_at:datetime`

**`strategy_ratio_snapshots`** — `id:integer!,ratio_id:integer,tenant_id:varchar,period:varchar!,value:numeric,benchmark_value:numeric,gap:numeric,created_at:datetime!,module:varchar,ratio_key:varchar,status:varchar`

**`strategy_ratios`** — `id:integer!,module:varchar,name:varchar!,numerator_kpi_id:integer,denominator_kpi_id:integer,formula:varchar,benchmark_category:varchar,description:text,unit:varchar,direction:varchar,target_min:numeric,target_max:numeric,created_at:datetime,updated_at:datetime`

**`strategy_ritual_sessions`** — `id:integer!,ritual_id:integer!,scheduled_at:datetime,started_at:datetime,completed_at:datetime,facilitator_id:integer,agenda:text,decisions:text,action_items:text,ai_summary:text,created_at:datetime,updated_at:datetime`

**`strategy_rituals`** — `id:integer!,tenant_id:varchar,name:varchar!,type:varchar,cadence:varchar,day_of_week:integer,day_of_month:integer,attendee_roles:text,plan_id:integer,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`strategy_scenario_assumptions`** — `id:integer!,scenario_id:integer!,variable_name:varchar!,description:text,base_value:numeric,adjusted_value:numeric,impact_scope:varchar,created_at:datetime,updated_at:datetime`

**`strategy_scenarios`** — `id:integer!,tenant_id:varchar,name:varchar!,description:text,type:varchar,base_plan_id:integer,status:varchar!,probability:numeric,created_by:integer,created_at:datetime,updated_at:datetime`

**`strategy_signals`** — `id:integer!,tenant_id:varchar,type:varchar!,source_module:varchar,source_metric:varchar,title:varchar!,description:text,recommendation:text,impacted_objective_ids:text,is_read:tinyint!,is_dismissed:tinyint!,detected_at:datetime,expires_at:datetime,created_at:datetime,updated_at:datetime`

