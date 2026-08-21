# Inventaire des tables — Socle CORE / système (12 modules)

Core, AI, Security, AuditLog, API, Integration, Validation, Shared, Settings, Setup, Workflow, Calendar. Voir `SCHEMA-GENERAL.md` pour la méthode (deux niveaux de migrations, phénomène des tables stub, tables hors périmètre) avant de lire ce fichier.

Légende : **réelle** = schéma métier dès l'origine (colonnes typées, FK) ; **stub patché** = créée au schéma générique `id/tenant_id/[status]/data/timestamps` par le catch-all `2026_05_29_000003_create_all_missing_module_tables.php` puis complétée par au moins une migration `patch_*` depuis — vérifiez le modèle Eloquent avant usage, une table patchée n'a pas forcément toutes les colonnes attendues ; **stub pur** = jamais retouchée depuis sa création générique, quasi certainement sans modèle Eloquent réel derrière (cf. le motif « 36 des 42 tables `acc_` sans modèle » documenté dans `CLAUDE.md`, ici généralisé).

## Core (41 tables : 27 réelles, 14 stub dont 8 patchées / 6 pures)

Le module le plus dispersé du dépôt côté schéma — mélange de tables préfixées `core_`, de tables sans préfixe (`tenants`, `sessions_enhanced`, `sandboxes`…) et de 4 tables du portail superadmin préfixées `admin_`.

| Table | Rôle / colonnes clés |
|---|---|
| `tenants` | Entreprise cliente multi-tenant — modèle `Tenant` |
| `tenant_modules` | Activation de modules par tenant (aligné `config/modules_statuses.json`) |
| `companies` | `App\Models\Company` — la fondation racine sur laquelle Consolidation/Budget-variance/compliance Security se sont reconnectés (voir `CLAUDE.md` Known Gaps) |
| `core_api_keys` | Clés API internes au Core (distinctes de `api_keys` racine et de `Modules\API\Models\ApiKey`/`api_keys` — 3 concepts « ApiKey » coexistent, voir `docs/03-MODULES/Core.md`) |
| `core_audit_logs` | **Le** journal d'audit réellement lu par l'API/UI (`AuditLog` Core), alimenté par `RecordsActivity`/`AuditableActions`/`AuditAuthListener` (54+23 modèles). A reçu une colonne `company_id` nullable (backfillée depuis `user_id`) au Chantier 8.5-light pour fermer une fuite cross-tenant — voir `DEPENDANCES-MODULES.md` |
| `core_secrets` / `core_secret_access_grants` / `core_secret_access_logs` / `core_secret_rotation_policies` | Coffre-fort de secrets applicatifs (AES-256-CBC, `SecretsService`/`EncryptionService`/`KeyManagementService`) avec rotation et contrôle d'accès |
| `core_data_requests` / `core_gdpr_consents` | RGPD/PDPL propres au Core (`GdprConsent`/`DataRequest`) — voir aussi `gdpr_*` racine ci-dessous, deuxième implémentation parallèle |
| `csp_violations` | Violations CSP remontées par le navigateur (`CspViolationController::report`, public/non-authentifié, throttlé) |
| `core_csrf_tokens` | Télémétrie CSRF |
| `ddos_incidents` | Incidents DDoS détectés |
| `rate_limit_metrics` | Métriques de rate limiting (gardé par `role:security-admin,admin,super-admin` route-only, pas de Policy — pas de modèle avec `company_id`) |
| `sessions_enhanced` / `session_security_events` | Sessions enrichies et évènements de sécurité liés (fingerprint/hijack detection, `session.security` middleware déployé sur les 27 modules) |
| `sandboxes` | Environnements de démo à expiration automatique (`SandboxService`) |
| `core_security_incidents` / `core_security_incident_communications` | Incidents de sécurité et communications associées, propriété Core (distinct de `security_incidents`/`security_incident_responses` du module **Security** — deux tables d'incidents coexistent) |
| `admin_audit_logs` / `admin_backups` / `admin_backup_schedules` / `admin_server_configs` | Portail multi-tenant superadmin (Phase 40 `SuperadminController` — provision/suspend/purge/export, stats globales), câblé sous `superadmin/*` (`role:super-admin`) au Chantier 8.3 Core+Security après avoir été écrit sans jamais être routé |
| `gdpr_audit_logs` / `gdpr_consents` / `gdpr_sar_requests` | Deuxième schéma RGPD, sans préfixe `core_` — coexiste avec `core_gdpr_consents`/`core_data_requests` ci-dessus ; à réconcilier, non fait à ce jour |

**Gap non documenté ailleurs, trouvé en croisant les modèles réels contre les migrations** : `TenantAuditLog` (`tenant_audit_log`), `TenantInvitation` (`tenant_invitations`) et `TenantUser` (`tenant_users`) — les 3 modèles `docs/03-MODULES/Core.md` documente comme « TenantUser / TenantModule / TenantInvitation — rattachement utilisateur ↔ tenant, modules activés par tenant, invitations » — déclarent chacun leur `$table` mais **aucune migration ne crée aucune des trois**. Pire : les trois sont activement référencées par `TenantManagerService` (le service de provisioning du portail superadmin Phase 40, `SuperadminController`, câblé au Chantier 8.3 Core+Security) — tout appel réel de provisioning/invitation de tenant via ce service plante en « table not found ». `TenantModule` a bien sa table (`tenant_modules`, racine, réelle) ; c'est spécifiquement le trio `TenantAuditLog`/`TenantInvitation`/`TenantUser` qui manque.

**Tables stub (générique `id/tenant_id/status/data`)** — *patchées depuis* : `core_approval_instances`, `core_approval_workflows`, `core_custom_field_values`, `core_custom_fields`, `core_import_jobs`, `core_tenant_exchange_history`, `core_tenant_exchanges`, `core_workflow_definitions`. *Jamais retouchées (stub pur)* : `core_approval_decisions`, `core_encrypted_fields`, `core_import_rows`, `core_key_rotations`, `core_validation_audits`, `core_workflow_states`. Ces tables correspondent aux modèles `ApprovalWorkflow`/`ApprovalInstance`/`ApprovalDecision`, `CustomField`/`CustomFieldValue`, `ImportJob`/`ImportRow`, `TenantExchange`/`TenantExchangeHistory`, `WorkflowDefinition`/`WorkflowState` documentés dans `docs/03-MODULES/Core.md` — vérifiez leur `$fillable` réel avant d'écrire dessus, plusieurs sont encore probablement incomplètes.

## AI (2 tables, toutes réelles)

| Table | Rôle |
|---|---|
| `ai_anomalies` | Anomalies détectées par `AiAnomalyDetectionService`, repointé au Chantier 8.5-light sur les vraies tables `<module>_`-préfixées (auparavant interrogeait des noms de table bruts inexistants — `products`, `invoices`, `journal_entries`…) |
| `ai_recommendations` | Recommandations produites par le moteur IA |

**Gap non documenté ailleurs** : `AiUsageLimit` (quotas d'usage IA par tenant/utilisateur — `limit_type`/`limit_value`/`period`/`block_on_exceed`, utilise `HasAuditLog`) déclare `protected $table = 'ai_usage_limits'` mais **aucune migration nulle part dans le dépôt** ne crée cette table — un vrai « table not found » en attente, pas une table stub (elle n'a même pas de scaffold générique).

Les modèles `AiInsight`/`AiRequest` (tables `ai_insights`/`ai_requests` documentées dans `docs/03-MODULES/AI.md`) et leurs migrations ont été **supprimés** au Chantier 8.5-light (confirmés morts, zéro consommateur — le vrai suivi d'usage IA passe par la table racine `ai_usage_logs`, non retrouvée dans les migrations lues ici et probablement dans le module qui expose `AiUsageLimit`/`ai_usage_limits`, à vérifier séparément si besoin). Ce fichier reflète l'état réel des migrations au moment de la rédaction — si `docs/03-MODULES/AI.md` liste encore `AiInsight`/`AiRequest`/`ai_usage_limits`, considérez ce document-ci comme la source à jour côté schéma.

## Security (12 tables, toutes réelles — aucun stub)

| Table | Rôle / colonnes clés |
|---|---|
| `security_authentication_events` | Tentatives de connexion (succès/échec, `trust_score`, `risk_factors`) |
| `security_incidents` / `security_incident_responses` | Incidents de sécurité et réponses associées |
| `security_threat_indicators` | Indicateurs de menace (IP, hash, domaine), avec `threat_level` (pas `severity` — bug de colonne inexistante corrigé au Chantier 8.3 Core+Security) |
| `security_compliance_controls` / `security_compliance_violations` | Contrôles de conformité par référentiel (SOX, HIPAA, PCI-DSS, GDPR) et violations détectées |
| `security_encryption_keys` | Clés de chiffrement — modèle `EncryptionKey` |
| `security_trust_zones` | Zones de confiance réseau, patchée au Chantier 8.3 (`zone_type`/`cidr_blocks`/`device_policies`/`authentication_policies`/`trust_score_minimum`/`assigned_resources` ajoutées — le contrôleur/modèle validait des colonnes qui n'existaient pas du tout) |
| `compliance_audits` | Audits de conformité — **sans préfixe `security_`**, créée par une migration Security dédiée (`2026_08_17_000002_create_compliance_encryption_service_tables.php`) |
| `encrypted_fields` | Champs chiffrés déclarés (`EncryptedField`) — sans préfixe |
| `key_rotation_logs` | Historique de rotation de clé — sans préfixe |
| `service_identities` | Identités de service/comptes techniques avec rotation de credentials — sans préfixe ; son contrôleur a été réécrit au Chantier 8.3 Core+Security car il validait des noms de colonnes ne correspondant ni à la vraie table ni même au `$fillable` du modèle |

Toutes les colonnes `company_id` de ce module sont `string(36)` (héritage d'une conception UUID-tenant abandonnée) alors que `users.company_id` est `unsignedBigInteger` — chaque comparaison d'appartenance dans les 8 Policies Security comparait un int à une string et échouait toujours silencieusement (fail-closed, pas une faille) avant d'être corrigée au Chantier 8.3 (cast des deux côtés en string).

## AuditLog (1 table)

| Table | Rôle |
|---|---|
| `audit_logs` | Modèle propre du module (`Modules\AuditLog\Models\AuditLog`, tenant-scopé via un vrai `tenant_id`) — **confirmé zéro lecteur dans le code applicatif**. Toute l'API/UI du module lit en réalité `core_audit_logs` (module **Core**, voir ci-dessus). Table dormante, laissée en l'état (narrow, sans risque). |

## API (3 tables, toutes réelles)

| Table | Rôle |
|---|---|
| `api_keys` | `Modules\API\Models\ApiKey` — scopes, rate limit, IP autorisées, expiration/révocation. **Distinct** de `core_api_keys` (Core) et de la table racine `api_keys` créée par `2024_01_01_000001_create_api_keys_table.php` — confirmer laquelle des deux `api_keys` gagne au runtime avant d'y toucher (collision de nom entre migration Core littérale et migration API module, à vérifier via `Schema::hasTable` guard) |
| `api_requests` | Journal de requête API (méthode, endpoint, `status_code`, `duration_ms`) |
| `api_webhooks` | `Modules\API\Models\ApiWebhook` — URL, `events` souscrits, secret HMAC, désactivation auto après 10 échecs. Policies `ApiKeyPolicy`/`WebhookPolicy` enregistrées et appelées seulement depuis le Chantier 8.5-light (trou RBAC avant) |

## Integration (7 tables, toutes réelles)

| Table | Rôle |
|---|---|
| `integrations` | Une ligne par tenant × intégration externe active |
| `integration_connectors` | Connecteur configuré (webhook/OAuth2/API key/basic_auth/custom), `config` chiffré |
| `integration_sync_logs` | Table partagée par **deux** modèles Eloquent différents (`SyncLog` avec `connector_id`, audité ; `IntegrationSyncLog` avec `integration_id`, sans timestamps) |
| `integration_webhook_endpoints` | Endpoint sortant rattaché à un connecteur |
| `whb_connections` / `whb_exchanges` / `whb_permissions` | Fédération inter-tenant WHB — `whb_connections.tenant_id` est un `string(36)` legacy, comparé en string dans `IntegrationConnectorPolicy` (même motif que Security) |

Deux IDOR réels ont été corrigés au Chantier 8.5-light : `IntegrationController::show/activate/addWebhook/dispatch/logs` sans `authorize()` ni filtre tenant, et `WhbPartnerController::approve/reject/suspend` sans scoping ni gate de rôle du tout.

## Validation (12 tables : 4 réelles, 8 stub dont 8 patchées — aucune stub pure)

Aucune migration propre au module (`Modules/Validation/database/migrations/` n'existe pas) — 100 % du schéma vit dans les migrations racine.

| Table | Rôle |
|---|---|
| `validation_rule_sets` / `validation_rule_set_rule` / `validation_rules` / `validation_rule_dependencies` | Moteur de règle-sets (Chantier 5, câblé pour la première fois au Chantier 8.5sv via `ValidationRuleSetController` + `addDependency()` avec détection de cycle) |

**Tables stub, toutes patchées depuis leur création générique** (donc probablement fonctionnelles malgré une origine stub) : `validation_approval_workflows`, `validation_approval_rules`, `validation_approval_requests` (3 migrations de patch, la plus retouchée du module — porte la relation polymorphe `approvable()`), `validation_approval_actions`, `validation_approval_history`, `validation_approval_hierarchies`, `validation_hierarchy_levels`, `validation_level_approvers`. Tous les modèles utilisent `SoftDeletes` + `RecordsActivity` (`$auditModule = 'Validation'`) par convention du module.

## Shared (3 tables, toutes réelles)

| Table | Rôle |
|---|---|
| `shared_countries` | Référentiel pays — codes ISO, devise, préfixe tél., `is_ohada`/`is_uemoa`/`is_cemac`, TVA par défaut, fuseau |
| `shared_currencies` | Référentiel devises — code, symbole, décimales, `is_cfa`, taux vers USD |
| `shared_preferences` | Préférences utilisateur/tenant, sans modèle Eloquent dédié dans le code actuel |

Les modèles `Language`/`Tag`/`SharedResourcePolicy` documentés dans `docs/03-MODULES/Shared.md` ont été **supprimés** au Chantier 8.5-light (confirmés orphelins, zéro consommateur) — leurs tables `shared_languages`/`shared_tags` n'apparaissent donc plus dans le schéma actuel. `CurrencyController` (filtrage région/CFA/actif, seule vraie implémentation de conversion) a été routé pour la première fois à ce chantier, remplaçant l'alias `CountryController::currencies()`.

## Settings (2 tables, toutes réelles)

| Table | Rôle |
|---|---|
| `settings` | Couple `module`/`key` → `value` typé (`value_type`), visibilité `is_public`, scope `tenant_id`. `SettingsController::update()` (PUT individuel) n'avait aucun `authorize()` contrairement à ses siblings — corrigé au Chantier 8.5-light |
| `setting_groups` | Regroupement de réglages par module pour l'affichage |

## Setup (9 tables, toutes réelles)

| Table | Rôle |
|---|---|
| `setup_import_jobs` | Job d'import (type source `excel`/`csv`/`pdf`/DB externe, statut, tenant) |
| `setup_source_schemas` | Structure détectée du fichier/table source après analyse |
| `setup_field_mappings` | Mapping colonne source → champ cible, suggéré par IA ou manuel |
| `setup_import_errors` | Erreur de validation/exécution par ligne |
| `setup_company_profiles` | Profil d'entreprise créé pendant l'onboarding |
| `setup_onboarding_sessions` / `setup_onboarding_step_events` / `setup_funnel_snapshots` / `setup_onboarding_funnel_snapshots` | Télémétrie du funnel d'onboarding — **explicitement hors scope fonctionnel** pour Life MDG (`CLAUDE.md` Known Gaps : mesure un KPI produit-marketing de WideHalo sur son propre wizard, pas un besoin d'une PME malgache) ; les tables existent et sont migrées mais rien ne les alimente sciemment côté produit Life MDG |

Une vraie faille cross-tenant a été corrigée au Chantier 8.5sv : `tenantId()` retombait sur un header `X-Company-ID` contrôlé par le client dès que `company_id` était null (cas courant) — n'importe quel utilisateur pouvait lire/écrire les jobs d'import et sessions d'onboarding d'un autre tenant. Fixé en supprimant totalement le fallback header, au profit de `user()->tenant_id`.

## Workflow (24 tables : 14 réelles, 10 stub dont 4 patchées / 6 pures)

Le module aux préfixes les plus hétérogènes du socle : `wfd_` (moteur « legacy », 2026), `automation_` (moteur n8n-like Phase 39), `connector_`/`logic_` (Phase 52, sans préfixe module), `flow_versions`/`webhook_events` (sans préfixe).

| Table | Rôle |
|---|---|
| `automation_flows` / `automation_nodes` / `automation_connections` / `automation_executions` / `automation_variables` / `automation_flow_templates` | Flow visuel type n8n (Phase 39) — nœuds, connexions, variables réutilisables, 12 templates prêts à l'emploi |
| `automation_rules` | Règles SI/QUAND/ALORS déclenchées par évènement (`FlowExecutionEngine::triggerByKey()`) |
| `flow_versions` | Historique de versions d'un `AutomationFlow` (rollback) |
| `connector_definitions` / `connector_credentials` | Framework de connecteurs SaaS (Phase 52, 20 connecteurs) — référencé par `WorkflowServiceProvider`, sans préfixe `workflow_`/`wfd_` |
| `logic_rules` / `logic_rule_executions` / `logic_action_types` / `logic_condition_types` | Moteur DSL SI/QUAND/ALORS (parseur français), distinct du moteur `automation_rules` ci-dessus |

**Tables stub, patchées depuis** : `wfd_definitions`, `wfd_executions`, `workflow_actions`, `workflow_steps`. **Stub pures** : `wfd_actions`, `wfd_execution_logs`, `webhook_events`, `workflow_chain_definitions`, `workflow_chain_executions`, `workflow_execution_steps` — ces trois derniers correspondent pourtant à des modèles bien réels et documentés (`WorkflowChainDefinition`/`WorkflowChainExecution`/`WorkflowExecutionStep`, Phase 39 trigger-key), donc soit leurs colonnes ont été ajoutées par une migration non détectée par cette lecture, soit ils tournent encore sur le schéma générique `id/tenant_id/status/data` en pratique — à vérifier avant d'écrire dessus.

Un bloc entier de 34 routes « Legacy Workflow Engine / Builder / Task / Approval » référençant 14 méthodes de contrôleur et 3 classes de contrôleur inexistantes (`ExecutionController`, `TaskController`, `ApprovalController`) a été supprimé au Chantier 8.5-light — les tables `wfd_*` restent cependant réellement migrées et partiellement utilisées par les endpoints DSL/chain-engine qui, eux, sont réels et routés. Voir aussi `SCHEMA-GENERAL.md` pour le **troisième** moteur de workflow racine (`App\Models\Workflow`/`Approval`), totalement mort, distinct de celui-ci.

## Calendar (5 tables, toutes réelles)

| Table | Rôle |
|---|---|
| `calendar_calendars` | Un calendrier par utilisateur/type (`personal`/`shared`/`module`), source `local`/`google`/`outlook`/`apple` |
| `calendar_events` | Évènement — récurrence, statut `confirmed`/`tentative`/`cancelled`, `module_type`/`module_id` polymorphiques pour rattacher l'évènement à un enregistrement d'un autre module (voir `DEPENDANCES-MODULES.md`) |
| `calendar_attendees` | Participants, suivi accept/refuse |
| `calendar_reminders` | Rappels associés à un évènement |
| `calendar_sync_tokens` | Jetons OAuth/sync Google/Outlook/Apple |

`CalendarPolicy`/`CalendarEventPolicy` étaient correctement écrites et appelées mais jamais enregistrées auprès du Gate (`CalendarServiceProvider` sans `registerPolicies()`) — chaque update/delete de calendrier ou d'évènement 403ait pour tout le monde, y compris les admins, jusqu'au Chantier 8.5-light.

## Détail des colonnes (succinct)

Extrait le 2026-08-21 directement du schéma SQLite réellement migré (`Schema::getColumns()`), pas des fichiers de migration — la source de vérité la plus fiable compte tenu du mécanisme « racine crée, module patche » documenté dans `SCHEMA-GENERAL.md`. Format : `colonne:type` — `!` = non nullable (requis). Types SQLite génériques (`integer`/`varchar`/`numeric`/`text`/`datetime`/`date`/`tinyint`) ; en production MySQL les types réels sont plus précis (`bigint unsigned`, `decimal(15,4)`, etc.) mais la structure des colonnes est identique.

### AI (3 tables)

**`ai_anomalies`** — `id:integer!,module:varchar!,entity_type:varchar!,entity_id:integer,anomaly_type:varchar!,severity:varchar!,description:text!,detected_at:datetime!,resolved_at:datetime,metadata:text`

**`ai_recommendations`** — `id:integer!,user_id:integer,module:varchar!,recommendation_type:varchar!,title:varchar!,description:text!,priority:varchar!,status:varchar!,metadata:text,created_at:datetime!`

**`ai_usage_limits`** — `id:integer!,tenant_id:integer!,user_id:integer,limit_type:varchar!,limit_value:numeric!,period:varchar!,block_on_exceed:tinyint!,active:tinyint!,created_at:datetime,updated_at:datetime`

### API (7 tables)

**`api_keys`** — `id:integer!,tenant_id:integer!,user_id:integer,name:varchar!,key_hash:varchar!,key_prefix:varchar!,scopes:text,rate_limit:integer!,last_used_at:datetime,expires_at:datetime,revoked_at:datetime,description:text,allowed_ips:text,metadata:text,created_at:datetime,updated_at:datetime`

**`api_requests`** — `id:integer!,tenant_id:integer,api_key_id:integer,user_id:integer,method:varchar!,endpoint:varchar!,query_params:text,request_body:text,response_body:text,status_code:integer,duration_ms:integer,ip_address:varchar,user_agent:varchar,error_message:text,created_at:datetime`

**`api_webhooks`** — `id:integer!,tenant_id:integer!,user_id:integer,name:varchar!,url:varchar!,events:text,secret:varchar,active:tinyint!,last_triggered_at:datetime,last_status_code:integer,failure_count:integer!,headers:text,description:text,created_at:datetime,updated_at:datetime`

**`webhook_audit_logs`** — `id:integer!,provider:varchar!,event_type:varchar,status:varchar!,ip_address:varchar,request_headers:text,response_code:integer!,error_message:text,processing_time_ms:integer,nonce:varchar,timestamp_received:datetime,created_at:datetime,updated_at:datetime`

**`webhook_deliveries`** — `id:integer!,webhook_id:integer!,event:varchar!,payload:text!,http_status:integer,response_body:text,success:tinyint!,attempt:integer!,created_at:datetime,updated_at:datetime`

**`webhook_events`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,webhook_id:integer,event_type:varchar,payload:text,retries:integer!,last_attempted_at:datetime`

**`webhooks`** — `id:integer!,user_id:integer!,url:varchar!,secret:varchar!,events:text!,is_active:tinyint!,description:varchar,created_at:datetime,updated_at:datetime`

### AuditLog (1 tables)

**`audit_logs`** — `id:integer!,tenant_id:integer!,user_id:integer,user_name:varchar,module:varchar!,action:varchar!,entity_type:varchar,entity_id:integer,old_values:text,new_values:text,ip_address:varchar,user_agent:text,created_at:datetime!`

### Calendar (5 tables)

**`calendar_attendees`** — `id:integer!,event_id:integer!,user_id:integer,email:varchar,name:varchar,role:varchar!,status:varchar!,created_at:datetime,updated_at:datetime,is_organizer:tinyint!`

**`calendar_calendars`** — `id:integer!,tenant_id:varchar,user_id:integer!,name:varchar!,color:varchar!,type:varchar!,source:varchar!,is_primary:tinyint!,is_visible:tinyint!,sync_token:varchar,external_calendar_id:varchar,created_at:datetime,updated_at:datetime`

**`calendar_events`** — `id:integer!,tenant_id:varchar,calendar_id:integer!,title:varchar!,description:text,start_at:datetime!,end_at:datetime!,all_day:tinyint!,location:varchar,url:varchar,recurrence_rule:varchar,recurrence_exception_dates:text,status:varchar!,visibility:varchar!,source:varchar!,external_event_id:varchar,external_etag:varchar,module_type:varchar,module_id:varchar,color:varchar,created_by:integer,deleted_at:datetime,created_at:datetime,updated_at:datetime`

**`calendar_reminders`** — `id:integer!,event_id:integer!,method:varchar!,minutes_before:integer!,is_sent:tinyint!,sent_at:datetime,created_at:datetime,updated_at:datetime,user_id:integer`

**`calendar_sync_tokens`** — `id:integer!,provider:varchar!,access_token:varchar,refresh_token:varchar,token_expires_at:datetime,sync_token:varchar,last_synced_at:datetime,created_at:datetime,updated_at:datetime,tenant_id:varchar,user_id:integer,calendar_ids:text,sync_errors:text`

### Core (33 tables)

**`core_api_keys`** — `id:varchar!,tenant_id:varchar,user_id:integer!,name:varchar!,key_hash:varchar!,key_preview:varchar!,scopes:text,ip_restrictions:varchar,expires_at:datetime,last_used_at:datetime,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`core_approval_decisions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,instance_id:integer,step:integer,step_order:integer,decision:varchar,comment:text,decided_by:integer,approver_id:integer,decided_at:datetime`

**`core_approval_instances`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,workflow_id:integer,subject_type:varchar,subject_id:integer,current_step:integer!,initiated_by:integer,completed_at:datetime,escalated_to:integer,escalation_reason:text`

**`core_approval_workflows`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,name:varchar,module:varchar,resource_type:varchar,is_active:tinyint!,steps_count:integer!,requires_all:tinyint!,steps:text,entity_type:varchar,allow_parallel:tinyint!,description:text,created_by:integer`

**`core_audit_logs`** — `id:integer,user_id:integer,user_name:varchar,user_role:varchar,action:varchar!,module:varchar,event_type:varchar,description:varchar,subject_type:varchar,subject_id:integer unsigned,old_values:text,new_values:text,ip_address:varchar,user_agent:text,tenant_id:varchar,created_at:datetime,company_id:integer`

**`core_csrf_tokens`** — `id:varchar!,user_id:varchar!,token_hash:varchar!,action:varchar,scope:varchar,ip_address:varchar,user_agent_hash:varchar,expires_at:datetime!,revoked_at:datetime,last_verified_at:datetime,rotation_count:integer!,tenant_id:varchar,metadata:text,created_at:datetime,updated_at:datetime`

**`core_custom_field_values`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,custom_field_id:integer,entity_type:varchar,entity_id:integer,value:text`

**`core_custom_fields`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,entity_type:varchar,name:varchar,type:varchar!,label:varchar,is_required:tinyint!,options:text,field_key:varchar,field_label:varchar,field_type:varchar!,is_unique:tinyint!,is_searchable:tinyint!,default_value:varchar,validation_rules:text,group_name:varchar,sort_order:integer!,is_active:tinyint!`

**`core_data_requests`** — `id:integer!,user_id:integer,email:varchar,request_type:varchar!,status:varchar!,notes:text,admin_notes:text,requested_at:datetime,completed_at:datetime,expires_at:datetime,data_snapshot:text,created_at:datetime,updated_at:datetime`

**`core_gdpr_consents`** — `id:integer!,user_id:integer,consent_type:varchar!,given:tinyint!,ip_address:varchar,user_agent:varchar,created_at:datetime,updated_at:datetime,email:varchar,granted:tinyint!,granted_at:datetime,revoked_at:datetime,source:varchar`

**`core_import_jobs`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,user_id:integer,filename:varchar,entity_type:varchar,total_rows:integer!,processed:integer!,failed:integer!,is_complete:tinyint!,file_path:text,file_type:varchar,target_entity:varchar,imported:integer!,errors:text,column_mapping:text,started_at:datetime,completed_at:datetime`

**`core_import_rows`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,import_job_id:integer,row_index:integer!,raw_data:text,mapped_data:text,errors:text,error_message:text,created_record_id:integer`

**`core_secret_access_grants`** — `id:varchar!,tenant_id:varchar,secret_id:varchar!,user_id:integer!,scopes:text,expires_at:datetime,revoked_at:datetime,granted_by:integer,reason:text,created_at:datetime,updated_at:datetime`

**`core_secret_access_logs`** — `id:varchar!,tenant_id:varchar,secret_id:varchar,user_id:integer,action:varchar!,ip_address:varchar,success:tinyint!,reason:text,timestamp:datetime`

**`core_secret_rotation_policies`** — `id:varchar!,tenant_id:varchar,secret_id:varchar!,rotation_interval:integer!,last_rotation_at:datetime,next_rotation_at:datetime,auto_rotate:tinyint!,notification_days_before:text,created_at:datetime,updated_at:datetime`

**`core_secrets`** — `id:varchar!,tenant_id:varchar!,name:varchar!,type:varchar!,encrypted_value:text!,key_version:integer!,created_by:integer,expires_at:datetime,rotated_at:datetime,next_rotation:datetime,is_active:tinyint!,tags:text,created_at:datetime,updated_at:datetime`

**`core_security_incident_communications`** — `id:integer!,incident_id:integer!,type:varchar!,message:text!,created_at:datetime,updated_at:datetime`

**`core_security_incidents`** — `id:integer!,title:varchar!,description:text!,severity:varchar!,status:varchar!,user_id:integer,ip_address:varchar,resolved_at:datetime,resolution:text,prevention:text,created_at:datetime,updated_at:datetime`

**`core_tenant_exchange_history`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,exchange_id:integer,action:varchar,actor_user_id:integer,actor_tenant_id:varchar,note:text,notes:text`

**`core_tenant_exchanges`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,source_tenant_id:integer,target_tenant_id:integer,data_type:varchar,payload:text,exchange_type:varchar,message:text,rejection_reason:text,expires_at:datetime,accepted_at:datetime,created_by_user_id:integer,accepted_by_user_id:integer`

**`core_workflow_definitions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,name:varchar,module:varchar,resource_type:varchar,is_active:tinyint!,steps:text,transitions:text`

**`core_workflow_states`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,workflow_definition_id:integer,name:varchar,is_initial:tinyint!,is_final:tinyint!,subject_type:varchar,subject_id:integer,current_step:varchar,started_at:datetime,completed_at:datetime,metadata:text`

**`csp_violations`** — `id:varchar!,document_uri:varchar,violated_directive:varchar,effective_directive:varchar,original_policy:text,disposition:varchar!,blocked_uri:varchar,source_file:varchar,line_number:integer,column_number:integer,status_code:integer,ip_address:varchar,user_agent:text,user_id:integer,tenant_id:varchar,module:varchar,violation_data:text,is_internal_request:tinyint!,severity:varchar!,resolved_at:datetime,created_at:datetime,updated_at:datetime`

**`gdpr_audit_logs`** — `id:integer!,action:varchar!,user_id:integer,timestamp:datetime,immutable:tinyint!,tenant_id:varchar,metadata:text,created_at:datetime,updated_at:datetime`

**`gdpr_consents`** — `id:integer!,user_id:integer,analytics:tinyint!,marketing:tinyint!,functional:tinyint!,tenant_id:varchar,created_at:datetime,updated_at:datetime`

**`gdpr_sar_requests`** — `id:integer!,user_id:integer,email:varchar,format:varchar!,status:varchar!,confirmation_token:varchar,confirmed_at:datetime,tenant_id:varchar,created_at:datetime,updated_at:datetime`

**`push_tokens`** — `id:integer!,user_id:integer!,token:varchar!,platform:varchar,device_type:varchar,device_name:varchar,last_used_at:datetime,created_at:datetime,updated_at:datetime`

**`sandboxes`** — `id:integer!,tenant_id:varchar!,parent_tenant_id:varchar!,name:varchar!,expires_at:datetime,status:varchar!,company_id:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`sync_queue`** — `id:varchar!,user_id:integer,entity_type:varchar!,entity_id:integer,operation:varchar!,payload:text,status:varchar!,retry_count:integer!,client_timestamp:datetime,synced_at:datetime,created_at:datetime,updated_at:datetime`

**`tenant_audit_log`** — `id:integer!,tenant_id:varchar!,user_id:integer,action:varchar!,entity_type:varchar,entity_id:varchar,old_values:text,new_values:text,ip_address:varchar,user_agent:text,created_at:datetime`

**`tenant_invitations`** — `id:integer!,tenant_id:varchar!,email:varchar!,role:varchar!,token:varchar!,invited_by:integer,expires_at:datetime,accepted_at:datetime,created_at:datetime,updated_at:datetime`

**`tenant_modules`** — `id:integer!,tenant_id:varchar!,module:varchar!,enabled:tinyint!,department:varchar,settings:text,created_at:datetime,updated_at:datetime`

**`tenant_users`** — `id:integer!,tenant_id:varchar!,user_id:integer!,role:varchar!,joined_at:datetime,invited_by:integer`

### Integration (9 tables)

**`connector_credentials`** — `id:integer!,tenant_id:integer!,connector_key:varchar!,name:varchar!,credentials:text!,expires_at:datetime,created_at:datetime,updated_at:datetime`

**`connector_definitions`** — `id:integer!,tenant_id:integer,key:varchar!,name:varchar!,category:varchar!,auth_type:varchar!,base_url:varchar!,icon:varchar!,color:varchar!,docs_url:varchar,auth_config:text,actions:text,triggers:text,rate_limit_per_minute:integer!,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`edi_transactions`** — `id:integer!,type:varchar!,direction:varchar!,content_raw:text!,parsed_json:text,status:varchar!,partner_id:integer,occurred_at:datetime!,created_at:datetime,updated_at:datetime`

**`integration_connectors`** — `id:integer!,tenant_id:varchar!,connector_type:varchar,name:varchar!,status:varchar!,config:text,auth_config:text,created_at:datetime,updated_at:datetime,slug:varchar,provider_type:varchar,last_sync_at:datetime,error_message:text,created_by:integer,deleted_at:datetime`

**`integration_sync_logs`** — `id:integer!,integration_id:integer,sync_type:varchar,status:varchar!,records_processed:integer!,records_failed:integer!,error_details:text,started_at:datetime!,completed_at:datetime,created_at:datetime,updated_at:datetime,direction:varchar,records_synced:integer!,errors:text,connector_id:integer,tenant_id:varchar,payload_size:integer`

**`integration_webhook_endpoints`** — `id:integer!,connector_id:integer,url:varchar!,method:varchar!,headers:text,secret_key:varchar,retry_attempts:integer!,timeout_seconds:integer!,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`whb_connections`** — `id:integer!,local_tenant_id:varchar!,remote_tenant_id:varchar,remote_server_url:varchar,remote_tenant_name:varchar,connection_type:varchar!,status:varchar!,invite_code:varchar,invite_expires_at:datetime,shared_secret:varchar,session_token:varchar,session_expires_at:datetime,public_key:varchar,initiated_by:varchar,approved_by:varchar,approved_at:datetime,last_sync_at:datetime,created_at:datetime,updated_at:datetime`

**`whb_exchanges`** — `id:integer!,connection_id:integer!,exchange_type:varchar,direction:varchar!,status:varchar!,payload:text,response:text,sent_at:datetime,received_at:datetime,created_at:datetime,updated_at:datetime,data_type:varchar,local_resource_type:varchar,local_resource_id:integer,remote_resource_id:varchar,error_message:text,initiated_by:integer,processed_at:datetime`

**`whb_permissions`** — `id:integer!,connection_id:integer!,resource_type:varchar,permission_level:varchar!,is_granted:tinyint!,created_at:datetime,updated_at:datetime,data_type:varchar,can_receive:tinyint!,can_send:tinyint!,auto_accept:tinyint!`

### Security (15 tables)

**`compliance_audits`** — `id:integer!,company_id:varchar!,audit_type:varchar!,framework:varchar!,audit_start_date:datetime,audit_end_date:datetime,controls_evaluated:integer!,controls_compliant:integer!,controls_non_compliant:integer!,compliance_score:numeric,findings:text,audit_status:varchar!,created_at:datetime,updated_at:datetime`

**`ddos_incidents`** — `id:integer!,ip_address:varchar!,type:varchar,severity:varchar,metadata:text,expires_at:datetime,created_at:datetime,updated_at:datetime,endpoint:varchar,risk_level:varchar,reason:varchar,attack_signatures:text,request_count:integer!,requests_per_second:float,detected_at:datetime,blocked_until:datetime,auto_unblock_at:datetime,auto_blocked:tinyint!,attack_signature:varchar,metrics:text,tenant_id:varchar`

**`encrypted_fields`** — `id:integer!,company_id:varchar!,table_name:varchar!,column_name:varchar!,encryption_algorithm:varchar!,encryption_key_id:integer,is_searchable:tinyint!,is_encrypted:tinyint!,metadata:text,created_at:datetime,updated_at:datetime`

**`key_rotation_logs`** — `id:integer!,encryption_key_id:integer!,rotation_type:varchar!,rotation_status:varchar!,old_key_hash:varchar,new_key_hash:varchar,records_reencrypted:integer!,started_at:datetime,completed_at:datetime,error_message:text,created_at:datetime,updated_at:datetime`

**`security_authentication_events`** — `id:integer!,user_id:integer,user_email:varchar!,event_type:varchar!,authentication_method:varchar!,ip_address:varchar!,user_agent:text,device_info:text,status:varchar!,failure_reason:varchar,trust_score:numeric,risk_factors:text,authenticated_at:datetime!,created_at:datetime!`

**`security_compliance_controls`** — `id:integer!,company_id:varchar!,framework:varchar!,control_id:varchar!,control_name:varchar!,control_description:text,control_type:varchar!,implementation_status:varchar!,implementation_details:text,last_verified_at:datetime,created_at:datetime,updated_at:datetime`

**`security_compliance_violations`** — `id:integer!,compliance_control_id:integer!,violation_type:varchar!,description:text!,severity:varchar!,status:varchar!,detected_at:datetime!,resolved_at:datetime,created_at:datetime,updated_at:datetime,company_id:varchar,violation_description:text,violation_status:varchar!,remediation_deadline:datetime,remediated_at:datetime,remediation_notes:text`

**`security_encryption_keys`** — `id:integer!,company_id:varchar!,key_name:varchar!,key_type:varchar!,key_usage:varchar!,key_status:varchar!,key_material_hash:varchar!,vault_reference:varchar,key_length_bits:integer!,rotated_at:datetime,expires_at:datetime,metadata:text,created_at:datetime,updated_at:datetime`

**`security_incident_responses`** — `id:integer!,security_incident_id:integer!,response_type:varchar!,response_status:varchar!,response_config:text,executed_at:datetime,execution_result:text,created_at:datetime,updated_at:datetime`

**`security_incidents`** — `id:integer!,company_id:varchar!,incident_type:varchar!,severity:varchar!,description:text!,threat_indicators:text,incident_status:varchar!,detected_at:datetime!,investigation_started_at:datetime,resolved_at:datetime,resolution_notes:text,affected_resources:text,deleted_at:datetime,created_at:datetime,updated_at:datetime`

**`security_threat_indicators`** — `id:integer!,indicator_type:varchar!,indicator_value:varchar!,threat_level:varchar!,description:text,source:varchar,is_whitelisted:tinyint!,detected_at:datetime!,expires_at:datetime,created_at:datetime,updated_at:datetime`

**`security_trust_zones`** — `id:integer!,company_id:varchar!,zone_name:varchar!,trust_level:varchar!,ip_ranges:text,allowed_services:text,is_active:tinyint!,created_at:datetime,updated_at:datetime,zone_type:varchar,description:text,cidr_blocks:text,device_policies:text,authentication_policies:text,trust_score_minimum:integer,assigned_resources:text,deleted_at:datetime`

**`service_identities`** — `id:integer!,company_id:varchar!,service_name:varchar!,service_type:varchar!,public_key:text,private_key_hash:varchar,allowed_permissions:text,resource_restrictions:text,last_rotated_at:datetime,expires_at:datetime,is_active:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`session_security_events`** — `id:integer!,session_id:varchar,user_id:integer,event_type:varchar,ip_address:varchar,old_fingerprint:varchar,new_fingerprint:varchar,reason:text,severity:varchar!,action_taken:varchar!,created_at:datetime,updated_at:datetime,tenant_id:varchar`

**`sessions_enhanced`** — `id:varchar!,user_id:integer,ip_address:varchar,user_agent_hash:varchar,device_fingerprint:varchar,browser_fingerprint:varchar,device_type:varchar,created_at:datetime,updated_at:datetime,last_activity_at:datetime,expires_at:datetime,fingerprint_checked_at:datetime,regeneration_count:integer!,concurrent_session_number:integer!,suspicious_activity_count:integer!,tenant_id:varchar`

### Settings (2 tables)

**`setting_groups`** — `id:integer!,tenant_id:integer!,name:varchar!,label:varchar!,description:text,icon:varchar,sort_order:integer!,is_system:tinyint!,created_at:datetime,updated_at:datetime`

**`settings`** — `id:integer!,tenant_id:integer,group_id:integer,key:varchar!,value:text,type:varchar!,label:varchar,description:text,is_public:tinyint!,is_system:tinyint!,created_at:datetime,updated_at:datetime,module:varchar,value_type:varchar!`

### Setup (9 tables)

**`setup_company_profiles`** — `id:integer!,tenant_id:varchar!,company_name:varchar!,legal_name:varchar,company_type:varchar,industry:varchar,country_code:varchar,currency_code:varchar,timezone:varchar,fiscal_year_start:integer,phone:varchar,email:varchar,website:varchar,address:varchar,city:varchar,postal_code:varchar,vat_number:varchar,logo_path:varchar,admin_profile:text,modules_selected:text,workflows_config:text,apps_config:text,onboarding_completed:tinyint!,onboarding_completed_at:datetime,created_at:datetime,updated_at:datetime,vat_exempt:tinyint!`

**`setup_field_mappings`** — `id:integer!,import_job_id:integer!,source_field:varchar!,target_field:varchar!,transform_type:varchar,transform_config:text,is_required:tinyint!,ai_suggested:tinyint!,ai_confidence:numeric,user_confirmed:tinyint!,created_at:datetime,updated_at:datetime,target_table:varchar,source_sample:text,is_ai_suggested:tinyint!,is_confirmed:tinyint!`

**`setup_funnel_snapshots`** — `id:integer!,tenant_id:varchar!,period:varchar!,snapshot_date:date!,started:integer!,completed:integer!,abandoned:integer!,completion_rate:numeric!,avg_duration_minutes:numeric!,created_at:datetime,updated_at:datetime`

**`setup_import_errors`** — `id:integer!,import_job_id:integer!,row_number:integer!,field_name:varchar,error_type:varchar!,error_message:text!,raw_data:text,created_at:datetime,updated_at:datetime,is_skipped:tinyint!`

**`setup_import_jobs`** — `id:integer!,tenant_id:varchar!,name:varchar!,source_type:varchar!,source_file_path:varchar,source_db_driver:varchar,source_db_config:text,target_module:varchar!,target_entity:varchar!,status:varchar!,total_rows:integer!,imported_rows:integer!,failed_rows:integer!,error_summary:text,ai_mapping_used:tinyint!,ai_mapping_confidence:numeric,created_by:integer,started_at:datetime,completed_at:datetime,deleted_at:datetime,created_at:datetime,updated_at:datetime`

**`setup_onboarding_funnel_snapshots`** — `id:integer!,tenant_id:integer!,snapshot_date:date!,sessions_started:integer!,sessions_completed:integer!,sessions_abandoned:integer!,avg_duration_seconds:integer,median_duration_seconds:integer,step1_completion_rate:numeric,step2_completion_rate:numeric,step3_completion_rate:numeric,step4_completion_rate:numeric,step5_completion_rate:numeric,ai_mapping_adoption_rate:numeric,created_at:datetime`

**`setup_onboarding_sessions`** — `id:integer!,tenant_id:varchar!,user_id:integer,started_at:datetime!,completed_at:datetime,abandoned_at:datetime,current_step:varchar!,total_duration_seconds:integer,source_type:varchar!,rows_imported:integer!,ai_mapping_used:tinyint!,ai_mapping_accepted_percent:numeric,errors_count:integer!,created_at:datetime,updated_at:datetime`

**`setup_onboarding_step_events`** — `id:integer!,onboarding_session_id:integer!,step_name:varchar,event_type:varchar,step_data:text,duration_seconds:integer,occurred_at:datetime,created_at:datetime,updated_at:datetime,tenant_id:integer,user_id:integer,step:integer,event:varchar,metadata:text`

**`setup_source_schemas`** — `id:integer!,import_job_id:integer!,columns:text,sample_data:text,total_rows:integer!,detected_encoding:varchar!,detected_delimiter:varchar,created_at:datetime,updated_at:datetime,detected_columns:text,row_count:integer!,sheet_names:text`

### Shared (3 tables)

**`shared_countries`** — `id:integer!,iso_alpha2:varchar!,iso_alpha3:varchar!,name:varchar!,name_fr:varchar,name_local:varchar,currency_code:varchar,phone_prefix:varchar,region:varchar,subregion:varchar,is_ohada:tinyint!,is_uemoa:tinyint!,is_cemac:tinyint!,vat_rate:numeric,fiscal_year_start:varchar,timezone:varchar,flag_emoji:varchar`

**`shared_currencies`** — `id:integer!,code:varchar!,name:varchar!,name_fr:varchar,symbol:varchar!,symbol_native:varchar,decimals:integer!,is_cfa:tinyint!,is_active:tinyint!,exchange_rate_to_usd:numeric,exchange_rate_updated_at:datetime,region:varchar`

**`shared_preferences`** — `id:integer!,tenant_id:integer!,user_id:integer,preference_key:varchar!,preference_value:text,created_at:datetime,updated_at:datetime`

### Validation (12 tables)

**`validation_approval_actions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,request_id:integer,approver_id:integer,action:varchar,comment:text,acted_at:datetime`

**`validation_approval_hierarchies`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,name:varchar,description:text,company_id:integer,module_name:varchar,is_active:tinyint!,escalation_role:varchar,deleted_at:datetime`

**`validation_approval_history`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,request_id:integer,action:varchar,old_status:varchar,new_status:varchar,changed_by:integer,changed_at:datetime,level:integer`

**`validation_approval_requests`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,workflow_id:integer,resource_type:varchar,resource_id:integer,requested_by:integer,amount:numeric,currency:varchar,current_level:integer!,total_levels:integer!,deleted_at:datetime,approver_id:integer,approved_by:integer,approved_at:datetime,rejected_at:datetime,approvable_type:varchar,approvable_id:integer,hierarchy_id:integer,escalated_from_id:integer,escalation_reason:varchar`

**`validation_approval_rules`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,workflow_id:integer,rule_order:integer!,condition_type:varchar,condition_operator:varchar,condition_value:varchar,condition_field:varchar,required_approvers_count:integer!,approval_mode:varchar!,hierarchy_id:integer`

**`validation_approval_workflows`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,name:varchar,module:varchar,resource_type:varchar,is_active:tinyint!,threshold_amount:numeric,currency:varchar,module_name:varchar,description:text,created_by:integer,deleted_at:datetime`

**`validation_hierarchy_levels`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,hierarchy_id:integer,level_order:integer!,title:varchar,approver_count:integer!,delegation_allowed:tinyint!`

**`validation_level_approvers`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,hierarchy_level_id:integer,user_id:integer,role:varchar,approver_order:integer!,backup_user_id:integer,backup_role:varchar,is_active:tinyint!`

**`validation_rule_dependencies`** — `id:integer!,rule_id:integer!,depends_on_rule_id:integer!,created_at:datetime,updated_at:datetime`

**`validation_rule_set_rule`** — `id:integer!,rule_set_id:integer!,rule_id:integer!,created_at:datetime,updated_at:datetime`

**`validation_rule_sets`** — `id:integer!,name:varchar!,description:text,version:integer!,created_at:datetime,updated_at:datetime`

**`validation_rules`** — `id:integer!,name:varchar!,field:varchar!,type:varchar!,params:text,message:varchar,created_at:datetime,updated_at:datetime`

### Workflow (25 tables)

**`automation_connections`** — `id:integer!,flow_id:integer,source_node_id:varchar,target_node_id:varchar,condition:varchar,data:text,created_at:datetime,updated_at:datetime,condition_type:varchar!,condition_expr:text`

**`automation_executions`** — `id:integer!,automation_rule_id:integer,triggered_by_user_id:integer,flow_id:integer,flow_key:varchar,tenant_id:integer,module:varchar,trigger:varchar,triggered_payload:text,trigger_data:text,context:text,status:varchar!,error_message:text,execution_result:text,node_results:text,error_node_id:integer,duration_ms:integer,correlation_id:varchar,started_at:datetime,completed_at:datetime,ended_at:datetime,created_at:datetime,updated_at:datetime`

**`automation_flow_templates`** — `id:integer!,key:varchar,name:varchar!,description:text,category:varchar,nodes:text,edges:text,is_active:tinyint!,usage_count:integer!,created_at:datetime,updated_at:datetime`

**`automation_flows`** — `id:integer!,tenant_id:integer,key:varchar,name:varchar!,description:text,trigger:varchar,trigger_type:varchar,trigger_config:text,nodes:text,edges:text,status:varchar!,is_active:tinyint!,icon:varchar,color:varchar,tags:text,version:integer!,created_by:integer,last_run_at:datetime,total_runs:integer!,success_runs:integer!,created_at:datetime,updated_at:datetime,deleted_at:datetime,version_number:integer!,is_published:tinyint!,parent_version_id:integer`

**`automation_nodes`** — `id:integer!,flow_id:integer,node_id:varchar,type:varchar,config:text,position_x:integer!,position_y:integer!,on_success:text,on_error:text,data:text,created_at:datetime,updated_at:datetime,node_type:varchar,node_key:varchar,label:varchar,input_schema:text,output_schema:text,error_handling:varchar!`

**`automation_rules`** — `id:integer!,module:varchar!,action:varchar!,name:varchar,description:text,conditions:text!,actions:text,is_enabled:tinyint!,disabled_for_roles:text,execution_count:integer!,skip_count:integer!,last_executed_at:datetime,created_by:integer,updated_by:integer,created_at:datetime,updated_at:datetime,key:varchar,trigger_event:varchar,is_active:tinyint!`

**`automation_templates`** — `id:integer!,name:varchar!,description:text,category:varchar,icon:varchar,flow_definition:text!,is_builtin:tinyint!,created_at:datetime,updated_at:datetime`

**`automation_variables`** — `id:integer!,flow_id:integer,name:varchar,type:varchar!,default_value:text,created_at:datetime,updated_at:datetime`

**`doc_approval_instances`** — `id:integer!,tenant_id:integer,name:varchar,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,completed_at:datetime,document_id:integer,workflow_id:integer,current_step:integer!`

**`documents`** — `id:integer!,folder_id:integer,name:varchar!,type:varchar!,mime_type:varchar,size:integer!,path:varchar,owner_id:integer,is_public:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime,created_by:integer,title:varchar,file_path:text,file_size:integer,storage_path:text,extension:varchar,description:text,disk:varchar!,uploaded_by:integer,version:varchar!,tags:text,is_locked:tinyint!,status:varchar!,requires_approval:tinyint!`

**`flow_versions`** — `id:integer!,flow_id:integer!,version_number:integer!,label:varchar,created_by:integer,nodes_snapshot:text!,connections_snapshot:text!,flow_meta:text,created_at:datetime,updated_at:datetime`

**`logic_action_types`** — `id:integer!,slug:varchar!,label:varchar!,description:varchar!,required_params:text!,target_models:text,sort_order:integer!,created_at:datetime,updated_at:datetime`

**`logic_condition_types`** — `id:integer!,slug:varchar!,label:varchar!,description:varchar!,applicable_types:text!,sort_order:integer!,created_at:datetime,updated_at:datetime`

**`logic_rule_executions`** — `id:integer!,rule_id:integer!,trigger_data:text!,conditions_met:tinyint!,actions_executed:text!,error_message:text,duration_ms:integer!,executed_at:datetime!`

**`logic_rules`** — `id:integer!,tenant_id:integer!,name:varchar!,description:text,trigger:varchar!,conditions:text!,actions:text!,is_enabled:tinyint!,execution_count:integer!,last_executed_at:datetime,created_by:integer!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`wfd_actions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,workflow_id:integer,name:varchar,type:varchar,config:text,order:integer!,action_type:varchar,action_config:text,node_id:varchar,flow_id:integer,position_x:integer!,position_y:integer!,sort_order:integer!,deleted_at:datetime`

**`wfd_definitions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,name:varchar,description:varchar,is_active:tinyint!,trigger_type:varchar,nodes:text,edges:text,created_by:integer,module:varchar,trigger_event:varchar,conditions:text,actions:text,trigger_conditions:text,deleted_at:datetime,last_run_at:datetime,run_count:integer!`

**`wfd_execution_logs`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,execution_id:integer,action_id:integer,output:text,error_message:text,executed_at:datetime,duration_ms:integer,result:text`

**`wfd_executions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,definition_id:integer,trigger_data:text,started_at:datetime,completed_at:datetime,error_message:text,workflow_id:integer,input:text,output:text,flow_id:integer,context:text,node_results:text,deleted_at:datetime`

**`workflow_actions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,workflow_id:integer,action_type:varchar,action_target:varchar,action_params:text,delay_seconds:integer!,retry_count:integer!,sequence:integer!,is_active:tinyint!,notes:text,deleted_at:datetime`

**`workflow_chain_definitions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,trigger_key:varchar,is_active:tinyint!,name:varchar,description:text,trigger_module:varchar,conditions:text,actions:text,execution_count:integer!,last_executed_at:datetime`

**`workflow_chain_executions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,workflow_definition_id:integer,trigger_key:varchar,context_snapshot:text,started_at:datetime,completed_at:datetime,result_log:text,error_message:text`

**`workflow_execution_steps`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,execution_id:integer,step_index:integer,action_key:varchar,input_context:text,output:text,duration_ms:integer,executed_at:datetime,error_message:text`

**`workflow_executions`** — `id:integer!,workflow_id:integer!,triggered_by:integer,trigger_data:text,status:varchar!,context:text,started_at:datetime,completed_at:datetime,duration_ms:integer!,error_message:text,trigger_entity_id:integer,trigger_entity_type:varchar,triggered_at:datetime,notes:text,execution_depth:integer!,payload_size_bytes:integer!,parent_execution_id:integer,deleted_at:datetime`

**`workflow_steps`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,execution_id:integer,action_type:varchar,sequence:integer!,result:text,error_message:text,started_at:datetime,completed_at:datetime,duration_ms:integer,notes:text,deleted_at:datetime`

