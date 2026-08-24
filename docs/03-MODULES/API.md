# API

## Rôle

`Modules/API` gère l'exposition programmatique de l'ERP aux systèmes tiers : émission, révocation et authentification de clés API, et journalisation des requêtes API. C'est le module qui matérialise le principe « API First » côté gouvernance des accès externes — à distinguer des routes métier `/api/v1/*` de chaque module, qui sont l'API elle-même.

**Chantier 32.5 (audit approfondi en 14 couches)** a supprimé le sous-système `ApiWebhook`/`WebhookController`/`WebhookPolicy` de ce module (confirmé mort/factice et doublon d'un système réel déjà présent ailleurs dans l'app — voir « Historique » plus bas) et a activé pour de vrai le pipeline `ApiKey`/`ApiRequest`, jusque-là réel côté écriture mais sans aucun consommateur.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `ApiKey` (`Modules\API\Models\ApiKey`) | `api_keys` | Clé API tenant/utilisateur : `scopes`, `rate_limit`, expiration, révocation. Depuis Chantier 32.5 : `use RecordsActivity` (audite création/révocation/renommage dans `core_audit_logs`, jamais `key_hash`) |
| `ApiRequest` | `api_requests` | Journal de chaque requête API authentifiée par clé (méthode, endpoint, `status_code`, `duration_ms`, ip, user-agent) — écrit pour de vrai depuis Chantier 32.5, voir « Authentification par clé API » |

Ce module possède son propre modèle `ApiKey` (table `api_keys`), distinct du modèle `Modules\Core\Models\ApiKey` du module Core (coffre-fort de secrets, un concept différent malgré le nom identique).

`ApiWebhook`/`api_webhooks` ont été supprimés à Chantier 32.5 — voir « Historique ».

## Authentification par clé API (nouveau, Chantier 32.5)

Jusqu'à ce chantier, `ApiKey`/`ApiKeyController` géraient un cycle de vie de clé API réel et fonctionnel côté écriture (créer/lister/révoquer), mais **aucun code de l'application ne validait jamais une requête entrante contre une vraie clé** — chaque route de l'app s'authentifie via une session/token Sanctum, jamais via `api_keys`. Conséquence directe : `ApiRequest` (le journal par clé que `RequestLogController`/`ApiKeyController::logs()` lisent déjà) n'avait aucun rédacteur nulle part — l'écran « historique de mes requêtes API » de n'importe quel tenant a toujours lu une table vide.

Activé, pas supprimé, puisque « API First » est un principe fondateur et que le modèle était déjà réel (pas de `uniqid()`/donnée jamais persistée) :

- **`Modules\API\Http\Middleware\AuthenticateApiKey`** — résout une clé brute envoyée via l'en-tête `X-Api-Key` (recherche par `key_prefix`, puis `Hash::check()` contre le hash bcrypt réel, `ApiKey::scopeActive()` pour révocation/expiration), applique le quota horaire propre à la clé (colonne `rate_limit`, réelle, validée à la création, jusqu'ici jamais lue nulle part — via `Illuminate\Cache\RateLimiter`), met à jour `last_used_at`, et journalise une vraie ligne `ApiRequest` en sortie.
- Enregistré comme alias de middleware `api-key` dans `APIServiceProvider::boot()`.
- Consommateur réel, borné à dessein : `GET /api/v1/api/ping` (nouveau, sans `auth:sanctum`/`module:API`/`role:` — pensé pour un appelant externe muni d'une clé, pas une session tableau de bord). Retourne l'identité de la clé (nom, scopes, tenant, quota) et confirme le fonctionnement de bout en bout.
- **Volontairement pas retrofité sur les vraies routes métier de l'app** (`/api/v1/crm/*`, etc.) — cela suppose une décision produit route par route (quelles routes doivent accepter l'auth par clé en plus/à la place de Sanctum ?) et toucherait les fichiers de routes d'une trentaine d'autres modules, hors périmètre d'un audit d'un seul module. Documenté comme un vrai chantier futur, pas construit ici.

## Endpoints principaux

Tous préfixés `/api/v1/api/` (voir `Modules/API/routes/api.php`) :

- **Clés API** (session Sanctum, `module:API`+`role:employee,admin,super-admin`, `store`/`revoke` sous `throttle:create_post` depuis Chantier 32.5 — auparavant sans limite dédiée au-delà du throttle global 60/min) : `GET keys`, `POST keys`, `GET keys/{id}`, `DELETE keys/{id}/revoke`, `GET keys/{id}/logs`
- **Diagnostic clé API** (auth par clé, pas de session) : `GET ping` — nouveau, Chantier 32.5
- **Logs de requêtes** (session Sanctum) : `GET logs`, `GET logs/stats`
- **AI Assisted First** : `POST ai/assist`

`routes/graphql.php` (précédemment orphelin, ~25 routes vers des contrôleurs inexistants — voir « Historique ») a été supprimé à Chantier 32.5.

## Contrôleurs

`Modules/API/app/Http/Controllers/Api/` (3 fichiers) :

| Contrôleur | Rôle |
|---|---|
| `ApiKeyController` | CRUD clés API + révocation + logs par clé + `ping()` (diagnostic auth par clé) |
| `RequestLogController` | Consultation du journal `api_requests` + stats |
| `APIAiAssistController` | Guidance IA contextuelle (AI Assisted First) |

`WebhookController` supprimé à Chantier 32.5 (voir « Historique »). `index()`/`show()`/`logs()` de `ApiKeyController` interrogent désormais le modèle Eloquent (`ApiKey::query()->forTenant()`/`scopeActive()`) plutôt que `DB::table()` brut — un vrai bug de sécurité corrigé au passage : la requête brute renvoyait `key_hash` (le hash bcrypt) en clair au client, `$hidden` sur le modèle Eloquent n'ayant jamais d'effet sur un résultat de query builder brut.

## Vues (Vue/Inertia)

Aucune — `Modules/API/routes/web.php` n'existe pas. Conforme au principe « API First » : ce module gère l'exposition programmatique de l'ERP à des systèmes tiers ou au tableau de bord via son API, il n'a jamais eu vocation à avoir une interface propre. Confirmé par grep applicatif : zéro page Vue nulle part dans le dépôt ne consomme `/api/v1/api/*`.

## Services

- `APIVersioningService` — informations de version (`v1.0.0` déprécié, `v1.5.0` supporté, `LATEST_VERSION = 2.0.0`), matrice de compatibilité, guides de migration — logique réelle mais non exposée par une route active du module (elle alimentait `routes/graphql.php`, désormais supprimé). Conservée (voir « Historique ») : testée directement en isolation par `APIServiceProvider`/`APITest.php`, sans dépendance à une route HTTP.
- `GraphQLSchemaBuilderService`, `GraphQLQueryOptimizerService`, `GraphQLSubscriptionManagerService` — services GraphQL réels (pas de `uniqid()`/donnée jamais persistée — de vrais algorithmes de calcul de profondeur/complexité de requête, suggestion d'eager-load, etc.) enregistrés en singleton dans `APIServiceProvider` (alias `graphql_schema`, `graphql_optimizer`, `graphql_subscriptions`), mais sans contrôleur pour les invoquer depuis que `routes/graphql.php` (le seul point d'entrée qui les exposait) a été supprimé. Confirmé à Chantier 32.5 (grep exhaustif) : leurs seuls appelants sont les tests `Feature`/`Unit` de ce module lui-même — pas « 74 tests hors module » comme une note antérieure du `CLAUDE.md` racine le laissait entendre (cette note faisait en réalité référence aux propres tests `Feature` de `Modules/API`, hors du seul fichier `GraphQLAPIv2Test.php`). Classées « réelles mais sans producteur » (couche 9), pas mortes/factices — conservées comme bibliothèque interne, pas supprimées : les reconstruire en vraies routes GraphQL v2 serait un chantier de construction de fonctionnalité à part entière, hors périmètre d'un audit de bugs.

## Permissions RBAC

`API` n'a pas de bloc `api.*.*` dans `RolesAndPermissionsSeeder::MODULES`. Le contrôle d'accès repose sur `ApiKeyPolicy`, **enregistrée auprès du Gate** (`APIServiceProvider::registerPolicies()`, Chantier 8.5-light — avant ce correctif, la policy existait et était correctement écrite mais n'était jamais résolue par Laravel, donc n'importe quel utilisateur authentifié de n'importe quel rôle pouvait créer/révoquer des clés API), sur le gate de route `module:API`+`role:employee,admin,super-admin`, et — pour `GET ping` uniquement — sur la clé API elle-même (`AuthenticateApiKey`), sans session ni rôle Spatie. `WebhookPolicy` a été supprimée avec `WebhookController` à Chantier 32.5. `ApiKeyPolicy` référençait un rôle `'api-manager'` jamais seedé nulle part dans ce dépôt (confirmé par grep) — retiré à Chantier 32.5 (n'a jamais rien changé d'observable, la policy retombant déjà sur `admin`/`super-admin`, mais c'était une référence trompeuse, même classe de correction déjà appliquée à `CspViolationPolicy`/Calendar cette session).

## Dépendances avec d'autres modules

- **Utilise** : `Modules\AI\...` (guidance contextuelle IA via `APIAiAssistController`), `Modules\Core\Traits\RecordsActivity` (audit de `ApiKey`, nouveau Chantier 32.5) — aucune dépendance vers un autre module métier.
- **Utilisé par** : aucun autre module n'importe de classe `Modules\API\*` — module « feuille », consommé uniquement via HTTP (par le tableau de bord via session Sanctum, ou par un système externe via une clé API).

## Historique — Chantier 32.5 (audit approfondi en 14 couches)

Audit complet du module contre la méthodologie 14 couches (voir `CLAUDE.md`). Trois trouvailles majeures :

1. **`ApiWebhook`/`WebhookController`/`WebhookPolicy` — confirmé mort/factice, supprimé**. Aucun mécanisme de livraison HTTP nulle part (`recordSuccess()`/`recordFailure()` sans appelant réel), `test()` retournait un message de succès codé en dur sans jamais réellement appeler l'URL cible, et `index()`/`show()` fuitaient le `secret` HMAC en clair (requête `DB::table()` brute contournant `$hidden`). Confirmé être un doublon complet d'un système réel, vivant, supérieur déjà présent à la racine de l'app : `App\Models\Webhook`/`WebhookDelivery`, `App\Http\Controllers\Api\WebhookController` (`/api/v1/webhooks`, CRUD + `redeliver` + `availableEvents`), `App\Services\WebhookService::dispatch()` (signature HMAC-SHA256 réelle, en-tête `X-WideHalo-Signature`, historique de livraison persisté). Ce système racine reste lui-même sans déclencheur automatique réel (`App\Traits\DispatchesWithWebhooks` n'est adopté par aucun modèle, confirmé par grep) — un gap réel mais entièrement hors du périmètre de `Modules\API`, documenté ici sans y toucher.
2. **`routes/graphql.php` — confirmé mort, supprimé**. Jamais chargé par aucun provider (seul `routes/api.php` l'est), et référençait des contrôleurs (`GraphQL\{GraphQLController,...}`, `APIController`) qui n'existent nulle part sous `Modules/API/app/Http/Controllers/`. Confirmé via `php artisan route:list` : zéro route de ce fichier n'a jamais été enregistrée — le seul endpoint `graphql` réellement servi par l'app est celui de Lighthouse (`config/lighthouse.php`, déjà documenté dans les « Known gaps » du `CLAUDE.md` racine).
3. **`ApiKey`/`ApiRequest` — réels mais sans producteur, activés** (voir « Authentification par clé API » plus haut).

Corrections plus ciblées : `key_hash` ne fuite plus via `index()`/`show()`/`logs()` (bascule sur le modèle Eloquent) ; `store()` rejette désormais une clé déjà expirée à la création (`after:now`) ; le rôle fantôme `'api-manager'` retiré de `ApiKeyPolicy` ; `store`/`revoke` gagnent `throttle:create_post` (génération de clé = action sensible, aucune limite dédiée avant) ; `ApiKey` audite désormais création/révocation via `RecordsActivity` — avec `$auditableFields` explicitement restreint pour qu'un simple `touchLastUsed()` (déclenché à chaque requête authentifiée par clé) ne pollue pas `core_audit_logs` à chaque appel, un bug trouvé empiriquement en testant l'ajout du trait, pas en le lisant. Les scopes déjà écrits `ApiKey::scopeForTenant()`/`scopeActive()` (jamais utilisés jusqu'ici, tout le contrôleur passant par des requêtes brutes dupliquant la même logique) sont désormais réellement utilisés.

Cloisonnement multi-tenant (`company_id`, corrigé Chantier 10, re-confirmé Chantier 19 Lot 3) re-vérifié empiriquement une troisième fois à Chantier 32.5, toujours correct. `RequestLogController::index()` avait déjà une vraie pagination (`paginate(50)`) — reconfirmé sans N+1 sous un volume réaliste (300 lignes, requête unique).

Non trouvé ici mais dans un autre module, à traiter dans un futur chantier : `Modules\AI\Services\AiContextualAssistantService::supportedModules()` n'a jamais eu d'entrée `'API'` — `APIAiAssistController::assist()` dégrade donc systématiquement vers `emptyGuidance()` (`enabled:false`, tous les champs vides) plutôt qu'un vrai texte de repli, en contradiction avec le principe « Fallback-First » documenté dans `CLAUDE.md` — exactement le même bug déjà trouvé et corrigé pour `'Strategy'` à Chantier 30. Non corrigé ici puisque le fichier concerné (`Modules/AI/app/Services/AiContextualAssistantService.php`) appartient à `Modules\AI`, audité en parallèle par un autre agent au moment de ce chantier.
