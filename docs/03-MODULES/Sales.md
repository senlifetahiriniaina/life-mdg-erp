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

## Contrôleurs

Le module reste volontairement compact : 2 contrôleurs Api seulement (`Modules/Sales/app/Http/Controllers/Api/`) — `SalesController` (tout le CRUD commandes/devis) et `SalesAiAssistController` (guidance IA). Aucun contrôleur Web dédié : la route `/sales` est une simple closure `Inertia::render()` (voir Vues).

## Vues (Vue/Inertia)

`Modules/Sales/resources/js/Pages/` contient `SalesIndex.vue` (page auto-suffisante qui appelle directement `GET /api/v1/sales/orders`), plus `CPQ/Index.vue` et `Subscriptions/Index.vue` — ces deux dernières sont **entièrement des maquettes statiques** (aucun `defineProps`, aucun appel `axios`/`fetch`), sans backend réel derrière, et volontairement laissées non routées (même traitement que les pages mock équivalentes de Logistics/Achats — `AIRiskMonitor.vue`/`SupplierRisk/Index.vue`).

`SalesIndex.vue` était jusqu'au Chantier 8.5-light totalement inaccessible : `routes/web.php` était un placeholder vide. Une route `GET /sales` a été ajoutée (`Route::middleware(['web','auth','module:Sales'])`), suivant le même schéma « page auto-suffisante → closure Inertia::render() » déjà utilisé pour `Inventory/Stock/Movements` ou `Achats/SpendAnalytics/Index`.

## Services

- **`SalesService`** — seul service métier du module. Responsabilités : génération de référence unique par année (`SO-{année}-{compteur}`, `QT-{année}-{compteur}`), `createOrder()` (transaction DB, création de la commande + ses lignes, recalcul des totaux), `recalculateOrderTotals()` (calcule sous-total/remise/taxe/total à partir des lignes, avec arrondi à 2 décimales), `confirmOrder()` (n'autorise la transition que depuis le statut `draft`, sinon lève une `RuntimeException`), `cancelOrder()` (vérifie `isCancellable()` avant transition, journalise le motif dans `notes`), `createQuotation()`, `convertQuotationToOrder()` (vérifie `isConvertible()` puis crée la commande dans une transaction et marque le devis `accepted`).

Devise par défaut dans `SalesService` : `XOF` (CFA Franc UEMOA), cohérent avec le positionnement Africa First du projet.

## Permissions RBAC

Sales a une entrée dédiée dans `RolesAndPermissionsSeeder::MODULES` : `'sales' => ['order', 'line', 'quotation']`, générant les permissions `sales.order.*`, `sales.line.*`, `sales.quotation.*` (5 actions × 3 ressources = 15 permissions). `SalesOrderPolicy` s'appuie directement sur ces permissions. Le rôle `sales-manager` reçoit l'intégralité de `sales.*` (en plus de `crm.*` et d'un accès BI/Accounting en lecture). Route-level : `module:Sales` sur tout le groupe `v1` (`Modules/Sales/routes/api.php`, vérifié dans le code).

**Chantier 8.5-light** a ajouté l'autorisation manquante sur `confirmOrder()`/`cancelOrder()` (aucune auparavant, contrairement à leurs méthodes sœurs) et un contrôle d'appartenance au tenant sur les 4 méthodes de lookup (`showOrder`/`updateOrder`/`confirmOrder`/`cancelOrder`) — un IDOR réel une fois la fuite multi-tenant ci-dessous corrigée, jusque-là masqué par le fait que toutes les commandes finissaient dans le même compartiment partagé.

## Dépendances avec d'autres modules

Sales importe (`use Modules\...`) `Modules\AI` (guidance IA) et `Modules\Helpdesk` (trait `HelpdeskLinkable` sur `SalesOrder`). Aucun autre module de life-mdg-erp n'importe `Modules\Sales` dans son code PHP — le lien fonctionnel avec CRM se fait uniquement via les colonnes `contact_id`/`account_id`/`opportunity_id` de `SalesOrder`, sans dépendance de code formelle dans un sens ou dans l'autre.

## Particularités du périmètre life-mdg-erp

**Fuite cross-tenant corrigée (Chantier 8.5-light)** : `SalesController::indexOrders()`/`storeOrder()` (et les équivalents devis) résolvaient le tenant via `$user->tenant_id ?? 1` — `users.tenant_id` est une colonne fantôme, jamais peuplée par le vrai flux d'inscription — donc toutes les commandes/devis de toutes les sociétés atterrissaient silencieusement dans le même compartiment partagé « tenant 1 ». Corrigé pour utiliser `$user->company_id ?? 0`, la vraie colonne de frontière multi-tenant (confirmé dans le code : `tenantId()` porte un commentaire explicite documentant ce fix et retourne bien `(int) ($request->user()?->company_id ?? 0)`).
