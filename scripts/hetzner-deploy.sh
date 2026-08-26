#!/usr/bin/env bash
#
# Provisionnement d'un serveur Hetzner Cloud (par défaut : type cx33) pour
# life-mdg-erp : crée le serveur (si absent), lui attache un pare-feu limité
# à 22/80/443, crée une zone Hetzner DNS pour le domaine configuré et pointe
# le sous-domaine vers l'IPv4 publique du serveur. Idempotent — peut être
# relancé sans risque, chaque étape vérifie l'existant avant de créer quoi
# que ce soit.
#
# Ce script fait UNIQUEMENT le provisionnement de l'infrastructure. Une fois
# le serveur prêt et le DNS délégué (voir l'étape manuelle affichée à la
# fin), le déploiement applicatif se fait avec le script générique existant,
# depuis un clone du dépôt sur le serveur :
#   scripts/deploy.sh
#
# Voir docs/07-DEPLOIEMENT/HETZNER.md pour la marche à suivre complète.
#
# Deux authentifications distinctes sont nécessaires : le contexte `hcloud`
# (compute/pare-feu, un jeton API par projet Hetzner Cloud) et un jeton
# séparé pour l'API Hetzner DNS Console (https://dns.hetzner.com/api/v1/),
# qui n'a pas de CLI officielle — ce script y accède directement en
# `curl`+`jq`, même précédent déjà établi côté AWS Lightsail pour l'API Route
# 53 brute.

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

CONFIG_FILE="deploy/hetzner.config"
CONFIG_EXAMPLE="deploy/hetzner.config.example"
DNS_API_BASE="https://dns.hetzner.com/api/v1"

log()  { printf '\033[1;32m[hetzner]\033[0m %s\n' "$1"; }
warn() { printf '\033[1;33m[hetzner]\033[0m %s\n' "$1"; }
die()  { printf '\033[1;31m[hetzner] ERREUR:\033[0m %s\n' "$1" >&2; exit 1; }

# ── 1. Config ───────────────────────────────────────────────────────────────
if [ ! -f "$CONFIG_FILE" ]; then
    log "Aucun $CONFIG_FILE trouvé — copie depuis $CONFIG_EXAMPLE."
    cp "$CONFIG_EXAMPLE" "$CONFIG_FILE"
    warn "Éditez $CONFIG_FILE avant de relancer ce script (nom de serveur, type, région, domaine, clé SSH, jeton DNS)."
    exit 1
fi

# shellcheck disable=SC1090
set -a; source "$CONFIG_FILE"; set +a

REQUIRED_VARS=(HETZNER_SERVER_NAME HETZNER_SERVER_TYPE HETZNER_LOCATION HETZNER_IMAGE HETZNER_SSH_KEY_NAME HETZNER_DOMAIN HETZNER_DNS_API_TOKEN)
missing=()
for var in "${REQUIRED_VARS[@]}"; do
    [ -z "${!var:-}" ] && missing+=("$var")
done
if [ ${#missing[@]} -gt 0 ]; then
    die "Variable(s) manquante(s) dans $CONFIG_FILE : ${missing[*]}"
fi

FQDN="${HETZNER_DOMAIN}"
if [ -n "${HETZNER_SUBDOMAIN:-}" ]; then
    FQDN="${HETZNER_SUBDOMAIN}.${HETZNER_DOMAIN}"
fi

FIREWALL_NAME="life-mdg-erp"

# ── 2. Prérequis ────────────────────────────────────────────────────────────
command -v hcloud >/dev/null 2>&1 || die "Le Hetzner Cloud CLI (hcloud) n'est pas installé. Voir https://github.com/hetznercloud/cli#installation"
command -v jq >/dev/null 2>&1 || die "jq n'est pas installé (nécessaire pour parser les réponses de l'API Hetzner DNS Console, qui n'a pas de CLI officielle)."
command -v curl >/dev/null 2>&1 || die "curl n'est pas installé."

log "Vérification du contexte hcloud actif..."
ACTIVE_CONTEXT=$(hcloud context active 2>/dev/null || echo "")
[ -n "$ACTIVE_CONTEXT" ] || die "Aucun contexte hcloud actif. Créez-en un avec 'hcloud context create <nom>' (il vous demandera un jeton API du projet Hetzner Cloud ciblé — Cloud Console → Sécurité → Jetons API)."
log "Contexte actif : $ACTIVE_CONTEXT"

# ── 3. Validation du type de serveur/image contre l'API réelle ────────────
log "Validation du type de serveur ($HETZNER_SERVER_TYPE)..."
if ! hcloud server-type describe "$HETZNER_SERVER_TYPE" >/dev/null 2>&1; then
    warn "Type de serveur '$HETZNER_SERVER_TYPE' introuvable. Types disponibles :"
    hcloud server-type list
    die "Corrigez HETZNER_SERVER_TYPE dans $CONFIG_FILE."
fi

log "Validation de l'emplacement ($HETZNER_LOCATION)..."
if ! hcloud location describe "$HETZNER_LOCATION" >/dev/null 2>&1; then
    warn "Emplacement '$HETZNER_LOCATION' introuvable. Emplacements disponibles :"
    hcloud location list
    die "Corrigez HETZNER_LOCATION dans $CONFIG_FILE."
fi

log "Validation de l'image ($HETZNER_IMAGE)..."
if ! hcloud image describe "$HETZNER_IMAGE" >/dev/null 2>&1; then
    warn "Image '$HETZNER_IMAGE' introuvable. Images système disponibles :"
    hcloud image list --type system -o columns=name,description
    die "Corrigez HETZNER_IMAGE dans $CONFIG_FILE."
fi

# ── 4. Clé SSH (obligatoire — contrairement à GCP, Hetzner Cloud n'a pas de
# mécanisme de clé éphémère type "hcloud server ssh" gérant sa propre paire) ─
if ! hcloud ssh-key describe "$HETZNER_SSH_KEY_NAME" >/dev/null 2>&1; then
    if [ -n "${HETZNER_SSH_PUBLIC_KEY_FILE:-}" ] && [ -f "$HETZNER_SSH_PUBLIC_KEY_FILE" ]; then
        log "Clé SSH '$HETZNER_SSH_KEY_NAME' absente du projet Hetzner — création depuis $HETZNER_SSH_PUBLIC_KEY_FILE..."
        hcloud ssh-key create --name "$HETZNER_SSH_KEY_NAME" --public-key-from-file "$HETZNER_SSH_PUBLIC_KEY_FILE" >/dev/null
    else
        die "Clé SSH '$HETZNER_SSH_KEY_NAME' introuvable dans le projet Hetzner (hcloud ssh-key list) et HETZNER_SSH_PUBLIC_KEY_FILE n'est pas renseigné dans $CONFIG_FILE pour en créer une. Sans clé SSH enregistrée au moment de la création, Hetzner Cloud n'offre aucun accès de secours pratique au serveur — contrairement à GCP, ce script ne peut pas se rabattre sur une paire de clés éphémère."
    fi
else
    log "Clé SSH '$HETZNER_SSH_KEY_NAME' déjà présente dans le projet."
fi

# ── 5. Serveur (idempotent) ─────────────────────────────────────────────────
if hcloud server describe "$HETZNER_SERVER_NAME" >/dev/null 2>&1; then
    log "Serveur '$HETZNER_SERVER_NAME' déjà provisionné."
else
    log "Création du serveur '$HETZNER_SERVER_NAME' ($HETZNER_SERVER_TYPE, $HETZNER_IMAGE, $HETZNER_LOCATION)..."
    hcloud server create \
        --name "$HETZNER_SERVER_NAME" \
        --type "$HETZNER_SERVER_TYPE" \
        --image "$HETZNER_IMAGE" \
        --location "$HETZNER_LOCATION" \
        --ssh-key "$HETZNER_SSH_KEY_NAME" \
        >/dev/null
fi

log "Attente du démarrage du serveur (état 'running')..."
tries=0
until [ "$(hcloud server describe "$HETZNER_SERVER_NAME" -o json | jq -r '.status')" = "running" ]; do
    tries=$((tries + 1))
    if [ "$tries" -gt 30 ]; then
        die "Le serveur n'est pas passé à l'état 'running' après 5 minutes."
    fi
    sleep 10
done
log "Serveur en cours d'exécution."

SERVER_IP=$(hcloud server describe "$HETZNER_SERVER_NAME" -o json | jq -r '.public_net.ipv4.ip')
[ -n "$SERVER_IP" ] && [ "$SERVER_IP" != "null" ] || die "Impossible de résoudre l'IPv4 publique du serveur depuis l'API Hetzner Cloud."
log "IP publique : $SERVER_IP"

# ── 6. Pare-feu (toujours réappliqué, déclaratif) ──────────────────────────
# Hetzner Cloud n'ouvre aucun port entrant par défaut — les 3 ports sont
# donc explicitement couverts ici, via un objet Firewall dédié appliqué au
# serveur (le modèle Hetzner, distinct des règles GCP par tag réseau ou des
# groupes de sécurité AWS).
if ! hcloud firewall describe "$FIREWALL_NAME" >/dev/null 2>&1; then
    log "Création du pare-feu '$FIREWALL_NAME' (22 SSH, 80 HTTP, 443 HTTPS)..."
    hcloud firewall create --name "$FIREWALL_NAME" >/dev/null
    for port in 22 80 443; do
        hcloud firewall add-rule "$FIREWALL_NAME" \
            --direction in --protocol tcp --port "$port" \
            --source-ips 0.0.0.0/0 --source-ips ::/0 \
            >/dev/null
    done
else
    log "Pare-feu '$FIREWALL_NAME' déjà présent."
fi

if ! hcloud firewall describe "$FIREWALL_NAME" -o json | jq -e --arg srv "$HETZNER_SERVER_NAME" '.applied_to[]? | select(.server.name == $srv)' >/dev/null 2>&1; then
    log "Application du pare-feu '$FIREWALL_NAME' au serveur '$HETZNER_SERVER_NAME'..."
    hcloud firewall apply-to-resource "$FIREWALL_NAME" --type server --server "$HETZNER_SERVER_NAME" >/dev/null
fi

# ── 7. Zone Hetzner DNS + enregistrement A (idempotents, API REST brute) ──
# Hetzner DNS Console n'a pas de CLI officielle — https://dns.hetzner.com/api/v1/,
# authentifié par un jeton distinct de celui du contexte hcloud (créé dans
# la Hetzner DNS Console, pas dans le Cloud Console).
dns_api() {
    local method="$1" path="$2" data="${3:-}"
    if [ -n "$data" ]; then
        curl -fsS -X "$method" "${DNS_API_BASE}${path}" \
            -H "Auth-API-Token: ${HETZNER_DNS_API_TOKEN}" \
            -H "Content-Type: application/json" \
            -d "$data"
    else
        curl -fsS -X "$method" "${DNS_API_BASE}${path}" \
            -H "Auth-API-Token: ${HETZNER_DNS_API_TOKEN}"
    fi
}

log "Vérification de la zone Hetzner DNS pour '$HETZNER_DOMAIN'..."
ZONE_JSON=$(dns_api GET "/zones?name=${HETZNER_DOMAIN}" || echo '{"zones":[]}')
ZONE_ID=$(echo "$ZONE_JSON" | jq -r '.zones[0].id // empty')

if [ -z "$ZONE_ID" ]; then
    log "Création de la zone Hetzner DNS pour '$HETZNER_DOMAIN'..."
    ZONE_ID=$(dns_api POST "/zones" "$(jq -n --arg name "$HETZNER_DOMAIN" '{name: $name, ttl: 300}')" | jq -r '.zone.id')
    [ -n "$ZONE_ID" ] && [ "$ZONE_ID" != "null" ] || die "Échec de création de la zone DNS — vérifiez HETZNER_DNS_API_TOKEN dans $CONFIG_FILE."
else
    log "Zone Hetzner DNS déjà présente (id: $ZONE_ID)."
fi

RECORD_NAME="${HETZNER_SUBDOMAIN:-@}"
RECORDS_JSON=$(dns_api GET "/records?zone_id=${ZONE_ID}")
EXISTING_RECORD_ID=$(echo "$RECORDS_JSON" | jq -r --arg name "$RECORD_NAME" '.records[] | select(.type=="A" and .name==$name) | .id' | head -n1)
EXISTING_VALUE=$(echo "$RECORDS_JSON" | jq -r --arg name "$RECORD_NAME" '.records[] | select(.type=="A" and .name==$name) | .value' | head -n1)

RECORD_PAYLOAD=$(jq -n --arg zone "$ZONE_ID" --arg name "$RECORD_NAME" --arg value "$SERVER_IP" \
    '{zone_id: $zone, type: "A", name: $name, value: $value, ttl: 300}')

if [ -n "$EXISTING_RECORD_ID" ] && [ "$EXISTING_VALUE" = "$SERVER_IP" ]; then
    log "Enregistrement DNS A pour '$FQDN' déjà à jour ($SERVER_IP)."
elif [ -n "$EXISTING_RECORD_ID" ]; then
    log "Enregistrement DNS A existant pour '$FQDN' pointe vers $EXISTING_VALUE — mise à jour vers $SERVER_IP..."
    dns_api PUT "/records/${EXISTING_RECORD_ID}" "$RECORD_PAYLOAD" >/dev/null
else
    log "Création de l'enregistrement DNS A pour '$FQDN' → $SERVER_IP..."
    dns_api POST "/records" "$RECORD_PAYLOAD" >/dev/null
fi

# ── 8. Écriture de l'IP dans la config locale (informatif uniquement) ─────
if grep -q '^HETZNER_SERVER_IP=' "$CONFIG_FILE"; then
    sed -i.bak "s/^HETZNER_SERVER_IP=.*/HETZNER_SERVER_IP=${SERVER_IP}/" "$CONFIG_FILE" && rm -f "${CONFIG_FILE}.bak"
else
    echo "HETZNER_SERVER_IP=${SERVER_IP}" >> "$CONFIG_FILE"
fi

# ── 9. Résumé ────────────────────────────────────────────────────────────────
echo
log "Provisionnement Hetzner Cloud terminé."
log "  Serveur   : $HETZNER_SERVER_NAME ($HETZNER_SERVER_TYPE, $HETZNER_LOCATION)"
log "  IP        : $SERVER_IP"
log "  Domaine   : $FQDN"
echo
warn "ÉTAPE MANUELLE OBLIGATOIRE — délégation DNS (non scriptable) :"
warn "  Chez le bureau d'enregistrement de '$HETZNER_DOMAIN', configurez les"
warn "  serveurs de noms (NS) vers ceux de la zone Hetzner DNS créée ci-dessus. Récupérez-les avec :"
warn "    curl -fsS '${DNS_API_BASE}/zones/${ZONE_ID}' -H \"Auth-API-Token: \$HETZNER_DNS_API_TOKEN\" | jq -r '.zone.ns[]'"
warn "  (si vous gérez déjà le DNS de $HETZNER_DOMAIN ailleurs sans vouloir déléguer"
warn "  toute la zone, créez plutôt manuellement un enregistrement A pour $FQDN → $SERVER_IP"
warn "  chez votre fournisseur DNS actuel, et ignorez cette délégation NS.)"
echo
warn "AVERTISSEMENT COÛT : ce serveur Hetzner Cloud facture en continu tant qu'il"
warn "  existe, indépendamment de son utilisation. Voir docs/07-DEPLOIEMENT/HETZNER.md."
echo
log "Une fois le DNS propagé (dig +short $FQDN doit renvoyer $SERVER_IP), déployez l'application :"
log "  ssh root@$SERVER_IP"
log "  git clone <votre-dépôt> && cd life-mdg-erp && ./scripts/deploy.sh"
log "  sudo ./scripts/install-resilience.sh   # reprise auto + rattrapage de code + sauvegarde des volumes"
