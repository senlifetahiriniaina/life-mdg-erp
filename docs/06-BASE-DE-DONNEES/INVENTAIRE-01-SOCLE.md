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
