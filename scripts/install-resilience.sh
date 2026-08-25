#!/usr/bin/env bash
#
# Installe la résilience de déploiement sur le serveur cible (voir CLAUDE.md
# § "Résilience du déploiement" et
# docs/07-DEPLOIEMENT/GUIDE-DEPLOIEMENT-SIMPLE.md § "Résilience") :
#
#   1. Une unité systemd qui relève automatiquement la stack Docker Compose
#      au démarrage de la VM (couvre le cas "VM arrêtée/mise en pause").
#   2. Une minuterie systemd qui rattrape périodiquement le dépôt distant si
#      du code a été poussé pendant que la VM était hors ligne, avec
#      rollback automatique en cas d'échec (couvre le cas "clone à mettre à
#      jour").
#
# À exécuter une fois, après un premier `scripts/deploy.sh` réussi. Requiert
# systemd et les droits root (sudo). Idempotent — peut être relancé sans
# risque pour réappliquer les gabarits (ex. après un `git pull` qui aurait
# modifié ces fichiers).

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
APP_DIR="$(pwd)"

log()  { printf '\033[1;32m[install-resilience]\033[0m %s\n' "$1"; }
warn() { printf '\033[1;33m[install-resilience]\033[0m %s\n' "$1"; }
die()  { printf '\033[1;31m[install-resilience] ERREUR:\033[0m %s\n' "$1" >&2; exit 1; }

command -v systemctl >/dev/null 2>&1 || die "systemd (systemctl) n'est pas disponible sur cette machine — ce script est spécifique à une distribution Linux avec systemd comme PID 1. Sur une machine sans systemd, un mécanisme équivalent (ex. crontab @reboot + */15 * * * *) doit être mis en place manuellement — voir scripts/reconcile.sh, qui reste utilisable indépendamment."
[ "$EUID" -eq 0 ] || die "Ce script doit être lancé avec les droits root (sudo $0) pour installer des unités systemd."
[ -f "$APP_DIR/docker-compose.prod.yml" ] || die "docker-compose.prod.yml introuvable dans $APP_DIR — lancez ce script depuis la racine du dépôt cloné."
[ -f "$APP_DIR/.env" ] || die "Aucun .env trouvé — exécutez d'abord scripts/deploy.sh (premier déploiement)."

UNIT_DIR="/etc/systemd/system"
SRC_DIR="$APP_DIR/deploy/systemd"

log "Installation des unités systemd depuis $SRC_DIR vers $UNIT_DIR (répertoire applicatif : $APP_DIR)..."

for unit in life-mdg-erp.service life-mdg-erp-reconcile.service life-mdg-erp-reconcile.timer; do
    [ -f "$SRC_DIR/$unit" ] || die "Gabarit manquant : $SRC_DIR/$unit"
    sed "s#{{APP_DIR}}#${APP_DIR}#g" "$SRC_DIR/$unit" > "$UNIT_DIR/$unit"
    log "  → $UNIT_DIR/$unit"
done

log "Rechargement de systemd..."
systemctl daemon-reload

log "Activation de la reprise au démarrage (life-mdg-erp.service)..."
systemctl enable --now life-mdg-erp.service

log "Activation de la minuterie de réconciliation (toutes les 15 min, life-mdg-erp-reconcile.timer)..."
systemctl enable --now life-mdg-erp-reconcile.timer

echo
log "Installation terminée. Vérifications utiles :"
echo "  systemctl status life-mdg-erp.service"
echo "  systemctl list-timers life-mdg-erp-reconcile.timer"
echo "  journalctl -u life-mdg-erp-reconcile.service -n 50"
echo "  tail -f ${APP_DIR}/storage/logs/reconcile.log"
