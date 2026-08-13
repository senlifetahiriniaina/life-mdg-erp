# Conventions API

Ce document couvre les conventions transverses. Les endpoints propres à chaque module sont documentés dans `docs/03-MODULES/<Module>.md`.

## Authentification

Deux mécanismes cohabitent :

- **Laravel Sanctum** (`auth:sanctum`) — tokens d'API classiques, expiration par défaut 30 jours (43200 minutes), configurable via `SANCTUM_TOKEN_EXPIRATION`.
- **JWT dédié**, sous `/api/v1/auth/jwt/*` (`login`, `refresh`, `verify`, `logout`) — pour les clients qui préfèrent un flux JWT explicite (`JWT_ACCESS_TOKEN_EXPIRY=900`s, `JWT_REFRESH_TOKEN_EXPIRY=2592000`s soit 30 jours, cf. `.env.example`).

## Autorisation

RBAC via `spatie/laravel-permission`. Chaque endpoint est protégé par policy/permission — voir `docs/09-RBAC-SECURITE/MATRICE-RBAC.md` pour la matrice complète des 22 rôles.

## Rate limiting

- Limite par défaut sur l'ensemble de l'API : **60 requêtes/minute** (`throttleApi('60,1')`, `bootstrap/app.php`).
- Un limiteur dédié `throttle:ai` s'applique aux endpoints IA (`/api/v1/ai/chat`), plus restrictif que le défaut.
- Réponse en cas de dépassement : HTTP 429, format Laravel standard (`Retry-After` header).

## Format de pagination

Pagination Laravel standard (`->paginate(N)`, généralement 15 ou 20 éléments par page selon le contrôleur) — enveloppe JSON avec `data`, `links` (first/last/prev/next), et `meta` (current_page, total, per_page...).

## Format d'erreur

Pas d'enveloppe d'erreur personnalisée : le rendu standard de Laravel est utilisé.

- **422** — erreurs de validation (`ValidationException`) : `{"message": "...", "errors": {"champ": ["message"]}}`
- **401** — non authentifié
- **403** — non autorisé (policy RBAC)
- **404** — ressource introuvable
- **429** — rate limit dépassé
- **5xx** — erreur serveur ; en production (`APP_DEBUG=false`), aucune trace de pile n'est exposée au client

Le rendu des exceptions API est centralisé dans `bootstrap/app.php` (`->withExceptions(...)`), qui distingue les requêtes API (réponse JSON) des requêtes web (page Inertia `Error`).

## Endpoints transverses (hors périmètre d'un module)

| Endpoint | Description |
|---|---|
| `GET /api/health` | Health check non authentifié, utilisé par les load balancers/monitoring |
| `GET /api/metrics` | Endpoint Prometheus, protégé par `MetricsToken` (bearer token via `METRICS_TOKEN`), fail-closed en production |
| `GET /api/v1/openapi` | Spécification OpenAPI, publique |
| `POST /api/v1/ai/chat` | Chat IA transverse (`auth:sanctum` + `throttle:ai`) |
| `POST /api/v1/ai/assist` | Guidance IA contextuelle par module/action (`AiContextualAssistantService`) |
| `GET /api/v1/ai/assist/modules` | Liste des modules/actions supportés par la guidance IA |

## Documentation OpenAPI générée

Le projet utilise `knuckleswtf/scribe` (cf. `composer.json`) pour générer une documentation OpenAPI à partir des annotations de contrôleurs — voir `GET /api/v1/openapi` pour la spec vivante.
