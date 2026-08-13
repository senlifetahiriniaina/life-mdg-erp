# Sales

## Rôle

Le module Sales gère le cycle de vente transactionnel de Life MDG ERP : devis (`SalesQuotation`) et commandes clients (`SalesOrder`) avec leurs lignes, calcul automatique des totaux (sous-total, remise, taxe), confirmation/annulation de commande, et conversion d'un devis accepté en commande confirmée. C'est un module volontairement compact (33 fichiers PHP) comparé à CRM — dans WideHalo-ERP d'origine il fait partie des « modules stub » consolidés en Phase 11, et cette structure légère a été conservée telle quelle dans life-mdg-erp.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `SalesOrder` | `sales_orders` | Commande client (statut, devise, sous-total/remise/taxe/total, adresse de livraison, dates de confirmation/annulation) ; utilise le trait `HelpdeskLinkable` (peut ouvrir des tickets Helpdesk) et `HasAuditLog` |
| `SalesOrderLine` | `sales_order_lines` | Ligne de commande (produit, quantité, prix unitaire, remise %, taux de taxe, total de ligne calculé par `computeTotal()`) |
| `SalesQuotation` | `sales_quotations` | Devis (référence, statut, devise, total, date de validité, lien vers la commande convertie `converted_to_order_id`) |

## Endpoints principaux

Routes API sous `auth:sanctum`, middleware `module:Sales`, préfixe `v1/sales` (`Modules/Sales/routes/api.php`).

| Méthode | Route | Description |
|---|---|---|
| GET | `sales/orders` | Liste des commandes |
| POST | `sales/orders` | Créer une commande (avec ses lignes) |
| GET | `sales/orders/{id}` | Détail d'une commande |
| PUT | `sales/orders/{id}` | Modifier une commande |
| PUT | `sales/orders/{id}/status` | Changer le statut d'une commande |
| POST | `sales/orders/{id}/confirm` | Confirmer une commande (`draft` → `confirmed`) |
| POST | `sales/orders/{id}/cancel` | Annuler une commande (avec motif) |
| GET | `sales/quotations` | Liste des devis |
| POST | `sales/quotations` | Créer un devis |
| GET | `sales/quotations/{id}` | Détail d'un devis |
| PUT | `sales/quotations/{id}` | Modifier un devis |
| POST | `sales/quotations/{id}/send` | Envoyer un devis |
| POST | `sales/quotations/{id}/convert` | Convertir un devis accepté en commande confirmée |
| POST | `v1/sales/ai/assist` | Guidance IA contextuelle (AI Assisted First) |

## Services

- **`SalesService`** — seul service métier du module. Responsabilités : génération de référence unique par année (`SO-{année}-{compteur}`, `QT-{année}-{compteur}`), `createOrder()` (transaction DB, création de la commande + ses lignes, recalcul des totaux), `recalculateOrderTotals()` (calcule sous-total/remise/taxe/total à partir des lignes, avec arrondi à 2 décimales), `confirmOrder()` (n'autorise la transition que depuis le statut `draft`, sinon lève une `RuntimeException`), `cancelOrder()` (vérifie `isCancellable()` avant transition, journalise le motif dans `notes`), `createQuotation()`, `convertQuotationToOrder()` (vérifie `isConvertible()` puis crée la commande dans une transaction et marque le devis `accepted`).

Devise par défaut dans `SalesService` : `XOF` (CFA Franc UEMOA), cohérent avec le positionnement Africa First du projet.

## Permissions RBAC

Sales a une entrée dédiée dans `RolesAndPermissionsSeeder::MODULES` : `'sales' => ['order', 'line', 'quotation']`, générant les permissions `sales.order.*`, `sales.line.*`, `sales.quotation.*` (5 actions × 3 ressources = 15 permissions). `SalesOrderPolicy` s'appuie directement sur ces permissions (`$user->can('sales.order.view-any')`, etc. — pas de vérification de rôle en dur, contrairement à Workflow/Calendar). Le rôle `sales-manager` reçoit l'intégralité de `sales.*` (en plus de `crm.*` et d'un accès BI/Accounting en lecture).

## Dépendances avec d'autres modules

Sales importe (`use Modules\...`) `Modules\AI` (guidance IA) et `Modules\Helpdesk` (trait `HelpdeskLinkable` sur `SalesOrder`). Aucun autre module de life-mdg-erp n'importe `Modules\Sales` dans son code PHP — le lien fonctionnel avec CRM se fait uniquement via les colonnes `contact_id`/`account_id`/`opportunity_id` de `SalesOrder`, sans dépendance de code formelle dans un sens ou dans l'autre.
