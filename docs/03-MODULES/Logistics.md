# Logistics

## Rôle

Le module Logistics est le TMS (Transport Management System) de l'ERP : expéditions, transporteurs et grilles tarifaires, optimisation de tournées (VRP), livraison du dernier kilomètre, facturation fret, dédouanement Afrique (droits OHADA, nomenclature SH), gestion des emplacements d'entrepôt (zones, règles de rangement) et visibilité de suivi maritime/aérien. Il forme, avec Inventory et Achats, le pôle **Stock et Logistique** de life-mdg-erp.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Shipment` | `logistics_shipments` | Expédition (TMS), liée à un `HelpdeskLinkable` |
| `ShipmentLine`, `ShipmentPackage`, `ShipmentTrackingEvent` / `TrackingEvent` / `LgxTrackingEvent` | — | Lignes, colis et évènements de suivi d'une expédition |
| `Carrier` / `LgxCarrier` | — | Transporteur (dont transporteurs africains : SenPost, CamPost, DHL, Chronopost, Bolloré) |
| `CarrierRate`, `CarrierRateCard` | — | Grilles tarifaires transporteur |
| `DeliveryRound`, `DeliveryStop` | — | Tournée de livraison dernier kilomètre et arrêts (preuve de livraison) |
| `Route` / `LogisticsRoute`, `RouteStop` | — | Itinéraire optimisé (VRP, plus proche voisin, Haversine) |
| `CustomsDeclaration`, `CustomsItem` | — | Déclaration douanière et lignes associées |
| `HsCode` | — | Nomenclature SH (codes douaniers) |
| `FreightInvoice` | — | Facture fret transporteur (approbation/contestation) |
| `Warehouse`, `WarehouseZone`, `WarehouseLocation`, `WarehouseMovement` | `wh_warehouses` | Entrepôt multi-sites, zones et emplacements (distinct du `Warehouse` d'Inventory) |
| `PutawayRule` / `WarehousePutAwayRule` | — | Règles de rangement (FEFO/FIFO) |
| `Vehicle` | — | Véhicule de livraison |
| `Location` | — | Emplacement géographique générique |
| `LgxShipment`, `LgxShipmentItem` | — | Modèles d'expédition liés aux connecteurs de visibilité (Flexport, MarineTraffic, FlightAware) |

## Endpoints principaux

Tous sous préfixe `v1`, `auth:sanctum`, avec garde de rôle `role:logistics-manager,warehouse-operator,manager,admin` (sauf routes IA/HS codes/visibilité qui n'imposent que `auth:sanctum`).

| Méthode | Route | Description |
|---|---|---|
| CRUD | `logistics/shipments` | Expéditions (TMS) |
| POST | `logistics/shipments/{id}/book\|dispatch\|deliver\|cancel` | Cycle de vie d'une expédition |
| GET | `logistics/shipments/{id}/tracking` | Historique de suivi |
| POST | `logistics/shipments/{id}/tracking-events` | Ajout d'un évènement de suivi |
| CRUD | `logistics/carriers` | Transporteurs |
| GET | `logistics/carriers/{id}/performance`, POST `logistics/carriers/select` | Performance et sélection de transporteur |
| CRUD | `logistics/carrier-rates`, POST `logistics/carrier-rates/estimate` | Grilles tarifaires et estimation |
| POST | `logistics/routes/optimize`, GET `logistics/routes/optimize/{jobId}/result` | Optimiseur de tournées VRP |
| CRUD | `logistics/routes` | Itinéraires |
| CRUD | `logistics/delivery-rounds` | Tournées de livraison |
| POST | `logistics/delivery-rounds/{id}/start\|complete\|optimize`, `.../stops/{stop}/pod` | Cycle de vie tournée + preuve de livraison |
| CRUD | `logistics/freight-invoices` | Factures fret |
| POST | `logistics/freight-invoices/{id}/approve\|dispute` | Approbation/contestation facture fret |
| CRUD | `logistics/customs-declarations`, POST `.../submit` | Déclarations douanières |
| GET | `logistics/locations/hierarchy`, CRUD `logistics/locations` | Hiérarchie des emplacements entrepôt |
| CRUD | `logistics/putaway-rules` | Règles de rangement |
| GET | `logistics/analytics/kpis`, `carrier-performance`, `shipment-stats`, `co2` | Analytique logistique (dont émissions CO2) |
| GET | `logistics/hs-codes/search`, `logistics/hs-codes/chapters`, `logistics/hs-codes/{code}` | Nomenclature SH (lecture seule) |
| GET | `logistics/shipments/{id}/visibility`, `tracking-events`, POST `refresh-tracking` | Agrégateur de visibilité maritime/aérien |
| POST | `v1/logistics/ai/assist` | Guidance IA contextuelle (AI Assisted First) |

## Services

- **`ShipmentService`** — cycle de vie d'une expédition (réservation, expédition, livraison, annulation).
- **`CarrierIntegrationService` / `CarrierSelectionService`** — intégration transporteur et sélection du meilleur transporteur selon coût/délai.
- **`RouteOptimizationService` / `RouteOptimizerService`** — optimisation de tournées (algorithme du plus proche voisin, distance Haversine, VRP 2-opt).
- **`FreightBillingService`** — facturation fret et rapprochement.
- **`CustomsService`** — calcul des droits de douane (règles OHADA), gestion des déclarations.
- **`LogisticsAnalyticsService`** — KPI transporteurs, statistiques d'expédition, émissions CO2.
- **`WarehouseService`** — gestion des zones/emplacements d'entrepôt et règles de rangement.
- **`LocationPrivacyService`** — anonymisation/protection des données de géolocalisation.
- **`WebhookSecurityService`** — vérification de signature des webhooks entrants (transporteurs, connecteurs).
- **`ShipmentVisibilityService`** avec connecteurs dédiés (`MarineTrafficConnector`, `FlightAwareConnector`, `FlexportConnector`, `FallbackTrackingConnector`) — agrégation de la visibilité de suivi maritime/aérien, avec repli si aucun connecteur externe n'est disponible.
- **`LgxShipmentService`** — services liés aux expéditions du sous-système "LGX" (connecteurs de visibilité).

## Permissions RBAC

Préfixe `logistics.` (`database/seeders/RolesAndPermissionsSeeder.php`), ressources `shipment, route, carrier, customs-declaration` × actions `view-any, view, create, update, delete`. Rôle dédié : `logistics-manager` (accès complet `inventory.*` + `logistics.*`). Les routes API imposent en plus un contrôle de rôle direct (`role:logistics-manager,warehouse-operator,manager,admin`) via middleware, en complément des policies (`CarrierPolicy`, `DeliveryRoundPolicy`, `ShipmentPolicy`).

## Dépendances avec d'autres modules

- **Core** : `RecordsActivity` (audit) sur la plupart des modèles.
- **Helpdesk** : `Modules\Logistics\Models\Shipment` utilise le trait `HelpdeskLinkable` — une expédition peut ouvrir/lister ses propres tickets, conforme au couplage Helpdesk décrit dans le CLAUDE.md du projet, et `HelpdeskServiceProvider` enregistre `shipment => Modules\Logistics\Models\Shipment::class` dans son morph-map d'allowlist.
- **AI** : `LogisticsAiAssistController` utilise `AiContextualAssistantService`.
- Aucune dépendance directe vers Achats ou Inventory n'a été trouvée dans le code (`use Modules\Achats\...` / `use Modules\Inventory\...` absents des services/contrôleurs de Logistics) — le couplage avec le stock se fait plutôt côté Inventory (via son propre `ShippingService`/`Shipment`) que depuis Logistics.

## Particularités du périmètre life-mdg-erp

Le middleware `module:Logistics` appliqué aux routes suppose l'existence d'un mécanisme de toggle de module par tenant (`admin.modules.toggle` dans les permissions admin) — cohérent avec le principe énoncé dans le CLAUDE.md racine selon lequel retirer un module se fait par suppression du dossier et de l'entrée dans `config/modules_statuses.json`, sans câblage de routes manuel.
