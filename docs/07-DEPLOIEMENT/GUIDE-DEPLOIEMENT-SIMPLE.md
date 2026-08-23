# Guide de déploiement simple — du clone à l'application en HTTPS

Ce guide déroule tout le chemin, de `git clone` jusqu'à l'application accessible sur votre domaine avec un certificat SSL valide, en une seule commande de déploiement (`scripts/deploy.sh`).

La stack utilisée est **Docker Compose + Caddy** (`docker-compose.prod.yml` + `Caddyfile`, à la racine du dépôt) : Caddy fait office de reverse-proxy et obtient/renouvelle automatiquement un certificat SSL Let's Encrypt dès que le DNS de votre domaine pointe vers le serveur — **aucune configuration manuelle de certbot n'est nécessaire**.

## Vue d'ensemble de la stack

| Service | Rôle |
|---|---|
| `app` | Application Laravel (php-fpm), image buildée depuis le `Dockerfile` racine |
| `queue` | Worker de file d'attente (`php artisan queue:work`) |
| `scheduler` | Planificateur (`php artisan schedule:run` toutes les 60s) |
| `reverb` | Serveur WebSocket (`php artisan reverb:start`) — notifications temps réel et messagerie interne (voir `CLAUDE.md` § Chantier 20), relayé par Caddy sur `/app/*` |
| `mysql` | Base de données (MySQL 8.4, volume persistant, non exposée sur Internet) |
| `redis` | Cache/session/queue (volume persistant, non exposée sur Internet) |
| `caddy` | Reverse-proxy + certificat SSL automatique (seul service exposé sur 80/443) |

## Prérequis (étapes manuelles, non scriptables)

Ces deux étapes doivent être faites avant de lancer le script — rien ne peut les automatiser depuis ce dépôt.

> **Vous provisionnez sur AWS Lightsail et n'avez pas encore de serveur ?** `scripts/lightsail-deploy.sh` automatise la création de l'instance, l'IP statique, et le pointage DNS via la zone DNS Lightsail — voir [docs/07-DEPLOIEMENT/AWS-LIGHTSAIL.md](AWS-LIGHTSAIL.md). Une fois ce script exécuté, revenez ici à la section [Déploiement](#déploiement) ci-dessous.

### 1. Un serveur (VPS) avec Docker installé

N'importe quel VPS Linux avec au moins 2 Go de RAM convient. Installez Docker (avec le plugin Compose v2) en suivant [la documentation officielle](https://docs.docker.com/engine/install/) — la commande d'installation exacte dépend de la distribution de votre serveur.

Ouvrez les ports 80 et 443 dans le pare-feu du serveur (obligatoire pour que Caddy puisse obtenir le certificat SSL et servir l'application).

### 2. Un nom de domaine pointant vers le serveur

Chez votre registrar/fournisseur DNS (OVH, Gandi, Cloudflare, Route53, etc.), créez un enregistrement DNS de type **A** :

| Type | Nom | Valeur |
|---|---|---|
| A | `erp` (ou `@` pour la racine du domaine) | l'adresse IP publique de votre serveur |

Exemple : si votre domaine est `example.com` et que vous voulez servir l'application sur `erp.example.com`, créez un enregistrement A pour `erp` pointant vers l'IP du serveur.

Vérifiez la propagation avant de continuer :

```bash
dig +short erp.example.com
# doit afficher l'IP de votre serveur
```

La propagation DNS peut prendre de quelques minutes à 24h selon le registrar.

## Déploiement

Sur le serveur cible :

```bash
git clone <url-du-dépôt> life-mdg-erp
cd life-mdg-erp
git checkout claude/life-mdg-erp-setup-aksh8m   # ou la branche à déployer

cp .env.example .env
```

Éditez `.env` et renseignez au minimum ces variables (voir `docs/07-DEPLOIEMENT/ENV-PRODUCTION.md` pour la liste complète des variables à revoir en production) :

```bash
APP_DOMAIN=erp.example.com        # le domaine configuré ci-dessus
DB_DATABASE=life_mdg_erp
DB_USERNAME=life_mdg
DB_PASSWORD=<mot-de-passe-fort>
DB_ROOT_PASSWORD=<mot-de-passe-fort-différent>
APP_ENV=production
APP_DEBUG=false
```

Puis une seule commande :

```bash
./scripts/deploy.sh
```

Ce script (idempotent — peut être relancé sans risque, y compris après un `git pull` pour mettre à jour l'application) :

1. Vérifie que Docker et le plugin Compose sont installés.
2. Construit et démarre toute la stack (`docker compose -f docker-compose.prod.yml up -d --build`).
3. Génère `APP_KEY` automatiquement si absent (première installation uniquement).
4. Attend que l'application soit prête (healthcheck).
5. Exécute les migrations (+ le seed initial, à la première installation).
6. Vérifie que `https://$APP_DOMAIN/api/health` répond bien — confirmant à la fois que Caddy a obtenu son certificat SSL et que l'application fonctionne.

À la fin, l'application est accessible sur `https://erp.example.com`.

## Vérification finale

- Ouvrez `https://votre-domaine` dans un navigateur — le cadenas SSL doit apparaître (certificat émis par Let's Encrypt, visible en cliquant sur le cadenas).
- `curl https://votre-domaine/api/health` doit répondre `200`.
- `docker compose -f docker-compose.prod.yml ps` doit lister 7 services (`app`, `queue`, `scheduler`, `reverb`, `mysql`, `redis`, `caddy` — `app-publish` s'arrête normalement une fois `public/` copié, ne pas s'attendre à le voir `Up`), tous `Up` (et `app` en `healthy`).

## Mettre à jour l'application

```bash
git pull
./scripts/deploy.sh
```

Le script reconstruit l'image, redémarre les conteneurs et applique les migrations en attente — sans réinitialiser la base de données ni les volumes existants.

## Dépannage

| Symptôme | Cause probable | Diagnostic |
|---|---|---|
| Le script bloque à l'étape 6 ("healthcheck") | DNS pas encore propagé, ou pare-feu bloquant le port 80/443 | `dig +short $APP_DOMAIN` doit renvoyer l'IP du serveur ; `docker compose -f docker-compose.prod.yml logs caddy` |
| Caddy log affiche des erreurs ACME/Let's Encrypt | Le port 80 doit être joignable depuis Internet pour la validation HTTP-01 du certificat — un pare-feu ou un NAT peut le bloquer | `curl -I http://$APP_DOMAIN` depuis une machine externe |
| `app` reste `unhealthy` | Erreur au démarrage de Laravel (config, base de données injoignable) | `docker compose -f docker-compose.prod.yml logs app` |
| La base de données ne démarre pas | `DB_ROOT_PASSWORD` absent ou vide dans `.env` | Le script refuse de démarrer si cette variable est vide — vérifiez `.env` |
| Page blanche / erreur 502 malgré un certificat SSL valide | php-fpm indisponible | `docker compose -f docker-compose.prod.yml logs app` puis `docker compose -f docker-compose.prod.yml restart app` |

## Ce que ce guide ne couvre pas

- **Sauvegardes** : voir `docs/07-DEPLOIEMENT/ENV-PRODUCTION.md` (section `BACKUP_*`, `spatie/laravel-backup`) — non automatisé par `scripts/deploy.sh`.
- **Déploiement continu automatique** (push sur `main` → déploiement) : voir `.github/workflows/deploy.yml`, qui build+push une image vers GHCR puis se connecte en SSH au serveur pour relancer la stack — nécessite d'avoir déjà fait le déploiement manuel initial décrit ci-dessus sur le serveur cible, et de configurer les secrets GitHub listés dans `docs/07-DEPLOIEMENT/README.md`.
- **Multi-serveur / haute disponibilité** : cette stack est conçue pour un VPS unique, conformément au périmètre demandé (simplicité).
