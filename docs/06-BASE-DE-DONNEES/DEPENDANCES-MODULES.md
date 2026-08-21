# Dépendances entre modules — niveau données

Ce document complète `docs/02-ARCHITECTURE/CARTE-MODULES.md` (dépendances de service/code) avec l'angle base de données : quelles tables de quels modules référencent des tables d'autres modules par clé étrangère, par lien polymorphique, ou par appel de service qui écrit directement dans le schéma d'un autre module sans passer par une relation Eloquent. Voir `SCHEMA-GENERAL.md` pour la méthode de lecture des migrations et les fichiers `INVENTAIRE-0{1..6}-*.md` pour le détail table par table (le 6ᵉ couvre Messaging, ajouté hors périmètre initial au Chantier 20).

## Liens polymorphiques

### Helpdesk ↔ 8 modules (`HelpdeskLinkable`)

`hd_tickets` porte un couple `source_type`/`source_id` polymorphique (`morphTo('source')`, ajouté par une migration module **après** la création de la table racine — voir `SCHEMA-GENERAL.md`), qui peut pointer vers un enregistrement de n'importe lequel des modules suivants, via un morph-map explicite enregistré dans `HelpdeskServiceProvider` (jamais un nom de classe brut envoyé par le client) :

- `Accounting\Invoice` (`acc_invoices`)
- `CRM\Contact` (`crm_contacts`)
- `Inventory\Product` (`inventory_products`)
- `Sales\SalesOrder` (`sales_orders`)
- `Achats\PurchaseOrder` (`achats_purchase_orders`)
- `Projects\Project` (`prj_projects`)
- `Logistics\Shipment` (`logistics_shipments`)
- `HR\Employee` (`hr_employees`)

Voir `docs/03-MODULES/Helpdesk.md` pour le détail du trait `HelpdeskLinkable` qui expose cette relation côté modèle source. Pour ajouter un 9ᵉ module linkable : ajouter le trait au modèle + enregistrer son alias morph-map, aucune migration de `hd_tickets` n'est nécessaire (la colonne `source_id` est un `unsignedBigInteger` générique).

### Calendar ↔ tous les modules (`module_type`/`module_id`)

`calendar_events` porte un couple polymorphique `module_type`/`module_id` (documenté dans `docs/03-MODULES/Calendar.md`) permettant de rattacher un évènement à un enregistrement de n'importe quel module — c'est le mécanisme derrière `ModuleEventAggregatorService` (congés HR, tâches Projects, SLA Helpdesk, jalons Strategy, échéances Accounting, Timesheets). Contrairement à `HelpdeskLinkable`, il n'existe pas de morph-map allowlist explicite documentée pour ce couple à ce jour — à vérifier avant d'exposer la création d'évènements liés depuis une entrée utilisateur non fiable.

### Strategy ↔ tous les modules (`StrategyObjectiveLink`)

`strategy_objective_links` relie un `StrategyObjective` à une ressource de n'importe quel autre module (alignement Cascade). Endpoint câblé pour la première fois au Chantier 8.5ars (`StrategyObjectiveLinkController`) après que l'absence d'enregistrement Gate ait rendu la fonctionnalité inaccessible (403 systématique).

## Dépendances de service — écriture directe hors relation Eloquent

Ces dépendances ne se voient **pas** dans le schéma des migrations (pas de FK déclarée) mais sont réelles au niveau code — un service d'un module lit ou écrit directement dans les tables d'un autre.

| Service | Module propriétaire | Écrit/lit dans | Nature |
|---|---|---|---|
| `PayrollIntegrationService::getCurrentCompensation()` | Payroll | `hr_employee_compensation` (HR) | Lecture directe — la vraie source de salaire pour le calcul de bulletin, après correction du Chantier 8.3 (`Employee::base_salary` n'était jamais réellement peuplé, voir `INVENTAIRE-05-RH-SUPPORT.md`) |
| `PayrollIntegrationService::calculateOvertime()` | Payroll | `timesheet_entries` (Timesheets, colonne `hours_worked`, entrées approuvées) | Lecture directe — remplace au Chantier 8.3 partie 3 la lecture d'une table `hr_timesheets` morte |
| `Modules\Analytics\Services\Forecasting\HrForecastService` | Analytics | `hr_leave_balances` (HR) | Lecture directe — table **orpheline côté écriture** depuis la suppression d'`AbsenceManagementService` au Chantier 8.3 partie 8 ; rien ne peuple plus `hr_leave_balances`, gap documenté dans `CLAUDE.md` mais non corrigé (hors scope de ce chantier HR) |
| `ImportDataJob`/`AiDataImportService`/`ImportExecutorService` | Setup | table cible résolue dynamiquement par `entityToTable()` (ex. `'invoices' => 'acc_invoices'`, `'contacts' => 'crm_contacts'`, `'stock_movements' => 'inventory_stock_movements'`) | Écriture par catalogue de schéma statique, pas par relation Eloquent — `bulkInsert()` filtre chaque ligne aux colonnes réellement existantes de la table cible (`Schema::getColumnListing()`) plutôt que de supposer un jeu de colonnes commun (`tenant_id`/`created_at`/`updated_at` n'existent pas sur toutes les tables cibles) |
| `CostEngineService::calculateBomCosts()` | Accounting | tables `cost_*` (Accounting lui-même) à partir d'un BOM/produit/projet/client d'un autre module | Le module source (BOM, produit) est identifié par type+ID générique, pas par FK typée |
| `AiAnomalyDetectionService`/`AiNaturalLanguageSearchService` | AI | tables réelles `<module>_`-préfixées de tout le périmètre (`acc_invoices`, `crm_contacts`, `inventory_stock_movements`, `acc_journal_entries`…) | Lecture directe par nom de table brut — repointée au Chantier 8.5-light depuis des noms de table inventés (`products`, `invoices`, `journal_entries` sans préfixe) vers les vraies tables `<module>_`-préfixées. Piège identifié en le corrigeant : `crm_contacts.company_id` est une FK vers une **entreprise cliente CRM**, pas la frontière de tenant de l'app — le filtre de tenant NL-search ne fait confiance qu'à une vraie colonne `tenant_id` (`Schema::hasColumn()` guard), jamais à `company_id`, pour éviter cette collision de nom |
| `KPIRegistryService` | Strategy | KPI pull callbacks vers Accounting/CRM/Inventory/Sales/Helpdesk/HR (basique) | Chaque ratio interroge son module source directement, avec fallback try/catch systématique si la source de donnée est absente ou fine (ex. `training_roi`/`time_to_fill` HR — valeurs statiques 145.0/28 jours) |
| `ModuleEventAggregatorService` | Calendar | HR (congés), Projects (tâches), Helpdesk (SLA), Strategy (jalons), Manufacturing (hors périmètre — ordres de fabrication), Accounting (échéances), Timesheets | Agrégation en lecture seule vers `calendar_events` |

## Clés étrangères directes confirmées (échantillon vérifié par lecture de migration)

| Table (module) | Colonne | Référence | Module référencé |
|---|---|---|---|
| `hr_employees` | `user_id` | `users.id` | racine |
| `hr_employees` | `department_id` | `hr_departments.id` | HR (même module) |
| `hr_employees` | `job_position_id` | `hr_job_positions.id` | HR (même module) |
| `hr_employees` | `manager_id` | `hr_employees.id` | HR (auto-référence — hiérarchie manager/subordonnés) |
| `acc_invoices` | `journal_id`, `partner_id`, `customer_id`, `created_by` | non typées FK en base (`unsignedBigInteger` nu, pas de `foreignId()->constrained()`) | Accounting (interne) / racine (`created_by` → `users`) |
| `hd_tickets` | `user_id`, `assigned_to` | `users.id` (`nullOnDelete()`) | racine |
| `prj_projects` | `owner_id` | `users.id` | racine |
| `sales_orders` | — (voir modèle `SalesOrder`) | `HelpdeskLinkable` + `HasAuditLog`, pas de FK produit typée au niveau migration | — |
| `achats_purchase_orders` / `achats_rfqs` / `achats_supplier_quotes` | `supplier_id` | `achats_suppliers.id` (non contraint en base, `unsignedBigInteger` nu) | Achats (interne) |
| `timesheet_entries` (patché) | `employee_id`, `project_id`, `task_id` | `hr_employees.id` / `prj_projects.id` / `prj_tasks.id` | HR / Projects — **point d'attention** : `StoreTimesheetEntryRequest` validait autrefois `employee_id` contre `exists:users,id` (mauvais espace d'ID) et `task_id` contre `exists:tasks,id` (table inexistante, la vraie est `prj_tasks`) — corrigé au Chantier 8.4 |

**Note méthodologique** : la grande majorité des FK inter-tables de ce dépôt sont des colonnes `unsignedBigInteger` **nues**, sans contrainte `->constrained()`/`->references()->on()` déclarée en migration — cohérent avec le style `Schema::hasTable()`-guardé du catch-all et des migrations racine massives. Cela signifie que `php artisan migrate:fresh --seed` ne détecte **pas** systématiquement une FK orpheline pointant vers un module absent du périmètre par une erreur de contrainte SQL — contrairement à ce qu'affirmait la version précédente de ce document. La vérification de cohérence réelle passe par la relation Eloquent (`belongsTo`) et par les tests d'intégration, pas par la seule exécution de la migration.

## Nouvelle dépendance cross-module — Messaging + `ParticipantNotificationService` (Chantier 20)

`Modules\Core\Services\ParticipantNotificationService::notifyProcess()` résout le supérieur hiérarchique direct d'un `owner` via `$owner->employee?->manager?->user` — une dépendance en lecture de **tout module appelant** vers le schéma HR (`hr_employees.manager_id`, auto-référence) sans FK déclarée, le même style de dépendance de service que celles déjà listées dans le tableau ci-dessus. Câblée pour de vrai (Chantier 20) dans 4 modules : `Modules\Validation` (`NotifyApprovalParticipants` listener), `Modules\Accounting` (`InvoiceApprovalService::notifyApprovers()`), `Modules\HR` (`LeaveRequestObserver`), `Modules\Helpdesk` (`Ticket`/`TicketComment` observers) — un premier lot documenté comme tel dans `CLAUDE.md`, pas une couverture totale. `Modules\Messaging` (`msg_conversations`/`msg_conversation_participants`/`msg_messages`, voir `INVENTAIRE-06-MESSAGING.md`) n'a, lui, aucune dépendance vers un autre module métier — seulement `company_id`/`created_by`/`sender_id` vers `companies`/`users` (racine), un module volontairement autonome.

## Pourquoi certaines dépendances ont été retirées

Trois relations vers des modules **exclus** du périmètre ont été retirées au niveau code lors de l'extraction (voir `docs/02-ARCHITECTURE/CARTE-MODULES.md` pour le détail) : `CRM→Email`, `HR→Planning`, `Projects→Notes`. **Point historique, largement obsolète depuis Chantier 9** : la version précédente de ce document notait que les colonnes/tables des modules exclus n'avaient pas systématiquement disparu du schéma migré (~184 tables « hors périmètre » scaffoldées par le catch-all racine) — Chantier 9 (voir `CLAUDE.md`) a depuis supprimé la quasi-totalité de ce résidu ; voir `SCHEMA-GENERAL.md` § Tables « hors périmètre » pour l'état réel au 2026-08-21 (quelques tables individuellement conservées avec un vrai consommateur, le reste supprimé). Toute tentative de relation Eloquent vers une classe de ces modules exclus lèverait toujours une erreur de classe introuvable (le comportement voulu, signal fort en cas de code résiduel non nettoyé) — mais désormais la table elle-même, dans l'immense majorité des cas, n'existe plus non plus.

## Vérification de cohérence

`php artisan migrate:fresh --seed` reste le test de référence pour la fraîcheur du schéma, mais — voir la note méthodologique ci-dessus — il ne garantit pas l'absence de FK logiquement orphelines puisque peu de contraintes SQL sont réellement déclarées. Pour auditer une dépendance inter-module suspecte, privilégier :

1. `grep` du nom de la table cible dans les `$fillable`/relations `belongsTo()`/`hasMany()` des modèles Eloquent des deux modules concernés.
2. Les tests `Modules/*/tests/Feature/` — plusieurs chantiers 8.x (voir `CLAUDE.md`) ont ajouté des tests de régression nommés `Chantier8*Test.php` qui verrouillent précisément ce type de dépendance inter-module une fois corrigée.
