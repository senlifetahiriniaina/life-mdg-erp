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

## Détail des colonnes (succinct)

Extrait le 2026-08-21 directement du schéma SQLite réellement migré (`Schema::getColumns()`), pas des fichiers de migration — la source de vérité la plus fiable compte tenu du mécanisme « racine crée, module patche » documenté dans `SCHEMA-GENERAL.md`. Format : `colonne:type` — `!` = non nullable (requis). Types SQLite génériques (`integer`/`varchar`/`numeric`/`text`/`datetime`/`date`/`tinyint`) ; en production MySQL les types réels sont plus précis (`bigint unsigned`, `decimal(15,4)`, etc.) mais la structure des colonnes est identique.

### Achats (12 tables)

**`achats_po_accounting_mappings`** — `id:integer!,purchase_order_id:integer!,data:text,created_at:datetime,updated_at:datetime`

**`achats_po_budget_allocations`** — `id:integer!,purchase_order_id:integer!,data:text,created_at:datetime,updated_at:datetime`

**`achats_po_inventory_mappings`** — `id:integer!,purchase_order_id:integer!,data:text,created_at:datetime,updated_at:datetime`

**`achats_purchase_invoice_matches`** — `id:integer!,tenant_id:integer,purchase_receipt_id:integer!,purchase_order_id:integer!,invoice_id:integer,quantity_variance:numeric!,price_variance:numeric!,match_result:varchar!,mismatch_details:text,status:varchar!,resolved_by:integer,resolved_at:datetime,resolution_notes:text,created_at:datetime,updated_at:datetime`

**`achats_purchase_order_lines`** — `id:integer!,purchase_order_id:integer!,description:varchar,quantity:numeric!,unit_price:numeric!,total_price:numeric!,created_at:datetime,updated_at:datetime,product_id:integer,unit:varchar,tax_rate:numeric!,received_qty:numeric!,invoiced_qty:numeric!,line_status:varchar!,line_total:numeric`

**`achats_purchase_orders`** — `id:integer!,tenant_id:integer,reference:varchar,status:varchar!,supplier_id:integer,total_amount:numeric!,currency:varchar!,expected_date:date,created_at:datetime,updated_at:datetime,deleted_at:datetime,po_number:varchar,order_date:date,delivery_date:date,created_by:integer,requested_by:integer,approved_by:integer,approved_at:datetime,subtotal:numeric!,tax_amount:numeric!,shipping_cost:numeric!,total:numeric!,notes:text,rejected_by:integer,rejected_at:datetime,company_id:integer,deposit_percent:numeric,deposit_required_amount:numeric,deposit_invoice_id:integer,balance_invoice_id:integer,production_order_id:integer`

**`achats_purchase_receipt_lines`** — `id:integer!,receipt_id:integer!,order_line_id:integer,quantity_received:numeric!,created_at:datetime,updated_at:datetime,purchase_order_line_id:integer,product_id:integer,quality_status:varchar!,variance_qty:numeric!,notes:text`

**`achats_purchase_receipts`** — `id:integer!,purchase_order_id:integer!,reference:varchar,received_at:date,created_at:datetime,updated_at:datetime,receipt_number:varchar,receipt_date:date,received_by:integer,warehouse_location:varchar,notes:text,total_received_value:numeric,status:varchar!,deleted_at:datetime,company_id:integer`

**`achats_rfq_lines`** — `id:integer!,rfq_id:integer!,description:varchar,quantity:numeric!,unit_price:numeric,created_at:datetime,updated_at:datetime,product_id:integer,unit:varchar,required_date:date,preferred_supplier_id:integer,notes:text`

**`achats_rfqs`** — `id:integer!,tenant_id:integer,reference:varchar,status:varchar!,supplier_id:integer,deadline:date,created_at:datetime,updated_at:datetime,deleted_at:datetime,title:varchar,description:varchar,category:varchar,is_urgent:tinyint!,created_by:integer,required_by_date:date,rfq_number:varchar,currency:varchar!,issued_date:date,deadline_date:date,company_id:integer`

**`achats_supplier_quotes`** — `id:integer!,rfq_id:integer!,supplier_id:integer!,total_amount:numeric,currency:varchar,status:varchar!,created_at:datetime,updated_at:datetime,quote_number:varchar,unit_price:numeric,total_price:numeric,delivery_days:integer,terms:text,validity_date:date,created_by:integer,deleted_at:datetime,company_id:integer`

**`achats_suppliers`** — `id:integer!,tenant_id:integer,name:varchar!,code:varchar,email:varchar,phone:varchar,country:varchar,currency:varchar,is_active:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime,created_by:integer,lead_time_days:integer,contact_person:varchar,address:varchar,city:varchar,tax_number:varchar,payment_terms:varchar,company_id:integer`

### Inventory (42 tables)

**`goods_receipts`** — `id:integer!,tenant_id:integer,reference:varchar,received_at:date,created_at:datetime,updated_at:datetime`

**`inventory_carriers`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,code:varchar,tracking_url_template:varchar,api_key:varchar,active:tinyint!,settings:text`

**`inventory_categories`** — `id:integer!,parent_id:integer,name:varchar!,slug:varchar!,description:text,image:varchar,created_at:datetime,updated_at:datetime,deleted_at:datetime,default_stock_account_code:varchar,default_purchase_account_code:varchar,default_sale_account_code:varchar,default_variance_account_code:varchar`

**`inventory_cost_layers`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,product_id:integer,warehouse_id:integer,quantity:numeric!,unit_cost:numeric!,valuation_type:varchar!,method:varchar!,quantity_received:numeric!,quantity_remaining:numeric!,total_cost:numeric!,received_at:datetime,reference:varchar,is_exhausted:tinyint!`

**`inventory_costing_sheet_lines`** — `id:integer!,costing_sheet_id:integer!,section:varchar!,designation:varchar!,product_template_id:integer,supplier_id:integer,consumption_qty:numeric!,unit:varchar,unit_price:numeric!,currency:varchar!,customs_freight_percent:numeric!,margin_percent:numeric,line_total:numeric!,sequence:integer!,created_at:datetime,updated_at:datetime`

**`inventory_costing_sheets`** — `id:integer!,reference:varchar!,name:varchar!,product_template_id:integer,opportunity_id:integer,gender:varchar,size_range:varchar,season:varchar,quantity:integer!,base_currency:varchar!,status:varchar!,version:integer!,parent_id:integer,production_minutes:numeric!,minute_cost:numeric!,labor_cost:numeric!,fixed_cost_coefficient:numeric!,washing_cost:numeric!,target_margin_percent:numeric,total_material_cost:numeric!,total_assembly_cost:numeric!,total_finishing_cost:numeric!,total_value_added_cost:numeric!,total_cost_price:numeric!,suggested_selling_price:numeric!,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`inventory_crossdock_operations`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,inbound_shipment_id:integer,outbound_order_id:integer,product_id:integer,qty:numeric!,reference:varchar,incoming_shipment_id:integer,outgoing_order_id:integer,warehouse_id:integer,items:text,executed_at:datetime`

**`inventory_cycle_count_lines`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,product_id:integer,location_id:integer,system_qty:numeric!,counted_qty:numeric,cycle_count_id:integer,variance:numeric,expected_qty:numeric!,lot_number:varchar,variance_value:numeric`

**`inventory_cycle_counts`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,warehouse_id:integer,reference:varchar,count_date:date,created_by:integer,is_active:tinyint!,notes:text,completed_at:datetime,assigned_to:integer`

**`inventory_demand_forecasts`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,product_id:integer,forecast_date:date,forecast_qty:numeric!,actual_qty:numeric,horizon_days:integer!,warehouse_id:integer,period_start:datetime,period_end:datetime,period_type:varchar!,forecasted_qty:numeric!,method:varchar,confidence:numeric,metadata:text,period:varchar,accuracy:numeric,algorithm:varchar!`

**`inventory_locations`** — `id:integer!,warehouse_id:integer!,name:varchar!,code:varchar,created_at:datetime,updated_at:datetime`

**`inventory_lot_movements`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,lot_id:integer,movement_type:varchar!,quantity:numeric!,reference:varchar,notes:text,warehouse_from_id:integer,warehouse_to_id:integer,product_id:integer,performed_by:integer,created_by:integer`

**`inventory_lots`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,product_id:integer,lot_number:varchar,expiry_date:date,quantity:numeric!,location_id:integer,serial_number:varchar,manufacture_date:date,warehouse_id:integer,notes:text,created_by:integer`

**`inventory_movements`** — `id:integer!,product_id:integer!,warehouse_id:integer!,location_id:integer,user_id:integer,reference:varchar,type:varchar!,quantity:numeric!,unit_cost:numeric!,lot_number:varchar,serial_number:varchar,expiry_date:date,notes:text,source_type:varchar!,source_id:integer!,created_at:datetime,updated_at:datetime`

**`inventory_pick_lines`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,wave_id:integer,product_id:integer,warehouse_location:varchar,qty_requested:numeric!,qty_picked:numeric!`

**`inventory_picking_lines`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,picking_order_id:integer,product_id:integer,location_id:integer,requested_qty:numeric!,picked_qty:numeric!,lot_number:varchar,quantity_requested:numeric!,quantity_picked:numeric!`

**`inventory_picking_orders`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,reference:varchar,warehouse_id:integer,type:varchar,source_type:varchar,assigned_to:integer,completed_at:datetime,priority:varchar!,wave_id:integer,order_id:integer,order_type:varchar!,started_at:datetime,source_id:integer`

**`inventory_picking_waves`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,order_ids:text,assigned_to:integer,completed_at:datetime,reference:varchar,wave_type:varchar!,warehouse_id:integer,picker_id:integer,started_at:datetime`

**`inventory_po_receipt_lines`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,receipt_id:integer,po_item_id:integer,product_id:integer,received_qty:numeric!,lot_number:varchar,expiry_date:date,purchase_order_item_id:integer,quantity_ordered:numeric,quantity_received:numeric`

**`inventory_po_receipts`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,purchase_order_id:integer,reference:varchar,warehouse_id:integer,received_by:integer,received_at:datetime,notes:text`

**`inventory_product_templates`** — `id:integer!,code:varchar!,name:varchar!,family:varchar!,category_id:integer!,unit_id:integer,description:text,default_attributes:text,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`inventory_production_orders`** — `id:integer!,reference:varchar!,costing_sheet_id:integer,sales_order_id:integer,subcontractor_supplier_id:integer,quantity:integer!,status:varchar!,started_at:date,expected_delivery_at:date,delivered_at:date,notes:text,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`inventory_products`** — `id:integer!,category_id:integer,unit_id:integer,name:varchar!,sku:varchar!,barcode:varchar,description:text,type:varchar!,cost_price:numeric!,sale_price:numeric!,selling_price:numeric!,currency:varchar!,category:varchar,unit:varchar,status:varchar!,reorder_level:integer!,tenant_id:integer,reorder_point:integer!,reorder_qty:integer!,valuation_method:varchar!,track_serial:tinyint!,track_lot:tinyint!,image:varchar,is_active:tinyint!,attributes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,ecommerce_synced_at:datetime,ecommerce_sync_pending:tinyint!`

**`inventory_purchase_order_items`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,purchase_order_id:integer,product_id:integer,quantity:numeric!,received_qty:numeric!,unit_price:numeric!,tax_rate:numeric!,subtotal:numeric!,product_name:varchar,sku:varchar,quantity_ordered:numeric!,total_price:numeric!,quantity_received:numeric!`

**`inventory_purchase_orders`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,supplier_id:integer,reference:varchar,warehouse_id:integer,currency:varchar!,shipping_cost:numeric!,subtotal:numeric!,tax_total:numeric!,grand_total:numeric!,created_by:integer,approved_by:integer,approved_at:datetime,expected_date:date,notes:text,po_number:varchar,sent_at:datetime,expected_at:datetime,received_at:datetime`

**`inventory_redistribution_rules`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,source_warehouse_id:integer,target_warehouse_id:integer,trigger_condition:varchar!,threshold:numeric!,is_active:tinyint!,rule_type:varchar,from_warehouse_id:integer,to_warehouse_id:integer,product_id:integer,trigger_threshold:numeric!,transfer_quantity:numeric!,priority:integer!`

**`inventory_reorder_rules`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,product_id:integer,warehouse_id:integer,min_level:numeric,max_level:numeric,reorder_quantity:numeric,lead_time_days:integer`

**`inventory_rmas`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,reference:varchar,order_id:integer,customer_name:varchar,reason:text,items:text,return_method:varchar,approved_at:datetime,received_at:datetime,refunded_at:datetime`

**`inventory_seasonal_factors`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,product_id:integer,category_id:integer,period_type:varchar!,period_index:integer!,factor:numeric!,notes:text`

**`inventory_shipment_events`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,shipment_id:integer,event_type:varchar,description:text,occurred_at:datetime,location:varchar,notes:text`

**`inventory_shipments`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,carrier_id:integer,reference:varchar,order_id:integer,tracking_number:varchar,label_url:text,origin_address:text,destination_address:text,weight_kg:numeric,dimensions:text,service_type:varchar,estimated_cost:numeric,actual_cost:numeric,shipped_at:datetime,estimated_delivery_at:datetime,delivered_at:datetime,type:varchar!,origin:varchar,destination:varchar,estimated_delivery:date,weight:numeric,items:text`

**`inventory_skus`** — `id:integer!,code:varchar!,name:varchar!,unit:varchar,reorder_level:numeric!,reorder_point:numeric!,reorder_qty:numeric!,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`inventory_sourcing_benchmarks`** — `id:integer!,product_id:integer,product_template_id:integer,material_label:varchar,source:varchar!,source_name_other:varchar,source_url:varchar,unit_price:numeric!,currency:varchar!,unit:varchar,quantity_reference:numeric,observed_at:date!,notes:text,created_by:integer,created_at:datetime,updated_at:datetime`

**`inventory_stock`** — `id:integer!,product_id:integer!,warehouse_id:integer!,location_id:integer,quantity:numeric!,reserved_quantity:numeric!,avg_cost:numeric!,created_at:datetime,updated_at:datetime`

**`inventory_stock_movements`** — `id:integer!,product_id:integer,warehouse_id:integer,type:varchar!,quantity:numeric!,reference_type:varchar,reference_id:integer,reason:varchar,created_by:integer,created_at:datetime,updated_at:datetime,sku_id:integer,reference:varchar`

**`inventory_suppliers`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,email:varchar,currency:varchar!,payment_terms:varchar,is_active:tinyint!,lead_time_days:integer!,code:varchar,contact_name:varchar,phone:varchar,address:text,country:varchar,website:varchar,notes:text,rating:numeric!`

**`inventory_transfer_order_lines`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,transfer_order_id:integer,product_id:integer,requested_qty:numeric!,transferred_qty:numeric!,lot_number:varchar,requested_quantity:numeric!,approved_quantity:numeric,shipped_quantity:numeric!,received_quantity:numeric!,unit_cost:numeric!,notes:text`

**`inventory_transfer_orders`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,reference:varchar,from_warehouse_id:integer,to_warehouse_id:integer,requested_by:integer,approved_by:integer,type:varchar!,priority:varchar!,expected_delivery_date:date,notes:text,total_items:integer!,total_value:numeric!,received_at:datetime,approved_at:datetime,shipped_at:datetime`

**`inventory_units`** — `id:integer!,name:varchar!,symbol:varchar!,type:varchar!,created_at:datetime,updated_at:datetime`

**`inventory_valuation_runs`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,method:varchar!,run_date:date,total_value:numeric!,valuation_date:date,product_count:integer!,results:text,created_by:integer`

**`inventory_warehouses`** — `id:integer!,name:varchar!,code:varchar!,type:varchar!,address:text,city:varchar,country:varchar,is_active:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`marketplace_channels`** — `id:integer!,tenant_id:integer!,type:varchar!,name:varchar!,config:text!,status:varchar!,last_synced_at:datetime,sync_stats:text,company_id:integer!,created_at:datetime,updated_at:datetime`

### Logistics (19 tables)

**`lgx_carrier_rate_cards`** — `id:integer!,carrier_id:integer!,origin_country:varchar,dest_country:varchar,service_type:varchar,weight_min_kg:numeric,weight_max_kg:numeric,base_rate:numeric,per_kg_rate:numeric,currency:varchar,transit_days_min:integer,transit_days_max:integer,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`lgx_delivery_routes`** — `id:integer!,company_id:integer!,reference:varchar,name:varchar,date:date,status:varchar!,vehicle_id:integer,driver_id:integer,total_distance_km:numeric,total_duration_min:integer,total_stops:integer,optimized:tinyint!,created_at:datetime,updated_at:datetime`

**`lgx_hs_codes`** — `id:integer!,code:varchar!,description_fr:varchar,description_en:varchar,duty_rate_default:numeric,vat_applicable:tinyint!,requires_license:tinyint!,notes:text,chapter:varchar,section:varchar,unit:varchar,created_at:datetime,updated_at:datetime`

**`lgx_route_stops`** — `id:integer!,route_id:integer!,shipment_id:integer,sequence:integer!,type:varchar,address:varchar,lat:numeric,lng:numeric,planned_arrival:varchar,actual_arrival:datetime,planned_duration_min:integer,status:varchar!,proof_of_delivery:text,signature_url:varchar,notes:text,created_at:datetime,updated_at:datetime`

**`lgx_vehicles`** — `id:integer!,company_id:integer!,name:varchar!,plate_number:varchar,type:varchar,max_weight_kg:numeric,max_volume_m3:numeric,status:varchar!,driver_id:integer,fuel_type:varchar,fuel_consumption_per_100km:numeric,created_at:datetime,updated_at:datetime`

**`logistics_carrier_rates`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,carrier_id:integer,zone:varchar,weight_min:numeric!,weight_max:numeric,rate:numeric!,currency:varchar!,name:varchar,mode:varchar,origin_country:varchar,destination_country:varchar,rate_type:varchar,base_rate:numeric,fuel_surcharge_pct:numeric,insurance_rate_pct:numeric,min_charge:numeric,transit_days:integer,is_active:tinyint!,origin_zone:varchar,destination_zone:varchar,valid_from:date,valid_until:date`

**`logistics_carriers`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,code:varchar,type:varchar,is_active:tinyint!,contact_email:varchar,contact_phone:varchar,country:varchar,rating:numeric,website:varchar,tracking_url_template:varchar,api_provider:varchar,api_credentials:text,notes:text`

**`logistics_customs_declarations`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,shipment_id:integer,declaration_number:varchar,country:varchar,declared_value:numeric,currency:varchar,reference:varchar,hs_codes:text,duties_amount:numeric!,type:varchar!,incoterm:varchar,country_export:varchar,country_import:varchar,total_declared_value:numeric,total_duties:numeric,total_taxes:numeric,customs_broker:varchar,mrn_number:varchar,submitted_at:datetime,cleared_at:datetime,rejection_reason:text,notes:text,created_by:integer,hs_code:varchar,item_description:text,quantity:numeric,country_of_origin:varchar`

**`logistics_delivery_rounds`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,carrier_id:integer,driver_name:varchar,driver_phone:varchar,vehicle_plate:varchar,vehicle_code:varchar,vehicle_type:varchar,planned_date:date,route:varchar,reference:varchar,created_by:integer,started_at:datetime,completed_at:datetime,total_stops:integer!,total_distance_km:numeric,notes:text`

**`logistics_delivery_stops`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,delivery_round_id:integer,shipment_id:integer,location_id:integer,stop_order:integer!,sequence:integer,delivery_window:varchar,address:text,contact_name:varchar,notes:text,recipient_name:varchar,recipient_address:text,recipient_city:varchar,recipient_country:varchar,recipient_phone:varchar,latitude:numeric,longitude:numeric,arrived_at:datetime,completed_at:datetime,pod_signature:text,pod_photo_path:varchar,pod_note:text,failure_reason:varchar`

**`logistics_freight_invoices`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,invoice_number:varchar,shipment_id:integer,carrier_id:integer,type:varchar,quoted_amount:numeric,invoiced_amount:numeric,variance_amount:numeric,currency:varchar,invoice_date:date,due_date:date,paid_at:datetime,carrier_invoice_ref:varchar,dispute_reason:text,notes:text,approved_by:integer,created_by:integer`

**`logistics_locations`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,warehouse_id:integer,name:varchar,code:varchar,type:varchar,is_active:tinyint!,parent_id:integer,location_class:varchar!,max_weight:numeric,volume:numeric,temperature_zone:varchar,capacity_units:numeric,occupied_units:numeric!,max_weight_kg:numeric,temperature_class:varchar`

**`logistics_putaway_rules`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,location_id:integer,product_id:integer,product_category:varchar,carrier_id:integer,transport_mode:varchar,requires_cold_chain:tinyint!,has_hazmat:tinyint!,priority:integer,is_active:tinyint!,notes:text`

**`logistics_routes`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,origin:varchar,destination:varchar,carrier_id:integer,estimated_days:integer,cost:numeric,is_active:tinyint!,code:varchar,origin_name:varchar,origin_country:varchar,origin_address:varchar,destination_name:varchar,destination_country:varchar,destination_address:varchar,mode:varchar,distance_km:integer,estimated_transit_days:integer,notes:text`

**`logistics_shipment_lines`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,shipment_id:integer,product_id:integer,description:varchar,sku:varchar,hs_code:varchar,quantity:numeric,unit:varchar,unit_value:numeric,country_of_origin:varchar,lot_number:varchar,serial_number:varchar,expiry_date:date`

**`logistics_shipment_packages`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,shipment_id:integer,package_number:varchar,type:varchar,weight_kg:numeric,length_cm:numeric,width_cm:numeric,height_cm:numeric,tracking_number:varchar,seal_number:varchar,is_fragile:tinyint!`

**`logistics_shipments`** — `id:integer!,tenant_id:integer,reference:varchar,status:varchar!,created_at:datetime,updated_at:datetime,deleted_at:datetime,type:varchar!,shipper_name:varchar,shipper_address:text,shipper_city:varchar,shipper_country:varchar,consignee_name:varchar,consignee_address:text,consignee_city:varchar,consignee_country:varchar,transport_mode:varchar!,weight_kg:numeric,incoterm:varchar,requires_cold_chain:tinyint!,has_hazmat:tinyint!,carrier_id:integer,tracking_number:varchar,shipped_at:datetime,estimated_delivery:datetime,delivered_at:datetime,origin_location_id:integer,destination_location_id:integer,created_by:integer,carrier_rate_id:integer,route_id:integer,volume_cbm:numeric,declared_value:numeric,estimated_delivery_at:datetime,special_instructions:text,origin_warehouse_id:integer,destination_warehouse_id:integer,value_currency:varchar,estimated_cost:numeric,actual_cost:numeric,booked_at:datetime,picked_up_at:datetime,temperature_min:numeric,temperature_max:numeric,co2_kg:numeric`

**`logistics_tracking_events`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,shipment_id:integer,event_type:varchar,status_detail:varchar,location_name:varchar,recorded_at:datetime,recorded_by:integer,location_city:varchar,location_country:varchar,latitude:numeric,longitude:numeric,carrier_ref:varchar,is_exception:tinyint!,exception_reason:text,provider_event_id:varchar,idempotency_key:varchar`

**`shipment_tracking_events`** — `id:integer!,shipment_id:integer!,provider:varchar!,raw_payload:text,event_type:varchar!,event_at:datetime!,location:varchar,created_at:datetime,updated_at:datetime`

