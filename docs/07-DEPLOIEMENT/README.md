# Déploiement

## CI/CD

`.github/workflows/` contient 7 workflows GitHub Actions :

| Workflow | Déclencheur | Rôle |
|---|---|---|
| `ci.yml` | Push/PR sur `main`, `develop`, `claude/**` | Tests PHP (Pest), PHPStan, Pint, build frontend, `composer audit`/`npm audit` |
| `docker-build.yml` | Push/PR touchant `Dockerfile`/`infra/**`/`composer.json`/`package.json` | Valide que l'image Docker build (pas de push registre) |
| `dependency-check.yml` | Hebdomadaire (vendredi 10h UTC) + manuel | Rapport de paquets obsolètes |
| `security-audit-scheduled.yml` | Hebdomadaire (lundi 02h UTC) + manuel | Audit de sécurité complet, ouvre une issue en cas d'échec |
| `supply-chain.yml` | Push/PR sur `main` + hebdomadaire | Scan CVE (Trivy), SBOM, détection de secrets (TruffleHog), revue de dépendances |
| `performance-budget.yml` | Push/PR sur `main`/`develop` | Taille de bundle JS, Lighthouse CI |
| `deploy.yml` | Push sur `main`/tags `v*.*.*` + manuel | Déploiement SSH+tar vers un serveur, avec rollback automatique |

**Tous les checks sauf `deploy.yml` sont informationnels** (`continue-on-error: true`) — voir `docs/09-RBAC-SECURITE/SECURITE.md` pour la justification de ce choix. Le workflow `mobile-release.yml` présent dans Widehalo-ERP a été retiré : life-mdg-erp n'a pas de module Mobile dans son périmètre de 27 modules.

**Pour déployer, voir en premier lieu le [Guide de déploiement simple](GUIDE-DEPLOIEMENT-SIMPLE.md)** — ce README documente les workflows CI/CD et l'état de chaque brique ; le guide simple donne la marche à suivre complète (DNS, `.env`, `scripts/deploy.sh`) en une page.

### Validation effectuée

Chaque workflow a été corrigé pour fonctionner avec la structure réelle de life-mdg-erp (composer.json/package.json à la racine, pas de dossier `webapp/` ni `apps/`, pas de split multi-dépôt) — ces fichiers étaient copiés tels quels depuis Widehalo-ERP au moment de l'extraction et référençaient des chemins inexistants ici. La validation a inclus l'observation de runs réels sur GitHub Actions (pas seulement une relecture du YAML, y compris un déclenchement manuel des workflows à cron pour les exercer avant tout merge), ce qui a permis de détecter plusieurs échecs invisibles jusqu'ici (masqués par `continue-on-error: true` ou jamais exécutés avant cette validation) :

- Deux SHA de commit invalides (`actions/setup-node`, `actions/cache`) qui faisaient échouer "Set up job" en quelques secondes.
- `actions/setup-node`'s option de cache npm intégrée, qui exige un `package-lock.json` déjà présent pour calculer sa clé de cache — inexistant ici (gitignoré intentionnellement).
- `npm ci`, qui exige lui-même un lockfile déjà existant plutôt que d'en générer un — cassé pour la même raison, dans tous les workflows qui l'utilisaient.
- `npm outdated` dans `dependency-check.yml`, qui sort avec un code 1 dès qu'il trouve des paquets obsolètes (comportement normal et documenté de la commande) — sous `set -e` (mode par défaut des blocs `run:` multi-lignes de GitHub Actions), cela tuait toute l'étape avant qu'elle ait pu produire son rapport.

## Docker

`Dockerfile` à la racine — build multi-étapes (`php:8.5-fpm` en base). `docker-build.yml` valide que l'image build sur chaque changement pertinent (Dockerfile, `docker-compose.{deepseek,redis,prod}.yml`, `Caddyfile`), sans la publier vers un registre sur les push vers les branches de travail (le push réel vers `ghcr.io` n'a lieu que dans `deploy.yml`, voir ci-dessous).

**Le build Docker fonctionne** — `docker/{supervisor.conf,php.ini,php-fpm.conf,health-check.php}` sont des fichiers réels (voir `CLAUDE.md` "Known gaps" pour l'historique de leur mise en place), confirmé par des runs CI réels, pas seulement un lint local.

## Déploiement en production — Docker Compose + Caddy (chemin officiel)

**Voir le [Guide de déploiement simple](GUIDE-DEPLOIEMENT-SIMPLE.md) pour la marche à suivre complète.** Résumé de l'architecture :

- `docker-compose.prod.yml` (racine) lance 6 services : `app` (php-fpm), `queue` (worker), `scheduler` (cron interne), `mysql`, `redis`, et `caddy` (reverse-proxy + certificat SSL Let's Encrypt automatique, seul service exposé sur les ports 80/443).
- `scripts/deploy.sh` orchestre le déploiement en une commande depuis un clone frais : construction de l'image, démarrage de la stack, migrations, vérification santé.
- `Caddyfile` définit le seul bloc de configuration nécessaire (`php_fastcgi` vers le service `app`) — Caddy gère lui-même l'obtention/le renouvellement du certificat SSL, aucune étape certbot manuelle.

## Déploiement continu (`deploy.yml`)

Sur push vers `main`/tag `v*.*.*`, ou déclenchement manuel :

1. Build de l'image Docker (`Dockerfile` racine) et push vers `ghcr.io/<repo>:<sha>` + `:latest`.
2. Connexion SSH au serveur cible (qui doit déjà avoir été initialisé une première fois via `scripts/deploy.sh`, voir le guide simple) : `git pull`, `docker pull` de la nouvelle image, `docker compose -f docker-compose.prod.yml up -d --no-build` (redémarre les conteneurs sur la nouvelle image sans reconstruire sur le serveur), migrations.
3. Vérification santé (`GET /api/health`) + tests de fumée (`scripts/smoke-tests.js`), notification Slack.
4. En cas d'échec après déploiement : `rollback` repointe automatiquement sur l'image précédente (taguée `previous` par le job `deploy`).

**Secrets requis** (non fournis dans ce dépôt, à configurer dans les paramètres GitHub du dépôt) : `DEPLOY_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `SLACK_WEBHOOK_URL`, `SMOKE_TEST_TOKEN`. `DEPLOY_PATH` doit pointer vers le répertoire du clone déjà initialisé sur le serveur (celui où `scripts/deploy.sh` a été lancé la première fois).

## Runbook de migration

`php artisan migrate --force` en production. Toujours précédé de `php artisan down` (mode maintenance) et suivi de `php artisan up` — voir la séquence complète dans `deploy.yml`.

## IA auto-hébergée (DeepSeek)

`docker-compose.deepseek.yml` à la racine lance un serveur Ollama servant un modèle DeepSeek distillé, comme alternative auto-hébergée à Anthropic/OpenAI pour le module IA — additive et non-régressive (comportement par défaut inchangé tant que `AI_DEFAULT_PROVIDER` reste `anthropic`). `docker-build.yml` valide la syntaxe de ce fichier compose (`docker compose config`) à chaque changement pertinent, sans télécharger l'image ni le modèle. Détails complets : `docs/07-DEPLOIEMENT/IA-AUTOHEBERGEE.md`.
