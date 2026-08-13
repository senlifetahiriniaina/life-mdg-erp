# Matrice RBAC

Source de vérité : `database/seeders/RolesAndPermissionsSeeder.php`. 22 rôles, permissions au format `{module}.{ressource}.{action}` avec `action ∈ {view-any, view, create, update, delete}`.

## Hiérarchie des rôles (du plus au moins privilégié)

| Rôle | Portée |
|---|---|
| `super-admin` | Contourne toutes les vérifications de Gate (`Gate::before`) |
| `admin` | Accès complet à tous les modules |
| `manager` | Créer/modifier/supprimer dans les modules assignés |
| `employee` | Lecture + création de ses propres enregistrements |
| `accountant` | Accès complet au module Accounting uniquement |
| `hr-manager` | Accès complet au module HR uniquement |
| `sales-rep` | Accès complet au module CRM uniquement |

### Rôles d'administration

| Rôle | Portée |
|---|---|
| `system-admin` | Infrastructure et gestion serveur |
| `security-admin` | Audit, politique 2FA, sessions |
| `billing-admin` | Facturation et abonnements |
| `support-admin` | Gestion du Helpdesk |
| `tenant-admin` | Activation de modules, gestion utilisateurs/rôles |

### Rôles opérationnels

| Rôle | Portée |
|---|---|
| `logistics-manager` | Inventory + Logistics complet |
| `service-partner` | Prestataire externe : Helpdesk, Projects |
| `purchasing-manager` | Achats : Inventory + Achats + gestion commandes fournisseur |
| `warehouse-operator` | Entrepôt physique : produits, mouvements de stock |
| `sales-manager` | CRM complet + tableaux de bord et rapports BI |
| `project-manager` | Projects complet + vue employés HR |
| `finance-manager` | Accounting complet + BI |
| `customer-service` | Helpdesk complet + vue contacts/comptes CRM |
| `inventory-analyst` | Inventory en lecture seule + analytics BI |
| `payroll-officer` | Payroll complet + compensation/congés HR |

## Modules couverts par le système de permissions granulaires

Format `{module}.{ressource}.{action}` — les modules et leurs ressources gérées :

| Module | Ressources |
|---|---|
| `crm` | contact, lead, opportunity, account, activity, pipeline |
| `sales` | order, line, quotation |
| `hr` | employee, department, job-position, leave, leave-type |
| `payroll` | payslip, run, tax-config |
| `timesheets` | timesheet, entry |
| `projects` | project, task |
| `inventory` | product, category, warehouse, unit, stock-movement, purchase-order, supplier |
| `logistics` | shipment, route, carrier, customs-declaration |
| `achats` | rfq, purchase-order, purchase-receipt, supplier |
| `accounting` | invoice, journal, chart-of-account |
| `helpdesk` | ticket, team |
| `bi` | dashboard, kpi, report |
| `analytics` | forecast, anomaly |
| `reporting` | report, template, schedule |
| `strategy` | ratio, objective, plan |
| `auditlog` | logs |
| `setup` | import, mapping, wizard |
| `integration` | connector, webhook, sync-log |
| `settings` | setting, group |

## Permissions d'administration dédiées

Préfixe `admin.*`, non liées à un module métier : `admin.servers.{view,manage}`, `admin.backups.{view,create,restore}`, `admin.users.{view,create,update,delete}`, `admin.roles.{view,assign}`, `admin.modules.{view,toggle}`, `admin.audit.view`, `admin.security.manage`, `admin.billing.{view,manage}`.

## Nettoyage effectué lors de l'extraction

Les rôles liés exclusivement à des modules hors périmètre (POS, Manufacturing, Ecommerce, Documents, Email, WhatsApp) ont été **retirés** plutôt que laissés orphelins avec des permissions pointant vers des modules inexistants — évite les rôles "fantômes" qui referenceraient des ressources jamais créées dans ce dépôt.

## Vérification

Après tout ajout de module ou de rôle, `php artisan migrate:fresh --seed` doit s'exécuter sans erreur et aucun rôle ne doit se retrouver sans permission associée.
