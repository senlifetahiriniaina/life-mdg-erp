#!/usr/bin/env bash
#
# Provisionnement d'une instance AWS Lightsail pour life-mdg-erp : crée
# l'instance (si absente), lui attache une IP statique, crée une zone DNS
# Lightsail pour le domaine configuré et pointe le sous-domaine vers cette
# IP. Idempotent — peut être relancé sans risque, chaque étape vérifie
# l'existant avant de créer quoi que ce soit.
#
# Ce script fait UNIQUEMENT le provisionnement de l'infrastructure. Une fois
# l'instance prête et le DNS délégué (voir l'étape manuelle affichée à la
# fin), le déploiement applicatif se fait avec le script générique existant :
#   ssh ubuntu@<IP> puis, depuis un clone du dépôt sur le serveur :
#   scripts/deploy.sh
#
# Voir docs/07-DEPLOIEMENT/AWS-LIGHTSAIL.md pour la marche à suivre complète.

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

CONFIG_FILE="deploy/lightsail.config"
CONFIG_EXAMPLE="deploy/lightsail.config.example"

log()  { printf '\033[1;32m[lightsail]\033[0m %s\n' "$1"; }
warn() { printf '\033[1;33m[lightsail]\033[0m %s\n' "$1"; }
die()  { printf '\033[1;31m[lightsail] ERREUR:\033[0m %s\n' "$1" >&2; exit 1; }

# ── 1. Config ───────────────────────────────────────────────────────────────
if [ ! -f "$CONFIG_FILE" ]; then
    log "Aucun $CONFIG_FILE trouvé — copie depuis $CONFIG_EXAMPLE."
    cp "$CONFIG_EXAMPLE" "$CONFIG_FILE"
    warn "Éditez $CONFIG_FILE avant de relancer ce script (région, domaine, sous-domaine, gabarit)."
    exit 1
fi

# shellcheck disable=SC1090
set -a; source "$CONFIG_FILE"; set +a

REQUIRED_VARS=(LIGHTSAIL_INSTANCE_NAME LIGHTSAIL_REGION LIGHTSAIL_AVAILABILITY_ZONE LIGHTSAIL_BUNDLE_ID LIGHTSAIL_BLUEPRINT_ID LIGHTSAIL_KEY_PAIR_NAME LIGHTSAIL_DOMAIN)
missing=()
for var in "${REQUIRED_VARS[@]}"; do
    [ -z "${!var:-}" ] && missing+=("$var")
done
if [ ${#missing[@]} -gt 0 ]; then
    die "Variable(s) manquante(s) dans $CONFIG_FILE : ${missing[*]}"
fi

FQDN="${LIGHTSAIL_DOMAIN}"
if [ -n "${LIGHTSAIL_SUBDOMAIN:-}" ]; then
    FQDN="${LIGHTSAIL_SUBDOMAIN}.${LIGHTSAIL_DOMAIN}"
fi

# ── 2. Prérequis ────────────────────────────────────────────────────────────
command -v aws >/dev/null 2>&1 || die "L'AWS CLI n'est pas installé. Voir https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html"
command -v jq  >/dev/null 2>&1 || die "jq n'est pas installé (nécessaire pour parser les réponses AWS CLI)."

log "Vérification des identifiants AWS..."
aws sts get-caller-identity >/dev/null 2>&1 || die "Identifiants AWS invalides ou absents. Lancez 'aws configure' ou exportez AWS_ACCESS_KEY_ID/AWS_SECRET_ACCESS_KEY."

# ── 3. Validation du gabarit/blueprint contre l'API réelle ─────────────────
log "Validation du gabarit ($LIGHTSAIL_BUNDLE_ID) dans la région $LIGHTSAIL_REGION..."
if ! aws lightsail get-bundles --region "$LIGHTSAIL_REGION" --query "bundles[?bundleId=='${LIGHTSAIL_BUNDLE_ID}']" --output text | grep -q .; then
    warn "Gabarit '$LIGHTSAIL_BUNDLE_ID' introuvable dans $LIGHTSAIL_REGION. Gabarits disponibles :"
    aws lightsail get-bundles --region "$LIGHTSAIL_REGION" --query "bundles[].{id:bundleId,ram:ramSizeInGb,price:price}" --output table
    die "Corrigez LIGHTSAIL_BUNDLE_ID dans $CONFIG_FILE."
fi

log "Validation du blueprint ($LIGHTSAIL_BLUEPRINT_ID)..."
if ! aws lightsail get-blueprints --query "blueprints[?blueprintId=='${LIGHTSAIL_BLUEPRINT_ID}']" --output text | grep -q .; then
    warn "Blueprint '$LIGHTSAIL_BLUEPRINT_ID' introuvable. Blueprints disponibles (OS) :"
    aws lightsail get-blueprints --query "blueprints[?type=='os'].blueprintId" --output table
    die "Corrigez LIGHTSAIL_BLUEPRINT_ID dans $CONFIG_FILE."
fi

# ── 4. Paire de clés SSH (idempotent) ───────────────────────────────────────
KEY_FILE="deploy/lightsail-default-key-${LIGHTSAIL_REGION}.pem"

if aws lightsail get-key-pair --key-pair-name "$LIGHTSAIL_KEY_PAIR_NAME" --region "$LIGHTSAIL_REGION" >/dev/null 2>&1; then
    log "Paire de clés '$LIGHTSAIL_KEY_PAIR_NAME' déjà présente dans Lightsail."
elif [ -n "${LIGHTSAIL_SSH_PUBLIC_KEY_FILE:-}" ] && [ -f "$LIGHTSAIL_SSH_PUBLIC_KEY_FILE" ]; then
    log "Import de la clé publique $LIGHTSAIL_SSH_PUBLIC_KEY_FILE sous le nom '$LIGHTSAIL_KEY_PAIR_NAME'..."
    aws lightsail import-key-pair \
        --key-pair-name "$LIGHTSAIL_KEY_PAIR_NAME" \
        --region "$LIGHTSAIL_REGION" \
        --public-key-base64 "$(base64 -w0 "$LIGHTSAIL_SSH_PUBLIC_KEY_FILE" 2>/dev/null || base64 "$LIGHTSAIL_SSH_PUBLIC_KEY_FILE")" \
        >/dev/null
else
    log "Aucune clé publique fournie — téléchargement de la paire de clés par défaut de la région $LIGHTSAIL_REGION..."
    aws lightsail download-default-key-pair --region "$LIGHTSAIL_REGION" --query 'privateKeyBase64' --output text | base64 -d > "$KEY_FILE" 2>/dev/null \
        || aws lightsail download-default-key-pair --region "$LIGHTSAIL_REGION" --query 'privateKeyBase64' --output text > "$KEY_FILE"
    chmod 600 "$KEY_FILE"
    LIGHTSAIL_KEY_PAIR_NAME="LightsailDefaultKeyPair-${LIGHTSAIL_REGION}"
    warn "Clé privée par défaut enregistrée dans $KEY_FILE (chmod 600) — nécessaire pour vous connecter en SSH ensuite."
fi

# ── 5. Instance (idempotent) ────────────────────────────────────────────────
if aws lightsail get-instance --instance-name "$LIGHTSAIL_INSTANCE_NAME" --region "$LIGHTSAIL_REGION" >/dev/null 2>&1; then
    log "Instance '$LIGHTSAIL_INSTANCE_NAME' déjà provisionnée."
else
    log "Création de l'instance '$LIGHTSAIL_INSTANCE_NAME' ($LIGHTSAIL_BUNDLE_ID, $LIGHTSAIL_BLUEPRINT_ID)..."
    aws lightsail create-instances \
        --instance-names "$LIGHTSAIL_INSTANCE_NAME" \
        --availability-zone "$LIGHTSAIL_AVAILABILITY_ZONE" \
        --blueprint-id "$LIGHTSAIL_BLUEPRINT_ID" \
        --bundle-id "$LIGHTSAIL_BUNDLE_ID" \
        --key-pair-name "$LIGHTSAIL_KEY_PAIR_NAME" \
        --region "$LIGHTSAIL_REGION" \
        >/dev/null
fi

log "Attente du démarrage de l'instance (état 'running')..."
tries=0
until [ "$(aws lightsail get-instance-state --instance-name "$LIGHTSAIL_INSTANCE_NAME" --region "$LIGHTSAIL_REGION" --query 'state.name' --output text 2>/dev/null)" = "running" ]; do
    tries=$((tries + 1))
    if [ "$tries" -gt 30 ]; then
        die "L'instance n'est pas passée à l'état 'running' après 5 minutes."
    fi
    sleep 10
done
log "Instance en cours d'exécution."

# ── 6. Ports publics (toujours ré-appliqué, déclaratif) ───────────────────
log "Ouverture des ports 22 (SSH), 80 (HTTP), 443 (HTTPS)..."
aws lightsail put-instance-public-ports \
    --instance-name "$LIGHTSAIL_INSTANCE_NAME" \
    --region "$LIGHTSAIL_REGION" \
    --port-infos \
        fromPort=22,toPort=22,protocol=TCP \
        fromPort=80,toPort=80,protocol=TCP \
        fromPort=443,toPort=443,protocol=TCP \
    >/dev/null

# ── 7. IP statique (idempotent) ─────────────────────────────────────────────
STATIC_IP_NAME="${LIGHTSAIL_INSTANCE_NAME}-static-ip"

if ! aws lightsail get-static-ip --static-ip-name "$STATIC_IP_NAME" --region "$LIGHTSAIL_REGION" >/dev/null 2>&1; then
    log "Allocation d'une IP statique '$STATIC_IP_NAME'..."
    aws lightsail allocate-static-ip --static-ip-name "$STATIC_IP_NAME" --region "$LIGHTSAIL_REGION" >/dev/null
fi

ATTACHED_TO=$(aws lightsail get-static-ip --static-ip-name "$STATIC_IP_NAME" --region "$LIGHTSAIL_REGION" --query 'staticIp.attachedTo' --output text 2>/dev/null || echo "")
if [ "$ATTACHED_TO" != "$LIGHTSAIL_INSTANCE_NAME" ]; then
    log "Attachement de l'IP statique à l'instance '$LIGHTSAIL_INSTANCE_NAME'..."
    aws lightsail attach-static-ip --static-ip-name "$STATIC_IP_NAME" --instance-name "$LIGHTSAIL_INSTANCE_NAME" --region "$LIGHTSAIL_REGION" >/dev/null
fi

SERVER_IP=$(aws lightsail get-static-ip --static-ip-name "$STATIC_IP_NAME" --region "$LIGHTSAIL_REGION" --query 'staticIp.ipAddress' --output text)
[ -n "$SERVER_IP" ] && [ "$SERVER_IP" != "None" ] || die "Impossible de résoudre l'adresse IP statique depuis l'API Lightsail."
log "IP statique : $SERVER_IP"

# ── 8. Zone DNS Lightsail (idempotent — API globale, sans --region) ────────
if ! aws lightsail get-domain --domain-name "$LIGHTSAIL_DOMAIN" >/dev/null 2>&1; then
    log "Création de la zone DNS Lightsail pour '$LIGHTSAIL_DOMAIN'..."
    aws lightsail create-domain --domain-name "$LIGHTSAIL_DOMAIN" >/dev/null
else
    log "Zone DNS Lightsail pour '$LIGHTSAIL_DOMAIN' déjà présente."
fi

RECORD_NAME="${LIGHTSAIL_SUBDOMAIN:-@}"
EXISTING_ENTRY=$(aws lightsail get-domain --domain-name "$LIGHTSAIL_DOMAIN" \
    --query "domain.domainEntries[?name=='${FQDN}.' && type=='A']" --output json)
EXISTING_TARGET=$(echo "$EXISTING_ENTRY" | jq -r '.[0].target // empty')
EXISTING_ID=$(echo "$EXISTING_ENTRY" | jq -r '.[0].id // empty')

if [ "$EXISTING_TARGET" = "$SERVER_IP" ]; then
    log "Enregistrement DNS A pour '$FQDN' déjà à jour ($SERVER_IP)."
else
    if [ -n "$EXISTING_ID" ]; then
        log "Enregistrement DNS A existant pour '$FQDN' pointe vers $EXISTING_TARGET — mise à jour vers $SERVER_IP..."
        aws lightsail delete-domain-entry --domain-name "$LIGHTSAIL_DOMAIN" \
            --domain-entry "id=${EXISTING_ID},name=${FQDN}.,type=A,target=${EXISTING_TARGET}" >/dev/null
    else
        log "Création de l'enregistrement DNS A pour '$FQDN' → $SERVER_IP..."
    fi
    aws lightsail create-domain-entry --domain-name "$LIGHTSAIL_DOMAIN" \
        --domain-entry "name=${FQDN}.,type=A,target=${SERVER_IP},isAlias=false" >/dev/null
fi

# ── 9. Écriture de l'IP dans la config locale (informatif uniquement) ─────
if grep -q '^LIGHTSAIL_SERVER_IP=' "$CONFIG_FILE"; then
    sed -i.bak "s/^LIGHTSAIL_SERVER_IP=.*/LIGHTSAIL_SERVER_IP=${SERVER_IP}/" "$CONFIG_FILE" && rm -f "${CONFIG_FILE}.bak"
else
    echo "LIGHTSAIL_SERVER_IP=${SERVER_IP}" >> "$CONFIG_FILE"
fi

# ── 10. Résumé ───────────────────────────────────────────────────────────────
echo
log "Provisionnement Lightsail terminé."
log "  Instance    : $LIGHTSAIL_INSTANCE_NAME ($LIGHTSAIL_REGION)"
log "  IP statique : $SERVER_IP"
log "  Domaine     : $FQDN"
echo
warn "ÉTAPE MANUELLE OBLIGATOIRE — délégation DNS (non scriptable) :"
warn "  Chez le bureau d'enregistrement de '$LIGHTSAIL_DOMAIN', configurez les"
warn "  serveurs de noms (NS) de la zone Lightsail créée ci-dessus. Récupérez-les avec :"
warn "    aws lightsail get-domain --domain-name $LIGHTSAIL_DOMAIN --query 'domain.domainEntries[?type==\`NS\`]'"
warn "  (si vous gérez déjà le DNS de $LIGHTSAIL_DOMAIN ailleurs sans vouloir déléguer"
warn "  toute la zone, créez plutôt manuellement un enregistrement A pour $FQDN → $SERVER_IP"
warn "  chez votre fournisseur DNS actuel, et ignorez cette délégation NS.)"
echo
warn "AVERTISSEMENT COÛT : cette instance Lightsail facture en continu tant qu'elle"
warn "  existe, indépendamment de son utilisation. Voir docs/07-DEPLOIEMENT/AWS-LIGHTSAIL.md."
echo
log "Une fois le DNS propagé (dig +short $FQDN doit renvoyer $SERVER_IP), déployez l'application :"
log "  ssh -i $KEY_FILE ubuntu@$SERVER_IP"
log "  git clone <votre-dépôt> && cd life-mdg-erp && ./scripts/deploy.sh"
