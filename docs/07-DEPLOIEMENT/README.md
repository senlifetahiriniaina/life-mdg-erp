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

### Validation effectuée

Chaque workflow a été corrigé pour fonctionner avec la structure réelle de life-mdg-erp (composer.json/package.json à la racine, pas de dossier `webapp/` ni `apps/`, pas de split multi-dépôt) — ces fichiers étaient copiés tels quels depuis Widehalo-ERP au moment de l'extraction et référençaient des chemins inexistants ici. La validation a inclus l'observation de runs réels sur GitHub Actions (pas seulement une relecture du YAML, y compris un déclenchement manuel des workflows à cron pour les exercer avant tout merge), ce qui a permis de détecter plusieurs échecs invisibles jusqu'ici (masqués par `continue-on-error: true` ou jamais exécutés avant cette validation) :

- Deux SHA de commit invalides (`actions/setup-node`, `actions/cache`) qui faisaient échouer "Set up job" en quelques secondes.
- `actions/setup-node`'s option de cache npm intégrée, qui exige un `package-lock.json` déjà présent pour calculer sa clé de cache — inexistant ici (gitignoré intentionnellement).
- `npm ci`, qui exige lui-même un lockfile déjà existant plutôt que d'en générer un — cassé pour la même raison, dans tous les workflows qui l'utilisaient.
- `npm outdated` dans `dependency-check.yml`, qui sort avec un code 1 dès qu'il trouve des paquets obsolètes (comportement normal et documenté de la commande) — sous `set -e` (mode par défaut des blocs `run:` multi-lignes de GitHub Actions), cela tuait toute l'étape avant qu'elle ait pu produire son rapport.

## Docker

`Dockerfile` à la racine — build multi-étapes (`php:8.4-fpm` en base). `docker-build.yml` valide que l'image build sur chaque changement pertinent, sans la publier vers un registre.

**Le build Docker ne peut pas aboutir en l'état** : le `Dockerfile` copie `docker/supervisor.conf`, `docker/php.ini`, `docker/php-fpm.conf` et `docker/health-check.php`, mais ce dossier `docker/` n'existe dans aucun des deux dépôts (Widehalo-ERP source compris — ce n'est donc pas un oubli de portage, c'est un `Dockerfile` jamais réellement construit avec succès, même dans le dépôt source). Les autres erreurs bloquantes du build (COPY de `.widehalo-core`, supprimé lors de l'extraction ; COPY de `composer.lock`/`package-lock.json`, gitignorés ; `npm ci --only=prod`, syntaxe npm obsolète) ont été corrigées. Écrire les 4 fichiers `docker/*` manquants (configuration PHP-FPM, supervisor, script de health-check) est un vrai travail d'infrastructure à part entière — non traité ici, tracké dans `CLAUDE.md` sous "Known gaps".

## Déploiement en production (`deploy.yml`)

Déploiement par tar+SSH (pas de conteneur en production malgré la présence d'un `Dockerfile` — celui-ci sert à la validation CI, pas au déploiement) :

1. Build (`composer install --no-dev`, `npm run build`)
2. Archive tar de l'application (exclut `.git`, `node_modules`, `.env*`, `storage/logs`, `bootstrap/cache`)
3. Transfert SCP vers le serveur cible
4. Extraction, `php artisan migrate --force`, bascule du symlink `current` vers la nouvelle release, purge des anciennes releases (garde les 5 dernières)
5. Vérification santé (`GET /api/health`), notification Slack

**Secrets requis** (non fournis dans ce dépôt, à configurer dans les paramètres GitHub du dépôt) : `DEPLOY_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `SLACK_WEBHOOK_URL`, `SMOKE_TEST_TOKEN`.

**Prérequis non encore présents dans ce dépôt** : le job `post-deploy-tests` de `deploy.yml` appelle `node scripts/smoke-tests.js`, qui n'existe pas encore (`scripts/` n'a pas été porté depuis Widehalo-ERP). Ce script devra être écrit avant la première utilisation réelle de `deploy.yml` — voir `docs/07-DEPLOIEMENT/CHECKLIST-GO-LIVE.md`.

## Runbook de migration

`php artisan migrate --force` en production. Toujours précédé de `php artisan down` (mode maintenance) et suivi de `php artisan up` — voir la séquence complète dans `deploy.yml`.

## IA auto-hébergée (DeepSeek)

`docker-compose.deepseek.yml` à la racine lance un serveur Ollama servant un modèle DeepSeek distillé, comme alternative auto-hébergée à Anthropic/OpenAI pour le module IA — additive et non-régressive (comportement par défaut inchangé tant que `AI_DEFAULT_PROVIDER` reste `anthropic`). `docker-build.yml` valide la syntaxe de ce fichier compose (`docker compose config`) à chaque changement pertinent, sans télécharger l'image ni le modèle. Détails complets : `docs/07-DEPLOIEMENT/IA-AUTOHEBERGEE.md`.
