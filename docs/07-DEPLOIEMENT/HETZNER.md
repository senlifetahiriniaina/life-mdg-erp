# Provisionnement automatisé sur Hetzner Cloud

Ce guide couvre `scripts/hetzner-deploy.sh` — un script autonome (pas un workflow GitHub Actions) qui automatise la **création de l'infrastructure** sur Hetzner Cloud : un serveur (type `cx33` par défaut), un pare-feu limité aux ports 22/80/443, et une zone Hetzner DNS pointant un sous-domaine vers l'IPv4 publique du serveur. Il applique les mêmes principes que [le chemin AWS Lightsail](AWS-LIGHTSAIL.md) et [le chemin GCP](GCP.md), adaptés à l'idiome Hetzner Cloud réel.

**Ce script ne fait que le provisionnement.** Une fois le serveur prêt, le déploiement de l'application lui-même se fait avec le script générique déjà documenté dans le [Guide de déploiement simple](GUIDE-DEPLOIEMENT-SIMPLE.md) (`scripts/deploy.sh`) — inchangé, indépendant du fournisseur d'hébergement. Ce chemin Hetzner est une commodité facultative pour les utilisateurs de Hetzner Cloud spécifiquement ; la stack Docker Compose + Caddy elle-même reste générique et fonctionne sur n'importe quel VPS.

## Vue d'ensemble

```
scripts/hetzner-deploy.sh   →  crée le serveur + le pare-feu + la zone Hetzner DNS
        (délégation NS manuelle chez votre bureau d'enregistrement)
scripts/deploy.sh           →  déploie l'application (Docker Compose + Caddy), sur le serveur
scripts/install-resilience.sh → reprise auto + rattrapage de code + sauvegarde quotidienne des volumes
```

## Prérequis

1. **Un compte Hetzner Cloud** avec un projet créé, et un jeton API pour ce projet (Cloud Console → Sécurité → Jetons API).
2. **Le Hetzner Cloud CLI (`hcloud`) installé et un contexte authentifié** — `hcloud context create <nom>` (vous demandera le jeton du point 1). Ce dépôt ne stocke **aucune** clé/identifiant Hetzner — le script échoue tôt et clairement (`hcloud context active`) si aucun contexte n'est actif.
3. **Un compte Hetzner DNS Console** (produit **séparé** du Cloud Console, avec son propre jeton API — https://dns.hetzner.com/ → Jetons API), puisque l'API Hetzner DNS n'a pas de CLI officielle : ce script y accède directement en `curl`+`jq`.
4. **Une clé SSH déjà enregistrée dans le projet Hetzner Cloud** (`hcloud ssh-key list`), ou le chemin d'une clé publique locale pour en créer une automatiquement. **Différence structurelle avec GCP** : Hetzner Cloud n'a pas de mécanisme de clé éphémère type `gcloud compute ssh` — une clé doit exister dans le projet AVANT la création du serveur pour y avoir un accès pratique.
5. **`jq`** installé localement (pour parser les réponses de l'API Hetzner DNS Console).
6. **Un nom de domaine déjà enregistré** chez n'importe quel bureau d'enregistrement — ce script ne l'achète pas, il ne fait que créer une zone Hetzner DNS pour ce domaine et y ajouter un enregistrement A.

## Configuration

```bash
cp deploy/hetzner.config.example deploy/hetzner.config
```

Éditez `deploy/hetzner.config` (gitignoré — jamais poussé sur le dépôt) :

| Variable | Rôle |
|---|---|
| `HETZNER_SERVER_NAME` | Nom du serveur dans votre projet |
| `HETZNER_SERVER_TYPE` | Type de serveur (taille) — voir ci-dessous |
| `HETZNER_LOCATION` | Emplacement (ex. `nbg1` Nuremberg, `fsn1` Falkenstein, `hel1` Helsinki) |
| `HETZNER_IMAGE` | Image de base (`ubuntu-22.04` par défaut) |
| `HETZNER_SSH_KEY_NAME` | Nom d'une clé SSH déjà enregistrée dans le projet (ou à créer, voir ci-dessous) |
| `HETZNER_SSH_PUBLIC_KEY_FILE` | Chemin vers une clé publique existante — utilisé uniquement pour créer `HETZNER_SSH_KEY_NAME` si elle n'existe pas encore |
| `HETZNER_DOMAIN` | Domaine racine déjà enregistré (ex. `example.com`) |
| `HETZNER_SUBDOMAIN` | Sous-domaine à exposer (ex. `erp` → `erp.example.com`) |
| `HETZNER_DNS_API_TOKEN` | Jeton API de la Hetzner DNS Console (distinct du contexte `hcloud`) |

### Choisir le type de serveur (`HETZNER_SERVER_TYPE`)

Cette stack fait tourner sur une seule machine : php-fpm, MySQL, Redis, un worker de file d'attente, un scheduler, le serveur WebSocket Reverb, Meilisearch, et Caddy — un type avec **au moins 4 Go de RAM** est recommandé. `cx33` (8 Go RAM / 4 vCPU) est la valeur par défaut, choisie comme confortable plutôt que comme minimum strict. Listez les types réellement disponibles avec :

```bash
hcloud server-type list
```

Le script lui-même valide `HETZNER_SERVER_TYPE`/`HETZNER_LOCATION`/`HETZNER_IMAGE` contre l'API réelle avant de créer quoi que ce soit, et affiche la liste des valeurs valides en cas d'erreur.

## Lancer le provisionnement

```bash
./scripts/hetzner-deploy.sh
```

Idempotent — peut être relancé sans risque : chaque ressource (serveur, clé SSH, pare-feu, zone DNS, enregistrement A) est vérifiée avant d'être créée, jamais recréée si elle existe déjà.

Le script :

1. Valide le contexte `hcloud` actif et les valeurs `HETZNER_SERVER_TYPE`/`HETZNER_LOCATION`/`HETZNER_IMAGE` contre l'API réelle.
2. Résout la clé SSH — crée `HETZNER_SSH_KEY_NAME` dans le projet depuis `HETZNER_SSH_PUBLIC_KEY_FILE` si elle n'existe pas encore et qu'un chemin est fourni, sinon échoue clairement (voir prérequis §4).
3. Crée le serveur si absent, attend qu'il soit `running`.
4. Crée le pare-feu `life-mdg-erp` (22 SSH, 80 HTTP, 443 HTTPS) si absent, et l'applique au serveur — Hetzner Cloud n'ouvre aucun port entrant par défaut.
5. Crée une zone Hetzner DNS pour `HETZNER_DOMAIN` si absente.
6. Crée ou met à jour l'enregistrement DNS de type A pour le sous-domaine configuré, pointant vers l'IPv4 publique du serveur.
7. Écrit l'IP obtenue dans `deploy/hetzner.config` (`HETZNER_SERVER_IP=`, purement informatif — le script ne relit jamais cette valeur comme une entrée, il la résout toujours depuis l'API Hetzner Cloud).

## Étape manuelle obligatoire — délégation DNS

La zone Hetzner DNS créée à l'étape 5 n'est **pas** automatiquement utilisée par Internet tant que le domaine ne délègue pas ses serveurs de noms (NS) vers Hetzner DNS. Cette étape ne peut pas être scriptée — elle se fait chez votre bureau d'enregistrement (OVH, Gandi, Namecheap, etc.).

Récupérez les serveurs de noms de la zone Hetzner DNS :

```bash
curl -fsS "https://dns.hetzner.com/api/v1/zones/<zone_id>" -H "Auth-API-Token: <votre jeton DNS>" | jq -r '.zone.ns[]'
```

(`<zone_id>` est affiché dans le résumé final de `scripts/hetzner-deploy.sh`, ou consultable via `curl -fsS "https://dns.hetzner.com/api/v1/zones?name=<domaine>" -H "Auth-API-Token: ..."`.)

Puis, chez votre bureau d'enregistrement, remplacez les serveurs de noms du domaine par ceux affichés ci-dessus.

**Alternative** si vous ne voulez pas déléguer toute la zone DNS à Hetzner (par exemple si vous gérez déjà d'autres enregistrements pour ce domaine ailleurs) : créez manuellement, chez votre fournisseur DNS actuel, un enregistrement A pour le sous-domaine pointant vers l'IP affichée par le script — ignorez alors la délégation NS.

Vérifiez la propagation avant de continuer :

```bash
dig +short erp.example.com
# doit afficher l'IP du serveur renvoyée par scripts/hetzner-deploy.sh
```

## Étape suivante — déployer l'application

Une fois le DNS propagé, connectez-vous au serveur et lancez le déploiement générique déjà documenté dans le [Guide de déploiement simple](GUIDE-DEPLOIEMENT-SIMPLE.md) :

```bash
ssh root@<IP-du-serveur>

git clone <url-du-dépôt> life-mdg-erp
cd life-mdg-erp
cp .env.example .env
# éditer .env : APP_DOMAIN=erp.example.com, DB_*, MEILISEARCH_KEY, APP_ENV=production, APP_DEBUG=false
./scripts/deploy.sh
```

`scripts/deploy.sh` n'a besoin d'aucune connaissance de Hetzner — il attend uniquement que `APP_DOMAIN` pointe déjà vers le serveur, ce que l'étape précédente garantit.

Une fois l'application accessible, activez la résilience (reprise automatique au démarrage, rattrapage périodique du code, et sauvegarde quotidienne des volumes Docker — voir `GUIDE-DEPLOIEMENT-SIMPLE.md` § « Résilience ») :

```bash
sudo ./scripts/install-resilience.sh
```

## Sauvegarde/restauration — ce qui est couvert, ce qui ne l'est pas

Deux mécanismes indépendants, tous deux **stockés localement sur ce serveur pour l'instant** — aucun n'est copié hors du VPS par défaut, donc la perte du disque/serveur entraînerait une perte de données (limitation assumée, pas un oubli) :

- **Base de données** : `backup:database`/`backup:restore` (planifiée quotidiennement à 02:00 UTC, voir `ENV-PRODUCTION.md`) — dump logique compressé avec réconciliation de schéma à la restauration.
- **Volumes Docker** (`storage_data`/`redis_data`/`meilisearch_data`/`caddy_data`) : `scripts/backup-volumes.sh` (planifiée quotidiennement à 02:30 UTC via `life-mdg-erp-backup-volumes.timer`, installée par `scripts/install-resilience.sh`) — un fichier `.tar.gz` par volume. `mysql_data` est délibérément exclu (déjà couvert par `backup:database`) ; `app_public`/`caddy_config` aussi (entièrement régénérés, aucune donnée réelle).

Restauration des volumes :

```bash
ls storage/backups/volumes/                          # lister les horodatages disponibles
./scripts/restore-volumes.sh <horodatage>             # restaure les 4 volumes
./scripts/restore-volumes.sh <horodatage> storage_data # restaure un seul volume
```

## Redéploiements ultérieurs

`scripts/hetzner-deploy.sh` n'a besoin d'être relancé que si le serveur/DNS doivent changer (nouveau serveur, changement de domaine). Pour un redéploiement applicatif classique après un `git push`, utilisez le chemin déjà documenté : soit `git pull && ./scripts/deploy.sh` directement sur le serveur, soit le rattrapage automatique via `life-mdg-erp-reconcile.timer` (voir ci-dessus), soit le déploiement continu automatique via `.github/workflows/deploy.yml` (voir `docs/07-DEPLOIEMENT/README.md`) — les trois sont indépendants de Hetzner.

## Dépannage

| Symptôme | Cause probable | Diagnostic |
|---|---|---|
| `Aucun contexte hcloud actif` | `hcloud context create` jamais lancé | `hcloud context active` doit afficher un contexte avant de relancer le script |
| `Type de serveur '...' introuvable` | Le type n'existe pas ou n'est pas disponible dans la localisation choisie | Le script affiche la liste réelle des types disponibles — copiez l'un d'eux dans `deploy/hetzner.config` |
| Le serveur reste bloqué, jamais `running` | Rare — quota de projet insuffisant ou problème côté Hetzner | `hcloud server describe <nom>` pour l'état détaillé |
| Échec de création de zone/enregistrement DNS | `HETZNER_DNS_API_TOKEN` invalide ou absent — c'est un jeton **distinct** du contexte hcloud | Vérifiez le jeton dans la Hetzner DNS Console, pas dans le Cloud Console |
| `dig +short <sous-domaine>` ne renvoie rien après la délégation NS | Propagation DNS pas encore terminée (jusqu'à 24-48h après une délégation NS, contre quelques minutes pour un simple enregistrement A) | Réessayez plus tard ; `curl -fsS "https://dns.hetzner.com/api/v1/records?zone_id=<id>" -H "Auth-API-Token: ..."` pour confirmer que l'enregistrement A est bien dans la zone |
| `scripts/deploy.sh` échoue au healthcheck HTTPS malgré un DNS qui répond | Le port 80/443 est bloqué, ou le serveur n'a pas encore fini son propre démarrage réseau | `curl -I http://<sous-domaine>` depuis une machine externe ; vérifiez que le pare-feu de l'étape 4 est bien appliqué via `hcloud firewall describe life-mdg-erp` |
| `scripts/restore-volumes.sh` échoue avec « Archive(s) introuvable(s) » | Horodatage incorrect, ou ce volume n'a jamais été sauvegardé à cet horodatage | `ls storage/backups/volumes/` pour lister les archives réellement présentes |

## Nettoyage / suppression

Ces commandes sont documentées pour référence — **non automatisées** par ce script (une suppression est une action destructive, hors du périmètre d'un script de provisionnement) :

```bash
hcloud server delete <nom>
hcloud firewall delete life-mdg-erp
curl -X DELETE "https://dns.hetzner.com/api/v1/zones/<zone_id>" -H "Auth-API-Token: <votre jeton DNS>"   # supprime la zone DNS ET tous ses enregistrements
```

## Coûts

**Un serveur Hetzner Cloud facture en continu tant qu'il existe**, indépendamment de son utilisation réelle — le tarif dépend du type choisi (`HETZNER_SERVER_TYPE`) et de la localisation. Consultez la [tarification Hetzner Cloud officielle](https://www.hetzner.com/cloud) avant de provisionner en production.
