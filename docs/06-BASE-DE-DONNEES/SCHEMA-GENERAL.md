# Schéma de base de données — vue générale

Cette section a été reconstruite pour Chantier 8.7, puis **re-générée le 2026-08-21** directement depuis le schéma SQLite réellement migré (`php artisan migrate:fresh --seed` + `Schema::getColumns()` sur chaque table, pas une relecture manuelle des fichiers de migration) — la source de vérité la plus fiable après plusieurs chantiers de nettoyage (voir plus bas) et l'ajout de nouvelles tables (Chantiers 15-26) depuis la version précédente de ce document. Méthode : `grep` de tous les `Schema::create(...)` pour la narration historique ci-dessous (littéraux **et** en boucle sur un tableau de noms), et une extraction en direct du schéma vivant pour les chiffres et le détail colonne par colonne (`docs/06-BASE-DE-DONNEES/INVENTAIRE-0{1..6}-*.md`).

**Chiffres clés, réels au 2026-08-21** (voir `docs/06-BASE-DE-DONNEES/INVENTAIRE-0{1..6}-*.md` pour le détail table par table) :

| Catégorie | Nombre de tables |
|---|---|
| **Total tables du schéma (live, `migrate:fresh --seed`)** | **599** |
| Tables des 27 modules du périmètre Life MDG (INVENTAIRE-01 à 05) | 543 |
| Tables du module Messaging, ajouté hors périmètre initial au Chantier 20 (INVENTAIRE-06) | 3 |
| Tables racine partagées (framework Laravel, RBAC, RGPD, cache, webhooks, portail superadmin…) | 53 |
| *(hors total)* Modèles réels déclarant une table absente de toute migration, y compris stub | 3 (`bi_audience_segments`/`bi_data_refresh_schedules`/`bi_timeseries_data`, `crm_forecast_inputs`/`crm_forecast_models` — voir § plus bas ; les 5 autres landmines documentées ici jusqu'à Chantier 8.6/8.7 — `achats_purchase_invoice_matches`, `ai_usage_limits`, `tenant_audit_log`, `tenant_invitations`, `tenant_users` — ont depuis reçu une vraie migration et existent en base) |

**Ce que ce total de 599 ne montre plus** (chiffres historiques, gardés ici pour contexte) : la version précédente de ce document (Chantier 8.7) comptait **≈ 803 tables**, dont 184 tables « hors périmètre » (scaffolding mort pour des modules explicitement exclus — Manufacturing, POS, Ecommerce, Email, WhatsApp, Documents, Discussion, Mobile/MobileSync, Planning, Quality, MarketingAutomation) et 11 tables d'un moteur d'approbation/workflow racine mort. **Chantier 9** (voir `CLAUDE.md`) a depuis supprimé la quasi-totalité des 184 tables hors périmètre (confirmé ici : plus aucun préfixe `ec_`/`mfg_`/`pos_`/`wa_`/`email_`/`doc_`/`whatsapp_`/`planning_`/`quality_` en dehors des 2-3 tables kept explicitement documentées ci-dessous) — d'où l'essentiel de l'écart entre 803 et 599, le reste étant l'ajout net de nouvelles tables par les Chantiers 15-26 (`acc_operation_templates`, `acc_financial_simulations`/`_lines`, `acc_finance_reviews`, `inventory_costing_sheets`/`_lines`, `inventory_production_orders`, `sales_objectives`, `sales_recurring_order_templates`/`_lines`, `msg_conversations`/`_participants`/`msg_messages`…).

## Le piège : où vit vraiment le schéma d'un module

Le réflexe naturel — chercher les tables d'un module dans `Modules/<Nom>/database/migrations/` — **ne suffit pas** pour la majorité des modules. Trois mécanismes coexistent, empilés au fil des phases de développement de WideHalo puis des chantiers Life MDG :

### 1. Migrations propres au module (`Modules/<Nom>/database/migrations/`)

Contiennent un vrai schéma métier dès l'origine, ou patchent une table créée ailleurs (cas très fréquent — voir l'exemple `hd_tickets` ci-dessous). Volumétrie très inégale : `Helpdesk` (11 fichiers), `Setup`/`Security`/`Integration` (9-12), `Core`/`BI`/`CRM`/`Calendar` (8-10), mais `Validation` et `Timesheets` n'ont **aucune** migration propre — 100 % de leur schéma vit à la racine.

### 2. Créations littérales à la racine (`database/migrations/*.php`)

Les plus grosses tables métier des modules fondateurs (Accounting, HR, Helpdesk, CRM, Inventory, Projects…) sont créées par de gros fichiers de migration racine datés du lancement (`2026_05_01_*`, `2026_05_29_000001_create_accounting_core_tables.php`, `2026_05_29_000003_create_all_missing_module_tables.php`…), avec un vrai schéma (types, valeurs par défaut, FK). C'est le cas de `acc_invoices`, `hr_employees`, `hd_tickets`, `prj_projects`, `crm_contacts`, etc.

**Illustration concrète du mécanisme « racine crée, module patche »** : `hd_tickets` est créé par la migration racine `2026_05_01_000005_create_core_helpdesk_email_tables.php` avec un schéma minimal (`ticket_number`, `user_id`, `assigned_to`, `subject`, `priority`, `status`, `category`). Les colonnes `customer_id`, `assignee_id`, la relation polymorphe `source_type`/`source_id` (celle qui porte le trait `HelpdeskLinkable` documenté dans `CLAUDE.md`) et plusieurs colonnes « fillable » ont toutes été ajoutées **après coup** par 4 migrations distinctes dans `Modules/Helpdesk/database/migrations/`. Le modèle Eloquent réel n'est donc jamais représenté par un seul fichier de migration — il faut lire la racine **et** le module, dans l'ordre chronologique, pour reconstituer le vrai schéma d'une table.

### 3. Le générateur de stubs en boucle — `2026_05_29_000003_create_all_missing_module_tables.php`

C'est le fichier le plus important à comprendre et la source du **phénomène des « tables stub »** documenté dans `CLAUDE.md` (Known Gaps, et de nombreuses entrées de chantier 8.x). Ce fichier unique (712 lignes) itère sur ~23 tableaux de noms de tables (un par module, y compris des modules **exclus** du périmètre Life MDG) et crée, pour chaque nom, une table au schéma générique quasi identique :

```php
$acc = ['acc_budget_actuals', 'acc_budget_alerts', /* … 40 autres … */];
foreach ($acc as $t) {
    if (!Schema::hasTable($t)) {
        Schema::create($t, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->text('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
```

(La forme exacte varie légèrement selon le groupe — certains ajoutent `status`, d'autres omettent `softDeletes()` — mais le principe est le même : `id`/`tenant_id`/[`status`]/`data`/`timestamps`, sans aucune colonne métier.)

**Cette technique a produit 207 tables dans le seul périmètre des 27 modules Life MDG.** Beaucoup ont depuis été patchées avec de vraies colonnes par des migrations additives (`patch_*`) au fil des chantiers 8.1-8.5 — c'est documenté table par table dans `CLAUDE.md` (ex. les 8 tables BI patchées en `2026_08_25_*`, les colonnes ajoutées à 11 tables Inventory/Logistics en Chantier 8.3 partie 3, les 6 tables HR biométrie/absences en Chantier 8.3 partie 2…). **95 des 207 tables stub d'origine ont reçu au moins une migration de patch** ; les **112 restantes n'ont jamais été touchées depuis leur création** et sont donc, sauf preuve du contraire, toujours au schéma générique `id/tenant_id/[status]/data/timestamps` — c'est très probablement (mais pas garanti à 100 % sans relire chaque modèle) le même ensemble que les « 36 des 42 tables `acc_` sans modèle Eloquent » documenté dans `CLAUDE.md`, généralisé aux 27 modules. Les fichiers `docs/06-BASE-DE-DONNEES/INVENTAIRE-0{1..5}-*.md` marquent explicitly chaque table stub comme **« patchée »** ou **« stub pur »** — mais dans les deux cas, vérifiez le modèle Eloquent réel (`$fillable`, `$table`) avant d'écrire une requête, une table « patchée » n'a pas forcément toutes les colonnes que son modèle attend (c'est exactement le genre de bug documenté à répétition dans `CLAUDE.md`, ex. Inventory/Logistics Chantier 8.3 partie 3).

### Une quatrième catégorie : des modèles réels sans aucune table, même stub

En croisant systématiquement tous les `protected $table = '...'` déclarés par les modèles Eloquent des 27 modules contre l'inventaire de migrations ci-dessus, **10 tables déclarées par un modèle réel n'existent dans aucune migration du dépôt, pas même au schéma générique stub** — un « table not found » garanti au premier appel, plus sévère que le phénomène stub (qui, lui, laisse au moins une table interrogeable) : `achats_purchase_invoice_matches` (Achats, `PurchaseInvoiceMatch`), `ai_usage_limits` (AI, `AiUsageLimit`), `bi_audience_segments`/`bi_data_refresh_schedules`/`bi_timeseries_data` (BI, `AudienceSegment`/`DataRefreshSchedule`/`TimeseriesData`), `crm_forecast_inputs`/`crm_forecast_models` (CRM, `ForecastInput`/`ForecastModel` — homonyme du `ForecastModel` d'Analytics et de BI, 3 classes distinctes), `tenant_audit_log`/`tenant_invitations`/`tenant_users` (Core, `TenantAuditLog`/`TenantInvitation`/`TenantUser` — les trois **activement référencées** par `TenantManagerService`, le service de provisioning derrière le portail superadmin Phase 40). Détail et contexte dans le fichier `INVENTAIRE-0{1,2,4}-*.md` correspondant à chaque module.

### Conséquence pratique

Pour connaître le vrai schéma d'une table donnée :
1. Chercher `Schema::create('<table>'` dans `database/migrations/` **et** dans `Modules/*/database/migrations/`.
2. Si rien ne sort, chercher le nom de la table dans le tableau en boucle de `2026_05_29_000003_create_all_missing_module_tables.php` (elle y est très probablement).
3. Chercher ensuite tous les `Schema::table('<table>'` (patches additifs) dans les deux emplacements, triés par date, pour voir les colonnes ajoutées après coup.
4. Croiser avec `$fillable`/`$casts` du modèle Eloquent réel — c'est la source de vérité finale, pas la migration seule (des colonnes migrées peuvent être absentes de `$fillable`, silencieusement ignorées en mass-assignment — motif récurrent documenté dans `CLAUDE.md`, ex. le bug salaire zéro de Payroll).

## Tables « hors périmètre » : ce qu'il en reste après Chantier 9 (quasi rien)

**Section largement obsolète, gardée pour contexte historique** — au moment du dernier `SCHEMA-GENERAL.md` (Chantier 8.7), le même fichier catch-all (`2026_05_29_000003_create_all_missing_module_tables.php`) scaffoldait encore ~184 tables « hors périmètre » pour des modules explicitement exclus (voir `CLAUDE.md` § Scope). **Chantier 9** (voir `CLAUDE.md`) a depuis supprimé la quasi-totalité de ce résidu via `database/migrations/2026_09_02_000001_drop_excluded_module_and_collision_stub_tables.php` (179 tables) — confirmé ici par un balayage direct du schéma live au 2026-08-21 : plus aucune table `ec_`/`ecommerce_`/`mfg_`/`pos_`/`wa_`/`whatsapp_`/`email_`/`planning_`/`quality_`/`doc_`/`document_` (hors les quelques exceptions individuellement conservées et documentées ci-dessous), et plus aucun des 10 stubs génériques de collision de nom (`products`, `customers`, `suppliers`, `purchase_orders`, etc.).

**Ce qui a été délibérément gardé, avec un vrai consommateur** (voir `CLAUDE.md` § Chantier 9 pour le détail complet) : `customers` (le vrai `App\Models\Customer`, utilisé par Accounting), `products`/`purchase_order_lines` (raw-queried par `CostEngineService`/`ProductionForecastService`/`DashboardService`), `documents`/`quality_inspections` (écrits par `Modules\Workflow\Services\Actions\{Documents,Quality}ActionHandler`), `mfg_production_orders` (toujours interrogée par `KPIRegistryService` pour les ratios TRS/taux de défaut — un écart doc/code non résolu, déjà documenté dans `CLAUDE.md`), `sync_queue`/`push_tokens` (`SyncController`/`SyncService`/export RGPD), `workflow_executions` (lue par `ModuleEventAggregatorService`), `approval_overrides` (`ApprovalOverrideService`, sans lien avec le moteur workflow racine mort documenté ci-dessous).

**Note historique conservée** : la découverte initiale de ce catch-all remonte à Chantier 8.7, méthode inchangée pour la re-vérifier — `grep -n "Schema::create" database/migrations/2026_05_29_000003_create_all_missing_module_tables.php` sur les blocs `// ── DOCUMENTS`, `// ── ECOMMERCE`, `// ── EMAIL`, `// ── MANUFACTURING`, `// ── MARKETING / CAMPAIGNS`, `// ── PLANNING`, `// ── POS`, `// ── QUALITY`, `// ── WHATSAPP` du même fichier.

## Un troisième moteur d'approbation/workflow, mort, à la racine de `app/`

En creusant les tables non préfixées `workflows`, `approvals`, `approval_chain(s)`, `approval_requests`, `approval_overrides`, `workflow_conditions`, `workflow_executions`, `workflow_step_logs`, `workflow_triggers`, `workflow_audit_logs` (11 tables), on trouve un **troisième** moteur de workflow/approbation dans ce dépôt — distinct des deux déjà documentés ailleurs :

1. `Modules\Workflow` (préfixes `wfd_`/`automation_`, moteur n8n-like Phase 39 + DSL/connecteurs Phase 52, chantier 8.5-light a supprimé son bloc de routes legacy mort).
2. `Modules\Validation` (préfixe `validation_`, moteur d'approbation générique multi-niveaux, chantier 8.5sv l'a audité et corrigé).
3. ~~`App\Models\Workflow` / `Approval` / `ApprovalChain` / `WorkflowExecution` / `WorkflowAuditLog` / `WorkflowStepLog`, à la racine de `app/Models/`~~ — **ce troisième moteur mort a été supprimé pour de bon** (voir `CLAUDE.md` § Chantier 9 : "A third, fully dead parallel workflow/approval engine at the plain `app/` namespace was found and deleted"), confirmé ici par une vérification directe au 2026-08-21 (`app/Models/` ne contient plus aucun de ces fichiers, `app/Services/WorkflowEngine.php` n'existe plus). Ses 8 tables propres (`workflows`, `approvals`, `approval_chains`, `workflow_step_logs`, `workflow_conditions`, `approval_requests`, `workflow_triggers`, `workflow_audit_logs`) ont été droppées dans la même migration de nettoyage — confirmé absentes du schéma live. **Deux tables de la liste d'origine ont volontairement été gardées, pour un motif sans rapport avec ce moteur mort** : `workflow_executions` (lue par `Modules\Calendar\Services\ModuleEventAggregatorService`) et `approval_overrides` (écrite par `app/Services/AuditLog/ApprovalOverrideService.php`, un service différent et bien réel). Une troisième table qui porte un nom proche, `approval_chain` (singulier, sans "s"), **existe toujours mais n'a jamais appartenu à ce moteur mort** — c'est la table réelle de `Modules\Accounting\Models\InvoiceApproval`, une coïncidence de nommage à ne pas confondre avec l'ex-`approval_chains` (pluriel) supprimée.

## Convention de nommage des tables

La majorité des modules préfixe ses tables réelles avec une abréviation courte, **mais avec des irrégularités confirmées par lecture directe** (pas supposées) :

| Module | Préfixe dominant | Exceptions confirmées |
|---|---|---|
| Accounting | `acc_` (94 tables) | 23 tables sans préfixe (`asset_impairments`, `consolidation_*`, `cost_*`, `depreciation_*`, `intercompany_*`, `revenue_contracts`, `tax_*`…) créées par la même migration `2026_05_29_000001_create_accounting_core_tables.php` — fonctionnalités de phase plus tardive (Budget variance, Consolidation, Cost Engine, ASC606, Dépréciation) qui n'ont simplement jamais reçu le préfixe `acc_` |
| HR | `hr_` | Aucune exception confirmée |
| Helpdesk | `hd_` (principal) | `helpdesk_*` (forums, CSAT, assignations) et `cs_*` (27 tables d'IA service client) sont deux préfixes additionnels du même module |
| Security | `security_` (8 tables) | 4 tables sans préfixe (`compliance_audits`, `encrypted_fields`, `key_rotation_logs`, `service_identities`) créées par une migration Security dédiée mais sans le préfixe |
| Logistics | `logistics_` (principal) | `lgx_` et `wh_` coexistent (voir `docs/03-MODULES/Logistics.md` — `wh_*`/`lgx_*` étaient en grande partie un sous-système mort supprimé au Chantier 8.3 partie 6 ; `lgx_vehicles`/`lgx_hs_codes`/`lgx_delivery_routes`/`lgx_route_stops`/`lgx_carrier_rate_cards` restent réels) |
| Timesheets | aucun préfixe dominant | `timesheet_entries`/`timesheets_entries`/`timesheets_sheets`/`ts_timesheet_periods`/`ts_project_billing`/`time_allocations`/`time_tracking_projects` — 4 conventions différentes pour le même module, documenté en détail dans `docs/03-MODULES/Timesheets.md` |
| AuditLog | `audit_logs` (son propre modèle) | La table réellement lue par l'API/UI du module est `core_audit_logs`, propriété du module **Core** — voir `docs/03-MODULES/AuditLog.md` |
| Workflow | `wfd_`/`automation_` | `connector_*`/`logic_*` (Phase 52, connecteurs SaaS + moteur DSL) n'ont ni l'un ni l'autre préfixe |

Point d'attention historique (déjà documenté ici et dans `docs/03-MODULES/Helpdesk.md`) : lors de l'extraction depuis WideHalo ERP, plusieurs intégrations cross-modules référençaient encore le nom `helpdesk_tickets` en SQL brut alors que la vraie table s'appelle `hd_tickets` — corrigé pendant l'extraction. Vérifiez toujours le nom réel de la table dans la migration plutôt que de le déduire du nom du modèle.

## Tables partagées clés (racine, 53 au total)

**Le compte est passé de 32 (Chantier 8.7) à 53** — pas une explosion réelle, surtout des tables déjà réelles à l'époque mais jamais dénombrées ici (Telescope, superadmin `admin_*`, `bill_of_materials`/`bom_lines`/`products`/`purchase_order_lines` — stubs de collision de nom déjà documentés plus haut, confirmés toujours présents et toujours sans consommateur réel sauf les 4 tables individuellement listées dans la section Chantier 9 ci-dessus), plus quelques ajouts nets réels (`companies`, `integrations`, `tenants`, `approval_chain`/`approval_overrides`).

| Table | Rôle |
|---|---|
| `users` | Comptes utilisateurs — porte aussi les colonnes fantômes `tenant_id`/`role`, jamais dans `$fillable`, documentées à de multiples reprises dans `CLAUDE.md` comme source de fuites cross-tenant corrigées (la vraie frontière de tenant est `company_id`) |
| `roles` / `permissions` / `model_has_roles` / `model_has_permissions` / `role_has_permissions` | RBAC (spatie/laravel-permission), créées par `2026_05_02_073520_create_permission_tables.php` (le seul autre fichier du dépôt à utiliser la même technique de boucle que le catch-all module) |
| `personal_access_tokens` | Tokens Sanctum |
| `sessions` / `password_reset_tokens` / `password_histories` | Authentification |
| `notifications` | File de notifications Laravel standard — voir `CLAUDE.md` § Chantier 20 pour le vrai chemin d'écriture (`NotificationService::sendToUser()`, colonne `data` JSON, pas `title`/`message`) |
| `cache` / `cache_locks` / `jobs` / `job_batches` / `failed_jobs` | Framework Laravel (cache DB driver, queue) |
| `cache_performance_logs` / `cache_strategies` / `cached_query_patterns` | Infrastructure de cache applicative additionnelle (`2026_05_17_180000_add_caching_infrastructure.php`) |
| `consent_logs` / `user_consents` / `gdpr_requests` / `gdpr_exports` | Conformité RGPD/PDPL au niveau racine (distinctes de `core_gdpr_consents`/`core_data_requests`, propriété du module Core — deux implémentations RGPD coexistent, voir `docs/03-MODULES/Core.md` et `docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md`) |
| `webhooks` / `webhook_deliveries` / `webhook_audit_logs` | Webhooks sortants génériques, signés HMAC |
| `export_audit_logs` / `audit_log_access_logs` | Télémétrie d'export et d'accès à l'audit |
| `analytics_events` | Télémétrie d'événements produit générique |
| `mv_*` (8 tables) | Vues matérialisées cross-module pour analytics (`mv_crm_pipeline_summary`, `mv_hr_metrics_summary`, `mv_inventory_stock_summary`, `mv_sales_daily_summary`, `mv_project_health_summary`, `mv_document_analytics`, `mv_email_campaign_analytics`, `mv_manufacturing_daily_kpis` — la dernière porte sur un module exclu) |
| `companies` | `App\Models\Company` — la vraie frontière de tenant (`company_id`) que la quasi-totalité des correctifs cross-tenant documentés dans `CLAUDE.md` cette session pointent désormais vers cette table plutôt que vers la colonne fantôme `users.tenant_id` |
| `tenants` | Le modèle multi-tenant Phase 40 (`TenantManagerService`/portail superadmin) — un concept **distinct** de `companies`, coexistant sans être unifié (chacun a son propre id-space, voir `docs/03-MODULES/Core.md`) |
| `integrations` | Config générique de connecteur, distincte de `Modules\Integration\Models\IntegrationConnector` (module dédié) — même risque de confusion de nom que `approval_chain`/`approval_chains` documenté plus haut |
| `admin_audit_logs` / `admin_backups` / `admin_backup_schedules` / `admin_server_configs` | Portail superadmin Phase 40 — distinctes de `core_audit_logs` (le vrai journal d'audit applicatif, propriété du module Core) |
| `telescope_entries` / `telescope_entries_tags` / `telescope_monitoring` | Laravel Telescope (outil de debug dev, pas un composant métier) |
| `approval_chain` / `approval_overrides` | Voir la section « troisième moteur d'approbation/workflow » plus haut — `approval_chain` (singulier) appartient à `Modules\Accounting\Models\InvoiceApproval`, `approval_overrides` à `app/Services/AuditLog/ApprovalOverrideService.php` ; ni l'une ni l'autre ne fait partie du moteur mort déjà supprimé |
| `bill_of_materials` / `bom_lines` / `products` / `purchase_order_lines` / `customers` | Stubs de collision de nom déjà détaillés dans la section « hors périmètre » ci-dessus — `customers`/`products`/`purchase_order_lines` ont un vrai consommateur documenté, `bill_of_materials`/`bom_lines` sont un résidu Manufacturing sans aucun (confirmé via grep, laissé en l'état, même statut que les autres résidus inertes) |

### Détail des colonnes (succinct)

Extrait le 2026-08-21 directement du schéma SQLite réellement migré (`Schema::getColumns()`), pas des fichiers de migration — la source de vérité la plus fiable compte tenu du mécanisme « racine crée, module patche » documenté plus haut. Format : `colonne:type` — `!` = non nullable (requis). Types SQLite génériques (`integer`/`varchar`/`numeric`/`text`/`datetime`/`date`/`tinyint`) ; en production MySQL les types réels sont plus précis (`bigint unsigned`, `decimal(15,4)`, etc.) mais la structure des colonnes est identique.

**`activity_log`** — `id:integer!,log_name:varchar,description:text!,subject_type:varchar,subject_id:integer,causer_type:varchar,causer_id:integer,properties:text,created_at:datetime,updated_at:datetime,event:varchar,batch_uuid:varchar`

**`admin_audit_logs`** — `id:integer!,user_id:integer,action:varchar!,resource_type:varchar,resource_id:integer,ip_address:varchar,user_agent:varchar,payload:text,created_at:datetime,updated_at:datetime,signature:varchar,is_immutable:tinyint!,data_expires_at:datetime,archived_at:datetime,module:varchar`

**`admin_backup_schedules`** — `id:integer!,type:varchar!,frequency:varchar!,time_of_day:varchar,retention_days:integer!,storage_driver:varchar!,enabled:tinyint!,last_run_at:datetime,created_at:datetime,updated_at:datetime`

**`admin_backups`** — `id:integer!,type:varchar!,status:varchar!,size_bytes:integer,file_path:varchar,storage_driver:varchar!,notes:text,triggered_by:integer,started_at:datetime,completed_at:datetime,created_at:datetime,updated_at:datetime`

**`admin_server_configs`** — `id:integer!,name:varchar!,provider:varchar!,region:varchar!,instance_type:varchar!,ip_address:varchar,status:varchar!,credentials_encrypted:text,api_endpoint:varchar,metadata:text,last_ping_at:datetime,created_at:datetime,updated_at:datetime`

**`analytics_events`** — `id:integer!,user_id:integer,event_type:varchar,properties:text,created_at:datetime,updated_at:datetime`

**`approval_chain`** — `id:integer!,approval_request_id:integer!,approver_id:integer!,status:varchar!,comment:text,escalation_level:integer!,sequence_order:integer,assigned_at:datetime!,due_at:datetime,reminded_at:datetime,decided_at:datetime,escalated_at:datetime`

**`approval_overrides`** — `id:integer!,approver_id:integer!,original_approval_id:integer!,reason:text!,approval_type:varchar!,related_resource_id:integer,related_resource_type:varchar,created_at:datetime,updated_at:datetime`

**`audit_log_access_logs`** — `id:integer!,user_id:integer!,audit_log_id:integer!,action:varchar!,ip_address:varchar,user_agent:varchar,created_at:datetime!`

**`bill_of_materials`** — `id:integer!,tenant_id:integer,name:varchar,status:varchar!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`bom_lines`** — `id:integer!,bom_id:integer!,component:varchar,quantity:numeric!,created_at:datetime,updated_at:datetime`

**`cache`** — `key:varchar!,value:text!,expiration:integer!`

**`cache_locks`** — `key:varchar!,owner:varchar!,expiration:integer!`

**`cache_performance_logs`** — `id:integer!,cache_key:varchar!,status:varchar!,duration_ms:integer!,response_size_bytes:integer,route:varchar,user_id:varchar,accessed_at:datetime!`

**`cache_strategies`** — `id:integer!,identifier:varchar!,description:varchar,type:varchar!,config:text!,is_enabled:tinyint!,hit_count:integer!,miss_count:integer!,created_at:datetime,updated_at:datetime`

**`cached_query_patterns`** — `id:integer!,pattern_name:varchar!,query_signature:text!,parameters:text!,cache_ttl:integer!,tags:text!,execution_count:integer!,avg_duration_ms:numeric!,is_optimized:tinyint!,created_at:datetime,updated_at:datetime`

**`companies`** — `id:integer!,name:varchar!,code:varchar,currency:varchar!,timezone:varchar!,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`consent_logs`** — `id:integer!,user_id:integer,consent_type:varchar!,granted:tinyint!,ip_address:varchar,user_agent:varchar,expires_at:datetime,created_at:datetime,updated_at:datetime`

**`customers`** — `id:integer!,company_id:integer,name:varchar!,email:varchar,phone:varchar,created_at:datetime,updated_at:datetime`

**`export_audit_logs`** — `id:integer!,user_id:integer!,export_type:varchar!,entity_type:varchar!,entity_id:integer,filename:varchar!,export_id:varchar!,format:varchar!,row_count:integer!,file_size_bytes:integer!,includes_sensitive_data:tinyint!,ip_address:varchar,user_agent:text,filters_applied:text,status:varchar!,error_message:text,created_at:datetime,updated_at:datetime`

**`failed_jobs`** — `id:integer!,uuid:varchar!,connection:text!,queue:text!,payload:text!,exception:text!,failed_at:datetime!`

**`gdpr_exports`** — `id:integer!,user_id:integer!,request_id:integer!,file_path:varchar!,file_size:integer,expires_at:datetime!,downloaded_at:datetime,created_at:datetime,updated_at:datetime`

**`gdpr_requests`** — `id:integer!,user_id:integer!,request_type:varchar!,status:varchar!,reason:text,response:text,requested_at:datetime!,completed_at:datetime,created_at:datetime,updated_at:datetime,failure_reason:text`

**`integrations`** — `id:integer!,tenant_id:varchar!,integration_key:varchar!,name:varchar!,status:varchar!,credentials:text,settings:text,last_synced_at:datetime,sync_count:integer!,error_count:integer!,created_at:datetime,updated_at:datetime`

**`job_batches`** — `id:varchar!,name:varchar!,total_jobs:integer!,pending_jobs:integer!,failed_jobs:integer!,failed_job_ids:text!,options:text,cancelled_at:integer,created_at:integer!,finished_at:integer`

**`jobs`** — `id:integer!,queue:varchar!,payload:text!,attempts:integer!,reserved_at:integer,available_at:integer!,created_at:integer!`

**`migrations`** — `id:integer!,migration:varchar!,batch:integer!`

**`model_has_permissions`** — `permission_id:integer!,model_type:varchar!,model_id:integer!`

**`model_has_roles`** — `role_id:integer!,model_type:varchar!,model_id:integer!`

**`mv_crm_pipeline_summary`** — `pipeline_id:integer!,pipeline_name:varchar!,total_deals:integer!,pipeline_value:numeric!,stage_count:integer!,avg_deal_size:numeric!,win_rate:numeric!,open_deals:integer!,open_value:numeric!,refreshed_at:datetime!`

**`mv_document_analytics`** — `date:date!,total_documents:integer!,new_documents:integer!,total_downloads:integer!,total_shares:integer!,unique_users:integer!,total_storage_bytes:integer!,refreshed_at:datetime!`

**`mv_email_campaign_analytics`** — `campaign_id:integer!,campaign_name:varchar!,total_recipients:integer!,delivered:integer!,opened:integer!,clicked:integer!,open_rate:numeric!,click_rate:numeric!,unsubscribed:integer!,sent_date:datetime,refreshed_at:datetime!`

**`mv_hr_metrics_summary`** — `period_date:date!,total_employees:integer!,active_employees:integer!,on_leave:integer!,avg_utilization_rate:numeric!,total_timesheets:integer!,total_hours:numeric!,approved_timesheets:integer!,pending_approvals:integer!,refreshed_at:datetime!`

**`mv_inventory_stock_summary`** — `product_id:integer!,product_name:varchar!,sku:varchar!,total_quantity:integer!,reserved_quantity:integer!,available_quantity:integer!,reorder_point:integer!,is_low_stock:tinyint!,warehouse_locations:integer!,last_movement:datetime,refreshed_at:datetime!`

**`mv_manufacturing_daily_kpis`** — `date:date!,total_work_orders:integer!,in_progress_orders:integer!,completed_orders:integer!,avg_completion_rate:numeric!,total_quality_checks:integer!,passed_checks:integer!,failed_checks:integer!,quality_pass_rate:numeric!,refreshed_at:datetime!`

**`mv_project_health_summary`** — `project_id:integer!,project_name:varchar!,total_tasks:integer!,todo_tasks:integer!,in_progress_tasks:integer!,completed_tasks:integer!,completion_percentage:numeric!,overdue_tasks:integer!,team_members:integer!,expected_end_date:date,refreshed_at:datetime!`

**`mv_sales_daily_summary`** — `date:date!,total_orders:integer!,total_revenue:numeric!,avg_order_value:numeric!,total_items:integer!,completed_orders:integer!,completed_revenue:numeric!,refreshed_at:datetime!`

**`notifications`** — `id:varchar!,type:varchar!,notifiable_type:varchar!,notifiable_id:integer!,data:text!,read_at:datetime,created_at:datetime,updated_at:datetime`

**`password_histories`** — `id:integer!,user_id:integer!,password_hash:varchar!,created_at:datetime`

**`password_reset_tokens`** — `email:varchar!,token:varchar!,created_at:datetime`

**`permissions`** — `id:integer!,name:varchar!,guard_name:varchar!,created_at:datetime,updated_at:datetime`

**`personal_access_tokens`** — `id:integer!,tokenable_type:varchar!,tokenable_id:integer!,name:text!,token:varchar!,abilities:text,last_used_at:datetime,expires_at:datetime,created_at:datetime,updated_at:datetime`

**`products`** — `id:integer!,tenant_id:integer,name:varchar!,sku:varchar,status:varchar!,created_at:datetime,updated_at:datetime,deleted_at:datetime,product_code:varchar,description:text,price:numeric,is_active:tinyint!`

**`purchase_order_lines`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`role_has_permissions`** — `permission_id:integer!,role_id:integer!`

**`roles`** — `id:integer!,name:varchar!,guard_name:varchar!,created_at:datetime,updated_at:datetime`

**`sessions`** — `id:varchar!,user_id:integer,ip_address:varchar,user_agent:text,payload:text!,last_activity:integer!`

**`telescope_entries`** — `sequence:integer!,uuid:varchar!,batch_id:varchar!,family_hash:varchar,should_display_on_index:tinyint!,type:varchar!,content:text!,created_at:datetime`

**`telescope_entries_tags`** — `entry_uuid:varchar!,tag:varchar!`

**`telescope_monitoring`** — `tag:varchar!`

**`tenants`** — `id:text,slug:text,name:text,company_name:text,domain:text,plan:text,is_active:integer,data:text,tenant_id:text,trial_ends_at:datetime,onboarding_completed_at:datetime,settings:text,created_at:datetime,updated_at:datetime,uuid:varchar,legal_name:varchar,company_type:varchar,country_code:varchar,region:varchar,city:varchar,currency:varchar,timezone:varchar,locale:varchar,industry:varchar,plan_expires_at:datetime,status:varchar!,db_name:varchar,db_host:varchar,db_port:integer,onboarding_step:integer!,owner_id:integer,contact_email:varchar,contact_phone:varchar,logo_url:varchar,primary_color:varchar,deleted_at:datetime`

**`user_consents`** — `id:integer!,user_id:integer!,tenant_id:varchar!,type:varchar!,granted:tinyint!,version:varchar!,ip_address:varchar,user_agent:text,consented_at:datetime!,withdrawn_at:datetime`

**`users`** — `id:integer!,name:varchar!,first_name:varchar,last_name:varchar,email:varchar!,phone:varchar,avatar:varchar,email_verified_at:datetime,password:varchar!,locale:varchar!,timezone:varchar!,is_active:tinyint!,google2fa_secret:varchar,last_login_at:datetime,last_login_ip:varchar,remember_token:varchar,created_at:datetime,updated_at:datetime,deleted_at:datetime,cookie_consent:tinyint!,marketing_consent:tinyint!,cookie_consent_at:datetime,marketing_consent_at:datetime,tenant_id:integer,address:text,pii_encrypted:text,social_security:varchar,tier:varchar,mfa_method:varchar,department_id:integer,two_factor_enabled:tinyint!,two_factor_confirmed_at:datetime,two_factor_recovery_codes:text,failed_login_attempts:integer!,locked_until:datetime,company_id:integer,role:varchar,mfa_secret:text,mfa_verified:tinyint!,mfa_backup_codes:text`

Noter, sur `users`, la présence simultanée de `tenant_id`/`role` (colonnes fantômes, jamais dans `$fillable`, jamais peuplées par un vrai chemin d'inscription — documenté à de multiples reprises dans `CLAUDE.md`) et de `company_id`/`département_id` (les vraies colonnes lues par le code corrigé cette session) — les deux paires coexistent dans le schéma migré, seule la seconde est réellement fiable.

## Inventaire détaillé par module

Le détail table-par-table (nom, origine réelle, colonnes/FK clés, statut stub/patché/réel — et, depuis le 2026-08-21, le détail colonne-par-colonne succinct extrait en direct du schéma live) est réparti sur 6 fichiers, regroupés comme dans `CLAUDE.md` :

- [`INVENTAIRE-01-SOCLE.md`](INVENTAIRE-01-SOCLE.md) — Core, AI, Security, AuditLog, API, Integration, Validation, Shared, Settings, Setup, Workflow, Calendar (12 modules)
- [`INVENTAIRE-02-FINANCE-COMMERCIAL.md`](INVENTAIRE-02-FINANCE-COMMERCIAL.md) — Accounting, CRM, Sales
- [`INVENTAIRE-03-STOCK-LOGISTIQUE.md`](INVENTAIRE-03-STOCK-LOGISTIQUE.md) — Inventory, Logistics, Achats
- [`INVENTAIRE-04-PILOTAGE.md`](INVENTAIRE-04-PILOTAGE.md) — BI, Analytics, Reporting, Strategy
- [`INVENTAIRE-05-RH-SUPPORT.md`](INVENTAIRE-05-RH-SUPPORT.md) — HR, Payroll, Timesheets, Projects, Helpdesk
- [`INVENTAIRE-06-MESSAGING.md`](INVENTAIRE-06-MESSAGING.md) — Messaging (hors périmètre initial des 27 modules, ajouté pour de vrai au Chantier 20 — voir `CLAUDE.md` § Scope)

Et les dépendances inter-modules niveau données dans [`DEPENDANCES-MODULES.md`](DEPENDANCES-MODULES.md).

## Environnement de test

Les tests tournent contre SQLite en mémoire (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` dans `phpunit.xml`) — rapide, mais certaines contraintes MySQL-spécifiques (longueur de clé, `ON UPDATE CASCADE` complexes) ne sont validées qu'en CI, où `ci.yml` utilise un vrai service MySQL 8.4. C'est pourquoi le job `php-tests` de `ci.yml` exécute `php artisan migrate` contre MySQL avant `vendor/bin/pest`, même si les tests eux-mêmes utilisent SQLite localement.

## Rafraîchir le schéma localement

```bash
php artisan migrate:fresh --seed
```

Doit s'exécuter sans erreur avant tout merge (cf. `docs/08-TESTS/STRATEGIE-TESTS.md`). Avec 599 tables (voir les chiffres clés en tête de ce document) et plusieurs centaines de fichiers de migration exécutés dans l'ordre chronologique de leur nom de fichier, une erreur de fraîcheur du schéma se manifeste généralement par une contrainte de clé étrangère pointant vers une table pas encore créée à ce stade — vérifiez la date du fichier fautif contre celle de la table qu'il référence avant de supposer un bug applicatif.
