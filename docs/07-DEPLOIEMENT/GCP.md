# Provisionnement automatisé sur Google Cloud Platform (GCP)

Ce guide couvre `scripts/gcp-deploy.sh` — un script autonome (pas un workflow GitHub Actions) qui automatise la **création de l'infrastructure** sur Google Compute Engine : une instance, une IP externe statique, et une zone Cloud DNS pointant un sous-domaine vers cette IP. Il applique les mêmes principes que [le chemin AWS Lightsail](AWS-LIGHTSAIL.md), adaptés à l'idiome GCP réel.

**Ce script ne fait que le provisionnement.** Une fois l'instance prête, le déploiement de l'application lui-même se fait avec le script générique déjà documenté dans le [Guide de déploiement simple](GUIDE-DEPLOIEMENT-SIMPLE.md) (`scripts/deploy.sh`) — inchangé, indépendant du fournisseur d'hébergement. Ce chemin GCP est une commodité facultative pour les utilisateurs de Google Cloud spécifiquement ; la stack Docker Compose + Caddy elle-même reste générique et fonctionne sur n'importe quel VPS.

## Vue d'ensemble

```
scripts/gcp-deploy.sh   →  crée l'instance + l'IP statique + le Cloud DNS
        (délégation NS manuelle chez votre bureau d'enregistrement)
scripts/deploy.sh       →  déploie l'application (Docker Compose + Caddy), sur le serveur
```

## Prérequis

1. **Un projet GCP** avec la facturation activée et l'API Compute Engine + l'API Cloud DNS activées (`gcloud services enable compute.googleapis.com dns.googleapis.com`).
2. **Le Google Cloud CLI installée et authentifiée** — `gcloud auth login`, ou un compte de service activé via `gcloud auth activate-service-account`. Ce dépôt ne stocke **aucune** clé/identifiant GCP — le script échoue tôt et clairement (`gcloud auth list`) si aucun compte actif n'est disponible.
3. **Un nom de domaine déjà enregistré** chez n'importe quel bureau d'enregistrement — ce script ne l'achète pas, il ne fait que créer une zone Cloud DNS pour ce domaine et y ajouter un enregistrement A.

Contrairement à AWS Lightsail, `jq` n'est pas nécessaire — `gcloud --format` extrait déjà directement les valeurs JSON dont le script a besoin.

## Configuration

```bash
cp deploy/gcp.config.example deploy/gcp.config
```

Éditez `deploy/gcp.config` (gitignoré — jamais poussé sur le dépôt) :

| Variable | Rôle |
|---|---|
| `GCP_PROJECT_ID` | ID du projet GCP (obligatoire — tout ressource GCP appartient à un projet, contrairement à Lightsail qui n'a pas cette notion) |
| `GCP_ZONE` | Zone GCP (ex. `europe-west1-b`, `europe-west9-a` pour Paris) — la région est dérivée automatiquement de cette zone |
| `GCP_INSTANCE_NAME` | Nom de l'instance dans votre projet |
| `GCP_MACHINE_TYPE` | Type de machine (taille) de l'instance — voir ci-dessous |
| `GCP_IMAGE_FAMILY` / `GCP_IMAGE_PROJECT` | Image de base (`ubuntu-2204-lts` / `ubuntu-os-cloud` par défaut) |
| `GCP_SSH_USERNAME` | Nom d'utilisateur Linux à créer sur l'instance |
| `GCP_SSH_PUBLIC_KEY_FILE` | Chemin vers une clé publique existante à injecter (optionnel) |
| `GCP_DOMAIN` | Domaine racine déjà enregistré (ex. `example.com`) |
| `GCP_SUBDOMAIN` | Sous-domaine à exposer (ex. `erp` → `erp.example.com`) |

### Choisir le type de machine (`GCP_MACHINE_TYPE`)

Cette stack fait tourner sur une seule machine : php-fpm, MySQL, Redis, un worker de file d'attente, un scheduler, le serveur WebSocket Reverb, et Caddy — un type avec **au moins 4 Go de RAM** est recommandé (`e2-medium` ou supérieur). Listez les types réellement disponibles dans votre zone avec :

```bash
gcloud compute machine-types list --zones=europe-west1-b --format="table(name,guestCpus,memoryMb)"
```

Le script lui-même valide `GCP_MACHINE_TYPE`/`GCP_IMAGE_FAMILY` contre l'API réelle avant de créer quoi que ce soit, et affiche la liste des valeurs valides en cas d'erreur.

## Lancer le provisionnement

```bash
./scripts/gcp-deploy.sh
```

Idempotent — peut être relancé sans risque : chaque ressource (instance, règles de pare-feu, IP statique, zone DNS, enregistrement A) est vérifiée avant d'être créée, jamais recréée si elle existe déjà.

Le script :

1. Valide votre authentification `gcloud` et les valeurs `GCP_MACHINE_TYPE`/`GCP_IMAGE_FAMILY` contre l'API réelle.
2. Résout la clé SSH — injecte `GCP_SSH_PUBLIC_KEY_FILE` dans les métadonnées de l'instance si fourni, sinon ne fait rien : `gcloud compute ssh` gère sa propre paire de clés éphémère (`~/.ssh/google_compute_engine`) au premier usage. **Différence structurelle avec le chemin AWS Lightsail** : aucune clé privée n'est téléchargée ni gitignorée dans ce dépôt côté GCP par défaut.
3. Crée l'instance si absente, attend qu'elle soit `RUNNING`.
4. Crée les 3 règles de pare-feu (22 SSH, 80 HTTP, 443 HTTPS) ciblées par tag réseau si absentes — GCP n'ouvre aucun port par défaut, contrairement à Lightsail.
5. Réserve une IP externe statique et l'attache à l'instance.
6. Crée une zone Cloud DNS pour `GCP_DOMAIN` si absente.
7. Crée ou met à jour l'enregistrement DNS de type A pour le sous-domaine configuré, pointant vers l'IP statique.
8. Écrit l'IP statique obtenue dans `deploy/gcp.config` (`GCP_SERVER_IP=`, purement informatif — le script ne relit jamais cette valeur comme une entrée, il la résout toujours depuis l'API Compute Engine).

## Étape manuelle obligatoire — délégation DNS

La zone Cloud DNS créée à l'étape 6 n'est **pas** automatiquement utilisée par Internet tant que le domaine ne délègue pas ses serveurs de noms (NS) vers Cloud DNS. Cette étape ne peut pas être scriptée — elle se fait chez votre bureau d'enregistrement (OVH, Gandi, Namecheap, etc.).

Récupérez les serveurs de noms Cloud DNS :

```bash
gcloud dns managed-zones describe life-mdg-erp-zone --project=<votre-projet> --format='value(nameServers)'
```

Puis, chez votre bureau d'enregistrement, remplacez les serveurs de noms du domaine par ceux affichés ci-dessus.

**Alternative** si vous ne voulez pas déléguer toute la zone DNS à Cloud DNS (par exemple si vous gérez déjà d'autres enregistrements pour ce domaine ailleurs) : créez manuellement, chez votre fournisseur DNS actuel, un enregistrement A pour le sous-domaine pointant vers l'IP statique affichée par le script — ignorez alors la délégation NS.

Vérifiez la propagation avant de continuer :

```bash
dig +short erp.example.com
# doit afficher l'IP statique renvoyée par scripts/gcp-deploy.sh
```

## Étape suivante — déployer l'application

Une fois le DNS propagé, connectez-vous à l'instance et lancez le déploiement générique déjà documenté dans le [Guide de déploiement simple](GUIDE-DEPLOIEMENT-SIMPLE.md) :

```bash
gcloud compute ssh life-mdg-erp --zone=<zone> --project=<projet>
# ou, si vous avez fourni GCP_SSH_PUBLIC_KEY_FILE :
# ssh -i <clé-privée-correspondante> <GCP_SSH_USERNAME>@<IP-statique>

git clone <url-du-dépôt> life-mdg-erp
cd life-mdg-erp
cp .env.example .env
# éditer .env : APP_DOMAIN=erp.example.com, DB_*, APP_ENV=production, APP_DEBUG=false
./scripts/deploy.sh
```

`scripts/deploy.sh` n'a besoin d'aucune connaissance de GCP — il attend uniquement que `APP_DOMAIN` pointe déjà vers le serveur, ce que l'étape précédente garantit.

## Redéploiements ultérieurs

`scripts/gcp-deploy.sh` n'a besoin d'être relancé que si l'instance/IP/DNS doivent changer (nouvelle instance, changement de domaine). Pour un redéploiement applicatif classique après un `git push`, utilisez le chemin déjà documenté : soit `git pull && ./scripts/deploy.sh` directement sur le serveur, soit le déploiement continu automatique via `.github/workflows/deploy.yml` (voir `docs/07-DEPLOIEMENT/README.md`) — les deux sont indépendants de GCP.

## Dépannage

| Symptôme | Cause probable | Diagnostic |
|---|---|---|
| `Aucun compte gcloud actif` | `gcloud auth login` jamais lancé, ou compte de service non activé | `gcloud auth list` doit afficher un compte `ACTIVE` avant de relancer le script |
| `Type de machine '...' introuvable dans <zone>` | Le nom de type varie selon la zone/région | Le script affiche la liste réelle des types disponibles — copiez l'un d'eux dans `deploy/gcp.config` |
| L'instance reste bloquée, jamais `RUNNING` | Rare — quota projet insuffisant ou problème côté GCP | `gcloud compute instances describe <nom> --zone=<zone>` pour l'état détaillé ; vérifiez les quotas du projet (`gcloud compute project-info describe`) |
| Erreur de permission sur `gcloud compute` / `gcloud dns` | Le compte/compte de service n'a pas les rôles IAM nécessaires | Attribuez au minimum `roles/compute.admin` et `roles/dns.admin` sur le projet |
| `dig +short <sous-domaine>` ne renvoie rien après la délégation NS | Propagation DNS pas encore terminée (jusqu'à 24-48h après une délégation NS, contre quelques minutes pour un simple enregistrement A) | Réessayez plus tard ; `gcloud dns record-sets list --zone=<zone-dns>` pour confirmer que l'enregistrement A est bien dans la zone Cloud DNS |
| `scripts/deploy.sh` échoue au healthcheck HTTPS malgré un DNS qui répond | Le port 80/443 est bloqué, ou l'instance n'a pas encore fini son propre démarrage réseau | `curl -I http://<sous-domaine>` depuis une machine externe ; vérifiez que les règles de pare-feu de l'étape 4 existent bien via `gcloud compute firewall-rules list --filter="targetTags:life-mdg-erp"` |

## Nettoyage / suppression

Ces commandes sont documentées pour référence — **non automatisées** par ce script (une suppression est une action destructive, hors du périmètre d'un script de provisionnement) :

```bash
gcloud compute instances delete <nom> --zone=<zone> --project=<projet>
gcloud compute addresses delete <nom>-static-ip --region=<région> --project=<projet>
gcloud compute firewall-rules delete life-mdg-erp-allow-ssh life-mdg-erp-allow-http life-mdg-erp-allow-https --project=<projet>
gcloud dns managed-zones delete <nom>-zone --project=<projet>   # supprime la zone DNS ET tous ses enregistrements
```

## Coûts

**Une VM Compute Engine facture en continu tant qu'elle existe**, indépendamment de son utilisation réelle — le tarif dépend du type de machine choisi (`GCP_MACHINE_TYPE`) et de la zone. Une IP externe statique attachée à une instance en cours d'exécution est gratuite ; une IP statique réservée mais non attachée est facturée. Consultez la [tarification Compute Engine officielle](https://cloud.google.com/compute/all-pricing) avant de provisionner en production.
