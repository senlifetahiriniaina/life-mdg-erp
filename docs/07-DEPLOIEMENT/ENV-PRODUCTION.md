# Variables d'environnement — production

Voir `.env.example` pour la liste complète et les valeurs de démarrage. Points d'attention spécifiques à un déploiement en production :

## À changer impérativement avant mise en production

| Variable | Valeur de démarrage (`.env.example`) | Action requise |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` (ne jamais exposer de stack trace en production) |
| `APP_KEY` | vide | `php artisan key:generate` (fait automatiquement par `scripts/deploy.sh` à la première installation) |
| `APP_DOMAIN` | vide | **Obligatoire** pour `scripts/deploy.sh` (Docker Compose + Caddy) — le nom de domaine public de l'application, utilisé par Caddy pour obtenir le certificat SSL Let's Encrypt. Voir `docs/07-DEPLOIEMENT/GUIDE-DEPLOIEMENT-SIMPLE.md`. |
| `DB_PASSWORD` | `secret` | Mot de passe fort, géré via secret manager |
| `DB_ROOT_PASSWORD` | vide | **Obligatoire** pour `scripts/deploy.sh` — mot de passe root MySQL du conteneur `mysql` de `docker-compose.prod.yml` (différent de `DB_PASSWORD`, non lu par Laravel lui-même). |
| `DB_SSL_CA` / `DB_SSL_VERIFY` | vide / `true` | Configurer un certificat TLS pour la connexion base de données |
| `METRICS_TOKEN` | vide | Token long aléatoire — l'endpoint `/api/metrics` **fail-closed** si vide en production (aucune valeur par défaut n'est acceptée) |
| `CORS_ALLOWED_ORIGINS` | vide | Domaines autorisés explicites — jamais `*` (rejeté de toute façon si `credentials` est actif) |
| `TELESCOPE_ENABLED` | `true` | `false` en production (Telescope est un outil de debug dev uniquement) |
| `SENTRY_DSN` / `SENTRY_ENABLED` | vide / `false` | Configurer pour le suivi d'erreurs en production |
| `BACKUP_S3_BUCKET`, `BACKUP_DISK` | `s3` | Configurer un vrai bucket + identifiants AWS |
| `GRAFANA_PASSWORD` | `changeme` | Mot de passe fort |

## Modules actifs

`ENABLED_MODULES` liste les 27 modules du périmètre — doit rester synchronisé avec `config/modules_statuses.json`.

## IA

`ANTHROPIC_API_KEY` — si absente, `AiContextualAssistantService::getGuidance()` renvoie `enabled: false` avec un contenu de repli statique, jamais d'erreur (voir `docs/02-ARCHITECTURE/PRINCIPES.md`, pilier AI Assisted First). En production, définir cette clé pour bénéficier des guidances IA dynamiques plutôt que du contenu statique de repli.

## Base de données

`DB_DATABASE=life_mdg_erp` par défaut — à adapter selon la convention de nommage de votre infrastructure. Le nom de base utilisé en CI (`life_mdg_test`) est distinct et n'a pas besoin de correspondre à celui de production.

## Devises et localisation

`APP_LOCALE=en` par défaut dans `.env.example` — Life MDG opérant probablement en contexte francophone/malgache, envisager `APP_LOCALE=fr` ou `mg` en production selon le marché cible (les deux dictionnaires existent déjà dans `lang/`).
