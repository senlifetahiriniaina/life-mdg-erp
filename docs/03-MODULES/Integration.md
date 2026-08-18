# Integration

## Rôle

Le module `Integration` est le hub des connecteurs externes de life-mdg-erp : paiement mobile africain (Orange Money, Wave, MTN MoMo, M-Pesa), e-commerce (Shopify, WooCommerce, Jumia), outils métiers (Google Workspace, Zapier), plus deux services d'infrastructure (Supabase, Firebase). Il porte aussi un sous-système distinct, le **WideHalo Bridge (WHB)** — un protocole de fédération pour échanger des données entre deux instances ERP (locale ↔ distante), avec invitations, permissions par type de donnée et signature HMAC des échanges. C'est le point d'entrée « Africa First » pour le paiement mobile.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Integration` | `integrations` | Une ligne par tenant × intégration externe active (statut connecté/déconnecté/erreur, credentials chiffrés). |
| `IntegrationConnector` | `integration_connectors` | Connecteur configuré (webhook/OAuth2/API key/basic_auth/custom), avec `config` chiffré (`encrypted:array`) et audit (`HasAuditLog`). |
| `SyncLog` / `IntegrationSyncLog` | `integration_sync_logs` | Deux modèles Eloquent pointent sur la **même table** `integration_sync_logs` : `SyncLog` (avec `connector_id`, audité) et `IntegrationSyncLog` (avec `integration_id`, sans timestamps). |
| `WebhookEndpoint` | `integration_webhook_endpoints` | Endpoint sortant rattaché à un connecteur (URL, méthode, secret, tentatives de retry). |
| `WhbConnection` | `whb_connections` | Connexion WHB entre le tenant local et un tenant distant : code d'invitation, secret partagé, jeton de session, statut. |
| `WhbExchange` | `whb_exchanges` | Un échange de données individuel dans une `WhbConnection` (direction, type de donnée, payload JSON). |
| `WhbPermission` | `whb_permissions` | Permissions par type de donnée pour une connexion WHB (`can_receive`, `can_send`, `auto_accept`). |

## Endpoints principaux

Toutes les routes sont sous `auth:sanctum`.

**Connecteurs et intégrations** (`prefix: v1/integration`)
| Méthode | Route | Description |
|---|---|---|
| GET | `connectors` | Liste des connecteurs du tenant |
| POST | `connectors` | Créer un connecteur |
| GET | `connectors/{connector}` | Détail d'un connecteur |
| POST | `connectors/{connector}/activate` | Activer un connecteur |
| POST | `connectors/{connector}/webhook` | Ajouter un endpoint webhook |
| POST | `connectors/{connector}/dispatch` | Déclencher un envoi sortant |
| GET | `connectors/{connector}/logs` | Historique des synchronisations |
| GET | `stats` | Statistiques d'intégration du tenant |
| GET/POST | `supabase/status`, `supabase/test` | Statut et test de la connexion Supabase |
| GET/POST | `firebase/status`, `firebase/test-push` | Statut et test push Firebase |
| POST | `ai/assist` | Guidance IA contextuelle (`IntegrationAiAssistController`) |

**WideHalo Bridge** (`prefix: v1/whb`)
| Méthode | Route | Description |
|---|---|---|
| GET | `connections` | Liste des connexions WHB |
| POST | `connections/invite` | Générer un code d'invitation |
| POST | `connections/join` | Rejoindre via un code d'invitation |
| POST | `connections/{id}/approve` \| `/reject` \| `/suspend` | Cycle de vie de la connexion |
| GET | `connections/{id}/exchanges` | Historique des échanges |
| POST | `send` | Envoyer des données à un partenaire |
| GET | `inbox` | Boîte de réception des échanges entrants |
| POST | `inbox/{exchangeId}/accept` \| `/reject` | Traiter un échange entrant |
| GET | `discover` | Découverte d'une instance distante |

**Fédération inter-serveurs** (`prefix: v1/federation`, protégée par `VerifyFederationSignature` — signature HMAC, pas d'`auth:sanctum`)
| Méthode | Route | Description |
|---|---|---|
| POST | `invite` \| `accept` \| `exchange` \| `refresh` | Réception des messages de fédération envoyés par une instance distante |

## Contrôleurs

`Modules/Integration/app/Http/Controllers/Api/` (5 fichiers) :

| Contrôleur | Rôle |
|---|---|
| `IntegrationController` | CRUD connecteurs + activation/webhook/dispatch/logs/stats |
| `WhbPartnerController` | Cycle de vie des connexions WHB (invite/join/approve/reject/suspend/inbox) |
| `WhbFederationController` | Réception des messages HMAC-signés d'une instance distante (`v1/federation/*`) |
| `BackendStatusController` | Statut/test Supabase et Firebase |
| `IntegrationAiAssistController` | Guidance IA contextuelle |

**Deux vulnérabilités IDOR réelles corrigées cette session** :
- `IntegrationController::show/activate/addWebhook/dispatch/logs` prenait un `IntegrationConnector` lié à la route sans aucun `authorize()` ni filtre tenant (seuls `index`/`store`/`stats` étaient correctement scopés) — n'importe quel utilisateur de n'importe quel tenant pouvait consulter la config d'un connecteur d'un autre tenant (identifiants potentiellement inclus), l'activer, y enregistrer des webhooks, y déclencher des envois arbitraires et en lire les logs, en devinant simplement l'id. Corrigé avec les appels `authorize()` manquants et un vrai contrôle d'appartenance ajouté **dans** `IntegrationConnectorPolicy` elle-même (qui existait et était enregistrée au Gate, mais ignorait totalement l'argument `$connector` et ne vérifiait qu'une chaîne de permission plate) — comparaison `$user->company_id` vs `integration_connectors.tenant_id` castée en chaîne des deux côtés (`tenant_id` est un reliquat `string(36)` d'une conception UUID antérieure).
- `WhbPartnerController::approve/reject/suspend` n'avait ni scope tenant ni gate de rôle (contrairement à `show`/`exchanges` sur le même contrôleur, déjà correctement `forTenant()`-scopés) — n'importe quel utilisateur authentifié de n'importe quel tenant pouvait approuver/rejeter/suspendre la connexion de fédération d'une **autre** société. Corrigé avec le même pattern `forTenant()` plus un gate `module:Integration`+`role:admin,super-admin` sur tout le groupe `v1/whb`.

Les endpoints publics `v1/federation/*` (HMAC-signés, sans `auth:sanctum`) étaient déjà correctement protégés et n'ont pas été touchés.

## Vues (Vue/Inertia)

`Modules/Integration/resources/js/Pages/IntegrationsIndex.vue` — page réelle, auto-alimentée (`fetch`) contre `v1/integration/*`, mais qui n'avait **aucune route web** avant cette session (`routes/web.php` n'existait pas du tout). Ajouté : `GET /integration` → `Inertia::render('Integration/IntegrationsIndex')`, sous `middleware(['auth', 'module:Integration'])`. La page appelait aussi un schéma `/webhooks` plat fictif (aucun backend de ce type n'existe) — corrigé pour cibler le vrai endpoint imbriqué par connecteur plutôt que d'inventer un nouveau backend.

## Services

- **`IntegrationManager`** — registre central (`REGISTRY` const) de toutes les intégrations disponibles : mobile money africain (`orange-money`, `wave`, `mtn-momo`, `mpesa`), e-commerce (`shopify`, `woocommerce`, `jumia`), et connecteurs métier. Chaque entrée déclare son connecteur PHP, les pays supportés et les champs de credentials requis.
- **`IntegrationService`** — cycle de vie CRUD d'un `IntegrationConnector` (création avec slug, activation, dispatch de webhooks sortants avec journalisation dans `SyncLog`, statistiques par tenant).
- **`WhbPartnerService`** — gère le cycle de vie complet d'une connexion WHB : génère des codes d'invitation à 8 caractères (charset évitant les caractères ambigus 0/O/1/I/L, TTL 24h), approbation/rejet/suspension, envoi et réception dans l'inbox.
- **`WhbFederationService`** — découverte d'instance distante (`GET /.well-known/widehalo`), signature et vérification HMAC-SHA256 des requêtes (fenêtre anti-rejeu de 5 minutes), refresh de session.
- **`WhbDataSerializerService`** — transformation des données locales vers/depuis le format d'échange WHB.
- **`FirebaseService`**, **`SupabaseService`** — intégrations d'infrastructure (push FCM, PostgREST/Storage Supabase).
- **`Connectors/`** — un connecteur PHP dédié par fournisseur : `OrangeMoneyConnector` (API Orange Money v2, paiement C2B par USSD/OTP, `initiatePayment`/`checkStatus`), `MtnMomoConnector` (MTN MoMo Open API — Collection + Disbursement), `WaveConnector`, `MPesaConnector`, `ShopifyConnector`, `WooCommerceConnector`, `JumiaConnector`, `GoogleWorkspaceConnector`, `ZapierConnector`.

## Permissions RBAC

Préfixe `integration.*` dans `RolesAndPermissionsSeeder::MODULES` — ressources `connector`, `webhook`, `sync-log`, avec les actions standard (`view-any`, `view`, `create`, `update`, `delete`). `IntegrationConnectorPolicy` applique ces permissions (`integration.connector.view-any`, etc.) via `Gate::policy(IntegrationConnector::class, ...)`, avec en plus désormais un vrai contrôle d'appartenance tenant (voir Contrôleurs). Le groupe `v1/whb` (invitation, approbation, suspension) n'a pas de permission Spatie dédiée mais est désormais gaté par `module:Integration`+`role:admin,super-admin` (ajouté cette session) — auparavant protégé par `auth:sanctum` seul.

## Dépendances avec d'autres modules

Aucun module métier de life-mdg-erp n'importe `Modules\Integration` dans son code PHP (`grep` sur `Modules\Integration` ne renvoie aucun résultat en dehors du module lui-même) : l'intégration se fait uniquement via ses propres endpoints REST, pas par appel direct de service. Le module utilise en interne `Modules\AuditLog\Traits\HasAuditLog` sur plusieurs modèles (`IntegrationConnector`, `SyncLog`, `WebhookEndpoint`).

## Particularités du périmètre life-mdg-erp

Le module conserve la totalité de la logique de connecteurs mobile money/e-commerce de WideHalo, mais réduit son registre aux besoins Africa First de Life MDG : Orange Money, Wave, MTN MoMo, M-Pesa restent les 4 connecteurs de paiement mobile, sans les connecteurs Asie (Alipay/WeChat Pay/UPI) mentionnés dans la documentation WideHalo d'origine — ceux-ci ne sont pas présents dans le code de ce dépôt (`app/Services/Connectors/` ne contient que les 9 connecteurs listés ci-dessus).
