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

Toutes les routes sont sous `auth:sanctum` avec garde de rôle `role:purchasing-manager,warehouse-operator,manager,admin`. **Correction (Chantier 10)** : le groupe principal et le groupe `ai/assist` n'imposaient auparavant **aucune** garde `module:Achats` — seul le rôle était vérifié, contrairement à Inventory/Logistics et au propre `routes/web.php` du module (qui l'avait déjà depuis le Chantier 8.5-light) ; un tenant ayant désactivé Achats pouvait quand même atteindre toute l'API. Les deux groupes ont désormais `module:Achats`.

| Méthode | Route | Description |
|---|---|---|
| GET | `purchase-orders`, `purchase-orders/{id}` | Liste/détail bons de commande |
| GET | `purchase-orders/{id}/export/json\|csv`, `.../summary` | Export et résumé d'un bon de commande |
| GET/POST/PUT/DELETE | `purchase-orders/{id}/lines[/{line}]` | Lignes de bon de commande (imbriquées) — CRUD complet depuis le Chantier 10 (voir Contrôleurs) |
| GET | `suppliers`, `suppliers/{id}`, `suppliers/{id}/performance`, `suppliers/{id}/quotes` | Fournisseurs, performance, historique devis |
| GET | `rfqs`, `rfqs/{id}`, `rfqs/{id}/comparison` | RFQ et comparaison des devis reçus |
| GET/POST/PUT/DELETE | `rfqs/{id}/lines[/{line}]` | Lignes RFQ — CRUD complet depuis le Chantier 10 |
| GET/POST | `supplier-quotes`, `supplier-quotes/{id}` | Devis fournisseur — `index()`/`store()` réels depuis le Chantier 10 |
| GET/POST/PUT/DELETE | `purchase-receipts`, `purchase-receipts/{id}` | Réceptions — CRUD complet depuis le Chantier 10 (voir Contrôleurs) |
| GET | `reports/spending`, `reports/pending-receipts`, `reports/overdue-invoices` | Rapports achats (analytiques) |
| POST | `purchase-orders` / PUT / DELETE | Création/mise à jour/suppression de bon de commande (statut brouillon uniquement) ; `lines[]` géré directement dans la requête depuis le Chantier 10 (voir Contrôleurs) |
| POST | `purchase-orders/{id}/submit`, `.../approve`, `.../cancel` | Cycle d'approbation d'un bon de commande |
| POST | `suppliers`, `rfqs`, `rfqs/{id}/issue`, `rfqs/{id}/close` | Gestion fournisseur et cycle de vie RFQ ; `rfqs` POST/PUT gèrent aussi `lines[]` depuis le Chantier 10 |
| POST | `rfqs/{id}/suppliers/{supplier}/quote` | Enregistrement d'un devis fournisseur — réel depuis le Chantier 10 (était un stub) |
| POST | `supplier-quotes/{id}/accept\|reject` | Décision sur un devis |
| POST | `purchase-receipts`, `purchase-orders/{id}/receipts` | Création d'une réception (deux variantes : `purchase_order_id` dans le corps, ou dans l'URL — cette dernière délègue à `storeForOrder()`) |
| POST | `purchase-receipts/{id}/complete`, `.../quality-issue` | Finalisation réception et signalement qualité |
| POST | `v1/achats/ai/assist` | Guidance IA contextuelle (AI Assisted First) — désormais gardée `module:Achats`+`role:` (Chantier 10, voir ci-dessus) |

## Contrôleurs

10 contrôleurs Api (`Modules/Achats/app/Http/Controllers/Api/`) + 3 contrôleurs Web — 13 au total, corrigeant l'hypothèse initiale du projet (« ≤5 contrôleurs ») qui s'est révélée fausse lors du Chantier 8.5-light.

Api : `PurchaseOrderController`, `PurchaseOrderLineController`, `PurchaseOrderExportController`, `SupplierController`, `SupplierQuoteController`, `RFQController`/`RFQLineController`, `PurchaseReceiptController`, `ThreeWayMatchController`, `PurchaseReportsController`, `AchatsAiAssistController`.

Web : `PurchaseOrderController`, `SupplierController`, `RFQController` (index/create/show/edit — pages modales-CRUD).

**Corrections RBAC (Chantier 8.5-light)** : `PurchaseOrderPolicy` était un no-op — chaque capacité, y compris `approve`/`reject`, retournait inconditionnellement `true` (commentaire de code admettant que ça n'existait que pour satisfaire des tests sans rôle). Corrigé pour vérifier les vraies permissions `achats.purchase-order.approve`/`.reject` (confirmé dans le code — `approve()`/`reject()` appellent bien `$user->can('achats.purchase-order.approve'|'.reject')`) ; `view`/`create`/`update` restent permissifs pour matcher les tests existants. `SupplierPolicy`/`PurchaseOrderLinePolicy` ont été enregistrées auprès du Gate (elles ne l'étaient pas) et leurs appels `authorize()` ajoutés. `routes/web.php` a reçu le même verrou `module:`+`role:` que `routes/api.php`.

**Sous-système mort supprimé** : `PurchaseApprovalChainService`/`PurchaseOrderApproval`(+factory)/`PurchaseApprovalController` (+ ses 2 tests isolés) formaient un sous-système d'approbation de bon de commande parallèle, jamais routé, avec ses propres seuils codés en dur (5000/10000), redondant avec le vrai flux d'approbation basé sur le moteur Validation (`ApprovalRoutingResolver`) — supprimé intégralement (confirmé : aucun fichier `PurchaseApproval*` ne subsiste dans le code du module).

## Chantier 10 (re-vérification) — le module le plus dégradé des trois

Une re-vérification complète a trouvé **4 contrôleurs entièrement ou partiellement stubbés** (`// Implementation to follow`) malgré des routes actives, un **bug de perte de données silencieuse** sur les deux flux de création principaux, et **2 routes mortes** (méthodes inexistantes) — le tout invisible au Chantier 8.5-light, qui avait corrigé le RBAC de ce module sans auditer si les endpoints faisaient réellement quelque chose.

- **Bug de perte de données silencieuse (trouvaille principale)** : `PurchaseOrders/Form.vue` et `RFQs/Form.vue` soumettent tout le formulaire (champs d'en-tête + un tableau `lines[]`) en une seule requête, mais `PurchaseOrderController::store()`/`update()` et `RFQController::store()`/`update()` ignoraient complètement `lines[]` (ni dans les règles de validation, ni lu par le service) — chaque bon de commande ou RFQ créé via l'interface réelle se retrouvait avec zéro ligne, silencieusement (réponse 201/200 « réussie »). Corrigé en s'appuyant sur les méthodes de service réelles et déjà testées mais jamais utilisées par ces contrôleurs (`PurchaseOrderService::addLineItem()`, `RFQService::addLineToRFQ()`) : `store()` ajoute chaque ligne après création de l'en-tête ; `update()` remplace intégralement le jeu de lignes (suppression puis recréation) faute de suivi d'id par ligne côté frontend entre deux éditions. `PurchaseOrderResource` exposait uniquement `lines_count`, jamais le tableau `lines[]` réel — corrigé pour que l'écran d'édition puisse se pré-remplir (sans quoi la nouvelle logique de remplacement aurait silencieusement vidé les lignes existantes à chaque édition n'y touchant pas). Le sélecteur de fournisseurs du formulaire de création RFQ (`suppliers[]`) reste volontairement non câblé — inviter des fournisseurs est une action UI distincte et déjà réelle (bouton « Issue » → `POST rfqs/{id}/issue`) ; auto-émettre à la création serait une nouvelle règle métier, documentée comme gap plutôt qu'inventée.
- **`RFQController::index()`** retournait un paginator brut sans enveloppe `meta` — même classe de bug déjà corrigée une fois ce chantier pour `Warehouses/Index.vue` (Chantier 8.3il), non détectée côté Achats jusqu'ici : `RFQs/Index.vue` lit `data.meta.*`, donc la pagination ne s'affichait jamais. Corrigé.
- **`SupplierQuoteController::index()`/`store()`** étaient des stubs littéraux — câblés sur `RFQService::recordSupplierQuote()`, déjà réelle et testée (utilisée en interne par `issueRFQ()`) ; `show()` n'appelait `authorize()` nulle part contrairement à `accept()`/`reject()` — corrigé.
- **`RFQLineController::store()`/`update()`/`destroy()`** étaient des stubs — câblés sur `RFQService::addLineToRFQ()`/`removeLineFromRFQ()` (déjà réelles) ; `update()` (sans méthode de service dédiée) applique directement la même règle « RFQ en brouillon uniquement » que le service impose ailleurs.
- **`PurchaseOrderLineController::store()`/`update()`/`destroy()`** étaient des stubs malgré des appels `authorize()` corrects — reconstruits entièrement sur `PurchaseOrderService::addLineItem()` (création) et la même règle « bon de commande en brouillon » appliquée directement en modification/suppression. Confirmé : ce chemin REST imbriqué (`purchase-orders/{po}/lines`) est un point d'entrée API distinct et légitime (principe API First) du flux `lines[]` intégré au corps de `PurchaseOrderController::store()`/`update()` ci-dessus — les deux s'appuient sur le même service, donc aucun risque de divergence de règle métier.
- **`PurchaseReceiptController` — reconstruction complète** : `index()`/`store()`/`recordQualityIssue()` étaient des stubs littéraux, et `update()`/`destroy()` étaient routées contre des méthodes qui n'existaient pas du tout sur la classe (erreur fatale garantie sur chaque `PUT`/`DELETE`) — alors que `PurchaseReceipts/{Index,Form,Show}.vue` sont de vraies pages, déjà construites, qui appellent tout cela en `fetch()` direct. Reconstruit entièrement sur les méthodes réelles déjà existantes de `PurchaseReceiptService` (`createReceipt`/`addReceiptLine`/`markLineAsReceived`/`recordQualityIssue`/`completeReceipt`), avec une nouvelle `PurchaseReceiptResource` qui traduit les noms de colonnes réels du modèle (`variance_qty`, `purchaseOrderLine`) vers ceux attendus par le frontend (`variance`, `po_line`) — pure traduction de forme, pas de nouvelle règle métier. Une migration additive (`achats_purchase_receipt_lines`) a comblé un vrai trou de schéma : la table stub issue du scaffold fourre-tout n'avait ni `purchase_order_line_id` (elle avait `order_line_id`, un nom différent), ni `product_id`/`quality_status`/`variance_qty`/`notes` — un `QueryException` garanti dès la première écriture réelle de ligne de réception. **Gap documenté, non inventé** : le frontend (`Show.vue`) attend une liste `quality_issues[]` avec son propre workflow de statut (open/investigating/resolved) — aucune entité de ce type n'existe dans le modèle de données réel (`recordQualityIssue()` ne fait que modifier `quality_status`/`notes` de la ligne concernée) ; les signalements qualité soumis sont bien enregistrés via ce mécanisme réel, mais la réponse ne peuple jamais `quality_issues[]` — la section correspondante de `Show.vue` ne s'affiche simplement jamais, dégradation cohérente avec le principe « fallback-first » déjà appliqué ailleurs dans ce dépôt plutôt qu'une invention silencieuse de workflow.
- **Bug de méthode HTTP, actif sur 4 formulaires réels** : `PurchaseOrders/Form.vue`, `RFQs/Form.vue`, `Suppliers/Form.vue` et `PurchaseReceipts/Form.vue` envoient tous `PATCH` en mode édition, mais les 4 routes correspondantes (`purchase-orders/{id}`, `rfqs/{id}`, `suppliers/{id}`, `purchase-receipts/{id}`) n'enregistraient que `PUT` — une erreur 405 systématique sur chaque soumission d'édition réelle, jamais détectée car aucun test existant n'exerçait le chemin d'édition. Corrigé avec `Route::match(['put', 'patch'], ...)` sur les 4 routes.
- **2 routes web mortes / navigation cassée réparées** : `RFQs/Show.vue`/`Index.vue` avaient un vrai bouton « Comparer » (`window.location.href = '/rfqs/{id}/compare'`) sans route `routes/web.php` correspondante (404 systématique) — ajoutée (`Achats/RFQs/Compare.vue`, page auto-suffisante existante). Cette même page appelait deux endpoints API fictifs (`rfqs/{id}/accept-quote`/`reject-quote`) — repointés vers les vrais endpoints déjà fonctionnels `supplier-quotes/{id}/accept\|reject`. `PurchaseReceipts/{Index,Form,Show}.vue` n'avaient elle-même aucune route web du tout — ajoutées (`Web\PurchaseReceiptController`, nouveau, même patron que les autres contrôleurs Web du module).
- **Audit `$fillable` vs schéma réel** : `RFQ`, `PurchaseOrder`, `Supplier`, `PurchaseOrderLine` confirmés alignés (`achats_rfqs`, `achats_purchase_orders`, `achats_suppliers`, `achats_purchase_order_lines`) ; `PurchaseReceiptLine` ne l'était pas (voir migration additive ci-dessus).
- **Gap déjà documenté re-confirmé, non retouché** : `ThreeWayMatchController` a toujours zéro route (voir `PurchaseIntegrationService` dans Services ci-dessus) — hors périmètre de ce chantier de correction de bugs, reste un chantier de câblage à part entière.

## Vues (Vue/Inertia)

`Modules/Achats/resources/js/Pages/` : `PurchaseOrders/`, `Suppliers/`, `RFQs/` (une page `RFQ/Index.vue` au singulier, dupliquée et masquée par la vraie `RFQs/Index.vue` au pluriel, a été supprimée au Chantier 8.5-light), `PurchaseReceipts/`, `SpendAnalytics/Index.vue` (page réelle, backend désormais complet — `spending()`/`overdueInvoices()` de `PurchaseReportsController` étaient de purs stubs `// Implementation to follow`, construits pour de vrai lors de ce chantier ; la section « Anomalies IA » fabriquée de la page a été retirée plutôt qu'un moteur de détection factice inventé), `SupplierRisk/Index.vue` (page 100 % maquette statique — aucun `defineProps`/appel réseau — laissée non câblée, même traitement que `Logistics/AIRiskMonitor.vue`).

`SpendAnalytics/Index.vue` n'est accessible que par URL directe (`/purchase-orders/spend-analytics` via une closure `Inertia::render()`, aucun lien de navigation), même schéma de découvrabilité que `consolidation-hierarchies` côté Accounting.

## Services

- **`PurchaseOrderService`** — création/mise à jour de bon de commande (modifiable en brouillon uniquement), génération du numéro `PO-{YYYY}-{MM}-{SEQUENCE}` (`config/achats.php`), soumission à l'approbation via `Modules\Validation\Services\ApprovalRequestService` et `ApprovalWorkflow` (`module_name = 'Achats'`), déclenche les événements `PurchaseOrderSubmittedForApproval`, `PurchaseOrderApproved`, `PurchaseOrderReceived`, `PurchaseOrderInvoiced`, `PurchaseOrderCancelled`.
- **`ApprovalRoutingService`** — routage des demandes d'approbation selon des règles (`Modules\Validation\Models\ApprovalRule`, `ApprovalWorkflow`) — typiquement des seuils de montant. (Le `PurchaseApprovalChainService` parallèle, redondant, a été supprimé au Chantier 8.5-light — voir Contrôleurs.)
- **`PurchaseReceiptService`** — création de réception (délègue à `PurchaseOrderService::markAsReceived`), enregistrement des quantités reçues et écarts par ligne, signalement d'un problème qualité (événement `QualityIssueRecorded`), finalisation de la réception (événement `PurchaseReceiptCompleted`), calcul des écarts quantité/valeur et du rapport de réception.
- **`RFQService`** — cycle de vie d'une demande de prix (émission, clôture, comparaison des devis).
- **`SupplierService`** — gestion des fournisseurs et de leurs métriques de performance.
- **`ThreeWayMatchService`** — rapprochement bon de commande / réception / facture (`PurchaseInvoiceMatch`).
- **`BulkPurchaseOrderService`** — création de bons de commande en masse.
- **`PurchaseOrderExportService`** — export JSON/CSV et résumé d'un bon de commande.
- **`PurchaseIntegrationService`** — point d'intégration croisée avec les autres modules (comptabilité, budget, stock) : 8 méthodes réelles (synchro PO↔Accounting/Inventory, allocation budgétaire, génération de facture), mais zéro contrôleur consommateur et 3 modèles dépendants sur des tables stub non patchées — signalé au Chantier 8.5-light comme gap documenté plutôt que construit (chantier de câblage à part entière, même précédent que les contrôleurs orphelins d'Accounting 8.1b).

## Permissions RBAC

Préfixe `achats.` (`database/seeders/RolesAndPermissionsSeeder.php`), ressources `rfq, purchase-order, purchase-receipt, supplier, purchaseorderline` × actions `view-any, view, create, update, delete` (`purchaseorderline` ajoutée au Chantier 8.5-light pour que `PurchaseOrderLinePolicy` soit couverte par la boucle générique). Un bloc `ACHATS_EXTRA_PERMISSIONS` seed en plus `achats.purchase-order.approve`/`.reject` — les deux verbes non-CRUD que `PurchaseOrderPolicy::approve()`/`reject()` vérifient réellement depuis leur correction (voir Contrôleurs).

Rôle dédié : `purchasing-manager` — accès complet `inventory.*` + `achats.*`. Les routes API et Web imposent en plus le middleware de rôle `role:purchasing-manager,warehouse-operator,manager,admin`, en complément des policies (`PurchaseOrderPolicy`, `PurchaseOrderLinePolicy`, `SupplierPolicy` — les deux dernières nouvellement enregistrées auprès du Gate au Chantier 8.5-light).

## Dépendances avec d'autres modules

- **Inventory ↔ Achats (couplage bidirectionnel)** : `Modules\Inventory\Services\ReorderAutomationService` crée directement des `Modules\Achats\Models\PurchaseOrder` quand un produit passe sous son point de commande. En sens inverse, `PoInventoryMapping::syncToInventory()` déclenche l'événement `PurchaseReceiptCompletedForInventory` pour répercuter une réception vers le stock ; `PurchaseOrderLine`, `PurchaseReceiptLine` et `RFQLine` référencent tous `Modules\Inventory\Models\Product`.
- **Validation** : `AchatsServiceProvider` injecte `Modules\Validation\Services\ApprovalRequestService` ; `PurchaseOrderService` et `ApprovalRoutingService` s'appuient sur `Modules\Validation\Models\ApprovalWorkflow` / `ApprovalRule` pour la chaîne d'approbation des bons de commande.
- **Core** : `RecordsActivity` (audit) sur `PurchaseOrder`, `PurchaseReceipt`, `RFQ`, `Supplier`, `SupplierQuote`.
- **Helpdesk** : `PurchaseOrder` utilise le trait `HelpdeskLinkable` (un bon de commande peut ouvrir/lister ses propres tickets), conforme au morph-map enregistré par `HelpdeskServiceProvider` (`purchase_order => Modules\Achats\Models\PurchaseOrder::class`).
- **AI** : `AchatsAiAssistController` utilise `AiContextualAssistantService`.
- Événements du module (`RFQIssued`, `RFQClosed`, `SupplierQuoteReceived/Accepted/Rejected`, `PurchaseOrderReadyForAccounting`, `PurchaseOrderReadyForInvoicing`, `PurchaseReceiptReadyForInventory`) suggèrent des points d'écoute côté Accounting et Inventory, même si ces listeners externes n'ont pas été confirmés dans le code lu.
