# Provisionnement automatisé sur AWS Lightsail

Ce guide couvre `scripts/lightsail-deploy.sh` — un script autonome (pas un workflow GitHub Actions) qui automatise la **création de l'infrastructure** sur AWS Lightsail : une instance, une IP statique, et une zone DNS Lightsail pointant un sous-domaine vers cette IP.

**Ce script ne fait que le provisionnement.** Une fois l'instance prête, le déploiement de l'application lui-même se fait avec le script générique déjà documenté dans le [Guide de déploiement simple](GUIDE-DEPLOIEMENT-SIMPLE.md) (`scripts/deploy.sh`) — inchangé, indépendant du fournisseur d'hébergement. Ce chemin Lightsail est une commodité facultative pour les utilisateurs d'AWS Lightsail spécifiquement ; la stack Docker Compose + Caddy elle-même reste générique et fonctionne sur n'importe quel VPS.

## Vue d'ensemble

```
scripts/lightsail-deploy.sh   →  crée l'instance + l'IP statique + le DNS Lightsail
        (délégation NS manuelle chez votre bureau d'enregistrement)
scripts/deploy.sh             →  déploie l'application (Docker Compose + Caddy), sur le serveur
```

## Prérequis

1. **Un compte AWS** avec les permissions Lightsail nécessaires (`lightsail:*` au minimum sur les actions instance/IP statique/domaine).
2. **L'AWS CLI installée et configurée** — identifiants renseignés via `aws configure` ou les variables d'environnement `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY`. Ce dépôt ne stocke **aucune** clé AWS — le script échoue tôt et clairement (`aws sts get-caller-identity`) si aucun identifiant n'est disponible.
3. **`jq`** installé (utilisé pour parser les réponses JSON de l'AWS CLI).
4. **Un nom de domaine déjà enregistré** chez n'importe quel bureau d'enregistrement — ce script ne l'achète pas, il ne fait que créer une zone DNS Lightsail pour ce domaine et y ajouter un enregistrement A.

## Configuration

```bash
cp deploy/lightsail.config.example deploy/lightsail.config
```

Éditez `deploy/lightsail.config` (gitignoré — jamais poussé sur le dépôt) :

| Variable | Rôle |
|---|---|
| `LIGHTSAIL_INSTANCE_NAME` | Nom de l'instance dans votre compte AWS |
| `LIGHTSAIL_REGION` | Région AWS (ex. `eu-west-3` pour Paris) |
| `LIGHTSAIL_AVAILABILITY_ZONE` | Zone de disponibilité dans cette région (ex. `eu-west-3a`) |
| `LIGHTSAIL_BUNDLE_ID` | Gabarit (taille) de l'instance — voir ci-dessous |
| `LIGHTSAIL_BLUEPRINT_ID` | Image de base (`ubuntu_22_04` par défaut) |
| `LIGHTSAIL_KEY_PAIR_NAME` | Nom de la paire de clés SSH |
| `LIGHTSAIL_SSH_PUBLIC_KEY_FILE` | Chemin vers une clé publique existante à importer (optionnel) |
| `LIGHTSAIL_DOMAIN` | Domaine racine déjà enregistré (ex. `example.com`) |
| `LIGHTSAIL_SUBDOMAIN` | Sous-domaine à exposer (ex. `erp` → `erp.example.com`) |

### Choisir le gabarit (`LIGHTSAIL_BUNDLE_ID`)

Cette stack fait tourner sur une seule machine : php-fpm, MySQL, Redis, un worker de file d'attente, un scheduler, le serveur WebSocket Reverb, et Caddy — un plan avec **au moins 4 Go de RAM** est recommandé (`medium_3_0` ou supérieur). Listez les gabarits réellement disponibles dans votre région avec :

```bash
aws lightsail get-bundles --region eu-west-3 --query "bundles[].{id:bundleId,ram:ramSizeInGb,price:price}" --output table
```

Le script lui-même valide `LIGHTSAIL_BUNDLE_ID`/`LIGHTSAIL_BLUEPRINT_ID` contre l'API réelle avant de créer quoi que ce soit, et affiche la liste des valeurs valides en cas d'erreur.

## Lancer le provisionnement

```bash
./scripts/lightsail-deploy.sh
```

Idempotent — peut être relancé sans risque : chaque ressource (instance, paire de clés, IP statique, zone DNS, enregistrement A) est vérifiée avant d'être créée, jamais recréée si elle existe déjà.

Le script :

1. Valide vos identifiants AWS et les valeurs `LIGHTSAIL_BUNDLE_ID`/`LIGHTSAIL_BLUEPRINT_ID` contre l'API réelle.
2. Résout une paire de clés SSH — réutilise `LIGHTSAIL_KEY_PAIR_NAME` si elle existe déjà, importe `LIGHTSAIL_SSH_PUBLIC_KEY_FILE` si fourni, ou télécharge la paire de clés par défaut de la région (`deploy/lightsail-default-key-<région>.pem`, `chmod 600`, gitignorée).
3. Crée l'instance si absente, attend qu'elle soit `running`.
4. Ouvre les ports 22/80/443.
5. Alloue une IP statique et l'attache à l'instance.
6. Crée une zone DNS Lightsail pour `LIGHTSAIL_DOMAIN` si absente.
7. Crée ou met à jour l'enregistrement DNS de type A pour le sous-domaine configuré, pointant vers l'IP statique.
8. Écrit l'IP statique obtenue dans `deploy/lightsail.config` (`LIGHTSAIL_SERVER_IP=`, purement informatif — le script ne relit jamais cette valeur comme une entrée, il la résout toujours depuis l'API Lightsail).

## Étape manuelle obligatoire — délégation DNS

La zone DNS Lightsail créée à l'étape 6 n'est **pas** automatiquement utilisée par Internet tant que le domaine ne délègue pas ses serveurs de noms (NS) vers Lightsail. Cette étape ne peut pas être scriptée — elle se fait chez votre bureau d'enregistrement (OVH, Gandi, Namecheap, etc.).

Récupérez les serveurs de noms Lightsail :

```bash
aws lightsail get-domain --domain-name example.com --query 'domain.domainEntries[?type==`NS`]'
```

Puis, chez votre bureau d'enregistrement, remplacez les serveurs de noms du domaine par ceux affichés ci-dessus.

**Alternative** si vous ne voulez pas déléguer toute la zone DNS à Lightsail (par exemple si vous gérez déjà d'autres enregistrements pour ce domaine ailleurs) : créez manuellement, chez votre fournisseur DNS actuel, un enregistrement A pour le sous-domaine pointant vers l'IP statique affichée par le script — ignorez alors la délégation NS.

Vérifiez la propagation avant de continuer :

```bash
dig +short erp.example.com
# doit afficher l'IP statique renvoyée par scripts/lightsail-deploy.sh
```

## Étape suivante — déployer l'application

Une fois le DNS propagé, connectez-vous à l'instance et lancez le déploiement générique déjà documenté dans le [Guide de déploiement simple](GUIDE-DEPLOIEMENT-SIMPLE.md) :

```bash
ssh -i deploy/lightsail-default-key-<région>.pem ubuntu@<IP-statique>
git clone <url-du-dépôt> life-mdg-erp
cd life-mdg-erp
cp .env.example .env
# éditer .env : APP_DOMAIN=erp.example.com, DB_*, APP_ENV=production, APP_DEBUG=false
./scripts/deploy.sh
```

`scripts/deploy.sh` n'a besoin d'aucune connaissance d'AWS/Lightsail — il attend uniquement que `APP_DOMAIN` pointe déjà vers le serveur, ce que l'étape précédente garantit.

## Redéploiements ultérieurs

`scripts/lightsail-deploy.sh` n'a besoin d'être relancé que si l'instance/IP/DNS doivent changer (nouvelle instance, changement de domaine). Pour un redéploiement applicatif classique après un `git push`, utilisez le chemin déjà documenté : soit `git pull && ./scripts/deploy.sh` directement sur le serveur, soit le déploiement continu automatique via `.github/workflows/deploy.yml` (voir `docs/07-DEPLOIEMENT/README.md`) — les deux sont indépendants d'AWS Lightsail.

## Dépannage

| Symptôme | Cause probable | Diagnostic |
|---|---|---|
| `Identifiants AWS invalides ou absents` | `aws configure` jamais lancé, ou variables d'environnement absentes | `aws sts get-caller-identity` doit réussir avant de relancer le script |
| `Gabarit '...' introuvable dans <région>` | Le nom de gabarit varie selon la région | Le script affiche la liste réelle des gabarits disponibles — copiez l'un d'eux dans `deploy/lightsail.config` |
| L'instance reste bloquée, jamais `running` | Rare — problème côté AWS | `aws lightsail get-instance --instance-name <nom> --region <région>` pour l'état détaillé |
| `dig +short <sous-domaine>` ne renvoie rien après la délégation NS | Propagation DNS pas encore terminée (jusqu'à 24-48h après une délégation NS, contre quelques minutes pour un simple enregistrement A) | Réessayez plus tard ; `aws lightsail get-domain --domain-name <domaine>` pour confirmer que l'enregistrement A est bien dans la zone Lightsail |
| `scripts/deploy.sh` échoue au healthcheck HTTPS malgré un DNS qui répond | Le port 80/443 est bloqué, ou l'instance n'a pas encore fini son propre démarrage réseau | `curl -I http://<sous-domaine>` depuis une machine externe ; vérifiez que l'étape 4 du script (ports publics) s'est bien exécutée via `aws lightsail get-instance-port-states --instance-name <nom> --region <région>` |

## Nettoyage / suppression

Ces commandes sont documentées pour référence — **non automatisées** par ce script (une suppression est une action destructive, hors du périmètre d'un script de provisionnement) :

```bash
aws lightsail delete-instance --instance-name <nom> --region <région>
aws lightsail release-static-ip --static-ip-name <nom>-static-ip --region <région>
aws lightsail delete-domain --domain-name <domaine>   # supprime la zone DNS ET tous ses enregistrements
```

## Coûts

**Une instance Lightsail facture en continu tant qu'elle existe**, indépendamment de son utilisation réelle — le tarif dépend du gabarit choisi (`LIGHTSAIL_BUNDLE_ID`), affiché par `aws lightsail get-bundles`. Une IP statique attachée à une instance en cours d'exécution est gratuite ; une IP statique allouée mais non attachée est facturée. Consultez la [tarification Lightsail officielle](https://aws.amazon.com/lightsail/pricing/) avant de provisionner en production.
