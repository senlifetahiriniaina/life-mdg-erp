# Logistics

## Rôle

Le module Logistics est le TMS (Transport Management System) de l'ERP : expéditions, transporteurs et grilles tarifaires, optimisation de tournées (VRP), livraison du dernier kilomètre, facturation fret, dédouanement Afrique (droits OHADA, nomenclature SH), gestion des emplacements d'entrepôt (zones, règles de rangement) et visibilité de suivi maritime/aérien. Il forme, avec Inventory et Achats, le pôle **Stock et Logistique** de life-mdg-erp.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Shipment` | `logistics_shipments` | Expédition (TMS), liée à un `HelpdeskLinkable` |
| `ShipmentLine`, `ShipmentPackage`, `ShipmentTrackingEvent` / `TrackingEvent` | — | Lignes, colis et évènements de suivi d'une expédition |
| `Carrier` | — | Transporteur (dont transporteurs africains : SenPost, CamPost, DHL, Chronopost, Bolloré) |
| `CarrierRate`, `CarrierRateCard` | — | Grilles tarifaires transporteur |
| `DeliveryRound`, `DeliveryStop` | — | Tournée de livraison dernier kilomètre et arrêts (preuve de livraison) |
| `LogisticsRoute`, `RouteStop`, `DeliveryRoute` | — | Itinéraire optimisé (VRP, plus proche voisin, Haversine) |
| `CustomsDeclaration` | — | Déclaration douanière |
| `HsCode` | — | Nomenclature SH (codes douaniers) |
| `FreightInvoice` | — | Facture fret transporteur (approbation/contestation) |
| `PutawayRule` | — | Règles de rangement (FEFO/FIFO) |
| `Vehicle` | — | Véhicule de livraison |
| `Location` | — | Emplacement géographique générique |
| `CarrierRateCard` | — | Grille tarifaire transporteur (table `lgx_carrier_rate_cards`) |

## Endpoints principaux

Tous sous préfixe `v1`, `auth:sanctum`, avec garde de rôle `role:logistics-manager,warehouse-operator,manager,admin`. **Correction (Chantier 10)** : les groupes `ai/assist` et l'agrégateur de visibilité maritime/aérien (`shipments/{id}/visibility`, `refresh-tracking`, `tracking-events`) n'imposaient auparavant que `auth:sanctum` — n'importe quel utilisateur authentifié de n'importe quel module/rôle pouvait les atteindre ; alignés sur le même verrou `module:Logistics`+`role:` que le reste du module. HS codes reste volontairement en lecture seule sans garde de rôle (nomenclature de référence, `module:Logistics` seul).

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

## Contrôleurs

15 contrôleurs Api (`Modules/Logistics/app/Http/Controllers/Api/`) + 1 contrôleur Web (`LogisticsWebController`).

Api : `ShipmentController`, `TrackingEventController`, `ShipmentVisibilityController`, `CarrierController`/`CarrierRateController`, `RouteController`/`RouteOptimizationController`, `DeliveryRoundController`, `FreightInvoiceController`, `CustomsDeclarationController`/`CustomsRouteController`, `HsCodeController` (nomenclature SH, lecture seule), `LocationController`, `PutawayRuleController`, `LogisticsAnalyticsController`, `LogisticsAiAssistController`.

**Corrections RBAC (Chantier 8.3)** : `CarrierPolicy`/`ShipmentPolicy`/`DeliveryRoundPolicy` existaient mais n'étaient jamais invoquées par `CarrierController`/`ShipmentController`/`DeliveryRoundController` ni enregistrées auprès du Gate — corrigé via `LogisticsServiceProvider::registerPolicies()` (les policies namespacées `Modules\*` ne s'auto-découvrent pas) et l'ajout des appels `authorize()` manquants, plus un bloc `LOGISTICS_EXTRA_PERMISSIONS` (8 permissions `logistics.deliveryround.*`).

**Bug de cycle de vie corrigé** : `ShipmentController::book()`/`dispatch()`/`deliver()`/`cancel()` avaient `ShipmentService` injecté mais ne l'appelaient jamais — ils modifiaient `status` directement, si bien que `booked_at`/`picked_up_at`/l'historique de suivi n'étaient jamais écrits ; `dispatch()` utilisait en plus le mauvais vocabulaire (`'dispatched'`, qui appartient au modèle mort `LgxShipment` — le vrai vocabulaire est `'picked_up'`). Les 4 méthodes délèguent désormais réellement à `ShipmentService`.

**Sous-système mort supprimé (Chantier 8.3)** : l'ensemble parallèle `wh_*`/`lgx_*` — `WarehouseShipmentController`, `WarehouseService`, `LgxShipmentService`, et les modèles `Warehouse`/`WarehouseZone`/`WarehouseMovement`/`WarehouseLocation`/`WarehousePutAwayRule`/`LgxShipment`/`LgxCarrier`/`LgxTrackingEvent`/`LgxShipmentItem` — entrait en collision d'URL avec le vrai `ShipmentController`/`CarrierController` déjà routé et a été supprimé intégralement (confirmé : aucun de ces modèles ne subsiste dans `Modules/Logistics/app/Models`). `Route.php` (doublon octet-pour-octet de `LogisticsRoute`) et `CustomsItem.php` (orphelin, table jamais migrée) ont également été supprimés.

## Vues (Vue/Inertia)

- **Racine** (`resources/js/Pages/Logistics/`) : `Analytics/`, `Carriers/`, `Customs/`, `DeliveryRounds/`, `FreightInvoices/`, `Shipments/` — versions réelles, servies par `LogisticsWebController`, qui font de vrais appels `fetch()` vers les vraies API.
- **Module** (`Modules/Logistics/resources/js/Pages/`) : `Dashboard/`, `RouteOptimization/Index.vue` (page auto-suffisante, routée par closure `Inertia::render()` au Chantier 8.3), `Returns/Index.vue` et `AIRiskMonitor/Index.vue` — ces deux dernières sont **100 % maquettes statiques** (aucun `defineProps`, aucun appel réseau, aucun modèle/service/table derrière) et restent volontairement non routées : les câbler impliquerait d'inventer un moteur de scoring de risque IA et un sous-système RMA complets, hors mandat du chantier de re-câblage (« construire du réel, ne pas inventer de logique métier »).

6 pages qui existaient en double côté module (`Analytics`, `Carriers`, `Customs`, `DeliveryRounds`, `FreightInvoices`, `Shipments`) ont été confirmées mortes et supprimées — les copies racine sont celles réellement servies par `LogisticsWebController`, `app.js`'s `resolve()` donnant la priorité à `./Pages/` avant `../../Modules/*/resources/js/Pages/`.

## Services

- **`ShipmentService`** — cycle de vie d'une expédition (réservation, expédition, livraison, annulation).
- **`CarrierIntegrationService` / `CarrierSelectionService`** — intégration transporteur et sélection du meilleur transporteur selon coût/délai.
- **`RouteOptimizationService` / `RouteOptimizerService`** — optimisation de tournées (algorithme du plus proche voisin, distance Haversine, VRP 2-opt).
- **`FreightBillingService`** — facturation fret et rapprochement.
- **`CustomsService`** — calcul des droits de douane (règles OHADA), gestion des déclarations.
- **`LogisticsAnalyticsService`** — KPI transporteurs, statistiques d'expédition, émissions CO2.
- **`PutawayRuleController`** contourne en réalité le modèle Eloquent `PutawayRule` et écrit via `DB::table()` brut, sur un jeu de champs distinct du modèle (confirmé au Chantier 8.3 : `product_category`/`carrier_id`/`transport_mode`/`requires_cold_chain`/`has_hazmat`, pas les colonnes `$fillable` inutilisées du modèle) — les règles de rangement d'entrepôt passent donc par ce contrôleur, pas par un `WarehouseService` dédié (le service du même nom, qui appartenait au sous-système mort `wh_*`, a été supprimé).
- **`LocationPrivacyService`** — anonymisation/protection des données de géolocalisation.
- **`WebhookSecurityService`** — vérification de signature des webhooks entrants (transporteurs, connecteurs).
- **`ShipmentVisibilityService`** avec connecteurs dédiés (`MarineTrafficConnector`, `FlightAwareConnector`, `FlexportConnector`, `FallbackTrackingConnector`) — agrégation de la visibilité de suivi maritime/aérien, avec repli si aucun connecteur externe n'est disponible.

## Permissions RBAC

Préfixe `logistics.` (`database/seeders/RolesAndPermissionsSeeder.php`), ressources `shipment, route, carrier, customs-declaration` × actions `view-any, view, create, update, delete`. Rôle dédié : `logistics-manager` (accès complet `inventory.*` + `logistics.*`). Les routes API imposent en plus un contrôle de rôle direct (`role:logistics-manager,warehouse-operator,manager,admin`) via middleware, en complément des policies (`CarrierPolicy`, `DeliveryRoundPolicy`, `ShipmentPolicy`).

## Dépendances avec d'autres modules

- **Core** : `RecordsActivity` (audit) sur la plupart des modèles.
- **Helpdesk** : `Modules\Logistics\Models\Shipment` utilise le trait `HelpdeskLinkable` — une expédition peut ouvrir/lister ses propres tickets, conforme au couplage Helpdesk décrit dans le CLAUDE.md du projet, et `HelpdeskServiceProvider` enregistre `shipment => Modules\Logistics\Models\Shipment::class` dans son morph-map d'allowlist.
- **AI** : `LogisticsAiAssistController` utilise `AiContextualAssistantService`.
- Aucune dépendance directe vers Achats ou Inventory n'a été trouvée dans le code (`use Modules\Achats\...` / `use Modules\Inventory\...` absents des services/contrôleurs de Logistics) — le couplage avec le stock se fait plutôt côté Inventory (via son propre `ShippingService`/`Shipment`) que depuis Logistics.

## Particularités du périmètre life-mdg-erp

Le middleware `module:Logistics` appliqué aux routes suppose l'existence d'un mécanisme de toggle de module par tenant (`admin.modules.toggle` dans les permissions admin) — cohérent avec le principe énoncé dans le CLAUDE.md racine selon lequel retirer un module se fait par suppression du dossier et de l'entrée dans `config/modules_statuses.json`, sans câblage de routes manuel.

## Chantier 10 (re-vérification)

Module le plus propre des trois de ce chantier : re-audit complet route → contrôleur → modèle → API → vue → RBAC, aucun contrôleur stub trouvé (aucune occurrence de « Implementation to follow » nulle part dans `app/Http/Controllers`), aucune route morte (chaque méthode routée existe réellement), et les 3 fichiers de `app/Policies/` correspondent exactement aux 3 `Gate::policy()` déjà enregistrées depuis le Chantier 8.3 (pas de policy orpheline). Seule trouvaille : les deux groupes de routes `ai/assist`/visibilité (voir Endpoints principaux, corrigé). `$fillable` de `CarrierRate`, `LogisticsRoute`, `FreightInvoice`, `CustomsDeclaration`, `Location` confirmés alignés avec les colonnes réellement migrées ; `PutawayRule::$fillable` reste divergent du schéma réel mais c'est un fait déjà documenté et sans impact — le contrôleur contourne le modèle Eloquent entièrement via `DB::table()` brut (voir Services ci-dessus).
