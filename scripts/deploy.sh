#!/usr/bin/env bash
#
# Déploiement de production en une commande : depuis un clone frais sur un
# serveur avec Docker installé, lance toute la stack (app + queue + scheduler
# + MySQL + Redis + Caddy) et obtient un certificat SSL automatique dès que le
# DNS de APP_DOMAIN pointe vers ce serveur. Voir
# docs/07-DEPLOIEMENT/GUIDE-DEPLOIEMENT-SIMPLE.md pour le guide complet.
#
# Idempotent : peut être relancé sans risque (ex. après un `git pull`) — ne
# réinitialise ni la base de données ni les volumes existants.

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

COMPOSE_FILE="docker-compose.prod.yml"
REQUIRED_VARS=(APP_DOMAIN DB_DATABASE DB_USERNAME DB_PASSWORD DB_ROOT_PASSWORD MEILISEARCH_KEY APP_KEY)

log()  { printf '\033[1;32m[deploy]\033[0m %s\n' "$1"; }
warn() { printf '\033[1;33m[deploy]\033[0m %s\n' "$1"; }
die()  { printf '\033[1;31m[deploy] ERREUR:\033[0m %s\n' "$1" >&2; exit 1; }

# ── 1. Prérequis ────────────────────────────────────────────────────────────
command -v docker >/dev/null 2>&1 || die "Docker n'est pas installé. Voir https://docs.docker.com/engine/install/"
docker compose version >/dev/null 2>&1 || die "Le plugin 'docker compose' (v2) n'est pas disponible. Docker >= 20.10 avec compose v2 est requis."

# ── 2. .env ──────────────────────────────────────────────────────────────────
if [ ! -f .env ]; then
    log "Aucun .env trouvé — copie depuis .env.example."
    cp .env.example .env
    warn "Éditez .env avant de relancer ce script. Variables obligatoires pour ce déploiement :"
    warn "  APP_DOMAIN       — le nom de domaine de l'application (ex. erp.example.com)"
    warn "  DB_DATABASE / DB_USERNAME / DB_PASSWORD — identifiants de la base applicative"
    warn "  DB_ROOT_PASSWORD — mot de passe root MySQL (nouvelle variable, à ajouter dans .env)"
    warn "  MEILISEARCH_KEY  — clé partagée avec le conteneur meilisearch (ex. openssl rand -hex 32)"
    warn "  APP_KEY          — laissez vide, ce script le génère automatiquement au premier lancement"
    exit 1
fi

# shellcheck disable=SC1091
set -a; source .env; set +a

missing=()
for var in "${REQUIRED_VARS[@]}"; do
    if [ "$var" != "APP_KEY" ] && [ -z "${!var:-}" ]; then
        missing+=("$var")
    fi
done
if [ ${#missing[@]} -gt 0 ]; then
    die "Variable(s) manquante(s) dans .env : ${missing[*]}"
fi

if [ "${APP_DOMAIN}" = "http://localhost" ] || [ -z "${APP_DOMAIN:-}" ]; then
    die "APP_DOMAIN n'est pas configuré dans .env (valeur actuelle: '${APP_DOMAIN:-vide}')."
fi

# ── 3. Rappel DNS (étape manuelle, non scriptable) ─────────────────────────
warn "Avant de continuer : assurez-vous qu'un enregistrement DNS de type A pour"
warn "  ${APP_DOMAIN}"
warn "pointe vers l'adresse IP publique de ce serveur — sinon Caddy ne pourra pas"
warn "obtenir de certificat SSL Let's Encrypt. Propagation DNS : jusqu'à 24h,"
warn "généralement quelques minutes. Vérifiez avec : dig +short ${APP_DOMAIN}"
echo

# ── 4. Lancement de la stack ────────────────────────────────────────────────
log "Construction et démarrage de la stack Docker (app, queue, scheduler, mysql, redis, caddy)..."
docker compose -f "$COMPOSE_FILE" up -d --build

# ── 5. APP_KEY (première installation uniquement) ──────────────────────────
if [ -z "${APP_KEY:-}" ]; then
    log "APP_KEY absent — génération..."
    docker compose -f "$COMPOSE_FILE" exec -T app php artisan key:generate --force
fi

# ── 6. Attente de l'état "healthy" du conteneur app ────────────────────────
log "Attente du démarrage de l'application (healthcheck)..."
tries=0
until [ "$(docker compose -f "$COMPOSE_FILE" ps --format '{{.Health}}' app 2>/dev/null)" = "healthy" ]; do
    tries=$((tries + 1))
    if [ "$tries" -gt 30 ]; then
        die "L'application n'est pas devenue 'healthy' après 5 minutes. Diagnostic : docker compose -f $COMPOSE_FILE logs app"
    fi
    sleep 10
done
log "Application prête."

# ── 7. Migrations ───────────────────────────────────────────────────────────
already_installed=$(docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate:status 2>/dev/null | grep -c 'Ran' || true)
if [ "$already_installed" -gt 0 ]; then
    log "Base de données déjà initialisée — exécution des migrations en attente uniquement."
    docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force
else
    log "Première installation — migration + seed initial."
    docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force --seed
fi

# ── 8. Vérification finale ──────────────────────────────────────────────────
log "Vérification de l'accès HTTPS (peut échouer les premières minutes le temps que Caddy obtienne le certificat)..."
health_url="https://${APP_DOMAIN}/api/health"
attempt=0
until curl -fsS -o /dev/null "$health_url" 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -gt 12 ]; then
        warn "Impossible de joindre $health_url après 2 minutes."
        warn "Causes possibles : DNS pas encore propagé, port 80/443 bloqué par un pare-feu."
        warn "Diagnostic : docker compose -f $COMPOSE_FILE logs caddy"
        exit 1
    fi
    sleep 10
done

log "Déploiement terminé — application accessible sur https://${APP_DOMAIN}"

# ── 9. Résilience (une seule fois, optionnel) ───────────────────────────────
if command -v systemctl >/dev/null 2>&1 && [ ! -f /etc/systemd/system/life-mdg-erp.service ]; then
    echo
    warn "Résilience non installée : si la VM peut être arrêtée/mise en pause, ou si"
    warn "le code peut être poussé pendant qu'elle est hors ligne, installez la reprise"
    warn "automatique et la réconciliation périodique (voir CLAUDE.md § \"Résilience du"
    warn "déploiement\") avec :"
    warn "  sudo ./scripts/install-resilience.sh"
fi
