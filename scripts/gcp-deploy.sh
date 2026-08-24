#!/usr/bin/env bash
#
# Provisionnement d'une instance Google Compute Engine pour life-mdg-erp :
# crée l'instance (si absente), lui attache une IP externe statique, crée une
# zone Cloud DNS pour le domaine configuré et pointe le sous-domaine vers
# cette IP. Idempotent — peut être relancé sans risque, chaque étape vérifie
# l'existant avant de créer quoi que ce soit.
#
# Ce script fait UNIQUEMENT le provisionnement de l'infrastructure. Une fois
# l'instance prête et le DNS délégué (voir l'étape manuelle affichée à la
# fin), le déploiement applicatif se fait avec le script générique existant :
#   gcloud compute ssh (ou ssh <utilisateur>@<IP>) puis, depuis un clone du
#   dépôt sur le serveur :
#   scripts/deploy.sh
#
# Voir docs/07-DEPLOIEMENT/GCP.md pour la marche à suivre complète.

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

CONFIG_FILE="deploy/gcp.config"
CONFIG_EXAMPLE="deploy/gcp.config.example"

log()  { printf '\033[1;32m[gcp]\033[0m %s\n' "$1"; }
warn() { printf '\033[1;33m[gcp]\033[0m %s\n' "$1"; }
die()  { printf '\033[1;31m[gcp] ERREUR:\033[0m %s\n' "$1" >&2; exit 1; }

# ── 1. Config ───────────────────────────────────────────────────────────────
if [ ! -f "$CONFIG_FILE" ]; then
    log "Aucun $CONFIG_FILE trouvé — copie depuis $CONFIG_EXAMPLE."
    cp "$CONFIG_EXAMPLE" "$CONFIG_FILE"
    warn "Éditez $CONFIG_FILE avant de relancer ce script (projet, zone, domaine, sous-domaine, gabarit)."
    exit 1
fi

# shellcheck disable=SC1090
set -a; source "$CONFIG_FILE"; set +a

REQUIRED_VARS=(GCP_PROJECT_ID GCP_ZONE GCP_INSTANCE_NAME GCP_MACHINE_TYPE GCP_IMAGE_FAMILY GCP_IMAGE_PROJECT GCP_SSH_USERNAME GCP_DOMAIN)
missing=()
for var in "${REQUIRED_VARS[@]}"; do
    [ -z "${!var:-}" ] && missing+=("$var")
done
if [ ${#missing[@]} -gt 0 ]; then
    die "Variable(s) manquante(s) dans $CONFIG_FILE : ${missing[*]}"
fi

FQDN="${GCP_DOMAIN}"
if [ -n "${GCP_SUBDOMAIN:-}" ]; then
    FQDN="${GCP_SUBDOMAIN}.${GCP_DOMAIN}"
fi

# La région est dérivée de la zone (ex. europe-west1-b → europe-west1) — GCP
# encode déjà la région dans le nom de zone, pas besoin d'une variable séparée.
REGION="${GCP_ZONE%-*}"

NETWORK_TAG="life-mdg-erp"
STATIC_IP_NAME="${GCP_INSTANCE_NAME}-static-ip"
DNS_ZONE_NAME="${GCP_INSTANCE_NAME}-zone"

# ── 2. Prérequis ────────────────────────────────────────────────────────────
command -v gcloud >/dev/null 2>&1 || die "Le Google Cloud CLI (gcloud) n'est pas installé. Voir https://cloud.google.com/sdk/docs/install"

log "Vérification de l'authentification gcloud..."
ACTIVE_ACCOUNT=$(gcloud auth list --filter=status:ACTIVE --format='value(account)' 2>/dev/null || echo "")
[ -n "$ACTIVE_ACCOUNT" ] || die "Aucun compte gcloud actif. Lancez 'gcloud auth login' (ou configurez un compte de service avec 'gcloud auth activate-service-account')."
log "Authentifié en tant que : $ACTIVE_ACCOUNT"

# ── 3. Validation du gabarit/image contre l'API réelle ─────────────────────
log "Validation du type de machine ($GCP_MACHINE_TYPE) dans la zone $GCP_ZONE..."
if ! gcloud compute machine-types describe "$GCP_MACHINE_TYPE" --zone="$GCP_ZONE" --project="$GCP_PROJECT_ID" >/dev/null 2>&1; then
    warn "Type de machine '$GCP_MACHINE_TYPE' introuvable dans $GCP_ZONE. Types disponibles :"
    gcloud compute machine-types list --zones="$GCP_ZONE" --project="$GCP_PROJECT_ID" --format="table(name,guestCpus,memoryMb)"
    die "Corrigez GCP_MACHINE_TYPE dans $CONFIG_FILE."
fi

log "Validation de la famille d'image ($GCP_IMAGE_FAMILY, projet $GCP_IMAGE_PROJECT)..."
if ! gcloud compute images describe-from-family "$GCP_IMAGE_FAMILY" --project="$GCP_IMAGE_PROJECT" >/dev/null 2>&1; then
    warn "Famille d'image '$GCP_IMAGE_FAMILY' introuvable dans le projet '$GCP_IMAGE_PROJECT'. Familles disponibles :"
    gcloud compute images list --project="$GCP_IMAGE_PROJECT" --format="table(family)" --filter="family~ubuntu" | sort -u
    die "Corrigez GCP_IMAGE_FAMILY/GCP_IMAGE_PROJECT dans $CONFIG_FILE."
fi

# ── 4. Clé SSH (optionnelle — gcloud compute ssh gère sa propre paire par défaut) ─
SSH_METADATA_ARGS=()
if [ -n "${GCP_SSH_PUBLIC_KEY_FILE:-}" ] && [ -f "$GCP_SSH_PUBLIC_KEY_FILE" ]; then
    log "Clé publique fournie ($GCP_SSH_PUBLIC_KEY_FILE) — sera injectée dans les métadonnées de l'instance."
    SSH_METADATA_ARGS=(--metadata="ssh-keys=${GCP_SSH_USERNAME}:$(cat "$GCP_SSH_PUBLIC_KEY_FILE")")
else
    log "Aucune clé publique fournie — 'gcloud compute ssh' gérera sa propre paire de clés éphémère (~/.ssh/google_compute_engine) au premier usage."
fi

# ── 5. Instance (idempotent) ────────────────────────────────────────────────
if gcloud compute instances describe "$GCP_INSTANCE_NAME" --zone="$GCP_ZONE" --project="$GCP_PROJECT_ID" >/dev/null 2>&1; then
    log "Instance '$GCP_INSTANCE_NAME' déjà provisionnée."
    if [ ${#SSH_METADATA_ARGS[@]} -gt 0 ]; then
        log "Réapplication de la clé SSH fournie sur l'instance existante..."
        gcloud compute instances add-metadata "$GCP_INSTANCE_NAME" \
            --zone="$GCP_ZONE" --project="$GCP_PROJECT_ID" \
            "${SSH_METADATA_ARGS[@]}" \
            >/dev/null
    fi
else
    log "Création de l'instance '$GCP_INSTANCE_NAME' ($GCP_MACHINE_TYPE, $GCP_IMAGE_FAMILY)..."
    gcloud compute instances create "$GCP_INSTANCE_NAME" \
        --project="$GCP_PROJECT_ID" \
        --zone="$GCP_ZONE" \
        --machine-type="$GCP_MACHINE_TYPE" \
        --image-family="$GCP_IMAGE_FAMILY" \
        --image-project="$GCP_IMAGE_PROJECT" \
        --tags="$NETWORK_TAG" \
        "${SSH_METADATA_ARGS[@]}" \
        >/dev/null
fi

log "Attente du démarrage de l'instance (état 'RUNNING')..."
tries=0
until [ "$(gcloud compute instances describe "$GCP_INSTANCE_NAME" --zone="$GCP_ZONE" --project="$GCP_PROJECT_ID" --format='value(status)' 2>/dev/null)" = "RUNNING" ]; do
    tries=$((tries + 1))
    if [ "$tries" -gt 30 ]; then
        die "L'instance n'est pas passée à l'état 'RUNNING' après 5 minutes."
    fi
    sleep 10
done
log "Instance en cours d'exécution."

# ── 6. Règles de pare-feu (toujours réappliquées, déclaratif) ──────────────
# GCP n'ouvre aucun port par défaut sur un réseau non-"default" (et même le
# réseau "default" ne garantit pas de règle SSH) — les 3 ports sont donc
# explicitement couverts ici, ciblés par tag réseau (jamais tout le VPC).
log "Vérification des règles de pare-feu (22 SSH, 80 HTTP, 443 HTTPS)..."
declare -A FIREWALL_PORTS=(
    ["${NETWORK_TAG}-allow-ssh"]="tcp:22"
    ["${NETWORK_TAG}-allow-http"]="tcp:80"
    ["${NETWORK_TAG}-allow-https"]="tcp:443"
)
for rule_name in "${!FIREWALL_PORTS[@]}"; do
    if ! gcloud compute firewall-rules describe "$rule_name" --project="$GCP_PROJECT_ID" >/dev/null 2>&1; then
        log "Création de la règle de pare-feu '$rule_name'..."
        gcloud compute firewall-rules create "$rule_name" \
            --project="$GCP_PROJECT_ID" \
            --direction=INGRESS \
            --action=ALLOW \
            --rules="${FIREWALL_PORTS[$rule_name]}" \
            --source-ranges=0.0.0.0/0 \
            --target-tags="$NETWORK_TAG" \
            >/dev/null
    fi
done

# ── 7. IP externe statique (idempotent) ─────────────────────────────────────
if ! gcloud compute addresses describe "$STATIC_IP_NAME" --region="$REGION" --project="$GCP_PROJECT_ID" >/dev/null 2>&1; then
    log "Réservation d'une IP externe statique '$STATIC_IP_NAME' ($REGION)..."
    gcloud compute addresses create "$STATIC_IP_NAME" --region="$REGION" --project="$GCP_PROJECT_ID" >/dev/null
fi

SERVER_IP=$(gcloud compute addresses describe "$STATIC_IP_NAME" --region="$REGION" --project="$GCP_PROJECT_ID" --format='value(address)')
[ -n "$SERVER_IP" ] || die "Impossible de résoudre l'adresse IP statique depuis l'API Compute Engine."

CURRENT_IP=$(gcloud compute instances describe "$GCP_INSTANCE_NAME" --zone="$GCP_ZONE" --project="$GCP_PROJECT_ID" \
    --format='value(networkInterfaces[0].accessConfigs[0].natIP)' 2>/dev/null || echo "")
if [ "$CURRENT_IP" != "$SERVER_IP" ]; then
    log "Attachement de l'IP statique $SERVER_IP à l'instance '$GCP_INSTANCE_NAME'..."
    ACCESS_CONFIG_NAME=$(gcloud compute instances describe "$GCP_INSTANCE_NAME" --zone="$GCP_ZONE" --project="$GCP_PROJECT_ID" \
        --format='value(networkInterfaces[0].accessConfigs[0].name)' 2>/dev/null || echo "External NAT")
    if [ -n "$CURRENT_IP" ]; then
        gcloud compute instances delete-access-config "$GCP_INSTANCE_NAME" \
            --zone="$GCP_ZONE" --project="$GCP_PROJECT_ID" \
            --access-config-name="$ACCESS_CONFIG_NAME" >/dev/null
    fi
    gcloud compute instances add-access-config "$GCP_INSTANCE_NAME" \
        --zone="$GCP_ZONE" --project="$GCP_PROJECT_ID" \
        --access-config-name="External NAT" \
        --address="$SERVER_IP" >/dev/null
fi
log "IP statique : $SERVER_IP"

# ── 8. Zone Cloud DNS + enregistrement A (idempotents) ─────────────────────
if ! gcloud dns managed-zones describe "$DNS_ZONE_NAME" --project="$GCP_PROJECT_ID" >/dev/null 2>&1; then
    log "Création de la zone Cloud DNS '$DNS_ZONE_NAME' pour '$GCP_DOMAIN'..."
    gcloud dns managed-zones create "$DNS_ZONE_NAME" \
        --project="$GCP_PROJECT_ID" \
        --dns-name="${GCP_DOMAIN}." \
        --description="life-mdg-erp — $GCP_DOMAIN" \
        --visibility=public \
        >/dev/null
else
    log "Zone Cloud DNS '$DNS_ZONE_NAME' déjà présente."
fi

EXISTING_TARGET=$(gcloud dns record-sets list --zone="$DNS_ZONE_NAME" --project="$GCP_PROJECT_ID" \
    --name="${FQDN}." --type=A --format='value(rrdatas[0])' 2>/dev/null || echo "")

if [ "$EXISTING_TARGET" = "$SERVER_IP" ]; then
    log "Enregistrement DNS A pour '$FQDN' déjà à jour ($SERVER_IP)."
elif [ -n "$EXISTING_TARGET" ]; then
    log "Enregistrement DNS A existant pour '$FQDN' pointe vers $EXISTING_TARGET — mise à jour vers $SERVER_IP..."
    gcloud dns record-sets update "${FQDN}." --type=A --zone="$DNS_ZONE_NAME" --project="$GCP_PROJECT_ID" \
        --rrdatas="$SERVER_IP" --ttl=300 >/dev/null
else
    log "Création de l'enregistrement DNS A pour '$FQDN' → $SERVER_IP..."
    gcloud dns record-sets create "${FQDN}." --type=A --zone="$DNS_ZONE_NAME" --project="$GCP_PROJECT_ID" \
        --rrdatas="$SERVER_IP" --ttl=300 >/dev/null
fi

# ── 9. Écriture de l'IP dans la config locale (informatif uniquement) ─────
if grep -q '^GCP_SERVER_IP=' "$CONFIG_FILE"; then
    sed -i.bak "s/^GCP_SERVER_IP=.*/GCP_SERVER_IP=${SERVER_IP}/" "$CONFIG_FILE" && rm -f "${CONFIG_FILE}.bak"
else
    echo "GCP_SERVER_IP=${SERVER_IP}" >> "$CONFIG_FILE"
fi

# ── 10. Résumé ───────────────────────────────────────────────────────────────
echo
log "Provisionnement GCP terminé."
log "  Instance    : $GCP_INSTANCE_NAME ($GCP_ZONE)"
log "  IP statique : $SERVER_IP"
log "  Domaine     : $FQDN"
echo
warn "ÉTAPE MANUELLE OBLIGATOIRE — délégation DNS (non scriptable) :"
warn "  Chez le bureau d'enregistrement de '$GCP_DOMAIN', configurez les"
warn "  serveurs de noms (NS) de la zone Cloud DNS créée ci-dessus. Récupérez-les avec :"
warn "    gcloud dns managed-zones describe $DNS_ZONE_NAME --project=$GCP_PROJECT_ID --format='value(nameServers)'"
warn "  (si vous gérez déjà le DNS de $GCP_DOMAIN ailleurs sans vouloir déléguer"
warn "  toute la zone, créez plutôt manuellement un enregistrement A pour $FQDN → $SERVER_IP"
warn "  chez votre fournisseur DNS actuel, et ignorez cette délégation NS.)"
echo
warn "AVERTISSEMENT COÛT : cette VM Compute Engine facture en continu tant qu'elle"
warn "  existe, indépendamment de son utilisation. Voir docs/07-DEPLOIEMENT/GCP.md."
echo
log "Une fois le DNS propagé (dig +short $FQDN doit renvoyer $SERVER_IP), déployez l'application :"
if [ -n "${GCP_SSH_PUBLIC_KEY_FILE:-}" ] && [ -f "${GCP_SSH_PUBLIC_KEY_FILE:-}" ]; then
    log "  ssh -i ${GCP_SSH_PUBLIC_KEY_FILE%.pub} ${GCP_SSH_USERNAME}@$SERVER_IP"
else
    log "  gcloud compute ssh $GCP_INSTANCE_NAME --zone=$GCP_ZONE --project=$GCP_PROJECT_ID"
fi
log "  git clone <votre-dépôt> && cd life-mdg-erp && ./scripts/deploy.sh"
