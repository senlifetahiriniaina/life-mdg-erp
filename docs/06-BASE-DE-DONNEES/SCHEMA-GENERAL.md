# Schéma de base de données — vue générale

Cette section a été reconstruite pour Chantier 8.7 à partir d'une lecture réelle de **toutes** les migrations du dépôt (`database/migrations/` + les 27 `Modules/*/database/migrations/`), pas depuis la mémoire ni depuis les docs `docs/03-MODULES/*.md` (qui restent la référence pour les modèles Eloquent, relations et logique métier — ce document ne couvre que le schéma physique). Méthode : `grep` de tous les `Schema::create(...)` (littéraux **et** en boucle sur un tableau de noms — voir plus bas), puis lecture des fermetures de colonnes pour un échantillon représentatif par module, avec recoupement contre `docs/03-MODULES/<Module>.md`.

**Chiffres clés** (voir `docs/06-BASE-DE-DONNEES/INVENTAIRE-0{1..5}-*.md` pour le détail table par table) :

| Catégorie | Nombre de tables |
|---|---|
| Tables des 27 modules du périmètre Life MDG | **576** |
| — dont créées avec un schéma métier réel dès l'origine | 369 |
| — dont créées comme stub générique (voir plus bas), une partie patchée depuis | 207 (95 patchées, 112 encore un stub pur) |
| Tables racine partagées (framework Laravel, RBAC, RGPD, cache, webhooks…) | 32 |
| Tables « hors périmètre » — scaffolding mort pour des modules **exclus** (Manufacturing, POS, Ecommerce, Email, WhatsApp, Documents, Discussion, Mobile/MobileSync, Planning, Quality, MarketingAutomation) | 184 |
| Tables d'un moteur d'approbation/workflow racine mort (jamais routé) | 11 |
| **Total tables du schéma** | **≈ 803** |
| *(hors total)* Modèles réels déclarant une table absente de toute migration, y compris stub | 10 |

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

## Tables « hors périmètre » : le scaffolding mort des modules exclus

Point non documenté ailleurs jusqu'ici : le même fichier catch-all (`2026_05_29_000003_create_all_missing_module_tables.php`) scaffold aussi, avec le même mécanisme en boucle, des tables pour des modules **explicitement exclus** du périmètre des 27 modules Life MDG (voir `CLAUDE.md` § Scope). Ces **184 tables** existent réellement dans le schéma migré, sans qu'aucun code applicatif du dépôt actuel (aucun `Modules/Manufacturing`, `Modules/POS`, etc. n'existe sur le disque) ne les lise ou écrive jamais. C'est un résidu inerte de l'extraction depuis WideHalo-ERP (49 modules) — inoffensif (`php artisan migrate:fresh --seed` fonctionne, rien n'y écrit), mais qui peut surprendre quiconque explore le schéma en base directement plutôt que le code :

| Module exclu | Tables (nombre) | Préfixe dominant |
|---|---|---|
| Ecommerce | 45 | `ec_` (13, migration racine dédiée) + `ecommerce_`/`ecom_` (32, stub en boucle) |
| Manufacturing | 33 | `mfg_` (31, dont 2 en création littérale) + `bill_of_materials`/`bom_lines` (2, stub générique) |
| POS | 22 | `pos_` (20 stub + 2 création littérale `pos_configs`/`pos_sessions`) |
| Documents | 18 | `doc_`/`document_` (16 stub) + `documents`/`doc_folders` (2, littéral) |
| WhatsApp | 17 | `wa_`/`whatsapp_` (14 stub + 3 littéral) |
| Email | 16 | `email_` (13 stub + 3 littéral) |
| Quality | 9 | générique (stub) |
| Planning | 6 | `planning_` (stub) |
| MarketingAutomation | 5 | générique (stub) — `campaign_executions`, `segments`, `lead_activities`, `contact_consent_logs`, `contact_scores` |
| Mobile/MobileSync | 2 | `push_tokens`, `sync_queue` |
| Discussion | 1 | `channel_members` |
| Stubs génériques sans module (collision de nom) | 10 | `products`, `customers`, `suppliers`, `purchase_orders`, `purchase_order_lines`, `product_changes`, `product_specifications`, `product_variants`, `product_versions`, `product_webhooks` |

(Détail nommé complet en relançant `grep -n "Schema::create" database/migrations/2026_05_29_000003_create_all_missing_module_tables.php` et en lisant les blocs `// ── DOCUMENTS`, `// ── ECOMMERCE`, `// ── EMAIL`, `// ── MANUFACTURING`, `// ── MARKETING / CAMPAIGNS`, `// ── PLANNING`, `// ── POS`, `// ── QUALITY`, `// ── WHATSAPP` de ce même fichier, plus `2026_05_01_000004_create_inventory_ecommerce_crm_tables.php` et `2026_05_01_000006_create_crm_pos_documents_tables.php` pour les tables `ec_*`/`wa_*`/`mfg_bill_of_materials`/`mfg_bom_items` créées littéralement.)

Deux tables stub méritent une mention à part : `products` et `customers`, créées par le même catch-all avec le schéma générique, **sans aucun modèle Eloquent nulle part dans le dépôt**. Elles ne doivent pas être confondues avec les vraies tables produit/client des modules du périmètre — `inventory_products` (Inventory), `crm_contacts`/`crm_accounts` (CRM). C'est un piège de nommage pur, pas un gap fonctionnel : rien ne les lit.

## Un troisième moteur d'approbation/workflow, mort, à la racine de `app/`

En creusant les tables non préfixées `workflows`, `approvals`, `approval_chain(s)`, `approval_requests`, `approval_overrides`, `workflow_conditions`, `workflow_executions`, `workflow_step_logs`, `workflow_triggers`, `workflow_audit_logs` (11 tables), on trouve un **troisième** moteur de workflow/approbation dans ce dépôt — distinct des deux déjà documentés ailleurs :

1. `Modules\Workflow` (préfixes `wfd_`/`automation_`, moteur n8n-like Phase 39 + DSL/connecteurs Phase 52, chantier 8.5-light a supprimé son bloc de routes legacy mort).
2. `Modules\Validation` (préfixe `validation_`, moteur d'approbation générique multi-niveaux, chantier 8.5sv l'a audité et corrigé).
3. **`App\Models\Workflow` / `Approval` / `ApprovalChain` / `WorkflowExecution` / `WorkflowAuditLog` / `WorkflowStepLog`, à la racine de `app/Models/`, consommés uniquement par `App\Services\WorkflowEngine`** — un service réel, non trivial, mais **confirmé non appelé par aucune route ni aucun contrôleur du dépôt** (recherche exhaustive). Ses 11 tables existent en base (schéma réel avec FK, pas des stubs) mais ce sous-système entier est mort à l'exécution. Ni documenté ni supprimé par les chantiers 8.x précédents — signalé ici pour la première fois comme un gap à trancher (build réel derrière une route, ou suppression) plutôt que laissé silencieusement.

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

## Tables partagées clés (racine, 32 au total)

| Table | Rôle |
|---|---|
| `users` | Comptes utilisateurs — porte aussi les colonnes fantômes `tenant_id`/`role`, jamais dans `$fillable`, documentées à de multiples reprises dans `CLAUDE.md` comme source de fuites cross-tenant corrigées (la vraie frontière de tenant est `company_id`) |
| `roles` / `permissions` / `model_has_roles` / `model_has_permissions` / `role_has_permissions` | RBAC (spatie/laravel-permission), créées par `2026_05_02_073520_create_permission_tables.php` (le seul autre fichier du dépôt à utiliser la même technique de boucle que le catch-all module) |
| `personal_access_tokens` | Tokens Sanctum |
| `sessions` / `password_reset_tokens` / `password_histories` | Authentification |
| `notifications` | File de notifications Laravel standard |
| `cache` / `cache_locks` / `jobs` / `job_batches` / `failed_jobs` | Framework Laravel (cache DB driver, queue) |
| `cache_performance_logs` / `cache_strategies` / `cached_query_patterns` | Infrastructure de cache applicative additionnelle (`2026_05_17_180000_add_caching_infrastructure.php`) |
| `consent_logs` / `user_consents` / `gdpr_requests` / `gdpr_exports` | Conformité RGPD/PDPL au niveau racine (distinctes de `core_gdpr_consents`/`core_data_requests`, propriété du module Core — deux implémentations RGPD coexistent, voir `docs/03-MODULES/Core.md` et `docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md`) |
| `webhooks` / `webhook_deliveries` / `webhook_audit_logs` | Webhooks sortants génériques, signés HMAC |
| `export_audit_logs` / `audit_log_access_logs` | Télémétrie d'export et d'accès à l'audit |
| `analytics_events` | Télémétrie d'événements produit générique |
| `mv_*` (8 tables) | Vues matérialisées cross-module pour analytics (`mv_crm_pipeline_summary`, `mv_hr_metrics_summary`, `mv_inventory_stock_summary`, `mv_sales_daily_summary`, `mv_project_health_summary`, `mv_document_analytics`, `mv_email_campaign_analytics`, `mv_manufacturing_daily_kpis` — la dernière porte sur un module exclu) |

## Inventaire détaillé par module

Le détail table-par-table (nom, origine réelle, colonnes/FK clés, statut stub/patché/réel) est réparti sur 5 fichiers, regroupés comme dans `CLAUDE.md` :

- [`INVENTAIRE-01-SOCLE.md`](INVENTAIRE-01-SOCLE.md) — Core, AI, Security, AuditLog, API, Integration, Validation, Shared, Settings, Setup, Workflow, Calendar (12 modules)
- [`INVENTAIRE-02-FINANCE-COMMERCIAL.md`](INVENTAIRE-02-FINANCE-COMMERCIAL.md) — Accounting, CRM, Sales
- [`INVENTAIRE-03-STOCK-LOGISTIQUE.md`](INVENTAIRE-03-STOCK-LOGISTIQUE.md) — Inventory, Logistics, Achats
- [`INVENTAIRE-04-PILOTAGE.md`](INVENTAIRE-04-PILOTAGE.md) — BI, Analytics, Reporting, Strategy
- [`INVENTAIRE-05-RH-SUPPORT.md`](INVENTAIRE-05-RH-SUPPORT.md) — HR, Payroll, Timesheets, Projects, Helpdesk

Et les dépendances inter-modules niveau données dans [`DEPENDANCES-MODULES.md`](DEPENDANCES-MODULES.md).

## Environnement de test

Les tests tournent contre SQLite en mémoire (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` dans `phpunit.xml`) — rapide, mais certaines contraintes MySQL-spécifiques (longueur de clé, `ON UPDATE CASCADE` complexes) ne sont validées qu'en CI, où `ci.yml` utilise un vrai service MySQL 8.4. C'est pourquoi le job `php-tests` de `ci.yml` exécute `php artisan migrate` contre MySQL avant `vendor/bin/pest`, même si les tests eux-mêmes utilisent SQLite localement.

## Rafraîchir le schéma localement

```bash
php artisan migrate:fresh --seed
```

Doit s'exécuter sans erreur avant tout merge (cf. `docs/08-TESTS/STRATEGIE-TESTS.md`). Avec ~803 tables et plusieurs centaines de fichiers de migration exécutés dans l'ordre chronologique de leur nom de fichier, une erreur de fraîcheur du schéma se manifeste généralement par une contrainte de clé étrangère pointant vers une table pas encore créée à ce stade — vérifiez la date du fichier fautif contre celle de la table qu'il référence avant de supposer un bug applicatif.
