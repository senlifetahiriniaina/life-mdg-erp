# Inventaire des tables — Stock et Logistique (3 modules)

Inventory, Logistics, Achats. Voir `SCHEMA-GENERAL.md` pour la méthode et la légende **réelle / stub patché / stub pur**.

## Inventory (37 tables : 11 réelles, 26 stub dont 14 patchées / 12 pures)

Le module avec le ratio stub le plus élevé du périmètre (26 des 37 tables sont d'origine stub) — cohérent avec le Chantier 8.3 qui a dû patcher des colonnes sur 11 tables `inventory_*` juste pour rendre des endpoints déjà routés utilisables (`inventory_cycle_counts.assigned_to`, `inventory_picking_orders.source_id`, `inventory_purchase_orders.expected_at`/`received_at`, etc.).

| Table | Rôle |
|---|---|
| `inventory_products` | Fiche produit — catalogue, unités, seuils de réapprovisionnement, coût |
| `inventory_warehouses` | Entrepôt (**distinct** du `Warehouse` de Logistics — deux modèles homonymes, deux tables différentes) |
| `inventory_stock` / `inventory_stock_movements` / `inventory_movements` | Niveaux de stock et journal des mouvements (entrée/sortie/transfert/ajustement) — trois tables au nom proche, vérifier laquelle le modèle réel `StockMovement` utilise avant d'écrire |
| `inventory_categories` | Taxonomie produit — `status` retiré du modèle/resource au Chantier 8.3 (jamais migré sur la vraie table, toujours résolu à `null`) |
| `inventory_units` | Unités de mesure — `UnitController` câblé pour la première fois au Chantier 8.3 partie 7 |
| `inventory_locations` | Emplacements |
| `inventory_skus` | SKU, ajoutée avec ses colonnes de mouvement associées |
| `edi_transactions` | Transactions EDI 850/856/810 — sans préfixe `inventory_`, créée par une migration Inventory dédiée |
| `marketplace_channels` | Canal marketplace connecté (Amazon, eBay) — `ChannelController`/`Channels/Index.vue` câblés au Chantier 8.3 partie 7, concept distinct d'`EcommerceSyncController` (celui-ci pousse vers une boutique déjà connectée, `ChannelController` gère la connexion elle-même) — sans préfixe |

**Tables stub, patchées depuis (14, cohérent avec `CLAUDE.md` Chantier 8.3 partie 3)** : `inventory_carriers`, `inventory_cycle_counts` (2 patches), `inventory_lot_movements`, `inventory_lots` (2), `inventory_pick_lines`, `inventory_picking_orders` (2), `inventory_po_receipt_lines`, `inventory_purchase_order_items`, `inventory_purchase_orders` (2), `inventory_redistribution_rules`, `inventory_reorder_rules`, `inventory_transfer_order_lines`, `inventory_transfer_orders` (2), `inventory_valuation_runs`. **Stub pures (12)** : `inventory_cost_layers`, `inventory_crossdock_operations`, `inventory_cycle_count_lines`, `inventory_demand_forecasts`, `inventory_picking_lines`, `inventory_picking_waves`, `inventory_po_receipts`, `inventory_rmas`, `inventory_seasonal_factors`, `inventory_shipment_events`, `inventory_shipments`, `inventory_suppliers`.

Un bug de nommage réel fixé au Chantier 8.3 partie 3 : `PutawayRuleController` écrivait via `DB::table()` brut avec un jeu de colonnes complètement différent de celui du modèle `PutawayRule` (`product_category`/`carrier_id`/`transport_mode`/`requires_cold_chain`/`has_hazmat` vs `product_category_id`/`strategy`/`temperature_required` sur le modèle) — la migration de patch correspond au contrôleur réel, pas au modèle mort.

## Logistics (19 tables : 7 réelles, 12 stub dont 11 patchées / 1 pure)

Le module le plus fortement patché du périmètre — seule `logistics_customs_declarations` n'a jamais reçu de patch. Trois familles de préfixes coexistent : `logistics_` (principal), `lgx_` (5 tables réelles), `wh_` (sous-système mort, voir plus bas).

| Table | Rôle |
|---|---|
| `logistics_shipments` | Expédition (TMS), liée à `HelpdeskLinkable`. `ShipmentController::book/dispatch/deliver/cancel` avaient `ShipmentService` injecté mais ne l'appelaient jamais (statut posé en dur, vocabulaire `'dispatched'` erroné hérité du modèle mort `LgxShipment`) — corrigé au Chantier 8.3 partie 3 |
| `shipment_tracking_events` | Évènements de suivi — sans préfixe, migration Logistics dédiée |
| `lgx_vehicles` | Véhicule de livraison |
| `lgx_hs_codes` | Nomenclature SH (codes douaniers, 50 codes) |
| `lgx_delivery_routes` / `lgx_route_stops` / `lgx_carrier_rate_cards` | Modèles `DeliveryRoute`/`RouteStop`/`CarrierRateCard` réels et déjà consommés par `RouteOptimizationService`/`CarrierIntegrationService::getRate()`, mais **sans aucune migration** avant le Chantier 8.3 partie 4 — chaque appel plantait en « table not found ». Migration ajoutée suivant la convention `id()`/`company_id` des `lgx_vehicles`, pas le scaffold générique `tenant_id`/`status`/`data` |

**Tables stub, patchées depuis (11)** : `logistics_carrier_rates`, `logistics_carriers` (2), `logistics_delivery_rounds`, `logistics_delivery_stops`, `logistics_freight_invoices`, `logistics_putaway_rules`, `logistics_routes` (2), `logistics_shipment_lines`, `logistics_shipment_packages`, `logistics_tracking_events` (2). **Stub pure** : `logistics_customs_declarations`.

Le sous-système `wh_*`/`lgx_*` parallèle (`WarehouseShipmentController`, `WarehouseService`, `LgxShipmentService`, modèles `Warehouse`/`WarehouseZone`/`WarehouseMovement`/`WarehouseLocation`/`WarehousePutAwayRule`/`LgxShipment`/`LgxCarrier`/`LgxTrackingEvent`/`LgxShipmentItem`) a été **entièrement supprimé** au Chantier 8.3 partie 6 — zéro route, zéro référence externe, collision d'URL avec le vrai `ShipmentController`/`CarrierController`. Leurs tables ne sont donc plus créées ni référencées ; `wh_` n'apparaît plus dans ce schéma. `Route.php` (doublon octet-pour-octet de `LogisticsRoute`) et `CustomsItem.php` (orphelin, table `lgx_customs_items` jamais migrée) ont été supprimés au même chantier.

## Achats (11 tables : 8 réelles, 3 stub dont 0 patchée — toutes stub pures)

| Table | Rôle |
|---|---|
| `achats_suppliers` | Fournisseur (distinct du `Supplier` d'Inventory) — `name`/`code`/`email`/`phone`/`country`/`currency`/`is_active` |
| `achats_rfqs` / `achats_rfq_lines` | Demande de prix et lignes — `supplier_id`, `deadline` |
| `achats_supplier_quotes` | Devis fournisseur — `rfq_id`, `supplier_id`, `total_amount`, `status` |
| `achats_purchase_orders` / `achats_purchase_order_lines` | Bon de commande fournisseur et lignes — `supplier_id`, `total_amount`, devise `XOF` par défaut ; lié à `Modules\Validation\Models\ApprovalRequest` et au trait `HelpdeskLinkable` |
| `achats_purchase_receipts` / `achats_purchase_receipt_lines` | Réception marchandise et lignes, three-way match avec facture/commande |

**Tables stub, jamais retouchées (3)** : `achats_po_accounting_mappings` (`PoAccountingMapping`, correspondance PO ↔ écriture comptable), `achats_po_budget_allocations` (`PoBudgetAllocation`), `achats_po_inventory_mappings` (`PoInventoryMapping`, porte `syncToInventory()`) — toutes trois créées au schéma générique `id`/`purchase_order_id`/`data`/`timestamps` (variante légèrement différente du stub standard : FK `purchase_order_id` au lieu de `tenant_id`).

**Gap non documenté ailleurs, trouvé en croisant ce document contre `docs/03-MODULES/Achats.md`** : le modèle `PurchaseInvoiceMatch` (rapprochement facture ↔ commande ↔ réception, three-way match) déclare `protected $table = 'achats_purchase_invoice_matches'` mais **aucune migration nulle part dans le dépôt ne crée cette table** — ni à la racine, ni dans `Modules/Achats/database/migrations/`, ni dans le catch-all stub. C'est un vrai « table not found » en attente dès que ce modèle est sollicité, distinct du phénomène des tables stub (celui-ci n'a même pas de scaffold générique).

`PurchaseOrderPolicy` était un no-op pur (toutes les abilities dont `approve`/`reject` retournaient `true` inconditionnellement, avec un commentaire de code l'admettant) — corrigé au Chantier 8.5-light avec de vraies permissions `achats.purchase-order.approve`/`.reject`. Le sous-système `PurchaseApprovalChainService`/`PurchaseOrderApproval`/`PurchaseApprovalController` (doublon cassé, jamais routé, seuils 5000/10000 en dur) a été supprimé au Chantier 8.5sv — le vrai flux d'approbation Achats passe par le moteur Validation (`ApprovalRoutingResolver`). `PurchaseIntegrationService` (PO↔Accounting/Inventory sync, 8 méthodes réelles, zéro consommateur, 3 modèles dépendants sur des tables stub non patchées) reste un gap documenté mais non construit — voir `CLAUDE.md` Chantier 8.5-light.
