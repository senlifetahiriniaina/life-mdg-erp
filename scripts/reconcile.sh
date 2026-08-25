#!/usr/bin/env bash
#
# Réconciliation périodique de production : rattrape le code si le dépôt
# distant a avancé (le cas "clone à mettre à jour" — voir CLAUDE.md §
# "Résilience du déploiement"), et remet en route tout conteneur de la stack
# qui ne serait plus "Up" malgré `restart: unless-stopped`.
#
# Conçu pour être appelé périodiquement (voir deploy/systemd/) plutôt que
# déclenché par un push — c'est ce qui permet de rattraper une VM qui était
# arrêtée/en pause au moment où `.github/workflows/deploy.yml` a tourné (une
# connexion SSH vers une VM éteinte échoue simplement, sans nouvelle
# tentative). Reproduit délibérément la même séquence sûre déjà éprouvée
# dans scripts/deploy.sh (attente de healthcheck, migrations) et dans
# .github/workflows/deploy.yml (tag `previous` + rollback), plutôt que
# d'inventer un nouveau mécanisme.
#
# Idempotent et silencieux quand rien n'a changé — pensé pour tourner sans
# supervision humaine (cron/systemd timer), donc n'écrit dans les logs que ce
# qui est réellement actionnable.

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

COMPOSE_FILE="docker-compose.prod.yml"
LOG_FILE="${RECONCILE_LOG_FILE:-storage/logs/reconcile.log}"
HEALTH_TIMEOUT_TRIES="${RECONCILE_HEALTH_TRIES:-18}"   # 18 x 10s = 3 min
HTTP_TIMEOUT_TRIES="${RECONCILE_HTTP_TRIES:-12}"        # 12 x 10s = 2 min

mkdir -p "$(dirname "$LOG_FILE")"

log()  { printf '[%s] %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$1" | tee -a "$LOG_FILE"; }
warn() { printf '[%s] AVERTISSEMENT: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$1" | tee -a "$LOG_FILE" >&2; }
die()  { printf '[%s] ERREUR: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$1" | tee -a "$LOG_FILE" >&2; exit 1; }

[ -f .env ] || die "Aucun .env trouvé — ce script suppose un premier déploiement déjà fait via scripts/deploy.sh."
# shellcheck disable=SC1091
set -a; source .env; set +a
[ -n "${APP_DOMAIN:-}" ] || die "APP_DOMAIN absent de .env."

wait_for_app_healthy() {
    local tries=0
    until [ "$(docker compose -f "$COMPOSE_FILE" ps --format '{{.Health}}' app 2>/dev/null)" = "healthy" ]; do
        tries=$((tries + 1))
        if [ "$tries" -gt "$HEALTH_TIMEOUT_TRIES" ]; then
            return 1
        fi
        sleep 10
    done
    return 0
}

check_http_health() {
    local tries=0
    until curl -fsS -o /dev/null "https://${APP_DOMAIN}/api/health" 2>/dev/null; do
        tries=$((tries + 1))
        if [ "$tries" -gt "$HTTP_TIMEOUT_TRIES" ]; then
            return 1
        fi
        sleep 10
    done
    return 0
}

# ── 1. Le dépôt distant a-t-il avancé ? ─────────────────────────────────────
branch="$(git rev-parse --abbrev-ref HEAD)"
git fetch origin "$branch" --quiet 2>>"$LOG_FILE" || { warn "git fetch a échoué (réseau ?) — nouvel essai au prochain passage."; exit 0; }

head_local="$(git rev-parse HEAD)"
head_remote="$(git rev-parse "origin/${branch}")"

if [ "$head_local" != "$head_remote" ]; then
    log "Nouveau commit détecté sur origin/${branch} (${head_local:0:8} → ${head_remote:0:8}) — rattrapage en cours."

    # Filet de sécurité rollback (même patron que .github/workflows/deploy.yml) :
    # tague l'image actuellement en service avant de la remplacer.
    current_tag="${IMAGE_TAG:-latest}"
    if docker image inspect "life-mdg-erp:${current_tag}" >/dev/null 2>&1; then
        docker tag "life-mdg-erp:${current_tag}" life-mdg-erp:previous
    fi

    if ! git pull --ff-only origin "$branch" >>"$LOG_FILE" 2>&1; then
        die "git pull --ff-only a échoué (divergence locale ?) — intervention manuelle requise."
    fi

    docker compose -f "$COMPOSE_FILE" up -d --build >>"$LOG_FILE" 2>&1

    if ! wait_for_app_healthy; then
        warn "L'application n'est pas devenue 'healthy' après le rattrapage — retour à l'image précédente."
        if docker image inspect life-mdg-erp:previous >/dev/null 2>&1; then
            IMAGE_TAG=previous docker compose -f "$COMPOSE_FILE" up -d --no-build >>"$LOG_FILE" 2>&1
            warn "Rollback effectué vers l'image 'previous'. Diagnostic : $LOG_FILE et 'docker compose -f $COMPOSE_FILE logs app'."
        else
            warn "Aucune image 'previous' disponible pour un rollback (premier rattrapage ?) — la stack reste sur le code neuf, à investiguer manuellement."
        fi
        exit 1
    fi

    docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force >>"$LOG_FILE" 2>&1 || warn "Les migrations ont échoué après rattrapage — vérifier manuellement."

    if check_http_health; then
        log "Rattrapage terminé avec succès — application à jour et saine (commit ${head_remote:0:8})."
    else
        warn "Rattrapage effectué mais /api/health ne répond pas encore — peut être transitoire (Caddy/DNS), à surveiller."
    fi
else
    # Rien de nouveau côté code — traces minimales, pas de bruit dans les logs
    # à chaque passage du timer.
    :
fi

# ── 2. Filet de sécurité : tous les conteneurs attendus sont-ils "Up" ? ────
# Un conteneur sorti d'un `restart: unless-stopped` après une boucle de
# crash prolongée (ou après un arrêt/démarrage de VM survenu entre deux
# passages du timer, avant que l'unité systemd de reprise n'ait eu le temps
# d'agir) doit être relevé plutôt que laissé hors service en silence.
down_services="$(docker compose -f "$COMPOSE_FILE" ps --services --status=exited,dead 2>/dev/null || true)"
if [ -n "$down_services" ]; then
    warn "Service(s) arrêté(s) détecté(s), relance : $(echo "$down_services" | tr '\n' ' ')"
    docker compose -f "$COMPOSE_FILE" up -d >>"$LOG_FILE" 2>&1
fi

exit 0
