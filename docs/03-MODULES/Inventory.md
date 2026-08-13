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
| GET/POST/PUT/DELETE | `products`, `categories`, `warehouses`, `suppliers`, `barcodes` | CRUD ressources catalogue (cache 10 min en lecture) |
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
| POST | `picking-orders/{id}/record\|assign\|complete`, `waves/{wave}/start\|complete` | Préparation de commande / picking par vagues |
| POST | `cycle-counts/{id}/record\|validate` | Comptage cyclique |
| POST | `barcodes/lookup`, GET `barcode/product/{barcode}`, `barcode/location/{barcode}` | Scan code-barres |
| POST | `ai/forecast-demand`, `ai/suggest-reorder`, `ai/analyze-anomalies`, `ai/classify-abc`, `ai/detect-obsolete` | IA stock (ABC, anomalies, obsolescence) |
| POST | `purchase-orders/{id}/send\|receive` | Cycle bon de commande interne à Inventory |
| POST | `shipments/rates`, GET `shipments/{id}/track` | Cotation transporteur et suivi |
| POST | `rmas/{id}/approve\|receive\|refund` | Cycle retour |
| POST | `v1/inventory/ai/assist` | Guidance IA contextuelle (AI Assisted First) |
| POST | `v1/inventory/edi/receive`, `edi/generate-810`, GET `edi/transactions` | EDI 850/856/810 |
| GET/POST | `v1/inventory/3pl/connectors`, `3pl/fulfill`, `3pl/orders/{id}/status`, `3pl/sync-inventory` | Connecteurs 3PL (Amazon FBA, ShipBob, ShipMonk) |

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
- **Inventory possède son propre modèle `PurchaseOrder`** (table `inventory_purchase_orders`, avec son propre `PurchaseOrderService` et `PurchaseOrderController`), distinct et non synchronisé automatiquement avec le `PurchaseOrder` du module **Achats** (table `achats_purchase_orders` implicite) que `ReorderAutomationService` utilise réellement pour l'automatisation. De même, `Supplier` existe en double (`inventory_suppliers` vs `achats_suppliers`), tout comme `Shipment` (`inventory_shipments` vs `logistics_shipments`) et `Warehouse` (`inventory_warehouses` vs `wh_warehouses` dans Logistics). Ce n'est pas une erreur d'extraction : cette duplication de modèles existe déjà telle quelle dans WideHalo-ERP et n'a pas été retirée lors du découpage vers Life MDG.
