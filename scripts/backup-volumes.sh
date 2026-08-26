#!/usr/bin/env bash
#
# Sauvegarde applicative des volumes Docker nommés de la stack de production
# (docker-compose.prod.yml) — un fichier .tar.gz par volume, jamais une
# archive combinée. Complète `backup:database` (Chantier 14/28), qui ne
# couvre que la base de données via un dump logique portable.
#
# Portée volontairement limitée à 4 des 7 volumes déclarés (voir
# docs/07-DEPLOIEMENT/HETZNER.md § "Sauvegarde/restauration") :
#   - storage_data     : fichiers utilisateurs réels
#   - redis_data       : jobs de file d'attente + cache (appendonly)
#   - meilisearch_data : index de recherche (coûteux à reconstruire)
#   - caddy_data       : certificats Let's Encrypt (évite de re-déclencher
#                        leur émission — et ses limites de taux — après une
#                        restauration)
# Exclus délibérément :
#   - mysql_data    : déjà couvert par `backup:database` (dump logique,
#                     restaurable avec réconciliation de schéma — un tar
#                     binaire séparé serait redondant et incohérent)
#   - app_public    : entièrement régénéré au démarrage par le conteneur
#                     `app-publish`, aucune donnée réelle
#   - caddy_config  : état interne trivial, régénéré automatiquement
#
# Limitation connue, documentée plutôt que silencieusement ignorée : ce
# script archive chaque volume "à chaud", pendant que les conteneurs
# tournent — pas d'arrêt de service pour garantir une cohérence parfaite.
# Risque pratique faible pour ces 4 volumes (fichiers simples, fichier AOF
# Redis résilient à une lecture partielle, index de recherche/certificats
# reconstructibles) ; voir `backup:database`/`backup:restore` pour la seule
# donnée où la cohérence transactionnelle est réellement critique.
#
# Idempotent au sens où il ne modifie jamais l'état de la stack — lecture
# seule sur les volumes existants. Planifié quotidiennement via
# deploy/systemd/life-mdg-erp-backup-volumes.timer (voir scripts/install-resilience.sh).

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

COMPOSE_FILE="docker-compose.prod.yml"
BACKUP_DIR="storage/backups/volumes"
ARCHIVER_IMAGE="alpine:3.20"
LOG_FILE="${BACKUP_VOLUMES_LOG_FILE:-storage/logs/backup-volumes.log}"

log()  { printf '[%s] %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$1" | tee -a "$LOG_FILE"; }
warn() { printf '[%s] AVERTISSEMENT: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$1" | tee -a "$LOG_FILE" >&2; }
die()  { printf '[%s] ERREUR: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$1" | tee -a "$LOG_FILE" >&2; exit 1; }

mkdir -p "$BACKUP_DIR" "$(dirname "$LOG_FILE")"

command -v docker >/dev/null 2>&1 || die "Docker n'est pas installé."
[ -f "$COMPOSE_FILE" ] || die "$COMPOSE_FILE introuvable — lancez ce script depuis la racine du dépôt."

RETENTION_DAYS=30
if [ -f .env ]; then
    # shellcheck disable=SC1091
    RETENTION_DAYS=$(grep -m1 '^BACKUP_RETENTION_DAYS=' .env | cut -d= -f2- || true)
    [ -n "$RETENTION_DAYS" ] || RETENTION_DAYS=30
fi

# ── Résolution des noms physiques de volume (labels Docker Compose, pas une
# convention de nommage supposée — robuste à un COMPOSE_PROJECT_NAME
# personnalisé) : on inspecte le conteneur du service qui monte chaque
# volume et on lit le vrai nom du volume monté à la destination attendue.
resolve_volume() {
    local service="$1" dest="$2" cid
    cid=$(docker compose -f "$COMPOSE_FILE" ps -q "$service" 2>/dev/null || true)
    [ -n "$cid" ] || { echo ""; return; }
    docker inspect --format '{{ range .Mounts }}{{ .Destination }} {{ .Name }}{{ "\n" }}{{ end }}' "$cid" \
        | awk -v d="$dest" '$1==d {print $2}'
}

TIMESTAMP="$(date -u +'%Y%m%dT%H%M%SZ')"
declare -A TARGETS=(
    [storage_data]="app:/app/storage/app"
    [redis_data]="redis:/data"
    [meilisearch_data]="meilisearch:/meili_data"
    [caddy_data]="caddy:/data"
)

failures=0
for logical_name in "${!TARGETS[@]}"; do
    service="${TARGETS[$logical_name]%%:*}"
    dest="${TARGETS[$logical_name]#*:}"

    volume_name=$(resolve_volume "$service" "$dest")
    if [ -z "$volume_name" ]; then
        warn "Volume '$logical_name' introuvable (service '$service' non démarré ?) — ignoré pour cette exécution."
        failures=$((failures + 1))
        continue
    fi

    archive_file="${logical_name}_${TIMESTAMP}.tar.gz"
    log "Sauvegarde de '$logical_name' (volume Docker réel : $volume_name) → $BACKUP_DIR/$archive_file..."
    docker run --rm \
        -v "${volume_name}:/source:ro" \
        -v "$(pwd)/${BACKUP_DIR}:/backup" \
        "$ARCHIVER_IMAGE" \
        tar czf "/backup/${archive_file}" -C /source . \
        >>"$LOG_FILE" 2>&1
    log "  → OK ($(du -h "${BACKUP_DIR}/${archive_file}" | cut -f1))"
done

# ── Rétention locale — même variable BACKUP_RETENTION_DAYS que backup:cleanup
# (config/backup.php), pour une politique cohérente entre DB et volumes. ────
log "Purge des archives de volumes de plus de ${RETENTION_DAYS} jours..."
find "$BACKUP_DIR" -name '*.tar.gz' -type f -mtime "+${RETENTION_DAYS}" -print -delete | while read -r deleted; do
    log "  supprimé : $deleted"
done

if [ "$failures" -gt 0 ]; then
    warn "$failures volume(s) n'ont pas pu être sauvegardés cette exécution — voir $LOG_FILE."
    exit 1
fi

log "Sauvegarde des volumes terminée (horodatage : $TIMESTAMP)."
