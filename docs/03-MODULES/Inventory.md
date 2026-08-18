# Inventory

## Rôle

Le module Inventory gère l'intégralité du cycle de vie du stock : catalogue produits, entrepôts multi-sites, mouvements de stock, traçabilité par lot, valorisation (FIFO/AVCO), prévision de la demande, comptage cyclique, cross-docking, picking par vagues, retours (RMA) et synchronisation avec les places de marché. C'est le socle "Stock" de la ligne **Stock et Logistique** aux côtés de Logistics et Achats, et il alimente en données de nombreux autres modules (BI, Analytics, Achats, Helpdesk).

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Product` | (catalogue produits) | Fiche produit, unités, seuils de réapprovisionnement, coût |
| `Warehouse` | `inventory_warehouses` | Entrepôt (distinct du `Warehouse` du module Logistics — voir Particularités) |
| `WarehouseStock` | — | Quantité en stock par produit × entrepôt |
| `Stock` / `StockMovement` | — | Niveaux de stock et journal des mouvements (entrée/sortie/transfert/ajustement) |
| `Lot`, `LotMovement` | — | Traçabilité lot/série, dates de péremption (FEFO) |
| `Category`, `Unit` | — | Taxonomie produit et unités de mesure |
| `Supplier` | `inventory_suppliers` | Fournisseur côté Inventory (distinct du `Supplier` du module Achats) |
| `PurchaseOrder`, `PurchaseOrderItem` | `inventory_purchase_orders` | Bon de commande "léger" propre à Inventory (voir Particularités) |
| `Shipment`, `ShipmentEvent` | `inventory_shipments` | Expédition sortante et évènements de suivi |
| `CycleCount`, `CycleCountLine` | — | Comptage cyclique et écarts |
| `CrossdockOperation` | — | Transfert direct réception → expédition |
| `PickingOrder`, `PickingLine`, `PickingWave`, `PickLine` | — | Préparation de commande, y compris picking par vagues |
| `TransferOrder`, `TransferOrderLine` | — | Transfert inter-entrepôts |
| `RedistributionRule` | — | Règles de rééquilibrage automatique du stock entre sites |
| `ReorderRule` | — | Règles de seuil de réapprovisionnement |
| `Rma` | — | Retour marchandise (Return Merchandise Authorization) |
| `DemandForecast`, `SeasonalFactor` | — | Prévision de la demande et facteurs saisonniers (Afrique) |
| `CostLayer`, `ValuationRun` | — | Couches de coût et exécutions de valorisation de stock |
| `MarketplaceChannel` | — | Canal marketplace connecté (Amazon, eBay…) |
| `EdiTransaction` | — | Transactions EDI (850/856/810) |
| `Carrier` | — | Transporteur utilisé pour les expéditions sortantes |

## Endpoints principaux

Tous les endpoints sont sous `auth:sanctum` avec limitation de débit différenciée (`throttle:simple_get`, `complex_get`, `expensive`, `create_post`) et mise en cache pour les listes (`cache.api:5/10/15`).

| Méthode | Route | Description |
|---|---|---|
| GET/POST/PUT/DELETE | `products`, `categories`, `warehouses`, `suppliers`, `channels`, `units` | CRUD ressources catalogue (cache 10 min en lecture) |
| GET | `products/low-stock`, `products/metrics`, `products/{id}/stock`, `products/{id}/history` | Analyses produit |
| GET | `products/valuation`, `low-stock` | Rapports de valorisation et rupture |
| PATCH/POST | `products/{id}/stock/{warehouseId}`, `products/{id}/transfer` | Ajustement et transfert de stock |
| CRUD + cache 5 min | `stock-movements`, `lots`, `picking-orders`, `shipments`, `purchase-orders`, `rmas` | Ressources opérationnelles fréquentes |
| CRUD + cache 15 min | `transfer-orders`, `demand-forecasts`, `valuations`, `seasonal-factors`, `cycle-counts` | Ressources de planification |
| GET/POST | `lots/expiring`, `lots/{lot}/receive\|issue\|transfer\|quarantine` | Cycle de vie d'un lot |
| POST | `valuation/receive`, `valuation/run`, GET `valuation/summary\|total-value\|by-warehouse` | Moteur de valorisation |
| POST | `demand-forecasts/generate`, `demand-forecasts/reconcile` | Prévision de la demande |
| POST | `transfer-orders/{id}/approve\|ship\|receive\|cancel` | Cycle de vie transfert inter-entrepôts |
| POST | `redistribution/rules`, `redistribution/auto-suggest` | Rééquilibrage automatique du stock |
| GET | `picking-orders/next` | File d'attente : prochaine commande à préparer (ajouté au Chantier 8.3, seul vrai manque trouvé parmi 7 routes mortes) |
| POST | `picking-orders/{id}/assign`, `.../lines/{line}/pick`, `.../complete`, `waves/{wave}/start\|complete` | Préparation de commande / picking par vagues |
| POST | `cycle-counts/{id}/validate`, `.../lines/{line}/count` | Comptage cyclique |
| GET | `barcode/product/{barcode}`, `barcode/location/{barcode}` | Scan code-barres (l'ancienne route `barcodes/lookup` et l'`apiResource('barcodes', ...)` non implémenté ont été supprimés au Chantier 8.3, un code-barres n'étant pas une ressource CRUD autonome dans ce modèle) |
| POST | `barcode/stock-movement` | Mouvement de stock déclenché par scan |
| POST | `ai/forecast-demand`, `ai/suggest-reorder`, `ai/analyze-anomalies`, `ai/classify-abc`, `ai/detect-obsolete` | IA stock (ABC, anomalies, obsolescence) |
| POST | `purchase-orders/{id}/send\|receive` | Cycle bon de commande interne à Inventory |
| POST | `shipments/rates`, GET `shipments/{id}/track` | Cotation transporteur et suivi |
| POST | `rmas/{id}/approve\|receive\|refund` | Cycle retour |
| POST | `v1/inventory/ai/assist` | Guidance IA contextuelle (AI Assisted First) |
| POST | `v1/inventory/edi/receive`, `edi/generate-810`, GET `edi/transactions` | EDI 850/856/810 |
| GET/POST | `v1/inventory/3pl/connectors`, `3pl/fulfill`, `3pl/orders/{id}/status`, `3pl/sync-inventory` | Connecteurs 3PL (Amazon FBA, ShipBob, ShipMonk) |

## Contrôleurs

25 contrôleurs Api (`Modules/Inventory/app/Http/Controllers/Api/`) + 4 contrôleurs Web (`Http/Controllers/Web/`).

Api : `ProductController`, `CategoryController`, `WarehouseController`, `SupplierController`, `StockMovementController`, `LotTrackingController`, `TransferOrderController`, `CycleCountController`, `PickingOrderController`, `WavePickingController`, `CrossdockController`, `RmaController`, `DemandForecastController`, `SeasonalFactorController`, `ValuationController`, `BarcodeController`, `ShipmentController`, `PurchaseOrderController` (bon de commande « léger » propre à Inventory), `EdiController`, `FulfillmentController` (3PL), `EcommerceSyncController`, `ChannelController`, `UnitController`, `InventoryAIController`/`InventoryAiAssistController`.

Web : `ProductController`, `CategoryController`, `WarehouseController` (les trois réduits à leur seule méthode `index()` réelle lors du Chantier 8.3 — les pages `categories`/`warehouses` sont des listes+modales CRUD qui appellent l'API JSON directement, sans besoin de routes web `create`/`store`/`show`/`edit`/`update`/`destroy` séparées), `InventoryWebController` (8 méthodes servant `Suppliers`, `PurchaseOrders`, `WMS/Picking`, `CycleCounts`, plus 4 pages auto-suffisantes routées par closure).

**Contrôleurs supprimés (Chantier 8.3, dette morte)** : `Http\Controllers\InventoryController` (scaffold mort, zéro route, vues blade jamais réelles dans cet Inertia-app) et `Http\Controllers\Web\ProductWebController` (100 % redondant avec `ProductController::index()`).

**ChannelController**/**UnitController** étaient des contrôleurs Api réels et complets mais sans aucune route — câblés au Chantier 8.3 (`channels*`/`units*`).

## Vues (Vue/Inertia)

`Modules/Inventory/resources/js/Pages/` contient (entre autres) `Products/`, `Categories/Index.vue` (nouvelle page liste+modale-CRUD construite au Chantier 8.3 sur le même patron que `Warehouses/Index.vue`), `Warehouses/Index.vue`, `Stock/Movements.vue` (couvre aussi les ajustements manuels de stock — la ressource `stock-adjustments` séparée, 100 % scaffold sans route API, a été supprimée plutôt que construite), `Suppliers/`, `PurchaseOrders/`, `WMS/Picking.vue`, `CycleCounts/`, `Shipments/Index.vue`, `Returns/Index.vue` (RMA), `WMS/Crossdock/Index.vue`, `WMS/Waves/Index.vue`, `Channels/Index.vue` (nouvelle, Chantier 8.3), `ReorderAutomation/Index.vue`, `DemandForecast/Index.vue`, `MarketplaceSync/Index.vue`.

`Channels/Index.vue` (`/inventory/channels`) n'est accessible que par URL directe — pas de lien de navigation, même schéma de découvrabilité que `consolidation-hierarchies` côté Accounting. `Shipments/Index.vue`, `Returns/Index.vue`, `WMS/Crossdock/Index.vue` et `WMS/Waves/Index.vue` sont des pages auto-suffisantes (fetch direct, aucune prop serveur) routées par simples closures `Inertia::render()`.

## Services

- **`InventoryService` / `StockManagementService` / `StockService`** — opérations cœur de gestion de stock (réception, ajustement, transfert).
- **`ReorderAutomationService`** — surveille le stock par tenant, calcule le point de commande (avec facteurs saisonniers Afrique de l'Ouest/Est/Madagascar : Ramadan, rentrée scolaire, récolte), sélectionne le fournisseur préféré et **crée directement un `Modules\Achats\Models\PurchaseOrder`** quand le stock passe sous le seuil — c'est le point d'entrée concret du couplage bidirectionnel Inventory ↔ Achats.
- **`ValuationService`** — valorisation FIFO/AVCO par couches de coût (`CostLayer`), exécutions de valorisation (`ValuationRun`).
- **`LotTrackingService`** — cycle de vie des lots (réception, sortie, transfert, mise en quarantaine), FEFO.
- **`DemandForecastService` / `SeasonalDemandService`** — prévision de la demande avec ajustement saisonnier.
- **`WavePickingService` / `WmsService`** — orchestration WMS et picking par vagues.
- **`CrossdockService`** — transfert direct réception → expédition sans stockage intermédiaire.
- **`CycleCountService`** — planification et validation des comptages cycliques.
- **`StockRedistributionService`** — analyse de rééquilibrage entre entrepôts et suggestions automatiques.
- **`RmaService`** — traitement des retours (approbation, réception, remboursement).
- **`BarcodeService`** — résolution code-barres → produit/emplacement.
- **`EdiService`** — réception EDI 850, génération EDI 810.
- **`ShippingService`** — cotation et gestion des transporteurs sortants.
- **`Marketplace\ChannelSyncService`, `AmazonSpApiConnector`, `EbayConnector`** — synchronisation catalogue/stock avec places de marché.
- **`ThirdPartyLogistics\FulfillmentService`** et connecteurs (`FulfillmentByAmazonConnector`, `ShipBobConnector`, `ShipMonkConnector`) — externalisation logistique 3PL.
- **`AI\InventoryAIService`, `AI\ABCAnalysisService`** — classification ABC, détection d'anomalies/obsolescence, suggestions de réapprovisionnement.

## Permissions RBAC

**Correction RBAC majeure (Chantier 8.3)** : `Modules/Inventory/routes/api.php` n'avait **aucun** verrou `module:`/`role:` sur ses ~120 endpoints — seulement `auth:sanctum, session.security, tenancy.user` — n'importe quel utilisateur authentifié de n'importe quel tenant pouvait lire/écrire toutes les données d'inventaire. Corrigé en ajoutant `module:Inventory` + `role:employee,logistics-manager,warehouse-operator,purchasing-manager,inventory-analyst,manager,admin` aux 4 groupes de routes de premier niveau (confirmé dans le code : les 4 occurrences de `module:Inventory` dans `routes/api.php`) ; `employee` est inclus délibérément — c'est le rôle « toutes permissions non-destructives, tous modules » de cette app.

Préfixe `inventory.` (`database/seeders/RolesAndPermissionsSeeder.php`), avec ressources `product, category, warehouse, unit, stock-movement, purchase-order, supplier` × actions `view-any, view, create, update, delete`. Rôles concernés :
- `logistics-manager` — accès complet `inventory.*` + `logistics.*`
- `purchasing-manager` — accès complet `inventory.*` + `achats.*`
- `warehouse-operator` — sous-ensemble restreint (produits, mouvements de stock, entrepôts, catégories, unités)
- `inventory-analyst` — lecture seule `inventory.*` (`view-any`/`view`) + accès complet `bi.*` et `analytics.*`

## Dépendances avec d'autres modules

- **Achats** : `ReorderAutomationService` importe `Modules\Achats\Models\PurchaseOrder` et crée des bons de commande automatiquement ; à l'inverse, `Modules\Achats\Models\PoInventoryMapping`, `PurchaseOrderLine`, `PurchaseReceiptLine` et `RFQLine` importent `Modules\Inventory\Models\Product`.
- **AI** : `InventoryAiAssistController` utilise `AiContextualAssistantService` (AI Assisted First).
- **Core** : `RecordsActivity` (audit) sur plusieurs modèles.
- **BI/Analytics** (consommateurs) : `Modules\BI\Http\Controllers\Api\ExportController` importe `Modules\Inventory\Models\Product` et `StockMovement` pour l'export analytique.
- **Helpdesk** (consommateur) : selon le CLAUDE.md du projet, `Inventory\Product` est linkable via `HelpdeskLinkable` pour ouvrir des tickets depuis une fiche produit.

## Particularités du périmètre life-mdg-erp

- Le module conserve plusieurs fonctionnalités très avancées (connecteurs Amazon SP-API/eBay, 3PL ShipBob/ShipMonk/FBA, EDI 850/856/810) héritées telles quelles de WideHalo, bien que les modules Ecommerce et Manufacturing aient été retirés du périmètre Life MDG — ces connecteurs restent fonctionnels de manière autonome (ils ne dépendent pas d'Ecommerce).
- **Chantier 8.3 a corrigé une casse active réelle** : `/categories`, `/warehouses` et l'ancien `/stock-adjustments` renvoyaient une 500 sur toutes leurs actions `create`/`show`/`edit` (les vues Vue ciblées n'existaient nulle part dans le dépôt) — `stock-adjustments` a été supprimé (100 % redondant avec `Stock/Movements.vue`), `categories`/`warehouses` ont reçu de vraies pages. Plusieurs dizaines de tables `inventory_*` issues de la migration fourre-tout de scaffold ont aussi été patchées pour porter les colonnes que leurs contrôleurs déjà routés écrivaient réellement (ex. `inventory_cycle_counts.assigned_to`, `inventory_purchase_orders.expected_at`/`received_at`) — voir le `CLAUDE.md` racine (Chantier 8.3, parties 1 à 7) pour le détail exhaustif.
- **Inventory possède son propre modèle `PurchaseOrder`** (table `inventory_purchase_orders`, avec son propre `PurchaseOrderService` et `PurchaseOrderController`), distinct et non synchronisé automatiquement avec le `PurchaseOrder` du module **Achats** (table `achats_purchase_orders` implicite) que `ReorderAutomationService` utilise réellement pour l'automatisation. De même, `Supplier` existe en double (`inventory_suppliers` vs `achats_suppliers`), tout comme `Shipment` (`inventory_shipments` vs `logistics_shipments`). Ce n'est pas une erreur d'extraction : cette duplication de modèles existe déjà telle quelle dans WideHalo-ERP et n'a pas été retirée lors du découpage vers Life MDG. **Correction (Chantier 10)** : `Warehouse` n'est en revanche plus dupliqué — le module Logistics avait bien son propre `Warehouse`/table `wh_warehouses`, mais ce sous-système parallèle (`wh_*`/`lgx_*`) a été supprimé entièrement au Chantier 8.3 part 6 comme dead code redondant avec `ShipmentController`/`CarrierController` ; Logistics n'a aujourd'hui aucun modèle `Warehouse` propre (ses `Location`/`Shipment.origin_warehouse_id`/`destination_warehouse_id` sont de simples entiers sans relation Eloquent déclarée vers `Inventory\Warehouse`, à traiter comme faisant implicitement référence à `inventory_warehouses`).

## Chantier 10 (re-vérification)

- **Trouvaille principale** : le module n'avait **aucun** `registerPolicies()`/`Gate::policy()` nulle part et **aucun** appel `authorize()` dans ses contrôleurs, alors que `WarehousePolicy`/`StockMovementPolicy`/`StockPolicy` (`app/Policies/`) existaient déjà, écrites en entier — même classe de bug que Core/BI/HR/Calendar/Strategy/API/CRM ailleurs dans ce dépôt. Corrigé : `InventoryServiceProvider::registerPolicies()` (nouveau) enregistre les 3 policies ; `authorize()` ajouté dans `WarehouseController` (toutes méthodes) et `StockMovementController` (`index`/`show`/`store`). `StockPolicy` est enregistrée mais **volontairement pas encore appelée** — `inventory.stock.*` n'est seedé nulle part dans `RolesAndPermissionsSeeder::MODULES['inventory']` (contrairement à `warehouse`/`stock-movement`) ; l'ajouter est un changement de seeder hors périmètre de ce chantier, signalé pour une passe centralisée dédiée aux permissions.
- **Bug de nommage de permission corrigé** : `StockMovementPolicy` vérifiait `inventory.stockmovement.*` (sans tiret) alors que la ressource réellement seedée est `stock-movement` (avec tiret) — chaque vérification échouait donc pour tout le monde, y compris `admin`, une fois la policy enregistrée. Corrigé pour matcher la convention déjà seedée.
- **Route morte corrigée** : `GET stock-movements/{id}` était routée (`apiResource(...)->only(['index','show'])`) contre une méthode `show()` qui n'existait pas du tout sur `StockMovementController` — erreur fatale sur toute requête. Ajoutée. `update`/`destroy` étaient routées de la même façon contre des méthodes inexistantes ; retirées de `routes/api.php` plutôt que stubbées — un mouvement de stock est traité comme un fait d'audit immuable dans ce design, et aucune page frontend n'appelle jamais ces deux verbes.
- **Audit `$fillable` vs schéma réel** : `Stock`, `StockMovement`, `Warehouse` confirmés alignés avec les colonnes réellement migrées (`inventory_stock`, `inventory_stock_movements`, `inventory_warehouses`).
