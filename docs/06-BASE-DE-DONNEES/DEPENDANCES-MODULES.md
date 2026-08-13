# Dépendances entre modules — niveau données

Ce document complète `docs/02-ARCHITECTURE/CARTE-MODULES.md` (dépendances de service/code) avec l'angle base de données : quelles tables de quels modules référencent des tables d'autres modules par clé étrangère ou par lien polymorphique.

## Liens polymorphiques (Helpdesk)

`hd_tickets` porte un couple `source_type`/`source_id` polymorphique (`morphTo('source')`), qui peut pointer vers un enregistrement de n'importe lequel des modules suivants, via un morph-map explicite enregistré dans `HelpdeskServiceProvider` (jamais un nom de classe brut envoyé par le client) :

- `Accounting\Invoice`
- `CRM\Contact`
- `Inventory\Product`
- `Sales\SalesOrder`
- `Achats\PurchaseOrder`
- `Projects\Project`
- `Logistics\Shipment`
- `HR\Employee`

Voir `docs/03-MODULES/Helpdesk.md` pour le détail du trait `HelpdeskLinkable` qui expose cette relation côté modèle source.

## Clés étrangères directes (échantillon représentatif)

| Table (module) | Référence | Module référencé |
|---|---|---|
| `Modules/Timesheets` (entries) | `project_id` | `Projects` |
| `Modules/Achats` (purchase_order) | lié à `Inventory\Product`/`Supplier` | `Inventory` |
| `Modules/Payroll` (payslip/run) | `employee_id` | `HR` |
| Migrations racine (`consent_logs`, `activity_log`) | `user_id` | racine (`users`) |

## Pourquoi certaines dépendances ont été retirées

Trois relations vers des modules **exclus** du périmètre ont été retirées au niveau code lors de l'extraction (voir `docs/02-ARCHITECTURE/CARTE-MODULES.md` pour le détail) : `CRM→Email`, `HR→Planning`, `Projects→Notes`. Les colonnes/tables correspondantes de ces modules exclus n'ont pas été portées dans `database/migrations/` — toute tentative de relation Eloquent vers une classe de ces modules lèverait une erreur de classe introuvable, ce qui est le comportement voulu (signal fort en cas de code résiduel non nettoyé) plutôt qu'un échec silencieux en base.

## Vérification de cohérence

`php artisan migrate:fresh --seed` est le test de référence : toute clé étrangère pointant vers une table d'un module non inclus dans le périmètre ferait échouer cette commande immédiatement, plutôt que de laisser une contrainte orpheline non détectée.
