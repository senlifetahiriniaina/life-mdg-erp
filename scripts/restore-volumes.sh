#!/usr/bin/env bash
#
# Restaure un ou plusieurs volumes Docker nommés depuis les archives
# produites par scripts/backup-volumes.sh.
#
# Usage :
#   scripts/restore-volumes.sh <horodatage>              # restaure les 4 volumes
#   scripts/restore-volumes.sh <horodatage> <volume>      # restaure un seul volume
#
# <horodatage> est celui affiché à la fin d'une exécution de
# scripts/backup-volumes.sh (format YYYYMMDDTHHMMSSZ), et se retrouve dans
# le nom de chaque fichier archive : storage/backups/volumes/<volume>_<horodatage>.tar.gz
# <volume> (optionnel) est l'un de : storage_data, redis_data,
# meilisearch_data, caddy_data.
#
# Refuse explicitement toute restauration si le fichier attendu pour un
# volume demandé est absent — jamais de restauration partielle silencieuse.
# Arrête uniquement le(s) service(s) qui montent le volume concerné pendant
# l'extraction (pas toute la stack), puis relance l'ensemble et attend le
# healthcheck de l'application avant de rendre la main.

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

COMPOSE_FILE="docker-compose.prod.yml"
BACKUP_DIR="storage/backups/volumes"
ARCHIVER_IMAGE="alpine:3.20"
LOG_FILE="${BACKUP_VOLUMES_LOG_FILE:-storage/logs/backup-volumes.log}"

log()  { printf '[%s] %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$1" | tee -a "$LOG_FILE"; }
warn() { printf '[%s] AVERTISSEMENT: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$1" | tee -a "$LOG_FILE" >&2; }
die()  { printf '[%s] ERREUR: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$1" | tee -a "$LOG_FILE" >&2; exit 1; }

mkdir -p "$(dirname "$LOG_FILE")"

TIMESTAMP="${1:-}"
[ -n "$TIMESTAMP" ] || die "Usage : $0 <horodatage> [volume]  (ex. $0 20260826T020000Z)"
REQUESTED_VOLUME="${2:-}"

command -v docker >/dev/null 2>&1 || die "Docker n'est pas installé."
[ -f "$COMPOSE_FILE" ] || die "$COMPOSE_FILE introuvable — lancez ce script depuis la racine du dépôt."

declare -A TARGETS=(
    [storage_data]="app:/app/storage/app"
    [redis_data]="redis:/data"
    [meilisearch_data]="meilisearch:/meili_data"
    [caddy_data]="caddy:/data"
)
# Services à arrêter pendant la restauration de chaque volume — storage_data
# est monté par 3 services (app/queue/scheduler), les 3 autres par un seul.
declare -A STOP_SERVICES=(
    [storage_data]="app queue scheduler"
    [redis_data]="redis"
    [meilisearch_data]="meilisearch"
    [caddy_data]="caddy"
)

if [ -n "$REQUESTED_VOLUME" ]; then
    [ -n "${TARGETS[$REQUESTED_VOLUME]+x}" ] || die "Volume inconnu : '$REQUESTED_VOLUME'. Valeurs valides : ${!TARGETS[*]}"
    VOLUMES_TO_RESTORE=("$REQUESTED_VOLUME")
else
    VOLUMES_TO_RESTORE=("${!TARGETS[@]}")
fi

# ── 1. Vérification que TOUTES les archives demandées existent avant de
# toucher quoi que ce soit — jamais de restauration partielle silencieuse. ──
missing=()
for vol in "${VOLUMES_TO_RESTORE[@]}"; do
    archive_file="${BACKUP_DIR}/${vol}_${TIMESTAMP}.tar.gz"
    [ -f "$archive_file" ] || missing+=("$archive_file")
done
if [ ${#missing[@]} -gt 0 ]; then
    die "Archive(s) introuvable(s) pour l'horodatage '$TIMESTAMP' : ${missing[*]}. Listez les horodatages disponibles avec : ls $BACKUP_DIR"
fi

resolve_volume() {
    local service="$1" dest="$2" cid
    cid=$(docker compose -f "$COMPOSE_FILE" ps -q "$service" 2>/dev/null || true)
    [ -n "$cid" ] || { echo ""; return; }
    docker inspect --format '{{ range .Mounts }}{{ .Destination }} {{ .Name }}{{ "\n" }}{{ end }}' "$cid" \
        | awk -v d="$dest" '$1==d {print $2}'
}

log "Restauration demandée pour : ${VOLUMES_TO_RESTORE[*]} (horodatage $TIMESTAMP)"

# ── 2. Pour chaque volume : arrêter le(s) service(s) concernés, vider le
# volume, extraire l'archive, relancer immédiatement le(s) service(s). ────
for vol in "${VOLUMES_TO_RESTORE[@]}"; do
    service="${TARGETS[$vol]%%:*}"
    dest="${TARGETS[$vol]#*:}"
    volume_name=$(resolve_volume "$service" "$dest")
    [ -n "$volume_name" ] || die "Volume Docker réel introuvable pour '$vol' (service '$service' jamais démarré ?). Lancez d'abord scripts/deploy.sh."

    archive_file="${vol}_${TIMESTAMP}.tar.gz"
    # shellcheck disable=SC2086
    log "Arrêt de : ${STOP_SERVICES[$vol]}..."
    docker compose -f "$COMPOSE_FILE" stop ${STOP_SERVICES[$vol]} >>"$LOG_FILE" 2>&1

    log "Restauration de '$vol' (volume Docker réel : $volume_name) depuis $archive_file..."
    docker run --rm \
        -v "${volume_name}:/target" \
        -v "$(pwd)/${BACKUP_DIR}:/backup:ro" \
        "$ARCHIVER_IMAGE" \
        sh -c "find /target -mindepth 1 -delete && tar xzf /backup/${archive_file} -C /target" \
        >>"$LOG_FILE" 2>&1

    log "  → OK."
done

# ── 3. Relance de toute la stack (couvre les services arrêtés ci-dessus +
# leurs dépendants) et attente du healthcheck de l'application. ────────────
log "Relance de la stack..."
docker compose -f "$COMPOSE_FILE" up -d >>"$LOG_FILE" 2>&1

tries=0
until [ "$(docker compose -f "$COMPOSE_FILE" ps --format '{{.Health}}' app 2>/dev/null)" = "healthy" ]; do
    tries=$((tries + 1))
    if [ "$tries" -gt 30 ]; then
        die "L'application n'est pas redevenue 'healthy' après 5 minutes. Diagnostic : docker compose -f $COMPOSE_FILE logs app"
    fi
    sleep 10
done

log "Restauration terminée — stack de nouveau saine."
