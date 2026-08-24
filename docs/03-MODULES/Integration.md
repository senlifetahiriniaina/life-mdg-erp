# Integration

## Rôle

Le module `Integration` est le hub des connecteurs externes de life-mdg-erp : paiement mobile africain (Orange Money, Wave, MTN MoMo, M-Pesa), e-commerce (Shopify, WooCommerce, Jumia), outils métiers (Google Workspace, Zapier), plus deux services d'infrastructure (Supabase, Firebase). Il porte aussi un sous-système distinct, le **WideHalo Bridge (WHB)** — un protocole de fédération pour échanger des données entre deux instances ERP (locale ↔ distante), avec invitations, permissions par type de donnée et signature HMAC des échanges. C'est le point d'entrée « Africa First » pour le paiement mobile.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Integration` | `integrations` | Une ligne par tenant × intégration externe active (statut connecté/déconnecté/erreur, credentials chiffrés+masqués). Contrainte unique **par tenant** (`[tenant_id, integration_key]`, corrigée Chantier 32.6 — voir plus bas). |
| `IntegrationConnector` | `integration_connectors` | Connecteur webhook générique configuré (webhook/OAuth2/API key/basic_auth/custom), avec `config` chiffré (`encrypted:array`) et audit (`HasAuditLog`). |
| `SyncLog` / `IntegrationSyncLog` | `integration_sync_logs` | Deux modèles Eloquent pointent sur la **même table** `integration_sync_logs` : `SyncLog` (avec `connector_id`, audité, utilisé par `IntegrationService`/`IntegrationController`) et `IntegrationSyncLog` (avec `integration_id`, sans timestamps, utilisé par `IntegrationManager`). |
| `WebhookEndpoint` | `integration_webhook_endpoints` | Endpoint sortant rattaché à un connecteur (URL, méthode, secret, tentatives de retry). `secret_key` masqué (`$hidden`) depuis Chantier 32.6. |
| `WhbConnection` | `whb_connections` | Connexion WHB entre le tenant local et un tenant distant : code d'invitation, secret partagé, jeton de session, statut. |
| `WhbExchange` | `whb_exchanges` | Un échange de données individuel dans une `WhbConnection` (direction, type de donnée, payload JSON — désormais un vrai payload métier, plus un gabarit vide, voir Chantier 32.6). |
| `WhbPermission` | `whb_permissions` | Permissions par type de donnée pour une connexion WHB (`can_receive`, `can_send`, `auto_accept`). |

## Endpoints principaux

Toutes les routes sont sous `auth:sanctum` + `module:Integration` (bascule métier ON/OFF par tenant — voir Permissions RBAC), à l'exception de `/.well-known/widehalo` (public) et `v1/federation/*` (protégé par signature HMAC, pas par session).

**Connecteurs webhook génériques et intégrations externes** (`prefix: v1/integration`)
| Méthode | Route | Description |
|---|---|---|
| GET | `connectors` | Liste des connecteurs du tenant |
| POST | `connectors` | Créer un connecteur |
| GET | `connectors/{connector}` | Détail d'un connecteur (webhooks associés, `secret_key` jamais exposé) |
| POST | `connectors/{connector}/activate` | Activer un connecteur |
| POST | `connectors/{connector}/webhook` | Ajouter un endpoint webhook |
| POST | `connectors/{connector}/dispatch` | Déclencher un envoi sortant (tous les endpoints actifs en parallèle depuis Chantier 32.6, voir Performance) |
| GET | `connectors/{connector}/logs` | Historique des synchronisations |
| DELETE | `connectors/{connector}` | Supprimer (soft-delete) un connecteur — **nouveau Chantier 32.6**, route absente auparavant malgré le bouton réel de `IntegrationsIndex.vue` |
| GET | `stats` | Statistiques d'intégration du tenant |
| GET/POST | `supabase/status`, `supabase/test` | Statut et test de la connexion Supabase — gaté `role:employee,manager,finance-manager,system-admin,tenant-admin,admin,super-admin` depuis Chantier 32.6 |
| GET/POST | `firebase/status`, `firebase/test-push` | Statut et test push Firebase — même gate de rôle |
| GET | `external` | **Nouveau Chantier 32.6** — registre `IntegrationManager` (9 intégrations) + statut par tenant |
| POST | `external/{key}/connect` \| `/disconnect` \| `/test` \| `/sync` | **Nouveau Chantier 32.6** — cycle de vie d'une intégration mobile-money/e-commerce/outil métier |
| POST | `ai/assist` | Guidance IA contextuelle (`IntegrationAiAssistController`) |

**WideHalo Bridge** (`prefix: v1/whb`, gaté `module:Integration`+`role:admin,super-admin`)
| Méthode | Route | Description |
|---|---|---|
| GET | `connections` | Liste des connexions WHB |
| POST | `connections/invite` | Générer un code d'invitation |
| POST | `connections/join` | Rejoindre via un code d'invitation |
| POST | `connections/{id}/approve` \| `/reject` \| `/suspend` | Cycle de vie de la connexion |
| GET | `connections/{id}/exchanges` | Historique des échanges |
| POST | `send` | Envoyer des données à un partenaire (données réelles transmises depuis Chantier 32.6, voir plus bas) |
| GET | `inbox` | Boîte de réception des échanges entrants |
| POST | `inbox/{exchangeId}/accept` \| `/reject` | Traiter un échange entrant |
| GET | `discover` | Découverte d'une instance distante (appelle `GET {url}/.well-known/widehalo`) |

**Fédération inter-serveurs** (`prefix: v1/federation`, protégée par `VerifyFederationSignature` — signature HMAC, pas d'`auth:sanctum`)
| Méthode | Route | Description |
|---|---|---|
| POST | `invite` \| `accept` \| `exchange` \| `refresh` | Réception des messages de fédération envoyés par une instance distante |

**Découverte publique** (aucun préfixe `v1/`, hors `auth`)
| Méthode | Route | Description |
|---|---|---|
| GET | `/.well-known/widehalo` | **Nouveau Chantier 32.6** — endpoint de bootstrap de découverte fédération. `WhbFederationController::wellKnown()` existait depuis toujours mais n'avait jamais été routé nulle part : ce serveur n'a jamais été découvrable par un partenaire, confirmé par grep avant correction. |

## Contrôleurs

`Modules/Integration/app/Http/Controllers/Api/` (6 fichiers) :

| Contrôleur | Rôle |
|---|---|
| `IntegrationController` | CRUD connecteurs webhook + activation/webhook/dispatch/logs/stats/destroy |
| `ExternalIntegrationController` | **Nouveau Chantier 32.6** — cycle de vie des intégrations `IntegrationManager` (mobile-money/e-commerce/outils métier) |
| `WhbPartnerController` | Cycle de vie des connexions WHB (invite/join/approve/reject/suspend/inbox) |
| `WhbFederationController` | Réception des messages HMAC-signés d'une instance distante (`v1/federation/*`) + `wellKnown()` (découverte publique) |
| `BackendStatusController` | Statut/test Supabase et Firebase |
| `IntegrationAiAssistController` | Guidance IA contextuelle |

**Deux vulnérabilités IDOR réelles corrigées en Chantier 8.5-light/8.6, re-confirmées toujours correctes en Chantier 32.6** (nouveau test de régression réel — voir `Chantier32IntegrationDeepAuditTest.php`) :
- `IntegrationController::show/activate/addWebhook/dispatch/logs` prenait un `IntegrationConnector` lié à la route sans aucun `authorize()` ni filtre tenant — corrigé avec les appels `authorize()` manquants et un vrai contrôle d'appartenance ajouté **dans** `IntegrationConnectorPolicy` elle-même (comparaison `$user->company_id` vs `integration_connectors.tenant_id` castée en chaîne des deux côtés — `tenant_id` est un reliquat `string(36)` d'une conception UUID antérieure).
- `WhbPartnerController::approve/reject/suspend` — corrigé avec `forTenant()` au niveau contrôleur plus un gate `module:Integration`+`role:admin,super-admin` sur tout le groupe `v1/whb`. **Chantier 32.6** a en plus durci `WhbPartnerService::approveConnection()`/`rejectConnection()`/`suspendConnection()` elles-mêmes avec un filtre tenant optionnel — jusque-là ces 3 méthodes de service n'avaient elles-mêmes aucun filtre (sûres uniquement parce que le contrôleur pré-vérifiait avant d'appeler le service), un vrai IDOR latent pour tout futur appelant direct (job, commande artisan, v2 d'API).

Les endpoints publics `v1/federation/*` (HMAC-signés, sans `auth:sanctum`) sont toujours correctement protégés — re-confirmé Chantier 32.6 par une vraie requête signée (acceptée) et une vraie requête forgée/non signée (rejetée 401).

## Vues (Vue/Inertia)

`Modules/Integration/resources/js/Pages/IntegrationsIndex.vue` — **reconstruite en profondeur en Chantier 32.6**, après un constat empirique : la page affichait un onglet « Connecteurs actifs » lisant des champs (`status: 'connected'|'error'`, `template_id`, `icon`, `category`, `last_sync`) qu'aucun endpoint réel ne renvoie jamais (le vrai `status` vaut `active`/`inactive`/`error`) — chaque connecteur actif affichait donc systématiquement « Erreur ». Le bouton de suppression appelait `DELETE connectors/{id}`, une route qui n'a jamais existé avant cette session. Les boutons « Ajouter »/« Configurer »/« Connecter » du catalogue naviguaient vers `/integration/connect`, `/integration/connectors/{id}/configure`, `/integration/connect/{id}` — trois routes jamais créées, un cul-de-sac total. Le catalogue lui-même listait Stripe et WhatsApp Business, deux intégrations sans aucun connecteur réel dans ce dépôt (Stripe n'a jamais eu de classe connecteur ; WhatsApp est un module explicitement hors périmètre de cette extraction). Reconstruite avec : lecture correcte des champs réels de connecteur, un modal de création (POST réel), un modal de détail avec gestion des webhooks + test de dispatch, et un onglet Catalogue reconstruit sur le vrai registre `IntegrationManager` (9 entrées réelles) avec un vrai flux connexion/test/déconnexion.

## Services

- **`IntegrationManager`** — registre central (`REGISTRY` const) des 9 intégrations disponibles : mobile money africain (`orange-money`, `wave`, `mtn-momo`, `mpesa`), e-commerce (`shopify`, `woocommerce`, `jumia`), et connecteurs métier (`google-workspace`, `zapier`). **Activé pour de vrai en Chantier 32.6** : cette classe existait complètement écrite et testée mais n'avait **aucun** contrôleur/route/policy/permission nulle part dans l'application (confirmé par grep — seuls ses propres tests la référençaient) ; `ExternalIntegrationController` + `ExternalIntegrationPolicy` lui donnent désormais un vrai producteur. Deux bugs réels trouvés et corrigés en l'activant : `testConnection()` appelait `$connector->ping()` sans condition alors que seuls les 4 connecteurs mobile money implémentent réellement cette méthode (les 5 autres levaient une erreur fatale non gérée) ; `sync()` cherchait `syncIn()`/`syncOut()` sur le connecteur alors qu'**aucun des 9 connecteurs** n'implémente ces méthodes — chaque appel « réussissait » silencieusement avec 0 enregistrement synchronisé, quel que soit le connecteur. Les deux méthodes échouent désormais franchement (`RuntimeException` → 501) plutôt que de mentir sur le résultat.
- **`IntegrationService`** — cycle de vie CRUD d'un `IntegrationConnector` (création avec slug, activation, dispatch de webhooks sortants avec journalisation dans `SyncLog`, statistiques par tenant). **`dispatchWebhook()` réécrite en Chantier 32.6** sur `Http::pool()` : auparavant une boucle séquentielle appelant chaque endpoint webhook actif un par un de façon bloquante à l'intérieur du cycle requête/réponse — un connecteur avec N endpoints actifs (aucune limite imposée) pouvait cumuler jusqu'à N × timeout (jusqu'à 300s chacun) avant toute réponse à l'appelant. Désormais tous les endpoints sont appelés en parallèle, même contrat de retour synchrone.
- **`WhbPartnerService`** — gère le cycle de vie complet d'une connexion WHB : génère des codes d'invitation à 8 caractères (charset évitant les caractères ambigus 0/O/1/I/L, TTL 24h), approbation/rejet/suspension, envoi et réception dans l'inbox.
- **`WhbFederationService`** — découverte d'instance distante (`GET /.well-known/widehalo`, désormais réellement exposé côté réception aussi — voir Endpoints), signature et vérification HMAC-SHA256 des requêtes (fenêtre anti-rejeu de 5 minutes), refresh de session.
- **`WhbDataSerializerService`** — transformation des données locales vers/depuis le format d'échange WHB. **Bug majeur trouvé et corrigé en Chantier 32.6** : `serialize()` ne récupérait en réalité **jamais** l'enregistrement réel référencé — `$resourceId`/`$tenantId` ne servaient qu'à construire la référence publique `whb_ref`, chaque facture/commande/devis/contact/produit envoyé transmettait un gabarit vide codé en dur (`total: 0`, `lines: []`, …), quel que soit l'enregistrement réel visé. Corrigé pour les 5 types adossés sans ambiguïté à un modèle Eloquent réel du périmètre (`invoice` → `Modules\Accounting\Models\Invoice`, `purchase_order` → `Modules\Achats\Models\PurchaseOrder`, `quote` → `Modules\CRM\Models\Quote`, `contact` → `Modules\CRM\Models\Contact`, `inventory` → `Modules\Inventory\Models\Product`+`Stock`), avec un garde-fou IDOR réel sur les 2 types disposant d'une colonne de portée tenant réellement peuplée (`purchase_order`/`contact`, via `company_id`) — `document`/`catalog`/`message` restent des gabarits honnêtement non implémentés (aucun modèle générique de document/catalogue/message-partenaire n'existe dans ce périmètre). `deserialize()` reste sans aucun appelant réel dans l'application, documenté tel quel.
- **`FirebaseService`**, **`SupabaseService`** — intégrations d'infrastructure (push FCM, PostgREST/Storage Supabase), config-gated par design (dégradation propre « non configuré » sans lever d'erreur). `FirebaseService::set/get/push/remove/uploadFile()` (Realtime Database/Storage) sont réels et testés mais n'ont aucun appelant réel dans l'app en dehors des tests — seule `sendPushNotification()` a un vrai producteur (`BackendStatusController::firebaseTestPush`).
- **`Connectors/`** — un connecteur PHP dédié par fournisseur : `OrangeMoneyConnector`, `WaveConnector`, `MtnMomoConnector`, `MPesaConnector` (les 4 seuls à implémenter `ping()`), `ShopifyConnector`, `WooCommerceConnector`, `JumiaConnector`, `GoogleWorkspaceConnector`, `ZapierConnector`. Tous réels, testés indirectement via `IntegrationManager`, désormais atteignables par un vrai producteur (voir ci-dessus) plutôt que du code mort — confirmé qu'aucun n'était un doublon ou du scaffolding cassé, juste jamais câblé à un contrôleur.

## Permissions RBAC

Préfixe `integration.*` dans `RolesAndPermissionsSeeder::MODULES` — ressources `connector`, `webhook`, `sync-log`, **`external-integration`** (nouveau Chantier 32.6), avec les actions standard (`view-any`, `view`, `create`, `update`, `delete`). `IntegrationConnectorPolicy`/`ExternalIntegrationPolicy` appliquent ces permissions via `Gate::policy()`, toutes deux avec un vrai contrôle d'appartenance tenant. Le groupe `v1/whb` (invitation, approbation, suspension) n'a pas de permission Spatie dédiée mais est gaté par `module:Integration`+`role:admin,super-admin`. `BackendStatusController` (Supabase/Firebase) n'a pas de modèle naturel pour une Policy — gaté `role:employee,manager,finance-manager,system-admin,tenant-admin,admin,super-admin` depuis Chantier 32.6 (auparavant aucun contrôle : n'importe quel utilisateur authentifié, même sans aucune permission `integration.*`, pouvait déclencher une vraie notification push FCM de test).

**Précision sur `module:Integration`** : ce middleware (`Modules\Core\Http\Middleware\CheckModuleEnabled`) est une bascule métier ON/OFF par tenant (ex. un tenant qui n'a pas souscrit au module Integration), **pas** un contrôle de permission — sur une base fraîche sans ligne `tenant_modules`, il est un no-op par conception (« activé par défaut »). Le vrai contrôle d'accès repose sur les Policies (`integration.connector.*`/`integration.external-integration.*`) et, pour `v1/whb`/Supabase/Firebase, sur `role:`.

## Dépendances avec d'autres modules

**Nouveau Chantier 32.6** : `WhbDataSerializerService` importe désormais `Modules\Accounting\Models\Invoice`, `Modules\Achats\Models\PurchaseOrder`, `Modules\CRM\Models\{Contact,Quote}`, `Modules\Inventory\Models\{Product,Stock}` — une dépendance en lecture seule, nécessaire pour que la fédération WHB transmette de vraies données plutôt qu'un gabarit vide (voir Services ci-dessus). Avant cette session, aucun module métier n'importait `Modules\Integration`. Le module utilise en interne `Modules\AuditLog\Traits\HasAuditLog` sur plusieurs modèles (`IntegrationConnector`, `SyncLog`, `WebhookEndpoint`, et désormais `Integration` depuis Chantier 32.6).

## Particularités du périmètre life-mdg-erp

Le module conserve la totalité de la logique de connecteurs mobile money/e-commerce de WideHalo, mais réduit son registre aux besoins Africa First de Life MDG : Orange Money, Wave, MTN MoMo, M-Pesa restent les 4 connecteurs de paiement mobile, sans les connecteurs Asie (Alipay/WeChat Pay/UPI) mentionnés dans la documentation WideHalo d'origine — ceux-ci ne sont pas présents dans le code de ce dépôt (`app/Services/Connectors/` ne contient que les 9 connecteurs listés ci-dessus).

## Chantier 32.6 — audit approfondi 14 couches (résumé)

Bugs réels trouvés par exécution (tinker/HTTP réel), pas par simple relecture, et corrigés dans cette passe :

1. **`integrations.integration_key`** portait un index unique **global** — un deuxième tenant se connectant à la même clé (ex. `orange-money`) provoquait un `UniqueConstraintViolationException` garanti, confirmé empiriquement. Corrigé par une migration additive (contrainte unique `[tenant_id, integration_key]`).
2. **`IntegrationManager`** activé pour la première fois (voir Services) — RBAC, route, vue catalogue.
3. **`testConnection()`/`sync()`** échouaient silencieusement de façon trompeuse (erreur fatale non gérée ou « succès » factice à 0 enregistrement) — corrigés pour échouer franchement (501 honnête).
4. **`WhbDataSerializerService::serialize()`** ne transmettait jamais de données réelles — corrigé pour 5 types de données sur 8, avec garde-fou IDOR réel où une colonne de portée tenant existe.
5. **`.well-known/widehalo`** n'avait aucune route — ce serveur n'a jamais été découvrable par un partenaire fédéré.
6. **`IntegrationController::destroy()`** manquant — le bouton de suppression de `IntegrationsIndex.vue` a toujours 404.
7. **`WebhookEndpoint.secret_key`** jamais masqué — secret HMAC exposé en clair dans la réponse JSON de détail de connecteur.
8. **Trou RBAC réel sur Supabase/Firebase** — aucun contrôle d'aucune sorte sur les 4 endpoints de `BackendStatusController` avant cette session.
9. **`dispatchWebhook()`** — appels HTTP sortants séquentiels bloquants à l'intérieur du cycle requête/réponse, source plausible de lenteur signalée par l'utilisateur — réécrit sur `Http::pool()`.
10. **`WhbPartnerService::approve/reject/suspendConnection()`** durcies en défense en profondeur (filtre tenant optionnel).

Vérifié : `vendor/bin/pest Modules/Integration --parallel` → 124 passés (99 de référence + 25 nouveaux, aucune régression) ; `npm run type-check`/`npm run build` propres ; `php -l` balayé sur tous les fichiers PHP modifiés/créés.
