# Achats

## Rôle

Le module Achats ("Purchasing") pilote le cycle procure-to-pay côté achats : demandes de prix (RFQ) auprès des fournisseurs, comparaison des devis, création et approbation de bons de commande (multi-niveaux), réception marchandise avec contrôle qualité, rapprochement à trois voies (three-way match) et reporting achats. Il complète Inventory et Logistics dans le pôle **Stock et Logistique**, et s'appuie sur le moteur d'approbation générique du module Validation.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Supplier` | `achats_suppliers` | Fournisseur (distinct du `Supplier` d'Inventory) |
| `RFQ`, `RFQLine` | — | Demande de prix et ses lignes |
| `SupplierQuote` | — | Devis fournisseur en réponse à une RFQ |
| `PurchaseOrder`, `PurchaseOrderLine` | — | Bon de commande fournisseur et ses lignes ; lié à `Modules\Validation\Models\ApprovalRequest` et au trait `HelpdeskLinkable` |
| `PurchaseOrderApproval` | — | Étape d'approbation d'un bon de commande |
| `PurchaseReceipt`, `PurchaseReceiptLine` | — | Réception marchandise et ses lignes, avec statut qualité par ligne |
| `PurchaseInvoiceMatch` | — | Rapprochement facture ↔ commande ↔ réception (three-way match) |
| `PoAccountingMapping` | — | Correspondance bon de commande ↔ écriture comptable (Accounting) |
| `PoBudgetAllocation` | — | Allocation budgétaire d'un bon de commande |
| `PoInventoryMapping` | `achats_po_inventory_mappings` | Correspondance bon de commande ↔ réception ↔ produit Inventory, avec méthode `syncToInventory()` |

## Endpoints principaux

Toutes les routes sont sous `auth:sanctum` avec garde de rôle `role:purchasing-manager,warehouse-operator,manager,admin`.

| Méthode | Route | Description |
|---|---|---|
| GET | `purchase-orders`, `purchase-orders/{id}` | Liste/détail bons de commande |
| GET | `purchase-orders/{id}/export/json\|csv`, `.../summary` | Export et résumé d'un bon de commande |
| GET/POST | `purchase-orders/{id}/lines[/{line}]` | Lignes de bon de commande (imbriquées) |
| GET | `suppliers`, `suppliers/{id}`, `suppliers/{id}/performance`, `suppliers/{id}/quotes` | Fournisseurs, performance, historique devis |
| GET | `rfqs`, `rfqs/{id}`, `rfqs/{id}/comparison` | RFQ et comparaison des devis reçus |
| GET/POST | `rfqs/{id}/lines[/{line}]` | Lignes RFQ |
| GET | `supplier-quotes`, `supplier-quotes/{id}` | Devis fournisseur |
| GET | `purchase-receipts`, `purchase-receipts/{id}` | Réceptions |
| GET | `reports/spending`, `reports/pending-receipts`, `reports/overdue-invoices` | Rapports achats (analytiques) |
| POST | `purchase-orders` / PUT / DELETE | Création/mise à jour/suppression de bon de commande (statut brouillon uniquement) |
| POST | `purchase-orders/{id}/submit`, `.../approve`, `.../cancel` | Cycle d'approbation d'un bon de commande |
| POST | `suppliers`, `rfqs`, `rfqs/{id}/issue`, `rfqs/{id}/close` | Gestion fournisseur et cycle de vie RFQ |
| POST | `rfqs/{id}/suppliers/{supplier}/quote` | Enregistrement d'un devis fournisseur |
| POST | `supplier-quotes/{id}/accept\|reject` | Décision sur un devis |
| POST | `purchase-receipts`, `purchase-orders/{id}/receipts` | Création d'une réception |
| POST | `purchase-receipts/{id}/complete`, `.../quality-issue` | Finalisation réception et signalement qualité |
| POST | `v1/achats/ai/assist` | Guidance IA contextuelle (AI Assisted First) |

## Services

- **`PurchaseOrderService`** — création/mise à jour de bon de commande (modifiable en brouillon uniquement), génération du numéro `PO-{YYYY}-{MM}-{SEQUENCE}` (`config/achats.php`), soumission à l'approbation via `Modules\Validation\Services\ApprovalRequestService` et `ApprovalWorkflow` (`module_name = 'Achats'`), déclenche les événements `PurchaseOrderSubmittedForApproval`, `PurchaseOrderApproved`, `PurchaseOrderReceived`, `PurchaseOrderInvoiced`, `PurchaseOrderCancelled`.
- **`ApprovalRoutingService` / `PurchaseApprovalChainService`** — routage des demandes d'approbation selon des règles (`Modules\Validation\Models\ApprovalRule`, `ApprovalWorkflow`) — typiquement des seuils de montant.
- **`PurchaseReceiptService`** — création de réception (délègue à `PurchaseOrderService::markAsReceived`), enregistrement des quantités reçues et écarts par ligne, signalement d'un problème qualité (événement `QualityIssueRecorded`), finalisation de la réception (événement `PurchaseReceiptCompleted`), calcul des écarts quantité/valeur et du rapport de réception.
- **`RFQService`** — cycle de vie d'une demande de prix (émission, clôture, comparaison des devis).
- **`SupplierService`** — gestion des fournisseurs et de leurs métriques de performance.
- **`ThreeWayMatchService`** — rapprochement bon de commande / réception / facture (`PurchaseInvoiceMatch`).
- **`BulkPurchaseOrderService`** — création de bons de commande en masse.
- **`PurchaseOrderExportService`** — export JSON/CSV et résumé d'un bon de commande.
- **`PurchaseIntegrationService`** — point d'intégration croisée avec les autres modules (comptabilité, budget, stock).

## Permissions RBAC

Préfixe `achats.` (`database/seeders/RolesAndPermissionsSeeder.php`), ressources `rfq, purchase-order, purchase-receipt, supplier` × actions `view-any, view, create, update, delete`. Rôle dédié : `purchasing-manager` — accès complet `inventory.*` + `achats.*`. Les routes API imposent en plus le middleware de rôle `role:purchasing-manager,warehouse-operator,manager,admin`, en complément des policies (`PurchaseOrderPolicy`, `PurchaseOrderLinePolicy`, `SupplierPolicy`).

## Dépendances avec d'autres modules

- **Inventory ↔ Achats (couplage bidirectionnel)** : `Modules\Inventory\Services\ReorderAutomationService` crée directement des `Modules\Achats\Models\PurchaseOrder` quand un produit passe sous son point de commande. En sens inverse, `PoInventoryMapping::syncToInventory()` déclenche l'événement `PurchaseReceiptCompletedForInventory` pour répercuter une réception vers le stock ; `PurchaseOrderLine`, `PurchaseReceiptLine` et `RFQLine` référencent tous `Modules\Inventory\Models\Product`.
- **Validation** : `AchatsServiceProvider` injecte `Modules\Validation\Services\ApprovalRequestService` ; `PurchaseOrderService` et `ApprovalRoutingService` s'appuient sur `Modules\Validation\Models\ApprovalWorkflow` / `ApprovalRule` pour la chaîne d'approbation des bons de commande.
- **Core** : `RecordsActivity` (audit) sur `PurchaseOrder`, `PurchaseReceipt`, `RFQ`, `Supplier`, `SupplierQuote`.
- **Helpdesk** : `PurchaseOrder` utilise le trait `HelpdeskLinkable` (un bon de commande peut ouvrir/lister ses propres tickets), conforme au morph-map enregistré par `HelpdeskServiceProvider` (`purchase_order => Modules\Achats\Models\PurchaseOrder::class`).
- **AI** : `AchatsAiAssistController` utilise `AiContextualAssistantService`.
- Événements du module (`RFQIssued`, `RFQClosed`, `SupplierQuoteReceived/Accepted/Rejected`, `PurchaseOrderReadyForAccounting`, `PurchaseOrderReadyForInvoicing`, `PurchaseReceiptReadyForInventory`) suggèrent des points d'écoute côté Accounting et Inventory, même si ces listeners externes n'ont pas été confirmés dans le code lu.
