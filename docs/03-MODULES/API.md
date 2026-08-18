# API

## Rôle

`Modules/API` gère l'exposition programmatique de l'ERP aux systèmes tiers : émission et révocation de clés API, webhooks sortants (avec signature HMAC), journalisation des requêtes API et versioning de l'API REST. C'est le module qui matérialise le principe « API First » côté gouvernance des accès externes — à distinguer des routes métier `/api/v1/*` de chaque module, qui sont l'API elle-même.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `ApiKey` (`Modules\API\Models\ApiKey`) | `api_keys` | Clé API tenant/utilisateur : `scopes`, `rate_limit`, `allowed_ips`, expiration, révocation |
| `ApiRequest` | `api_requests` | Journal de chaque requête API (méthode, endpoint, `status_code`, `duration_ms`) |
| `ApiWebhook` | `api_webhooks` | Webhook sortant : URL, `events` souscrits, `secret` HMAC, compteur d'échecs avec désactivation automatique après 10 échecs consécutifs |

Ce module possède son propre modèle `ApiKey` (table `api_keys`), distinct du modèle `Modules\Core\Models\ApiKey` du module Core — voir `docs/03-MODULES/Core.md`.

## Endpoints principaux

Tous préfixés `/api/v1/api/`, protégés `auth:sanctum` (voir `Modules/API/routes/api.php`) :

- **Clés API** : `GET keys`, `POST keys`, `GET keys/{id}`, `DELETE keys/{id}/revoke`, `GET keys/{id}/logs`
- **Webhooks** : `GET webhooks`, `POST webhooks`, `PUT webhooks/{id}`, `DELETE webhooks/{id}`, `POST webhooks/{id}/test`
- **Logs de requêtes** : `GET logs`, `GET logs/stats`
- **AI Assisted First** : `POST ai/assist`

Un second fichier de routes, `routes/graphql.php`, déclare un préfixe `graphql/*` (query, mutation, subscriptions WebSocket, gestion de schéma, optimisation de requêtes, versioning) — voir Particularités : les contrôleurs ciblés n'existent pas dans ce périmètre.

## Contrôleurs

`Modules/API/app/Http/Controllers/Api/` (4 fichiers) :

| Contrôleur | Rôle |
|---|---|
| `ApiKeyController` | CRUD clés API + révocation + logs par clé |
| `WebhookController` | CRUD webhooks sortants + test |
| `RequestLogController` | Consultation du journal `api_requests` + stats |
| `APIAiAssistController` | Guidance IA contextuelle (AI Assisted First) |

Ni doublon ni scaffold mort trouvé dans ce module cette session — le seul point orphelin réel est `routes/graphql.php` (voir Particularités), pas un contrôleur.

## Vues (Vue/Inertia)

Aucune — `Modules/API/routes/web.php` n'existe pas. Conforme au principe « API First » : ce module gère l'exposition programmatique de l'ERP à des systèmes tiers, il n'a jamais eu vocation à avoir une interface propre.

## Services

- `APIVersioningService` — informations de version (`v1.0.0` déprécié, `v1.5.0` supporté, `LATEST_VERSION = 2.0.0`), matrice de compatibilité, guides de migration — logique présente mais non exposée par une route active du module (elle alimentait `routes/graphql.php`, non chargé)
- `GraphQLSchemaBuilderService`, `GraphQLQueryOptimizerService`, `GraphQLSubscriptionManagerService` — services GraphQL enregistrés en singleton dans `APIServiceProvider` (avec alias `graphql_schema`, `graphql_optimizer`, `graphql_subscriptions`) mais sans contrôleur pour les invoquer (voir Particularités). Ces services restent utiles indépendamment de GraphQL : le `CLAUDE.md` racine note que 74 tests hors du périmètre GraphQL dépendent d'eux pour de la bookkeeping d'optimisation de requêtes/souscriptions non liée à GraphQL — ils n'ont donc pas été supprimés malgré l'absence de route HTTP GraphQL réelle.

## Permissions RBAC

`API` n'a pas de bloc `api.*.*` dans `RolesAndPermissionsSeeder::MODULES`. Le contrôle d'accès repose sur des policies dédiées (`ApiKeyPolicy`, `WebhookPolicy`) désormais **enregistrées auprès du Gate** (`APIServiceProvider::registerPolicies()`, ajouté cette session — avant ce correctif, les deux policies existaient et étaient correctement écrites mais n'étaient jamais résolues par Laravel, donc n'importe quel utilisateur authentifié de n'importe quel rôle pouvait créer/révoquer des clés API et des webhooks) et sur `auth:sanctum`. Aucune permission Spatie nommée n'est par ailleurs vérifiée explicitement dans les contrôleurs.

## Dépendances avec d'autres modules

- **Utilise** : uniquement `Modules\AI\...` (pour la guidance contextuelle IA via `APIAiAssistController`) — aucune dépendance vers un autre module métier.
- **Utilisé par** : aucun autre module n'importe de classe `Modules\API\*` — module « feuille », consommé uniquement via HTTP par des systèmes externes.

## Particularités du périmètre life-mdg-erp

- **Pas de `module.json`/`composer.json` propre au module** (contrairement à Core, qui en a un) — l'autoload PSR-4 `Modules\API\` est déclaré directement dans le `composer.json` racine, et le service provider `APIServiceProvider` est découvert par convention par `nwidart/laravel-modules`.
- **`routes/graphql.php` est en grande partie orphelin** : il déclare ~25 routes vers `Modules\API\Http\Controllers\GraphQL\{GraphQLController, SubscriptionController, SchemaController, OptimizerController, IntrospectionController}` et `Modules\API\Http\Controllers\APIController` — aucun de ces fichiers n'existe sous `Modules/API/app/Http/Controllers/` (seul le sous-dossier `Api/` existe, avec `ApiKeyController`, `WebhookController`, `RequestLogController`, `APIAiAssistController`). Les services GraphQL sous-jacents (`GraphQLSchemaBuilderService` etc.) existent bien et sont enregistrés dans le conteneur, mais restent inatteignables sans ces contrôleurs. Toute route effectivement appelée dans ce fichier échouerait avec une classe introuvable.
- **`APIVersioningService::getVersionInfo()`** encode un cycle de version WideHalo (v1.0.0 déprécié au 2026-05-01, sunset 2026-12-31 ; v2.0.0 « latest ») qui n'a pas été retaillé pour Life MDG — cette logique n'est de toute façon plus atteignable depuis que `routes/graphql.php` (seul point d'entrée qui l'exposait via `APIController@versions`) pointe vers un contrôleur absent.
